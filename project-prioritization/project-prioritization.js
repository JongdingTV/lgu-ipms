// Project Prioritization Module
console.log('project-prioritization.js loaded');

const sidebarToggle = document.getElementById('toggleSidebar');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function(e) {
        e.preventDefault();
        
        const navbar = document.getElementById('navbar');
        const body = document.body;
        const toggleBtn = document.getElementById('showSidebarBtn');
        
        navbar.classList.toggle('hidden');
        body.classList.toggle('sidebar-hidden');
        toggleBtn.classList.toggle('show');
    });
}

const sidebarShow = document.getElementById('toggleSidebarShow');
if (sidebarShow) {
    sidebarShow.addEventListener('click', function(e) {
        e.preventDefault();
        
        const navbar = document.getElementById('navbar');
        const body = document.body;
        const toggleBtn = document.getElementById('showSidebarBtn');
        
        navbar.classList.toggle('hidden');
        body.classList.toggle('sidebar-hidden');
        toggleBtn.classList.toggle('show');
    });
}

// Load projects from database
let allProjects = [];

function loadProjectsFromDatabase() {
    console.log('Loading projects from database...');
    fetch('project-prioritization.php?action=load_projects')
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) throw new Error('Failed to load projects');
            return response.json();
        })
        .then(projects => {
            console.log('Projects loaded:', projects);
            allProjects = projects;
            renderProjects();
        })
        .catch(error => {
            console.error('Error loading projects:', error);
            allProjects = [];
            renderProjects();
        });
}

function renderProjects() {
    const tbody = document.querySelector('#inputsTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    
    if (!allProjects.length) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px;">No projects available</td></tr>';
        return;
    }
    
    // Load feedback items from localStorage
    const feedbacks = JSON.parse(localStorage.getItem('lgu_prioritization_v1') || '[]');
    feedbacks.forEach(input => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${new Date(input.date).toLocaleDateString()}</td>
            <td>${input.name}</td>
            <td><span class="badge type-${input.type}">${input.type}</span></td>
            <td>${input.subject}</td>
            <td>${input.category}</td>
            <td>${input.location}</td>
            <td><span class="badge urgency-${input.urgency}">${input.urgency}</span></td>
            <td><span class="badge status-${input.status.replace(/\s+/g, '\ ')}">${input.status}</span></td>
            <td>
                <button onclick="alert('Feedback: ${input.description}')">View</button>
                <button onclick="updateStatus('${input.id}', 'Under Review')">Review</button>
                <button onclick="updateStatus('${input.id}', 'Addressed')">Address</button>
                <button onclick="deleteInput('${input.id}')">Delete</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    updateSummary(feedbacks);
}

function updateProjectSummary() {
    const total = allProjects.length;
    const critical = allProjects.filter(p => p.priority === 'Critical').length;
    const high = allProjects.filter(p => p.priority === 'High').length;
    const pending = allProjects.filter(p => p.status === 'Pending').length;
    
    document.getElementById('totalInputs').textContent = total;
    document.getElementById('criticalInputs').textContent = critical;
    document.getElementById('highInputs').textContent = high;
    document.getElementById('pendingInputs').textContent = pending;
}

// CRUD for Citizen Inputs
const PRIORITIZATION_KEY = 'lgu_prioritization_v1';

function loadInputs() {
    return JSON.parse(localStorage.getItem(PRIORITIZATION_KEY) || '[]');
}

function saveInputs(inputs) {
    localStorage.setItem(PRIORITIZATION_KEY, JSON.stringify(inputs));
}

function uid() {
    return 'input_' + Math.random().toString(36).substr(2, 9);
}

function renderInputs() {
    const inputs = loadInputs();
    const tbody = document.querySelector('#inputsTable tbody');
    tbody.innerHTML = '';
    
    const typeFilter = document.getElementById('filterType').value;
    const categoryFilter = document.getElementById('filterCategory').value;
    const urgencyFilter = document.getElementById('filterUrgency').value;
    
    const filtered = inputs.filter(input => {
        if (typeFilter && input.type !== typeFilter) return false;
        if (categoryFilter && input.category !== categoryFilter) return false;
        if (urgencyFilter && input.urgency !== urgencyFilter) return false;
        return true;
    });
    
    filtered.forEach(input => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${new Date(input.date).toLocaleDateString()}</td>
            <td>${input.name}</td>
            <td><span class="badge type-${input.type}">${input.type}</span></td>
            <td>${input.subject}</td>
            <td>${input.category}</td>
            <td>${input.location}</td>
            <td><span class="badge urgency-${input.urgency}">${input.urgency}</span></td>
            <td><span class="badge status-${input.status.replace(/\s+/g, '\\ ')}">${input.status}</span></td>
            <td>
                <button onclick="viewInput('${input.id}')">View</button>
                <button onclick="updateStatus('${input.id}', 'Under Review')">Review</button>
                <button onclick="updateStatus('${input.id}', 'Addressed')">Address</button>
                <button onclick="deleteInput('${input.id}')">Delete</button>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    updateSummary(inputs);
}

function updateSummary(inputs) {
    document.getElementById('totalInputs').textContent = inputs.length;
    document.getElementById('criticalInputs').textContent = inputs.filter(i => i.urgency === 'Critical').length;
    document.getElementById('highInputs').textContent = inputs.filter(i => i.urgency === 'High').length;
    document.getElementById('pendingInputs').textContent = inputs.filter(i => i.status === 'Pending').length;
}

function viewInput(id) {
    const inputs = loadInputs();
    const input = inputs.find(i => i.id === id);
    if (input) {
        alert(`Name: ${input.name}\nEmail: ${input.email || 'N/A'}\nType: ${input.type}\nSubject: ${input.subject}\nDescription: ${input.description}\nCategory: ${input.category}\nLocation: ${input.location}\nUrgency: ${input.urgency}\nStatus: ${input.status}\nDate: ${new Date(input.date).toLocaleString()}`);
    }
}

function updateStatus(id, status) {
    const inputs = loadInputs();
    const input = inputs.find(i => i.id === id);
    if (input) {
        input.status = status;
        saveInputs(inputs);
        renderProjects();
    }
}

function deleteInput(id) {
    if (confirm('Are you sure you want to delete this input?')) {
        const inputs = loadInputs().filter(i => i.id !== id);
        saveInputs(inputs);
        renderInputs();
    }
}

// Filter event listeners - safely check if elements exist
['filterType', 'filterCategory', 'filterUrgency'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('change', renderInputs);
    }
});

// Initialize - load projects from database first
document.addEventListener('DOMContentLoaded', loadProjectsFromDatabase);