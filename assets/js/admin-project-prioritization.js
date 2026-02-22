(function () {
    if (!location.pathname.endsWith('/project-prioritization.php')) return;

    function openModalById(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        document.querySelectorAll('.modal.show').forEach(function (m) { m.classList.remove('show'); });
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function openOverlayModalById(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        // Keep currently opened details modal visible; just open photo on top.
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModalById(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('show');
        if (!document.querySelector('.modal.show')) {
            document.body.style.overflow = '';
        }
    }

    function showTinyToast(title, text, isError) {
        var toast = document.createElement('div');
        toast.className = 'prioritization-toast ' + (isError ? 'is-error' : 'is-success');
        toast.innerHTML = '<strong>' + title + '</strong><span>' + text + '</span>';
        document.body.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('show'); });
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 220);
        }, 2600);
    }

    document.addEventListener('click', function (e) {
        var editBtn = e.target.closest('[data-edit-modal]');
        if (editBtn) return openModalById(editBtn.getAttribute('data-edit-modal'));

        var viewBtn = e.target.closest('[data-view-modal]');
        if (viewBtn) return openModalById(viewBtn.getAttribute('data-view-modal'));

        var addressBtn = e.target.closest('[data-address-modal]');
        if (addressBtn) return openModalById(addressBtn.getAttribute('data-address-modal'));

        var photoBtn = e.target.closest('[data-photo-modal]');
        if (photoBtn) return openOverlayModalById(photoBtn.getAttribute('data-photo-modal'));

        var closeBtn = e.target.closest('[data-close-modal]');
        if (closeBtn) return closeModalById(closeBtn.getAttribute('data-close-modal'));

        var copyBtn = e.target.closest('[data-copy-control]');
        if (copyBtn) {
            var control = copyBtn.getAttribute('data-copy-control') || '';
            if (!control) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(control).then(function () {
                    showTinyToast('Copied', control, false);
                }).catch(function () {
                    showTinyToast('Copy failed', 'Please copy manually.', true);
                });
            } else {
                showTinyToast('Copy unavailable', 'Clipboard API not supported.', true);
            }
            return;
        }

        if (e.target.classList && e.target.classList.contains('modal')) {
            e.target.classList.remove('show');
            if (!document.querySelector('.modal.show')) document.body.style.overflow = '';
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.modal.show').forEach(function (m) { m.classList.remove('show'); });
        document.body.style.overflow = '';
    });

    var searchInput = document.getElementById('fbSearch');
    var statusFilter = document.getElementById('fbStatusFilter');
    var categoryFilter = document.getElementById('fbCategoryFilter');
    var visibleCount = document.getElementById('fbVisibleCount');
    var priorityCard = document.getElementById('priorityTopCard');
    var rows = Array.from(document.querySelectorAll('#inputsTable tbody tr[data-status]'));
    var sectionHeaders = Array.from(document.querySelectorAll('#inputsTable tbody tr[data-section-header]'));
    var sectionEmptyRows = Array.from(document.querySelectorAll('#inputsTable tbody tr[data-section-empty]'));
    var activePriorityFilter = null;

    function applyFeedbackFilters() {
        var query = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
        var status = (statusFilter && statusFilter.value ? statusFilter.value : '').trim().toLowerCase();
        var category = (categoryFilter && categoryFilter.value ? categoryFilter.value : '').trim().toLowerCase();
        var shown = 0;

        rows.forEach(function (row) {
            var haystack = row.textContent.toLowerCase();
            var rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
            var rowCategory = (row.getAttribute('data-category') || '').toLowerCase();
            var rowDistrict = (row.getAttribute('data-district') || '').toLowerCase();
            var rowBarangay = (row.getAttribute('data-barangay') || '').toLowerCase();
            var rowAlternative = (row.getAttribute('data-alternative-name') || '').toLowerCase();
            var rowLocation = (row.getAttribute('data-location') || '').toLowerCase();
            var matchesPriority = true;

            if (activePriorityFilter) {
                if (activePriorityFilter.district || activePriorityFilter.barangay || activePriorityFilter.alternative) {
                    matchesPriority = (!activePriorityFilter.district || rowDistrict === activePriorityFilter.district)
                        && (!activePriorityFilter.barangay || rowBarangay === activePriorityFilter.barangay)
                        && (!activePriorityFilter.alternative || rowAlternative === activePriorityFilter.alternative);
                } else if (activePriorityFilter.location) {
                    matchesPriority = rowLocation === activePriorityFilter.location;
                }
            }

            var visible = (!query || haystack.indexOf(query) >= 0)
                && (!status || rowStatus === status)
                && (!category || rowCategory === category);
            visible = visible && matchesPriority;
            row.style.display = visible ? '' : 'none';
            if (visible) shown += 1;
        });

        var visibleBySection = {};
        rows.forEach(function (row) {
            var section = (row.getAttribute('data-section') || '').toLowerCase();
            if (!section) return;
            if (!visibleBySection[section]) visibleBySection[section] = 0;
            if (row.style.display !== 'none') visibleBySection[section] += 1;
        });
        sectionHeaders.forEach(function (headerRow) {
            var section = (headerRow.getAttribute('data-section-header') || '').toLowerCase();
            var count = visibleBySection[section] || 0;
            headerRow.style.display = (shown === 0 && !query && !status && !category && !activePriorityFilter) || count > 0 ? '' : 'none';
        });
        sectionEmptyRows.forEach(function (emptyRow) {
            var section = (emptyRow.getAttribute('data-section-empty') || '').toLowerCase();
            var count = visibleBySection[section] || 0;
            emptyRow.style.display = count > 0 ? 'none' : '';
        });

        if (visibleCount) {
            visibleCount.textContent = 'Showing ' + shown + ' of ' + rows.length + (activePriorityFilter ? ' | Priority filter active' : '');
        }
    }

    var statusFlash = new URLSearchParams(window.location.search).get('status');
    if (statusFlash) {
        if (statusFlash === 'updated') {
            showTinyToast('Success', 'Feedback status updated.', false);
        } else if (statusFlash === 'reject_note_required') {
            showTinyToast('Reason required', 'Please provide rejection reason before saving.', true);
        } else if (statusFlash === 'invalid') {
            showTinyToast('Invalid status', 'Please choose a valid status.', true);
        } else {
            showTinyToast('Update failed', 'Unable to save feedback status.', true);
        }
        var cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('status');
        history.replaceState({}, '', cleanUrl.toString());
    }

    if (searchInput) searchInput.addEventListener('input', applyFeedbackFilters);
    if (statusFilter) statusFilter.addEventListener('change', applyFeedbackFilters);
    if (categoryFilter) categoryFilter.addEventListener('change', applyFeedbackFilters);

    var clearBtn = document.getElementById('clearSearch');
    var applyBtn = document.getElementById('applyServerFilters');

    function applyServerFilters() {
        var url = new URL(window.location.href);
        var qVal = (searchInput && searchInput.value ? searchInput.value : '').trim();
        var sVal = (statusFilter && statusFilter.value ? statusFilter.value : '').trim().toLowerCase();
        var cVal = (categoryFilter && categoryFilter.value ? categoryFilter.value : '').trim().toLowerCase();

        if (qVal) url.searchParams.set('q', qVal); else url.searchParams.delete('q');
        if (sVal) url.searchParams.set('status_filter', sVal); else url.searchParams.delete('status_filter');
        if (cVal) url.searchParams.set('category_filter', cVal); else url.searchParams.delete('category_filter');
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    if (applyBtn) {
        applyBtn.addEventListener('click', applyServerFilters);
    }

    [searchInput, statusFilter, categoryFilter].forEach(function (el) {
        if (!el) return;
        el.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                applyServerFilters();
            }
        });
    });
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            var url = new URL(window.location.href);
            url.searchParams.delete('q');
            url.searchParams.delete('status_filter');
            url.searchParams.delete('category_filter');
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    }

    function togglePriorityFilter() {
        if (!priorityCard) return;
        if (activePriorityFilter) {
            activePriorityFilter = null;
            priorityCard.classList.remove('is-active');
            applyFeedbackFilters();
            return;
        }
        var district = (priorityCard.getAttribute('data-district') || '').trim().toLowerCase();
        var barangay = (priorityCard.getAttribute('data-barangay') || '').trim().toLowerCase();
        var alternative = (priorityCard.getAttribute('data-alternative-name') || '').trim().toLowerCase();
        var location = (priorityCard.getAttribute('data-location') || '').trim().toLowerCase();
        activePriorityFilter = {
            district: district,
            barangay: barangay,
            alternative: alternative,
            location: location
        };
        priorityCard.classList.add('is-active');
        applyFeedbackFilters();
        var table = document.getElementById('inputsTable');
        if (table && table.scrollIntoView) {
            table.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    if (priorityCard) {
        priorityCard.addEventListener('click', togglePriorityFilter);
        priorityCard.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                togglePriorityFilter();
            }
        });
    }

    document.querySelectorAll('select.status-dropdown').forEach(function (selectEl) {
        function toggleRejectNote() {
            var id = (selectEl.id || '').replace('status-', '');
            if (!id) return;
            var wrap = document.getElementById('reject-note-wrap-' + id);
            if (!wrap) return;
            var isRejected = (selectEl.value || '').toLowerCase() === 'rejected';
            wrap.style.display = isRejected ? '' : 'none';
            var textarea = wrap.querySelector('textarea[name="rejection_note"]');
            if (textarea) textarea.required = isRejected;
        }
        selectEl.addEventListener('change', toggleRejectNote);
        toggleRejectNote();
    });

    applyFeedbackFilters();
})();
