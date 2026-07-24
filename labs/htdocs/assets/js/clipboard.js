/**
 * clipboard.js — Single source for all copy-to-clipboard operations.
 *
 * Loaded via <script src="/js/clipboard.js"> in _master.php (separate from app.js).
 * Provides:
 *   window.copyText(text, toastMsg?)        — copy a raw string, show toast
 *   window.copyValue(elementId, toastMsg?)   — copy an input's value by ID
 *   data-copy="text" on any element          — click-to-copy with check-icon feedback
 *   .clipboard[data-clipboard-text="..."]     — legacy selector support (challenges, labs)
 *
 * Works in both HTTPS (Clipboard API) and HTTP (execCommand fallback).
 */
(function () {
    "use strict";

    /* ── Fallback for non-secure contexts ─────────────────────────── */
    function fallbackCopy(text) {
        var ta = document.createElement("textarea");
        ta.value = text;
        ta.style.cssText = "position:fixed;left:-9999px;top:0;opacity:0";
        var container = document.querySelector(".modal.show") || document.body;
        container.appendChild(ta);
        ta.focus();
        ta.select();
        var ok = false;
        try { ok = document.execCommand("copy"); } catch (_) { /* ignore */ }
        container.removeChild(ta);
        return ok;
    }

    /* ── Toast helper ─────────────────────────────────────────────── */
    function toast(msg) {
        if (window.TomNotify && typeof TomNotify.show === "function") {
            TomNotify.show(msg, "Copied", "success", 3000);
        } else {
            var el = document.getElementById("copyToast");
            var mEl = document.getElementById("toast-message");
            if (el && mEl) {
                mEl.textContent = msg;
                try { new coreui.Toast(el, { delay: 3000, autohide: true }).show(); } catch (_) { /* ignore */ }
            }
        }
    }

    /* ── Icon feedback on the triggering button ───────────────────── */
    function flashIcon(btn) {
        try {
            var icon = btn && btn.querySelector("i");
            if (!icon) return;
            var orig = icon.className;
            icon.className = "bx bx-check text-success";
            setTimeout(function () { icon.className = orig; }, 1500);
        } catch (_) { /* ignore */ }
    }

    /* ── Core copy function ───────────────────────────────────────── */
    function doCopy(text, btn) {
        var after = function () { toast("Copied to clipboard!"); flashIcon(btn); };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(after).catch(function () {
                if (fallbackCopy(text)) after();
            });
        } else {
            if (fallbackCopy(text)) after();
        }
    }

    /* ── Public API ───────────────────────────────────────────────── */
    window.copyText = function (text, toastMsg) {
        var after = function () { toast(toastMsg || "Text copied to clipboard!"); };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(after).catch(function () {
                if (fallbackCopy(text)) after();
            });
        } else {
            if (fallbackCopy(text)) after();
        }
    };

    window.copyValue = function (elementId, toastMsg) {
        var el = document.getElementById(elementId);
        if (el) window.copyText(el.value, toastMsg || "Command copied!");
    };

    /* ── Delegated click handler for data-copy and .clipboard ─────── */
    document.addEventListener("click", function (e) {
        // data-copy="some text" — new standard
        var dataCopyEl = e.target.closest("[data-copy]");
        if (dataCopyEl) {
            e.preventDefault();
            doCopy(dataCopyEl.getAttribute("data-copy"), dataCopyEl);
            return;
        }

        // .clipboard[data-clipboard-text="..."] — legacy (challenges, labs, quiz)
        var clipEl = e.target.closest(".clipboard[data-clipboard-text]");
        if (clipEl) {
            e.preventDefault();
            doCopy(clipEl.getAttribute("data-clipboard-text"), clipEl);
            return;
        }

        // .copy-hash-btn[data-hash="..."] — instance manage header
        var hashBtn = e.target.closest(".copy-hash-btn[data-hash]");
        if (hashBtn) {
            e.preventDefault();
            doCopy(hashBtn.getAttribute("data-hash"), hashBtn);
            return;
        }
    });
})();
