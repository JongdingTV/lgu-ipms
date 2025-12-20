// Project Prioritization Module
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

// CRUD for Citizen Inputs
const PRIORITIZATION_KEY = 'lgu_prioritization_v1';

function loadInputs() {
    return JSON.parse(localStorage.getItem(PRIORITIZATION_KEY) || '[]');

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
        renderInputs();
    }
}

function deleteInput(id) {
    if (confirm('Are you sure you want to delete this input?')) {
        const inputs = loadInputs().filter(i => i.id !== id);
        saveInputs(inputs);
        renderInputs();
    }
}

// Filter event listeners
document.getElementById('filterType').addEventListener('change', renderInputs);
document.getElementById('filterCategory').addEventListener('change', renderInputs);
document.getElementById('filterUrgency').addEventListener('change', renderInputs);

// Initialize
document.addEventListener('DOMContentLoaded', renderInputs);