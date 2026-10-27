"""Guides and credit explanations — Tom Labs tools.

Static guide content is authored for THIS platform (Tom Labs, tomweb) with the
same JSON shape used across Tom Labs tools; explain_my_credits derives every item
from real ledger/activity collections:
    transactions (valid ledger rows), user_stats, quiz_attempts + quizzes,
    challenge_instances (CTF missions).
"""

import time
from typing import Any, Dict, Optional

from mcp.server.fastmcp import Context

from src.mcp.deps import get_current_user

DASH = "http://dev.tomweb.in:9080"
LIMITS = ("Explanation only. This assistant cannot play, start, answer, submit, "
          "unlock hints or reveal answers, flags, test cases or solutions, for any account. "
          "Do those in the web dashboard; the buttons below open the right page.")
NO_DOCS = ("This install ships no public docs site; the dashboard pages linked below are the "
           "reference.")
REASON_TEXT = {
    "paid": "Paid: a valid ledger credit exists for this activity.",
    "not_started": "You opened this but never started it.",
    "not_perfect_first_run": "Your first complete run was not 5/5 (or was not completed). Only a perfect first run pays; restarts are practice.",
    "not_captured": "You started this mission but did not capture its flag (yet).",
    "payment_missing": "The activity says won but no ledger row exists (points may have been off then). Contact support with the reference.",
}
SETTINGS_KEYS = {
    "tool_surface": {"values": ["three", "full", "default"],
                     "desc": "three exposes only get_service_info, get_type_info and execute_request; full exposes every tool."},
    "ui_widgets": {"values": ["on", "off", "default"],
                   "desc": "Interactive widgets in tool results."},
    "lab_gateway": {"values": ["on", "off", "default"],
                    "desc": "The lab_mcp_* tools. Off hides them and refuses calls with that reason."},
}
PLATFORM_DEFAULTS = {"tool_surface": "full", "ui_widgets": "off", "lab_gateway": "on"}
OPEN_URLS = {
    "currency_guide": [{"label": "Dashboard (balances and streak)", "path": "/dashboard", "url": f"{DASH}/dashboard"}],
    "evaluate_guide": [
        {"label": "Spot Quiz", "path": "/quiz", "url": f"{DASH}/quiz"},
        {"label": "CTF missions", "path": "/labs/challenge", "url": f"{DASH}/labs/challenge"},
    ],
    "competitions_guide": [
        {"label": "CTF missions", "path": "/labs/challenge", "url": f"{DASH}/labs/challenge"},
        {"label": "Discussions", "path": "/discussion/questions/recent/all", "url": f"{DASH}/discussion/questions/recent/all"},
    ],
    "learning_paths_guide": [{"label": "Learn AI", "path": "/learn", "url": f"{DASH}/learn"}],
    "community_guide": [{"label": "Discussions", "path": "/discussion/questions/recent/all", "url": f"{DASH}/discussion/questions/recent/all"}],
}


def _untrusted(text) -> str:
    return f"[[untrusted]]{text}[[/untrusted]]"


def _guide_payload(guide_id: str, title: str, summary: str, topic: dict, topics: list, next_line: str, play_here: Optional[dict]) -> dict[str, Any]:
    payload = {
        "guide": guide_id,
        "title": title,
        "summary": summary,
        "topic": topic,
        "topics": [{"id": t["id"], "title": t["title"], "available": True,
                    "call": {"tool": guide_id, "args": {"topic": t["id"]}}} for t in topics],
        "limits": LIMITS,
        "docs": NO_DOCS,
        "next": next_line,
    }
    if play_here:
        payload["play_here"] = play_here
    return payload


def _topic(id, title, summary, **extra):
    out = {"id": id, "title": title, "summary": summary, "available": True}
    out.update(extra)
    return out


CURRENCY_OPEN = OPEN_URLS["currency_guide"]
GUIDE_CURRENCY = _guide_payload(
    "currency_guide", "Zeal and Jolt",
    "The platform's two currencies. Zeal is your mastery score: it ranks you and unlocks privileges, and is never spent. Jolt is energy: you spend it on hints, AI tools, renewals and entry fees.",
    _topic("overview", "How credits work",
           "Every credit and every spend is a row in a ledger. Balances are recalculated from the valid rows. A reward that 'did not arrive' has no valid ledger row; explain_my_credits checks exactly that for your recent activity.",
           modes=[
               {"name": "Zeal (Mastery Score)", "detail": "Rank, league, privileges. Not spendable; drops only if staff reverse a reward."},
               {"name": "Jolt (Energy Points)", "detail": "Spent on hints, AI tools, renewals and entry fees."},
           ],
           credit_rules=[
               {"rule": "A credit is a valid ledger row from the platform to you; a spend is a valid row from you to the platform. Invalidated rows (reversals) do not count.", "source": "transaction ledger (valid rows only)"},
               {"rule": "If points are switched off on the platform, rewards write nothing to the ledger, even when an activity shows as won.", "source": "ledger freeze"},
               {"rule": "A spend with too little Jolt is refused and nothing is taken.", "source": "spending validations"},
           ],
           why_no_credits=[
               {"reason_code": "points_disabled", "reason": "Points are switched off on this platform.", "fix": "Ask a platform administrator.", "source": "settings freeze"},
               {"reason_code": "reversed", "reason": "Staff reversed or invalidated the credit.", "fix": "Contact support.", "source": "ledger valid:false"},
               {"reason_code": "payment_missing", "reason": "The activity says won but no ledger row exists (points may have been off then).", "fix": "Contact support with the reference.", "source": "ledger lookup"},
           ],
           open=CURRENCY_OPEN,
           sources=[]),
    [{"id": "overview", "title": "How credits work"},
     {"id": "earning", "title": "Ways to earn"},
     {"id": "spending", "title": "What Jolt is spent on"},
     {"id": "hints", "title": "Costs and hints"},
     {"id": "credits", "title": "How credits are decided"}],
    "Other topics: currency_guide(topic='earning'), currency_guide(topic='spending'), currency_guide(topic='hints'), currency_guide(topic='credits'). For the user's OWN recent results and why each did or did not pay: explain_my_credits().",
    None)


GUIDE_EVALUATE = _guide_payload(
    "evaluate_guide", "Evaluate",
    "Practice assessments that pay Zeal and Jolt: Spot Quiz quizzes and CTF challenge lab missions with flags.",
    _topic("overview", "Evaluate at a glance",
           "Evaluate has two parts. Spot Quiz quiz attempts scored against their answer key; CTF missions where you find a flag inside your deployed challenge lab and capture it. Both pay Zeal (rank) and Jolt (spendable).",
           modes=[
               {"name": "Spot Quiz", "detail": "Multiple-choice quizzes by topic and difficulty."},
               {"name": "CTF missions", "detail": "Deploy the challenge lab, capture its flag, and earn its Zeal."},
           ],
           steps=[
               "Open the counterpart page in the dashboard.",
               "Pick a quiz, or a challenge lab and one of its missions.",
               "Play in the dashboard. Rewards are recorded automatically when a run or capture qualifies.",
               "To see why a finished run did or did not pay, ask the assistant to explain your credits.",
           ],
           credit_rules=[
               {"rule": "A Spot Quiz pays only when your first complete run is perfect. Retakes are practice and never pay.", "source": "quiz scoring"},
               {"rule": "A mission pays once, when a correct flag is captured.", "source": "challenge instance completion"},
           ],
           open=OPEN_URLS["evaluate_guide"],
           sources=[]),
    [{"id": "overview", "title": "Evaluate at a glance"},
     {"id": "spot_quiz", "title": "Spot Quiz"},
     {"id": "missions", "title": "CTF missions"},
     {"id": "hints", "title": "Costs and hints"},
     {"id": "credits", "title": "How credits are decided"}],
    "Other topics: evaluate_guide(topic='spot_quiz'), evaluate_guide(topic='missions'), evaluate_guide(topic='hints'), evaluate_guide(topic='credits'). For the user's OWN recent results and why each did or did not pay: explain_my_credits().",
    {"say": "Play in the web dashboard. The assistant must not generate its own version or build one in a lab to stand in for the platform's: results there earn nothing.",
     "links": OPEN_URLS["evaluate_guide"]})


GUIDE_COMPETITIONS = _guide_payload(
    "competitions_guide", "Competitions",
    "Challenge labs (capture-the-flag missions inside a lab you deploy). Captures pay Zeal.",
    _topic("overview", "Competitions at a glance",
           "A challenge lab is a lab with missions. Start a mission, find its flag inside your lab, and capture it; the mission pays Zeal once.",
           modes=[
               {"name": "Solo challenge labs", "detail": "Your own lab, your own missions."},
           ],
           steps=[
               "Pick a challenge lab and deploy it (missions only work while it runs).",
               "Start a mission.",
               "Find the flag inside your lab and capture it.",
           ],
           credit_rules=[
               {"rule": "A mission pays once, when a correct flag is captured.", "source": "challenge instance completion"},
               {"rule": "Each failed attempt cuts the mission's reward share.", "source": "failed_attempts count"},
           ],
           open=OPEN_URLS["competitions_guide"],
           sources=[]),
    [{"id": "overview", "title": "Competitions at a glance"},
     {"id": "missions", "title": "Missions and flags"},
     {"id": "hints", "title": "Costs and hints"},
     {"id": "credits", "title": "How credits are decided"}],
    "Other topics: competitions_guide(topic='missions'), competitions_guide(topic='hints'), competitions_guide(topic='credits'). For the user's OWN recent results and why each did or did not pay: explain_my_credits().",
    {"say": "Play in the web dashboard. The assistant must not generate its own version or build one in a lab to stand in for the platform's: results there earn nothing.",
     "links": OPEN_URLS["competitions_guide"]})


GUIDE_LEARNING = _guide_payload(
    "learning_paths_guide", "Learning paths",
    "AI lessons (Learn AI) and generated study material. Lesson reading is free; generation spends Jolt.",
    _topic("overview", "Learning paths at a glance",
           "Describe what you want to learn and get a lesson, with modules and chapters you read at your own pace.",
           modes=[
               {"name": "Learn AI", "detail": "AI-written lessons with modules and chapters."},
           ],
           credit_rules=[
               {"rule": "Reading lessons is free. Generating costs Jolt.", "source": "lesson jobs"},
               {"rule": "Quizzes you take from a lesson pay by the Spot Quiz rules (a perfect first run).", "source": "quiz scoring"},
           ],
           open=OPEN_URLS["learning_paths_guide"],
           sources=[]),
    [{"id": "overview", "title": "Learning paths at a glance"},
     {"id": "learn_ai", "title": "Learn AI"},
     {"id": "hints", "title": "Costs and hints"},
     {"id": "credits", "title": "How credits are decided"}],
    "Other topics: learning_paths_guide(topic='learn_ai'), learning_paths_guide(topic='hints'), learning_paths_guide(topic='credits'). For the user's OWN recent results and why each did or did not pay: explain_my_credits().",
    {"say": "Play in the web dashboard. The assistant must not generate its own version or build one in a lab to stand in for the platform's: results there earn nothing.",
     "links": OPEN_URLS["learning_paths_guide"]})


GUIDE_COMMUNITY = _guide_payload(
    "community_guide", "Community",
    "Questions, answers and discussions, with AI writing helpers. Posting, answering, voting and accepting pay no Zeal or Jolt.",
    _topic("overview", "Community at a glance",
           "Ask and answer questions with 2-5 tags, keep drafts, and share into clubs. AI helpers can proofread or summarise.",
           steps=[
               "Open Discussions.",
               "Ask a question with 2 to 5 tags, or answer one; drafts are saved.",
               "Use the AI helpers while writing or reading.",
           ],
           credit_rules=[
               {"rule": "Answering, voting and accepting pay no Zeal or Jolt.", "source": "community activity rules"},
           ],
           open=OPEN_URLS["community_guide"],
           sources=[]),
    [{"id": "overview", "title": "Community at a glance"},
     {"id": "clubs", "title": "Clubs"},
     {"id": "hints", "title": "Costs and hints"},
     {"id": "credits", "title": "How credits are decided"}],
    "Other topics: community_guide(topic='clubs'), community_guide(topic='hints'), community_guide(topic='credits'). For the user's OWN recent results and why each did or did not pay: explain_my_credits().",
    {"say": "Play in the web dashboard. The assistant must not generate its own version or build one in a lab to stand in for the platform's: results there earn nothing.",
     "links": OPEN_URLS["community_guide"]})

GUIDES = {
    "currency_guide": GUIDE_CURRENCY,
    "evaluate_guide": GUIDE_EVALUATE,
    "competitions_guide": GUIDE_COMPETITIONS,
    "learning_paths_guide": GUIDE_LEARNING,
    "community_guide": GUIDE_COMMUNITY,
}

_GUIDE_TOPICS = {
    "currency_guide": {
        "earning": _topic("earning", "Ways to earn",
                          "Streaks pay first; assessment wins pay on qualifying runs.",
                          sources=[]),
        "spending": _topic("spending", "What Jolt is spent on",
                           "Spends are valid ledger rows from you to the platform: hints, AI generation, renewals and entry fees.",
                           sources=[]),
        "hints": _topic("hints", "Costs and hints",
                        "Hints cost Jolt and are shown where you would use them, before you unlock.",
                        sources=[]),
        "credits": _topic("credits", "How credits are decided",
                          "Every credit and spend is a ledger row from the platform to you (or you to the platform), valid when the activity qualified and points are on. Balances are computed from valid rows only.",
                          shorts=[{"why": "Activity shows as won but nothing arrived.", "reason_code": "payment_missing", "what": "No ledger row exists for the win — points may have been off then.", "fix": "Contact support with the reference."}]),
    },
    "evaluate_guide": {
        "spot_quiz": _topic("spot_quiz", "Spot Quiz",
                            "Multiple-choice quizzes scored against the answer key; a perfect first complete run pays.",
                            rules="A quiz pays only when your first complete run is perfect. Retakes are practice and never pay.",
                            pay_rules="Points per question come from the quiz's own rubric; the ledger records exactly what was granted."),
        "missions": _topic("missions", "CTF missions",
                           "Deploy the challenge lab, start the mission, find its flag, capture it.",
                           pay_rules="A mission pays once, when a correct flag is captured."),
        "hints": _topic("hints", "Costs and hints",
                        "Hints cost Jolt and are shown where you would use them, before you unlock."),
        "credits": _topic("credits", "How credits are decided",
                          "Paid activity writes valid ledger rows; from then on Zeal and Jolt follow the currency rules.",
                          shorts=[{"why": "Run completed but not perfect.", "reason_code": "not_perfect_first_run", "what": "Only a perfect first complete run pays.", "fix": "Practice retakes are free."}]),
    },
    "competitions_guide": {
        "missions": _topic("missions", "Missions and flags",
                           "Deploy the challenge lab, start the mission, find and capture its flag.",
                           accounts="Missions are per user; captures are recorded against the mission and pay once.",
                           core="capture_first_run"),
        "hints": _topic("hints", "Costs and hints",
                        "Hints cost Jolt and are shown where you would use them, before you unlock."),
        "credits": _topic("credits", "How credits are decided",
                          "Paid captures write valid ledger rows; from then on Zeal and Jolt follow the currency rules.",
                          shorts=[{"why": "Mission says completed but nothing arrived.", "reason_code": "payment_missing", "what": "No ledger row exists for the capture.", "fix": "Contact support with the reference."}]),
    },
    "learning_paths_guide": {
        "learn_ai": _topic("learn_ai", "Learn AI",
                           "AI-written lessons with modules and chapters; reading is free, generation spends Jolt.",
                           how="Describe what you want to learn and generate a lesson."),
        "hints": _topic("hints", "Costs and hints",
                        "Generation and helper features cost Jolt and are shown before you spend."),
        "credits": _topic("credits", "How credits are decided",
                          "Spent Jolt is a valid ledger row from you to the platform; balances follow the currency rules."),
    },
    "community_guide": {
        "clubs": _topic("clubs", "Clubs",
                        "Share drafts and questions into clubs you belong to.",
                        ask="Find clubs from the Discussions page."),
        "hints": _topic("hints", "Costs and hints",
                        "Some AI helpers cost Jolt; ordinary posting, voting and accepting are free."),
        "credits": _topic("credits", "How credits are decided",
                          "Community activity pays no Zeal or Jolt; only moderator actions and reversals change balances from here."),
    },
}

MCP_SETTINGS_COLLECTION = "mcp_user_settings"
DASHBOARD_SETTINGS_PATH = "/settings/mcp"
_SETTINGS_TOPIC = {"currency_guide": "credits", "evaluate_guide": "credits",
                   "competitions_guide": "credits", "learning_paths_guide": "hints",
                   "community_guide": "hints"}


def _db():
    from src.config import Config
    import pymongo
    cfg = Config()
    return pymongo.MongoClient(cfg.mongo_uri)[cfg.main_db]


async def _guide(name: str, topic: Optional[str]) -> dict[str, Any]:
    base = GUIDES[name]
    if not topic:
        return dict(base)
    data = _GUIDE_TOPICS[name].get(topic)
    if not data:
        known = ", ".join(f"'{t['id']}'" for t in base["topics"])
        return {"success": False, "error": f"Unknown topic '{topic}' for {name}. Topics: {known}."}
    out = dict(base)
    out["topic"] = data
    out["limits"] = LIMITS
    return out


async def currency_guide(ctx: Context, topic: Optional[str] = None) -> dict[str, Any]:
    """How Zeal and Jolt work: what each is for, the ways to earn (daily streaks included), what Jolt is spent on, and why a reward can be missing.

    Explanation only; it moves no currency. For the user's OWN recent
    results and why each did or did not pay, call explain_my_credits.
    """
    return await _guide("currency_guide", topic)


async def evaluate_guide(ctx: Context, topic: Optional[str] = None) -> dict[str, Any]:
    """How this platform's Quiz (Spot Quiz and game modes) and Code Arena work: modes, rules, hint costs and exactly when Zeal and Jolt are paid.

    Use this for ANY question about the platform's Quiz and Code Arena
    instead of making up a quiz or a coding problem yourself.
    """
    return await _guide("evaluate_guide", topic)


async def competitions_guide(ctx: Context, topic: Optional[str] = None) -> dict[str, Any]:
    """How this platform's CTF missions (challenge labs you deploy, flags to capture) work: steps, flags, hint costs, and what a capture pays in Zeal.

    Use this for ANY question about the platform's CTF missions instead of
    inventing a challenge yourself.
    """
    return await _guide("competitions_guide", topic)


async def learning_paths_guide(ctx: Context, topic: Optional[str] = None) -> dict[str, Any]:
    """How this platform's Learn AI (AI lessons), Roadmaps and generated study paths work, step by step, with what each costs in Jolt.

    Explanation only: it creates, generates and spends nothing. Use it for
    ANY question about the platform's learning features instead of writing
    your own course.
    """
    return await _guide("learning_paths_guide", topic)


async def community_guide(ctx: Context, topic: Optional[str] = None) -> dict[str, Any]:
    """How this platform's Discussions (questions and answers), Discussion AI and For You (a personalised feed) work.

    Explanation only: it posts, votes and spends nothing.
    """
    return await _guide("community_guide", topic)


def _default_prefs() -> dict[str, Any]:
    return {"tool_surface": None, "ui_widgets": None, "lab_gateway": None}


async def my_mcp_settings(ctx: Context) -> dict[str, Any]:
    """Read your own MCP settings: what you chose, what is in force, and the platform defaults.

    Settings are per-user; a changed key applies on your next request, and
    your AI client may need a refresh to see a changed tool list.
    """
    user = await get_current_user(ctx)
    rec = _db()[MCP_SETTINGS_COLLECTION].find_one({"email": user.get("email")}) or {}
    prefs = rec.get("prefs", _default_prefs())
    effective = {}
    for key, default in PLATFORM_DEFAULTS.items():
        chosen = prefs.get(key)
        used = chosen if chosen in ("three", "full", "on", "off") else default
        effective[key] = {"value": used, "source": "user" if used == chosen else "platform", "allowed": True}
    return {
        "prefs": prefs,
        "effective": effective,
        "platform": PLATFORM_DEFAULTS,
        "dashboard": f"{DASH}{DASHBOARD_SETTINGS_PATH}",
        "note": "AI clients cache their tool list: refresh or reconnect the connector after a change to see it.",
    }


async def set_my_mcp_setting(ctx: Context, key: str, value: str) -> dict[str, Any]:
    """Change one of YOUR MCP settings (only your account; the platform's limits still apply).

    Keys: tool_surface (three|full), ui_widgets (on|off), lab_gateway
    (on|off); 'default' resets a key to the platform's setting. The change
    applies on your next request; refresh the connector to see it.
    """
    user = await get_current_user(ctx)
    spec = SETTINGS_KEYS.get(key)
    if spec is None:
        return {"success": False, "error": f"Unknown setting '{key}'. Keys: {', '.join(SETTINGS_KEYS)}."}
    if value not in spec["values"]:
        return {"success": False, "error": f"Value '{value}' is not valid for '{key}'. Allowed: {', '.join(spec['values'])}."}
    prefs_val = None if value == "default" else value
    rec = _db()[MCP_SETTINGS_COLLECTION].find_one({"email": user.get("email")}) or {}
    prefs = rec.get("prefs", _default_prefs())
    prefs[key] = prefs_val
    _db()[MCP_SETTINGS_COLLECTION].update_one(
        {"email": user.get("email")},
        {"$set": {"email": user.get("email"), "prefs": prefs, "updated_at": int(time.time())}},
        upsert=True)
    out = await my_mcp_settings(ctx)
    out["set"] = {"key": key, "value": value, "description": spec["desc"]}
    out["notice"] = None
    return out


def _ts(v):
    if isinstance(v, (int, float)):
        return int(v)
    try:
        return int(str(v).split(".", 1)[0])
    except (TypeError, ValueError):
        return None


async def explain_my_credits(ctx: Context, window_days: int = 30, limit: int = 10) -> dict[str, Any]:
    """Why the signed-in user did or did not get Zeal / Jolt for their recent activity.

    Use it for 'why didn't I get credits / points / Zeal', 'credits not
    applied', 'was I paid for that'. Own account only: outcomes only, it
    cannot award, restore or move credits.
    """
    user = await get_current_user(ctx)
    db = _db()
    since = int(time.time()) - max(1, min(int(window_days), 365)) * 86400
    email = user.get("email")
    cap = max(1, min(int(limit), 50))
    items = []

    attempts = list(db["quiz_attempts"].find(
        {"user_email": email, "attempted_at": {"$gte": since}},
        {"quiz_hash": 1, "score": 1, "total": 1, "status": 1, "attempted_at": 1}).sort("attempted_at", -1))
    hashes = list({a["quiz_hash"] for a in attempts})
    qmeta = {q["hash"]: q for q in (db["quizzes"].find({"hash": {"$in": hashes}}, {"hash": 1, "title": 1, "difficulty": 1}) if hashes else [])}

    ledger_types = [r["_id"] for r in db["transactions"].aggregate([{"$group": {"_id": "$type"}}])]
    ledger_rows = []
    tx_doc = None  # matched per-item below when types link to activities

    for attempt in attempts[:cap]:
        meta = qmeta.get(attempt["quiz_hash"], {})
        perfect = attempt["status"] == "completed" and attempt["total"] > 0 and attempt["score"] == attempt["total"]
        ref = attempt["quiz_hash"]
        title = meta.get("title") or "Untitled quiz"
        if perfect:
            matched = db["transactions"].find_one(
                {"user_email": email, "valid": True, "direction": "earned",
                 "$or": [{"description": {"$regex": title[:30]}}, {"quiz_hash": ref}, {"ref": ref}]})
            rewarded = matched is not None
            reason_code = "paid" if rewarded else "payment_missing"
            amount = {"zeal": int((matched or {}).get("amount", 0)) if matched and (matched or {}).get("currency") == "zeal" else 0,
                      "jolt": int((matched or {}).get("amount", 0)) if matched and (matched or {}).get("currency") == "jolt" else 0}
            paid_at = (matched or {}).get("created_at") if matched else None
        else:
            reason_code = "not_perfect_first_run"
            amount = {"zeal": 0, "jolt": 0}
            paid_at = None
        items.append({
            "kind": "spot_quiz",
            "ref": ref,
            "title": _untrusted(title),
            "url": f"/quiz/spot/{ref}",
            "started_at": attempt.get("attempted_at"),
            "finished_at": attempt.get("attempted_at"),
            "paid_at": paid_at,
            "outcome": "won" if perfect else "completed",
            "rewarded": bool(perfect and reason_code == "paid"),
            "amount": amount,
            "reason_code": reason_code,
            "reason": REASON_TEXT[reason_code],
            "details": {"difficulty": meta.get("difficulty", "normal"), "perfect": perfect},
            "open_url": f"{DASH}/quiz/spot/{ref}",
        })

    missions = list(db["challenge_instances"].find(
        {"username": user.get("username")},
        {"instance_hash": 1, "challenge_id": 1, "mission_started": 1, "mission_start_time": 1,
         "completed_at": 1, "failed_attempts": 1, "zeal_earned": 1, "jolt_earned": 1, "created_at": 1}))
    missions = [m for m in missions if (_ts(m.get("mission_start_time")) or _ts(m.get("created_at")) or 0) >= since]
    started = [m for m in missions if m.get("mission_started")]
    for inst in started[:cap]:
        captured = inst.get("completed_at") is not None
        reason_code = "paid" if captured else "not_captured"
        items.append({
            "kind": "mission",
            "ref": inst.get("instance_hash"),
            "title": _untrusted(inst.get("challenge_id", "Mission")),
            "url": f"/labs/challenges/{inst.get('challenge_id')}",
            "started_at": _ts(inst.get("mission_start_time")) or _ts(inst.get("created_at")),
            "finished_at": _ts(inst.get("completed_at")),
            "paid_at": _ts(inst.get("completed_at")),
            "outcome": "captured" if captured else "not_captured",
            "rewarded": captured,
            "amount": {"zeal": int(inst.get("zeal_earned") or 0), "jolt": int(inst.get("jolt_earned") or 0)},
            "reason_code": reason_code,
            "reason": REASON_TEXT[reason_code],
            "details": {"lab_id": inst.get("challenge_id"),
                        "failed_attempts": inst.get("failed_attempts", 0),
                        "mission_started": True},
            "open_url": f"{DASH}/labs/challenges/{inst.get('challenge_id')}",
        })
    for inst in [m for m in missions if not m.get("mission_started")][:max(0, cap - len(items))]:
        items.append({
            "kind": "mission",
            "ref": inst.get("instance_hash"),
            "title": _untrusted(inst.get("challenge_id", "Mission")),
            "url": f"/labs/challenges/{inst.get('challenge_id')}",
            "started_at": None,
            "finished_at": None,
            "paid_at": None,
            "outcome": "not_captured",
            "rewarded": False,
            "amount": {"zeal": 0, "jolt": 0},
            "reason_code": "not_started",
            "reason": REASON_TEXT["not_started"],
            "details": {"lab_id": inst.get("challenge_id"),
                        "failed_attempts": inst.get("failed_attempts", 0),
                        "mission_started": False},
            "open_url": f"{DASH}/labs/challenges/{inst.get('challenge_id')}",
        })

    paid = sum(1 for i in items if i["rewarded"])
    return {
        "scope": "self",
        "kinds": ["spot_quiz", "mission"],
        "skipped": {},
        "points_enabled": True,
        "window_days": int(window_days),
        "items": items,
        "counts": {"total": len(items), "paid": paid, "not_paid": len(items) - paid},
        "reason_codes": {k: REASON_TEXT[k] for k in {i["reason_code"] for i in items}},
        "summary": f"{paid} of {len(items)} recent activities paid.",
        "limits": ("Own account only, outcomes only. This cannot show or change questions, answers, "
                   "test cases, code, flags or hints, and cannot award, restore or move credits. "
                   "For a wrong result, the user contacts support."),
        "next": "The rules behind each reason: currency_guide(topic='credits'), or the module's own guide with topic='credits'.",
    }
