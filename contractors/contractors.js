console.log('contractors.js loaded');

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

// Load projects for contractor assignment
let allProjects = [];

function loadProjectsFromDatabase() {
    console.log('Loading projects from database...');
    fetch('contractors.php?action=load_projects')
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) throw new Error('Failed to load projects');
            return response.json();
        })
        .then(projects => {
            console.log('Projects loaded:', projects);
            allProjects = projects;
            populateProjectDropdown();
        })
        .catch(error => {
            console.error('Error loading projects:', error);
            allProjects = [];
        });
}

function populateProjectDropdown() {
    const select = document.getElementById('projectSelect');
    if (select) {
        select.innerHTML = '<option value="">Select a project</option>';
        allProjects.forEach(project => {
            const option = document.createElement('option');
            option.value = project.id;
            option.textContent = (project.code || '') + ' - ' + (project.name || '');
            select.appendChild(option);
        });
    }
    
    // Also display projects in the table
    const tbody = document.querySelector('#projectsTable tbody');
    if (tbody) {
        tbody.innerHTML = '';
        if (!allProjects.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">No projects available</td></tr>';
            return;
        }
        
        allProjects.forEach(project => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${project.code || ''}</td>
                <td>${project.name || ''}</td>
                <td>${project.type || ''}</td>
                <td>${project.sector || ''}</td>
                <td>${project.status || 'Draft'}</td>
            `;
            tbody.appendChild(tr);
        });
    }
}

// CRUD for Contractors
let contractors = [];
let editingId = null;

// Helper function to get current contractors
async function loadContractorsData() {
    try {
        const response = await fetch('contractors-api.php');
        if (response.ok) {
            return await response.json();
        }
        return [];
    } catch (error) {
        console.error('Error loading contractors data:', error);
        return [];
    }
}

async function loadContractors() {
    try {
        const response = await fetch('contractors-api.php');
        if (response.ok) {
            contractors = await response.json();
            renderContractors();
        } else {
            const msg = document.getElementById('formMessage');
            if (msg) {
                msg.style.color = '#d00';
                msg.textContent = 'Failed to load contractors.';
                msg.style.display = 'block';
            }
            console.error('Failed to load contractors');
        }
    } catch (error) {
        const msg = document.getElementById('formMessage');
        if (msg) {
            msg.style.color = '#d00';
            msg.textContent = 'Error loading contractors: ' + error.message;
            msg.style.display = 'block';
        }
        console.error('Error loading contractors:', error);
    }
}

function renderContractors() {
    const tbody = document.querySelector('#contractorsTable tbody');
    tbody.innerHTML = '';
    if (!contractors.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#6b7280;">No contractors registered yet.</td></tr>';
        return;
    }
    contractors.forEach((c) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${c.company || ''}</td>
            <td>${c.license || ''}</td>
            <td>${c.email || c.phone || ''}</td>
            <td>${c.status || 'Active'}</td>
            <td>${c.rating || 'N/A'}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn-edit" data-id="${c.id}">Edit</button>
                    <button class="btn-delete" data-id="${c.id}">Delete</button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function editContractor(id) {
    const c = contractors.find(ctr => ctr.id == id);
    if (!c) return;
    editingId = id;
    document.getElementById('ctrCompany').value = c.company || '';
    document.getElementById('ctrOwner').value = c.owner || '';
    document.getElementById('ctrLicense').value = c.license || '';
    document.getElementById('ctrEmail').value = c.email || '';
    document.getElementById('ctrPhone').value = c.phone || '';
    document.getElementById('ctrAddress').value = c.address || '';
    document.getElementById('ctrSpecialization').value = c.specialization || '';
    document.getElementById('ctrExperience').value = c.experience || '';
    document.getElementById('ctrRating').value = c.rating || '';
    document.getElementById('ctrStatus').value = c.status || 'Active';
    document.getElementById('ctrNotes').value = c.notes || '';
    document.getElementById('contractorForm').scrollIntoView({ behavior: 'smooth' });
    const submitBtn = document.querySelector('#contractorForm button[type="submit"]');
    submitBtn.innerHTML = 'Update Contractor';
}

async function deleteContractor(id) {
    const contractors = await loadContractorsData();
    const contractor = contractors.find(c => c.id === id);
    
    showConfirmation({
        title: 'Delete Contractor',
        message: 'This contractor will be permanently removed from the system. This action cannot be undone.',
        itemName: `Contractor: ${contractor ? contractor.company : 'Unknown'}`,
        icon: '🗑️',
        confirmText: 'Delete Permanently',
        cancelText: 'Cancel',
        onConfirm: async () => {
            try {
                const response = await fetch('contractors-api.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                if (response.ok) {
                    await loadContractors();
                } else {
                    console.error('Failed to delete contractor');
                }
            } catch (error) {
                console.error('Error deleting contractor:', error);
            }
        }
    });
}

document.getElementById('resetBtn').addEventListener('click', function() {
    document.getElementById('contractorForm').reset();
    editingId = null;
    const submitBtn = document.querySelector('#contractorForm button[type="submit"]');
    submitBtn.innerHTML = 'Create Contractor';
    document.getElementById('formMessage').style.display = 'none';
});

document.getElementById('contractorForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('formMessage');
    const data = {
        company: document.getElementById('ctrCompany').value.trim(),
        owner: document.getElementById('ctrOwner').value.trim(),
        license: document.getElementById('ctrLicense').value.trim(),
        email: document.getElementById('ctrEmail').value.trim(),
        phone: document.getElementById('ctrPhone').value.trim(),
        address: document.getElementById('ctrAddress').value.trim(),
        specialization: document.getElementById('ctrSpecialization').value.trim(),
        experience: parseInt(document.getElementById('ctrExperience').value) || 0,
        rating: parseFloat(document.getElementById('ctrRating').value) || 0,
        status: document.getElementById('ctrStatus').value.trim(),
        notes: document.getElementById('ctrNotes').value.trim()
    };

    try {
        let response;
        if (editingId) {
            data.id = editingId;
            response = await fetch('contractors-api.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        } else {
            response = await fetch('contractors-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        }

        if (response.ok) {
            msg.style.color = '#0b5';
            msg.textContent = editingId ? 'Contractor updated successfully!' : 'Contractor created successfully!';
            msg.style.display = 'block';
            document.getElementById('contractorForm').reset();
            editingId = null;
            const submitBtn = document.querySelector('#contractorForm button[type="submit"]');
            submitBtn.innerHTML = 'Create Contractor';
            await loadContractors();
        } else {
            const error = await response.json();
            msg.style.color = '#d00';
            msg.textContent = 'Error: ' + (error.error || 'Unknown error');
            msg.style.display = 'block';
        }
    } catch (error) {
        msg.style.color = '#d00';
        msg.textContent = 'Error: ' + error.message;
        msg.style.display = 'block';
    }
});

// Event delegation for edit and delete buttons
document.querySelector('#contractorsTable tbody').addEventListener('click', (e) => {
    if (e.target.classList.contains('btn-edit')) {
        const id = e.target.dataset.id;
        editContractor(id);
    } else if (e.target.classList.contains('btn-delete')) {
        const id = e.target.dataset.id;
        deleteContractor(id);
    }
});

// Load contractors on page load
document.addEventListener('DOMContentLoaded', () => {
    loadProjectsFromDatabase();
    loadContractors();
});