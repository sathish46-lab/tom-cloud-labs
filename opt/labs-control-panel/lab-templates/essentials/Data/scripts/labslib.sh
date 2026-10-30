# labslib.sh — sourced helper for TomCloud Lab template authoring.
#
# Baked into every essentials lab image at /var/labsdata/scripts/labslib.sh
# (same place as wgconfig.py / wgfree.sh). SOURCE it from your entry.sh —
# do NOT execute it:
#
#     . /var/labsdata/scripts/labslib.sh
#     wait_for_labsconfig || exit 1
#     # now $LABS_USER / $LABS_EMAIL / $LABS_CSPASSWORD are set
#
# WHY THIS EXISTS
# ---------------
# The platform writes /.labsconfig into the container a moment AFTER the
# container's CMD (entry.sh) starts — it is produced by TomCloud Labs labsctl
# at deploy, right after linkuser.sh. So an app that reads /.labsconfig at the
# top of entry.sh hits a RACE and sees a missing/half-written file. This
# library is the one correct copy of the poll + parse contract.#
# CONTRACT (matches the writer, TomCloud Labs labsctl Lab._write_labsconfig):
#   .user          → $LABS_USER        (platform login / uid-1000 user)
#   .email/.owner  → $LABS_EMAIL       (the account's email address)
#   .cs_password   → $LABS_CSPASSWORD  (code-server / app admin password)
#
# DESIGN RULES:
#   * POSIX sh only (entry.sh files are `#!/bin/sh`; may be dash/busybox).
#   * Safe to source any number of times (idempotent; sets no traps, no `set -e`).
#   * Never enables `set -e` and never exits — it only ever `return`s, so a
#     sourcing script keeps control of its own error handling.
#   * Loud on failure (clear message to stderr), silent on success.
#   * jq is used when present; python3 is the fallback so stripped images work.

: "${LABS_CONFIG_FILE:=/.labsconfig}"

LABS_USER=""
LABS_EMAIL=""
LABS_CSPASSWORD=""

_labs_err() {
    printf '[labslib] %s\n' "$*" >&2
}

# Print a field from the JSON config with the available JSON parser.
_labs_jq() {
    if command -v jq >/dev/null 2>&1; then
        jq -r "$1 // empty" "$LABS_CONFIG_FILE" 2>/dev/null
        return $?
    fi
    if command -v python3 >/dev/null 2>&1; then
        # Fall back to python3 (every base image has it); mirror jq `// empty`
        # semantics: print the value on success, nothing on null/missing/error.
        python3 -c '
import json, sys
try:
    with open(sys.argv[2]) as f:
        d = json.load(f)
    for part in sys.argv[1].lstrip(".").split("."):
        if part.endswith("//"):
            part = part[:-3].strip()
            if isinstance(d, dict) and d.get(part) is not None:
                d = d[part]
            else:
                d = None
                break
        else:
            d = d.get(part) if isinstance(d, dict) else None
        if d is None:
            break
    if d is not None:
        print(d)
except Exception:
    pass
' "$1" "$LABS_CONFIG_FILE" 2>/dev/null
        return $?
    fi
    _labs_err "neither jq nor python3 is available on PATH — cannot read ${LABS_CONFIG_FILE}."
    return 1
}

# Return 0 iff $LABS_CONFIG_FILE exists and parses as JSON. No side effects.
_labs_config_ready() {
    [ -f "$LABS_CONFIG_FILE" ] || return 1
    if command -v jq >/dev/null 2>&1; then
        jq -e . "$LABS_CONFIG_FILE" >/dev/null 2>&1 && return 0
        return 1
    fi
    if command -v python3 >/dev/null 2>&1; then
        python3 -c 'import json,sys; json.load(open(sys.argv[1]))' "$LABS_CONFIG_FILE" >/dev/null 2>&1 && return 0
        return 1
    fi
    _labs_err "neither jq nor python3 is available on PATH — cannot validate ${LABS_CONFIG_FILE}."
    return 1
}

# Populate the LABS_* variables from a config that is already valid JSON.
# Idempotent; returns non-zero (without clobbering existing values) if the file
# is not readable JSON.
labs_load_config() {
    _labs_config_ready || return 1
    LABS_USER="$(_labs_jq '.user')"
    # The account lives under .owner/user-owned labs; prefer an explicit
    # .email when the writer adds one (it already does).
    LABS_EMAIL="$(_labs_jq '.email // .owner')"
    LABS_CSPASSWORD="$(_labs_jq '.cs_password')"
    export LABS_USER LABS_EMAIL LABS_CSPASSWORD
    return 0
}

# Block until /.labsconfig exists AND is valid JSON, then load the LABS_* vars.
#
#   wait_for_labsconfig [timeout_seconds]   (default 120)
#
# Returns 0 once loaded; non-zero (with a clear stderr message) on timeout.
# Polls once per second. Safe to call more than once.
wait_for_labsconfig() {
    timeout="${1:-120}"
    case "$timeout" in
        ''|*[!0-9]*) timeout=120 ;;
    esac

    waited=0
    while [ "$waited" -lt "$timeout" ]; do
        if _labs_config_ready; then
            labs_load_config
            return 0
        fi
        sleep 1
        waited=$((waited + 1))
    done

    _labs_err "timed out after ${timeout}s waiting for a valid ${LABS_CONFIG_FILE}."
    return 1
}

# Echo one field from the config ("" and non-zero on missing/error):
#
#     pass=$(labs_field '.cs_password')
#     uid=$(labs_field '.user_id')
labs_field() {
    _labs_config_ready || return 1
    _labs_jq "$1"
}
