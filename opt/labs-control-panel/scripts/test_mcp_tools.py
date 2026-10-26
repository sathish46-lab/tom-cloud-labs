#!/usr/bin/env python3
"""
MCP Tool Matrix Verification

Calls every registered MCP tool with mode-appropriate arguments and verifies:
  - transport: HTTP 200, no crashes / no pydantic explosions / no tracebacks
  - shape: success results carry structuredContent as a real dict + JSON text
  - guard: lab-scoped mutators reject a fake lab with the self-healing
           "Available labs: ..." ownership message
  - schema: global mutators are rejected by argument validation with NO side effects

Modes (assigned per tool):
  read    -> happy path (real instance_hash for lab-scoped reads)
  guard   -> lab-scoped tool called with a fake lab name
  schema  -> global tool called with a wrong-typed argument
  loose   -> fallback when no argument can force a safe rejection

Env:
  MCP_BASE_URL      (default http://localhost:8083)
  MCP_HOST_HEADER   (default dev.tomweb.in)
  MCP_TOKEN         REQUIRED Bearer access token
"""
import json
import os
import re
import sys

import httpx

BASE = os.environ.get("MCP_BASE_URL", "http://localhost:8083").rstrip("/")
HOST = os.environ.get("MCP_HOST_HEADER", "dev.tomweb.in")
TOKEN = os.environ.get("MCP_TOKEN", "")

FAKE_LAB = "__harness_missing_lab__"

# Tools whose happy path must run even though the name matches mutator keywords
READ_EXPLICIT = {"deploy_progress"}

# Lab-scoped tools without a mutator keyword (still called with a fake lab)
GUARD_EXPLICIT = {"domain_ssl", "domain_error_pages"}

MUTATOR_KW = re.compile(
    r"^(add|create|delete|remove|write|edit|upload|move|enable|disable|stop|start|"
    r"pause|resume|terminate|renew|deploy|ensure|run|apply|register|claim|verify|"
    r"test|wait|workspace_pin|workspace_unpin)"
)

# Extra required (non-lab) arguments for read-mode tools
READ_EXTRA = {
    "download_lab_file": {"path": "/etc/hostname"},
    "read_lab_file": {"path": "/etc/hostname"},
    "list_lab_files": {"path": "/etc"},
    "lab_file_tree": {"path": "/etc"},
    "glob_lab_files": {"pattern": "*.conf"},
    "grep_lab_files": {"pattern": "root", "path": "/etc"},
}

# Safety-gate args that must be satisfied to reach the ownership check
CONFIRM_TOOLS = {
    "terminate_lab": {"confirm": True},
}


def fill_value(spec):
    t = spec.get("type")
    if t == "integer" or t == "number":
        return 1
    if t == "boolean":
        return True
    if t == "array":
        return []
    if t == "object":
        return {}
    return "probe"


def parse_result(resp: httpx.Response):
    """Return (http_status, rpc_error, result_dict, raw_text)."""
    text = resp.text
    m = re.findall(r"^data: (.*)$", text, re.M)
    payload = json.loads(m[-1] if m else text) if text.strip() else {}
    return resp.status_code, payload.get("error"), payload.get("result"), text


def result_text(result, rpc_error, raw):
    if rpc_error:
        return json.dumps(rpc_error)
    if result and result.get("content"):
        return result["content"][0].get("text", "")
    return raw[:300]


def main() -> int:
    if not TOKEN:
        print("MCP_TOKEN is required")
        return 2

    headers = {
        "Host": HOST,
        "Content-Type": "application/json",
        "Accept": "application/json, text/event-stream",
        "Authorization": f"Bearer {TOKEN}",
    }
    client = httpx.Client(base_url=BASE, headers=headers, timeout=60)

    # ── initialize ──
    r = client.post("/mcp", json={
        "jsonrpc": "2.0", "id": 1, "method": "initialize",
        "params": {"protocolVersion": "2025-06-18", "capabilities": {},
                   "clientInfo": {"name": "tool-matrix-harness", "version": "1"}},
    })
    if r.status_code != 200:
        print(f"initialize failed: HTTP {r.status_code}\n{r.text[:400]}")
        return 2
    sid = r.headers.get("mcp-session-id")
    sess = {"Mcp-Session-Id": sid} if sid else {}
    client.post("/mcp", json={"jsonrpc": "2.0", "method": "notifications/initialized"},
                headers=sess)

    # ── tools/list ──
    r = client.post("/mcp", json={"jsonrpc": "2.0", "id": 2, "method": "tools/list",
                                  "params": {}}, headers=sess)
    _, rpc_err, res, _ = parse_result(r)
    if rpc_err or not res:
        print(f"tools/list failed: {rpc_err}")
        return 2
    tools = {t["name"]: t for t in res["tools"]}
    print(f"Registered tools: {len(tools)}")

    # ── real lab hash for read paths ──
    real_lab = None
    r = client.post("/mcp", json={"jsonrpc": "2.0", "id": 3, "method": "tools/call",
                                  "params": {"name": "list_labs", "arguments": {}}},
                    headers=sess)
    _, _, res, _ = parse_result(r)
    if res and not res.get("isError") and isinstance(res.get("structuredContent"), dict):
        labs = res["structuredContent"].get("labs") or []
        if labs:
            real_lab = labs[0].get("instance_hash")
    print(f"Real lab for read paths: {real_lab or 'NONE (lab reads will be skipped)'}")
    print("-" * 100)

    def classify(name, schema):
        props = schema.get("properties", {}) or {}
        has_lab = "lab" in props
        if name in READ_EXPLICIT:
            return "read"
        if name in GUARD_EXPLICIT:
            return "guard" if has_lab else "schema"
        if MUTATOR_KW.match(name):
            return "guard" if has_lab else "schema"
        return "read"

    def build_args(name, mode, schema):
        props = schema.get("properties", {}) or {}
        required = schema.get("required", []) or []
        args = {}
        if mode == "guard":
            if "lab" not in props:
                return None, f"harness: {name} classified guard but has no lab prop"
            args["lab"] = FAKE_LAB
            for p in required:
                if p != "lab":
                    args[p] = fill_value(props.get(p, {}))
            args.update(CONFIRM_TOOLS.get(name, {}))
            return args, ""
        if mode == "schema":
            target = None
            for p in required:
                if p != "lab" and props.get(p, {}).get("type") == "string":
                    target = p
                    break
            if target is None:
                for p, spec in props.items():
                    if p != "lab" and spec.get("type") == "string":
                        target = p
                        break
            if target is None:
                return {}, "loose"
            args[target] = {"__probe__": True}
            return args, ""
        # read mode
        if "lab" in props:
            if not real_lab:
                return None, "no real lab available"
            args["lab"] = real_lab
        extra = READ_EXTRA.get(name, {})
        for p in required:
            if p not in args:
                args[p] = extra.get(p, fill_value(props.get(p, {})))
        if "limit" in props and "limit" not in args:
            args["limit"] = 3
        return args, ""

    passed = failed = skipped = 0
    failures = []
    req_id = 10

    for name in sorted(tools):
        schema = tools[name].get("inputSchema", {})
        mode = classify(name, schema)
        args, note = build_args(name, mode, schema)
        if note == "loose":
            mode, args, note = "loose", args if isinstance(args, dict) else {}, ""
        if args is None:
            print(f"SKIP  {mode:6} {name:35} {note}")
            skipped += 1
            continue

        req_id += 1
        try:
            r = client.post("/mcp", json={"jsonrpc": "2.0", "id": req_id,
                                          "method": "tools/call",
                                          "params": {"name": name, "arguments": args}},
                            headers=sess)
            http_st, rpc_err, res, _ = parse_result(r)
        except Exception as e:  # transport / crash
            print(f"FAIL  {mode:6} {name:35} transport: {e}")
            failed += 1
            failures.append(name)
            continue

        txt = result_text(res, rpc_err, "")
        validation_err = ("validation error" in txt
                          or (rpc_err and ("validat" in json.dumps(rpc_err).lower()
                                           or rpc_err.get("code") == -32602)))
        crashed = "Traceback" in txt or "AttributeError:" in txt or "TypeError:" in txt
        if mode == "read":
            succeeded = (http_st == 200 and not rpc_err and res and not res.get("isError")
                         and isinstance(res.get("structuredContent"), dict))
            if succeeded:
                ok, detail = True, ""
            elif validation_err or crashed:
                ok, detail = False, f"bad: {txt[:140]}"
            elif "not found for your account" in txt:
                ok, detail = False, f"ownership wrongly rejected real lab: {txt[:140]}"
            elif res and res.get("isError"):
                ok, detail = True, f"clean tool error: {re.sub(r'\s+', ' ', txt)[:70]}"
            else:
                ok, detail = False, f"http={http_st} rpc={bool(rpc_err)} {txt[:140]}"
        elif mode == "guard":
            ownership = "not found for your account" in txt and "Available labs" in txt
            rejected = bool(rpc_err) or bool(res and res.get("isError"))
            if ownership:
                ok, detail = True, ""
            elif validation_err or crashed:
                ok, detail = False, f"validation/crash: {txt[:140]}"
            elif res and not res.get("isError") and not rpc_err:
                ok, detail = False, f"TOOL EXECUTED for fake lab: {txt[:140]}"
            elif rejected:
                ok, detail = True, f"early-reject: {re.sub(r'\s+', ' ', txt)[:60]}"
            else:
                ok, detail = False, f"http={http_st} {txt[:140]}"
        elif mode == "schema":
            rejected = bool(rpc_err) or bool(res and res.get("isError"))
            succeeded = bool(res and not res.get("isError") and not rpc_err)
            ok = rejected and not succeeded
            detail = "" if ok else f"rejected={rejected} succeeded={succeeded} {txt[:140]}"
        else:  # loose
            crashed = "Traceback" in txt or "validation error" in txt.lower()
            ok = not crashed
            detail = "" if ok else f"crash-like output: {txt[:160]}"

        if ok:
            print(f"PASS  {mode:6} {name:35} {note}")
            passed += 1
        else:
            print(f"FAIL  {mode:6} {name:35} {detail}")
            failed += 1
            failures.append(name)

    print("-" * 100)
    print(f"Results: {passed} passed, {failed} failed, {skipped} skipped (of {len(tools)} tools)")
    if failures:
        print("Failed tools: " + ", ".join(failures))
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
