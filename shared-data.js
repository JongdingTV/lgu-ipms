// Shared data management for IPMS
// This file provides utility functions for managing project data across the system

const IPMS_DATA = {
    projectsKey: 'lgu_ipms_projects',
    
    // Get all projects from localStorage
    getProjects: function() {
        try {
            return JSON.parse(localStorage.getItem(this.projectsKey) || '[]');
        } catch (e) {
            console.error('Error parsing projects from localStorage:', e);
            return [];
        }
    },
    
    // Save projects to localStorage
    saveProjects: function(projects) {
        try {
            localStorage.setItem(this.projectsKey, JSON.stringify(projects));
            return true;
        } catch (e) {
            console.error('Error saving projects to localStorage:', e);
            return false;
        }
    },
    
    // Add or update a project
    saveProject: function(project) {
        try {
            const projects = this.getProjects();
            const index = projects.findIndex(p => p.id === project.id);
            if (index >= 0) {
                projects[index] = project;
            } else {
                projects.push(project);
            }
            this.saveProjects(projects);
            return true;
        } catch (e) {
            console.error('Error saving project:', e);
            return false;
        }
    },
    
    // Delete a project
    deleteProject: function(projectId) {
        try {
            const projects = this.getProjects();
            const filtered = projects.filter(p => p.id !== projectId);
            this.saveProjects(filtered);
            return true;
        } catch (e) {
            console.error('Error deleting project:', e);
            return false;
        }
    },
    
    // Get dashboard metrics from stored projects
    getDashboardMetrics: function() {
        const projects = this.getProjects();
        
        return {
            totalProjects: projects.length,
            approvedProjects: projects.filter(p => p.status === 'Approved').length,
            inProgressProjects: projects.filter(p => {
                const progress = Number(p.progress || 0);
                return progress > 0 && progress < 100;
            }).length,
            completedProjects: projects.filter(p => {
                const progress = Number(p.progress || 0);
                return progress === 100 || p.status === 'Completed';
            }).length,
            totalBudget: projects.reduce((sum, p) => sum + Number(p.budget || 0), 0)
        };
    }
};

// Make IPMS_DATA available globally
window.IPMS_DATA = IPMS_DATA;

/* ============================================
   CONFIRMATION MODAL UTILITY
   ============================================ */

// Create confirmation modal if it doesn't exist
function initializeConfirmationModal() {
    if (document.getElementById('confirmationModal')) return;
    
    const modal = document.createElement('div');
    modal.id = 'confirmationModal';
    modal.className = 'confirmation-modal';
    modal.innerHTML = `
        <div class="confirmation-content">
            <div class="confirmation-icon" id="confirmIcon">⚠️</div>
            <h2 class="confirmation-title" id="confirmTitle">Confirm Action</h2>
            <p class="confirmation-message" id="confirmMessage">Are you sure?</p>
            <div class="confirmation-item" id="confirmItemName" style="display: none;"></div>
            <div class="confirmation-buttons">
                <button class="confirmation-btn btn-confirm-cancel" id="btnConfirmCancel">Cancel</button>
                <button class="confirmation-btn btn-confirm-delete" id="btnConfirmDelete">Delete</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Setup cancel button
    document.getElementById('btnConfirmCancel').addEventListener('click', () => {
        closeConfirmationModal();
    });
}

// Show confirmation modal
function showConfirmation(options = {}) {
    initializeConfirmationModal();
    
    const {
        title = 'Confirm Deletion',
        message = 'Are you sure you want to delete this item?',
        itemName = null,
        icon = '⚠️',
        confirmText = 'Delete',
        cancelText = 'Cancel',
        onConfirm = () => {},
        onCancel = () => {}
    } = options;
    
    const modal = document.getElementById('confirmationModal');
    document.getElementById('confirmIcon').textContent = icon;
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    
    const itemNameDiv = document.getElementById('confirmItemName');
    if (itemName) {
        itemNameDiv.textContent = itemName;
        itemNameDiv.style.display = 'block';
    } else {
        itemNameDiv.style.display = 'none';
    }
    
    document.getElementById('btnConfirmDelete').textContent = confirmText;
    document.getElementById('btnConfirmCancel').textContent = cancelText;
    
    // Remove old event listeners
    const oldDeleteBtn = document.getElementById('btnConfirmDelete');
    const newDeleteBtn = oldDeleteBtn.cloneNode(true);
    oldDeleteBtn.parentNode.replaceChild(newDeleteBtn, oldDeleteBtn);
    
    // Add new event listeners
    newDeleteBtn.addEventListener('click', () => {
        closeConfirmationModal();
        onConfirm();
    });
    
    document.getElementById('btnConfirmCancel').onclick = () => {
        closeConfirmationModal();
        onCancel();
    };
    
    modal.classList.add('show');
}

// Close confirmation modal
function closeConfirmationModal() {
    const modal = document.getElementById('confirmationModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

// Make functions globally available
window.showConfirmation = showConfirmation;
window.closeConfirmationModal = closeConfirmationModal;
window.initializeConfirmationModal = initializeConfirmationModal;

/* ============================================
   LOGOUT CONFIRMATION
   ============================================ */

// Setup logout confirmation on all pages
function setupLogoutConfirmation() {
    const logoutLinks = document.querySelectorAll('.nav-logout');
    logoutLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const logoutUrl = link.getAttribute('href');
            
            showConfirmation({
                title: 'Logout',
                message: 'You will be logged out of your account. Are you sure you want to continue?',
                itemName: 'Session will be ended',
                icon: '🚪',
                confirmText: 'Logout',
                cancelText: 'Cancel',
                onConfirm: () => {
                    window.location.href = logoutUrl;
                }
            });
        });
    });
}

// Initialize logout confirmation when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    setupLogoutConfirmation();
});

