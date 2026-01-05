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

// CRUD for Projects
let projects = [];
let editingIndex = -1;

function saveProjects() {
    // Projects are now saved via AJAX to database
    // This function is kept for compatibility but does nothing
}

function loadProjects() {
    fetch('project_registration.php?action=load_projects')
        .then(response => response.json())
        .then(data => {
            projects = data;
            displayProjects();
        })
        .catch(error => {
            console.error('Error loading projects:', error);
            projects = [];
            displayProjects();
        });
}

function displayProjects() {
    const tbody = document.querySelector('#projectsTable tbody');
    tbody.innerHTML = '';

    if (!projects.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:#6b7280;">No projects registered yet.</td></tr>';
        return;
    }

    projects.forEach((project, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${project.code}</td>
            <td>${project.name}</td>
            <td>${project.type}</td>
            <td>${project.sector}</td>
            <td>${project.priority}</td>
            <td>${project.status}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn-edit" onclick="editProject(${index})" title="Edit Project">
                        Edit
                    </button>
                    <button class="btn-delete" onclick="deleteProject(${index})" title="Delete Project">
                        Delete
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function editProject(index) {
    const project = projects[index];
    editingIndex = index;

    // Populate form
    document.getElementById('projCode').value = project.code;
    document.getElementById('projName').value = project.name;
    document.getElementById('projType').value = project.type;
    document.getElementById('projSector').value = project.sector;
    document.getElementById('projDescription').value = project.description;
    document.getElementById('projPriority').value = project.priority;
    document.getElementById('province').value = project.province;
    document.getElementById('barangay').value = project.barangay;
    document.getElementById('projLocation').value = project.location;
    document.getElementById('startDate').value = project.start_date;
    document.getElementById('endDate').value = project.end_date;
    document.getElementById('projDuration').value = project.duration_months;
    document.getElementById('projBudget').value = project.budget;
    document.getElementById('projManager').value = project.project_manager;
    document.getElementById('status').value = project.status;

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = 'Update Project';

    // Scroll to form
    document.getElementById('projectForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function deleteProject(index) {
    const project = projects[index];
    if (confirm(`Are you sure you want to delete project "${project.name}"?\n\nThis action cannot be undone.`)) {
        const formData = new FormData();
        formData.append('action', 'delete_project');
        formData.append('id', project.id);

        fetch('project_registration.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadProjects(); // Reload projects from database
                document.getElementById('formMessage').textContent = data.message;
                document.getElementById('formMessage').style.display = 'block';
                setTimeout(() => document.getElementById('formMessage').style.display = 'none', 3000);
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the project.');
        });
    }
}

// Override the form submission to handle CRUD
document.getElementById('projectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('action', 'save_project');
    formData.append('code', document.getElementById('projCode').value);
    formData.append('name', document.getElementById('projName').value);
    formData.append('type', document.getElementById('projType').value);
    formData.append('sector', document.getElementById('projSector').value);
    formData.append('description', document.getElementById('projDescription').value);
    formData.append('priority', document.getElementById('projPriority').value);
    formData.append('province', document.getElementById('province').value);
    formData.append('barangay', document.getElementById('barangay').value);
    formData.append('location', document.getElementById('projLocation').value);
    formData.append('start_date', document.getElementById('startDate').value);
    formData.append('end_date', document.getElementById('endDate').value);
    formData.append('duration_months', document.getElementById('projDuration').value);
    formData.append('budget', document.getElementById('projBudget').value);
    formData.append('project_manager', document.getElementById('projManager').value);
    formData.append('status', document.getElementById('status').value);
    
    if (editingIndex >= 0) {
        formData.append('id', projects[editingIndex].id);
    }

    fetch('project_registration.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadProjects(); // Reload projects from database
            this.reset();
            editingIndex = -1;
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = 'Create Project';
            
            document.getElementById('formMessage').textContent = data.message;
            document.getElementById('formMessage').style.display = 'block';
            setTimeout(() => document.getElementById('formMessage').style.display = 'none', 3000);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the project.');
    });
});

// Load projects on page load
document.addEventListener('DOMContentLoaded', loadProjects);