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
            formData.append('action', 'submit_feedback');

            try {
                const response = await fetch('feedback-api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showMessage(result.message, 'success');
                    
                    // Also save to localStorage for backward compatibility with prioritization module
                    if (result.prioritization_data) {
                        const PRIORITIZATION_KEY = 'lgu_prioritization_v1';
                        const existingInputs = JSON.parse(localStorage.getItem(PRIORITIZATION_KEY) || '[]');
                        existingInputs.push(result.prioritization_data);
                        localStorage.setItem(PRIORITIZATION_KEY, JSON.stringify(existingInputs));
                    }
                    
                    // Reset form
                    feedbackForm.reset();
                    
                    // Reload feedback list
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

    // Function to load and display feedback from database
    async function loadFeedbackHistory() {
        const historyContainer = document.getElementById('feedbackHistoryList');
        if (!historyContainer) return;

        try {
            const response = await fetch('feedback-api.php?action=get_user_feedback');
            const result = await response.json();

            if (!result.success || !result.feedbacks || result.feedbacks.length === 0) {
                historyContainer.innerHTML = '<p style="color: #999; text-align: center; padding: 20px;">No feedback submitted yet. Submit your first feedback using the form below.</p>';
                return;
            }

            const categoryNames = {
                'transportation': 'Transportation',
                'energy': 'Energy',
                'water-waste': 'Water & Waste',
                'social-infrastructure': 'Social Infrastructure',
                'public-buildings': 'Public Buildings'
            };

            const statusColors = {
                'Pending': { bg: '#fef3c7', color: '#92400e' },
                'Under Review': { bg: '#dbeafe', color: '#1e40af' },
                'In Progress': { bg: '#e0e7ff', color: '#4338ca' },
                'Resolved': { bg: '#d1fae5', color: '#065f46' },
                'Closed': { bg: '#f3f4f6', color: '#374151' }
            };

            const html = result.feedbacks.map(item => {
                const date = new Date(item.created_at);
                const formattedDate = date.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                const statusStyle = statusColors[item.status] || statusColors['Pending'];
                const location = `${item.street || ''}, ${item.barangay || ''}`.trim().replace(/^,|,$/g, '');

                return `
                    <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 12px; background: #f9fafb;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <span style="display: inline-block; padding: 4px 12px; background: #dbeafe; color: #1e40af; border-radius: 20px; font-size: 0.8em; font-weight: 600; margin-right: 8px;">
                                    ${categoryNames[item.category] || item.category}
                                </span>
                                <span style="display: inline-block; padding: 4px 12px; background: ${statusStyle.bg}; color: ${statusStyle.color}; border-radius: 20px; font-size: 0.8em; font-weight: 600;">
                                    ${item.status}
                                </span>
                            </div>
                            <span style="font-size: 0.85em; color: #666;">${formattedDate}</span>
                        </div>
                        ${location ? `
                            <div style="margin-bottom: 8px;">
                                <strong style="color: #374151; font-size: 0.9em;">Location:</strong> 
                                <span style="color: #6b7280; font-size: 0.9em;">${location}</span>
                            </div>
                        ` : ''}
                        <div style="background: white; padding: 12px; border-radius: 6px; border-left: 3px solid #2563eb; margin-bottom: 8px;">
                            <p style="margin: 0; color: #374151; font-size: 0.9em; line-height: 1.5;">${item.feedback || 'No feedback provided'}</p>
                        </div>
                        ${item.photo_path ? `
                            <div style="margin-bottom: 8px;">
                                <img src="../${item.photo_path}" alt="Feedback attachment" style="max-width: 200px; border-radius: 6px; border: 1px solid #e5e7eb;">
                            </div>
                        ` : ''}
                        ${item.admin_response ? `
                            <div style="background: #eff6ff; padding: 12px; border-radius: 6px; border-left: 3px solid #3b82f6;">
                                <strong style="color: #1e40af; font-size: 0.85em; display: block; margin-bottom: 4px;">Admin Response:</strong>
                                <p style="margin: 0; color: #374151; font-size: 0.9em; line-height: 1.5;">${item.admin_response}</p>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');

            historyContainer.innerHTML = html;
        } catch (error) {
            console.error('Error loading feedback:', error);
            historyContainer.innerHTML = '<p style="color: #ef4444; text-align: center; padding: 20px;">Failed to load feedback. Please refresh the page.</p>';
        }
    }
});