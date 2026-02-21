(function () {
    const form = document.getElementById('engineerRegistrationForm');
    if (!form) return;

    const maxSizeBytes = 5 * 1024 * 1024;
    const allowedMime = ['application/pdf', 'image/jpeg', 'image/png'];

    function showClientError(message) {
        let box = document.getElementById('engineerClientError');
        if (!box) {
            box = document.createElement('div');
            box.id = 'engineerClientError';
            box.className = 'engineer-alert error';
            form.parentNode.insertBefore(box, form);
        }
        box.textContent = message;
        box.style.display = 'block';
        box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function clearClientError() {
        const box = document.getElementById('engineerClientError');
        if (box) box.style.display = 'none';
    }

    function isFutureDate(value) {
        if (!value) return false;
        const entered = new Date(value + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return entered.getTime() > today.getTime();
    }

    function validateFileInput(input, required) {
        if (!input) return null;
        const files = Array.from(input.files || []);
        if (required && files.length === 0) {
            return input.name + ' is required.';
        }
        for (const file of files) {
            if (file.size > maxSizeBytes) {
                return file.name + ' exceeds 5MB limit.';
            }
            const mime = String(file.type || '').toLowerCase();
            if (!allowedMime.includes(mime)) {
                return file.name + ' has invalid file type. Allowed: PDF/JPG/PNG.';
            }
        }
        return null;
    }

    form.addEventListener('submit', function (event) {
        clearClientError();

        const email = form.querySelector('input[name="email"]');
        const pwd = form.querySelector('input[name="password"]');
        const confirmPwd = form.querySelector('input[name="confirm_password"]');
        const expiry = form.querySelector('input[name="license_expiry_date"]');
        const prcFile = form.querySelector('input[name="prc_license_file"]');
        const resumeFile = form.querySelector('input[name="resume_file"]');
        const govIdFile = form.querySelector('input[name="government_id_file"]');
        const certsFile = form.querySelector('input[name="certificates_files[]"]');

        if (!form.checkValidity()) {
            event.preventDefault();
            showClientError('Please fill all required fields.');
            form.reportValidity();
            return;
        }

        const emailVal = String(email.value || '').trim();
        const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal);
        if (!emailOk) {
            event.preventDefault();
            showClientError('Please enter a valid email format.');
            return;
        }

        if (String(pwd.value || '').length < 8) {
            event.preventDefault();
            showClientError('Password must be at least 8 characters.');
            return;
        }

        if (pwd.value !== confirmPwd.value) {
            event.preventDefault();
            showClientError('Password and Confirm Password do not match.');
            return;
        }

        if (!isFutureDate(expiry.value)) {
            event.preventDefault();
            showClientError('License expiry date must be in the future.');
            return;
        }

        const fileChecks = [
            validateFileInput(prcFile, true),
            validateFileInput(resumeFile, true),
            validateFileInput(govIdFile, false),
            validateFileInput(certsFile, false),
        ].filter(Boolean);

        if (fileChecks.length > 0) {
            event.preventDefault();
            showClientError(fileChecks[0]);
        }
    });
})();

