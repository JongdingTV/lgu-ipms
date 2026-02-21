(function () {
    if (!location.pathname.endsWith('/project-prioritization.php')) return;

    function openModalById(modalId) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        document.querySelectorAll('.modal.show').forEach(function (m) { m.classList.remove('show'); });
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
        if (photoBtn) return openModalById(photoBtn.getAttribute('data-photo-modal'));

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
    var rows = Array.from(document.querySelectorAll('#inputsTable tbody tr')).filter(function (row) {
        return !row.querySelector('.no-results');
    });

    function applyFeedbackFilters() {
        var query = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
        var status = (statusFilter && statusFilter.value ? statusFilter.value : '').trim().toLowerCase();
        var category = (categoryFilter && categoryFilter.value ? categoryFilter.value : '').trim().toLowerCase();
        var shown = 0;

        rows.forEach(function (row) {
            var haystack = row.textContent.toLowerCase();
            var rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
            var rowCategory = (row.getAttribute('data-category') || '').toLowerCase();
            var visible = (!query || haystack.indexOf(query) >= 0)
                && (!status || rowStatus === status)
                && (!category || rowCategory === category);
            row.style.display = visible ? '' : 'none';
            if (visible) shown += 1;
        });

        if (visibleCount) visibleCount.textContent = 'Showing ' + shown + ' of ' + rows.length;
    }

    var statusFlash = new URLSearchParams(window.location.search).get('status');
    if (statusFlash) {
        if (statusFlash === 'updated') {
            showTinyToast('Success', 'Feedback status updated.', false);
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
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (statusFilter) statusFilter.value = '';
            if (categoryFilter) categoryFilter.value = '';
            applyFeedbackFilters();
        });
    }

    applyFeedbackFilters();
})();
