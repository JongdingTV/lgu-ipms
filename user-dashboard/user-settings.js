document.addEventListener('DOMContentLoaded', function () {
    const newPassword = document.getElementById('newPassword');
    const strength = document.getElementById('passwordStrength');

    if (!newPassword || !strength) return;

    function scorePassword(value) {
        let score = 0;
        if (value.length >= 8) score += 1;
        if (/[A-Z]/.test(value)) score += 1;
        if (/[0-9]/.test(value)) score += 1;
        if (/[!@#$%^&*(),.?":{}|<>]/.test(value)) score += 1;
        return score;
    }

    newPassword.addEventListener('input', function () {
        const score = scorePassword(newPassword.value);
        if (score <= 1) {
            strength.textContent = 'Password strength: Weak';
            strength.style.color = '#b91c1c';
        } else if (score <= 3) {
            strength.textContent = 'Password strength: Medium';
            strength.style.color = '#b45309';
        } else {
            strength.textContent = 'Password strength: Strong';
            strength.style.color = '#166534';
        }
    });
});
