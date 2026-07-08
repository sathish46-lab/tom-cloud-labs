/**
 * htmx-bridge.js — labs-dashboard-web SPA glue.
 *
 * ============================================================================
 * INVARIANT (per CLAUDE.md rules 8/9/10/11 — see project root .claude/CLAUDE.md)
 *
 * This file IS the only sanctioned seam between htmx and jQuery:
 *
 *   - No page-level script may attach `htmx:*` event listeners. ALL
 *     cross-cutting cleanup / re-init on swap lives in this file inside
 *     the existing htmx:beforeRequest / htmx:beforeSwap / htmx:afterSwap
 *     / htmx:afterSettle / htmx:historyRestore blocks.
 *
 *   - No page-level script may call `htmx.process()` or `htmx.ajax()`.
 *     For programmatic SPA navigation use `window.spaNavigate(url)` (this
 *     file). For data fetching that returns JSON or partial HTML for a
 *     non-SPA region, use jQuery `$.get` / `$.post` instead.
 *
 *   - Masonry on MasonryMgr-owned selectors (#masonry-area,
 *     #masonry-area1, #labcard-masonry-area, #discussion-masonry,
 *     #discussion-search-masonry, #clan-masonry-area, #code-masonry-area,
 *     #evaluate-grid, #roadmap-masonry, .club-feed-grid) must go through
 *     `MasonryMgr` so the ResizeObserver registry stays consistent across
 *     swaps. Raw `.masonry()` calls on these selectors leak observers.
 *
 *   - Masonry sequencing (blurRampIn, zoom-in, remove+append timing,
 *     height-lock dance) is OFF LIMITS. Do not convert tab strips
 *     (.topic-nav / .clubs-dir-tab / .discussion-sort-tab / .achievement-
 *     tab / labs filter checkboxes) to htmx-driven swaps.
 *
 * Anything that violates the above belongs in this file or stays in the
 * page script untouched. When in doubt: would the action change the URL
 * or replace #main? → htmx. Otherwise → jQuery.
 * ============================================================================
 *
 * Responsibilities (after `htmx.min.js` and `jquery.min.js` are loaded):
 *
 *   1. Drive #htmx-progress (top progress bar).
 *   2. Re-initialise CoreUI Tooltip/Popover instances inside swapped subtrees.
 *   3. Re-initialise DataTables, Masonry, hljs, mermaid, wavedrom on swap.
 *   4. Flip `.active` on #sidebar .nav-link to match the new URL (sidebar
 *      itself is outside the swap zone and is not re-rendered).
 *   5. Read the JSON blob emitted by _master_fragment.php (#htmx-page-bootstrap)
 *      and: render breadcrumbs, run the page-init hook (`Session::set('_pageInit',
 *      'fnName')`), and idempotently inject any per-page CSS/JS the page
 *      declared via Session::customCss() / customJs().
 *   6. Fan out to jQuery's `$(document).trigger('ajaxComplete', ...)` so the
 *      existing app.js:459 global handler (toasts, hljs, scanAndObserve) keeps
 *      working unchanged.
 *   7. Gracefully disconnect STOMP when the server returns HX-Redirect to a
 *      login URL (session-expiry path).
 *
 * No dependencies beyond htmx + jQuery, both already loaded by _master.php.
 */
// Round-8 GLOBAL UNHANDLED-REJECTION HANDLER: was nothing in workspace.
// Every async chain without .catch silently surfaced as "Uncaught (in
// promise)" console errors. This swallows them in prod (preventDefault)
// and surfaces in dev under window.SNL_DEBUG. Bound at file scope BEFORE
// the IIFE so it covers htmx-bridge own awaits too.
if (typeof window !== 'undefined' && !window.__snlUnhandledHooked) {
    window.__snlUnhandledHooked = true;
    window.addEventListener('unhandledrejection', function (e) {
        if (window.SNL_DEBUG && window.console && console.warn) {
            console.warn('[unhandled-rejection]', e.reason && e.reason.message ? e.reason.message : e.reason);
        }
        e.preventDefault();
    });
}

(function () {
    // NOTE: previously `if (typeof htmx === 'undefined') return;` bailed
    // the entire IIFE early when htmx wasn't loaded — admin pages use
    // _admin_master.php which doesn't include htmx.min.js, so WsMgr /
    // TimerMgr / SseMgr / ObserverMgr / spaNavigate / spaReload /
    // IndicatorState would be undefined, causing console errors like
    // "WsMgr is not defined" in connectVPNStatusMQ at app.js:424 and
    // "Cannot read properties of null" in other spots.
    //
    // Fix: only the htmx-specific event listeners (htmx:beforeRequest,
    // htmx:afterSwap) get skipped when htmx is absent. The global
    // helpers below work fine without htmx — pages just won't get SPA
    // navigation, which is the existing behavior on excluded routes.
    var hasHtmx = typeof htmx !== 'undefined';

    // ---------- IndicatorState: single source of truth for .indicator-dot -----
    //
    // PROBLEM (workflow w1x3r0lm1 RCA): the `.indicator-dot` pill is written
    // from 6+ sites scattered across mq_terminal.js, labs.js, and the PHP
    // template. Whichever site fires last wins. With htmx swaps re-rendering
    // the dot to a default red, and MQTerminal's idempotent path force-
    // painting green based on a stale `connected` flag, the dot frequently
    // lied about real state ("green when not actually connected").
    //
    // SOLUTION: producers write to a state dict; a 1s render loop is the
    // ONLY DOM writer. Liveness is tracked by `lastBeatMs` per channel —
    // updated on every STOMP MESSAGE / SSE log event — so the dot also
    // catches "WS is connected but no data is flowing", which the previous
    // architecture couldn't detect (heartbeats are disabled at the broker).
    //
    // SPEC the user confirmed:
    //   red    = WS not connected
    //   yellow = SSE active during deploy
    //   green  = WS connected, frames flowing cross-tab (via SharedWorker)
    //
    // Producer API (used from mq_terminal.js, labs.js, etc.):
    //   IndicatorState.setStatus('mq_user',     'up' | 'down')
    //   IndicatorState.setStatus('sse_deploy',  'opening' | 'open' | 'idle' | 'down')
    //   IndicatorState.beat('mq_user')           // call on each STOMP MESSAGE
    //   IndicatorState.beat('sse_deploy')        // call on each SSE log event
    //
    // Renderer reads ALL channels, picks priority: SSE > MQ > red default.
    window.IndicatorState = (function () {
        var STALE_MS = 15000;        // no beat for 15s ⇒ down
        var RENDER_INTERVAL_MS = 1000;
        var channels = {
            mq_user:    { status: 'down', lastBeatMs: 0 },
            mq_clan:    { status: 'down', lastBeatMs: 0 },
            sse_deploy: { status: 'idle', lastBeatMs: 0 },
        };
        var lastPainted = null;

        function setStatus(channel, status) {
            if (!channels[channel]) channels[channel] = { status: 'down', lastBeatMs: 0 };
            channels[channel].status = status;
            // On any non-'down' transition, refresh the beat so the next
            // render pass sees a fresh window.
            if (status !== 'down') channels[channel].lastBeatMs = Date.now();
        }
        function beat(channel) {
            if (!channels[channel]) channels[channel] = { status: 'down', lastBeatMs: 0 };
            channels[channel].lastBeatMs = Date.now();
        }

        function render() {
            var now = Date.now();
            var sse = channels.sse_deploy;
            var mq  = channels.mq_user;
            var sseFresh = (now - sse.lastBeatMs) < STALE_MS;
            var mqFresh  = (now - mq.lastBeatMs)  < STALE_MS;

            // SSE during deploy wins (yellow).
            var cls;
            if (sse.status === 'opening') {
                cls = 'bg-warning';
            } else if (sse.status === 'open' && sseFresh) {
                cls = 'bg-warning';
            } else if (mq.status === 'up' && mqFresh) {
                cls = 'bg-success';
            } else {
                cls = 'bg-danger';
            }
            if (cls === lastPainted) return;  // no-op when state hasn't changed
            lastPainted = cls;
            if (window.jQuery) {
                jQuery('.indicator-dot')
                    .removeClass('bg-success bg-danger bg-warning')
                    .addClass(cls);
            } else {
                var dots = document.querySelectorAll('.indicator-dot');
                for (var i = 0; i < dots.length; i++) {
                    dots[i].classList.remove('bg-success', 'bg-danger', 'bg-warning');
                    dots[i].classList.add(cls);
                }
            }
        }

        function start() {
            if (state._timer) return;
            state._timer = setInterval(render, RENDER_INTERVAL_MS);
            // Force-render once on htmx swaps so a default-red dot from the
            // PHP template gets corrected immediately (don't wait 1s).
            document.body && document.body.addEventListener('htmx:afterSwap', function () {
                lastPainted = null;
                render();
            });
        }

        var state = {
            channels: channels,
            setStatus: setStatus,
            beat: beat,
            render: render,
            start: start,
            STALE_MS: STALE_MS,
            // exposed for diagnostics
            inspect: function () {
                var out = {};
                Object.keys(channels).forEach(function (k) {
                    var c = channels[k];
                    out[k] = { status: c.status, lastBeatMs: c.lastBeatMs, ageSec: c.lastBeatMs ? Math.round((Date.now() - c.lastBeatMs) / 1000) : Infinity };
                });
                return { painted: lastPainted, channels: out };
            },
        };
        return state;
    })();
    // Start the render loop ASAP; if htmx-bridge runs before document.body
    // exists, defer to DOMContentLoaded.
    if (document.body) IndicatorState.start();
    else document.addEventListener('DOMContentLoaded', function () { IndicatorState.start(); }, { once: true });

    // ---------- spaNavigate helper ----------------------------------------------
    //
    // Page JS used to do `window.location.href = url` for every post-action
    // navigation (after creating a club, switching code categories, opening
    // a quiz topic, navigating to an event detail, etc.). Each of those was
    // a full page reload that:
    //   - Re-downloaded app.js (~2 MB) and every vendor bundle.
    //   - Tore down the SharedWorker LabsWS + reconnected STOMP.
    //   - Re-instantiated CoreUI widgets, the xterm renderer (if /labs/*).
    //   - Reset chat scroll positions, masonry layout caches, etc.
    //
    // `spaNavigate(url)` does an htmx-driven swap into #main instead — same
    // visual result, no page lifecycle reset. Falls back to a real navigation
    // if htmx isn't available or the route is in the exclusion list (e.g.
    // /admin/*, /code/<slug>/arena — those need a full reload by design).
    //
    // Usage: replace `window.location.href = '/foo'` with `spaNavigate('/foo')`.
    window.spaNavigate = function (url) {
        if (!url) return;
        // External URLs always do a real navigation.
        if (/^([a-z]+:)?\/\//i.test(url) && url.indexOf(location.origin) !== 0) {
            window.location.href = url;
            return;
        }
        // Excluded routes (admin, theme editor, code arena child) — these are
        // explicitly NOT boost-eligible on the server side, so a swap into
        // #main would put admin content inside the labs chrome. Hard nav.
        var path = url.split('#')[0].split('?')[0];
        if (/^\/(admin|theme\/editor)\b/.test(path) || /^\/code\/.+\/arena\b/.test(path)) {
            window.location.href = url;
            return;
        }
        // htmx not loaded yet or hx-boost wrapper missing → fall back.
        if (!window.htmx || !document.body.querySelector('[hx-boost="true"]')) {
            window.location.href = url;
            return;
        }
        try {
            htmx.ajax('GET', url, {
                target: '#main',
                swap: 'outerHTML swap:0ms settle:0ms show:window:top',
                select: '#main',
            }).then(function () {
                // htmx.ajax doesn't push history on its own when called from
                // JS; do it explicitly so the back button works.
                try { history.pushState({}, '', url); } catch (_) {}
            }).catch(function () {
                window.location.href = url;
            });
        } catch (_) {
            window.location.href = url;
        }
    };

    // ---------- spaReload helper -----------------------------------------------
    // Re-fetch the CURRENT page via SPA-boost instead of a hard
    // window.location.reload(). Callers use this after server-side mutations
    // (e.g. "user added", "post deleted") that want a fresh server render
    // without tearing down LabsWS / xterm / chat scroll. Falls back to a
    // real reload if htmx isn't available or the current route is excluded.
    window.spaReload = function () {
        var url = location.pathname + location.search;
        // For excluded routes (admin, theme editor, code arena) we MUST do a
        // real reload — spaNavigate would bail to window.location.href
        // anyway; doing it here is cheaper.
        var path = location.pathname;
        if (/^\/(admin|theme\/editor)\b/.test(path) || /^\/code\/.+\/arena\b/.test(path)) {
            window.location.reload();
            return;
        }
        if (!window.htmx || !document.body.querySelector('[hx-boost="true"]')) {
            window.location.reload();
            return;
        }
        try {
            htmx.ajax('GET', url, {
                target: '#main',
                swap: 'outerHTML swap:0ms settle:0ms show:window:top',
                select: '#main',
            }).catch(function () { window.location.reload(); });
        } catch (_) {
            window.location.reload();
        }
    };

    // ---------- $(document).ready capture + replay ------------------------------
    //
    // Legacy labs JS has ~117 `$(document).ready(function(){...})` handlers that
    // bind event listeners, initialize widgets (terminal, quiz, charts, etc.),
    // and wire up STOMP subscriptions. Those handlers fire ONCE per document
    // lifetime — never again after an htmx swap — so anything inside `#main`
    // that depended on a boot-time .ready() init silently breaks on navigation.
    //
    // To make the legacy code "just work", we monkey-patch `jQuery.fn.ready`
    // BEFORE app.js loads (load order in _master.php: jQuery → htmx →
    // htmx-bridge → app.js via customJs), capturing every handler. On each
    // htmx swap we replay the captured list, so anything that runs at boot
    // also runs on every page change.
    //
    // Caveat: re-firing the WHOLE ready list per swap is heavy if any handler
    // is non-idempotent (e.g. doubles up event listeners, opens duplicate
    // sockets). The labs code is mostly idempotent thanks to existing guards
    // (`.connected === true` checks, `data-*Ready` flags, `.off(evt).on(evt)`
    // pairs). Pages that genuinely need to skip a swap can check
    // `window.__htmxSwapping === true`.
    // Captured `$(document).ready(...)` handlers — replayed on every swap.
    //
    // Two-stage filtering to prevent the multiplicative growth that was
    // making /labs/* terminal stack instances (xterm init nested inside
    // a captured $(document).ready, so EACH swap added one new closure
    // that got replayed on EVERY subsequent swap — O(K²) renderer cascade
    // observed at K≈5 navs):
    //
    // 1. Identity dedupe: same function-reference is never useful twice.
    //    Catches app.js boot handlers re-installed when inline scripts
    //    re-run during a swap (most of the leak).
    //
    // 2. Skip capture while `__htmxSwapping === true`: handlers added by
    //    code that's CURRENTLY being replayed are by definition the
    //    nested-ready leak. They'd run anyway (replay fires the OUTER
    //    handler which schedules the inner) so we don't need to capture
    //    them — and capturing would double on every future swap.
    //
    //    Safe for new customJs added during a swap because that runs in
    //    `rerunScripts` BEFORE `replayReady` flips the flag (see line
    //    where `__htmxSwapping = true` is set).
    var capturedReady = [];
    var capturedSeen = (typeof Set === 'function') ? new Set() : null;
    if (window.jQuery && jQuery.fn && jQuery.fn.ready) {
        var origReady = jQuery.fn.ready;
        jQuery.fn.ready = function (fn) {
            if (typeof fn === 'function'
                && !window.__htmxSwapping
                && (!capturedSeen || !capturedSeen.has(fn))) {
                if (capturedSeen) capturedSeen.add(fn);
                capturedReady.push(fn);
            }
            return origReady.apply(this, arguments);
        };
        window.__htmxReadyHandlers = capturedReady;
    }

    // Dedup document-level event delegates that share (event, selector,
    // function-source). Page init code keeps re-registering the same
    // `$(document).on('click', '.foo', fn)` on every boost-nav (because
    // both `rerunScripts` re-evals inline scripts AND `replayReady`
    // re-fires every captured `$(document).ready`). After N navs you have
    // N copies of the same handler — one user click fires the action N
    // times. We can't reasonably require every site in app.js to convert
    // to `.off('event.ns').on('event.ns', ...)` so we sweep document
    // events post-swap and drop pure duplicates.
    //
    // Heuristic: same `(type, selector, handler.toString())` triple = dup.
    // Function source comparison catches identical closures from re-run
    // inline scripts. We keep the FIRST registration (so boot-time
    // delegates always survive) and drop later additions.
    function dedupDocDelegates() {
        if (!window.jQuery) return 0;
        try {
            var events = jQuery._data(document, 'events');
            if (!events) return 0;
            var droppedTotal = 0;
            Object.keys(events).forEach(function (type) {
                var list = events[type];
                if (!Array.isArray(list)) return;
                // jQuery splits each event type's handler array into a
                // delegate prefix [0 .. delegateCount-1] and a direct
                // suffix [delegateCount .. length-1]. When jQuery's
                // `handlers()` iterates, it uses `delegateCount` as the
                // boundary — if we shrink the array without also
                // shrinking that counter, handlers() reads past the end
                // and crashes with "Cannot read properties of undefined
                // (reading 'selector')". So we need to track the counter
                // as we filter, and prefer the LATEST occurrence of each
                // duplicate (fresh closures from a re-run __init function
                // are the up-to-date ones — keeping the first would pin
                // us to stale page-init state).
                var delegateCount = list.delegateCount || 0;
                // First pass (reverse): mark which indices to keep.
                var seen = Object.create(null);
                var keepMask = new Array(list.length).fill(false);
                for (var i = list.length - 1; i >= 0; i--) {
                    var h = list[i];
                    if (!h) continue;
                    // R10-C3 FIX: was substring(0, 200) which collapsed
                    // handlers sharing a 200-char boilerplate prefix into
                    // the same dedup bucket. Real handlers in app.js
                    // commonly start with the same `function(e){e
                    // .preventDefault();e.stopPropagation();var $this=$
                    // (this);...` prefix and only diverge at byte ~250+.
                    // Two distinct handlers got collapsed into one, so the
                    // dedup pass dropped a still-needed handler from
                    // service. Use the FULL handler source as the key —
                    // a few extra bytes per entry but correct.
                    var key = (h.selector || '__') + '\x00' +
                              (h.namespace || '') + '\x00' +
                              (h.handler ? h.handler.toString() : '');
                    if (seen[key]) { droppedTotal++; continue; }
                    seen[key] = true;
                    keepMask[i] = true;
                }
                // Second pass: rebuild list + new delegateCount.
                var newList = [];
                var newDelegateCount = 0;
                for (var j = 0; j < list.length; j++) {
                    if (!keepMask[j]) continue;
                    newList.push(list[j]);
                    if (j < delegateCount) newDelegateCount++;
                }
                if (newList.length !== list.length) {
                    list.length = 0;
                    for (var k = 0; k < newList.length; k++) list.push(newList[k]);
                    list.delegateCount = newDelegateCount;
                }
            });
            return droppedTotal;
        } catch (e) {
            if (window.console) console.warn('[htmx-bridge] dedupDocDelegates', e);
            return 0;
        }
    }
    window.__dedupDocDelegates = dedupDocDelegates;

    function replayReady(root) {
        if (!window.jQuery || !capturedReady.length) return;
        window.__htmxSwapping = true;
        // Snapshot length BEFORE replay so handlers that re-call
        // `$(document).ready(...)` inside themselves don't recurse forever.
        // We only replay what was already captured at the start of THIS swap;
        // anything added during the loop gets picked up by the NEXT swap.
        var snapshotLen = capturedReady.length;
        try {
            for (var i = 0; i < snapshotLen; i++) {
                try {
                    capturedReady[i].call(document, jQuery);
                } catch (err) {
                    if (window.console && console.warn) {
                        console.warn('[htmx-bridge] replayReady #' + i + ' threw', err);
                    }
                }
            }
        } finally {
            window.__htmxSwapping = false;
        }
    }

    // ---------- TimerMgr / SseMgr / WsMgr ---------------------------------------
    //
    // SPA resource managers. Each one owns a registry keyed by a string label.
    // Page code calls `TimerMgr.setInterval('quiz-tick', fn, 1000)` instead of
    // bare `setInterval(...)`. Re-running the page-init function under SPA
    // boost-nav (e.g. on every `pageReady`) is now idempotent: the second
    // call to `TimerMgr.setInterval` with the same label clears the previous
    // timer before installing the new one — no leak across navs.
    //
    // Optional `clearOnSwap` flag (default true) makes the resource get torn
    // down on `htmx:beforeRequest` (start of any boost nav). Set false for
    // timers/sockets that should survive the navigation (e.g. a chat connection
    // you want persistent — though those should go through LabsWS).
    //
    // Each manager exposes:
    //   .get(label)         -> the stored handle, or null
    //   .has(label)         -> bool
    //   .clear(label)       -> dispose the entry under that label
    //   .clearAll()         -> dispose everything (called on swap)
    //   .keys()             -> array of active labels (debugging)
    function makeMgr(disposeFn) {
        var reg = new Map();
        return {
            _reg: reg,
            _set: function (label, handle, opts) {
                if (reg.has(label)) {
                    try { disposeFn(reg.get(label).h); } catch (_) {}
                    reg.delete(label);
                }
                reg.set(label, { h: handle, opts: opts || {} });
                return handle;
            },
            get: function (label) { var e = reg.get(label); return e ? e.h : null; },
            has: function (label) { return reg.has(label); },
            clear: function (label) {
                var e = reg.get(label); if (!e) return;
                try { disposeFn(e.h); } catch (_) {}
                reg.delete(label);
            },
            clearAll: function (onlyClearOnSwap) {
                reg.forEach(function (e, label) {
                    if (onlyClearOnSwap && e.opts.clearOnSwap === false) return;
                    try { disposeFn(e.h); } catch (_) {}
                    reg.delete(label);
                });
            },
            keys: function () { return Array.from(reg.keys()); },
        };
    }

    var TimerMgr = (function () {
        var iv = makeMgr(function (h) { clearInterval(h); });
        var to = makeMgr(function (h) { clearTimeout(h); });
        return {
            setInterval: function (label, fn, ms, opts) {
                return iv._set(label, setInterval(fn, ms), opts);
            },
            setTimeout: function (label, fn, ms, opts) {
                return to._set(label, setTimeout(fn, ms), opts);
            },
            clearInterval: function (label) { iv.clear(label); },
            clearTimeout: function (label) { to.clear(label); },
            clearAll: function (onlyClearOnSwap) {
                iv.clearAll(onlyClearOnSwap);
                to.clearAll(onlyClearOnSwap);
            },
            keys: function () { return { intervals: iv.keys(), timeouts: to.keys() }; },
        };
    })();
    window.TimerMgr = TimerMgr;

    var SseMgr = (function () {
        var mgr = makeMgr(function (es) {
            try { if (es && typeof es.close === 'function') es.close(); } catch (_) {}
        });
        return {
            open: function (label, url, handlers, opts) {
                var es = new EventSource(url);
                if (handlers) {
                    Object.keys(handlers).forEach(function (evt) {
                        var fn = handlers[evt];
                        if (typeof fn === 'function') es.addEventListener(evt, fn);
                    });
                }
                return mgr._set(label, es, opts);
            },
            get: function (label) { return mgr.get(label); },
            has: function (label) { return mgr.has(label); },
            close: function (label) { mgr.clear(label); },
            closeAll: function (onlyClearOnSwap) { mgr.clearAll(onlyClearOnSwap); },
            keys: function () { return mgr.keys(); },
        };
    })();
    window.SseMgr = SseMgr;

    var WsMgr = (function () {
        var mgr = makeMgr(function (ws) {
            // Detach event handlers BEFORE close. STOMP wrappers sitting on
            // the same ws emit DISCONNECT / heartbeat / unsubscribe frames
            // out of `onopen`/`onclose` listeners — if we just call close()
            // those listeners still fire and `_transmit("DISCONNECT", …)`
            // ends up calling `ws.send(...)` on the CLOSING socket, which
            // throws InvalidStateError → the "WebSocket is already in
            // CLOSING or CLOSED state" console spam. Nulling the handlers
            // first short-circuits that path. We also skip the close()
            // entirely when the socket is already CLOSING/CLOSED.
            if (!ws) return;
            try { ws.onmessage = ws.onerror = ws.onclose = ws.onopen = null; } catch (_) {}
            try {
                var s = ws.readyState;
                if (s === WebSocket.OPEN || s === WebSocket.CONNECTING) ws.close();
            } catch (_) {}
        });
        return {
            open: function (label, url, handlers, opts) {
                // Idempotent: if a live socket with same label exists AND is
                // still OPEN/CONNECTING, return it. Caller can pass
                // `opts.force = true` to force reopen.
                var existing = mgr.get(label);
                if (existing && !(opts && opts.force)) {
                    var state = existing.readyState;
                    if (state === WebSocket.OPEN || state === WebSocket.CONNECTING) return existing;
                }
                var ws = new WebSocket(url);
                if (handlers) {
                    if (handlers.onopen)    ws.addEventListener('open',    handlers.onopen);
                    if (handlers.onmessage) ws.addEventListener('message', handlers.onmessage);
                    if (handlers.onclose)   ws.addEventListener('close',   handlers.onclose);
                    if (handlers.onerror)   ws.addEventListener('error',   handlers.onerror);
                }
                return mgr._set(label, ws, opts);
            },
            get: function (label) { return mgr.get(label); },
            has: function (label) { return mgr.has(label); },
            close: function (label) { mgr.clear(label); },
            closeAll: function (onlyClearOnSwap) { mgr.clearAll(onlyClearOnSwap); },
            keys: function () { return mgr.keys(); },
        };
    })();
    window.WsMgr = WsMgr;

    // ---------- ObserverMgr -----------------------------------------------------
    // For MutationObserver / IntersectionObserver that observe persistent
    // chrome (like document.documentElement for theme changes). Without
    // teardown, every htmx swap creates a new observer with closures over
    // detached DOM nodes from prior pages — observers stack and run on every
    // mutation. ObserverMgr.register('label', obs) replaces any prior
    // observer at the same label (calling disconnect first) and disposes
    // all clearOnSwap observers on the next nav.
    var ObserverMgr = (function () {
        var bag = new Map();
        return {
            register: function (label, obs, opts) {
                this.clear(label);
                bag.set(label, { obs: obs, clearOnSwap: (opts && opts.clearOnSwap === false) ? false : true });
                return obs;
            },
            clear: function (label) {
                var entry = bag.get(label);
                if (entry) { try { entry.obs.disconnect(); } catch (_) {} bag.delete(label); }
            },
            clearAll: function (onlyClearOnSwap) {
                bag.forEach(function (entry, label) {
                    if (onlyClearOnSwap && !entry.clearOnSwap) return;
                    try { entry.obs.disconnect(); } catch (_) {}
                    bag.delete(label);
                });
            },
        };
    })();
    window.ObserverMgr = ObserverMgr;

    // From here on, all listeners attach to htmx events. If htmx isn't
    // loaded on this page (admin pages use _admin_master.php which omits
    // htmx.min.js), bail out — but the Mgr / spaNavigate / IndicatorState
    // globals defined above ARE installed regardless, so app.js' use of
    // WsMgr / TimerMgr / etc. still works.
    if (!hasHtmx) return;

    // On htmx:beforeRequest (start of any boost nav), tear down resources
    // marked `clearOnSwap: true` (the default for the managers above).
    // Resources opted out via `{clearOnSwap: false}` survive the navigation.
    // NOTE: teardown ONLY runs for real boosted navigations. The href="#"
    // / javascript: / hash-anchor guard lives in the SECOND beforeRequest
    // listener (around line 901). Both listeners fire in REGISTRATION ORDER,
    // so a teardown here would run BEFORE that guard's preventDefault() —
    // every dropdown click (href="#") would kill TimerMgr / SseMgr / WsMgr /
    // ObserverMgr (so STOMP chat / deploy SSE / CPU polling / etc. all die
    // on a single dropdown click). The merged consolidated listener below
    // checks the href FIRST, bails on no-op clicks, then tears down.
    document.body.addEventListener('htmx:beforeRequest', function (e) {
        var el = e.detail && e.detail.elt;
        if (el && el.tagName === 'A') {
            var href = el.getAttribute('href');
            if (!href || href === '#' || href.indexOf('#') === 0 || /^javascript:/i.test(href)) {
                // No-op click — do NOT tear down. Let the second listener
                // (or capture-phase guard) preventDefault.
                return;
            }
        }
        try { TimerMgr.clearAll(true);   } catch (_) {}
        try { SseMgr.closeAll(true);     } catch (_) {}
        try { WsMgr.closeAll(true);      } catch (_) {}
        try { ObserverMgr.clearAll(true); } catch (_) {}
        // ZP#19: orphan LazyLoad bound to window (e.g. code/foryou.js does
        // `new LazyLoad($(window), ...)`) survives htmx swap because the
        // scroll listener sits on window, not on a swapped element. After
        // nav, the OLD callback fires on the NEW page's scroll, POSTs to
        // the wrong endpoint with the new page's filter values, server
        // returns 400, global ajaxComplete shows "Bad request" toast.
        //
        // BUT we cannot call LazyLoad.stopAll() unconditionally: the
        // paranoid sweep surfaced ~14 pages whose LazyLoad init has a
        // one-time data-attribute guard that would block re-creation on
        // subsequent navs (notifications.js already fixed). For those
        // element-bound LazyLoads, the element itself is destroyed by the
        // swap (`#main` contents get replaced), so the scroll listener
        // dies with its host — no orphan callback possible. Only the
        // window-bound ones are the actual risk.
        //
        // So: stop ONLY window/document-bound LazyLoad instances on every
        // beforeRequest. Element-bound ones are fine; they either die
        // with their element or live on in hx-preserve chrome (notif
        // dropdown) where their callback context is still valid.
        try {
            if (window.LazyLoad && LazyLoad.__instances) {
                LazyLoad.__instances.forEach(function (inst) {
                    try {
                        var el = inst && inst.element && inst.element[0];
                        if (el === window || el === document || el === document.body) {
                            inst.stop();
                        }
                    } catch (_) {}
                });
            }
        } catch (_) {}
        try { cleanupOpenModals('beforeRequest'); } catch (_) {}
        try { cleanupOpenTooltips('beforeRequest'); } catch (_) {}
        // R11.6 — Abort in-flight jQuery XHRs that the previous page kicked
        // off. app.js's $.ajaxPrefilter tags every $.ajax call into
        // window.__inflightXHRs; on boost-nav we drain them so their
        // late responses don't write into the new page's DOM. Only abort
        // when this beforeRequest came from an hx-boost click — programmatic
        // htmx.ajax() calls also fire beforeRequest but those are explicit
        // user-driven requests we want to keep running.
        if (e && e.detail && e.detail.boosted && window.__inflightXHRs) {
            window.__inflightXHRs.forEach(function (x) {
                try { x.abort('htmx-nav'); } catch (_) {}
            });
            window.__inflightXHRs.clear();
        }
        // R10-C5 FIX: cleanupOpenModals strips .show class + display:none
        // directly — it bypasses CoreUI's hide lifecycle so the
        // `hidden.coreui.modal` event never fires. dialog.js decrements
        // window.__dlgStack only inside that hidden handler. Net: every
        // SPA-nav with an open modal leaked the stack counter by 1.
        // After N such navs every subsequent Dialog rendered at z-index
        // 1055 + 10*N, eventually punching above the toast layer (~1300).
        // Reset the counter here so the next dialog opens at the base
        // layer regardless of how the prior one was disposed of.
        try { window.__dlgStack = 0; } catch (_) {}
        // R11 — DETACHED-CANVAS Chart sweep (morph-aware variant).
        //
        // Round-2 had a beforeRequest sweep that destroyed every Chart
        // attached to #main. Under R11 morph that's wrong: morph PRESERVES
        // canvases whose id matches the new response, so destroying their
        // Chart instances and forcing re-init would burn morph's win.
        //
        // But morph ALSO removes canvases that exist in the old #main
        // but NOT in the new #main (cross-page nav: /dashboard → /learn).
        // Chart.js doesn't auto-destroy when its canvas is removed from
        // the DOM — the Chart instance lingers in `Chart.instances` with
        // a dangling canvas reference. Verified live: 2 canvases on
        // /dashboard → nav to /learn (canvases removed) → nav back to
        // /dashboard (new canvases inserted by morph, same ids but FRESH
        // element identities) → per-canvas Chart.getChart(newCanvas)
        // misses the lingering prior (which still points at the detached
        // old canvas). New Chart() creates a 2nd instance per id → leak.
        //
        // Fix: enumerate Chart.instances and destroy ONLY those whose
        // canvas is no longer attached to the live document. Morph-
        // preserved canvases (still in DOM) keep their Chart untouched.
        try {
            if (window.Chart && Chart.instances) {
                Object.keys(Chart.instances).forEach(function (key) {
                    try {
                        var ch = Chart.instances[key];
                        if (!ch || !ch.canvas) return;
                        if (!document.contains(ch.canvas)) ch.destroy();
                    } catch (_) {}
                });
            }
        } catch (_) {}
    });

    // ---------- cleanupOpenModals --------------------------------------------
    //
    // Intern bug report #18 / #19 / #20 / #17 / #5 — symptom: open a CoreUI/
    // Bootstrap modal (syllabus unit content, discussion-syllabus-mention,
    // code-arena Surprise Me, quiz Generating, Add User form), then either
    // SPA-nav away OR open another modal on top, and the page ends up:
    //   - un-clickable / frozen (the .modal-backdrop persists on <body>
    //     with z-index 1050 and pointer-events:auto, eating every click)
    //   - locked overflow (body.modal-open + style="overflow:hidden;
    //     padding-right:15px" survive the swap)
    //   - showing two stacked modals (new modal opens while the old one's
    //     backdrop is still mounted)
    //
    // ROOT CAUSE: bootstrap/coreui mount .modal-backdrop divs OUTSIDE the
    // swap target (#main) — they're appended to <body>. The body also gets
    // the .modal-open class + inline overflow/padding-right styles. htmx
    // swaps #main and removes the .modal element itself, but the backdrop +
    // body styles are orphaned: there's no modal instance left to receive
    // hide() so the bootstrap teardown never runs.
    //
    // FIX: on every boosted navigation (beforeRequest) AND on every swap
    // (beforeSwap, belt-and-suspenders for non-boost SPA paths), proactively
    //   1. Hide every coreui.Modal instance still attached to a .modal.show
    //   2. Hide every bootstrap.Modal instance (defensive — most templates
    //      use coreui; bootstrap may still be loaded for legacy plugins)
    //   3. Force-remove orphaned .modal-backdrop divs from body
    //   4. Strip body.modal-open class + restore overflow / padding-right
    //   5. Hide any .modal.show elements that are about to be swapped away
    //      (so the next render starts from a clean visible state)
    //
    // Idempotent: running twice in a row finds nothing to clean on the 2nd pass.
    //
    // Also covers Bootstrap-4 jQuery-API ($('#m').modal('hide')) and modal-open-
    // class-on-html (some templates patch <html> instead of <body>).
    function cleanupOpenModals(when) {
        var cleaned = 0;
        try {
            // 1. CoreUI Modal instances
            if (window.coreui && coreui.Modal) {
                document.querySelectorAll('.modal').forEach(function (el) {
                    try {
                        var inst = coreui.Modal.getInstance(el);
                        if (inst) { inst.hide(); cleaned++; }
                    } catch (_) {}
                });
            }
            // 2. Bootstrap Modal instances (some legacy templates load bootstrap directly)
            if (window.bootstrap && window.bootstrap.Modal) {
                document.querySelectorAll('.modal').forEach(function (el) {
                    try {
                        var inst = window.bootstrap.Modal.getInstance(el);
                        if (inst) { inst.hide(); cleaned++; }
                    } catch (_) {}
                });
            }
            // 3. Bootstrap-4 jQuery API ($('#m').modal('hide'))
            if (window.jQuery && jQuery.fn && typeof jQuery.fn.modal === 'function') {
                try { jQuery('.modal.show, .modal.in').modal('hide'); } catch (_) {}
            }
        } catch (_) {}

        // 4. Force-strip orphaned backdrops + body lock state. ALWAYS run
        //    this — even if the hide()s above ran, coreui/bootstrap remove
        //    the backdrop asynchronously (after the fade-out animation),
        //    which is too late: the swap will fire and the backdrop survives.
        try {
            document.querySelectorAll('.modal-backdrop').forEach(function (b) {
                try { b.parentNode && b.parentNode.removeChild(b); } catch (_) {}
                cleaned++;
            });
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
            if (document.body.style.overflow === 'hidden') document.body.style.overflow = '';
            if (document.body.style.paddingRight) document.body.style.paddingRight = '';
            // Hide .modal.show elements about to be swapped — clears the
            // .show + aria-hidden + display style so the next render starts
            // clean without a stale state being read by re-init code.
            document.querySelectorAll('.modal.show, .modal.in').forEach(function (m) {
                try {
                    m.classList.remove('show');
                    m.classList.remove('in');
                    m.setAttribute('aria-hidden', 'true');
                    m.style.display = 'none';
                    cleaned++;
                } catch (_) {}
            });
            // 5. Remove orphan Dialog-pattern modals from #modalsGarbage.
            //    The Dialog class (workspace/js/plugins/dialog.js) clones
            //    #dummy-dialog-modal and appends to #modalsGarbage with a
            //    unique data-modal-id. #modalsGarbage has hx-preserve so it
            //    survives swaps — and unclosed dialogs survive with it.
            //    Result (intern bug #4): /services/mysql → Add User → leave
            //    open → SPA nav → /services/mysql → Add User → TWO forms.
            //    The dummy template itself MUST be preserved (id=
            //    'dummy-dialog-modal'); we only purge clones.
            var garbage = document.getElementById('modalsGarbage');
            if (garbage) {
                garbage.querySelectorAll('[data-modal-id]').forEach(function (m) {
                    if (m.id === 'dummy-dialog-modal') return;
                    try { m.parentNode.removeChild(m); cleaned++; } catch (_) {}
                });
            }
        } catch (_) {}

        if (cleaned && window.console && window.__htmxBridgeDebug) {
            console.debug('[htmx-bridge] cleanupOpenModals(' + when + ')', cleaned);
        }
        return cleaned;
    }
    // Expose for manual call from page code if needed (e.g. a flow that
    // wants to force-cleanup before opening a new modal).
    window.__htmxCleanupOpenModals = cleanupOpenModals;

    // ---------- cleanupOpenTooltips + popovers ---------------------------------
    //
    // Orphan-tooltip problem (round-7 fix): CoreUI/Bootstrap tooltips and
    // popovers append their floating element (`.tooltip.show` / `.popover.show`)
    // to <body> by default — NOT inside the trigger element. When htmx swaps
    // #main, the trigger element is destroyed but the tooltip's body-level
    // node survives, leaving a floating "Open Lab Dashboard (Admin Access)"
    // pill anchored to where the trigger used to be. Visible until the user
    // mouses over something else (which fires the next tooltip's hide,
    // happening to also clean up the orphan), or never.
    //
    // Sweep on beforeRequest: enumerate every visible tooltip/popover and
    // call its instance .hide() — falls back to direct DOM .remove() if
    // the instance can't be located (orphan whose trigger was already
    // garbage-collected in a prior partial swap). Also covers the case
    // where a tooltip was triggered via jQuery `.tooltip('show')` rather
    // than via CoreUI's data-attribute autoinit — both leave nodes at
    // body level.
    function cleanupOpenTooltips(when) {
        var cleaned = 0;
        try {
            // 1. Tooltips currently showing. Iterate over body-level nodes
            //    rather than `[data-coreui-toggle="tooltip"]` triggers
            //    because the triggers may already be detached.
            var nodes = document.querySelectorAll(
                '.tooltip.show, .tooltip.fade.show, ' +
                '.popover.show, .popover.fade.show, ' +
                'div.tooltip, div.popover'  // any leftover floating tooltip/popover wrapper
            );
            for (var i = 0; i < nodes.length; i++) {
                var node = nodes[i];
                // Best effort: ask CoreUI/Bootstrap for the controlling
                // instance via the linked trigger (`aria-describedby` /
                // `aria-labelledby` points to this node's id from the
                // trigger), and call its dispose.
                try {
                    var nodeId = node.id;
                    var trigger = nodeId
                        ? document.querySelector('[aria-describedby="' + nodeId + '"], [aria-labelledby="' + nodeId + '"]')
                        : null;
                    if (trigger && window.coreui) {
                        var ttInst = coreui.Tooltip && coreui.Tooltip.getInstance && coreui.Tooltip.getInstance(trigger);
                        if (ttInst) { ttInst.hide(); ttInst.dispose && ttInst.dispose(); cleaned++; continue; }
                        var poInst = coreui.Popover && coreui.Popover.getInstance && coreui.Popover.getInstance(trigger);
                        if (poInst) { poInst.hide(); poInst.dispose && poInst.dispose(); cleaned++; continue; }
                    }
                } catch (_) {}
                // Fallback: trigger is gone / instance unreachable. Remove
                // the floating DOM node directly.
                try { node.parentNode && node.parentNode.removeChild(node); cleaned++; } catch (_) {}
            }

            // 2. Reset aria-describedby on any surviving triggers so future
            //    re-hover doesn't try to point at a destroyed node id.
            try {
                var stale = document.querySelectorAll('[aria-describedby^="tooltip"], [aria-describedby^="popover"]');
                for (var j = 0; j < stale.length; j++) {
                    var ad = stale[j].getAttribute('aria-describedby');
                    if (ad && !document.getElementById(ad)) {
                        stale[j].removeAttribute('aria-describedby');
                    }
                }
            } catch (_) {}
        } catch (_) {}

        if (cleaned && window.console && window.__htmxBridgeDebug) {
            console.debug('[htmx-bridge] cleanupOpenTooltips(' + when + ')', cleaned);
        }
        return cleaned;
    }
    window.__htmxCleanupOpenTooltips = cleanupOpenTooltips;

    // ---------- MasonryMgr ------------------------------------------------------
    //
    // Single owner for every Masonry instance in the app. Page-level JS used to
    // run `var $grid = $('#masonry-area').masonry({...})` directly — that left:
    //   - stale `$grid` refs after htmx swap (old DOM gone, ops became no-ops)
    //   - duplicate inits when both the bridge and page code touched the same
    //     container in the same swap
    //   - missing imagesLoaded recalc, so first paint had wrong heights (cards
    //     overflowed the container, sticky-bottom server-logs strip overlaid
    //     them mid-page)
    //   - no listener for container resize / late content
    //
    // MasonryMgr fixes all four:
    //   - `get(sel)` looks up the live Masonry instance, attached to the DOM
    //     via $.data(). After swap it returns the NEW instance, never stale.
    //   - `scan(root)` is idempotent (data-masonryReady flag + .data lookup),
    //     called from `reinit()` on every swap.
    //   - Init runs inside imagesLoaded so first layout uses real heights,
    //     and `imagesLoaded.progress` re-layouts on each image arrival.
    //   - ResizeObserver on the grid + a MutationObserver on #main catch
    //     resize + new-grid-inserted cases.
    //
    // Page-level migration path:
    //   OLD: var $grid = $('#masonry-area').masonry({...});
    //        $grid.append(html).masonry('appended', html).masonry('layout');
    //   NEW: MasonryMgr.append('#masonry-area', html);
    //        // get() returns live instance:
    //        var $grid = MasonryMgr.$grid('#masonry-area');

    // R11.52 — flip entrance/exit styles, defined in the OUTER bridge scope so
    // BOTH the MasonryMgr IIFE (BASE.hiddenStyle/visibleStyle, below) AND
    // revealInitialItems()/reinitAndRevealReadyGrids() — which live OUTSIDE the
    // IIFE — can read them. R11.51 defined these INSIDE the IIFE, but
    // revealInitialItems is outside it, so `instance.options.hiddenStyle =
    // FLIP_HIDDEN` threw a ReferenceError the surrounding try/catch swallowed —
    // so instance.reveal() never ran on FIRST LOAD and the initial cards never
    // flipped in (append/back-nav still flipped via BASE.hiddenStyle, masking
    // the bug). flipInX/flipOutX feel: edge-on (rotateX 90°) → face-on (0°).
    var FLIP_HIDDEN  = { opacity: 0, transform: 'perspective(400px) rotateX(90deg)' };
    var FLIP_VISIBLE = { opacity: 1, transform: 'perspective(400px) rotateX(0deg)' };
    var MasonryMgr = (function () {
        // Every grid the app uses. Keep in sync with PHP templates that
        // render `.row` containers for the bridge to auto-init.
        var SELECTORS = [
            '#masonry-area',
            '#masonry-area1',
            '#labcard-masonry-area',
            '#discussion-masonry',
            '#discussion-search-masonry',
            '#clan-masonry-area',
            '#code-masonry-area',
            '#evaluate-grid',
            '#roadmap-masonry',
            '#event-challenge-mansory',  // sic — typo in templates
            '#devices-masonry-area',
            '#domains-masonry-area',
            '#network-masonry-area',
            '.club-feed-grid',
        ];
        // Per-grid options.
        //
        // Round-6 fix (2026): BASE used to set `transitionDuration: 0`
        // (to kill jiggle from imagesLoaded / ResizeObserver pulses), but
        // this ALSO killed Masonry's built-in entrance animation
        // (hiddenStyle:{opacity:0,scale:0.001} → visibleStyle:{opacity:1,
        // scale:1} over the same transitionDuration). Result: cards
        // appeared flat instead of zooming in, which is the "beautiful"
        // behavior the user wanted to keep on /learn, /quiz/evaluate,
        // /clans, etc.
        //
        // Restoring 0.4s here brings back the zoom entrance everywhere
        // BASE applies. The three grids that genuinely needed flat-no-
        // animation (#discussion-masonry, #discussion-search-masonry,
        // #labcard-masonry-area) have explicit per-grid overrides below
        // that keep transitionDuration: 0 — so the flicker fix the user
        // asked for there is preserved.
        //
        // Jiggle trade-off: subsequent layout pulses (image loads,
        // sidebar collapse) on BASE grids will now animate position
        // changes too. The original "0 everywhere" comment was wrong
        // about prod — prod uses Masonry's 0.4s default. Match prod.
        // R11.51 — SINGLE SOURCE OF TRUTH for the entrance/exit animation.
        // Masonry's reveal() (entrance: initial + appended/lazy) and hide()
        // (exit: removed / tab-switch) both transition between these two styles.
        // Change them here → every grid's flip changes everywhere. This is a
        // flipInX/flipOutX feel: the card starts edge-on (rotateX 90°, invisible)
        // and flips to face-on (rotateX 0°). perspective(400px) matches
        // animate.css flipInX's depth. (Was scale 0.001↔1 = the old "zoom".)
        //
        // BLUR: a 3D rotateX on the .col fights the child .card.blur::before
        // backdrop-filter. We do NOT suppress it in JS — app.scss gates it on
        // the transform itself: `.col[style*="rotateX"] .card.blur::before
        // { opacity:0 }`. Masonry writes this transform inline ONLY during the
        // flip (positions use left/top via layoutInstant), then cleans it to
        // none when settled, so the glass fades back in automatically. Covers
        // initial + appended + removed centrally, no per-page wiring.
        // FLIP_HIDDEN/FLIP_VISIBLE are defined in the OUTER scope (just above the
        // MasonryMgr IIFE) so revealInitialItems() — which lives outside this
        // IIFE — can read them too. See R11.52 note there. BASE resolves them via
        // closure to that outer scope.
        var BASE = {
            itemSelector: '.col',
            percentPosition: true,
            transitionDuration: '0.4s',
            // R11.49 — layoutInstant makes masonry('layout') SNAP items into
            // position with no translate animation. The animated layout (the
            // "jiggle trade-off" noted above) is what made cards slide in from
            // the top as images loaded (imagesLoaded → masonry('layout') every
            // image) — the user's "all cards start from the first card and
            // move" jank. transitionDuration:0.4s is kept, so reveal() (append /
            // lazy flip-in) and hide() (remove flip-out) STILL animate. Net:
            // initial + reflow layouts are instant; add/remove still animate.
            layoutInstant: true,
            // R11.51 — flip entrance/exit (see FLIP_HIDDEN/FLIP_VISIBLE above).
            hiddenStyle: FLIP_HIDDEN,
            visibleStyle: FLIP_VISIBLE,
        };
        var OPTIONS = {
            DEFAULT: BASE,
            // Discussion masonry uses Bootstrap col-md-6/col-12 children
            // and a hidden `.d-masonry-sizer` driver element. The grid
            // doesn't carry `row-cols-md-2`-style tokens (its class is just
            // `row questions-section`), so our row-cols-* based column
            // counter would resolve to 1 and stack everything in one column.
            // Using `.d-masonry-sizer` as columnWidth (matches what
            // questions.js does on first init) lets Masonry sample the
            // sizer's actual width — which the template sets to 50% in
            // grid view, 100% in list view.
            '#discussion-masonry':         { itemSelector: '.col-md-6, .col-12', columnWidth: '.d-masonry-sizer', percentPosition: true, transitionDuration: 0 },
            '#discussion-search-masonry':  { itemSelector: '.col-md-6', columnWidth: '.col-md-6', percentPosition: true, transitionDuration: 0 },
            '#labcard-masonry-area':       { itemSelector: '.col', percentPosition: true, transitionDuration: 0 },
        };

        function optsFor(grid) {
            return OPTIONS[grid.id ? '#' + grid.id : ''] || OPTIONS.DEFAULT;
        }

        // Bootstrap row-cols-* breakpoints are media-query-driven, NOT
        // container-driven — `row-cols-xl-3` activates when the VIEWPORT
        // is >= 1200, regardless of the grid container width. We were
        // computing against the container width and disagreeing with
        // Bootstrap's CSS (e.g. viewport 1426, container 1066: CSS picks
        // xl-3 = 3 cols at 33% of 1066 = 355 each, but our container check
        // 1066 < 1200 dropped down to md-2 = 2 cols at 533 each).
        // Use viewport here so the resolved column count matches what
        // Bootstrap's .col % rule produces at the same breakpoint.
        function expectedCols(_containerWUnused, classes) {
            var vw = window.innerWidth;
            if (vw >= 1200 && /row-cols-xl-(\d)/.test(classes)) return +RegExp.$1;
            if (vw >=  992 && /row-cols-lg-(\d)/.test(classes)) return +RegExp.$1;
            if (vw >=  768 && /row-cols-md-(\d)/.test(classes)) return +RegExp.$1;
            if (vw >=  576 && /row-cols-sm-(\d)/.test(classes)) return +RegExp.$1;
            if (/row-cols-(\d)/.test(classes)) return +RegExp.$1;
            return 1;
        }
        function computeColumnWidth(grid) {
            var w = grid.getBoundingClientRect().width;
            var want = expectedCols(w, grid.className || '');
            return want > 0 ? w / want : null;
        }

        // In-place reflow: option-merge a fresh numeric columnWidth, then
        // layout. NO destroy. No position-stripping. No animation glitch.
        // Rate-limited per grid via `__smartRefreshPending` so a
        // MutationObserver burst + afterSwap reinit can't double-fire it
        // within one frame.
        //
        // CRITICAL: we also re-assert `position: relative` on the grid here
        // because htmx's swap can land before the bridge's first scan
        // (MutationObserver races afterSwap), and on certain boost-nav
        // sequences (challenge → machine → challenge) the inline style we
        // set in initOne gets cleared somewhere between swap and our
        // re-init. Without `position: relative` on the grid, Bootstrap's
        // `.col { width: 33.33% }` resolves against body (1426px), giving
        // 475px cards that pack 2-per-row instead of 3.
        function smartRefresh(grid) {
            if (grid.__smartRefreshPending) return;
            grid.__smartRefreshPending = true;
            requestAnimationFrame(function () {
                grid.__smartRefreshPending = false;
                try {
                    if (grid.style.position !== 'relative') grid.style.position = 'relative';
                    var $g = jQuery(grid);
                    if (!$g.data('masonry')) return;

                    // R11.14 — reloadItems BEFORE layout. Under R11 morph
                    // swap, when a swap arrives carrying new children into a
                    // pre-existing #masonry-area, masonry's internal item
                    // list is stale (it still tracks the OLD page's cards).
                    // .masonry('layout') alone re-positions the OLD items,
                    // ignoring the new ones — visible symptom: some new
                    // cards end up at the OLD page's calculated positions
                    // (off-grid, off-screen, or stacked on each other).
                    // reloadItems re-reads the children from the DOM into
                    // masonry's items[] before layout positions them.
                    try { $g.masonry('reloadItems'); } catch (_) {}

                    // Only override columnWidth if the grid is using the
                    // row-cols-* mechanism (where we KNOW how to compute it).
                    // Grids configured with a selector-based columnWidth
                    // (e.g. '#discussion-masonry' → '.d-masonry-sizer') are
                    // managed by their own page JS; overriding their
                    // columnWidth with our row-cols-* heuristic stacks
                    // every card in one column because the grid lacks the
                    // row-cols-* tokens and expectedCols falls back to 1.
                    var pageOpts = optsFor(grid);
                    if (typeof pageOpts.columnWidth === 'string' && pageOpts.columnWidth.charAt(0) === '.') {
                        // Selector-driven sizing — just re-layout, don't touch columnWidth.
                        $g.masonry('layout');
                        return;
                    }

                    var cw = computeColumnWidth(grid);
                    if (!cw) return;
                    // .masonry('option', ...) merges into this.options; next
                    // .masonry('layout') call uses the new numeric columnWidth
                    // and re-positions items without ever stripping them.
                    $g.masonry('option', { columnWidth: cw });
                    $g.masonry('layout');
                } catch (_) {}
            });
        }

        function initOne(grid) {
            if (!window.Masonry) return null;
            if (grid.dataset.masonryReady === '1') {
                // R11.33 — for already-init grids, just return the existing
                // instance. DO NOT call smartRefresh.
                //
                // smartRefresh was firing on every MutationObserver fire (which
                // happens for every DOM change under body.subtree). During an
                // in-page tab swap, that meant every card removal + every card
                // append triggered smartRefresh, which did reloadItems +
                // option + layout. reloadItems re-syncs items[] from CURRENT
                // DOM — while old cards are mid-removal-animation they're
                // still in DOM, so they got re-added to items[]. Subsequent
                // layout positioned new cards AFTER the old slots, giving
                // new1 a translate3d Y of ~1164px (bottom of column). User
                // perception: "appears under content then flies up".
                //
                // Pages that need an explicit refresh can call
                // $grid.masonry('layout') directly. The bridge no longer
                // auto-intervenes for already-init grids.
                return jQuery(grid).data('masonry') || jQuery.data(grid, 'masonry');
            }
            try {
                // CRITICAL: Bootstrap's row-cols-X-N sets `.col { width: (100/N)% }`.
                // When Masonry sets the item to `position: absolute`, that `%`
                // resolves against the *nearest positioned ancestor*, NOT
                // the masonry grid itself (because Outlayer/Masonry 4 does
                // NOT set `position: relative` on the container automatically).
                // Result: every `.col` ends up width = 33.33% of body/html
                // (~475px at 1426 viewport) instead of 33.33% of the grid's
                // 1138px (~379px) — and the grid then visually shows 2 cards
                // per row because 475 + 475 fits but 475 * 3 overflows.
                // Forcing the grid `position: relative` makes Bootstrap's
                // percent widths resolve correctly against it.
                if (grid.style.position !== 'relative') grid.style.position = 'relative';

                // Compute numeric columnWidth UP FRONT so Masonry's first
                // layout uses the correct value instead of sampling `.col`
                // from a mid-transition DOM. Outlayer's first layout() is
                // instant (isInstant = !_isLayoutInited) — it positions every
                // item at its final left/top with NO transition, so the initial
                // paint is already static / in place.
                var opts = Object.assign({}, optsFor(grid));
                var cw = computeColumnWidth(grid);
                if (cw) opts.columnWidth = cw;
                var $grid = jQuery(grid).masonry(opts);

                // R11.53 — re-sync a SURVIVING instance to the swapped-in DOM
                // before revealing. Under htmx boost/morph forward-nav (e.g.
                // /labs/machine → /labs/challenge, SAME grid id), idiomorph
                // PRESERVES the grid element AND jQuery's masonry instance (id
                // match) but REPLACES the .col children. The surviving instance
                // still holds the previous page's items[] (e.g. 7 machine cards),
                // so `.masonry(opts)` above returns it WITHOUT re-reading the DOM
                // — revealInitialItems would then flip only those 7 stale items
                // while the morph-ADDED cards (13 more) never reveal. Symptom:
                // "only the first ~6 cards animate, regardless of card count".
                // reloadItems re-reads the CURRENT .col set into items[]; layout
                // (instant via layoutInstant) positions them; revealInitialItems
                // then flips the FULL current set. Guarded on a count mismatch so
                // a fresh construct (items already synced) skips the redundant
                // reload+layout — and so already-synced morph-nav (count
                // unchanged) doesn't churn.
                try {
                    var _inst = $grid.data('masonry');
                    var _domN = grid.querySelectorAll(opts.itemSelector || '.col').length;
                    if (_inst && _inst.items && _inst.items.length !== _domN) {
                        $grid.masonry('reloadItems');
                        $grid.masonry('layout');
                    }
                } catch (_) {}

                // R11.51 — initial cards FLIP IN PLACE (flipInX entrance).
                //
                // revealInitialItems runs masonry.reveal() on the initial cards:
                // rotateX 90°→0° + opacity 0→1 at each card's FINAL left/top (no
                // translate). It also (a) bumps transitionDuration to 0.4s,
                // (b) sets layoutInstant:true so the SEPARATE jank — animated
                // layout() reflow on imagesLoaded (cards sliding across) — is
                // instant, and (c) sets the flip hiddenStyle/visibleStyle on the
                // instance. Net: flip-in entrance, no position slide. Order
                // matters: reveal applies hiddenStyle (opacity:0) then ready
                // lifts the visibility gate while items are still opacity:0, so
                // there's no full-opacity flash before the flip-in.
                revealInitialItems($grid);
                grid.dataset.masonryReady = '1';

                // One belt-and-braces smartRefresh after next paint in case
                // the container width was 0 at init (display:none → block
                // tab swap). rAF-debounced.
                smartRefresh(grid);

                // R11.33 — REMOVED imagesLoaded + ResizeObserver auto-triggers.
                //
                // These were firing smartRefresh continuously during in-page
                // tab swaps:
                //   - Old cards removed (grid shrinks) → ResizeObserver fires
                //   - New cards appended (grid grows) → ResizeObserver fires
                //   - Each image loads → imagesLoaded progress fires
                // Each event calls smartRefresh which does
                // reloadItems + option + layout. reloadItems re-syncs masonry's
                // items[] from current DOM. Old cards are still in DOM mid-
                // removal-animation → they get RE-ADDED to items[] → masonry
                // positions new cards after the old cards' slots → new cards
                // end up at slot Y = column_height (~1164px for 12 cards).
                //
                // Live trace evidence on /learn tab switch: smartRefresh fired
                // at t=45, 449, 528, 1579, 2064 (~every 500ms during animation).
                // Each fire poisoned the slot calculation for new cards.
                //
                // Pages that need explicit layout refresh on image load (rare)
                // can call $grid.imagesLoaded().progress(() => $grid.masonry('layout'))
                // themselves. The bridge no longer auto-interferes.
                return $grid;
            } catch (e) {
                if (window.console) console.warn('[MasonryMgr] init failed for', grid, e);
                return null;
            }
        }

        // Idempotent batch init within `root` (DOM subtree, defaults to document).
        function scan(root) {
            root = root || document;
            if (!window.Masonry) return;
            var nodes = root.querySelectorAll(SELECTORS.join(','));
            nodes.forEach(function (g) { initOne(g); });
        }

        function find(sel) {
            return document.querySelector(sel);
        }

        return {
            scan: scan,
            init: function (sel) {
                var el = find(sel); if (!el) return null;
                return initOne(el);
            },
            $grid: function (sel) {
                var el = find(sel); if (!el) return null;
                if (el.dataset.masonryReady !== '1') initOne(el);
                return jQuery(el);
            },
            get: function (sel) {
                var el = find(sel); if (!el) return null;
                return jQuery(el).data('masonry') || jQuery.data(el, 'masonry');
            },
            layout: function (sel) {
                var $g = this.$grid(sel); if (!$g) return;
                try { $g.masonry('layout'); } catch (_) {}
            },
            append: function (sel, $items) {
                var $g = this.$grid(sel); if (!$g) return;
                try { $g.append($items).masonry('appended', $items).masonry('layout'); } catch (_) {}
            },
            remove: function (sel, $items) {
                var $g = this.$grid(sel); if (!$g) return;
                try { $g.masonry('remove', $items).masonry('layout'); } catch (_) {}
            },
            // Tear down the instance + observers. Useful before swap if the
            // DOM is being removed — htmx swap naturally drops the old grid
            // so this is mostly defensive.
            destroy: function (sel) {
                var el = find(sel); if (!el) return;
                try { jQuery(el).masonry('destroy'); } catch (_) {}
                if (el.__masonryRO) { try { el.__masonryRO.disconnect(); } catch (_) {} delete el.__masonryRO; }
                delete el.dataset.masonryReady;
            },
        };
    })();
    window.MasonryMgr = MasonryMgr;

    // MutationObserver on document.body — catches grids that are injected
    // by JS (search results, infinite scroll, modal content) and need init
    // outside of an htmx swap. Throttled via requestAnimationFrame so a
    // burst of DOM mutations only triggers one scan per frame.
    if (window.MutationObserver) {
        var moScheduled = false;
        var mo = new MutationObserver(function () {
            if (moScheduled) return;
            moScheduled = true;
            (window.requestAnimationFrame || setTimeout)(function () {
                moScheduled = false;
                MasonryMgr.scan(document);
            }, 0);
        });
        // Wait for body to exist (bridge loads in head sometimes)
        function startMO() {
            if (document.body) mo.observe(document.body, { childList: true, subtree: true });
            else setTimeout(startMO, 50);
        }
        startMO();
    }

    // R11.38 — masonry's native reveal animation for initial items.
    //
    // For both first-load and browser-back, apply masonry's hiddenStyle to
    // all items then call instance.reveal(items). Masonry handles the
    // transition exactly like an appended item — same easing, same code
    // path. No CSS keyframe to conflict with masonry's own animation.
    function revealInitialItems($grid) {
        try {
            var instance = $grid && $grid.data && $grid.data('masonry');
            if (!instance || !instance.items || !instance.items.length) return;

            // R11.47 — Masonry's item.transition() (Outlayer source line 1070)
            // already applies args.from (hiddenStyle) then force-reflows
            // (var h = this.element.offsetHeight) before transitioning to
            // args.to (visibleStyle). The manual hiddenStyle write + offsetWidth
            // poke we had before was redundant — AND it set item.isHidden=true
            // which created a stale-state race on rapid tab-switch:
            //   1. revealInitialItems flags item.isHidden=true, sets opacity:0
            //   2. masonry.reveal() begins transition opacity:0 → 1 (400ms)
            //   3. user clicks tab-switch handler at ~50ms
            //   4. handler calls masonry('remove', items) → item.hide() which
            //      sets isHidden=true (already true) + transitions to
            //      hiddenStyle. Browser sees item already at hiddenStyle →
            //      zero-distance transition → transitionEnd may NOT fire →
            //      removeElem never runs → orphan .col stays in DOM.
            // Letting masonry own the entire transition pipeline avoids the
            // stale-flag race.

            // R11.44 — some grids (#labcard-masonry-area, #discussion-masonry,
            // #discussion-search-masonry) had transitionDuration: 0 in their
            // bridge options for legacy "no fly-up" reasons (fixed in R11.33).
            // With duration 0 masonry's reveal AND append-reveal both apply
            // visibleStyle INSTANTLY — no animation on init OR lazy load.
            // Permanently bump to 0.4s so lazy-loaded cards also animate via
            // masonry's native pipeline. The legacy fly-up race is long gone.
            var originalDuration = instance.options.transitionDuration;
            var needsBump = !originalDuration || originalDuration === 0 || originalDuration === '0' || originalDuration === '0s';
            if (needsBump) {
                instance.options.transitionDuration = '0.4s';
            }
            // R11.49 — force layoutInstant on the instance too. Grids inited
            // DIRECTLY by page scripts (quiz.js / code.js do
            // $('#masonry-area').masonry({...}) without layoutInstant, then
            // re-layout on imagesLoaded) won't pick up BASE.layoutInstant, so
            // set it here on whatever instance this grid ended up with. Makes
            // every masonry('layout') (image-load reflow, smartRefresh) snap
            // instead of slide; reveal()/hide() still animate (add/remove).
            instance.options.layoutInstant = true;
            // R11.51 — flip entrance. Set the flip styles on THIS instance (the
            // one central path that also reaches page-script-inited grids like
            // quiz/code/roadmap and the per-grid OPTIONS grids that optsFor
            // REPLACES — neither inherits BASE) before revealing. reveal() then
            // transitions the initial SSR cards from FLIP_HIDDEN (rotateX 90°,
            // opacity 0) to FLIP_VISIBLE (rotateX 0°) IN PLACE at each card's
            // final left/top — a flipInX entrance. The blur is gated off during
            // the flip by the app.scss rule keyed on the inline rotateX
            // transform (see FLIP_HIDDEN note near BASE). reveal() also animates
            // the BFCache pre-hide (rotateX 90° applied by
            // reinitAndRevealReadyGrids) back, so back-nav flips in the same way.
            //
            // NOT the jank the user hated — that was the ANIMATED layout()
            // reflow on imagesLoaded (cards sliding as image heights changed),
            // now instant via layoutInstant:true above. The flip is in place.
            instance.options.hiddenStyle  = FLIP_HIDDEN;
            instance.options.visibleStyle = FLIP_VISIBLE;
            instance.reveal(instance.items);
        } catch (_) {}
    }

    // R11.38 / R11.50 — on browser back/forward + BFCache restore, force a
    // clean masonry init so the grid re-inits and replays the flip-in entrance.
    //
    // Why force re-init: htmx history restore replaces grid via innerHTML.
    // The new DOM elements may carry data-masonry-ready="1" from the cache
    // but the masonry JS INSTANCE is gone (innerHTML wipes the jQuery .data
    // cache). So $grid.data('masonry') returns null and the grid is unmanaged.
    //
    // Reset data-masonry-ready=0 → MasonryMgr.scan calls initOne → masonry
    // re-inits → revealInitialItems reveal()s the items from the hiddenStyle
    // pre-hide applied below. Single code path for first-load AND back-nav.
    function reinitAndRevealReadyGrids() {
        try {
            var sel = '#masonry-area, #masonry-area1, #evaluate-grid, ' +
                      '#labcard-masonry-area, #discussion-masonry, #discussion-search-masonry, ' +
                      '#code-masonry-area, #clan-masonry-area, #event-challenge-mansory, ' +
                      '.club-feed-grid';
            document.querySelectorAll(sel).forEach(function (g) {
                if (!g.querySelector('.col, .col-md-6, .col-12')) return;
                // Force re-init: drop the ready flag so initOne doesn't bail.
                delete g.dataset.masonryReady;
                // R11.51 — Apply the flip hiddenStyle (opacity:0 + rotateX 90°)
                // SYNCHRONOUSLY before paint so back-nav-restored cards (a) don't
                // flash at full opacity before re-init, and (b) start from the
                // flip entry state. revealInitialItems' reveal() then flips them
                // back to face-on, so browser-back gets the SAME flipInX entrance
                // as a fresh open. (rotateX in the inline style also triggers the
                // app.scss blur gate, so the glass stays off through the flip.)
                var items = g.querySelectorAll('.col, .col-md-6, .col-12');
                items.forEach(function (el) {
                    el.style.opacity = '0';
                    el.style.transform = 'perspective(400px) rotateX(90deg)';
                });
            });
            // Trigger init on next frame; initOne's tail call to
            // revealInitialItems handles the animation.
            requestAnimationFrame(function () {
                MasonryMgr.scan(document);
            });
        } catch (_) {}
    }
    // R11.47 — guard against popstate × htmx:historyRestore double-init race.
    //
    // On browser back, popstate fires almost immediately while htmx then
    // emits htmx:historyRestore once it finishes the swap. Without a guard,
    // both gates open within ~16ms and BOTH ends call MasonryMgr.scan +
    // page-script's __init runs again → two competing Masonry instances
    // on the same grid + a leaked ResizeObserver from the first one.
    //
    // Set a window flag during the historyRestore window so popstate's
    // reinitAndRevealReadyGrids is a no-op while htmx handles the restore
    // through its proper afterSwap-like path.
    document.body.addEventListener('htmx:beforeHistoryRestore', function () {
        window.__historyRestoring = true;
    });
    document.body.addEventListener('htmx:historyRestore', function () {
        // Clear after the synchronous historyRestore handler at line 2194
        // has had a chance to run reinit + scan via the htmx code path.
        setTimeout(function () { window.__historyRestoring = false; }, 50);
    });
    // popstate fires on browser back/forward (htmx history restore or
    // native). pageshow fires on BFCache restore (gate with persisted).
    window.addEventListener('popstate', function () {
        if (window.__historyRestoring) return;
        reinitAndRevealReadyGrids();
    });
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) reinitAndRevealReadyGrids();
    });

    // ---------- progress bar -----------------------------------------------------
    // Progress bar CSS is INJECTED FROM HERE (bridge) instead of living
    // inline in _master.php. Why: an inline <style> block in <head>
    // has no cache-buster, and under SPA-boost morph nav <head> is
    // never re-rendered — so any inline CSS edit only reaches users on
    // a full page reload (which they get for free on the next deploy
    // because htdocs/js/htmx-bridge.js is mtime-cache-busted, so the
    // <script src=...?_=$bridge_mtime> URL changes → fresh JS → fresh
    // CSS injection).
    //
    // R11.12 — disable htmx's default indicator styles entirely.
    // htmx 2.0.10 (upgraded from 2.0.4 in R11) added `visibility: hidden`
    // to its default `.htmx-indicator` rule. When the request completes
    // and `htmx-request` is removed, visibility:hidden kicks in INSTANTLY
    // and the bar becomes invisible — regardless of opacity/scaleX.
    // Setting this BEFORE htmx scans the DOM prevents that style block.
    if (window.htmx && window.htmx.config) {
        window.htmx.config.includeIndicatorStyles = false;
        // R11.16 — disable htmx's CSS-transition / settle behavior entirely.
        // htmx's default settle pipeline waits `defaultSettleDelay` ms (20ms)
        // after swap to let CSS transitions complete, and applies "settled"
        // attributes (class/style/width/height) through the settle phase.
        // For masonry that means: morph swaps in new cards with old inline
        // style.top/transform → htmx holds those styles through settle →
        // browser paints OLD positions → settle expires → page-init runs
        // masonry → cards JUMP to new positions. Visible as a 500px Y jank.
        // We want htmx to swap atomically and let our jQuery + CSS handle
        // any visual transitions ourselves (which is how the masonry + CSS
        // R9 reveal animation are already designed). Per CLAUDE.md rule 10
        // (bridge is the seam), this is the bridge enforcing that boundary.
        window.htmx.config.attributesToSettle = [];
        window.htmx.config.defaultSettleDelay = 0;
        window.htmx.config.defaultSwapDelay = 0;
        window.htmx.config.globalViewTransitions = false;
    }
    (function injectProgressCSS() {
        // Replace any existing block — bridge JS is mtime-cache-busted,
        // so reaching this code at all means we have the latest CSS.
        var existing = document.getElementById('snl-htmx-progress-css');
        if (existing) existing.parentNode.removeChild(existing);
        var style = document.createElement('style');
        style.id = 'snl-htmx-progress-css';
        // R11.10 — use TWO separate keyframe animations instead of class-
        // transition pattern. The class-transition approach was unreliable
        // because cancelling the running animation didn't give the browser
        // a stable "from" value to transition out of — scaleX snapped
        // instantly to 1.0 regardless of the transition duration. Two
        // independent animations with explicit keyframes work consistently.
        style.textContent = [
            '@keyframes snlHtmxProgressRunning {',
            '  0%   { transform: scaleX(0.05); }',
            '  15%  { transform: scaleX(0.30); }',
            '  40%  { transform: scaleX(0.60); }',
            '  70%  { transform: scaleX(0.80); }',
            '  100% { transform: scaleX(0.90); }',
            '}',
            // R11.13 — snappier exit. R11.12 held the full bar for
            // 1000ms which felt sluggish. Tightened to 200ms hold +
            // 250ms fade over a 600ms animation. Still gives the user
            // a clear "done" signal but doesn't linger.
            //
            // Timeline (600ms total):
            //   0-100ms:    scaleX rapidly fills 0.30 → 1.00 (17%)
            //   100-300ms: hold at full bar (33%, 200ms)
            //   300-600ms: fade opacity 1 → 0 (50%, 300ms)
            '@keyframes snlHtmxProgressDone {',
            '  0%   { transform: scaleX(0.30); opacity: 1; }',
            '  17%  { transform: scaleX(1.00); opacity: 1; }',
            '  50%  { transform: scaleX(1.00); opacity: 1; }',
            '  100% { transform: scaleX(1.00); opacity: 0; }',
            '}',
            // R11.12 — visibility:visible !important MANDATORY because
            // htmx 2.0.10 ships `.htmx-indicator { visibility: hidden }`
            // in its default styles. Even after disabling
            // includeIndicatorStyles (above), this guards against any
            // surviving stale style block in users' browsers from before
            // the bridge ran. THIS IS THE FIX for the "bar dies at 30%"
            // bug — every previous attempt was correct about opacity
            // and scaleX but missed that visibility:hidden zeroed out
            // the bar regardless.
            '#htmx-progress.snl-progress-running {',
            '  animation: snlHtmxProgressRunning 1.5s cubic-bezier(0.1, 0.6, 0.3, 1) forwards !important;',
            '  opacity: 1 !important;',
            '  visibility: visible !important;',
            '  transition: none !important;',
            '  transform-origin: left !important;',
            '}',
            '#htmx-progress.snl-progress-done {',
            '  animation: snlHtmxProgressDone 0.6s linear forwards !important;',
            '  transform-origin: left !important;',
            '  box-shadow: 0 0 8px 0 currentColor !important;',
            '  transition: none !important;',
            '  visibility: visible !important;',   // R11.12 — see comment above
            '}',
            // R11.24 — .snl-masonry-pending rule DELETED. The class itself
            // is no longer set anywhere (tagMasonryRevealOnSwap removed).
        ].join('\n');
        (document.head || document.documentElement).appendChild(style);
    })();
    //
    // 'middle' is a no-op now — the progressive-fill animation handles
    // the "still loading" affordance without needing a midpoint state.
    function setProgress(state) {
        var el = document.getElementById('htmx-progress');
        if (!el) return;
        switch (state) {
            case 'start':
                el.classList.remove('snl-progress-done');
                el.classList.add('snl-progress-running');
                break;
            case 'middle':
                // No-op under indeterminate animation
                break;
            case 'done':
                el.classList.remove('snl-progress-running');
                el.classList.add('snl-progress-done');
                // R11.13 — snappier timeline:
                //   - 100ms rapid fill to scaleX(1)
                //   - 200ms hold at full bar
                //   - 300ms fade
                // Total = 600ms animation. 700ms cleanup for safety.
                setTimeout(function () {
                    el.classList.remove('snl-progress-done');
                }, 700);
                break;
        }
    }

    // Cancel boost on no-op anchors. Dropdown menus throughout labs use
    // `<a href="#" class="dropdown-item ...">` with their actual behavior
    // wired via jQuery click delegates (toggle-visibility, delete-lesson,
    // difficulty-filter, etc.). Without this guard, hx-boost intercepts the
    // click → fires GET on the current URL → swaps #main with the same page
    // → wasted request + breaks the delegate's call to `event.preventDefault()`
    // (because htmx already prevented it for navigation).
    //
    // Also catches javascript:void(0), bare `#frag` (hash links — let the
    // browser handle in-page anchors), and `data-href` placeholders that
    // never carried a real href.
    document.body.addEventListener('htmx:beforeRequest', function (e) {
        var el = e.detail && e.detail.elt;
        if (el && el.tagName === 'A') {
            var href = el.getAttribute('href');
            if (!href || href === '#' || href.indexOf('#') === 0 || /^javascript:/i.test(href)) {
                e.preventDefault();
                return;
            }
            // Same-URL short-circuit (round-7 fix): clicking a link whose
            // resolved href matches the current location's pathname+search
            // used to trigger a full htmx swap of #main against the same
            // server response. Most visible on the quiz banner's "Recent"
            // dropdown — both the dropdown TOGGLE and the currently-selected
            // item carry the same href as the page URL, so opening the
            // dropdown caused a self-reload. Resolve the href via URL() so
            // we handle relative / absolute / origin-prefixed equally, then
            // bail if the target matches what's already in the address bar.
            // Skips the comparison if the link explicitly opted out via
            // `data-allow-same-url="true"` (escape hatch for any future
            // intentional same-URL fetch — e.g. forcing a refresh).
            try {
                if (el.getAttribute('data-allow-same-url') !== 'true') {
                    var resolved = new URL(href, document.baseURI);
                    var currentSame = (resolved.origin === location.origin)
                        && (resolved.pathname === location.pathname)
                        && (resolved.search === location.search);
                    if (currentSame) {
                        e.preventDefault();
                        return;
                    }
                }
            } catch (_) { /* malformed href — fall through to normal handling */ }
        }
        setProgress('start');
    });

    // Capture-phase guard: htmx 2.x explicitly bypasses `<a href="#">`
    // and similar in-page anchors — its boost machinery never intercepts
    // them, so `htmx:beforeRequest` (and the guard above) is never fired
    // for these clicks. The browser then takes its default action and
    // appends `#` to the URL (visible as a nav to `/something/#`).
    //
    // The page-level jQuery handlers DO call `e.preventDefault()` inside,
    // but CoreUI dropdown wrappers can swallow the click before the
    // page handler runs (and direct bindings only attach if the
    // page-init had already wired them). Capture-phase preventDefault
    // here forecloses the browser nav for ANY `href="#"` / `href="#..."` /
    // `href="javascript:..."` anchor inside an hx-boost container, without
    // stopping propagation — page handlers continue to fire normally.
    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest && e.target.closest('a');
        if (!a) return;
        var href = a.getAttribute('href');
        if (!href || href === '#' || href.charAt(0) === '#' || /^javascript:/i.test(href)) {
            // Only intervene when the anchor is inside our boost area so
            // we don't break standalone `href="#"` widgets elsewhere.
            if (a.closest('[hx-boost="true"], [data-hx-boost="true"]')) {
                e.preventDefault();
            }
        }
    }, true);  // <-- capture phase: runs before any other click listener
    document.body.addEventListener('htmx:beforeSwap',    function () {
        setProgress('middle');
        // Belt-and-suspenders modal/backdrop cleanup. The beforeRequest
        // listener already did this for boosted navs, but htmx.ajax()
        // calls or history restores hit beforeSwap without firing
        // beforeRequest from the boost path — we run it again here so
        // every code path that ends in a DOM swap also clears stuck
        // modal state. cleanupOpenModals is idempotent so the double-
        // call from boost paths is harmless.
        try { cleanupOpenModals('beforeSwap'); } catch (_) {}
        try { cleanupOpenTooltips('beforeSwap'); } catch (_) {}
        // Dispose stale CoreUI component instances BEFORE the swap. The
        // reinit() block uses Cls.getOrCreateInstance(el); when idiomorph
        // leaves matching ids/attrs in place CoreUI can hand back a stale
        // instance whose internal element pointers are dead. Symptoms:
        //   - Mastery Hall: Quiz Leaderboard → Code Arena Leaderboard tab
        //     collapses the new pane (stale Tab instance leaves display:none
        //     from a prior hide() animation). ZP#20.
        //   - LearnAI lesson tabs (Outline → Roadmap → Outline) leave the
        //     panel layout broken from a stale Tab transition. LD#18 / ZP#12.
        // Disposing first forces reinit to construct a fresh instance bound
        // to the new DOM. Apply to tab / dropdown / offcanvas. Modal/tooltip
        // already handled by cleanupOpenModals + cleanupOpenTooltips above.
        //
        // NOTE: `collapse` is intentionally NOT disposed/reinit'd here — see
        // the coreuiMap NOTE in reinit(). CoreUI Collapse must live on its
        // TARGET `.collapse` element, never on the `[data-coreui-toggle=
        // "collapse"]` TRIGGER. The old code disposed/created instances on the
        // triggers, which added `class="collapse"` (→ display:none) to the
        // League of Ronin accordion header buttons and hid every header after
        // a boost-nav (LD#14b / ZP#18). Collapse toggles ride CoreUI's
        // document-level data-API delegation and are already swap-safe.
        try {
            if (window.coreui) {
                var disposeKinds = ['tab', 'dropdown', 'offcanvas'];
                disposeKinds.forEach(function (kind) {
                    var Cls = (
                        kind === 'tab'       ? coreui.Tab      :
                        kind === 'dropdown'  ? coreui.Dropdown :
                        kind === 'offcanvas' ? coreui.Offcanvas: null
                    );
                    if (!Cls || typeof Cls.getInstance !== 'function') return;
                    document.querySelectorAll('[data-coreui-toggle="' + kind + '"]').forEach(function (el) {
                        try {
                            var inst = Cls.getInstance(el);
                            if (inst && typeof inst.dispose === 'function') inst.dispose();
                        } catch (_) {}
                    });
                });
            }
        } catch (_) {}
        // Phase 7 follow-up — destroy any DataTables instances inside the
        // swap target so the morph that follows produces "clean" tables.
        // Idiomorph preserves the <table> element when ids/attrs match, so
        // without this destroy the `dataTable` class persists into the new
        // page; the page's own $(document).ready replay then tries to
        // .DataTable({…config…}) on the live instance — DataTables throws
        // "Cannot reinitialise DataTable". User-reported on
        // /admin/transactions (and applies to every admin DataTable page:
        // transactions, instances, users, achievements, cohorts, clans,
        // reports, gstats).
        //
        // Destroy targets:
        //   - `table.dataTable` (any initialised DataTable, generic)
        //   - explicit admin-* classes for defense even when DataTables
        //     hasn't tagged with dataTable yet (rare timing)
        //
        // The bridge's own DataTables auto-init at line ~1670 uses default
        // options and would intercept admin tables before the page's
        // $(document).ready could install the rich config. Skip admin
        // tables in that auto-init list — destroy here, let the page
        // re-init with its full config.
        try {
            if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
                jQuery('table.dataTable').each(function () {
                    try {
                        var $t = jQuery(this);
                        if (jQuery.fn.DataTable.isDataTable($t)) {
                            $t.DataTable().destroy();
                        }
                        // Strip residual classes/attrs so morph + page init
                        // see a fresh table — DataTables.destroy() removes
                        // most but leaves `.dataTable` + a few aria-* hints.
                        $t.removeClass('dataTable no-footer');
                        $t.removeAttr('role aria-describedby');
                    } catch (_) {}
                });
            }
        } catch (_) {}
    });
    document.body.addEventListener('htmx:afterSettle',   function () { setProgress('done'); });
    document.body.addEventListener('htmx:responseError', function () { setProgress('done'); });
    document.body.addEventListener('htmx:sendError',     function () { setProgress('done'); });

    // R11.27 — RESTORE R9 cross-page reveal animation (minimal version).
    // On htmx:afterSwap, tag masonry grids with data-masonry-reveal so
    // server-rendered .col children animate in via the CSS keyframe.
    // Server-rendered cards have no "transition source" for Masonry's
    // native scale(0.001)→1 — they're at scale 1 from the start. Without
    // this attribute the cards just appear without entrance animation,
    // which the user has been describing as "labs masonry lost its
    // animation" / "quiz topics page used to have animation".
    //
    // The CSS keyframe is restored at the same time in app.scss.
    // No hide-class, no transform stripping, no double-rAF — minimal.
    function tagMasonryRevealOnSwap(root) {
        try {
            var scope = root || document;
            var grids = scope.querySelectorAll(
                '#masonry-area, #masonry-area1, #evaluate-grid, ' +
                '#labcard-masonry-area, #discussion-masonry, #discussion-search-masonry, ' +
                '.club-feed-grid'
            );
            grids.forEach(function (g) {
                if (!g.querySelector('.col, .col-md-6, .col-12')) return;
                if (g.dataset.snlRevealed === '1') return;  // once per grid lifetime
                g.setAttribute('data-masonry-reveal', 'true');
                g.dataset.snlRevealed = '1';
                setTimeout(function () {
                    g.removeAttribute('data-masonry-reveal');
                }, 900);
            });
        } catch (_) {}
    }
    document.body.addEventListener('htmx:afterSwap', function (e) {
        tagMasonryRevealOnSwap(e.target);
    });

    // ---------- re-init bridge --------------------------------------------------
    function reinit(root) {
        root = root || document;

        // 1. CoreUI components — auto-init only fires at boot, so any element
        //    with `data-coreui-toggle="X"` swapped in via htmx has no JS
        //    instance and the click does nothing. Re-init each kind here.
        //    Tabs were the bug that broke /learn tab switches after boost.
        if (window.coreui) {
            var coreuiMap = {
                tooltip:    coreui.Tooltip,
                popover:    coreui.Popover,
                tab:        coreui.Tab,         // <— /learn, /quiz, profile, leaderboard tabs
                // NOTE: `collapse` is deliberately ABSENT. CoreUI Collapse must be
                // instantiated on the TARGET `.collapse` element, never on the
                // `[data-coreui-toggle="collapse"]` TRIGGER. getOrCreateInstance(trigger)
                // adds `class="collapse"` to the button → `.collapse:not(.show)` is
                // display:none → the button itself vanishes. On League of Ronin this hid
                // every accordion header after a boost-nav (LD#14b / ZP#18). Collapse
                // toggles already work swap-safe via CoreUI's document-level data-API
                // delegation (registered once at load, survives swaps) — no reinit needed.
                dropdown:   coreui.Dropdown,    // <— top-nav dropdowns, action menus (trigger IS the ref element — correct)
                modal:      coreui.Modal,       // <— confirmation dialogs
                offcanvas:  coreui.Offcanvas,   // <— mobile sidebar drawer
                unfoldable: coreui.Sidebar,     // <— sidebar narrow/unfoldable toggle
            };
            Object.keys(coreuiMap).forEach(function (kind) {
                var Cls = coreuiMap[kind];
                if (!Cls || typeof Cls.getOrCreateInstance !== 'function') return;
                root.querySelectorAll('[data-coreui-toggle="' + kind + '"]').forEach(function (el) {
                    try { Cls.getOrCreateInstance(el); } catch (_) {}
                });
            });

            // 1b. CoreUI Navigation — the sidebar nav-group expand/collapse.
            //     Unlike the toggles above it binds to the CONTAINER
            //     `<ul class="sidebar-nav" data-coreui="navigation">` (a single
            //     delegated handler on .nav-group-toggle), and CoreUI only
            //     auto-inits it once at window load. Crossing the admin↔labs
            //     master boundary swaps in a fresh sidebar with no instance, so
            //     the nav groups stop expanding until a hard reload.
            //     Scoped to `document`, not `root`: the sidebar lives outside
            //     #main, and the historyRestore (back/forward) path passes
            //     #main as root — so a root-scoped query would miss it there.
            //     getOrCreateInstance is idempotent and there is one sidebar.
            if (coreui.Navigation && typeof coreui.Navigation.getOrCreateInstance === 'function') {
                document.querySelectorAll('[data-coreui="navigation"]').forEach(function (el) {
                    try { coreui.Navigation.getOrCreateInstance(el); } catch (_) {}
                });
            }
        }

        // 2. DataTables — only generic `table.datatable` here. Admin
        //    pages use specialised classes (admin-transactions-table,
        //    admin-users-table, etc.) AND their per-page JS configures
        //    them with serverSide processing, custom columns, filter
        //    integrations, etc. Letting the bridge auto-init those with
        //    default options collides with the page's later init (the
        //    page would throw "Cannot reinitialise DataTable").
        //
        //    Instead, the bridge destroys all DataTables instances in
        //    htmx:beforeSwap (above) so the page's $(document).ready
        //    replay sees a clean table and can install its own config.
        if (window.jQuery && jQuery.fn && jQuery.fn.DataTable) {
            jQuery(root).find('table.datatable:not(.dataTable)').each(function () {
                try { jQuery(this).DataTable(); } catch (_) {}
            });
        }

        // 3. Masonry — centralised via window.MasonryMgr (defined below).
        //    See the manager for full rationale; per-page JS should call
        //    `MasonryMgr.get('#masonry-area')` instead of doing its own
        //    `.masonry({...})` init.
        if (window.MasonryMgr) MasonryMgr.scan(root);

        // 4. Declarative chart factories: <... data-chart-init="initFooChart">.
        //    If a previous swap left a Chart.js instance attached to the same
        //    canvas, destroy it first so the new init isn't fighting over the
        //    underlying <canvas> (Chart.js will silently render a blank
        //    second instance otherwise).
        root.querySelectorAll('[data-chart-init]').forEach(function (el) {
            var fn = window[el.getAttribute('data-chart-init')];
            if (typeof fn !== 'function') return;
            if (window.Chart && typeof Chart.getChart === 'function') {
                try {
                    var canvas = el.tagName === 'CANVAS' ? el : el.querySelector('canvas');
                    if (canvas) {
                        var prev = Chart.getChart(canvas);
                        if (prev) prev.destroy();
                    }
                } catch (_) {}
            }
            try { fn(el); el.dataset.chartReady = '1'; } catch (_) {}
        });

        // 5. Clipboard.js — every `.copy-btn` / `[data-clipboard-target]`
        //    needs its own Clipboard listener bound. We attach via the
        //    `data-clipboardReady` flag for idempotency.
        if (window.ClipboardJS) {
            var clipSelectors = [
                '.copy-btn:not([data-clipboardReady])',
                '[data-clipboard-target]:not([data-clipboardReady])',
                '[data-clipboard-text]:not([data-clipboardReady])',
            ];
            root.querySelectorAll(clipSelectors.join(',')).forEach(function (el) {
                try {
                    new ClipboardJS(el);
                    el.dataset.clipboardReady = '1';
                } catch (_) {}
            });
        }

        // 6. QRCode.js — render any `[data-qrcode-text]` containers that
        //    haven't been rendered yet.
        if (window.QRCode) {
            root.querySelectorAll('[data-qrcode-text]:not([data-qrcodeReady])').forEach(function (el) {
                try {
                    new QRCode(el, el.getAttribute('data-qrcode-text'));
                    el.dataset.qrcodeReady = '1';
                } catch (_) {}
            });
        }

        // 7. KaTeX auto-render — /learn rendering depends on this. Re-run
        //    on every swap so `\\(math\\)` and `$$math$$` in the new page
        //    body render. KaTeX has its own idempotency: the rendered
        //    spans get a `katex` class so re-render of the same input is
        //    a no-op.
        if (window.renderMathInElement) {
            try {
                renderMathInElement(root, {
                    delimiters: [
                        { left: '$$', right: '$$', display: true },
                        { left: '\\(', right: '\\)', display: false },
                    ],
                    throwOnError: false,
                });
            } catch (_) {}
        }

        // 8. hljs, mermaid, wavedrom — already idempotent in their own libs.
        if (window.hljs) {
            try { hljs.configure({ ignoreUnescapedHTML: true }); hljs.highlightAll(); } catch (_) {}
        }
        if (window.mermaid) {
            try { mermaid.run({ querySelector: 'pre.mermaid' }); } catch (_) {}
        }
        if (window.WaveDrom) {
            try { WaveDrom.ProcessAll(); } catch (_) {}
        }

        // 9. Prism.js — re-highlight any `<code class="language-*">` that
        //    appeared in the swapped subtree. Prism only auto-highlights
        //    once at boot via DOMContentLoaded.
        if (window.Prism && typeof Prism.highlightAllUnder === 'function') {
            try { Prism.highlightAllUnder(root); } catch (_) {}
        }
    }

    // ---------- sidebar active-link sync ----------------------------------------
    //
    // Best-match scoring: exact href === path wins; otherwise the LONGEST
    // href that is a prefix of path (with `/` boundary) wins. So on
    // /code/python/123/arena the `/code` sidebar item activates instead of
    // nothing; on /learn/syllabus/xxx the `/learn` item still highlights
    // because `/learn` is the longest matching prefix. CoreUI's cold-load
    // boot does the same scoring.
    function syncSidebarActive() {
        var path = location.pathname;
        var links = document.querySelectorAll('#sidebar .nav-link');
        var best = null;
        var bestLen = -1;
        links.forEach(function (a) {
            var href = a.getAttribute('href');
            if (!href || href === '#' || href.charAt(0) === '#') return;
            if (href === path) { best = a; bestLen = Infinity; return; }
            // Prefix match with `/` boundary so `/code` matches /code/x but
            // not /codex. Treat root `/` as exact-only to avoid winning
            // every comparison.
            if (href !== '/' && (path === href || path.indexOf(href + '/') === 0)) {
                if (href.length > bestLen) { best = a; bestLen = href.length; }
            }
        });
        links.forEach(function (a) { a.classList.toggle('active', a === best); });
    }

    // ---------- bootstrap JSON consumer ------------------------------------------
    //
    // Returns a Promise that resolves once any customJs scripts declared by
    // the page have finished loading. `pageReady` is fired AFTER this resolves
    // so page-init code (e.g. __initLabsPage's `new Terminal(...)`) can safely
    // reference globals like xterm.js's `Terminal` that came in via customJs.
    // Without this await, the first /labs/* swap fired pageReady → __initLabsPage
    // → `new Terminal()` → ReferenceError "Terminal is not defined" because the
    // xterm.js script tag had only just been appended (not yet executed).
    function consumeBootstrap() {
        var node = document.getElementById('htmx-page-bootstrap');
        if (!node) return Promise.resolve();
        var data;
        try { data = JSON.parse(node.textContent || '{}'); } catch (_) { return Promise.resolve(); }

        if (Array.isArray(data.breadcrumbs) && typeof window.__renderBreadcrumbs === 'function') {
            try { window.__renderBreadcrumbs(data.breadcrumbs); } catch (_) {}
        }
        if (data.title) {
            try { document.title = data.title; } catch (_) {}
        }
        // Theme chrome refresh — the cold-load <head> emits a <link id="theme-css">
        // and <style id="theme-blur-css">, both OUTSIDE #main. Under hx-boost the
        // bridge only re-renders #main, so without this branch every mid-session
        // theme change leaves the chrome styled by the original cold-load theme
        // until a hard refresh. Triggers:
        //   - /api/app/set_theme from a fetch wrapper (in-page theme picker)
        //   - future SPA-driven swatch pickers
        //   - any boosted nav landing on a page whose session theme differs
        //     from the cold-load theme (e.g. user changed theme in tab A,
        //     then SPA-navs in tab B — covered ONLY if tab B's nav triggers
        //     a server round-trip; see cross-tab note below).
        // NOT triggered by /theme/editor's "Apply" button — that page is
        // boost-excluded (str_starts_with(path, '/theme/editor') in
        // Session::isExcludedFromHtmx), so its callback `set_theme` +
        // `window.location.href='/dashboard'` does a full reload of the
        // destination, which re-renders the chrome from scratch.
        try { applyThemeFromBootstrap(data.theme); } catch (e) {
            if (window.console) console.warn('[htmx-bridge] theme apply failed', e);
        }
        var jsLoadPromise = loadCustomAssetsIdempotent(data.customJs || [], data.customCss || []);
        // pageInit hook must run AFTER customJs has loaded — otherwise the
        // pageInit function may not yet be defined on window (race), or it
        // may try to use globals (Chart, Terminal, etc.) that customJs
        // brings in. Chain on the jsLoadPromise. Surface missing-hook /
        // throw via console — previous silent skip hid real bugs.
        return jsLoadPromise.then(function () {
            if (!data.pageInit) return;
            var fn = window[data.pageInit];
            if (typeof fn !== 'function') {
                if (window.console) console.warn('[htmx-bridge] _pageInit hook "' + data.pageInit + '" is not a function on window after customJs load');
                return;
            }
            try { fn(); }
            catch (e) { if (window.console) console.error('[htmx-bridge] _pageInit hook "' + data.pageInit + '" threw:', e); }
        });
    }

    // Swap chrome-level theme assets (theme-css link, theme-blur-css style,
    // swatch-server-css style, theme-color meta) to match what the server
    // rendered for the just-swapped page. Idempotent — if the bootstrap
    // theme matches what's already in the DOM, nothing changes.
    function applyThemeFromBootstrap(theme) {
        if (!theme || typeof theme !== 'object') return;

        // 1) Theme stylesheet: <link id="theme-css">. Compare on data-theme-id
        //    to skip a needless reload + flash when nothing changed.
        if (typeof theme.href === 'string' && theme.href) {
            var link = document.getElementById('theme-css');
            var currentId = link ? link.getAttribute('data-theme-id') : null;
            if (!link) {
                link = document.createElement('link');
                link.id = 'theme-css';
                link.rel = 'stylesheet';
                document.head.appendChild(link);
            }
            if (currentId !== theme.id || link.getAttribute('href') !== theme.href) {
                // Build the new <link>, then swap atomically once it has loaded
                // to avoid an unstyled flash. If we can't detect onload (cached
                // hit), it resolves immediately.
                var nextLink = document.createElement('link');
                nextLink.id = 'theme-css';
                nextLink.rel = 'stylesheet';
                nextLink.href = theme.href;
                if (theme.id) nextLink.setAttribute('data-theme-id', theme.id);
                // Drop ANY in-flight previous theme links (id stripped + marked
                // pending) before installing the new one. Without this, rapid
                // theme swaps (back/forward between two themed pages within
                // the 2s safety window) leave stale stylesheets continuing
                // to apply rules from prior themes — even after the visible
                // active id matches the URL, the old <link rel="stylesheet">
                // is still in <head> casting CSS.
                var stale = document.head.querySelectorAll('link[data-theme-pending="1"]');
                for (var si = 0; si < stale.length; si++) {
                    if (stale[si].parentNode) stale[si].parentNode.removeChild(stale[si]);
                }
                var prev = link;
                var swap = function () {
                    if (prev && prev !== nextLink && prev.parentNode) {
                        prev.parentNode.removeChild(prev);
                    }
                };
                nextLink.addEventListener('load',  swap, { once: true });
                nextLink.addEventListener('error', swap, { once: true });
                // Safety: if neither fires within 2s (proxy hiccup), still drop the old
                setTimeout(swap, 2000);
                // We need both for a brief moment; insert next, leave prev until load.
                // Strip the id from prev (only one element may carry an id),
                // and tag it as pending-removal so a subsequent rapid swap can
                // garbage-collect it deterministically.
                prev.removeAttribute('id');
                prev.setAttribute('data-theme-pending', '1');
                document.head.appendChild(nextLink);
            }
        }

        // 2) --blur-light / --blur-dark inline <style id="theme-blur-css">
        //    Idempotent: only writes if the computed text actually changed.
        //    Without this guard, every SPA-nav rewrites the style block (the
        //    bootstrap blob always carries blurLight/blurDark) which costs a
        //    needless style-sheet recompute and a paint invalidation on the
        //    blurred chrome layers.
        if (theme.blurLight || theme.blurDark) {
            var blurStyle = document.getElementById('theme-blur-css');
            var bl = theme.blurLight || 'rgba(240,240,240,0.95)';
            var bd = theme.blurDark  || 'rgba(28,35,48,0.95)';
            var nextBlurText =
                ':root{--blur-light:' + bl + ';--blur-dark:' + bd + ';}' +
                'html[data-coreui-theme="light"]{--blur:var(--blur-light);}' +
                'html[data-coreui-theme="dark"]{--blur:var(--blur-dark);}';
            if (!blurStyle) {
                blurStyle = document.createElement('style');
                blurStyle.id = 'theme-blur-css';
                blurStyle.textContent = nextBlurText;
                document.head.appendChild(blurStyle);
            } else if (blurStyle.textContent !== nextBlurText) {
                blurStyle.textContent = nextBlurText;
            }
        }

        // 3) Server-side swatch CSS — comes pre-wrapped in <style id="swatch-server-css">
        //    tags from __generateSwatchCss(). Strip the wrapping <style> so we can
        //    write into a single canonical element instead of stacking copies.
        if (typeof theme.swatchCss === 'string') {
            var swatchStyle = document.getElementById('swatch-server-css');
            var inner = theme.swatchCss.replace(/^\s*<style[^>]*>/i, '').replace(/<\/style>\s*$/i, '');
            if (inner.trim()) {
                if (!swatchStyle) {
                    swatchStyle = document.createElement('style');
                    swatchStyle.id = 'swatch-server-css';
                    document.head.appendChild(swatchStyle);
                }
                if (swatchStyle.textContent !== inner) swatchStyle.textContent = inner;
            } else if (swatchStyle && swatchStyle.parentNode) {
                swatchStyle.parentNode.removeChild(swatchStyle);
            }
        }

        // 4) <meta name="theme-color"> for the mobile browser chrome
        if (theme.themeColor) {
            var meta = document.querySelector('meta[name="theme-color"]');
            if (!meta) {
                meta = document.createElement('meta');
                meta.setAttribute('name', 'theme-color');
                document.head.appendChild(meta);
            }
            meta.setAttribute('content', theme.themeColor);
        }

        // 5) Expose the current theme id to legacy code paths that read it
        //    (bg.js's `window.__currentTheme`).
        if (theme.id) {
            try { window.__currentTheme = theme.id; } catch (_) {}
        }
    }

    function loadCustomAssetsIdempotent(jsList, cssList) {
        // Build a stable key set from the desired CSS (strip cache-buster
        // so different ?_= query strings compare as the same asset). Use
        // these keys both for "is it already present?" and for "should it
        // stay or be removed on swap?".
        var wantCssKeys = Object.create(null);
        cssList.forEach(function (h) {
            if (!h) return;
            wantCssKeys[String(h).split('?')[0].split('#')[0]] = String(h);
        });

        // CASCADE LEAK FIX: remove bridge-managed <link>s that are NOT in
        // the new page's customCss list. Without this, /learn's
        // katex.min.css leaks into /labs, /code's monokai-sublime leaks
        // into /dashboard, etc. — global selectors (.btn, .card) keep
        // applying their old rules.
        document.querySelectorAll('link[data-htmx-css]').forEach(function (l) {
            var key = l.getAttribute('data-htmx-css');
            if (!wantCssKeys[key]) {
                if (l.parentNode) l.parentNode.removeChild(l);
            }
        });

        Object.keys(wantCssKeys).forEach(function (key) {
            var href = wantCssKeys[key];
            // Idempotency check — match by the cache-buster-stripped key so
            // cold-load <link> (e.g. `?_=v0.8`) and post-swap <link> resolve
            // to the same key. Without this, a cache-buster bump would
            // duplicate every per-page stylesheet.
            if (document.querySelector('link[data-htmx-css="' + key + '"]')) return;
            var l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = href;
            l.setAttribute('data-htmx-css', key);
            document.head.appendChild(l);
        });
        // FOUC FIX: await CSS load just like JS. Previously CSS was
        // fire-and-forget, so pageReady (and any pageInit) fired before
        // the stylesheet finished applying. Visible as: SPA nav into
        // /labs / /code / /theme briefly renders unstyled before snapping
        // into place. By including link.onload promises here, the bridge
        // delays pageReady until the page is fully styled.
        var pendingLoads = [];
        Object.keys(wantCssKeys).forEach(function (key) {
            var l = document.querySelector('link[data-htmx-css="' + key + '"]');
            // Only await NEW links we just appended (sheet not yet loaded);
            // links already-loaded (sheet present) resolve instantly.
            if (!l) return;
            if (l.sheet) return;  // already loaded — no wait needed
            pendingLoads.push(new Promise(function (resolve) {
                l.addEventListener('load',  function () { resolve(); }, { once: true });
                l.addEventListener('error', function () { resolve(); }, { once: true });
                // 3s safety timeout so a hung CDN can't deadlock pageReady.
                setTimeout(resolve, 3000);
            }));
        });
        // Append each unique customJs script and return a Promise that resolves
        // once they ALL fire `load` (or `error`, so a single bad asset can't
        // hold up pageReady forever). Scripts already in the document — even
        // marked via a prior swap's data-htmx-js attribute — are treated as
        // loaded immediately.
        jsList.forEach(function (src) {
            if (!src) return;
            if (document.querySelector('script[data-htmx-js="' + src + '"]')) return;
            var s = document.createElement('script');
            s.src = src;
            s.async = false;
            s.setAttribute('data-htmx-js', src);
            pendingLoads.push(new Promise(function (resolve) {
                s.addEventListener('load',  function () { resolve(); });
                s.addEventListener('error', function () { resolve(); });  // don't block on bad assets
            }));
            document.body.appendChild(s);
        });
        return pendingLoads.length ? Promise.all(pendingLoads).then(function () {}) : Promise.resolve();
    }

    // ---------- script re-execution --------------------------------------------
    //
    // htmx innerHTML-swaps DO execute inline <script> tags by default, BUT
    // page templates often use `$(document).ready(function(){...})` to bind
    // event handlers, and on swap N>=2 the document is already ready so any
    // handler bound at boot-time to a DOM element that just got REPLACED is
    // lost — the new DOM has no listener attached. Pages have to either use
    // event delegation OR re-run their init each swap.
    //
    // To make legacy pages "just work" without rewriting them, we re-execute
    // every inline <script> found in the swapped subtree. Idempotency depends
    // on the script — most labs page scripts (CoreUI/DataTables/chart inits)
    // are safe to re-run because they guard via :not(.dataTable), data-*Ready
    // flags, or getOrCreateInstance(). External <script src> tags that are
    // not yet in <head> get dynamically appended; those already loaded are
    // skipped (no re-download) but their on-ready callbacks fire via the
    // jQuery `pageReady` synthetic event below.
    function rerunScripts(root) {
        if (!root) return;
        var scripts = root.querySelectorAll('script');
        scripts.forEach(function (oldS) {
            try {
                // Skip non-executable script types (JSON data islands,
                // template fragments, etc.). The `<script id="htmx-page-
                // bootstrap" type="application/json">` block falls in here;
                // without this skip, `new Function(jsonString)` throws a
                // SyntaxError on every swap and on every history restore,
                // spamming `[htmx-bridge] inline script error` in the
                // console. Any type other than empty / "text/javascript" /
                // "application/javascript" / "module" is data, not code.
                var t = (oldS.getAttribute('type') || '').trim().toLowerCase();
                var isExecutable = !t
                    || t === 'text/javascript'
                    || t === 'application/javascript'
                    || t === 'module';
                if (!isExecutable) return;

                if (oldS.src) {
                    // External: only append if not already there (idempotent)
                    if (document.querySelector('script[src="' + oldS.src + '"]')) return;
                    var s = document.createElement('script');
                    for (var i = 0; i < oldS.attributes.length; i++) {
                        var a = oldS.attributes[i];
                        s.setAttribute(a.name, a.value);
                    }
                    document.head.appendChild(s);
                } else if (oldS.textContent) {
                    // Inline: re-execute. New Function call gives clean global scope.
                    try {
                        (new Function(oldS.textContent))();
                    } catch (err) {
                        if (window.console && console.warn) {
                            console.warn('[htmx-bridge] inline script error', err);
                        }
                    }
                }
            } catch (_) {}
        });
    }

    // ---------- main lifecycle hook ---------------------------------------------
    document.body.addEventListener('htmx:afterSwap', function (e) {
        try { reinit(e.target); } catch (_) {}
        try { syncSidebarActive(); } catch (_) {}

        // P0 FIX: hoist `__htmxSwapping = true` BEFORE rerunScripts so any
        // `$(document).ready(...)` inside an inline <script> the bridge
        // re-evaluates here doesn't get re-captured. Previously the flag
        // was only set inside replayReady() (which runs AFTER rerunScripts)
        // → every nav re-captured the same boot closures, capturedReady
        // grew linearly, and `window.__htmxReadyHandlers.length` confirmed
        // memory growth over time. Now the entire rerun + replay window
        // is gated under __htmxSwapping; capture-side guard at line ~287
        // correctly skips additions during this window.
        window.__htmxSwapping = true;
        try {
            // Re-execute inline <script>s in the swapped subtree so page-level
            // init that used to run at boot (DataTables, chart factories, etc.)
            // runs every swap. Safe because the page-side guards we already use
            // (`:not(.dataTable)`, `data-*Ready` flags) keep this idempotent.
            try { rerunScripts(e.target); } catch (_) {}

            // Replay every $(document).ready handler that was captured during
            // boot. Without this, ~117 ready callbacks in app.js (terminal
            // init, quiz init, chart bindings, click handlers on
            // .n-mark-all-read, .lab-node, etc.) only fire on the initial full
            // page load — every htmx-swapped page sees a half-initialized DOM.
            try { replayReady(e.target); } catch (_) {}
        } finally {
            window.__htmxSwapping = false;
        }

        // CRITICAL: clear all per-page idempotency markers BEFORE pageReady
        // fires. Each `__initLearnPage` / `__initRoadmapPage` / etc. injected
        // a `data-<page>InitRan="1"` flag on #main. Outer #main element is
        // replaced on outerHTML swap so fresh starts are free — but be
        // defensive and also clear here in case any pre-existing flag from a
        // direct page-load (no swap) is still on the element.
        try {
            var mainEl = document.getElementById('main');
            if (mainEl && mainEl.dataset) {
                Object.keys(mainEl.dataset).forEach(function (k) {
                    if (k.length >= 'InitRan'.length && k.slice(-'InitRan'.length) === 'InitRan') {
                        delete mainEl.dataset[k];
                    }
                });
            }
        } catch (_) {}

        // Bootstrap consumer returns a Promise that resolves AFTER any
        // page-declared customJs scripts have finished loading. pageReady
        // must wait for that — otherwise __initLabsPage runs before
        // xterm.js's `Terminal` global is defined and `new Terminal(...)`
        // throws ReferenceError on the very first swap into /labs/*.
        // (Subsequent swaps work because the script is already loaded.)
        var bootstrapDone;
        try { bootstrapDone = consumeBootstrap(); } catch (_) { bootstrapDone = Promise.resolve(); }
        if (!bootstrapDone || typeof bootstrapDone.then !== 'function') {
            bootstrapDone = Promise.resolve();
        }
        bootstrapDone.then(function () {
            // Synthetic `pageReady` event for pages that want a clean hook
            // independent of jQuery's `ready`. Fires after customJs has loaded.
            if (window.jQuery) {
                try { jQuery(document).trigger('pageReady', [e.target]); } catch (_) {}
            }
            try {
                document.dispatchEvent(new CustomEvent('pageReady', { detail: { root: e.target } }));
            } catch (_) {}
            // CRITICAL: dedup AFTER pageReady. `__initRoadmapPage` and the
            // other __init*Page handlers register their delegates inside the
            // pageReady listener, AFTER the synchronous dedup below ran.
            // Without this second pass, every swap into a page with
            // delegate-registering init code STACKS another copy — observed
            // as buttons firing actions 2× / dialogs popping twice / etc.
            try {
                var droppedAsync = dedupDocDelegates();
                if (droppedAsync && window.console && console.debug) {
                    console.debug('[htmx-bridge] dedupDocDelegates (post-pageReady) dropped', droppedAsync);
                }
            } catch (_) {}
        });

        // First dedup pass — catches replays from `rerunScripts` + the
        // captured-ready replay above. The post-pageReady pass in the
        // bootstrapDone.then() above catches delegates registered by
        // page-init handlers.
        try {
            var dropped = dedupDocDelegates();
            if (dropped && window.console && console.debug) {
                console.debug('[htmx-bridge] dedupDocDelegates dropped', dropped);
            }
        } catch (_) {}

        // Fan out to existing jQuery ajaxComplete consumers in app.js
        // (toasts on 4xx/5xx, hljs, scanAndObserve at app.js:619).
        if (window.jQuery) {
            var fakeXhr = { status: 200, responseJSON: undefined };
            var fakeOpts = { url: location.pathname + location.search, _htmx: true };
            try { jQuery(document).trigger('ajaxComplete', [fakeXhr, fakeOpts]); } catch (_) {}
        }
    });

    // ---------- htmx:historyRestore handler (browser back/forward) ----------
    //
    // htmx restores cached pages from its history snapshot on back/forward
    // navigation without firing `htmx:afterSwap`. Without this handler the
    // bridge never re-runs:
    //   - reinit (CoreUI tabs/modals/dropdowns/tooltips re-attach)
    //   - syncSidebarActive (sidebar highlight stays on the OLD page)
    //   - rerunScripts (inline init in the restored DOM never fires)
    //   - replayReady (~117 captured-ready handlers never fire)
    //   - consumeBootstrap (per-page customCss not refreshed → restored page
    //     renders with WRONG stylesheets, e.g. katex stripped on a /learn
    //     back-nav after visiting /dashboard)
    //   - pageReady event (page-level __initXPage idempotency flags stale)
    // Result observed: back-button to /learn shows unstyled math; back to
    // /labs has dead CoreUI tabs; sidebar `.active` points at the page the
    // user just LEFT instead of the restored page.
    //
    // Mirror the afterSwap flow. The restored DOM IS the live DOM at this
    // point (htmx has already swapped it in), so `e.target` is `document`
    // and we pass `document.getElementById('main')` (or document) as the
    // root to the re-init helpers.
    document.body.addEventListener('htmx:historyRestore', function () {
        var root = document.getElementById('main') || document;
        try { reinit(root); } catch (_) {}
        try { syncSidebarActive(); } catch (_) {}
        try { rerunScripts(root); } catch (_) {}
        try { replayReady(root); } catch (_) {}
        try {
            var mainEl = document.getElementById('main');
            if (mainEl && mainEl.dataset) {
                Object.keys(mainEl.dataset).forEach(function (k) {
                    if (k.length >= 'InitRan'.length && k.slice(-'InitRan'.length) === 'InitRan') {
                        delete mainEl.dataset[k];
                    }
                });
            }
        } catch (_) {}
        var bootstrapDone;
        try { bootstrapDone = consumeBootstrap(); } catch (_) { bootstrapDone = Promise.resolve(); }
        if (!bootstrapDone || typeof bootstrapDone.then !== 'function') {
            bootstrapDone = Promise.resolve();
        }
        bootstrapDone.then(function () {
            if (window.jQuery) {
                try { jQuery(document).trigger('pageReady', [root]); } catch (_) {}
            }
            try {
                document.dispatchEvent(new CustomEvent('pageReady', { detail: { root: root } }));
            } catch (_) {}
            try { dedupDocDelegates(); } catch (_) {}
        });
        try { dedupDocDelegates(); } catch (_) {}
    });

    // ---------- error path -------------------------------------------------------
    document.body.addEventListener('htmx:responseError', function (e) {
        var xhr = e.detail && e.detail.xhr;
        if (!xhr) return;
        var msg = 'Something went wrong (' + (xhr.status || '?') + ')';
        try {
            var j = JSON.parse(xhr.responseText || '{}');
            if (j && j.error) msg = j.error;
        } catch (_) {}
        // Use app.js Toast factory if available
        if (typeof window.Toast === 'function') {
            try {
                var t = new Toast('Attention', 'now', msg);
                if (typeof t.setIcon === 'function') t.setIcon('cil-warning');
                t.show();
            } catch (_) {}
        }
    });

    // ---------- session expiry ---------------------------------------------------
    document.body.addEventListener('htmx:beforeOnLoad', function (e) {
        var xhr = e.detail && e.detail.xhr;
        if (!xhr) return;
        // HX-Redirect to a login/auth path implies session expiry — tear down
        // both the legacy `window.client` STOMP connection AND the SharedWorker-
        // owned `LabsWS` channel before the browser actually navigates.
        //
        // Previously: regex was /^\/home/ only. Any redirect to /login, /signin,
        // /auth/expired, /logout, etc. left the legacy STOMP heartbeats firing
        // against an expired auth ticket (visible as console spam + 30s of
        // "ghost online" presence in clan chat) AND left the SharedWorker
        // subscription alive for OTHER tabs of the same user.
        var hxRedirect = xhr.getResponseHeader && xhr.getResponseHeader('HX-Redirect');
        if (hxRedirect && /^\/(home|login|signin|auth|logout)\b/.test(hxRedirect)) {
            try { if (window.client && typeof window.client.disconnect === 'function') window.client.disconnect(); } catch (_) {}
            try { if (window.LabsWS && window.LabsWS._port && typeof window.LabsWS._port.postMessage === 'function') window.LabsWS._port.postMessage({ type: 'forceDisconnect' }); } catch (_) {}
            try { if (window.LabsWS && window.LabsWS._fbStomp && typeof window.LabsWS._fbStomp.disconnect === 'function') window.LabsWS._fbStomp.disconnect(function () {}); } catch (_) {}
        }
    });
})();
