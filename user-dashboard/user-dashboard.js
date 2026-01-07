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
        toggleSidebar.addEventListener('click', toggleSidebarVisibility);
    }
    if (toggleSidebarShow) {
        toggleSidebarShow.addEventListener('click', toggleSidebarVisibility);
    }

    // Feedback form submission
    const feedbackForm = document.getElementById('userFeedbackForm');
    const messageDiv = document.getElementById('message');

    feedbackForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Get form data
        const formData = new FormData(feedbackForm);
        const data = Object.fromEntries(formData.entries());

        // Simple validation
        if (!data.street || !data.barangay || !data.category || !data.feedback) {
            showMessage('Please fill in all required fields.', 'error');
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

        // Show success message
        showMessage('Thank you for your feedback! Your submission has been received and will be reviewed by our team. You can track its progress in the Project Prioritization section.', 'success');

        // Reset form
        feedbackForm.reset();
    });

    function showMessage(text, type) {
        messageDiv.textContent = text;
        messageDiv.className = `message ${type}`;
        messageDiv.style.display = 'block';

        // Hide after 5 seconds
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 5000);
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

function loadUserData() {
    // Load user info
    const userData = JSON.parse(localStorage.getItem('currentUser') || '{}');
    const usernameEl = document.querySelector('.nav-username');
    if (usernameEl && userData.firstName) {
        usernameEl.textContent = `Welcome, ${userData.firstName}`;
    }

    // Load projects data
    let projects = [];
    if (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.getProjects) {
        projects = IPMS_DATA.getProjects();
    } else {
        projects = JSON.parse(localStorage.getItem('projects') || '[]');
    }    
    // Calculate metrics
    const totalProjects = projects.length;
    const inProgressProjects = projects.filter(p => p.status === 'Approved' || p.status === 'On-hold').length;
    const completedProjects = projects.filter(p => p.status === 'Completed').length;
    const totalBudget = projects.reduce((sum, p) => sum + (parseFloat(p.budget) || 0), 0);

    // Update metrics
    document.querySelector('.metric-card:nth-child(1) .metric-value').textContent = totalProjects;
    document.querySelector('.metric-card:nth-child(2) .metric-value').textContent = inProgressProjects;
    document.querySelector('.metric-card:nth-child(3) .metric-value').textContent = completedProjects;
    document.querySelector('.metric-card:nth-child(4) .metric-value').textContent = `₱${totalBudget.toLocaleString()}`;

    // Update recent projects table
    const tbody = document.querySelector('.projects-table tbody');
    if (tbody && projects.length > 0) {
        const recentProjects = projects.slice(0, 3); // Show first 3 projects
        tbody.innerHTML = recentProjects.map(p => `
            <tr>
                <td>${p.name || 'Unnamed project'}</td>
                <td>${p.location || 'N/A'}</td>
                <td><span class="status-badge ${p.status ? p.status.toLowerCase().replace(' ', '-') : 'draft'}">${p.status || 'Draft'}</span></td>
                <td>
                    <div class="progress-small">
                        <div class="progress-fill-small" style="width: ${p.progress || 0}%;"></div>
                    </div>
                </td>
                <td>₱${(parseFloat(p.budget) || 0).toLocaleString()}</td>
            </tr>
        `).join('');
    }
}