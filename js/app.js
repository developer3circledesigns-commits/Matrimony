(function () {
    'use strict';

    var _confirmCallback = null;
    var _toastInstances = [];

    function showToast(message, type, delay) {
        var container = document.getElementById('toast-container');
        if (!container) return;
        var bgMap = { success: 'bg-success', danger: 'bg-danger', error: 'bg-danger', warning: 'bg-warning text-dark', info: 'bg-info text-dark' };
        var bgClass = bgMap[type] || 'bg-secondary';
        var id = 't-' + Date.now() + '-' + Math.random().toString(36).slice(2, 6);
        var html = '<div id="' + id + '" class="toast ' + bgClass + ' text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">' +
            '<div class="toast-body d-flex align-items-center justify-content-between gap-2">' +
            '<span class="flex-grow-1">' + escapeHtml(message) + '</span>' +
            '<button type="button" class="btn-close btn-close-white ms-2 flex-shrink-0" data-bs-dismiss="toast" aria-label="Close"></button>' +
            '</div></div>';
        container.insertAdjacentHTML('beforeend', html);
        var el = document.getElementById(id);
        if (!el) return;
        var instance = new bootstrap.Toast(el, { delay: delay || 4000, animation: true });
        _toastInstances.push(instance);
        instance.show();
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        return instance;
    }

    function showConfirm(options, callback) {
        var el = document.getElementById('confirmModal');
        if (!el) return;
        var titleEl = document.getElementById('confirmModalLabel');
        var bodyEl = document.getElementById('confirmModalBody');
        var iconEl = document.getElementById('confirmModalIcon');
        var confirmBtn = document.getElementById('confirmModalConfirm');
        var cancelBtn = document.getElementById('confirmModalCancel');
        if (!titleEl || !bodyEl || !iconEl || !confirmBtn || !cancelBtn) return;

        var iconMap = {
            danger: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            warning: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ffc107" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0dcaf0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };
        iconEl.innerHTML = iconMap[options.danger ? 'danger' : options.type || 'warning'];

        titleEl.textContent = options.title || 'Confirm';
        bodyEl.innerHTML = options.message || 'Are you sure?';
        confirmBtn.textContent = options.confirmText || 'Confirm';
        confirmBtn.className = 'btn ' + (options.confirmClass || 'btn-danger');
        if (options.cancelText) cancelBtn.textContent = options.cancelText;

        _confirmCallback = callback;
        var modal = bootstrap.Modal.getInstance(el);
        if (!modal) modal = new bootstrap.Modal(el, { backdrop: 'static', keyboard: true });
        modal.show();

        function onConfirm() {
            modal.hide();
            if (_confirmCallback) { _confirmCallback(true); _confirmCallback = null; }
            cleanup();
        }
        function onCancel() {
            modal.hide();
            if (_confirmCallback) { _confirmCallback(false); _confirmCallback = null; }
            cleanup();
        }
        function onKeydown(e) {
            if (e.key === 'Escape') { onCancel(); }
        }
        function cleanup() {
            confirmBtn.removeEventListener('click', onConfirm);
            cancelBtn.removeEventListener('click', onCancel);
            el.removeEventListener('keydown', onKeydown);
            el.removeEventListener('hidden.bs.modal', cleanup);
        }
        confirmBtn.addEventListener('click', onConfirm);
        cancelBtn.addEventListener('click', onCancel);
        el.addEventListener('keydown', onKeydown);
        el.addEventListener('hidden.bs.modal', cleanup);
    }

    function showAlert(type, title, message, callback) {
        var el = document.getElementById('alertModal');
        if (!el) return;
        var titleEl = document.getElementById('alertModalLabel');
        var bodyEl = document.getElementById('alertModalBody');
        var iconEl = document.getElementById('alertModalIcon');
        var closeBtn = document.getElementById('alertModalClose');
        if (!titleEl || !bodyEl || !iconEl || !closeBtn) return;

        var headerIconMap = {
            success: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#198754" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            error: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            danger: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            warning: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ffc107" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0dcaf0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };
        iconEl.innerHTML = headerIconMap[type] || headerIconMap.info;
        titleEl.textContent = title || 'Notification';
        bodyEl.innerHTML = message || '';
        if (type === 'error' || type === 'danger') {
            closeBtn.className = 'btn btn-danger';
        } else if (type === 'warning') {
            closeBtn.className = 'btn btn-warning text-dark';
        } else if (type === 'success') {
            closeBtn.className = 'btn btn-success';
        } else {
            closeBtn.className = 'btn btn-primary';
        }
        closeBtn.textContent = type === 'error' || type === 'danger' ? 'Dismiss' : 'OK';

        var modal = bootstrap.Modal.getInstance(el);
        if (!modal) modal = new bootstrap.Modal(el, { backdrop: 'static', keyboard: true });
        modal.show();

        function onClose() {
            modal.hide();
            cleanup();
            if (callback) callback();
        }
        function onKeydown(e) {
            if (e.key === 'Escape') { onClose(); }
        }
        function cleanup() {
            closeBtn.removeEventListener('click', onClose);
            el.removeEventListener('keydown', onKeydown);
            el.removeEventListener('hidden.bs.modal', cleanup);
        }
        closeBtn.addEventListener('click', onClose);
        el.addEventListener('keydown', onKeydown);
        el.addEventListener('hidden.bs.modal', cleanup);
    }

    function showLoading(message) {
        var el = document.getElementById('loadingModal');
        if (!el) return;
        var textEl = document.getElementById('loadingModalText');
        if (textEl) textEl.textContent = message || 'Please wait...';
        var modal = bootstrap.Modal.getInstance(el);
        if (!modal) modal = new bootstrap.Modal(el, { backdrop: 'static', keyboard: false });
        modal.show();
    }
    function hideLoading() {
        var el = document.getElementById('loadingModal');
        if (!el) return;
        var modal = bootstrap.Modal.getInstance(el);
        if (modal) modal.hide();
    }

    window.addEventListener('beforeunload', function () {
        _toastInstances.forEach(function (t) { try { t.hide(); } catch(e) {} });
        _toastInstances = [];
    });

    window.showToast = showToast;
    window.showConfirm = function(opts, cb) { showConfirm(opts, cb); };
    window.showAlert = function(type, title, msg, cb) { showAlert(type, title, msg, cb); };
    window.showInfoAlert = function(title, msg) { showAlert('info', title || 'Information', msg); };
    window.showSuccessAlert = function(title, msg) { showAlert('success', title || 'Success', msg); };
    window.showWarningAlert = function(title, msg) { showAlert('warning', title || 'Warning', msg); };
    window.showErrorAlert = function(title, msg) { showAlert('error', title || 'Error', msg); };
    window.showLoading = showLoading;
    window.hideLoading = hideLoading;

    window.deactivateAccount = function () {
        var reason = (document.getElementById('deactivation-reason') || {}).value || '';
        showConfirm({
            title: 'Deactivate Account',
            message: 'Are you sure you want to deactivate your account? You will not be able to log in or appear in searches until you reactivate.',
            confirmText: 'Deactivate',
            confirmClass: 'btn-danger',
            cancelText: 'Cancel'
        }, function (confirmed) {
            if (!confirmed) return;
            showLoading();
            var csrf = document.querySelector('[data-csrf]')?.getAttribute('data-csrf') || '';
            fetch('/api/profile/deactivate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ reason: reason, csrf: csrf })
            }).then(function (r) { return r.json(); }).then(function (res) {
                hideLoading();
                if (res.success) {
                    showSuccessAlert('Account Deactivated', 'Your account has been deactivated. You can reactivate anytime by logging in again.', function () {
                        window.location.href = res.redirect || '/';
                    });
                } else {
                    showErrorAlert('Error', res.error || 'Deactivation failed');
                }
            }).catch(function () {
                hideLoading();
                showErrorAlert('Error', 'Network error. Please try again.');
            });
        });
    };

    window.reactivateAccount = function () {
        var password = document.getElementById('reactivate-password')?.value || '';
        if (!password) { showWarningAlert('Required', 'Please enter your password.'); return; }
        showLoading();
        var csrf = document.querySelector('[data-csrf]')?.getAttribute('data-csrf') || '';
        fetch('/api/profile/reactivate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ password: password, csrf: csrf })
        }).then(function (r) { return r.json(); }).then(function (res) {
            hideLoading();
            if (res.success) {
                showSuccessAlert('Account Reactivated', 'Your account is now active again. Welcome back!', function () {
                    window.location.reload();
                });
            } else {
                showErrorAlert('Error', res.error || 'Reactivation failed');
            }
        }).catch(function () {
            hideLoading();
            showErrorAlert('Error', 'Network error. Please try again.');
        });
    };

    var openPanelId = null;

    function togglePanel(panelId) {
        var panel = document.getElementById(panelId);
        if (!panel) return;
        if (openPanelId === panelId) { closeAllPanels(); return; }
        closeAllPanels();
        panel.classList.add('open');
        openPanelId = panelId;
        var btn = document.querySelector('[data-panel="' + panelId + '"]');
        if (btn) {
            btn.classList.add('active');
            btn.setAttribute('aria-expanded', 'true');
        }
    }
    window.togglePanel = togglePanel;

    function closeAllPanels() {
        document.querySelectorAll('.filter-panel.open').forEach(function(p) { p.classList.remove('open'); });
        document.querySelectorAll('[data-panel]').forEach(function(b) {
            b.classList.remove('active');
            b.setAttribute('aria-expanded', 'false');
        });
        openPanelId = null;
    }

    function applyPanel(panelId) {
        closeAllPanels();
        updateFilterUI();
    }
    window.applyPanel = applyPanel;

    var searchDebounce;
    function onSearchChange() {
        clearTimeout(searchDebounce);
        filterState.search = document.querySelector('[name="search"]')?.value || '';
        searchDebounce = setTimeout(function() {
            matchesState.page = 1;
            loadMatches(1, true);
        }, 500);
    }
    window.onSearchChange = onSearchChange;

    function onAgeChange() {
        filterState.age.min = document.getElementById('age-min').value || '';
        filterState.age.max = document.getElementById('age-max').value || '';
    }
    window.onAgeChange = onAgeChange;

    document.addEventListener('change', function(e) {
        handleAgeFilterInput(e.target);
    });
    document.addEventListener('input', function(e) {
        handleAgeFilterInput(e.target);
    });
    function handleAgeFilterInput(el) {
        var ageInput = el.closest ? el.closest('[data-testid="age-filter"]') : null;
        if (!ageInput) return;
        var val = ageInput.value.trim();
        var parts = val.split('-');
        if (parts.length === 2) {
            var ageMin = document.getElementById('age-min');
            var ageMax = document.getElementById('age-max');
            if (ageMin) ageMin.value = parts[0].trim();
            if (ageMax) ageMax.value = parts[1].trim();
            filterState.age.min = parts[0].trim();
            filterState.age.max = parts[1].trim();
            updateFilterUI();
            matchesState.page = 1;
            loadMatches(1, true);
        }
    }

    function onLocationChange() {
        filterState.location.state = document.getElementById('location-state').value || '';
        filterState.location.city = document.getElementById('location-city').value || '';
    }
    window.onLocationChange = onLocationChange;

    function onReligionChange() {
        filterState.religion = [];
        document.querySelectorAll('#religion-list input[type="checkbox"]:checked').forEach(function(cb) {
            filterState.religion.push(cb.value);
        });
    }
    window.onReligionChange = onReligionChange;

    function onMaritalChange() {
        filterState.marital = [];
        document.querySelectorAll('#marital-list input[type="checkbox"]:checked').forEach(function(cb) {
            filterState.marital.push(cb.value);
        });
    }
    window.onMaritalChange = onMaritalChange;

    function onMoreChange() {
        filterState.caste = document.getElementById('caste-select').value || '';
        filterState.tongue = document.getElementById('tongue-select').value || '';
        filterState.education = document.getElementById('education-select').value || '';
        filterState.occupation = document.getElementById('occupation-select').value || '';
        filterState.income = document.getElementById('income-select').value || '';
        filterState.diet = document.getElementById('diet-select').value || '';

        filterState.photoRequired = document.getElementById('photo-req').checked;
        filterState.verifiedOnly = document.getElementById('verified-only').checked;
        filterState.excludeContacted = document.getElementById('exclude-contacted').checked;
    }
    window.onMoreChange = onMoreChange;

    function clearFilter(name) {
        switch (name) {
            case 'search':
                clearTimeout(searchDebounce);
                var inp = document.querySelector('[name="search"]');
                if (inp) inp.value = '';
                filterState.search = '';
                updateFilterUI();
                closeAllPanels();
                matchesState.page = 1;
                loadMatches(1, true);
                return;
            case 'age':
                filterState.age = { min: '', max: '' };
                var el1 = document.getElementById('age-min');
                var el2 = document.getElementById('age-max');
                if (el1) el1.value = '';
                if (el2) el2.value = '';
                break;
            case 'location':
                filterState.location = { state: '', city: '' };
                var el3 = document.getElementById('location-state');
                var el4 = document.getElementById('location-city');
                if (el3) el3.value = '';
                if (el4) el4.value = '';
                break;
            case 'religion':
                filterState.religion = [];
                document.querySelectorAll('#religion-list input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
                break;
        }
        updateFilterUI();
        closeAllPanels();
    }
    window.clearFilter = clearFilter;

    function clearAllFilters() {
        filterState = {
            search: '',
            age: { min: '', max: '' },
            location: { state: '', city: '' },
            religion: [],
            marital: ['never_married'],
            caste: '',
            tongue: '',
            education: '',
            occupation: '',
            income: '',
            diet: '',
            photoRequired: false,
            verifiedOnly: false,
            excludeContacted: false,
        };
        document.querySelectorAll('#filter-form input[type="text"], #filter-form input[type="search"]').forEach(function(i) { i.value = ''; });
        document.querySelectorAll('#filter-form select').forEach(function(s) { s.selectedIndex = 0; });
        document.querySelectorAll('#filter-form input[type="checkbox"]').forEach(function(c) { c.checked = c.value === 'never_married'; });
        var hfNew = document.getElementById('hf-new-profiles');
        var hfPremium = document.getElementById('hf-premium-only');
        var hfActive = document.getElementById('hf-recently-active');
        if (hfNew) hfNew.value = '';
        if (hfPremium) hfPremium.value = '';
        if (hfActive) hfActive.value = '';
        document.querySelectorAll('.quick-chip').forEach(function(c) {
            c.classList.remove('active');
            c.setAttribute('aria-pressed', 'false');
        });
        var allChip = document.querySelector('.quick-chip[data-preset="all"]');
        if (allChip) {
            allChip.classList.add('active');
            allChip.setAttribute('aria-pressed', 'true');
        }
        updateFilterUI();
        matchesState.page = 1;
        loadMatches(1, true);
        closeAllPanels();
    }
    window.clearAllFilters = clearAllFilters;

    function removeFilterPill(key) {
        switch (key) {
            case 'search': clearTimeout(searchDebounce); var inp=document.querySelector('[name="search"]'); if(inp)inp.value=''; filterState.search=''; matchesState.page=1; loadMatches(1,true); return;
            case 'age': filterState.age={min:'',max:''}; var a1=document.getElementById('age-min'),a2=document.getElementById('age-max'); if(a1)a1.value=''; if(a2)a2.value=''; break;
            case 'location': filterState.location={state:'',city:''}; var l1=document.getElementById('location-state'),l2=document.getElementById('location-city'); if(l1)l1.value=''; if(l2)l2.value=''; break;
            case 'religion': filterState.religion=[]; document.querySelectorAll('#religion-list input[type="checkbox"]').forEach(function(c){c.checked=false}); break;
            case 'marital': filterState.marital=['never_married']; document.querySelectorAll('#marital-list input[type="checkbox"]').forEach(function(c){c.checked=c.value==='never_married'}); break;
            case 'caste': filterState.caste=''; var cs=document.getElementById('caste-select'); if(cs)cs.value=''; break;
            case 'tongue': filterState.tongue=''; break;
            case 'education': filterState.education=''; break;
            case 'occupation': filterState.occupation=''; break;
            case 'income': filterState.income=''; break;
            case 'diet': filterState.diet=''; break;
            case 'photoReq': filterState.photoRequired=false; var pr=document.getElementById('photo-req'); if(pr)pr.checked=false; break;
            case 'verified': filterState.verifiedOnly=false; var vo=document.getElementById('verified-only'); if(vo)vo.checked=false; break;
            case 'exclude': filterState.excludeContacted=false; var ec=document.getElementById('exclude-contacted'); if(ec)ec.checked=false; break;
        }
        updateFilterUI();
    }
    window.removeFilterPill = removeFilterPill;

    function removeSkeleton() {
        var b = document.body;
        if (b) b.classList.remove('skeleton-active');
    }
    if (document.readyState === 'complete') {
        removeSkeleton();
    } else {
        window.addEventListener('load', removeSkeleton);
    }
    try { setTimeout(removeSkeleton, 2500); } catch(e) {}

    function animateCounter(el) {
        var target = parseInt(el.getAttribute('data-target'), 10);
        if (isNaN(target)) return;
        var duration = 2000;
        var start = 0;
        var startTime = null;
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var current = Math.floor(progress * target);
            el.textContent = current.toLocaleString();
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString();
            }
        }
        requestAnimationFrame(step);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-auto-dismiss]').forEach(function (el) {
            setTimeout(function () {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, parseInt(el.dataset.autoDismiss, 10) || 5000);
        });
        document.querySelectorAll('[data-counter]').forEach(function (el) {
            animateCounter(el);
        });
    });

    function getNavbarHeight() {
        var navbar = document.querySelector('.navbar');
        return navbar ? navbar.offsetHeight : 72;
    }

    document.addEventListener('click', function (e) {
        var target = e.target.closest('a[href^="#"]');
        if (!target) return;
        var id = target.getAttribute('href').slice(1);
        if (!id) return;
        var el = document.getElementById(id);
        if (!el) return;
        e.preventDefault();
        var offset = getNavbarHeight() + 16;
        var top = el.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: top, behavior: 'smooth' });
    });

    var matchesState = {
        page: 1,
        perPage: 24,
        sort: 'compatibility',
        total: 0,
        loading: false,
        hasMore: true,
    };

    var filterState = {
        search: '',
        age: { min: '', max: '' },
        location: { state: '', city: '' },
        religion: [],
        marital: ['never_married'],
        caste: '',
        tongue: '',
        education: '',
        occupation: '',
        income: '',
        diet: '',
        photoRequired: false,
        verifiedOnly: false,
        excludeContacted: false,
    };

    function updateFilterUI() {
        var s = filterState;
        var totalActive = 0;

        var ageEl = document.getElementById('age-value');
        if (s.age.min || s.age.max) {
            if (ageEl) ageEl.textContent = (s.age.min || 'Any') + ' - ' + (s.age.max || 'Any');
            totalActive++;
        } else {
            if (ageEl) ageEl.textContent = 'Any';
        }
        updateTriggerActive('panel-age', s.age.min || s.age.max);

        var searchEl = document.getElementById('search-value');
        if (s.search) {
            if (searchEl) searchEl.textContent = s.search;
            totalActive++;
        } else {
            if (searchEl) searchEl.textContent = '';
        }
        updateTriggerActive('panel-search', !!s.search);

        var locEl = document.getElementById('location-value');
        if (s.location.city || s.location.state) {
            if (locEl) locEl.textContent = s.location.city || s.location.state || 'Any';
            totalActive++;
        } else {
            if (locEl) locEl.textContent = 'Any';
        }
        updateTriggerActive('panel-location', s.location.city || s.location.state);

        var relEl = document.getElementById('religion-value');
        if (s.religion.length > 0) {
            if (relEl) relEl.textContent = s.religion.length === 1 ? s.religion[0] : s.religion.length + ' selected';
            totalActive++;
        } else {
            if (relEl) relEl.textContent = 'Any';
        }
        updateTriggerActive('panel-religion', s.religion.length > 0);

        var moreBadge = document.getElementById('moreFiltersBadge');
        var moreCount = 0;
        if (s.marital.length > 0 && !(s.marital.length === 1 && s.marital[0] === 'never_married')) moreCount++;
        if (s.caste) moreCount++;
        if (s.tongue) moreCount++;
        if (s.education) moreCount++;
        if (s.occupation) moreCount++;
        if (s.income) moreCount++;
        if (s.diet) moreCount++;
        if (s.photoRequired) moreCount++;
        if (s.verifiedOnly) moreCount++;
        if (s.excludeContacted) moreCount++;

        if (moreCount > 0 && moreBadge) {
            moreBadge.textContent = moreCount;
            moreBadge.style.display = 'inline-flex';
            totalActive += moreCount;
        } else if (moreBadge) {
            moreBadge.style.display = 'none';
        }

        var mobileCount = document.getElementById('mobileFilterCount');
        if (mobileCount) mobileCount.textContent = totalActive;

        renderFilterPills();
    }

    function updateTriggerActive(panelId, hasValue) {
        var btn = document.querySelector('[data-panel="' + panelId + '"]');
        if (btn) {
            if (hasValue) btn.classList.add('has-value');
            else btn.classList.remove('has-value');
        }
    }

    function renderFilterPills() {
        var container = document.getElementById('activeFilters');
        if (!container) return;
        var s = filterState;
        var pills = [];

        var inp = document.querySelector('[name="search"]');
        if (inp && inp.value) pills.push({ label: 'Search: ' + inp.value, key: 'search' });
        if (s.age.min || s.age.max) pills.push({ label: 'Age: ' + (s.age.min || 'Any') + '-' + (s.age.max || 'Any'), key: 'age' });
        if (s.location.city || s.location.state) pills.push({ label: 'Location: ' + (s.location.city || s.location.state), key: 'location' });
        if (s.religion.length > 0) pills.push({ label: 'Religion: ' + s.religion.join(', '), key: 'religion' });
        if (s.marital.length > 0 && !(s.marital.length === 1 && s.marital[0] === 'never_married')) pills.push({ label: 'Status: ' + s.marital.join(', ').replace(/_/g, ' '), key: 'marital' });
        if (s.caste) pills.push({ label: 'Caste: ' + s.caste, key: 'caste' });
        if (s.tongue) pills.push({ label: 'Tongue: ' + s.tongue, key: 'tongue' });
        if (s.education) pills.push({ label: 'Education: ' + s.education, key: 'education' });
        if (s.occupation) pills.push({ label: 'Occupation: ' + s.occupation, key: 'occupation' });
        if (s.income) pills.push({ label: 'Income: ' + s.income, key: 'income' });
        if (s.diet) pills.push({ label: 'Diet: ' + s.diet, key: 'diet' });
        if (s.photoRequired) pills.push({ label: 'Photo required', key: 'photoReq' });
        if (s.verifiedOnly) pills.push({ label: 'Verified only', key: 'verified' });
        if (s.excludeContacted) pills.push({ label: 'Exclude contacted', key: 'exclude' });

        if (pills.length === 0) { container.innerHTML = ''; return; }
        container.innerHTML = pills.map(function(p) {
            return '<span class="active-filter-pill">' +
                escapeHtml(p.label) +
                '<span class="remove" onclick="removeFilterPill(\'' + p.key + '\')">&times;</span></span>';
        }).join('');
    }

    window.openMobileDrawer = function() {
        var overlay = document.getElementById('filterOverlay');
        var drawer = document.getElementById('mobileDrawer');
        var content = document.getElementById('mobileDrawerContent');
        if (!overlay || !drawer || !content) return;

        var bar = document.getElementById('filter-form');
        if (bar) {
            var clone = bar.cloneNode(true);
            clone.classList.remove('filter-bar');
            clone.querySelectorAll('.filter-trigger').forEach(function(t) {
                var p = t.querySelector('.filter-panel');
                if (p) {
                    p.classList.remove('open');
                }
                var btn = t.querySelector('[data-panel]');
                if (btn) {
                    btn.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        var pid = btn.getAttribute('data-panel');
                        var panel = clone.querySelector('#' + pid);
                        if (panel) {
                            var isOpen = panel.classList.contains('open');
                            clone.querySelectorAll('.filter-panel').forEach(function(p) {
                                p.classList.remove('open');
                            });
                            if (!isOpen) {
                                panel.classList.add('open');
                                btn.setAttribute('aria-expanded', 'true');
                            } else {
                                btn.setAttribute('aria-expanded', 'false');
                            }
                        }
                    };
                }
            });
            var af = clone.querySelector('.active-filters');
            if (af) af.remove();
            var right = clone.querySelector('.filter-bar-right');
            if (right) right.remove();
            content.innerHTML = '';
            content.appendChild(clone);

            content.querySelectorAll('select, input').forEach(function(el) {
                el.onchange = function() { syncFilterFromDOM(); };
                el.oninput = function() { syncFilterFromDOM(); };
            });

            // Mobile: open first accordion panel by default
            if (window.innerWidth <= 576) {
                var firstPanel = content.querySelector('.filter-panel');
                if (firstPanel) {
                    firstPanel.classList.add('open');
                    var firstBtn = content.querySelector('[data-panel]');
                    if (firstBtn) firstBtn.setAttribute('aria-expanded', 'true');
                }
            }
        }

        overlay.classList.add('active');
        drawer.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    window.closeMobileDrawer = function() {
        var overlay = document.getElementById('filterOverlay');
        var drawer = document.getElementById('mobileDrawer');
        if (overlay) overlay.classList.remove('active');
        if (drawer) drawer.classList.remove('open');
        document.body.style.overflow = '';
    };

    function syncFilterFromDOM() {
        filterState.search = document.querySelector('[name="search"]')?.value || '';
        var a1 = document.getElementById('age-min');
        var a2 = document.getElementById('age-max');
        if (a1) filterState.age.min = a1.value || '';
        if (a2) filterState.age.max = a2.value || '';
        var l1 = document.getElementById('location-state');
        var l2 = document.getElementById('location-city');
        if (l1) filterState.location.state = l1.value || '';
        if (l2) filterState.location.city = l2.value || '';
        filterState.religion = [];
        document.querySelectorAll('#religion-list input[type="checkbox"]:checked').forEach(function(c) { filterState.religion.push(c.value); });
        filterState.marital = [];
        document.querySelectorAll('#marital-list input[type="checkbox"]:checked').forEach(function(c) { filterState.marital.push(c.value); });
        var cs = document.getElementById('caste-select'); if (cs) filterState.caste = cs.value || '';
        var ts = document.getElementById('tongue-select'); if (ts) filterState.tongue = ts.value || '';
        var es = document.getElementById('education-select'); if (es) filterState.education = es.value || '';
        var os = document.getElementById('occupation-select'); if (os) filterState.occupation = os.value || '';
        var is = document.getElementById('income-select'); if (is) filterState.income = is.value || '';
        var ds = document.getElementById('diet-select'); if (ds) filterState.diet = ds.value || '';
        var pr = document.getElementById('photo-req'); if (pr) filterState.photoRequired = pr.checked;
        var vo = document.getElementById('verified-only'); if (vo) filterState.verifiedOnly = vo.checked;
        var ec = document.getElementById('exclude-contacted'); if (ec) filterState.excludeContacted = ec.checked;
    }

    function initStickyDetection() {
        var bar = document.getElementById('filter-form');
        if (!bar) return;
        var sentinel = document.createElement('div');
        sentinel.style.position = 'absolute';
        sentinel.style.top = '-1px';
        sentinel.style.height = '1px';
        sentinel.style.width = '1px';
        bar.parentNode.insertBefore(sentinel, bar);
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (!entry.isIntersecting) bar.classList.add('is-sticky');
                else bar.classList.remove('is-sticky');
            });
        }, { threshold: [0, 1] });
        observer.observe(sentinel);
    }

    function initMatchesPage() {
        var grid = document.getElementById('matches-grid');
        if (!grid) return;

        var container = document.querySelector('.matches-page');
        if (!container) return;

        loadMatches(1, true);

        var filterForm = document.getElementById('filter-form');
        if (filterForm) {
            filterForm.addEventListener('submit', function (e) {
                e.preventDefault();
                closeAllPanels();
                matchesState.page = 1;
                loadMatches(1, true);
            });

            var debounceTimer;
            filterForm.addEventListener('change', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(fetchCount, 300);
            });
            filterForm.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(fetchCount, 300);
            });
        }

        var sortSelect = document.getElementById('sort-select');
        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                matchesState.sort = this.value;
                matchesState.page = 1;
                loadMatches(1, true);
            });
        }

        var perPageSelect = document.getElementById('per-page-select');
        if (perPageSelect) {
            perPageSelect.addEventListener('change', function () {
                matchesState.perPage = parseInt(this.value);
                matchesState.page = 1;
                loadMatches(1, true);
            });
        }

        document.querySelectorAll('.quick-chip').forEach(function(chip) {
            chip.addEventListener('click', function() {
                var preset = this.getAttribute('data-preset');

                if (preset === 'all') {
                    document.querySelectorAll('.quick-chip').forEach(function(c) {
                        c.classList.remove('active');
                        c.setAttribute('aria-pressed', 'false');
                    });
                    this.classList.add('active');
                    this.setAttribute('aria-pressed', 'true');
                    clearAllFilters();
                    return;
                }

                var allChip = document.querySelector('.quick-chip[data-preset="all"]');
                if (allChip) {
                    allChip.classList.remove('active');
                    allChip.setAttribute('aria-pressed', 'false');
                }

                var isActive = this.classList.contains('active');
                this.classList.toggle('active');
                this.setAttribute('aria-pressed', isActive ? 'false' : 'true');

                var anyActive = document.querySelector('.quick-chip.active:not([data-preset="all"])');
                if (!anyActive && allChip) {
                    allChip.classList.add('active');
                    allChip.setAttribute('aria-pressed', 'true');
                }

                applyQuickPreset(preset, !isActive);
            });
        });

        document.addEventListener('click', function(e) {
            var bar = document.getElementById('filter-form');
            if (bar && openPanelId && !bar.contains(e.target)) {
                closeAllPanels();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllPanels();
                closeMobileDrawer();
            }
        });

        initStickyDetection();
        updateFilterUI();
    }

    function getFilters() {
        var filterForm = document.getElementById('filter-form');
        if (!filterForm) return {};

        var data = {};
        var formData = new FormData(filterForm);

        for (var pair of formData.entries()) {
            var key = pair[0];
            var val = pair[1];

            if (key.endsWith('[]')) {
                var baseKey = key.slice(0, -2);
                if (!data[baseKey]) data[baseKey] = [];
                if (val !== '') data[baseKey].push(val);
            } else {
                if (key === 'photo_required' || key === 'verified_only' || key === 'exclude_contacted') {
                    if (val === '1') data[key] = true;
                } else {
                    if (val !== '') data[key] = val;
                }
            }
        }

        return data;
    }

    function loadMatches(page, replace) {
        if (matchesState.loading) return;
        matchesState.loading = true;

        var grid = document.getElementById('matches-grid');
        if (!grid) return;

        if (replace) {
            grid.querySelectorAll('[data-testid="match-card"], .matches-empty-state').forEach(function(el) { el.remove(); });
        }

        var filters = getFilters();
        var sortSelect = document.querySelector('[name="sort"]');
        var sort = sortSelect ? sortSelect.value : 'compatibility';

        var params = new URLSearchParams();
        params.set('page', page);
        params.set('per_page', matchesState.perPage);
        params.set('sort', sort);
        params.set('filters', JSON.stringify(filters));

        fetch('/api/matches/list?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'include'
        })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status + ': ' + res.statusText);
            }
            return res.json();
        })
        .then(function (result) {
            matchesState.loading = false;
            if (!result.success) {
                grid.innerHTML = '<div class="col-12 text-center py-5"><p class="text-muted fs-5">Something went wrong. Please try again.</p><button class="btn btn-primary btn-sm mt-2" onclick="window.loadMatches(1, true)">Retry</button></div>';
                return;
            }

            matchesState.total = result.meta.total;
            matchesState.hasMore = page * matchesState.perPage < result.meta.total;

            var countEl = document.getElementById('result-count');
            if (countEl) {
                countEl.textContent = result.meta.total + ' matches found';
            }

            if (replace) {
                grid.innerHTML = '';
            }

            if (result.data.length === 0) {
                grid.innerHTML = '<div class="col-12 matches-empty-state" data-testid="empty-state">' +
                    '<svg width="48" height="48" viewBox="0 0 24 24" fill="var(--brand-gray-300)" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>' +
                    '<h3>No matches found</h3>' +
                    '<p>Try adjusting your filters or removing some criteria to see more results.</p>' +
                    '<button class="btn btn-primary" data-testid="reset-filters" onclick="clearAllFilters()">Clear All Filters</button>' +
                    '</div>';
                return;
            }

            result.data.forEach(function (profile) {
                grid.appendChild(createMatchCard(profile));
            });

            updatePagination(result.meta);
        })
        .catch(function () {
            matchesState.loading = false;
            grid.innerHTML = '<div class="col-12 text-center py-5"><p class="text-muted fs-5">Network error. Please check your connection and try again.</p><button class="btn btn-primary btn-sm mt-2" onclick="window.loadMatches(1, true)">Retry</button></div>';
        });
    }

    window.loadMatches = loadMatches;

    function placeholderSVG(initial) {
        var colors = ['#2d1656','#3d206b','#5d4777','#7a6494','#9565be','#b894d8'];
        var c = colors[initial.charCodeAt(0) % colors.length];
        return 'data:image/svg+xml,' + encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="400" viewBox="0 0 300 400">' +
            '<rect fill="' + c + '" width="300" height="400"/>' +
            '<text x="150" y="200" text-anchor="middle" dominant-baseline="central" fill="rgba(255,255,255,0.3)" font-family="sans-serif" font-size="80" font-weight="700">' + initial + '</text>' +
            '</svg>'
        );
    }

    function createMatchCard(profile) {
        var col = document.createElement('div');
        col.className = 'col-md-6 col-lg-3';

        var initials = (profile.first_name || '?').charAt(0).toUpperCase();
        var ageStr = profile.age ? profile.age + ' yrs' : '';
        var heightStr = '';
        if (profile.height_cm) {
            var totalInches = Math.round(profile.height_cm / 2.54);
            var feet = Math.floor(totalInches / 12);
            var inches = totalInches % 12;
            heightStr = feet + "'" + inches + '"';
        }
        var verifiedClass = profile.verified ? ' verified' : '';
        var photo = profile.primary_photo || placeholderSVG(initials);

        var premiumBadge = profile.premium || profile.membership === 'premium' ? '<div class="premium-badge" data-testid="premium-badge" aria-label="Premium profile"><svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></div>' : '';

        col.innerHTML =
            '<div class="match-card' + verifiedClass + '" data-testid="match-card">' +
                '<button class="bookmark-btn" data-action="shortlist" data-id="' + profile.user_id + '" aria-label="Shortlist ' + escapeHtml(profile.first_name || 'this profile') + '" aria-pressed="false">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
                        '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>' +
                    '</svg>' +
                '</button>' +
                premiumBadge +
                '<div class="match-percentage" aria-label="' + (profile.compatibility || '0') + ' percent match">' +
                    '<span class="match-percentage-value">' + (profile.compatibility || '?') + '%</span>' +
                    '<span class="match-percentage-label">Match</span>' +
                '</div>' +
                '<div class="match-photo">' +
                    '<img src="' + photo + '" alt="Photo of ' + escapeHtml(profile.first_name || '') + '" loading="lazy" data-testid="match-photo" onerror="this.src=\'' + placeholderSVG(initials) + '\'">' +
                    '<div class="match-overlay">' +
                        '<div class="match-name">' + escapeHtml(profile.first_name || '') + '</div>' +
                        '<div class="match-badges">' +
                            (ageStr ? '<span class="match-badge">' + ageStr + '</span>' : '') +
                            (heightStr ? '<span class="match-badge">' + heightStr + '</span>' : '') +
                            (profile.religion ? '<span class="match-badge">' + escapeHtml(profile.religion) + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                    (profile.verified ? '<div class="verified-badge" aria-label="Verified profile"><svg width="12" height="12" viewBox="0 0 24 24" fill="white" aria-hidden="true"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></div>' : '') +
                '</div>' +
                '<div class="match-details">' +
                    '<div class="match-info">' +
                        '<div class="match-info-item"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>' + escapeHtml(profile.education || '') + '</div>' +
                        '<div class="match-info-item"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>' + escapeHtml(profile.occupation || '') + '</div>' +
                        '<div class="match-info-item"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>' + escapeHtml(profile.city || '') + '</div>' +
                    '</div>' +
                    '<div class="match-actions" data-testid="quick-actions">' +
                        '<button class="btn btn-primary btn-sm btn-interest" data-id="' + profile.user_id + '">Send Interest</button>' +
                        '<button class="btn btn-outline-primary btn-sm btn-view-profile" data-id="' + profile.user_id + '">View Profile</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        col.querySelector('.btn-interest').addEventListener('click', function () {
            performAction(profile.user_id, 'interested');
        });

        col.querySelector('.btn-view-profile').addEventListener('click', function () {
            viewProfile(profile.user_id);
        });

        col.querySelector('.bookmark-btn').addEventListener('click', function () {
            performAction(profile.user_id, 'shortlisted');
            this.classList.toggle('active');
            var isActive = this.classList.contains('active');
            this.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        return col;
    }

    function performAction(targetId, status) {
        var container = document.querySelector('.matches-page');
        var csrf = container ? container.dataset.csrf : '';

        fetch('/api/matches/action', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ target_id: targetId, status: status, csrf: csrf })
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (result) {
            if (result.success) {
                if (result.mutual) {
                    showToast('It\'s a match! Mutual interest confirmed!', 'success');
                } else if (status === 'interested') {
                    showToast('Interest sent successfully!', 'success');
                } else if (status === 'shortlisted') {
                    showToast('Profile shortlisted!', 'info');
                }
            } else {
                showToast(result.error || 'Action failed', 'danger');
            }
        })
        .catch(function () {
            showToast('Network error', 'danger');
        });
    }

    function viewProfile(userId) {
        var modal = new bootstrap.Modal(document.getElementById('profileModal'));
        modal.show();

        var body = document.getElementById('profile-modal-body');
        body.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';

        fetch('/api/matches/profile/' + userId, {
            headers: { 'Accept': 'application/json' },
            credentials: 'include'
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (result) {
            if (!result.success || !result.data) {
                body.innerHTML = '<div class="text-center py-5 text-danger">Profile not found</div>';
                return;
            }
            renderProfileModal(body, result.data);
        })
        .catch(function () {
            body.innerHTML = '<div class="text-center py-5 text-danger">Network error</div>';
        });
    }

    function renderProfileModal(body, profile) {
        var initials = (profile.first_name || '?').charAt(0).toUpperCase();
        var photo = profile.primary_photo || placeholderSVG(initials);
        var age = profile.age || '';
        var fullName = escapeHtml(profile.first_name || '') + ' ' + escapeHtml(profile.last_name || '');

        body.innerHTML =
            '<div class="row">' +
                '<div class="col-md-5">' +
                    '<img src="' + photo + '" class="img-fluid" alt="Photo of ' + fullName + '" onerror="this.src=\'' + placeholderSVG(initials) + '\'">' +
                '</div>' +
                '<div class="col-md-7">' +
                    '<h3>' + fullName + '</h3>' +
                    '<div class="mb-2">' +
                        '<span class="badge bg-primary me-1">' + age + ' yrs</span>' +
                        '<span class="badge bg-secondary me-1">' + escapeHtml(profile.religion || '') + '</span>' +
                        '<span class="badge bg-info me-1">' + escapeHtml(profile.mother_tongue || '') + '</span>' +
                        (profile.verified ? '<span class="badge bg-success">Verified</span>' : '') +
                    '</div>' +
                    '<table class="table table-sm table-borderless mt-3">' +
                        (profile.education ? '<tr><td class="text-muted pe-3">Education</td><td>' + escapeHtml(profile.education) + '</td></tr>' : '') +
                        (profile.occupation ? '<tr><td class="text-muted pe-3">Occupation</td><td>' + escapeHtml(profile.occupation) + '</td></tr>' : '') +
                        (profile.annual_income ? '<tr><td class="text-muted pe-3">Income</td><td>' + escapeHtml(profile.annual_income) + '</td></tr>' : '') +
                        (profile.city ? '<tr><td class="text-muted pe-3">Location</td><td>' + escapeHtml(profile.city) + (profile.state ? ', ' + escapeHtml(profile.state) : '') + '</td></tr>' : '') +
                        (profile.marital_status ? '<tr><td class="text-muted pe-3">Status</td><td>' + escapeHtml(profile.marital_status.replace(/_/g, ' ')) + '</td></tr>' : '') +
                        (profile.caste ? '<tr><td class="text-muted pe-3">Caste</td><td>' + escapeHtml(profile.caste) + '</td></tr>' : '') +
                        (profile.diet ? '<tr><td class="text-muted pe-3">Diet</td><td>' + escapeHtml(profile.diet) + '</td></tr>' : '') +
                    '</table>' +
                    '<div class="d-flex gap-2 mt-3">' +
                        '<button class="btn btn-primary btn-interest" data-id="' + profile.user_id + '">Send Interest</button>' +
                        '<button class="btn btn-outline-danger btn-decline" data-id="' + profile.user_id + '">Decline</button>' +
                    '</div>' +
                '</div>' +
            '</div>';

        body.querySelector('.btn-interest').addEventListener('click', function () {
            performAction(profile.user_id, 'interested');
        });
        body.querySelector('.btn-decline').addEventListener('click', function () {
            performAction(profile.user_id, 'declined');
            bootstrap.Modal.getInstance(document.getElementById('profileModal')).hide();
        });
    }

    function fetchCount() {
        var filters = getFilters();
        var params = new URLSearchParams();
        for (var key in filters) {
            if (Array.isArray(filters[key])) {
                filters[key].forEach(function (v) { params.append(key + '[]', v); });
            } else {
                params.set(key, filters[key]);
            }
        }

        fetch('/api/matches/count?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'include'
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (result) {
            if (result.success) {
                var countEl = document.getElementById('result-count');
                if (countEl) {
                    countEl.textContent = result.data.count + ' matches found';
                }
            }
        })
        .catch(function () {});
    }

    function updatePagination(meta) {
        var nav = document.getElementById('pagination-nav');
        var list = nav ? nav.querySelector('.pagination') : null;
        if (!nav || !list) return;

        var totalPages = Math.ceil(meta.total / meta.per_page);
        if (totalPages <= 1) {
            nav.classList.add('d-none');
            return;
        }
        nav.classList.remove('d-none');

        var current = meta.page;
        list.innerHTML = '';

        var prevLi = document.createElement('li');
        prevLi.className = 'page-item' + (current <= 1 ? ' disabled' : '');
        prevLi.innerHTML = '<a class="page-link" href="#">Previous</a>';
        prevLi.addEventListener('click', function (e) {
            e.preventDefault();
            if (current > 1) {
                matchesState.page = current - 1;
                loadMatches(current - 1, true);
            }
        });
        list.appendChild(prevLi);

        var start = Math.max(1, current - 2);
        var end = Math.min(totalPages, current + 2);
        for (var i = start; i <= end; i++) {
            var li = document.createElement('li');
            li.className = 'page-item' + (i === current ? ' active' : '');
            li.innerHTML = '<a class="page-link" href="#">' + i + '</a>';
            li.addEventListener('click', (function (p) {
                return function (e) {
                    e.preventDefault();
                    matchesState.page = p;
                    loadMatches(p, true);
                };
            })(i));
            list.appendChild(li);
        }

        var nextLi = document.createElement('li');
        nextLi.className = 'page-item' + (current >= totalPages ? ' disabled' : '');
        nextLi.innerHTML = '<a class="page-link" href="#">Next</a>';
        nextLi.addEventListener('click', function (e) {
            e.preventDefault();
            if (current < totalPages) {
                matchesState.page = current + 1;
                loadMatches(current + 1, true);
            }
        });
        list.appendChild(nextLi);
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function applyQuickPreset(preset, activate) {
        switch (preset) {
            case 'new':
                document.getElementById('sort-select').value = activate ? 'recently_joined' : 'compatibility';
                var hfNew = document.getElementById('hf-new-profiles');
                if (hfNew) hfNew.value = activate ? '1' : '';
                break;
            case 'premium':
                var hfPremium = document.getElementById('hf-premium-only');
                if (hfPremium) hfPremium.value = activate ? '1' : '';
                filterState.photoRequired = activate;
                break;
            case 'verified':
                var verifiedOnly = document.getElementById('verified-only');
                if (verifiedOnly) {
                    verifiedOnly.checked = activate;
                    filterState.verifiedOnly = activate;
                }
                break;
            case 'online':
                document.getElementById('sort-select').value = activate ? 'last_active' : 'compatibility';
                var hfActive = document.getElementById('hf-recently-active');
                if (hfActive) hfActive.value = activate ? '1' : '';
                break;
        }
        updateFilterUI();
        matchesState.page = 1;
        loadMatches(1, true);
    }

    var profileSubmitting = false;

    function initProfilePage() {
        var page = document.querySelector('.profile-page');
        if (!page) return;

        var csrf = page.dataset.csrf || '';
        var userId = page.dataset.userId || '';

        page.querySelectorAll('.profile-section-form').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                if (profileSubmitting) return;

                var section = form.dataset.section;
                var data = {};
                new FormData(form).forEach(function (val, key) {
                    if (key !== 'section') data[key] = val;
                });
                // Exclude disabled fields so they aren't sent
                form.querySelectorAll('[disabled]').forEach(function (el) {
                    delete data[el.name];
                });
                data.section = section;
                data.csrf = csrf;

                var required = form.querySelectorAll('[required]');
                for (var i = 0; i < required.length; i++) {
                    if (!required[i].value.trim()) {
                        showToast('Please fill in all required fields.', 'danger');
                        required[i].focus();
                        return;
                    }
                }

                var btn = form.querySelector('button[type="submit"]');
                var orig = btn.innerHTML;
                profileSubmitting = true;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

                fetch('/api/profile/' + section, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(data)
                })
                .then(function (res) { return res.json(); })
                .then(function (result) {
                    profileSubmitting = false;
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    if (result.success) {
                        showToast('Saved successfully!', 'success');
                        refreshProfileCompletion();
                    } else {
                        showToast(result.error || 'Save failed. Please try again.', 'danger');
                    }
                })
                .catch(function () {
                    profileSubmitting = false;
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    showToast('Network error', 'danger');
                });
            });
        });

        var photoInput = document.getElementById('photo-upload-input');
        var galleryInput = document.getElementById('gallery-upload-input');
        if (photoInput) photoInput.addEventListener('change', function (e) { uploadPhoto(e.target, csrf); });
        if (galleryInput) galleryInput.addEventListener('change', function (e) { uploadPhoto(e.target, csrf); });

        // Toggle Has Children field based on Marital Status
        var maritalStatus = document.querySelector('select[name="marital_status"]');
        var hasChildrenField = document.getElementById('has-children-field');
        function toggleHasChildren() {
            if (!hasChildrenField) return;
            var hidden = maritalStatus && maritalStatus.value === 'never_married';
            hasChildrenField.style.display = hidden ? 'none' : '';
            var sel = hasChildrenField.querySelector('select');
            if (sel) sel.disabled = hidden;
        }
        if (maritalStatus) {
            maritalStatus.addEventListener('change', toggleHasChildren);
            toggleHasChildren();
        }

        page.querySelectorAll('.set-primary-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.dataset.id;
                var origHtml = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                fetch('/api/profile/photo/' + id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ csrf: csrf, _method: 'PUT' })
                })
                .then(function (res) { return res.json(); })
                .then(function (result) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    if (result.success) {
                        showToast('Primary photo updated!', 'success');
                        var page = document.querySelector('.profile-page');
                        if (page) {
                            page.querySelectorAll('.photo-card').forEach(function (card) {
                                card.classList.remove('border-primary', 'border-2');
                                card.querySelector('.primary-badge')?.remove();
                            });
                            var card = page.querySelector('.photo-card[data-id="' + id + '"]');
                            if (card) {
                                card.classList.add('border-primary', 'border-2');
                                var badge = document.createElement('span');
                                badge.className = 'primary-badge badge bg-primary position-absolute top-0 end-0 m-1';
                                badge.textContent = 'Primary';
                                card.appendChild(badge);
                            }
                        }
                    } else {
                        showToast(result.error || 'Failed to set primary photo.', 'danger');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    showToast('Network error. Please try again.', 'danger');
                });
            });
        });

        page.querySelectorAll('.delete-photo-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var self = this;
                showConfirm({
                    title: 'Delete Photo',
                    message: 'Are you sure you want to delete this photo?',
                    confirmText: 'Delete',
                    confirmClass: 'btn-danger'
                }, function(confirmed) {
                    if (!confirmed) return;
                    var id = self.dataset.id;
                    var origHtml = self.innerHTML;
                    self.disabled = true;
                    self.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                    fetch('/api/profile/photo/' + id, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include',
                        body: JSON.stringify({ csrf: csrf })
                    })
                    .then(function (res) { return res.json(); })
                    .then(function (result) {
                        btn.disabled = false;
                        btn.innerHTML = origHtml;
                        if (result.success) {
                            showToast('Photo deleted successfully.', 'info');
                            var parentCol = btn.closest('.col-md-3, .col-6');
                            if (parentCol) parentCol.remove();
                            var gallery = document.getElementById('photo-gallery');
                            if (gallery && !gallery.querySelector('.photo-card')) {
                                gallery.innerHTML = '<div class="col-12 text-center py-5 text-muted"><p>No photos yet.</p><p class="small">Upload your first photo above.</p></div>';
                            }
                        } else {
                            showToast(result.error || 'Failed to delete photo.', 'danger');
                        }
                    })
                    .catch(function () {
                        btn.disabled = false;
                        btn.innerHTML = origHtml;
                        showToast('Network error. Please try again.', 'danger');
                    });
                });
            });
        });

        page.querySelectorAll('.privacy-toggle').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var key = this.dataset.key;
                var val = this.checked ? 1 : 0;
                var data = {};
                data[key] = val;
                data.csrf = csrf;

                var toggle = this;
                toggle.disabled = true;
                fetch('/api/profile/privacy', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(data)
                })
                .then(function (res) { return res.json(); })
                .then(function (result) {
                    toggle.disabled = false;
                    if (result.success) {
                        showToast('Privacy setting updated.', 'success');
                    } else {
                        toggle.checked = !toggle.checked;
                        showToast(result.error || 'Failed to update privacy.', 'danger');
                    }
                })
                .catch(function () {
                    toggle.disabled = false;
                    toggle.checked = !toggle.checked;
                    showToast('Network error. Please try again.', 'danger');
                });
            });
        });

        loadActivity(userId);
    }

    function uploadPhoto(input, csrf) {
        if (!input.files || !input.files[0]) return;
        var formData = new FormData();
        formData.append('photo', input.files[0]);
        formData.append('csrf', csrf);

        fetch('/api/profile/photo', {
            method: 'POST',
            credentials: 'include',
            body: formData
        })
        .then(function (res) {
            return res.text().then(function (text) {
                try { return JSON.parse(text); }
                catch (e) { throw new Error('Server returned non-JSON: ' + text.substring(0, 200)); }
            });
        })
        .then(function (result) {
            if (result.success) {
                showToast('Photo uploaded!', 'success');
                if (result.data) addPhotoCard(result.data);
            } else {
                showToast(result.error || 'Upload failed', 'danger');
            }
        })
        .catch(function () {
            showToast('Upload failed: Network error', 'danger');
        });
        input.value = '';
    }
    window.uploadPhoto = uploadPhoto;

    function loadActivity(userId) {
        var timeline = document.getElementById('activity-timeline');
        if (!timeline) return;

        fetch('/api/profile/activity', {
            headers: { 'Accept': 'application/json' },
            credentials: 'include'
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (result) {
            if (!result.success || !result.data || result.data.length === 0) {
                timeline.innerHTML = '<div class="text-center py-4 text-muted small">No recent activity</div>';
                return;
            }
            var html = '<ul class="list-group list-group-flush">';
            result.data.forEach(function (item) {
                var label = item.action.replace(/\./g, ' ').replace(/_/g, ' ');
                var time = item.created_at ? new Date(item.created_at).toLocaleDateString() : '';
                html += '<li class="list-group-item d-flex justify-content-between align-items-center">' +
                    '<span>' + escapeHtml(label) + '</span>' +
                    '<small class="text-muted">' + time + '</small>' +
                '</li>';
            });
            html += '</ul>';
            timeline.innerHTML = html;
        })
        .catch(function () {
            timeline.innerHTML = '<div class="text-center py-4 text-muted small">Could not load activity</div>';
        });
    }

    window.showCompletionChecklist = function () {
        var modal = new bootstrap.Modal(document.getElementById('completionModal'));
        modal.show();
    };

    window.viewAsPublic = function () {
        var page = document.querySelector('.profile-page');
        var userId = page ? page.dataset.userId : '';
        if (!userId) return;
        fetch('/api/profile/' + userId, {
            headers: { 'Accept': 'application/json' },
            credentials: 'include'
        })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            if (result.success && result.data) {
                var p = result.data;
                var initials = (p.first_name || '?').charAt(0).toUpperCase();
                var photo = p.primary_photo || placeholderSVG(initials);
                var fullName = escapeHtml(p.first_name || '') + ' ' + escapeHtml(p.last_name || '');

                var sections = '';

                function infoItem(label, val) {
                    if (!val) return '';
                    return '<div class="col-6 mb-2"><span class="text-secondary" style="font-size:11px;display:block">' + label + '</span><span style="font-size:13px;color:#222">' + escapeHtml(val) + '</span></div>';
                }

                var basic = '';
                basic += infoItem('Age', (p.age || '') + ' yrs');
                basic += infoItem('Gender', p.gender ? p.gender.charAt(0).toUpperCase() + p.gender.slice(1) : '');
                basic += infoItem('Religion', p.religion);
                basic += infoItem('Caste', p.caste);
                basic += infoItem('Sub Caste', p.sub_caste);
                basic += infoItem('Mother Tongue', p.mother_tongue);
                basic += infoItem('Marital Status', p.marital_status ? p.marital_status.replace(/_/g, ' ') : '');
                basic += infoItem('Has Children', p.has_children ? p.has_children.charAt(0).toUpperCase() + p.has_children.slice(1) : '');
                basic += infoItem('Profile By', p.created_by ? p.created_by.charAt(0).toUpperCase() + p.created_by.slice(1) : '');
                basic += infoItem('Height', p.height_cm ? (Math.floor(p.height_cm / 30.48) + "'" + Math.round(p.height_cm % 30.48 / 2.54) + '"') : '');
                basic += infoItem('Weight', p.weight_kg ? p.weight_kg + ' kg' : '');
                basic += infoItem('Education', p.education);
                basic += infoItem('Institution', p.institution);
                basic += infoItem('Occupation', p.occupation);
                basic += infoItem('Company', p.company);
                basic += infoItem('Income', p.annual_income);
                basic += infoItem('Location', p.city + (p.state ? ', ' + p.state : '') + (p.country ? ', ' + p.country : ''));
                basic += infoItem('Work Location', p.work_location);

                if (basic) {
                    sections += '<div class="p-3 mb-2 rounded" style="background:#f8f6ff"><h6 style="font-size:13px;font-weight:600;color:#2d1656;margin:0 0 8px 0">Basic Details</h6><div class="row gx-3">' + basic + '</div></div>';
                }

                if (p.diet || p.body_type || p.complexion || p.smoking_habits || p.drinking_habits || p.languages_known || p.hobbies || p.interests) {
                    var life = '';
                    life += infoItem('Diet', p.diet);
                    life += infoItem('Body Type', p.body_type);
                    life += infoItem('Complexion', p.complexion);
                    life += infoItem('Smoking', p.smoking_habits);
                    life += infoItem('Drinking', p.drinking_habits);
                    life += infoItem('Languages', p.languages_known);
                    life += infoItem('Hobbies', p.hobbies);
                    life += infoItem('Interests', p.interests);
                    sections += '<div class="p-3 mb-2 rounded" style="background:#f0faf0"><h6 style="font-size:13px;font-weight:600;color:#1a6b1a;margin:0 0 8px 0">Lifestyle & Interests</h6><div class="row gx-3">' + life + '</div></div>';
                }

                if (p.father_name || p.mother_name || p.family_type || p.family_values || p.about_family) {
                    var fam = '';
                    fam += infoItem('Father', p.father_name ? p.father_name + (p.father_occupation ? ' (' + p.father_occupation + ')' : '') : '');
                    fam += infoItem('Mother', p.mother_name ? p.mother_name + (p.mother_occupation ? ' (' + p.mother_occupation + ')' : '') : '');
                    var siblings = (p.brothers_count ? p.brothers_count + ' brother' + (p.brothers_count > 1 ? 's' : '') : '') + (p.brothers_count && p.sisters_count ? ', ' : '') + (p.sisters_count ? p.sisters_count + ' sister' + (p.sisters_count > 1 ? 's' : '') : '');
                    fam += infoItem('Siblings', siblings || '');
                    fam += infoItem('Type', p.family_type ? p.family_type.charAt(0).toUpperCase() + p.family_type.slice(1) : '');
                    fam += infoItem('Values', p.family_values ? p.family_values.charAt(0).toUpperCase() + p.family_values.slice(1) : '');
                    fam += infoItem('Income', p.family_income);
                    fam += infoItem('Origin', p.family_origin);
                    if (p.about_family) {
                        sections += '<div class="p-3 mb-2 rounded" style="background:#fff8f0"><h6 style="font-size:13px;font-weight:600;color:#8a5d00;margin:0 0 8px 0">Family</h6><div class="row gx-3">' + fam + '</div><div class="mt-2 pt-2 border-top" style="font-size:13px;color:#555">' + escapeHtml(p.about_family) + '</div></div>';
                    } else {
                        sections += '<div class="p-3 mb-2 rounded" style="background:#fff8f0"><h6 style="font-size:13px;font-weight:600;color:#8a5d00;margin:0 0 8px 0">Family</h6><div class="row gx-3">' + fam + '</div></div>';
                    }
                }

                if (p.rashi || p.nakshatra) {
                    var hor = '';
                    hor += infoItem('Rashi', p.rashi);
                    hor += infoItem('Nakshatra', p.nakshatra);
                    sections += '<div class="p-3 mb-2 rounded" style="background:#f5f0ff"><h6 style="font-size:13px;font-weight:600;color:#6b3fa0;margin:0 0 8px 0">Horoscope</h6><div class="row gx-3">' + hor + '</div></div>';
                }

                if (p.about_me) {
                    sections += '<div class="p-3 rounded" style="background:#f5f5f5"><h6 style="font-size:13px;font-weight:600;color:#333;margin:0 0 8px 0">About</h6><p style="font-size:13px;color:#555;margin:0;line-height:1.5">' + escapeHtml(p.about_me) + '</p></div>';
                }

                var html =
                    '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">' +
                    '<div class="modal-content" style="border-radius:14px;overflow:hidden;border:none">' +
                    '<div style="background:linear-gradient(135deg,#2d1656,#9565be);color:#fff;padding:18px 20px;display:flex;align-items:center;gap:14px">' +
                    '<img src="' + photo + '" alt="" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.4)" onerror="this.src=\'' + placeholderSVG(initials) + '\'">' +
                    '<div style="flex:1"><h5 style="margin:0;font-size:16px;font-weight:600">' + fullName + '</h5>' +
                    '<p style="margin:2px 0 0;font-size:12px;opacity:.85">' + (p.age || '') + ' yrs &middot; ' + escapeHtml(p.religion || '') + ' &middot; ' + escapeHtml(p.city || '') + '</p></div>' +
                    '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="font-size:14px"></button></div>' +
                    '<div class="modal-body" style="padding:14px;background:#fafafa">' + sections + '</div></div></div>';
                var modalEl = document.createElement('div');
                modalEl.className = 'modal fade';
                modalEl.innerHTML = html;
                document.body.appendChild(modalEl);
                var modal = new bootstrap.Modal(modalEl);
                modal.show();
                modalEl.addEventListener('hidden.bs.modal', function () { modalEl.remove(); });
            }
        });
    };

    window.shareProfile = function () {
        var url = window.location.origin + '/profile/' + (document.querySelector('.profile-page')?.dataset?.userId || '');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () {
                showToast('Profile link copied to clipboard!', 'success');
            }).catch(function () {
                showInfoAlert('Share Profile', '<div class="text-center"><p class="mb-2">Copy this link to share your profile:</p><input type="text" class="form-control text-center" value="' + escapeHtml(url) + '" readonly onclick="this.select()" style="cursor:text;background:#f8f9fa" /></div>');
            });
        } else {
            showInfoAlert('Share Profile', '<div class="text-center"><p class="mb-2">Copy this link to share your profile:</p><input type="text" class="form-control text-center" value="' + escapeHtml(url) + '" readonly onclick="this.select()" style="cursor:text;background:#f8f9fa" /></div>');
        }
    };

    function initAuthForms() {
        var loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', function (e) {
                document.getElementById('login-error').classList.add('d-none');
                var btn = loginForm.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Logging in...';
            });
        }

        var regForm = document.getElementById('register-form');
        if (regForm) {
            var regPassword = document.getElementById('password');
            var regConfirm = document.getElementById('confirm_password');
            var pwStrength = document.getElementById('password-strength');
            var confirmMatch = document.getElementById('confirm-match');

            if (regPassword && pwStrength) {
                regPassword.addEventListener('input', function () {
                    var v = regPassword.value;
                    var s = '';
                    if (v.length < 8) { s = 'Too short'; pwStrength.className = 'text-danger'; }
                    else if (!/[a-z]/.test(v)) { s = 'Missing lowercase letter'; pwStrength.className = 'text-warning'; }
                    else if (!/[0-9]/.test(v)) { s = 'Missing number'; pwStrength.className = 'text-warning'; }
                    else { s = 'Strong password'; pwStrength.className = 'text-success'; }
                    pwStrength.textContent = s;
                    if (confirmMatch && regConfirm.value) checkPasswordMatch();
                });
            }

            function checkPasswordMatch() {
                if (regConfirm.value === '') { confirmMatch.textContent = ''; return; }
                if (regPassword.value === regConfirm.value) {
                    confirmMatch.textContent = 'Passwords match';
                    confirmMatch.className = 'text-success';
                } else {
                    confirmMatch.textContent = 'Passwords do not match';
                    confirmMatch.className = 'text-danger';
                }
            }

            if (regConfirm && confirmMatch) {
                regConfirm.addEventListener('input', checkPasswordMatch);
            }

            regForm.addEventListener('submit', function (e) {
                if (regForm.dataset.submitting === '1') return;

                if (regPassword && regConfirm && regPassword.value !== regConfirm.value) {
                    e.preventDefault();
                    if (confirmMatch) {
                        confirmMatch.textContent = 'Passwords do not match';
                        confirmMatch.className = 'text-danger';
                    }
                    return;
                }

                e.preventDefault();
                regForm.dataset.submitting = '1';

                var btn = regForm.querySelector('button[type="submit"]');
                var orig = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating account...';

                var errEl = document.getElementById('register-errors');
                var errList = document.getElementById('register-errors-list');
                if (errEl) errEl.classList.add('d-none');
                if (errList) errList.innerHTML = '';

                var formData = new FormData(regForm);
                var data = {};
                formData.forEach(function (v, k) { data[k] = v; });

                fetch('/users/register', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(data)
                })
                .then(function (r) { return r.json(); })
                .then(function (result) {
                    regForm.dataset.submitting = '0';
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    if (result.success) {
                        window.location.href = result.redirect || '/profile';
                    } else if (result.errors && errEl && errList) {
                        errList.innerHTML = result.errors.map(function (e) { return '<li>' + escapeHtml(e) + '</li>'; }).join('');
                        errEl.classList.remove('d-none');
                    } else if (result.error && errEl) {
                        errEl.textContent = result.error;
                        errEl.classList.remove('d-none');
                    }
                })
                .catch(function () {
                    regForm.dataset.submitting = '0';
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    if (errEl) {
                        errEl.textContent = 'Network error. Please try again.';
                        errEl.classList.remove('d-none');
                    }
                });
            });
        }
    }

    window.handleLogout = function () {
        var csrf = '';
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) csrf = meta.getAttribute('content');

        fetch('/users/logout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ csrf: csrf })
        })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) {
                window.location.href = result.redirect || '/home';
            } else {
                var f = document.getElementById('logout-form');
                if (f) f.submit();
            }
        })
        .catch(function () {
            var f = document.getElementById('logout-form');
            if (f) f.submit();
        });
    };

    function initContactForm() {
        var form = document.querySelector('#contact-form form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (form.dataset.submitting === '1') return;
            form.dataset.submitting = '1';

            var btn = form.querySelector('button[type="submit"]');
            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending...';

            var data = {
                name: document.getElementById('name')?.value || '',
                email: document.getElementById('email')?.value || '',
                phone: document.getElementById('phone')?.value || '',
                subject: document.getElementById('subject')?.value || '',
                message: document.getElementById('message')?.value || ''
            };

            fetch('/api/contact', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(data)
            })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                form.dataset.submitting = '0';
                btn.disabled = false;
                btn.innerHTML = orig;
                if (result.success) {
                    showToast('Message sent! We will get back to you soon.', 'success');
                    form.reset();
                } else {
                    showToast(result.error || 'Failed to send message. Please try again.', 'danger');
                }
            })
            .catch(function () {
                form.dataset.submitting = '0';
                btn.disabled = false;
                btn.innerHTML = orig;
                showToast('Network error. Please try again.', 'danger');
            });
        });
    }

    function addPhotoCard(photo) {
        var gallery = document.getElementById('photo-gallery');
        if (!gallery) return;

        var empty = gallery.querySelector('.col-12.text-center.py-5');
        if (empty) empty.remove();

        var col = document.createElement('div');
        col.className = 'col-md-3 col-6';
        col.dataset.photoId = photo.id;
        col.innerHTML =
            '<div class="photo-card position-relative" data-photo-id="' + photo.id + '">' +
                '<img src="' + escapeHtml(photo.path) + '" class="img-fluid" alt="" onerror="this.src=\'uploads/photos/default-avatar.svg\'">' +
                '<div class="photo-overlay">' +
                    (photo.is_primary
                        ? '<span class="badge bg-success">Primary</span>'
                        : '<button class="btn btn-sm btn-outline-light set-primary-btn" data-id="' + photo.id + '">Set as Primary</button>') +
                    '<button class="btn btn-sm btn-outline-danger delete-photo-btn" data-id="' + photo.id + '">Delete</button>' +
                '</div>' +
            '</div>';

        var priBtn = col.querySelector('.set-primary-btn');
        if (priBtn) {
            priBtn.addEventListener('click', function () {
                setPrimaryPhotoAjax(photo.id, col);
            });
        }

        var delBtn = col.querySelector('.delete-photo-btn');
        delBtn.addEventListener('click', function () {
            showConfirm({
                title: 'Delete Photo',
                message: 'Are you sure you want to delete this photo?',
                confirmText: 'Delete',
                confirmClass: 'btn-danger'
            }, function(confirmed) {
                if (!confirmed) return;
                deletePhotoAjax(photo.id, col);
            });
        });

        gallery.insertBefore(col, gallery.firstChild);
    }

    function setPrimaryPhotoAjax(photoId, colEl) {
        var csrf = document.querySelector('.profile-page')?.dataset?.csrf || '';

        fetch('/api/profile/photo/' + photoId, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ csrf: csrf, _method: 'PUT' })
        })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) {
                document.querySelectorAll('#photo-gallery .badge.bg-success').forEach(function (b) {
                    var parentCard = b.closest('.photo-card');
                    if (parentCard) {
                        var overlay = parentCard.querySelector('.photo-overlay');
                        if (overlay) {
                            var pid = parentCard.dataset.photoId;
                            var btn = document.createElement('button');
                            btn.className = 'btn btn-sm btn-outline-light set-primary-btn';
                            btn.dataset.id = pid;
                            btn.textContent = 'Set as Primary';
                            btn.addEventListener('click', function () {
                                setPrimaryPhotoAjax(pid, parentCard.closest('.col-md-3, .col-6'));
                            });
                            b.replaceWith(btn);
                        }
                    }
                });

                if (colEl) {
                    var overlay = colEl.querySelector('.photo-overlay');
                    if (overlay) {
                        var oldBtn = overlay.querySelector('.set-primary-btn');
                        if (oldBtn) {
                            var badge = document.createElement('span');
                            badge.className = 'badge bg-success';
                            badge.textContent = 'Primary';
                            oldBtn.replaceWith(badge);
                        }
                    }
                }

                var img = colEl?.querySelector('img');
                if (img) {
                    var avatar = document.getElementById('profile-avatar-img');
                    if (avatar) avatar.src = img.src;
                }

                showToast('Primary photo updated!', 'success');
            } else {
                showToast(result.error || 'Failed to set primary photo.', 'danger');
            }
        })
        .catch(function () {
            showToast('Network error. Please try again.', 'danger');
        });
    }

    function deletePhotoAjax(photoId, colEl) {
        var csrf = document.querySelector('.profile-page')?.dataset?.csrf || '';

        fetch('/api/profile/photo/' + photoId, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ csrf: csrf })
        })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) {
                if (colEl && colEl.parentNode) {
                    colEl.parentNode.removeChild(colEl);
                }
                var remaining = document.querySelectorAll('#photo-gallery .photo-card');
                if (remaining.length === 0) {
                    var gallery = document.getElementById('photo-gallery');
                    if (gallery) {
                        gallery.innerHTML = '<div class="col-12 text-center py-5 text-muted">' +
                            '<p class="mb-2">No photos uploaded yet.</p>' +
                            '<p>Upload photos to increase your profile visibility.</p></div>';
                    }
                }
                showToast('Photo deleted successfully.', 'info');
            } else {
                showToast(result.error || 'Failed to delete photo.', 'danger');
            }
        })
        .catch(function () {
            showToast('Network error. Please try again.', 'danger');
        });
    }

    function applyPrivacyPreset(preset, btn) {
        var csrf = document.querySelector('.profile-page')?.dataset?.csrf || '';
        var origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying...';

        fetch('/api/profile/privacy', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ privacy_preset: preset, csrf: csrf })
        })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            if (result.success) {
                document.querySelectorAll('[data-preset]').forEach(function (b) {
                    b.className = 'btn btn-sm ' + (b.dataset.preset === preset ? 'btn-primary' : 'btn-outline-primary');
                });
                var page = document.querySelector('.profile-page');
                if (page && result.data) {
                    page.querySelectorAll('.privacy-toggle').forEach(function (cb) {
                        var key = cb.dataset.key;
                        if (result.data[key] !== undefined) {
                            cb.checked = result.data[key] == 1;
                        }
                    });
                }
                showToast('Privacy preset "' + preset + '" applied.', 'success');
            } else {
                showToast(result.error || 'Failed to apply privacy preset.', 'danger');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = origHtml;
            showToast('Network error. Please try again.', 'danger');
        });
    }

    function upgradePhotoHandlers() {
        var page = document.querySelector('.profile-page');
        if (!page) return;

        var uploadInputs = ['photo-upload-input', 'gallery-upload-input'];
        uploadInputs.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                var clone = el.cloneNode(true);
                el.parentNode.replaceChild(clone, el);
                clone.addEventListener('change', function (e) {
                    var csrf = page.dataset.csrf || '';
                    window.uploadPhoto(e.target, csrf);
                });
            }
        });
        page.querySelectorAll('.set-primary-btn').forEach(function (btn) {
            var clone = btn.cloneNode(true);
            btn.parentNode.replaceChild(clone, btn);
            clone.addEventListener('click', function () {
                var id = this.dataset.id;
                var col = this.closest('.col-md-3, .col-6');
                setPrimaryPhotoAjax(id, col);
            });
        });

        page.querySelectorAll('.delete-photo-btn').forEach(function (btn) {
            var clone = btn.cloneNode(true);
            btn.parentNode.replaceChild(clone, btn);
            clone.addEventListener('click', function () {
                var self = this;
                showConfirm({
                    title: 'Delete Photo',
                    message: 'Are you sure you want to delete this photo?',
                    confirmText: 'Delete',
                    confirmClass: 'btn-danger'
                }, function(confirmed) {
                    if (!confirmed) return;
                    var id = self.dataset.id;
                    var col = self.closest('.col-md-3, .col-6');
                    deletePhotoAjax(id, col);
                });
            });
        });

        page.querySelectorAll('[data-preset]').forEach(function (btn) {
            var clone = btn.cloneNode(true);
            btn.parentNode.replaceChild(clone, btn);
            clone.addEventListener('click', function () {
                var preset = this.dataset.preset;
                applyPrivacyPreset(preset, this);
            });
        });
    }

    function refreshProfileCompletion() {
        fetch('/api/profile/completion', {
            headers: { 'Accept': 'application/json' },
            credentials: 'include'
        })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (!result.success) return;
            var pct = result.data?.percentage ?? result.percentage ?? 0;
            var bar = document.querySelector('.profile-page .progress-bar');
            if (bar) {
                bar.style.width = pct + '%';
                bar.textContent = '';
            }
            var label = document.querySelector('.profile-page .fw-medium');
            if (label && label.textContent.indexOf('%') > -1) {
                label.textContent = pct + '% Complete';
            }
            var checklistBtn = document.querySelector('.btn[onclick*="showCompletionChecklist"]');
            if (checklistBtn) {
                checklistBtn.innerHTML = checklistBtn.innerHTML.replace(/\d+%/, pct + '%');
            }
        })
        .catch(function () {});
    }

    function initPasswordToggles() {
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-pw-toggle]');
            if (!btn) return;
            var input = btn.parentNode.querySelector('input');
            if (!input) return;
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            var eye = btn.querySelector('.pw-eye');
            var eyeOff = btn.querySelector('.pw-eye-off');
            if (eye) eye.classList.toggle('d-none');
            if (eyeOff) eyeOff.classList.toggle('d-none');
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (typeof closeMobileDrawer === 'function') closeMobileDrawer();
            if (typeof closeAllPanels === 'function') closeAllPanels();
        }
    });

    document.addEventListener('click', function(e) {
        var overlay = document.getElementById('filterOverlay');
        if (overlay && overlay.classList.contains('active') && e.target === overlay) {
            closeMobileDrawer();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        initMatchesPage();
        initProfilePage();
        upgradePhotoHandlers();
        initAuthForms();
        initContactForm();
        initPasswordToggles();
    });
})();
