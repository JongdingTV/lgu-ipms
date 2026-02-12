// User Feedback JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const toggleSidebar = document.getElementById('toggleSidebar');
    const toggleSidebarShow = document.getElementById('toggleSidebarShow');
    const navbar = document.getElementById('navbar');
    const body = document.body;
    const toggleBtn = document.getElementById('showSidebarBtn');

    function toggleSidebarVisibility() {
        navbar.classList.toggle('hidden');
        body.classList.toggle('sidebar-hidden');
        toggleBtn.classList.toggle('show');
    }

    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebarVisibility();
        });
    }
    if (toggleSidebarShow) {
        toggleSidebarShow.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebarVisibility();
        });
    }

    // Load feedback on page load
    loadFeedbackHistory();

    // Feedback form submission
    const feedbackForm = document.getElementById('userFeedbackForm');
    const messageDiv = document.getElementById('message');

    if (feedbackForm) {
        feedbackForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(feedbackForm);
            try {
                const response = await fetch('user-feedback.php', {
                    method: 'POST',
                    body: formData
                });
                const text = await response.text();
                // Try to parse as JSON, fallback to text
                let result;
                try {
                    result = JSON.parse(text);
                } catch {
                    result = { success: text.includes('Feedback submitted!'), message: text };
                }
                if (result.success || (result.message && result.message.includes('Feedback submitted'))) {
                    showMessage('Feedback submitted!', 'success');
                    feedbackForm.reset();
                    loadFeedbackHistory();
                } else {
                    showMessage(result.message || 'Failed to submit feedback. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Error submitting feedback:', error);
                showMessage('An error occurred. Please try again.', 'error');
            }
        });
    }

    function showMessage(text, type) {
        if (!messageDiv) return;
        messageDiv.textContent = text;
        messageDiv.style.display = 'block';
        messageDiv.style.padding = '12px';
        messageDiv.style.borderRadius = '8px';
        messageDiv.style.marginTop = '15px';
        
        if (type === 'success') {
            messageDiv.style.background = '#d1fae5';
            messageDiv.style.color = '#065f46';
            messageDiv.style.border = '1px solid #a7f3d0';
        } else {
            messageDiv.style.background = '#fee2e2';
            messageDiv.style.color = '#991b1b';
            messageDiv.style.border = '1px solid #fecaca';
        }

        // Hide after 5 seconds
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
    }

    // Function to load and display feedback from database (DISABLED: No longer using feedback-api.php)
    function loadFeedbackHistory() {
        const historyContainer = document.getElementById('feedbackHistoryList');
        if (!historyContainer) return;
        historyContainer.innerHTML = '<p style="color: #999; text-align: center; padding: 20px;">Feedback history is currently unavailable. Please contact admin for your feedback status.</p>';
    }
});