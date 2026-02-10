// Render feedback review table from localStorage
function renderUserFeedbackTable() {
    const feedbacks = JSON.parse(localStorage.getItem('lgu_prioritization_v1') || '[]');
    const tbody = document.querySelector('#userFeedbackTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    if (!feedbacks.length) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;color:#999;">No feedback submitted yet</td></tr>';
        return;
    }
    feedbacks.forEach(fb => {
        tbody.innerHTML += `<tr>
            <td>${new Date(fb.date).toLocaleDateString()}</td>
            <td>${fb.subject}</td>
            <td>${fb.category}</td>
            <td><span class="status-badge">${fb.status}</span></td>
        </tr>`;
    });
}

document.addEventListener('DOMContentLoaded', renderUserFeedbackTable);
// User Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality (shared)
    const sidebar = document.getElementById('sidebar');
    const toggleSidebarBtn = document.getElementById('sidebarToggle');
    const showSidebarBtn = document.getElementById('showSidebarBtn');
    const toggleSidebarShow = document.getElementById('toggleSidebarShow');
    const body = document.body;

    function toggleSidebarVisibility(e) {
        if (e) e.preventDefault();
        sidebar.classList.toggle('active');
        body.classList.toggle('sidebar-hidden');
        if (showSidebarBtn) showSidebarBtn.classList.toggle('show');
    }

    if (toggleSidebarBtn) {
        toggleSidebarBtn.addEventListener('click', toggleSidebarVisibility);
    }
    if (toggleSidebarShow) {
        toggleSidebarShow.addEventListener('click', toggleSidebarVisibility);
    }

    // Feedback form submission
    const feedbackForm = document.getElementById('userFeedbackForm');
    const messageDiv = document.getElementById('message');

    if (feedbackForm) {
        feedbackForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Get form data
            const formData = new FormData(feedbackForm);
            const data = Object.fromEntries(formData.entries());

            // Simple validation
            if (!data.street || !data.barangay || !data.category || !data.feedback) {
                if (typeof showMessage === 'function' && messageDiv) {
                    showMessage('Please fill in all required fields.', 'error');
                }
                return;
            }

            // Map user dashboard fields to prioritization module format
            const prioritizationData = {
                id: 'input_' + Math.random().toString(36).substr(2, 9),
                name: 'Anonymous User', // Since user dashboard doesn't have name field
                email: '', // User dashboard doesn't collect email
                type: 'Suggestion', // Default to suggestion since it's feedback
                subject: getCategorySubject(data.category),
                description: data.feedback,
                category: mapCategoryToPrioritization(data.category),
                location: `${data.street}, ${data.barangay}`,
                urgency: 'Medium', // Default urgency
                status: 'Pending',
                date: new Date().toISOString()
            };

            // Save to the same localStorage that project-prioritization module uses
            const PRIORITIZATION_KEY = 'lgu_prioritization_v1';
            const existingInputs = JSON.parse(localStorage.getItem(PRIORITIZATION_KEY) || '[]');
            existingInputs.push(prioritizationData);
            localStorage.setItem(PRIORITIZATION_KEY, JSON.stringify(existingInputs));

            // Also save to user's feedback history
            const USER_FEEDBACK_KEY = 'user_feedback_history';
            const userFeedback = JSON.parse(localStorage.getItem(USER_FEEDBACK_KEY) || '[]');
            userFeedback.push({
                id: prioritizationData.id,
                category: data.category,
                location: `${data.street}, ${data.barangay}`,
                feedback: data.feedback,
                date: new Date().toISOString(),
                status: 'Pending'
            });
            localStorage.setItem(USER_FEEDBACK_KEY, JSON.stringify(userFeedback));

            // Show success message
            if (typeof showMessage === 'function' && messageDiv) {
                showMessage('Thank you for your feedback! Your submission has been received and will be reviewed by our team.', 'success');
            }

            // Reset form
            feedbackForm.reset();

            // Reload feedback list
            loadFeedbackHistory();
        });
    }

    // Function to load and display feedback submissions
    function loadFeedbackHistory() {
        const historyContainer = document.getElementById('feedbackHistoryList');
        if (!historyContainer) return;

        const USER_FEEDBACK_KEY = 'user_feedback_history';
        const userFeedback = JSON.parse(localStorage.getItem(USER_FEEDBACK_KEY) || '[]');

        if (userFeedback.length === 0) {
            historyContainer.innerHTML = '<p style="color: #999; text-align: center; padding: 20px;">No feedback submitted yet. Submit your first feedback using the form below.</p>';
            return;
        }

        // Sort by date (newest first)
        userFeedback.sort((a, b) => new Date(b.date) - new Date(a.date));

        const categoryNames = {
            'transportation': 'Transportation',
            'energy': 'Energy',
            'water-waste': 'Water & Waste',
            'social-infrastructure': 'Social Infrastructure',
            'public-buildings': 'Public Buildings'
        };

        const html = userFeedback.map(item => {
            const date = new Date(item.date);
            const formattedDate = date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            return `
                <div style="border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 12px; background: #f9fafb;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                        <div>
                            <span style="display: inline-block; padding: 4px 12px; background: #dbeafe; color: #1e40af; border-radius: 20px; font-size: 0.8em; font-weight: 600; margin-right: 8px;">
                                ${categoryNames[item.category] || item.category}
                            </span>
                            <span style="display: inline-block; padding: 4px 12px; background: #fef3c7; color: #92400e; border-radius: 20px; font-size: 0.8em; font-weight: 600;">
                                ${item.status}
                            </span>
                        </div>
                        <span style="font-size: 0.85em; color: #666;">${formattedDate}</span>
                    </div>
                    <div style="margin-bottom: 8px;">
                        <strong style="color: #374151; font-size: 0.9em;">Location:</strong> 
                        <span style="color: #6b7280; font-size: 0.9em;">${item.location || 'N/A'}</span>
                    </div>
                    <div style="background: white; padding: 12px; border-radius: 6px; border-left: 3px solid #2563eb;">
                        <p style="margin: 0; color: #374151; font-size: 0.9em; line-height: 1.5;">${item.feedback || 'No feedback provided'}</p>
                    </div>
                </div>
            `;
        }).join('');

        historyContainer.innerHTML = html;
    }

    function showMessage(text, type) {
        if (!messageDiv) return;
        messageDiv.textContent = text;
        messageDiv.className = `message ${type}`;
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

    // Load feedback list on page load
    if (document.getElementById('feedbackHistoryList')) {
        loadFeedbackHistory();
    }

    // Helper functions for mapping user dashboard data to prioritization format
    function getCategorySubject(category) {
        const subjects = {
            'transportation': 'Transportation Infrastructure Feedback',
            'energy': 'Energy Infrastructure Feedback',
            'water-waste': 'Water & Waste Management Feedback',
            'social-infrastructure': 'Social Infrastructure Feedback',
            'public-buildings': 'Public Buildings Feedback'
        };
        return subjects[category] || 'Infrastructure Feedback';
    }

    function mapCategoryToPrioritization(category) {
        const mapping = {
            'transportation': 'Roads',
            'energy': 'Electricity',
            'water-waste': 'Water',
            'social-infrastructure': 'Buildings',
            'public-buildings': 'Buildings'
        };
        return mapping[category] || 'Other';
    }

    // Load user data (placeholder)
    loadUserData();
});

async function loadUserData() {
    // Load user info
    const userData = JSON.parse(localStorage.getItem('currentUser') || '{}');
    const usernameEl = document.querySelector('.nav-username');
    if (usernameEl && userData.firstName) {
        usernameEl.textContent = `Welcome, ${userData.firstName}`;
    }

    // Load projects data from database
    let projects = [];
    try {
        const response = await fetch(getApiUrl('progress-monitoring/progress_monitoring.php?action=load_projects'));
        if (response.ok) {
            projects = await response.json();
        }
    } catch (error) {
        console.error('Error loading projects:', error);
        // Fallback to localStorage
        if (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.getProjects) {
            projects = IPMS_DATA.getProjects();
        } else {
            projects = JSON.parse(localStorage.getItem('projects') || '[]');
        }
    }
    
    // Calculate metrics
    const totalProjects = projects.length;
    const inProgressProjects = projects.filter(p => 
        p.status === 'Approved' || p.status === 'For Approval' || p.status === 'On-hold'
    ).length;
    const completedProjects = projects.filter(p => p.status === 'Completed').length;
    const totalBudget = projects.reduce((sum, p) => sum + (parseFloat(p.budget) || 0), 0);

    // Update metrics
    const metricCards = document.querySelectorAll('.metric-card .metric-value');
    if (metricCards[0]) metricCards[0].textContent = totalProjects;
    if (metricCards[1]) metricCards[1].textContent = inProgressProjects;
    if (metricCards[2]) metricCards[2].textContent = completedProjects;
    if (metricCards[3]) metricCards[3].textContent = `₱${totalBudget.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

    // Update status distribution chart
    if (totalProjects > 0) {
        const completedPercent = Math.round((completedProjects / totalProjects) * 100);
        const inProgressPercent = Math.round((inProgressProjects / totalProjects) * 100);
        const otherPercent = 100 - completedPercent - inProgressPercent;
        
        const completedEl = document.getElementById('completedPercent');
        const inProgressEl = document.getElementById('inProgressPercent');
        const otherEl = document.getElementById('otherPercent');
        
        if (completedEl) completedEl.textContent = `Completed: ${completedPercent}%`;
        if (inProgressEl) inProgressEl.textContent = `In Progress: ${inProgressPercent}%`;
        if (otherEl) otherEl.textContent = `Other: ${otherPercent}%`;
    }

    // Update budget utilization (simplified - showing percentage based on completed projects)
    if (totalProjects > 0) {
        const budgetUtilization = Math.round((completedProjects / totalProjects) * 100);
        const budgetFillEl = document.getElementById('budgetProgressFill');
        const budgetTextEl = document.getElementById('budgetUtilizationText');
        
        if (budgetFillEl) budgetFillEl.style.width = budgetUtilization + '%';
        if (budgetTextEl) budgetTextEl.textContent = `Budget utilization: ${budgetUtilization}% Used`;
    }

    // Update recent projects table
    const tbody = document.querySelector('.projects-table tbody');
    if (tbody) {
        if (projects.length > 0) {
            const recentProjects = projects.slice(0, 5); // Show first 5 projects
            tbody.innerHTML = recentProjects.map(p => {
                const statusClass = (p.status || 'draft').toLowerCase().replace(/\s+/g, '');
                const progress = p.progress || p.percent_complete || 0;
                return `
                <tr>
                    <td>${p.name || 'Unnamed project'}</td>
                    <td>${p.location || 'N/A'}</td>
                    <td><span class="status-badge ${statusClass}">${p.status || 'Draft'}</span></td>
                    <td>
                        <div class="progress-small">
                            <div class="progress-fill-small" style="width: ${progress}%;"></div>
                        </div>
                    </td>
                    <td>₱${(parseFloat(p.budget) || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                </tr>
            `;
            }).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #999;">No projects registered yet</td></tr>';
        }
    }

    // Update quick stats
    if (projects.length > 0) {
        // Calculate average duration
        const validDurations = projects.filter(p => p.duration_months || p.durationMonths).map(p => 
            parseInt(p.duration_months || p.durationMonths)
        );
        const avgDuration = validDurations.length > 0 
            ? Math.round(validDurations.reduce((a, b) => a + b, 0) / validDurations.length)
            : 0;
        
        // Calculate on-time delivery rate (completed / total * 100)
        const onTimeRate = totalProjects > 0 ? Math.round((completedProjects / totalProjects) * 100) : 0;
        
        const statItems = document.querySelectorAll('.stat-item p');
        if (statItems[0]) statItems[0].textContent = avgDuration + ' months';
        if (statItems[1]) statItems[1].textContent = onTimeRate + '%';
        if (statItems[2]) statItems[2].textContent = '0%'; // Budget variance would need more data
    }
}