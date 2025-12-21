// User Settings JavaScript

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

    // Load user data - handled by PHP now
    // loadUserData();

    // Settings form submission - handled by PHP now
    // const settingsForm = document.getElementById('userSettingsForm');
    // const messageDiv = document.getElementById('settingsMessage');

    // settingsForm.addEventListener('submit', function(e) {
    //     e.preventDefault();

    //     const formData = new FormData(settingsForm);
    //     const data = Object.fromEntries(formData.entries());

    //     // Save to localStorage
    //     localStorage.setItem('currentUser', JSON.stringify(data));

    //     // Show success message
    //     showMessage('Your information has been updated successfully!', 'success');
    // });

    function showMessage(text, type) {
        messageDiv.textContent = text;
        messageDiv.className = `message ${type}`;
        messageDiv.style.display = 'block';

        // Hide after 3 seconds
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 3000);
    }
});

function loadUserData() {
    const userData = JSON.parse(localStorage.getItem('currentUser') || '{}');

    // Populate form fields
    Object.keys(userData).forEach(key => {
        const element = document.getElementById(key);
        if (element) {
            element.value = userData[key];
        }
    });
}