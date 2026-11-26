/**
 * ============================================================================
 * TOM LABS — REPORT ISSUES (TomReport)
 * ============================================================================
 * Global helper that lets any page open a "Report an issue" modal and submit
 * it to /api/reports/submit. Superusers receive the report in the bell's
 * Report tab; clicking it opens a read-only popup (TomReport.view) instead of
 * navigating away.
 *
 * Usage:
 *   TomReport.open({ type: 'quiz', id: '<entity-id>', title: 'Report an issue' });
 * ============================================================================
 */
(function () {
    'use strict';

    var MODAL_ID = 'tom-report-modal';
    var VIEW_ID  = 'tom-report-view-modal';
    var SUBMIT_URL = '/api/reports/submit';

    var current = { type: '', id: '', title: '' };
    var pending = false;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function toast(message, title, type) {
        if (window.TomNotify && typeof window.TomNotify.show === 'function') {
            window.TomNotify.show(message, title || 'Notification', type || 'info');
        }
    }

    function modalFor(el) {
        if (!window.coreui || !coreui.Modal) return null;
        if (typeof coreui.Modal.getOrCreateInstance === 'function') {
            return coreui.Modal.getOrCreateInstance(el);
        }
        return coreui.Modal.getInstance(el) || new coreui.Modal(el);
    }

    function showModal(el) {
        var modal = modalFor(el);
        if (modal) modal.show();
    }

    function hideModal(el) {
        var modal = modalFor(el);
        if (modal) modal.hide();
    }

    function showError(message) {
        var el = document.getElementById(MODAL_ID + '-error');
        if (!el) return;
        if (message) {
            el.textContent = message;
            el.classList.remove('d-none');
        } else {
            el.textContent = '';
            el.classList.add('d-none');
        }
    }

    function setPending(state) {
        pending = state;
        var btn  = document.getElementById(MODAL_ID + '-submit');
        var spin = document.getElementById(MODAL_ID + '-spin');
        if (btn) btn.disabled = state;
        if (spin) spin.classList.toggle('d-none', !state);
    }

    function ensureModal() {
        var existing = document.getElementById(MODAL_ID);
        if (existing) return existing;

        var html =
            '<div class="modal fade" id="' + MODAL_ID + '" tabindex="-1" aria-hidden="true">' +
              '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">' +
                '<div class="modal-content blur">' +
                  '<div class="modal-header border-0 pb-0">' +
                    '<h5 class="modal-title fw-bold" id="' + MODAL_ID + '-title">Report an issue</h5>' +
                    '<button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>' +
                  '</div>' +
                  '<div class="modal-body">' +
                    '<p class="text-body-secondary small mb-2" id="' + MODAL_ID + '-context"></p>' +
                    '<label class="form-label fw-semibold" for="' + MODAL_ID + '-message">Describe the issue</label>' +
                    '<textarea class="form-control" id="' + MODAL_ID + '-message" rows="5" maxlength="1000" ' +
                      'placeholder="What went wrong? Please add enough detail so we can reproduce it."></textarea>' +
                    '<div class="d-flex justify-content-between mt-1">' +
                      '<small class="text-body-tertiary">Required &middot; max 1000 characters</small>' +
                      '<small class="text-body-tertiary"><span id="' + MODAL_ID + '-count">0</span>/1000</small>' +
                    '</div>' +
                    '<div class="alert alert-danger mt-2 mb-0 py-2 small d-none" id="' + MODAL_ID + '-error" role="alert"></div>' +
                  '</div>' +
                  '<div class="modal-footer border-0 pt-0">' +
                    '<button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-coreui-dismiss="modal">Cancel</button>' +
                    '<button type="button" class="btn btn-danger rounded-pill px-4 fw-bold" id="' + MODAL_ID + '-submit">' +
                      '<span class="spinner-border spinner-border-sm me-1 d-none" id="' + MODAL_ID + '-spin" role="status" aria-hidden="true"></span>' +
                      'Submit Report' +
                    '</button>' +
                  '</div>' +
                '</div>' +
              '</div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', html);

        var modal    = document.getElementById(MODAL_ID);
        var textarea = document.getElementById(MODAL_ID + '-message');
        var counter  = document.getElementById(MODAL_ID + '-count');
        var submit   = document.getElementById(MODAL_ID + '-submit');

        if (textarea && counter) {
            textarea.addEventListener('input', function () {
                counter.textContent = String(textarea.value.length);
            });
        }
        if (submit) {
            submit.addEventListener('click', function (ev) {
                ev.preventDefault();
                submitReport();
            });
        }
        return modal;
    }

    function submitReport() {
        if (pending) return;

        var textarea = document.getElementById(MODAL_ID + '-message');
        var message  = textarea ? textarea.value.trim() : '';

        showError('');
        if (message.length < 5) {
            showError('Please describe the issue (at least 5 characters).');
            if (textarea) textarea.focus();
            return;
        }
        if (message.length > 1000) {
            showError('Message is too long (max 1000 characters).');
            return;
        }

        setPending(true);

        fetch(SUBMIT_URL, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                type: current.type || 'general',
                entity_id: current.id || '',
                message: message,
                page: window.location.pathname
            })
        })
        .then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                return { ok: res.ok, data: data };
            });
        })
        .then(function (result) {
            if (result.ok && result.data && result.data.success) {
                toast('Report submitted. Thanks!', 'Success', 'success');
                hideModal(document.getElementById(MODAL_ID));
                if (textarea) textarea.value = '';
                var counter = document.getElementById(MODAL_ID + '-count');
                if (counter) counter.textContent = '0';
                return;
            }
            showError(
                result.data && result.data.error
                    ? result.data.error
                    : 'Could not submit the report. Please try again.'
            );
        })
        .catch(function () {
            showError('Network error. Please check your connection and try again.');
        })
        .then(function () {
            setPending(false);
        });
    }

    function open(opts) {
        opts = opts || {};
        var modal = ensureModal();

        current = {
            type: opts.type ? String(opts.type) : 'general',
            id: opts.id != null ? String(opts.id) : '',
            title: opts.title ? String(opts.title) : ''
        };

        var titleEl = document.getElementById(MODAL_ID + '-title');
        var ctxEl   = document.getElementById(MODAL_ID + '-context');
        var textarea = document.getElementById(MODAL_ID + '-message');
        var counter  = document.getElementById(MODAL_ID + '-count');

        if (titleEl) titleEl.textContent = current.title || 'Report an issue';
        if (ctxEl) {
            var parts = ['Type: ' + current.type];
            if (current.id) parts.push('Ref: ' + current.id);
            parts.push('Page: ' + window.location.pathname);
            ctxEl.textContent = parts.join(' \u2022 ');
        }
        if (textarea) textarea.value = '';
        if (counter) counter.textContent = '0';

        showError('');
        setPending(false);
        showModal(modal);

        if (textarea) {
            setTimeout(function () { textarea.focus(); }, 200);
        }
    }

    function view(notification) {
        var data = (notification && notification.data) || {};
        var report = data.report || {};

        if (!document.getElementById(VIEW_ID)) {
            var html =
                '<div class="modal fade" id="' + VIEW_ID + '" tabindex="-1" aria-hidden="true">' +
                  '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">' +
                    '<div class="modal-content blur">' +
                      '<div class="modal-header border-0 pb-0">' +
                        '<h5 class="modal-title fw-bold"><i class="bx bx-flag text-danger me-1"></i> Issue Report</h5>' +
                        '<button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Close"></button>' +
                      '</div>' +
                      '<div class="modal-body" id="' + VIEW_ID + '-body"></div>' +
                      '<div class="modal-footer border-0 pt-0">' +
                        '<button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-coreui-dismiss="modal">Close</button>' +
                      '</div>' +
                    '</div>' +
                  '</div>' +
                '</div>';
            document.body.insertAdjacentHTML('beforeend', html);
        }

        var time = report.at || '';
        if (!time && notification && notification.created_at) {
            time = new Date(notification.created_at * 1000).toLocaleString();
        }

        var rows = [
            ['Reported by', report.reported_by || data.subtitle || '\u2014'],
            ['Type', report.type || (notification && notification.type) || '\u2014'],
            ['Entity ID', report.entity_id || '\u2014'],
            ['Page', report.page || '\u2014'],
            ['Time', time || '\u2014'],
            ['Message', report.message || (notification && notification.body) || '\u2014']
        ];

        var rowsHtml = rows.map(function (row) {
            return '<div class="mb-2">' +
                '<div class="text-body-tertiary small text-uppercase fw-semibold">' + esc(row[0]) + '</div>' +
                '<div class="text-body-emphasis" style="white-space:pre-wrap;word-break:break-word;">' + esc(row[1]) + '</div>' +
            '</div>';
        }).join('');

        var bodyEl = document.getElementById(VIEW_ID + '-body');
        if (bodyEl) bodyEl.innerHTML = rowsHtml;

        showModal(document.getElementById(VIEW_ID));
    }

    window.TomReport = {
        open: open,
        view: view
    };
})();
