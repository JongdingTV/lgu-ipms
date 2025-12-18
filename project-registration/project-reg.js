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
let projects = JSON.parse(localStorage.getItem('projects')) || [];
let editingIndex = -1;

function saveProjects() {
    localStorage.setItem('projects', JSON.stringify(projects));
}

function displayProjects() {
    const tbody = document.querySelector('#projectsTable tbody');
    tbody.innerHTML = '';
    
    projects.forEach((project, index) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${project.projCode}</td>
            <td>${project.projName}</td>
            <td>${project.projType}</td>
            <td>${project.projSector}</td>
            <td>${project.projPriority}</td>
            <td>${project.status || 'Active'}</td>
            <td>
                <button onclick="editProject(${index})">Edit</button>
                <button onclick="deleteProject(${index})">Delete</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function editProject(index) {
    const project = projects[index];
    editingIndex = index;
    
    // Populate form
    document.getElementById('projCode').value = project.projCode;
    document.getElementById('projName').value = project.projName;
    document.getElementById('projType').value = project.projType;
    document.getElementById('projSector').value = project.projSector;
    document.getElementById('projDescription').value = project.projDescription;
    document.getElementById('projPriority').value = project.projPriority;
    document.getElementById('province').value = project.province;
    document.getElementById('barangay').value = project.barangay;
    document.getElementById('startDate').value = project.startDate;
    document.getElementById('endDate').value = project.endDate;
    document.getElementById('budget').value = project.budget;
    document.getElementById('contractor').value = project.contractor;
    
    const submitBtn = document.querySelector('#projectForm button[type="submit"]');
    submitBtn.textContent = 'Update Project';
    submitBtn.style.background = '#f59e0b';
}

function deleteProject(index) {
    if (confirm('Are you sure you want to delete this project?')) {
        projects.splice(index, 1);
        saveProjects();
        displayProjects();
    }
}

// Override the form submission to handle CRUD
document.getElementById('projectForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const project = {
        projCode: document.getElementById('projCode').value,
        projName: document.getElementById('projName').value,
        projType: document.getElementById('projType').value,
        projSector: document.getElementById('projSector').value,
        projDescription: document.getElementById('projDescription').value,
        projPriority: document.getElementById('projPriority').value,
        province: document.getElementById('province').value,
        barangay: document.getElementById('barangay').value,
        startDate: document.getElementById('startDate').value,
        endDate: document.getElementById('endDate').value,
        budget: document.getElementById('budget').value,
        contractor: document.getElementById('contractor').value,
        status: 'Active'
    };
    
    if (editingIndex >= 0) {
        projects[editingIndex] = project;
        editingIndex = -1;
    } else {
        projects.push(project);
    }
    
    saveProjects();
    displayProjects();
    this.reset();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.textContent = 'Create Project';
    submitBtn.style.background = '';
    
    document.getElementById('formMessage').textContent = 'Project saved successfully!';
    document.getElementById('formMessage').style.display = 'block';
    setTimeout(() => document.getElementById('formMessage').style.display = 'none', 3000);
});

// Load projects on page load
document.addEventListener('DOMContentLoaded', displayProjects);