document.getElementById('toggleSidebar').addEventListener('click', function(e) {
    e.preventDefault();
    
    const navbar = document.getElementById('navbar');
    const body = document.body;
    const toggleBtn = document.getElementById('showSidebarBtn');
    
    navbar.classList.toggle('hidden');
    body.classList.toggle('sidebar-hidden');
    toggleBtn.classList.toggle('show');
});

document.getElementById('toggleSidebarShow').addEventListener('click', function(e) {
    e.preventDefault();
    
    const navbar = document.getElementById('navbar');
    const body = document.body;
    const toggleBtn = document.getElementById('showSidebarBtn');
    
    navbar.classList.toggle('hidden');
    body.classList.toggle('sidebar-hidden');
    toggleBtn.classList.toggle('show');
});