/* ==========================================================================
   Matrimony — Admin panel behaviour
   Self-contained; does not depend on js/app.js so the panel can never be
   coupled to public-site code.
   --------------------------------------------------------------------------
     1. Theme (light/dark, persisted)
     2. Sidebar (collapse on desktop, off-canvas on mobile)
     3. Topbar search + "/" shortcut
     4. User menu
     5. Confirmations on destructive actions
     6. Double-submit protection
     7. Filter bar auto-submit
     8. Toasts
     9. Form validation (real-time, email uniqueness, password strength)
     10. Form state preservation
     11. Misc (flash autohide, table row click, plan editor)
   ========================================================================== */
(function () {
    'use strict';

    var THEME_KEY = 'adm.theme';
    var SIDEBAR_KEY = 'adm.sidebar.collapsed';

    function $(sel, root) { return (root || document).querySelector(sel); }
    function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    /* ------------------------------------------------------------ 1. theme */

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        try { localStorage.setItem(THEME_KEY, theme); } catch (e) { /* private mode */ }

        $$('[data-theme-toggle]').forEach(function (btn) {
            var isDark = theme === 'dark';
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            btn.setAttribute('title', isDark ? 'Switch to light theme' : 'Switch to dark theme');
            var label = $('[data-theme-label]', btn);
            if (label) { label.textContent = isDark ? 'Light' : 'Dark'; }
            $$('.admin-icon', btn).forEach(function (ico) { ico.style.display = ''; });
            var sun = $('[data-icon="sun"]', btn);
            var moon = $('[data-icon="moon"]', btn);
            if (sun) { sun.style.display = isDark ? 'none' : ''; }
            if (moon) { moon.style.display = isDark ? '' : 'none'; }
        });
    }

    function initTheme() {
        var stored = null;
        try { stored = localStorage.getItem(THEME_KEY); } catch (e) { /* noop */ }
        if (!stored) {
            stored = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        applyTheme(stored);

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-theme-toggle]');
            if (!btn) return;
            e.preventDefault();
            var next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        });
    }

    /* ---------------------------------------------------------- 2. sidebar */

    function initSidebar() {
        var body = document.body;

        // Mobile off-canvas
        $$('[data-sidebar-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                body.classList.toggle('admin-sidebar-open');
                syncScrim();
            });
        });
        var scrim = $('.admin-scrim');
        if (scrim) {
            scrim.addEventListener('click', function () {
                body.classList.remove('admin-sidebar-open');
                syncScrim();
            });
        }

        // Desktop collapse
        var collapsed = false;
        try { collapsed = localStorage.getItem(SIDEBAR_KEY) === '1'; } catch (e) { /* noop */ }
        body.classList.toggle('admin-sidebar-collapsed', collapsed);

        $$('[data-sidebar-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (window.matchMedia('(max-width: 1100px)').matches) {
                    body.classList.toggle('admin-sidebar-open');
                    syncScrim();
                    return;
                }
                collapsed = !collapsed;
                body.classList.toggle('admin-sidebar-collapsed', collapsed);
                try { localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0'); } catch (err) { /* noop */ }
            });
        });

        function syncScrim() {
            if (!scrim) return;
            scrim.classList.toggle('is-visible', body.classList.contains('admin-sidebar-open'));
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && body.classList.contains('admin-sidebar-open')) {
                body.classList.remove('admin-sidebar-open');
                syncScrim();
            }
        });
    }

    /* ------------------------------------------------------ 3. search key */

    function initSearchShortcut() {
        document.addEventListener('keydown', function (e) {
            var tag = (e.target && e.target.tagName) || '';
            var typing = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (e.target && e.target.isContentEditable);
            if (typing) return;
            if (e.key === '/' || (e.key === 'k' && (e.ctrlKey || e.metaKey))) {
                var input = $('[data-global-search]');
                if (input) {
                    e.preventDefault();
                    input.focus();
                    input.select();
                }
            }
        });

        // The topbar search is a real form so it works without JS.
        $$('[data-global-search-form]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var input = $('[data-global-search]', form);
                if (input && !input.value.trim()) { input.value = '__empty__'; }
            });
        });
    }

    /* ----------------------------------------------------- 4. user menu */

    function initUserMenu() {
        var menu = $('.admin-usermenu');
        if (!menu) return;
        var trigger = $('[data-usermenu-trigger]', menu);
        var panel = $('.admin-dropdown', menu);
        if (!trigger || !panel) return;

        function close() {
            panel.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }
        function open() {
            panel.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.classList.contains('is-open') ? close() : open();
        });
        document.addEventListener('click', function (e) {
            if (!menu.contains(e.target)) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    }

    /* --------------------------------------------------- 5. confirmations */

    function initConfirmations() {
        // A single shared modal keeps markup out of every view.
        var host = $('#adminConfirmModal');
        var pendingForm = null;
        var pendingEl = null;
        var pendingEndpoint = null;
        var pendingFields = null;
        var needsReason = false;

        if (!host) {
            // Fallback: native confirm() when the modal markup is absent.
            document.addEventListener('submit', function (e) {
                var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
                if (msg && !window.confirm(msg)) e.preventDefault();
            });
            return;
        }

        var titleEl = $('#adminConfirmTitle', host);
        var bodyEl = $('#adminConfirmBody', host);
        var okBtn = $('#adminConfirmOk', host);
        var cancelBtn = $('#adminConfirmCancel', host);
        var reasonWrap = $('#adminConfirmReasonWrap', host);
        var reasonInput = $('#adminConfirmReason', host);
        var reasonHint = $('#adminConfirmReasonHint', host);
        var modal = null;
        if (window.bootstrap) {
            modal = new window.bootstrap.Modal(host);
        }

        function open(title, message, onConfirm) {
            pendingForm = onConfirm.form || null;
            pendingEl = onConfirm.el || null;
            pendingEndpoint = onConfirm.endpoint || null;
            pendingFields = onConfirm.fields || null;
            needsReason = !!onConfirm.requiresReason;

            var dangerOk = !!onConfirm.danger;
            if (titleEl) titleEl.textContent = title;
            if (bodyEl) bodyEl.textContent = message;
            if (okBtn) {
                okBtn.className = 'btn btn-sm ' + (dangerOk ? 'btn-danger' : 'btn-primary');
                okBtn.textContent = onConfirm.confirmText || (dangerOk ? 'Confirm' : 'Save');
            }
            if (reasonWrap) { reasonWrap.classList.toggle('d-none', !needsReason); }
            if (reasonInput) {
                reasonInput.value = '';
                reasonInput.classList.remove('is-invalid');
            }
            if (reasonHint) {
                reasonHint.textContent = needsReason
                    ? 'Required. The member is notified with this reason.'
                    : '';
            }

            if (modal) {
                modal.show();
                if (needsReason) { setTimeout(function () { reasonInput && reasonInput.focus(); }, 180); }
            } else {
                host.style.display = 'block';
                host.classList.add('show');
            }
        }

        function close() {
            pendingForm = null;
            pendingEl = null;
            pendingEndpoint = null;
            pendingFields = null;
            needsReason = false;
            if (modal) { modal.hide(); } else { host.style.display = 'none'; host.classList.remove('show'); }
        }

        /** Build (and submit) a one-shot form for endpoint-driven actions. */
        function submitEndpoint(endpoint, fields) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            var csrf = meta ? meta.getAttribute('content') : '';
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = endpoint;
            form.style.display = 'none';

            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = 'csrf';
            token.value = csrf;
            form.appendChild(token);

            Object.keys(fields || {}).forEach(function (key) {
                if (fields[key] === null || fields[key] === undefined || fields[key] === '') return;
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        }

        function runPending() {
            var form = pendingForm;
            var el = pendingEl;
            var endpoint = pendingEndpoint;
            var fields = pendingFields;
            var reason = reasonInput ? reasonInput.value.trim() : '';

            if (needsReason && reason === '') {
                if (reasonInput) {
                    reasonInput.classList.add('is-invalid');
                    reasonInput.focus();
                }
                return;
            }
            if (needsReason) {
                fields = Object.assign({}, fields || {}, { reason: reason });
            }

            close();

            if (endpoint) {
                showLoading('Applying…');
                submitEndpoint(endpoint, fields);
                return;
            }
            if (form) {
                if (needsReason && reasonInput) {
                    var field = document.createElement('input');
                    field.type = 'hidden';
                    field.name = 'reason';
                    field.value = reason;
                    form.appendChild(field);
                }
                disableOnce(form);
                form.submit();
                return;
            }
            if (el) {
                disableOnce(el.closest('form') || el);
                el.click();
            }
        }

        document.addEventListener('submit', function (e) {
            var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
            if (!msg || e.target.dataset.confirmed === '1') return;
            e.preventDefault();
            open(e.target.getAttribute('data-confirm-title') || 'Please confirm', msg, { form: e.target });
        });

        document.addEventListener('click', function (e) {
            var el = e.target.closest('[data-confirm]');
            if (!el || el.tagName === 'FORM') return;
            e.preventDefault();

            var fields = null;
            var raw = el.getAttribute('data-fields');
            if (raw) {
                try { fields = JSON.parse(raw); } catch (err) { fields = null; }
            }

            open(el.getAttribute('data-confirm-title') || 'Please confirm', el.getAttribute('data-confirm'), {
                el: el,
                fields: fields,
                endpoint: el.getAttribute('data-endpoint') || null,
                requiresReason: el.hasAttribute('data-requires-reason'),
                danger: el.hasAttribute('data-confirm-danger'),
                confirmText: el.getAttribute('data-confirm-text')
            });
        });

        if (okBtn) okBtn.addEventListener('click', runPending);
        if (cancelBtn) cancelBtn.addEventListener('click', close);
        if (window.bootstrap && host) {
            host.addEventListener('hidden.bs.modal', function () {
                pendingForm = null;
                pendingEl = null;
                pendingEndpoint = null;
                pendingFields = null;
                needsReason = false;
            });
        }
    }

    /* -------------------------------------------- 6. double-submit guard */

    function enableOnce(form) {
        if (!form) return;
        delete form.dataset.busy;
        $$('[type="submit"]', form).forEach(function (btn) {
            btn.classList.remove('is-busy');
            btn.disabled = false;
        });
    }

    function disableOnce(form) {
        if (!form || form.dataset.busy === '1') return;
        form.dataset.busy = '1';
        $$('[type="submit"]', form).forEach(function (btn) {
            btn.classList.add('is-busy');
            btn.disabled = true;
        });
    }

    function initSubmitGuard() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            // Another handler (e.g. the confirm modal) already took over.
            if (e.defaultPrevented) return;
            if (form.dataset.busy === '1') { e.preventDefault(); return; }
            // Let the browser run native constraint validation first.
            if (!form.checkValidity || form.checkValidity()) {
                disableOnce(form);
            }
        });
    }

    /* ------------------------------------------------ 7. filter autosubmit */

    function initFilters() {
        $$('[data-autosubmit]').forEach(function (el) {
            el.addEventListener('change', function () {
                var form = el.closest('form');
                if (!form) return;
                var page = form.querySelector('[name="page"]');
                if (page) page.value = '1';
                form.submit();
            });
        });

        // Segmented filter groups (radio-style chips) submit on change.
        $$('[data-filter-radio]').forEach(function (input) {
            input.addEventListener('change', function () {
                var form = input.closest('form');
                if (form) form.submit();
            });
        });

        // Debounced search-as-you-type.
        $$('[data-search-submit]').forEach(function (input) {
            var timer = null;
            input.addEventListener('input', function () {
                var form = input.closest('form');
                if (!form) return;
                clearTimeout(timer);
                timer = setTimeout(function () {
                    var page = form.querySelector('[name="page"]');
                    if (page) page.value = '1';
                    form.submit();
                }, 550);
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { clearTimeout(timer); }
            });
        });

        // Clear-all link on filter bars
        $$('[data-filter-reset]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                var form = a.closest('form');
                if (!form) return;
                e.preventDefault();
                $$('input, select, textarea', form).forEach(function (f) {
                    if (f.type === 'hidden') return;
                    if (f.type === 'checkbox' || f.type === 'radio') { f.checked = false; }
                    else { f.value = ''; }
                });
                form.submit();
            });
        });
    }

    /* ---------------------------------------------------------- 8. toasts */

    function showLoading(text) {
        var el = document.createElement('div');
        el.className = 'admin-busy-overlay';
        el.setAttribute('role', 'status');
        el.innerHTML = '<span class="matrimony-loader">'
            + '<span class="matrimony-loader__rings">'
            + '<span class="matrimony-loader__ring matrimony-loader__ring--a"></span>'
            + '<span class="matrimony-loader__ring matrimony-loader__ring--b"></span>'
            + '<span class="matrimony-loader__heart"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></span>'
            + '</span>'
            + '<span class="matrimony-loader__brand">Matrimony Admin</span>'
            + '<span class="matrimony-loader__text">' + (text || 'Working…') + '</span>'
            + '<span class="visually-hidden">Loading</span>'
            + '</span>';
        document.body.appendChild(el);
    }

    var toastHost = null;
    function getToastHost() {
        if (!toastHost) {
            toastHost = $('#adminToasts');
            if (!toastHost) {
                toastHost = document.createElement('div');
                toastHost.id = 'adminToasts';
                toastHost.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                toastHost.setAttribute('aria-live', 'polite');
                document.body.appendChild(toastHost);
            }
        }
        return toastHost;
    }

    window.adminToast = function (message, type, ms) {
        var tone = ['success', 'danger', 'warning', 'info'].indexOf(type) > -1 ? type : 'info';
        var el = document.createElement('div');
        el.className = 'toast show align-items-center border-0 mb-2';
        el.setAttribute('role', 'status');
        el.style.background = 'var(--adm-surface)';
        el.style.color = 'var(--adm-text)';
        el.style.boxShadow = 'var(--adm-shadow-lg)';
        el.style.border = '1px solid var(--adm-border)';
        el.style.minWidth = '260px';

        var dot = document.createElement('span');
        dot.style.width = '8px';
        dot.style.height = '8px';
        dot.style.borderRadius = '50%';
        dot.style.margin = '0 .65rem';
        dot.style.background = 'var(--adm-' + (tone === 'danger' ? 'danger' : tone) + ')';
        el.appendChild(dot);

        var body = document.createElement('div');
        body.className = 'toast-body';
        body.textContent = message;
        el.appendChild(body);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close me-2';
        close.setAttribute('data-bs-dismiss', 'toast');
        close.setAttribute('aria-label', 'Dismiss');
        el.appendChild(close);

        getToastHost().appendChild(el);
        var life = ms || (tone === 'danger' ? 8000 : 4000);
        setTimeout(function () {
            if (window.bootstrap) {
                var inst = window.bootstrap.Toast.getOrCreateInstance(el, { delay: 250 });
                inst.hide();
            } else {
                el.remove();
            }
        }, life);
        setTimeout(function () { el.remove(); }, life + 1500);
    };

    /* ----------------------------------------------------- 9. validation */

    var FORM_STATE_KEY = 'adm.form.state';
    var EMAIL_CHECK_DEBOUNCE = 500;

    function initFormValidation() {
        // Email uniqueness check with debounce
        var emailTimers = {};
        $$('[name="email"]').forEach(function (input) {
            input.addEventListener('input', function () {
                var form = input.closest('form');
                if (!form) return;
                var userId = form.querySelector('[name="user_id"]');
                var userIdVal = userId ? userId.value : '';

                clearTimeout(emailTimers[input.name]);
                emailTimers[input.name] = setTimeout(function () {
                    checkEmailUniqueness(input.value, userIdVal, input);
                }, EMAIL_CHECK_DEBOUNCE);
            });
        });

        // Password strength meter
        $$('[name="password"]').forEach(function (input) {
            initPasswordStrength(input);
        });

        // Character counters for textareas
        $$('textarea[maxlength]').forEach(function (textarea) {
            initCharacterCounter(textarea);
        });

        // Cross-field validation
        initCrossFieldValidation();

        // File upload validation
        initFileUploadValidation();

        // Form state preservation
        initFormStatePreservation();
    }

    function checkEmailUniqueness(email, userId, input) {
        email = email.trim();
        if (!email || !input.checkValidity()) {
            clearFieldError(input, 'email-exists');
            return;
        }

        var meta = document.querySelector('meta[name="csrf-token"]');
        var csrf = meta ? meta.getAttribute('content') : '';

        var formData = new FormData();
        formData.append('email', email);
        if (userId) formData.append('user_id', userId);
        formData.append('csrf', csrf);

        fetch('/api/admin/users/check-email', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.exists) {
                showFieldError(input, 'email-exists', 'An account with this email already exists.');
            } else {
                clearFieldError(input, 'email-exists');
            }
        })
        .catch(function () {
            // Silently fail - let server-side validation handle it
        });
    }

    function initPasswordStrength(input) {
        var container = input.closest('.admin-field');
        if (!container) return;

        // Create strength meter UI
        var meter = document.createElement('div');
        meter.className = 'admin-password-strength';
        meter.innerHTML = '\
            <div class="admin-password-strength__bar">\n\
                <div class="admin-password-strength__fill"></div>\n\
            </div>\n\
            <div class="admin-password-strength__label">Password strength</div>\n\
            <div class="admin-password-strength__requirements">\n\
                <span data-req="length">10+ characters</span>\n\
                <span data-req="lower">Lowercase letter</span>\n\
                <span data-req="upper">Uppercase letter</span>\n\
                <span data-req="number">Number</span>\n\
            </div>\n\
        ';
        container.appendChild(meter);

        var fill = meter.querySelector('.admin-password-strength__fill');
        var label = meter.querySelector('.admin-password-strength__label');
        var reqs = meter.querySelectorAll('[data-req]');

        input.addEventListener('input', function () {
            var password = input.value;
            var score = 0;
            var met = { length: false, lower: false, upper: false, number: false };

            if (password.length >= 10) { score++; met.length = true; }
            if (/[a-z]/.test(password)) { score++; met.lower = true; }
            if (/[A-Z]/.test(password)) { score++; met.upper = true; }
            if (/[0-9]/.test(password)) { score++; met.number = true; }

            // Update requirements list
            reqs.forEach(function (req) {
                var key = req.getAttribute('data-req');
                req.classList.toggle('is-met', met[key]);
            });

            // Update meter
            var levels = ['Weak', 'Fair', 'Good', 'Strong'];
            var level = levels[Math.min(score, 3)];
            var widths = ['25%', '50%', '75%', '100%'];
            var colors = ['var(--adm-danger)', 'var(--adm-warning)', 'var(--adm-success)', 'var(--adm-success)'];

            fill.style.width = widths[Math.min(score, 3)];
            fill.style.background = colors[Math.min(score, 3)];
            label.textContent = level;
            meter.className = 'admin-password-strength admin-password-strength--' + level.toLowerCase();
        });
    }

    function initCharacterCounter(textarea) {
        var container = textarea.closest('.admin-field');
        if (!container) return;

        var max = parseInt(textarea.getAttribute('maxlength'), 10) || 2000;
        var counter = document.createElement('div');
        counter.className = 'admin-char-counter';
        counter.textContent = '0 / ' + max;
        container.appendChild(counter);

        function update() {
            var len = textarea.value.length;
            counter.textContent = len + ' / ' + max;
            counter.classList.toggle('is-near-limit', len >= max * 0.9);
            counter.classList.toggle('is-at-limit', len >= max);
        }

        textarea.addEventListener('input', update);
        update();
    }

    function initCrossFieldValidation() {
        var form = $('form[action*="/api/admin/users/"]');
        if (!form) return;

        var minAge = form.querySelector('[name="min_age"]');
        var maxAge = form.querySelector('[name="max_age"]');
        var minHeight = form.querySelector('[name="min_height_cm"]');
        var maxHeight = form.querySelector('[name="max_height_cm"]');

        function validatePair(min, max, fieldName) {
            if (!min || !max) return;
            var minVal = parseInt(min.value, 10);
            var maxVal = parseInt(max.value, 10);

            if (min.value && max.value && minVal > maxVal) {
                showFieldError(max, fieldName, 'Minimum must be less than or equal to maximum.');
            } else {
                clearFieldError(max, fieldName);
            }
        }

        if (minAge && maxAge) {
            minAge.addEventListener('input', function () { validatePair(minAge, maxAge, 'age-range'); });
            maxAge.addEventListener('input', function () { validatePair(minAge, maxAge, 'age-range'); });
        }

        if (minHeight && maxHeight) {
            minHeight.addEventListener('input', function () { validatePair(minHeight, maxHeight, 'height-range'); });
            maxHeight.addEventListener('input', function () { validatePair(minHeight, maxHeight, 'height-range'); });
        }
    }

    function initFileUploadValidation() {
        $$('input[type="file"]').forEach(function (input) {
            input.addEventListener('change', function () {
                var file = input.files[0];
                if (!file) return;

                var maxSize = 5 * 1024 * 1024; // 5MB
                var allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                if (file.size > maxSize) {
                    showFieldError(input, 'file-size', 'File must be under 5MB.');
                    input.value = '';
                    return;
                }

                if (!allowedTypes.includes(file.type)) {
                    showFieldError(input, 'file-type', 'Only JPG, PNG, WebP, or GIF images are allowed.');
                    input.value = '';
                    return;
                }

                clearFieldError(input, 'file-size');
                clearFieldError(input, 'file-type');

                // Show preview for profile photo
                if (input.name === 'photo') {
                    showImagePreview(input, file);
                }
            });
        });
    }

    function showImagePreview(input, file) {
        var container = input.closest('.admin-field');
        if (!container) return;

        var existingPreview = container.querySelector('.admin-image-preview');
        if (existingPreview) existingPreview.remove();

        var reader = new FileReader();
        reader.onload = function (e) {
            var preview = document.createElement('div');
            preview.className = 'admin-image-preview';
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="max-height:140px;border-radius:10px;border:1px solid var(--adm-border)">';
            container.appendChild(preview);
        };
        reader.readAsDataURL(file);
    }

    function showFieldError(input, errorKey, message) {
        var container = input.closest('.admin-field');
        if (!container) return;

        input.classList.add('is-invalid');

        var errorEl = container.querySelector('.invalid-feedback[data-error="' + errorKey + '"]');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'invalid-feedback d-block';
            errorEl.setAttribute('data-error', errorKey);
            container.appendChild(errorEl);
        }
        errorEl.textContent = message;
    }

    function clearFieldError(input, errorKey) {
        var container = input.closest('.admin-field');
        if (!container) return;

        var errorEl = container.querySelector('.invalid-feedback[data-error="' + errorKey + '"]');
        if (errorEl) errorEl.remove();

        // Remove is-invalid if no other errors remain
        var hasErrors = container.querySelector('.invalid-feedback');
        if (!hasErrors) {
            input.classList.remove('is-invalid');
        }
    }

    function initFormStatePreservation() {
        var form = $('form[action*="/api/admin/users/"]');
        if (!form) return;

        var formKey = FORM_STATE_KEY + '.' + window.location.pathname;

        // Baseline of the values the form loaded with, for dirty comparison.
        var initial = snapshotForm(form);
        var submitting = false;

        // Save state on input changes
        form.addEventListener('input', debounce(function () {
            if (submitting) return;
            saveFormState(form, formKey);
        }, 1000));

        form.addEventListener('change', function () {
            if (submitting) return;
            saveFormState(form, formKey);
        });

        // Check for saved state on load
        try {
            var saved = localStorage.getItem(formKey);
            if (saved) {
                offerRestore(form, formKey, JSON.parse(saved));
            }
        } catch (e) {
            // localStorage disabled or error
        }

        // A real submit clears saved state and disables the unsaved-changes
        // prompt, so saving never triggers "Changes may not be saved".
        form.addEventListener('submit', function () {
            submitting = true;
            try { localStorage.removeItem(formKey); } catch (e) {}
        });

        // Warn before navigation only when there are genuine unsaved edits.
        window.addEventListener('beforeunload', function (e) {
            if (submitting) return;
            if (formHasChanges(form, initial)) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    }

    function saveFormState(form, key) {
        var data = {};
        $$('input, select, textarea', form).forEach(function (field) {
            if (field.type === 'checkbox' || field.type === 'radio') {
                if (field.checked) data[field.name] = field.value;
            } else if (field.type !== 'file' && field.type !== 'submit') {
                data[field.name] = field.value;
            }
        });

        try {
            localStorage.setItem(key, JSON.stringify({
                data: data,
                timestamp: Date.now()
            }));
        } catch (e) {
            // localStorage disabled or quota exceeded
        }
    }

    function offerRestore(form, key, saved) {
        var age = Date.now() - (saved.timestamp || 0);
        var maxAge = 30 * 60 * 1000; // 30 minutes

        if (age > maxAge) {
            try { localStorage.removeItem(key); } catch (e) {}
            return;
        }

        var message = 'You have unsaved changes from ' + Math.round(age / 60000) + ' minutes ago. Restore them?';
        if (!window.confirm(message)) {
            try { localStorage.removeItem(key); } catch (e) {}
            return;
        }

        // Restore form values
        Object.keys(saved.data || {}).forEach(function (name) {
            var field = form.querySelector('[name="' + name + '"]');
            if (!field) return;

            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = (field.value === saved.data[name]);
            } else {
                field.value = saved.data[name];
            }

            // Trigger input events to update counters, meters, etc.
            field.dispatchEvent(new Event('input'));
            field.dispatchEvent(new Event('change'));
        });

        try { localStorage.removeItem(key); } catch (e) {}
        window.adminToast('Form restored from saved state', 'success');
    }

    /** Capture the current form values (hidden and file inputs excluded). */
    function snapshotForm(form) {
        var snapshot = {};
        $$('input, select, textarea', form).forEach(function (field) {
            if (!field.name) return;
            if (field.type === 'hidden' || field.type === 'file'
                || field.type === 'submit' || field.type === 'button') return;
            if (field.type === 'checkbox' || field.type === 'radio') {
                if (!Array.isArray(snapshot[field.name])) snapshot[field.name] = [];
                if (field.checked) snapshot[field.name].push(field.value);
            } else {
                snapshot[field.name] = field.value;
            }
        });
        return snapshot;
    }

    function formHasChanges(form, initial) {
        if (!initial) return false;
        var current = snapshotForm(form);
        var keys = Object.keys(initial).concat(Object.keys(current));
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            var before = initial[key];
            var after = current[key];
            if (Array.isArray(before) || Array.isArray(after)) {
                before = Array.isArray(before) ? before.slice().sort() : [];
                after = Array.isArray(after) ? after.slice().sort() : [];
                if (before.length !== after.length || before.join('\u0000') !== after.join('\u0000')) {
                    return true;
                }
            } else if (before !== after) {
                return true;
            }
        }
        return false;
    }

    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var args = arguments;
            var ctx = this;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(ctx, args);
            }, delay);
        };
    }

    /* ---------------------------------------------------------- 10. misc */

    function initFlashAutohide() {
        $$('[data-admin-autodismiss]').forEach(function (el) {
            setTimeout(function () {
                if (window.bootstrap) {
                    window.bootstrap.Alert.getOrCreateInstance(el).close();
                } else {
                    el.remove();
                }
            }, parseInt(el.getAttribute('data-admin-autodismiss'), 10) || 6000);
        });
    }

    function initTableLinks() {
        // Clicking anywhere in a [data-row-link] row follows its link,
        // except on interactive controls.
        $$('[data-row-link]').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('a, button, input, select, textarea, form, label')) return;
                var link = row.getAttribute('data-row-link');
                if (link) window.location.href = link;
            });
            row.classList.add('is-clickable');
        });
    }

    function initPlanEditors() {
        $$('[data-plan-unlimited]').forEach(function (cb) {
            var limit = document.getElementById(cb.getAttribute('data-plan-unlimited'));
            if (!limit) return;
            function sync() {
                limit.disabled = cb.checked;
                if (cb.checked) limit.value = '';
                limit.classList.toggle('is-disabled', cb.checked);
            }
            cb.addEventListener('change', sync);
            sync();
        });
    }

    function initCopyButtons() {
        $$('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var text = btn.getAttribute('data-copy');
                if (!text) return;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function () {
                        window.adminToast('Copied to clipboard', 'success');
                    });
                } else {
                    window.adminToast('Copy failed — select the text manually', 'warning');
                }
            });
        });
    }

    function initTableOverflowHints() {
        var wraps = $$('.admin-tablewrap');
        if (!wraps.length) return;

        function sync() {
            wraps.forEach(function (w) {
                w.classList.toggle('admin-tablewrap--overflowing', w.scrollWidth > w.clientWidth + 1);
            });
        }
        sync();
        window.addEventListener('resize', sync);
        if (window.ResizeObserver) {
            var ro = new ResizeObserver(sync);
            wraps.forEach(function (w) { ro.observe(w); });
        }
    }

    /* ------------------------------------------- multi-select dropdowns */
    /*
     * A .admin-ms is a <details> whose panel holds checkboxes that post as
     * `name[]`. The native control cannot show a live summary, so mirror the
     * ticked labels into the summary and keep one panel open at a time.
     */
    function initMultiSelects() {
        var groups = $$('[data-admin-ms]');
        if (!groups.length) return;

        function summaryOf(group) {
            var empty = group.getAttribute('data-empty') || 'Nothing selected';
            var picked = $$('.admin-ms__option', group).filter(function (opt) {
                var cb = $('input[type="checkbox"]', opt);
                return cb && cb.checked;
            });
            if (!picked.length) return { text: empty, empty: true };
            if (picked.length > 3) return { text: picked.length + ' selected', empty: false };
            return {
                text: picked.map(function (opt) {
                    var label = $('.admin-ms__option-label', opt);
                    return label ? label.textContent.trim() : '';
                }).filter(Boolean).join(', '),
                empty: false,
            };
        }

        function sync(group) {
            var value = $('.admin-ms__value', group);
            if (!value) return;
            var state = summaryOf(group);
            value.textContent = state.text;
            value.classList.toggle('is-placeholder', state.empty);
        }

        groups.forEach(function (group) {
            $$('input[type="checkbox"]', group).forEach(function (cb) {
                cb.addEventListener('change', function () { sync(group); });
            });
            group.addEventListener('toggle', function () {
                if (group.open) {
                    groups.forEach(function (other) {
                        if (other !== group) other.open = false;
                    });
                }
            });
            sync(group);
        });

        document.addEventListener('click', function (e) {
            groups.forEach(function (group) {
                if (group.open && !group.contains(e.target)) group.open = false;
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            groups.forEach(function (group) {
                if (group.open) {
                    group.open = false;
                    var control = $('.admin-ms__control', group);
                    if (control) control.focus();
                }
            });
        });
    }

    /* ------------------------------------------------ password view toggle */

    function initPasswordToggles() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-pw-toggle]');
            if (!btn) { return; }
            var input = btn.parentNode.querySelector('input');
            if (!input) { return; }
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            var eye = btn.querySelector('.pw-eye');
            var eyeOff = btn.querySelector('.pw-eye-off');
            if (eye) { eye.classList.toggle('d-none'); }
            if (eyeOff) { eyeOff.classList.toggle('d-none'); }
            btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    }

    /* -------------------------------------------------------------- boot */

    onReady(function () {
        initTheme();
        initSidebar();
        initSearchShortcut();
        initUserMenu();
        initConfirmations();
        initSubmitGuard();
        initFilters();
        initFlashAutohide();
        initTableLinks();
        initTableOverflowHints();
        initPlanEditors();
        initCopyButtons();
        initMultiSelects();
        initFormValidation();
        initPasswordToggles();
    });

    // Exposed for views that need programmatic submits.
    window.adminDisableOnce = disableOnce;
})();
