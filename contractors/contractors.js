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

// Contractor form handling
const contractorForm = document.getElementById('contractorForm');
const formMessage = document.getElementById('formMessage');
const resetBtn = document.getElementById('resetBtn');
let editingId = null;

document.getElementById('resetBtn').addEventListener('click', function() {
    contractorForm.reset();
    editingId = null;
    const submitBtn = contractorForm.querySelector('button[type="submit"]');
    submitBtn.innerHTML = 'Create Contractor';
    formMessage.style.display = 'none';
});

contractorForm.addEventListener('submit', async (e) => {
    e.preventDefault();
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
            formMessage.style.color = '#0b5';
            formMessage.textContent = editingId ? 'Contractor updated successfully!' : 'Contractor created successfully!';
            formMessage.style.display = 'block';
            contractorForm.reset();
            editingId = null;
            const submitBtn = contractorForm.querySelector('button[type="submit"]');
            submitBtn.innerHTML = 'Create Contractor';
            setTimeout(() => { formMessage.style.display = 'none'; }, 3000);
        } else {
            const error = await response.json();
            formMessage.style.color = '#d00';
            formMessage.textContent = 'Error: ' + (error.error || 'Unknown error');
            formMessage.style.display = 'block';
        }
    } catch (error) {
        formMessage.style.color = '#d00';
        formMessage.textContent = 'Error: ' + error.message;
        formMessage.style.display = 'block';
    }
});

// Dropdown navigation toggle
document.addEventListener('DOMContentLoaded', () => {
    const contractorsToggle = document.getElementById('contractorsToggle');
    const navItemGroup = contractorsToggle?.closest('.nav-item-group');
    
    if (contractorsToggle && navItemGroup) {
        // Keep dropdown open by default
        navItemGroup.classList.add('open');
        
        contractorsToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            navItemGroup.classList.toggle('open');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!navItemGroup.contains(e.target)) {
                navItemGroup.classList.remove('open');
            }
        });
    }
});