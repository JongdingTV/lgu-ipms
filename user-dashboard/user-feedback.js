document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('userFeedbackForm');
    const message = document.getElementById('message');
    const category = document.getElementById('category');

    if (!form) return;

    if (category) {
        category.addEventListener('mousedown', function () {
            category.dataset.scrollY = String(window.scrollY || 0);
        });
        category.addEventListener('change', function () {
            const y = parseInt(category.dataset.scrollY || '0', 10);
            window.requestAnimationFrame(function () {
                window.scrollTo({ top: Number.isNaN(y) ? 0 : y, behavior: 'auto' });
            });
        });
    }

    function showMessage(text, ok) {
        if (!message) return;
        message.style.display = 'block';
        message.textContent = text;
        message.style.padding = '10px 12px';
        message.style.borderRadius = '8px';
        message.style.border = ok ? '1px solid #86efac' : '1px solid #fecaca';
        message.style.background = ok ? '#dcfce7' : '#fee2e2';
        message.style.color = ok ? '#166534' : '#991b1b';
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const data = new FormData(form);

        try {
            const response = await fetch('user-feedback.php', {
                method: 'POST',
                body: data
            });

            const payload = await response.json();
            if (payload.success) {
                showMessage(payload.message || 'Feedback submitted successfully.', true);
                form.reset();
                window.setTimeout(function () {
                    window.location.reload();
                }, 700);
            } else {
                showMessage(payload.message || 'Unable to submit feedback.', false);
            }
        } catch (error) {
            console.error(error);
            showMessage('An unexpected error occurred while submitting feedback.', false);
        }
    });
});
