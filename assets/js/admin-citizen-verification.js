(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('[data-row]'));
    var search = document.getElementById('verifySearch');
    var filter = document.getElementById('verifyStatus');
    var userIdInput = document.getElementById('vmUserId');
    var idImg = document.getElementById('vmIdImage');
    var idFrontImg = document.getElementById('vmIdFrontImage');
    var idBackImg = document.getElementById('vmIdBackImage');
    var idDual = document.getElementById('vmIdDual');
    var idPdf = document.getElementById('vmIdPdf');
    var idEmpty = document.getElementById('vmIdEmpty');

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) el.textContent = value && value !== '' ? value : '-';
    }

    function openRow(row) {
        if (!row) return;
        rows.forEach(function (r) { r.classList.remove('active'); });
        row.classList.add('active');

        setText('vmName', row.getAttribute('data-name'));
        setText('vmEmail', row.getAttribute('data-email'));
        setText('vmMobile', row.getAttribute('data-mobile'));
        setText('vmBirthdate', row.getAttribute('data-birthdate'));
        setText('vmGender', row.getAttribute('data-gender'));
        setText('vmCivilStatus', row.getAttribute('data-civil_status'));
        setText('vmAddress', row.getAttribute('data-address'));
        setText('vmIdType', row.getAttribute('data-id_type'));
        setText('vmIdNumber', row.getAttribute('data-id_number'));
        setText('vmCreated', row.getAttribute('data-created'));

        var badge = document.getElementById('vmStatusBadge');
        if (badge) {
            badge.textContent = row.getAttribute('data-status') || 'Pending';
            badge.className = 'status-badge ' + (row.getAttribute('data-status_class') || 'pending');
        }
        if (userIdInput) userIdInput.value = row.getAttribute('data-user_id') || '0';

        if (idImg) { idImg.src = ''; idImg.style.display = 'none'; }
        if (idFrontImg) { idFrontImg.src = ''; idFrontImg.style.display = 'none'; }
        if (idBackImg) { idBackImg.src = ''; idBackImg.style.display = 'none'; }
        if (idDual) idDual.style.display = 'none';
        if (idPdf) { idPdf.src = ''; idPdf.style.display = 'none'; }

        var file = row.getAttribute('data-id_upload') || '';
        if (!file) {
            if (idEmpty) idEmpty.style.display = 'block';
            return;
        }
        if (idEmpty) idEmpty.style.display = 'none';

        var lower = file.toLowerCase();
        var parsed = null;
        if (file && (file.charAt(0) === '{' || file.charAt(0) === '[')) {
            try { parsed = JSON.parse(file); } catch (_) { parsed = null; }
        }
        if (parsed && typeof parsed === 'object' && (parsed.front || parsed.back)) {
            if (idDual) idDual.style.display = 'grid';
            if (idFrontImg) {
                idFrontImg.src = parsed.front || parsed.back || '';
                idFrontImg.style.display = 'block';
            }
            if (idBackImg) {
                idBackImg.src = parsed.back || parsed.front || '';
                idBackImg.style.display = 'block';
            }
            return;
        }

        if (lower.endsWith('.pdf')) {
            if (idPdf) { idPdf.src = file; idPdf.style.display = 'block'; }
        } else {
            if (idImg) { idImg.src = file; idImg.style.display = 'block'; }
        }
    }

    function applyFilter() {
        var q = (search && search.value ? search.value : '').toLowerCase().trim();
        var s = filter ? filter.value : 'all';

        rows.forEach(function (row) {
            var text = (row.textContent || '').toLowerCase();
            var status = (row.getAttribute('data-status_key') || '').toLowerCase();
            var okStatus = s === 'all' || status === s;
            var okQuery = q === '' || text.indexOf(q) !== -1;
            row.style.display = okStatus && okQuery ? '' : 'none';
        });

        var active = document.querySelector('.verify-row.active');
        if (!active || active.style.display === 'none') {
            var firstVisible = rows.find(function (r) { return r.style.display !== 'none'; });
            if (firstVisible) openRow(firstVisible);
        }
    }

    rows.forEach(function (row) {
        row.addEventListener('click', function () { openRow(row); });
    });
    if (search) search.addEventListener('input', applyFilter);
    if (filter) filter.addEventListener('change', applyFilter);

    var selectedId = Number(document.body.getAttribute("data-selected-user") || 0);
    if (selectedId > 0) {
        var selected = document.querySelector('[data-row][data-user_id="' + String(selectedId) + '"]');
        if (selected) openRow(selected);
    } else {
        var first = rows[0];
        if (first) openRow(first);
    }
})();
