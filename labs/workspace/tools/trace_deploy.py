#!/usr/bin/env python3
"""
TEST-ONLY end-to-end deploy tracer.

Traces lab deploys across every hop with timing:

  Browser(HTTP) -> deploy.php -> RabbitMQ labs_jobs -> worker
       -> labsctl stdout -> RabbitMQ amq.topic logs.<hash> -> "browser"

Measures, with wall-clock + monotonic timestamps:
  t0  API request sent
  t1  API response received (deploy queued)         => API/PHP latency
  t2  worker journal "Received: deploy ..."         => queue->worker latency
  t3  worker journal sudo labsctl spawn             => worker->labsctl latency
  ..  every amq.topic logs.<hash> message arrival   => live stream (browser leg)
  tN  final message / progress 100                  => total e2e

Journal markers come from a LIVE journalctl --follow tail (started before the
API fires, Job Done awaited on an event — no blind sleeps). labsctl.log body
lines are timestamped ([ISO8601Z] ...) so each line can be joined to its
browser arrival: labsctl_write -> MQ -> browser transit stats.

Self-validation: every run asserts its own invariants (subscription order,
positive queue latency, Job Done captured, completion signal, log join non-
empty, ...) and the process exits 1 if any run fails — a corrupted/partial
trace can never be reported as success.

Outputs: human report to stdout + JSON trace + companion .md report under
tools/ (plus trace_runs_*.json/.md with --runs N aggregates: min/p50/p95/max).

Usage:
  python3 trace_deploy.py                 # one full trace (exit 0 = validation passed)
  python3 trace_deploy.py --runs 3        # 3 runs + aggregate percentiles
  python3 trace_deploy.py --cleanup       # full trace + remove lab afterwards
  python3 trace_deploy.py --buffer        # buffering probe only (no deploy)

Exit codes: 0 all validations passed, 1 validation failed, 2 missing deps.
"""
import argparse
import hashlib
import json
import math
import os
import re
import statistics
import subprocess
import sys
import threading
import time
from datetime import datetime, timezone

# ── Config ──────────────────────────────────────────────────────────────────
REPO = "/var/www"
LAB = os.environ.get("TRACE_LAB", "essentials")
SALT = "8b51626f3a468904e8b6f83747f2fcf1"          # User.class.php getLabHash salt
BASE_URL = os.environ.get("TEST_BASE_URL", "http://localhost:8083")
HOST_HEADER = os.environ.get("TEST_HOST_HEADER", "dev.tomweb.in")
WORKER_UNIT = "labs-worker@1.service"
LABCTL_LOG = "/var/log/labsctl/labsctl.log"
TOOLS_DIR = os.path.dirname(os.path.abspath(__file__))
MINT_PHP = os.path.join(TOOLS_DIR, "trace_deploy_mint_session.php")
STALL_MS = 500.0
JOB_DONE_TIMEOUT_S = 15

try:
    import pika
except ImportError:
    print("FATAL: pika not installed", file=sys.stderr)
    sys.exit(2)
try:
    import requests
except ImportError:
    print("FATAL: requests not installed", file=sys.stderr)
    sys.exit(2)


def now_wall():
    return time.time()


def iso(ts):
    return datetime.fromtimestamp(ts, tz=timezone.utc).strftime("%H:%M:%S.%f")[:-3]


def load_amqp():
    with open(os.path.join(REPO, "env.json")) as f:
        cfg = json.load(f)
    return (cfg.get("amqp_host", "127.0.0.1"),
            int(cfg.get("amqp_port", 5672)),
            cfg.get("amqp_user", "admin"),
            cfg.get("amqp_pass", ""))


def vstats(vals):
    if not vals:
        return {"n": 0}
    s = sorted(vals)
    n = len(s)
    return {"n": n,
            "min": s[0],
            "p50": round(statistics.median(s), 1),
            "p95": s[min(n - 1, math.ceil(0.95 * n) - 1)],
            "max": s[-1],
            "mean": round(statistics.fmean(s), 1)}


# ── Browser simulation: subscribe to amq.topic logs.<hash> before API fires ──
class BrowserSim(threading.Thread):
    """Faithful stand-in for mq.js: STOMP /topic/logs.<hash> == amq.topic + rk."""

    def __init__(self, hash_, events, stop_evt):
        super().__init__(daemon=True)
        self.hash = hash_
        self.routing_key = f"logs.{hash_}"
        self.events = events          # shared list of dict events
        self.stop_evt = stop_evt
        self.ready = threading.Event()
        self.connected = False

    def run(self):
        host, port, user, pw = load_amqp()
        creds = pika.PlainCredentials(user, pw)
        params = pika.ConnectionParameters(host=host, port=port, credentials=creds,
                                           heartbeat=0, blocked_connection_timeout=30)
        while not self.stop_evt.is_set():
            try:
                conn = pika.BlockingConnection(params)
                break
            except Exception as e:
                self.events.append({"k": "amqp_err", "t": now_wall(), "msg": str(e)})
                time.sleep(0.5)
        else:
            return
        ch = conn.channel()
        # Exclusive auto-delete queue bound to our routing key (browser "subscription")
        q = ch.queue_declare(queue="", exclusive=True, auto_delete=True)
        ch.queue_bind(queue=q.method.queue, exchange="amq.topic",
                      routing_key=self.routing_key)
        self.connected = True
        self.ready.set()
        self.events.append({"k": "browser_subscribed", "t": now_wall(),
                            "msg": f"subscribed {self.routing_key}"})

        def on_msg(ch_, method, props, body):
            t = now_wall()
            raw = body.decode("utf-8", "replace")
            try:
                data = json.loads(raw)
            except Exception:
                data = {"log": raw}
            self.events.append({"k": "msg", "t": t, "data": data, "raw": raw})
            if self.stop_evt.is_set():
                ch_.basic_cancel(consumer_tag=ctag[0])
                conn.add_callback_threadsafe(conn.close)

        ctag = [None]
        ctag[0] = ch.basic_consume(queue=q.method.queue, on_message_callback=on_msg,
                                   auto_ack=True)
        while not self.stop_evt.is_set():
            try:
                conn.process_data_events(time_limit=0.2)
            except Exception:
                break
        try:
            conn.close()
        except Exception:
            pass


# ── Journal correlation: LIVE tailing (no blind sleeps) ─────────────────────
def _classify_journal_line(line, markers, state):
    m = re.match(r"(\S+)", line)
    if not m:
        return
    try:
        ts = datetime.fromisoformat(m.group(1).replace("Z", "+00:00")).timestamp()
    except Exception:
        return
    hash_ = state["hash"]
    if hash_ and hash_ in line and "Received:" in line and markers["received"] is None:
        markers["received"] = ts
        state["received_ts"] = ts
    if hash_ and hash_ in line and "COMMAND=/usr/local/bin/labsctl" in line \
            and markers["sudo_labsctl"] is None:
        markers["sudo_labsctl"] = ts
    # Job Done carries no hash — take the first one after our job started
    if "Job Done" in line and state["received_ts"] is not None \
            and ts >= state["received_ts"] and markers["job_done"] is None:
        markers["job_done"] = ts
        state["evt"].set()


class JournalTail(threading.Thread):
    """journalctl -u <unit> --follow, classified as it streams.

    Started BEFORE the API fires so nothing is missed; the caller waits on
    job_done_evt (instead of sleeping) once the deploy completes.
    """

    def __init__(self, unit, since_iso, hash_):
        super().__init__(daemon=True)
        self.since_iso = since_iso
        self.unit = unit
        self.markers = {"received": None, "sudo_labsctl": None, "job_done": None}
        self.state = {"hash": hash_, "received_ts": None,
                      "evt": threading.Event()}
        self.job_done_evt = self.state["evt"]
        self.started_evt = threading.Event()
        self.proc = None
        self.err = None

    def run(self):
        try:
            self.proc = subprocess.Popen(
                ["journalctl", "-u", self.unit, "-f", "-o", "short-iso-precise",
                 "--no-pager", "--since", self.since_iso],
                stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
                text=True, bufsize=1)
            self.started_evt.set()
            for line in self.proc.stdout:
                _classify_journal_line(line, self.markers, self.state)
        except Exception as e:
            self.err = str(e)
            self.started_evt.set()

    def stop(self):
        if self.proc and self.proc.poll() is None:
            try:
                self.proc.terminate()
                self.proc.wait(timeout=3)
            except Exception:
                pass


def journal_markers(since_iso, hash_):
    """Fallback: one-shot journal read (used only if the live tail failed)."""
    try:
        out = subprocess.run(
            ["journalctl", "-u", WORKER_UNIT, "--since", since_iso,
             "-o", "short-iso-precise", "--no-pager"],
            capture_output=True, text=True, timeout=15).stdout
    except Exception as e:
        return {"err": str(e)}
    markers = {"received": None, "sudo_labsctl": None, "job_done": None}
    state = {"hash": hash_, "received_ts": None, "evt": threading.Event()}
    for line in out.splitlines():
        _classify_journal_line(line, markers, state)
    return markers


# ── labsctl.log segment + join to browser arrivals ──────────────────────────
def parse_labsctl_log(hash_, since_ts):
    """This run's body lines from labsctl.log (only [ISO8601Z]-prefixed lines).

    Segment = lines between the `=== ... | <hash> | ... ===` header for our
    hash and the next header. Legacy unprefixed lines are skipped.
    """
    try:
        with open(LABCTL_LOG) as f:
            raw_lines = f.readlines()
    except OSError as e:
        return {"err": str(e), "records": []}
    records = []
    in_seg = False
    for line in raw_lines:
        m = re.match(r"^\[([^\]]+)\] (.*)$", line.rstrip("\n"))
        if not m:
            continue
        ts_s, msg = m.group(1), m.group(2)
        try:
            ts = datetime.fromisoformat(ts_s.replace("Z", "+00:00")).timestamp()
        except ValueError:
            continue
        if msg.startswith("=== "):
            body = msg[4:].rsplit(" ===", 1)[0]
            parts = [p.strip() for p in body.split("|")]
            # works for both writers: "=== TIME | action | hash | ..." (worker)
            # and "=== TIME IST | Action: x | Hash: h ===" (PHP DeployLog)
            seg_hash = next((p for p in parts if re.fullmatch(r"[0-9a-f]{32}", p)), "")
            in_seg = (seg_hash == hash_)
            continue
        if in_seg and ts >= since_ts:
            records.append((ts, msg))
    return {"err": None, "records": records}


def join_labsctl_to_browser(records, msgs):
    """labsctl.log write time -> same text's browser arrival (file is written
    AFTER emit(), so allow a small negative skew)."""
    by_text = {}
    for m in msgs:
        text = (m.get("data") or {}).get("log")
        if isinstance(text, str):
            by_text.setdefault(text.strip(), []).append(m["t"])
    cursor = {}
    transits = []
    matched = 0
    for ts, msg in records:
        text = msg.strip()
        if not text:
            continue
        arr = by_text.get(text)
        if not arr:
            continue
        i = cursor.get(text, 0)
        while i < len(arr) and arr[i] < ts - 0.5:
            i += 1
        if i < len(arr) and arr[i] <= ts + 5.0:
            cursor[text] = i + 1
            matched += 1
            transits.append(round((arr[i] - ts) * 1000, 1))
        else:
            cursor[text] = i
    return matched, transits


# ── Phases (progress steps) and stalls ──────────────────────────────────────
def compute_phases(messages):
    prog = []
    last_pct = -1
    for m in messages:
        d = m.get("data") or {}
        p = d.get("progress")
        if isinstance(p, int) and p > last_pct:
            prog.append((m["t"], p, str(d.get("label", "")).strip()))
            last_pct = p
    phases = []
    for (t1, p1, l1), (t2, p2, l2) in zip(prog, prog[1:]):
        ms = round((t2 - t1) * 1000, 1)
        phases.append({"from_pct": p1, "from_label": l1,
                       "to_pct": p2, "to_label": l2, "ms": ms})
    span_ms = round((prog[-1][0] - prog[0][0]) * 1000, 1) if len(prog) > 1 else 0.0
    for ph in phases:
        ph["share_pct"] = round(100.0 * ph["ms"] / span_ms, 1) if span_ms > 0 else None
    return phases, span_ms


def compute_stalls(messages, threshold_ms):
    stalls = []
    for a, b in zip(messages, messages[1:]):
        gap = round((b["t"] - a["t"]) * 1000, 1)
        if gap > threshold_ms:
            stalls.append({"after": a["raw"][:120], "next": b["raw"][:120],
                           "gap_ms": gap, "at": iso(a["t"])})
    stalls.sort(key=lambda s: s["gap_ms"], reverse=True)
    return stalls


# ── Self-validation (pure function — doctored traces must FAIL) ─────────────
def validate_trace(trace):
    errors = []
    h = trace.get("hops") or {}
    d = trace.get("derived_ms") or {}
    msgs = trace.get("messages") or []

    def need(cond, msg):
        if not cond:
            errors.append(msg)

    need(h.get("subscribed_before_api") is True,
         "browser was not subscribed before the API fired")
    need((d.get("api_call") or 0) > 0, "api_call missing or <= 0")
    need(h.get("worker_received") is not None,
         "worker 'Received:' marker missing (journal tail failed)")
    need((d.get("queue_to_worker") or 0) > 0,
         "queue_to_worker missing or <= 0 (journal correlation failed)")
    need(h.get("worker_sudo_labsctl") is not None,
         "worker sudo labsctl marker missing")
    if h.get("worker_sudo_labsctl") is not None:
        need(h["worker_sudo_labsctl"] >= (h.get("worker_received") or 0),
             "sudo labsctl precedes job receipt")
    need(h.get("deploy_complete") is not None,
         "deploy never reached a completion signal")
    need(h.get("worker_job_done") is not None,
         f"Job Done missing after {JOB_DONE_TIMEOUT_S}s wait (journal tail failed)")
    if h.get("worker_job_done") is not None and h.get("deploy_complete"):
        need(h["worker_job_done"] >= h["deploy_complete"] - 0.05,
             "Job Done precedes deploy_complete (bad correlation)")
    need(len(msgs) > 0, "no messages streamed to browser")
    if msgs:
        need(msgs[-1]["t"] >= msgs[0]["t"], "message timestamps not monotonic")
        need(h.get("first_msg") and h.get("api_response")
             and h["first_msg"] > h["api_response"],
             "first browser message does not follow the API response")
        has_done = any(isinstance((m.get("data") or {}).get("progress"), int)
                       and m["data"]["progress"] >= 100 for m in msgs)
        has_reload = any("[*] reload" in (m.get("raw") or "") for m in msgs)
        need(has_done or has_reload,
             "no completion signal (neither progress=100 nor reload)")
    need((d.get("total_e2e") or 0) > 0, "total_e2e missing or <= 0")
    transit = trace.get("labsctl_transit") or {}
    need((transit.get("n") or 0) > 0,
         "labsctl.log -> browser join empty (timestamps missing in labsctl.log?)")
    return (not errors, errors)


# ── Mongo cleanup (same database_file resolution as the worker) ─────────────
def mongo_cleanup(hash_, email):
    try:
        with open(os.path.join(REPO, "env.json")) as f:
            cfg = json.load(f)
        db_file = cfg.get("database_file", "")
        if not db_file or "://" not in db_file:
            return "no database_file in env.json"
        m = re.match(r"mongodb://([^:]+):([^@]+)@([^:/]+):(\d+)/", db_file)
        if not m:
            return "could not parse database_file"
        import pymongo
        user, pw, host, port = m.group(1), m.group(2), m.group(3), m.group(4)
        uri = f"mongodb://{user}:{pw}@{host}:{port}/?authSource=admin"
        cli = pymongo.MongoClient(uri, serverSelectionTimeoutMS=5000)
        n1 = cli["tom_labs_db"]["machine_labs"].delete_one({"instance_hash": hash_})
        n2 = cli["tom_labs_instances_db"]["instances"].delete_one({"instance_hash": hash_})
        n3 = cli["tom_labs_db"]["users"].delete_many({"email": email})
        return (f"machine_labs={n1.deleted_count} instances={n2.deleted_count} "
                f"users={n3.deleted_count}")
    except Exception as e:
        return f"cleanup error: {e}"


# ── Buffering probe ─────────────────────────────────────────────────────────
def buffer_probe():
    """Empirically show raw print (no flush) vs print(flush=True) over a pipe."""
    results = {}
    for label, flush in (("raw_print_no_flush", False), ("print_flush_True", True)):
        child = (
            "import sys,time\n"
            f"for i in range(4):\n"
            f"    print('line %d' % i, flush={flush})\n"
            "    time.sleep(0.25)\n"
        )
        t0 = time.monotonic()
        p = subprocess.Popen([sys.executable, "-c", child],
                             stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
        arrivals = []
        for line in p.stdout:
            arrivals.append((time.monotonic() - t0, line.strip()))
        p.wait()
        results[label] = [{"t": round(t, 3), "line": ln} for t, ln in arrivals]
    return results


def print_buffer_probe(buf):
    print("\n" + "-" * 78)
    print("BUFFERING PROBE (why self.log(..., flush=True) matters)")
    print("-" * 78)
    for label, rows in buf.items():
        print(f"  {label}:")
        for r in rows:
            print(f"     t=+{r['t']:.3f}s  {r['line']}")


# ── Session mint (via PHP bootstrap) ────────────────────────────────────────
def mint_session(email):
    out = subprocess.run(["php", MINT_PHP, email],
                         capture_output=True, text=True, timeout=30)
    if out.returncode != 0:
        raise RuntimeError(f"mint_session failed: {out.stderr.strip()}")
    return json.loads(out.stdout.strip().splitlines()[-1])


def failed_trace(email, err):
    return {"meta": {"email": email, "lab": LAB, "hash": "", "run_id": "",
                     "generated_at": datetime.now(timezone.utc).isoformat()},
            "hops": {}, "derived_ms": {}, "messages": [],
            "validation": {"passed": False, "errors": [err]}}


# ── Full trace ──────────────────────────────────────────────────────────────
def full_trace(run_idx, do_cleanup, buffer):
    email = f"tracer_{int(time.time())}_{run_idx}@example.com"
    sess = mint_session(email)
    cookie = sess["cookie"]

    # Pre-compute lab hash so we subscribe BEFORE firing the API (no race)
    lab_hash = hashlib.md5((email + LAB + SALT).encode()).hexdigest()

    events = []
    stop_evt = threading.Event()
    browser = BrowserSim(lab_hash, events, stop_evt)
    browser.start()
    browser.ready.wait(timeout=10)
    if not browser.connected:
        return failed_trace(email, "could not subscribe to amq.topic")

    journal_since = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    tail = JournalTail(WORKER_UNIT, journal_since, lab_hash)
    tail.start()
    tail.started_evt.wait(timeout=5)

    t0 = now_wall()
    events.append({"k": "t0_api_sent", "t": t0, "msg": "POST /api/labs/deploy"})

    # Fire the API exactly like the browser
    resp = requests.post(
        f"{BASE_URL}/api/labs/deploy",
        data={"lab": LAB},
        headers={"Host": HOST_HEADER, "Cookie": cookie},
        timeout=60,
    )
    t1 = now_wall()
    body = resp.json() if resp.text else {}
    api_latency = t1 - t0
    events.append({"k": "t1_api_resp", "t": t1,
                   "msg": f"HTTP {resp.status_code} {json.dumps(body)}"})

    run_id = body.get("run_id", "")
    resp_hash = body.get("hash", "")
    print(f"  API responded in {api_latency*1000:.0f} ms  run_id={run_id}")

    if resp_hash and resp_hash != lab_hash:
        print(f"  [warn] hash mismatch: computed={lab_hash} api={resp_hash}")

    # Stream ends at "[*] reload" (last emitted line); progress 100 recorded too.
    # Quiet-window fallback: if reload is ever lost, stop 2s after the last msg.
    deadline = time.monotonic() + 120
    complete_at = None
    reload_at = None
    last_msg_t = None
    while time.monotonic() < deadline:
        for e in events:
            if e["k"] != "msg":
                continue
            d = e.get("data", {})
            last_msg_t = max(last_msg_t or 0, e["t"])
            if isinstance(d.get("progress"), int) and d["progress"] >= 100 \
                    and complete_at is None:
                complete_at = e["t"]
            if "[*] reload" in (e.get("raw") or ""):
                reload_at = e["t"]
        if reload_at:
            break
        if complete_at and last_msg_t and time.time() - last_msg_t > 2.0:
            break
        time.sleep(0.2)

    # reload is the last message — short drain so no arrival is lost
    if reload_at:
        time.sleep(0.3)
    stop_evt.set()
    browser.join(timeout=3)

    # Job Done lands ~0.5s after the stream ends — wait on the event, no sleep
    tail.job_done_evt.wait(timeout=JOB_DONE_TIMEOUT_S)
    tail.stop()
    tail.join(timeout=3)
    markers = dict(tail.markers)
    if markers.get("received") is None and markers.get("job_done") is None:
        markers = journal_markers(journal_since, lab_hash)

    if complete_at is None and reload_at is not None:
        complete_at = reload_at

    # ── Build report ────────────────────────────────────────────────────────
    msgs = [e for e in events if e["k"] == "msg"]
    sub = next((e for e in events if e["k"] == "browser_subscribed"), None)
    log_seg = parse_labsctl_log(lab_hash, since_ts=t0)
    matched, transits = join_labsctl_to_browser(log_seg["records"], msgs)
    phases, phase_span_ms = compute_phases(msgs)
    stalls = compute_stalls(msgs, STALL_MS)
    transit_stats = vstats(transits)
    transit_stats["matched_lines"] = matched
    transit_stats["log_lines"] = len(log_seg["records"])
    transit_stats["log_err"] = log_seg["err"]

    trace = {
        "meta": {
            "email": email, "lab": LAB, "hash": lab_hash, "run_id": run_id,
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "run_idx": run_idx,
        },
        "hops": {
            "api_request_sent": t0,
            "api_response": t1,
            "api_latency_ms": round(api_latency * 1000, 1),
            "browser_subscribed_at": sub["t"] if sub else None,
            "subscribed_before_api": bool(sub and sub["t"] < t0),
            "worker_received": markers.get("received"),
            "worker_sudo_labsctl": markers.get("sudo_labsctl"),
            "worker_job_done": markers.get("job_done"),
            "first_msg": msgs[0]["t"] if msgs else None,
            "last_msg": msgs[-1]["t"] if msgs else None,
            "deploy_complete": complete_at,
            "stream_reloaded_at": reload_at,
        },
        "derived_ms": {},
        "messages": [{"t": e["t"], "iso": iso(e["t"]), "raw": e["raw"],
                      "data": e.get("data")} for e in msgs],
        "phases": phases,
        "phase_span_ms": phase_span_ms,
        "stalls": stalls,
        "labsctl_transit": transit_stats,
        "buffer_probe": buffer,
    }

    h = trace["hops"]
    d = trace["derived_ms"]
    d["api_call"] = round((h["api_response"] - h["api_request_sent"]) * 1000, 1)
    if h["worker_received"] and h["api_response"]:
        d["queue_to_worker"] = round((h["worker_received"] - h["api_response"]) * 1000, 1)
    if h["worker_sudo_labsctl"] and h["worker_received"]:
        d["worker_to_labsctl"] = round(
            (h["worker_sudo_labsctl"] - h["worker_received"]) * 1000, 1)
    if h["first_msg"] and h["api_response"]:
        d["api_to_first_browser_msg"] = round(
            (h["first_msg"] - h["api_response"]) * 1000, 1)
    if h["last_msg"] and h["first_msg"]:
        d["stream_span_first_to_last"] = round(
            (h["last_msg"] - h["first_msg"]) * 1000, 1)
    if h["deploy_complete"] and h["api_request_sent"]:
        d["total_e2e"] = round((h["deploy_complete"] - h["api_request_sent"]) * 1000, 1)
    if h["worker_job_done"] and h["deploy_complete"]:
        d["complete_to_job_done"] = round(
            (h["worker_job_done"] - h["deploy_complete"]) * 1000, 1)

    gaps = [round((b["t"] - a["t"]) * 1000, 1) for a, b in zip(msgs, msgs[1:])]
    trace["msg_gaps_ms"] = gaps

    passed, errors = validate_trace(trace)
    trace["validation"] = {"passed": passed, "errors": errors}

    # ── Print human table ───────────────────────────────────────────────────
    print("\n" + "=" * 78)
    print("DEPLOY TRACE  (browser -> deploy.php -> queue -> worker -> labsctl -> MQ -> browser)")
    print("=" * 78)
    print(f"hash={lab_hash}  run_id={run_id}")
    print(f"browser subscribed BEFORE api fire: {h['subscribed_before_api']}")
    print("-" * 78)
    print(f"{'STAGE':<38}{'ISO (UTC)':<16}{'Δprev ms':>10}")
    print("-" * 78)
    stages = [
        ("t0 API request sent", h["api_request_sent"], None),
        ("t1 API response (queued)", h["api_response"], h["api_request_sent"]),
        ("t2 worker received job", h["worker_received"], h["api_response"]),
        ("t3 worker -> sudo labsctl", h["worker_sudo_labsctl"], h["worker_received"]),
        ("t4 first msg -> browser", h["first_msg"], h["api_response"]),
        ("t5 last msg -> browser", h["last_msg"], h["first_msg"]),
        ("t6 deploy complete (100%)", h["deploy_complete"], h["last_msg"]),
        ("t7 worker job done (journal)", h["worker_job_done"], h["deploy_complete"]),
    ]
    for name, ts, prev in stages:
        if ts is None:
            print(f"{name:<38}{'—':<16}{'n/a':>10}")
            continue
        delta = f"{(ts-prev)*1000:.0f}" if prev else "—"
        print(f"{name:<38}{iso(ts):<16}{delta:>10}")
    print("-" * 78)
    print("KEY LATENCIES (ms):")
    for k, v in d.items():
        print(f"  {k:<32}{v}")
    print(f"\n  messages streamed to browser: {len(msgs)}")
    if gaps:
        print(f"  msg gaps ms: min={min(gaps)} med={sorted(gaps)[len(gaps)//2]} max={max(gaps)}")

    if phases:
        print("\nPHASE BREAKDOWN (progress steps, slowest first):")
        print(f"  {'PHASE':<58}{'ms':>8}{'share':>7}")
        for ph in sorted(phases, key=lambda p: p["ms"], reverse=True)[:12]:
            frm = f"{ph['from_pct']}% {ph['from_label']}".strip()
            to = f"{ph['to_pct']}% {ph['to_label']}".strip()
            share = f"{ph['share_pct']}%" if ph["share_pct"] is not None else "—"
            print(f"  {frm + ' -> ' + to:<58}{ph['ms']:>8}{share:>7}")
        print(f"  (progress stream span: {phase_span_ms} ms)")

    if stalls:
        print(f"\nSTALLS > {STALL_MS:.0f} ms between streamed messages:")
        for s in stalls[:10]:
            print(f"  +{s['gap_ms']:>7} ms  after: {s['after']}")
            print(f"  {'':>10}  next:  {s['next']}")
    else:
        print(f"\nSTALLS > {STALL_MS:.0f} ms: none")

    print(f"\nLABSCTL.LOG -> BROWSER transit: {transit_stats['matched_lines']}"
          f"/{transit_stats['log_lines']} lines joined"
          + (f"  (log read error: {transit_stats['log_err']})"
             if transit_stats["log_err"] else ""))
    if transit_stats["n"]:
        print(f"  ms: p50={transit_stats['p50']} p95={transit_stats['p95']} "
              f"max={transit_stats['max']} min={transit_stats['min']}")

    print_buffer_probe(buffer)

    print("\n" + "-" * 78)
    if passed:
        print("VALIDATION: PASS")
    else:
        print("VALIDATION: FAIL")
        for e in errors:
            print(f"  ✗ {e}")

    # ── Write trace + companion .md ─────────────────────────────────────────
    base = f"trace_{int(time.time())}_{lab_hash[:8]}"
    out_path = os.path.join(TOOLS_DIR, base + ".json")
    md_path = os.path.join(TOOLS_DIR, base + ".md")
    with open(out_path, "w") as f:
        json.dump(trace, f, indent=2, default=str)
    with open(md_path, "w") as f:
        f.write(md_report(trace))
    print(f"\n  trace written: {out_path}")
    print(f"  report written: {md_path}")

    # ── Optional cleanup ────────────────────────────────────────────────────
    if do_cleanup:
        print("\n[cleanup] removing lab + records...")
        subprocess.run(["sudo", "labsctl", "lab", "remove", f"--hash={lab_hash}"],
                       capture_output=True, text=True)
        print(f"[cleanup] mongo: {mongo_cleanup(lab_hash, email)}")

    return trace


# ── Summaries / aggregates for --runs N ─────────────────────────────────────
SUMMARY_KEYS = ["api_call", "queue_to_worker", "worker_to_labsctl",
                "api_to_first_browser_msg", "stream_span_first_to_last",
                "total_e2e", "complete_to_job_done"]


def summarize(trace):
    h = trace.get("hops") or {}
    d = trace.get("derived_ms") or {}
    v = trace.get("validation") or {}
    s = {"run_idx": (trace.get("meta") or {}).get("run_idx"),
         "hash": (trace.get("meta") or {}).get("hash"),
         "run_id": (trace.get("meta") or {}).get("run_id"),
         "msg_count": len(trace.get("messages") or []),
         "stall_count": len(trace.get("stalls") or []),
         "transit_p50": (trace.get("labsctl_transit") or {}).get("p50"),
         "validation_passed": bool(v.get("passed"))}
    for k in SUMMARY_KEYS:
        s[k] = d.get(k)
    if not s["validation_passed"]:
        s["errors"] = v.get("errors") or []
    return s


def aggregate(summaries):
    agg = {}
    for key in SUMMARY_KEYS + ["msg_count", "stall_count"]:
        vals = [s[key] for s in summaries if isinstance(s.get(key), (int, float))]
        if vals:
            agg[key] = vstats(vals)
    passed = sum(1 for s in summaries if s["validation_passed"])
    agg["_validation"] = {"runs": len(summaries), "passed": passed,
                          "failed": len(summaries) - passed}
    return agg


def print_aggregate(summaries, agg):
    print("\n" + "=" * 78)
    print(f"AGGREGATE over {len(summaries)} runs")
    print("=" * 78)
    hdr = ["metric", "min", "p50", "p95", "max", "mean"]
    print(f"  {hdr[0]:<30}{hdr[1]:>9}{hdr[2]:>9}{hdr[3]:>9}{hdr[4]:>9}{hdr[5]:>9}")
    for key in SUMMARY_KEYS + ["msg_count", "stall_count"]:
        st = agg.get(key)
        if not st:
            continue
        print(f"  {key:<30}{st['min']:>9}{st['p50']:>9}{st['p95']:>9}"
              f"{st['max']:>9}{st['mean']:>9}")
    v = agg["_validation"]
    print(f"\n  validation: {v['passed']}/{v['runs']} runs passed"
          + (f", {v['failed']} FAILED" if v["failed"] else ""))


def runs_report(summaries, agg, buffer):
    ts = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")
    lines = [f"# Deploy trace — {len(summaries)} runs", "",
             f"- generated: {ts}", ""]
    lines.append("## Per-run summary")
    cols = ["run", "validation", "api_call", "queue_to_worker",
            "api_to_first_msg", "total_e2e", "msg_count", "stalls", "transit_p50"]
    lines.append("| " + " | ".join(cols) + " |")
    lines.append("|" + "|".join(["---"] * len(cols)) + "|")
    for s in summaries:
        vals = [s["run_idx"], "PASS" if s["validation_passed"] else "FAIL",
                s.get("api_call"), s.get("queue_to_worker"),
                s.get("api_to_first_browser_msg"), s.get("total_e2e"),
                s.get("msg_count"), s.get("stall_count"), s.get("transit_p50")]
        lines.append("| " + " | ".join("—" if v is None else str(v) for v in vals) + " |")
    lines += ["", "## Aggregates (ms unless unitless)", "",
              "| metric | min | p50 | p95 | max | mean |",
              "|---|---|---|---|---|---|"]
    for key in SUMMARY_KEYS + ["msg_count", "stall_count"]:
        st = agg.get(key)
        if st:
            lines.append(f"| {key} | {st['min']} | {st['p50']} | {st['p95']} "
                         f"| {st['max']} | {st['mean']} |")
    v = agg["_validation"]
    lines += ["", f"**Validation: {v['passed']}/{v['runs']} runs passed"
              + (f" — {v['failed']} FAILED**" if v["failed"] else "**"), ""]
    fails = [s for s in summaries if not s["validation_passed"]]
    for s in fails:
        lines.append(f"- run {s['run_idx']} FAILED: " + "; ".join(s.get("errors") or []))
    lines += ["", "## Buffer probe (run-independent)", ""]
    for label, rows in (buffer or {}).items():
        lines.append(f"- `{label}`: " + ", ".join(f"+{r['t']}s" for r in rows))
    lines.append("")
    return "\n".join(lines)


# ── Per-run .md report ──────────────────────────────────────────────────────
def md_report(trace):
    m = trace.get("meta") or {}
    h = trace.get("hops") or {}
    d = trace.get("derived_ms") or {}
    v = trace.get("validation") or {}
    msgs = trace.get("messages") or []
    phases = trace.get("phases") or []
    stalls = trace.get("stalls") or []
    transit = trace.get("labsctl_transit") or {}
    lines = [f"# Deploy trace — {m.get('lab', '?')} ({(m.get('hash') or '?')[:8]})", ""]
    lines.append(f"- generated: {m.get('generated_at', '?')}")
    lines.append(f"- run: idx={m.get('run_idx')} id=`{m.get('run_id', '')}` "
                 f"user=`{m.get('email', '')}`")
    lines.append(f"- validation: **{'PASS' if v.get('passed') else 'FAIL'}**")
    for e in v.get("errors") or []:
        lines.append(f"  - ✗ {e}")
    lines += ["", "## Stage timeline", "",
              "| stage | ISO (UTC) | Δ prev ms |",
              "|---|---|---|"]
    stages = [
        ("t0 API request sent", "api_request_sent", None),
        ("t1 API response (queued)", "api_response", "api_request_sent"),
        ("t2 worker received job", "worker_received", "api_response"),
        ("t3 worker -> sudo labsctl", "worker_sudo_labsctl", "worker_received"),
        ("t4 first msg -> browser", "first_msg", "api_response"),
        ("t5 last msg -> browser", "last_msg", "first_msg"),
        ("t6 deploy complete (100%)", "deploy_complete", "last_msg"),
        ("t7 worker job done (journal)", "worker_job_done", "deploy_complete"),
    ]
    for name, key, prev_key in stages:
        ts = h.get(key)
        if ts is None:
            lines.append(f"| {name} | — | n/a |")
            continue
        delta = f"{(ts - h[prev_key])*1000:.0f}" if prev_key and h.get(prev_key) else "—"
        lines.append(f"| {name} | {iso(ts)} | {delta} |")
    lines += ["", "## Key latencies (ms)", "",
              "| metric | ms |", "|---|---|"]
    for k, v_ in d.items():
        lines.append(f"| {k} | {v_} |")
    lines += ["", f"Messages streamed: **{len(msgs)}**"]
    if phases:
        lines += ["", "## Phase breakdown (slowest first)", "",
                  "| phase | ms | share |", "|---|---|---|"]
        for ph in sorted(phases, key=lambda p: p["ms"], reverse=True)[:15]:
            frm = f"{ph['from_pct']}% {ph['from_label']}".strip()
            to = f"{ph['to_pct']}% {ph['to_label']}".strip()
            share = f"{ph['share_pct']}%" if ph.get("share_pct") is not None else "—"
            lines.append(f"| {frm} → {to} | {ph['ms']} | {share} |")
    if stalls:
        lines += ["", f"## Stalls > {STALL_MS:.0f} ms", "",
                  "| gap ms | after | next |", "|---|---|---|"]
        for s in stalls[:15]:
            lines.append(f"| {s['gap_ms']} | {s['after']} | {s['next']} |")
    else:
        lines += ["", f"No stalls > {STALL_MS:.0f} ms."]
    lines += ["", "## labsctl.log → browser transit", "",
              f"- joined {transit.get('matched_lines', 0)}/"
              f"{transit.get('log_lines', 0)} lines"]
    if transit.get("n"):
        lines.append(f"- ms: p50={transit.get('p50')} p95={transit.get('p95')} "
                     f"max={transit.get('max')} min={transit.get('min')}")
    if transit.get("log_err"):
        lines.append(f"- log read error: {transit['log_err']}")
    buf = trace.get("buffer_probe") or {}
    if buf:
        lines += ["", "## Buffer probe", ""]
        for label, rows in buf.items():
            lines.append(f"- `{label}`: " + ", ".join(f"+{r['t']}s" for r in rows))
    lines.append("")
    return "\n".join(lines)


def main():
    global STALL_MS, LAB
    p = argparse.ArgumentParser(
        description="TEST-ONLY end-to-end deploy tracer "
                    "(exit 0 = all runs validated)")
    p.add_argument("--buffer", action="store_true",
                   help="buffering probe only (no deploy)")
    p.add_argument("--cleanup", action="store_true",
                   help="remove the lab + trace records after each run")
    p.add_argument("--runs", type=int, default=1,
                   help="number of deploy runs + aggregate stats (default 1)")
    p.add_argument("--stall-ms", type=float, default=STALL_MS,
                   help=f"stall threshold in ms (default {STALL_MS:.0f})")
    p.add_argument("--lab", default=LAB, help=f"lab to deploy (default {LAB})")
    args = p.parse_args()
    STALL_MS = args.stall_ms
    LAB = args.lab

    if args.buffer:
        print("BUFFERING PROBE")
        print("-" * 60)
        buf = buffer_probe()
        for label, rows in buf.items():
            print(f"{label}:")
            for r in rows:
                print(f"   t=+{r['t']:.3f}s  {r['line']}")
        out = os.path.join(TOOLS_DIR, f"buffer_probe_{int(time.time())}.json")
        with open(out, "w") as f:
            json.dump(buf, f, indent=2)
        print(f"\nwritten: {out}")
        return

    if args.runs < 1:
        p.error("--runs must be >= 1")

    print("BUFFERING PROBE (run-independent, included in reports)")
    buf = buffer_probe()
    print_buffer_probe(buf)

    summaries = []
    traces = []
    for i in range(1, args.runs + 1):
        print(f"\n>>> RUN {i}/{args.runs}")
        try:
            tr = full_trace(i, args.cleanup, buf)
        except Exception as e:
            print(f"RUN {i} EXCEPTION: {e}")
            tr = failed_trace(f"run_{i}", f"exception: {e}")
        traces.append(tr)
        summaries.append(summarize(tr))

    failed = [s for s in summaries if not s["validation_passed"]]
    if args.runs > 1:
        agg = aggregate(summaries)
        print_aggregate(summaries, agg)
        base = os.path.join(TOOLS_DIR, f"trace_runs_{int(time.time())}")
        with open(base + ".json", "w") as f:
            json.dump({"summaries": summaries, "aggregate": agg,
                       "buffer_probe": buf}, f, indent=2, default=str)
        with open(base + ".md", "w") as f:
            f.write(runs_report(summaries, agg, buf))
        print(f"\n  runs report: {base}.json / {base}.md")

    if failed:
        print(f"\nVALIDATION: {len(failed)}/{len(summaries)} RUNS FAILED")
        sys.exit(1)
    print(f"\nVALIDATION: all {len(summaries)} run(s) passed")
    sys.exit(0)


if __name__ == "__main__":
    main()
