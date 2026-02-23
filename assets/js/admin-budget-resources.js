(function () {
        const path = (window.location.pathname || '').replace(/\\/g, '/');
        if (!path.endsWith('/admin/budget_resources.php')) return;

        let booted = false;
        let activePanel = 'sources';
        let stateCache = { milestones: [], expenses: [] };
        const tableState = {
            milestones: { page: 1, perPage: 12, total: 0, totalPages: 1, q: '', rows: [] },
            expenses: { page: 1, perPage: 12, total: 0, totalPages: 1, q: '', rows: [] }
        };
        const API_BASE = 'budget_resources.php';

        function byId(id) { return document.getElementById(id); }

        function notify(title, message, type) {
            const kind = type || 'info';
            let stack = document.querySelector('.br-toast-stack');
            if (!stack) {
                stack = document.createElement('div');
                stack.className = 'br-toast-stack';
                document.body.appendChild(stack);
            }

            const toast = document.createElement('div');
            toast.className = 'br-toast ' + kind;
            toast.innerHTML = '<div class="br-toast-title"></div><div class="br-toast-message"></div>';
            toast.querySelector('.br-toast-title').textContent = title;
            toast.querySelector('.br-toast-message').textContent = message;
            stack.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(6px)';
                toast.style.transition = 'all 0.2s ease';
                setTimeout(() => toast.remove(), 220);
            }, 2600);
        }

        function ensureConfirmDialog() {
            let overlay = document.querySelector('.br-confirm-overlay');
            if (overlay) return overlay;
            overlay = document.createElement('div');
            overlay.className = 'br-confirm-overlay';
            overlay.innerHTML = [
                '<div class="br-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="brConfirmTitle">',
                '  <div class="br-confirm-head">',
                '    <span class="br-confirm-icon">!</span>',
                '    <h3 id="brConfirmTitle" class="br-confirm-title">Delete item?</h3>',
                '  </div>',
                '  <p class="br-confirm-message">This action cannot be undone.</p>',
                '  <div class="br-confirm-item" id="brConfirmItem">Item</div>',
                '  <div class="br-confirm-actions">',
                '    <button type="button" class="br-confirm-cancel">Cancel</button>',
                '    <button type="button" class="br-confirm-delete">Delete Permanently</button>',
                '  </div>',
                '</div>'
            ].join('');
            document.body.appendChild(overlay);
            return overlay;
        }

        function confirmDelete(itemLabel, message) {
            const overlay = ensureConfirmDialog();
            const msgEl = overlay.querySelector('.br-confirm-message');
            const itemEl = overlay.querySelector('#brConfirmItem');
            const cancelBtn = overlay.querySelector('.br-confirm-cancel');
            const deleteBtn = overlay.querySelector('.br-confirm-delete');

            if (msgEl) msgEl.textContent = message || 'This action cannot be undone.';
            if (itemEl) itemEl.textContent = itemLabel || 'Selected item';

            return new Promise((resolve) => {
                const close = (result) => {
                    overlay.classList.remove('show');
                    cancelBtn.removeEventListener('click', onCancel);
                    deleteBtn.removeEventListener('click', onDelete);
                    overlay.removeEventListener('click', onBackdrop);
                    document.removeEventListener('keydown', onEscape);
                    resolve(result);
                };
                const onCancel = () => close(false);
                const onDelete = () => close(true);
                const onBackdrop = (e) => { if (e.target === overlay) close(false); };
                const onEscape = (e) => { if (e.key === 'Escape') close(false); };

                cancelBtn.addEventListener('click', onCancel);
                deleteBtn.addEventListener('click', onDelete);
                overlay.addEventListener('click', onBackdrop);
                document.addEventListener('keydown', onEscape);

                overlay.classList.add('show');
                deleteBtn.focus();
            });
        }

        function switchPanel(panelKey) {
            const tabs = document.querySelectorAll('.br-tab[data-panel]');
            const panels = document.querySelectorAll('.br-panel[id^="panel-"]');
            tabs.forEach((tab) => {
                const isActive = tab.getAttribute('data-panel') === panelKey;
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            panels.forEach((panel) => {
                const isActive = panel.id === 'panel-' + panelKey;
                panel.classList.toggle('active', isActive);
            });
            activePanel = panelKey;
        }

        function initSectionTabs() {
            const tabs = document.querySelectorAll('.br-tab[data-panel]');
            if (!tabs.length) return;
            tabs.forEach((tab) => {
                tab.addEventListener('click', function () {
                    switchPanel(this.getAttribute('data-panel') || 'sources');
                });
            });
            switchPanel(activePanel);
        }

        function currency(value) {
            const num = Number(value || 0);
            return 'PHP ' + num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        }

        function getApiUrlLocal(action) {
            if (typeof window.getApiUrl === 'function') {
                return window.getApiUrl('admin/' + API_BASE + '?action=' + encodeURIComponent(action));
            }
            return API_BASE + '?action=' + encodeURIComponent(action);
        }

        async function apiGet(action, params) {
            const url = getApiUrlLocal(action);
            const finalUrl = params ? (url + '&' + new URLSearchParams(params).toString()) : url;
            const res = await fetch(finalUrl, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        }

        async function apiPost(action, data) {
            const url = getApiUrlLocal(action);
            const body = new URLSearchParams();
            Object.keys(data || {}).forEach((k) => body.set(k, String(data[k] ?? '')));
            const res = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            if (json && json.success === false) throw new Error(json.message || 'Request failed');
            return json;
        }

        async function refreshState() {
            const json = await apiGet('load_budget_state');
            if (!json || json.success === false || !json.data) {
                throw new Error((json && json.message) ? json.message : 'Failed to load budget state');
            }
            stateCache = {
                milestones: Array.isArray(json.data.milestones) ? json.data.milestones : [],
                expenses: Array.isArray(json.data.expenses) ? json.data.expenses : []
            };
            return stateCache;
        }

        function getSpentForMilestone(state, milestoneId) {
            return state.expenses
                .filter((exp) => exp.milestoneId === milestoneId)
                .reduce((sum, exp) => sum + Number(exp.amount || 0), 0);
        }

        function ensureTablePager(tableKey) {
            const tableId = tableKey === 'milestones' ? 'milestonesTable' : 'expensesTable';
            const pagerId = tableKey === 'milestones' ? 'brMilestonesPager' : 'brExpensesPager';
            const table = byId(tableId);
            if (!table) return null;
            const wrap = table.closest('.table-wrap');
            if (!wrap) return null;
            let pager = byId(pagerId);
            if (pager) return pager;
            pager = document.createElement('div');
            pager.id = pagerId;
            pager.className = 'pm-pagination-controls';
            wrap.insertAdjacentElement('afterend', pager);
            return pager;
        }

        function renderTablePager(tableKey) {
            const pager = ensureTablePager(tableKey);
            if (!pager) return;
            const s = tableState[tableKey];
            const page = Number(s.page || 1);
            const totalPages = Math.max(1, Number(s.totalPages || 1));
            const total = Number(s.total || 0);
            const perPage = Number(s.perPage || 12);
            const from = total === 0 ? 0 : ((page - 1) * perPage) + 1;
            const to = total === 0 ? 0 : Math.min(total, page * perPage);
            pager.innerHTML = `
                <span class="pm-result-summary">Showing ${from}-${to} of ${total}</span>
                <div>
                    <button type="button" class="btn-clear-filters" data-br-table="${tableKey}" data-page-dir="prev" ${page <= 1 ? 'disabled' : ''}>Previous</button>
                    <button type="button" class="btn-clear-filters" data-br-table="${tableKey}" data-page-dir="next" ${page >= totalPages ? 'disabled' : ''}>Next</button>
                </div>
            `;
        }

        function renderMilestones(rows) {
            const tbody = document.querySelector('#milestonesTable tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            const data = Array.isArray(rows) ? rows : [];

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#64748b;padding:16px;">No matching project budgets.</td></tr>';
                return;
            }

            data.forEach((ms) => {
                const allocated = Number(ms.allocated || 0);
                const spent = Number(ms.spent || 0);
                const remaining = Number(ms.remaining || Math.max(0, allocated - spent));
                const consumed = Number(ms.consumed_percent || (allocated ? Math.round((spent / allocated) * 100) : 0));

                const tr = document.createElement('tr');
                tr.innerHTML = [
                    '<td>' + String(ms.name || '') + '</td>',
                    '<td>' + currency(allocated) + '</td>',
                    '<td>' + currency(spent) + '</td>',
                    '<td>' + currency(remaining) + '</td>',
                    '<td>' + consumed + '%</td>'
                ].join('');
                tbody.appendChild(tr);
            });
        }

        function renderExpenses(rows) {
            const tbody = document.querySelector('#expensesTable tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            const data = Array.isArray(rows) ? rows : [];

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#64748b;padding:16px;">No matching expenses.</td></tr>';
                return;
            }

            data.forEach((exp) => {
                const projectName = String(exp.project_name || '(project removed)');
                const tr = document.createElement('tr');
                tr.innerHTML = [
                    '<td>' + new Date(exp.date || Date.now()).toLocaleString() + '</td>',
                    '<td>' + projectName + '</td>',
                    '<td>' + String(exp.description || '') + '</td>',
                    '<td>' + currency(exp.amount) + '</td>',
                    '<td><button class="br-btn-delete btnDeleteExpense" type="button" data-id="' + exp.id + '" data-desc="' + String(exp.description || '').replace(/"/g, '&quot;') + '">Delete</button></td>'
                ].join('');
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('.btnDeleteExpense').forEach((btn) => {
                btn.addEventListener('click', async function () {
                    const id = this.getAttribute('data-id');
                    const desc = this.getAttribute('data-desc') || 'Expense entry';
                    const label = desc.trim() ? desc : 'Expense entry';
                    const ok = await confirmDelete(label, 'Delete this expense record permanently?');
                    if (!ok) return;
                    try {
                        await apiPost('delete_expense', { id: id });
                        await renderAllFromServer();
                        notify('Deleted', 'Expense removed successfully.', 'success');
                    } catch (err) {
                        console.error(err);
                        notify('Delete Failed', 'Failed to delete expense.', 'error');
                    }
                });
            });
        }

        async function loadMilestonesPage() {
            const json = await apiGet('load_milestones', {
                page: tableState.milestones.page,
                per_page: tableState.milestones.perPage,
                q: tableState.milestones.q,
                _: Date.now()
            });
            if (!json || json.success === false) {
                throw new Error((json && json.message) ? json.message : 'Failed to load milestones');
            }
            const rows = Array.isArray(json.data) ? json.data : [];
            const meta = json.meta || {};
            tableState.milestones.rows = rows;
            tableState.milestones.page = Number(meta.page || 1);
            tableState.milestones.perPage = Number(meta.per_page || tableState.milestones.perPage);
            tableState.milestones.total = Number(meta.total || rows.length);
            tableState.milestones.totalPages = Math.max(1, Number(meta.total_pages || 1));
            renderMilestones(rows);
            renderTablePager('milestones');
        }

        async function loadExpensesPage() {
            const json = await apiGet('load_expenses', {
                page: tableState.expenses.page,
                per_page: tableState.expenses.perPage,
                q: tableState.expenses.q,
                _: Date.now()
            });
            if (!json || json.success === false) {
                throw new Error((json && json.message) ? json.message : 'Failed to load expenses');
            }
            const rows = Array.isArray(json.data) ? json.data : [];
            const meta = json.meta || {};
            tableState.expenses.rows = rows;
            tableState.expenses.page = Number(meta.page || 1);
            tableState.expenses.perPage = Number(meta.per_page || tableState.expenses.perPage);
            tableState.expenses.total = Number(meta.total || rows.length);
            tableState.expenses.totalPages = Math.max(1, Number(meta.total_pages || 1));
            renderExpenses(rows);
            renderTablePager('expenses');
        }

        function populateSourceSelect(state) {
            const select = byId('expenseMilestone');
            if (!select) return;
            select.innerHTML = '<option value="">Select project</option>';
            state.milestones.forEach((m) => {
                const option = document.createElement('option');
                option.value = m.id;
                option.textContent = m.name;
                select.appendChild(option);
            });
        }

        function renderSummary(state) {
            const allocated = state.milestones.reduce((sum, m) => sum + Number(m.allocated || 0), 0);
            const spent = state.expenses.reduce((sum, e) => sum + Number(e.amount || 0), 0);
            const remaining = Math.max(0, allocated - spent);
            const base = allocated || 0;
            const consumption = base ? Math.round((spent / base) * 100) : 0;

            const allocatedEl = byId('summaryAllocated');
            const spentEl = byId('summarySpent');
            const remainingEl = byId('summaryRemaining');
            const consumptionEl = byId('summaryConsumption');
            if (allocatedEl) allocatedEl.textContent = currency(allocated);
            if (spentEl) spentEl.textContent = currency(spent);
            if (remainingEl) remainingEl.textContent = currency(remaining);
            if (consumptionEl) consumptionEl.textContent = consumption + '%';

            const healthFill = byId('budgetHealthFill');
            const healthTag = byId('budgetHealthTag');
            const healthText = byId('budgetHealthText');
            if (healthFill) healthFill.style.width = Math.max(0, Math.min(100, consumption)) + '%';
            if (healthTag && healthText) {
                healthTag.className = 'br-health-tag';
                if (consumption >= 90) {
                    healthTag.classList.add('danger');
                    healthTag.textContent = 'Critical';
                    healthText.textContent = 'Budget is near or over limit. Consider immediate review.';
                } else if (consumption >= 70) {
                    healthTag.classList.add('warn');
                    healthTag.textContent = 'Warning';
                    healthText.textContent = 'Budget usage is high. Monitor next expenses closely.';
                } else if (base > 0) {
                    healthTag.classList.add('good');
                    healthTag.textContent = 'Healthy';
                    healthText.textContent = 'Budget is under control with safe remaining balance.';
                } else {
                    healthTag.classList.add('normal');
                    healthTag.textContent = 'Normal';
                    healthText.textContent = 'No budget activity yet.';
                }
            }
        }

        function drawChart(state) {
            const canvas = byId('consumptionChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            const dpr = window.devicePixelRatio || 1;
            const cssWidth = Math.max(320, canvas.clientWidth || 800);
            const cssHeight = Math.max(220, canvas.clientHeight || 280);
            canvas.width = Math.floor(cssWidth * dpr);
            canvas.height = Math.floor(cssHeight * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, cssWidth, cssHeight);

            const padding = 18;
            const leftLabel = 180;
            const rightLabel = 110;
            const chartX = padding + leftLabel;
            const chartY = padding;
            const chartW = Math.max(120, cssWidth - leftLabel - rightLabel - padding * 2);
            const chartH = cssHeight - padding * 2;
            const ms = state.milestones || [];

            if (!ms.length) {
                ctx.fillStyle = '#6b7280';
                ctx.font = '14px Poppins, sans-serif';
                ctx.fillText('Register projects with estimated budget to generate the graph.', padding, padding + 20);
                return;
            }

            const maxVal = Math.max(1, ...ms.map((m) => Number(m.allocated || 0)));
            const gap = 10;
            const barH = Math.max(16, Math.floor((chartH - gap * (ms.length - 1)) / ms.length));

            ms.forEach((m, i) => {
                const y = chartY + i * (barH + gap);
                const allocated = Number(m.allocated || 0);
                const spent = getSpentForMilestone(state, m.id);
                const allocW = (allocated / maxVal) * chartW;
                const spentW = allocated > 0 ? Math.min(allocW, (spent / allocated) * allocW) : 0;

                ctx.fillStyle = '#ecf2ff';
                ctx.fillRect(chartX, y, chartW, barH);
                ctx.fillStyle = '#3b82f6';
                ctx.fillRect(chartX, y, allocW, barH);
                ctx.fillStyle = '#16a34a';
                ctx.fillRect(chartX, y, spentW, barH);

                ctx.fillStyle = '#1f3f65';
                ctx.font = '600 12px Poppins, sans-serif';
                ctx.fillText(String(m.name || ''), padding, y + barH / 2 + 4);

                ctx.fillStyle = '#0f172a';
                ctx.font = '500 11px Poppins, sans-serif';
                ctx.fillText(currency(allocated), chartX + 6, y + barH / 2 + 4);

                ctx.fillStyle = '#0f766e';
                ctx.fillText(currency(spent), chartX + chartW + 10, y + barH / 2 + 4);
            });
        }

        function renderAll(stateArg) {
            const state = stateArg || stateCache;
            populateSourceSelect(state);
            renderSummary(state);
            drawChart(state);
        }

        async function renderAllFromServer() {
            const state = await refreshState();
            renderAll(state);
            await Promise.all([loadMilestonesPage(), loadExpensesPage()]);
        }

        async function addExpenseEntry() {
            const sourceEl = byId('expenseMilestone');
            const amountEl = byId('expenseAmount');
            const descEl = byId('expenseDesc');
            if (!sourceEl || !amountEl || !descEl) return;
            const milestoneId = sourceEl.value;
            const amount = Math.max(0, Number(amountEl.value || 0));
            const description = descEl.value.trim();
            if (!milestoneId || !amount) return;
            const selected = stateCache.milestones.find((m) => String(m.id) === String(milestoneId));
            if (!selected) {
                notify('Invalid Project', 'Selected project budget is not available.', 'error');
                return;
            }
            const allocated = Number(selected.allocated || 0);
            const spent = getSpentForMilestone(stateCache, selected.id);
            const remaining = Math.max(0, allocated - spent);
            if (amount > remaining) {
                notify('Budget Exceeded', 'Expense exceeds remaining budget for this project.', 'error');
                return;
            }
            try {
                await apiPost('add_expense', {
                    milestoneId: milestoneId,
                    amount: amount,
                    description: description
                });
                sourceEl.value = '';
                amountEl.value = '';
                descEl.value = '';
                await renderAllFromServer();
            } catch (err) {
                console.error(err);
                notify('Add Failed', 'Failed to add expense.', 'error');
            }
        }

        function exportCsv() {
            const state = stateCache;
            const rows = [];
            rows.push(['type', 'project_id', 'project_name', 'allocated', 'expense_id', 'expense_amount', 'description', 'date'].join(','));
            state.milestones.forEach((m) => {
                const expenses = state.expenses.filter((e) => e.milestoneId === m.id);
                if (!expenses.length) {
                    rows.push(['project', m.id, '"' + String(m.name).replace(/"/g, '""') + '"', m.allocated, '', '', '', ''].join(','));
                    return;
                }
                expenses.forEach((e) => {
                    rows.push(['expense', m.id, '"' + String(m.name).replace(/"/g, '""') + '"', m.allocated, e.id, e.amount, '"' + String(e.description || '').replace(/"/g, '""') + '"', e.date].join(','));
                });
            });
            const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
            const href = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = href;
            a.download = 'budget-resources-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(href);
        }

        function init() {
            if (booted) return;
            booted = true;

            const required = ['expenseForm', 'milestonesTable', 'expensesTable', 'consumptionChart'];
            for (let i = 0; i < required.length; i++) {
                if (!byId(required[i])) {
                    console.warn('Budget module missing element:', required[i]);
                    return;
                }
            }

            const expenseForm = byId('expenseForm');
            const addExpense = byId('addExpense');
            const btnExport = byId('btnExportBudget');
            const searchSources = byId('searchSources');
            const searchExpenses = byId('searchExpenses');

            if (expenseForm) {
                expenseForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    addExpenseEntry();
                }, true);
            }

            if (addExpense) addExpense.addEventListener('click', addExpenseEntry);

            if (btnExport) btnExport.addEventListener('click', exportCsv);
            let sourcesTimer = null;
            let expensesTimer = null;
            if (searchSources) {
                searchSources.addEventListener('input', function () {
                    if (sourcesTimer) clearTimeout(sourcesTimer);
                    sourcesTimer = setTimeout(async function () {
                        tableState.milestones.q = String(searchSources.value || '').trim();
                        tableState.milestones.page = 1;
                        try {
                            await loadMilestonesPage();
                        } catch (err) {
                            console.error(err);
                            notify('Load Failed', 'Failed to load project budgets.', 'error');
                        }
                    }, 250);
                });
            }
            if (searchExpenses) {
                searchExpenses.addEventListener('input', function () {
                    if (expensesTimer) clearTimeout(expensesTimer);
                    expensesTimer = setTimeout(async function () {
                        tableState.expenses.q = String(searchExpenses.value || '').trim();
                        tableState.expenses.page = 1;
                        try {
                            await loadExpensesPage();
                        } catch (err) {
                            console.error(err);
                            notify('Load Failed', 'Failed to load expenses.', 'error');
                        }
                    }, 250);
                });
            }

            document.addEventListener('click', function (event) {
                const btn = event.target.closest('button[data-br-table][data-page-dir]');
                if (!btn || btn.disabled) return;
                const tableKey = btn.getAttribute('data-br-table');
                const dir = btn.getAttribute('data-page-dir');
                if (!tableState[tableKey]) return;
                if (dir === 'prev' && tableState[tableKey].page > 1) {
                    tableState[tableKey].page -= 1;
                } else if (dir === 'next' && tableState[tableKey].page < tableState[tableKey].totalPages) {
                    tableState[tableKey].page += 1;
                } else {
                    return;
                }
                if (tableKey === 'milestones') {
                    loadMilestonesPage().catch((err) => {
                        console.error(err);
                        notify('Load Failed', 'Failed to change project budget page.', 'error');
                    });
                } else if (tableKey === 'expenses') {
                    loadExpensesPage().catch((err) => {
                        console.error(err);
                        notify('Load Failed', 'Failed to change expense page.', 'error');
                    });
                }
            });

            window.addEventListener('resize', function () {
                drawChart(stateCache);
            });

            initSectionTabs();
            renderAllFromServer().catch((err) => {
                console.error(err);
                notify('Load Failed', 'Failed to load budget data from database.', 'error');
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
