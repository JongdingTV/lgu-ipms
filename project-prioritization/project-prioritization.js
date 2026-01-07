// Project Prioritization Module
console.log('project-prioritization.js loaded');

// All feedback table manipulation code is disabled to allow PHP-rendered feedback to display from the database.
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