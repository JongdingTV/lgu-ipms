(function () {
    var path = (window.location.pathname || '').replace(/\\/g, '/');
    if (!path.endsWith('/admin/project_registration.php')) {
        return;
    }

    var formMsg = document.getElementById('formMessage');
    var budgetInput = document.getElementById('projBudget');
    var maxBudgetText = document.body.textContent.match(/Maximum allowed budget:\s*PHP\s*([\d,\.]+)/i);
    var maxBudget = maxBudgetText ? parseFloat(maxBudgetText[1].replace(/,/g, '')) : null;

    function showMessage(message, isError) {
        if (!formMsg) return;
        formMsg.textContent = message;
        formMsg.style.display = 'block';
        formMsg.style.color = isError ? '#dc2626' : '#15803d';
        window.setTimeout(function () {
            formMsg.style.display = 'none';
        }, 3800);
    }

    if (budgetInput && maxBudget) {
        budgetInput.addEventListener('input', function () {
            var value = parseFloat(String(budgetInput.value || '0'));
            if (!isFinite(value)) return;
            if (value > maxBudget) {
                budgetInput.setCustomValidity('Budget cannot exceed PHP ' + maxBudget.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            } else {
                budgetInput.setCustomValidity('');
            }
        });
    }

    var params = new URLSearchParams(window.location.search);
    var saved = params.get('saved');
    var error = params.get('error');
    if (saved === '1') {
        showMessage(params.get('msg') || 'Project has been added successfully.', false);
    }
    if (error) {
        showMessage(decodeURIComponent(error), true);
    }
})();
