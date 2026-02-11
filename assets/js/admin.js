'use strict';
/* ===== Consolidated Admin JS ===== */

/* ===== File: assets/js/shared/shared-config.js ===== */
// Shared configuration for API paths - works for both local and production
(function() {
    const pathname = window.location.pathname;
    const pathSegments = pathname.split('/').filter(p => p);
    
    // Known subdirectory names in the app
    const knownDirs = ['dashboard', 'contractors', 'project-registration', 'progress-monitoring', 
                      'budget-resources', 'task-milestone', 'project-prioritization', 'user-dashboard'];
    
    let appRoot = '/';
    let currentPagePath = '';
    
    // Find which known directory we're in
    const currentDirIndex = pathSegments.findIndex(seg => knownDirs.includes(seg));
    
    if (currentDirIndex >= 0) {
        // We're in a subdirectory
        appRoot = '/' + pathSegments.slice(0, currentDirIndex).join('/') + '/';
        currentPagePath = pathSegments[currentDirIndex] + '/';
    } else if (pathSegments.length > 0) {
        // We're in the root, but there might be an app folder
        appRoot = '/' + pathSegments[0] + '/';
    }
    
    window.CONFIG = {
        appRoot: appRoot,
        currentPath: currentPagePath,
        getApiUrl: function(endpoint) {
            return this.appRoot + endpoint;
        },
        getCurrentDirUrl: function(endpoint) {
            return endpoint;
        }
    };
})();



/* ===== File: assets/js/shared/security-no-back.js ===== */
/**
 * Back Button Prevention Script
 * Prevents users from using browser back button to access protected pages after logout
 * 
 * Usage: Add this script to the HEAD of all protected pages
 * <script src="/assets/js/shared/security-no-back.js"></script>
 */

(function() {
    // Prevent back button navigation
    if (window.history && window.history.pushState) {
        // Push current state to history
        window.history.pushState(null, null, window.location.href);
        
        // Listen for popstate (back button pressed)
        window.addEventListener('popstate', function(event) {
            // Push again to prevent going back
            window.history.pushState(null, null, window.location.href);
            
            // Force page reload from server (which will check auth)
            window.location.reload();
        });
    }
    
    // Additional protection: disable keyboard shortcuts for back navigation
    document.addEventListener('keydown', function(e) {
        // Alt+Left Arrow (Firefox, Chrome on Windows)
        if ((e.altKey || e.metaKey) && e.key === 'ArrowLeft') {
            e.preventDefault();
            alert('Navigation back is disabled for security. Please use the menu to navigate.');
            return false;
        }
        
        // Backspace key (only if not in input field)
        if (e.key === 'Backspace' && 
            e.target.tagName !== 'INPUT' && 
            e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
            alert('Navigation back is disabled for security. Please use the menu to navigate.');
            return false;
        }
    });
    
    // Monitor for page visibility (tab switching)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            // User returned to tab - reload to ensure still authenticated
            // Commented out to avoid excessive reloads
            // location.reload();
        }
    });
})();






/* ===== File: assets/js/shared/shared-data.js ===== */
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




/* ===== File: assets/js/shared/shared-toggle.js ===== */
/**
 * Shared Toggle Sidebar Functionality
 * Used across all pages for consistent sidebar toggle behavior
 */

document.addEventListener('DOMContentLoaded', function() {
    const toggleSidebar = document.getElementById('toggleSidebar');
    const toggleSidebarShow = document.getElementById('toggleSidebarShow');
    const navbarMenuIcon = document.getElementById('navbarMenuIcon');
    const showSidebarBtn = document.getElementById('showSidebarBtn');
    const navbar = document.getElementById('navbar');

    function toggleSidebarVisibility(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // Toggle the sidebar-hidden class on body
        const isSidebarHidden = document.body.classList.toggle('sidebar-hidden');
        
        // Also toggle .show class on floating button and navbar icon as backup
        if (showSidebarBtn) {
            showSidebarBtn.classList.toggle('show', isSidebarHidden);
        }
        if (navbarMenuIcon) {
            navbarMenuIcon.classList.toggle('show', isSidebarHidden);
        }
    }

    // Attach event listeners for all toggle buttons
    if (toggleSidebar) {
        toggleSidebar.addEventListener('click', toggleSidebarVisibility);
    }

    if (toggleSidebarShow) {
        toggleSidebarShow.addEventListener('click', toggleSidebarVisibility);
    }

    if (navbarMenuIcon) {
        navbarMenuIcon.addEventListener('click', toggleSidebarVisibility);
    }

    // Close dropdowns when clicking elsewhere
    document.addEventListener('click', function(e) {
        const navItemGroups = document.querySelectorAll('.nav-item-group');
        navItemGroups.forEach(group => {
            if (!group.contains(e.target)) {
                group.classList.remove('open');
            }
        });
    });
});




/* ===== File: dashboard/dashboard.js ===== */
// Debounce helper for expensive operations
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Demo fallback data (used when IPMS_DATA is not available)
function loadDashboardData() {
    // Check if shared data service is available
    if (typeof IPMS_DATA === 'undefined') {
        console.error('IPMS_DATA service not loaded');
        return;
    }

    const metrics = IPMS_DATA.getDashboardMetrics();
    const recentProjects = IPMS_DATA.getRecentProjects(3);

    // Update key metrics
    updateMetricCard(0, metrics.projects.total, 'Active & Completed');
    updateMetricCard(1, metrics.projects.inProgress, 'Currently executing');
    updateMetricCard(2, metrics.projects.completed, 'Successfully finished');
    updateMetricCard(3, IPMS_DATA.formatCurrency(metrics.budget.total), 'Total allocated');

    // Update status distribution
    updateStatusDistribution(metrics.statusDistribution);

    // Update budget utilization
    updateBudgetUtilization(metrics.budget);

    // Update recent projects table
    updateRecentProjectsTable(recentProjects);

    // Update quick stats
    updateQuickStats(metrics.analytics);
}

function updateMetricCard(index, value, status) {
    const cards = document.querySelectorAll('.metric-card');
    if (cards[index]) {
        const valueEl = cards[index].querySelector('.metric-value');
        const statusEl = cards[index].querySelector('.metric-status');
        if (valueEl) valueEl.textContent = value;
        if (statusEl) statusEl.textContent = status;
    }
}

function updateStatusDistribution(distribution) {
    const legendItems = document.querySelectorAll('.legend-item span:last-child');
    if (legendItems[0]) legendItems[0].textContent = `Completed: ${distribution.completed}%`;
    if (legendItems[1]) legendItems[1].textContent = `In Progress: ${distribution.inProgress}%`;
    if (legendItems[2]) legendItems[2].textContent = `Delayed: ${distribution.delayed}%`;
}

function updateBudgetUtilization(budget) {
    const progressFill = document.querySelector('.progress-fill');
    const utilizationText = document.querySelector('.chart-placeholder p');
    
    if (progressFill) {
        progressFill.style.width = budget.utilization + '%';
    }
    
    if (utilizationText) {
        utilizationText.textContent = `Budget utilization: ${budget.utilization}% Used (${IPMS_DATA.formatCurrency(budget.spent)} of ${IPMS_DATA.formatCurrency(budget.total)} allocated)`;
    }
}

function updateRecentProjectsTable(projects) {
    const tbody = document.querySelector('.projects-table tbody');
    if (!tbody) return;

    if (projects.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">No projects registered yet</td></tr>';
        return;
    }

    tbody.innerHTML = projects.map(p => {
        const statusClass = p.status.toLowerCase().replace(/\s+/g, '-');
        return `
            <tr>
                <td>${p.name}</td>
                <td>${p.location}</td>
                <td><span class="status-badge ${statusClass}">${p.status}</span></td>
                <td>
                    <div class="progress-small">
                        <div class="progress-fill-small" style="width: ${p.progress}%;"></div>
                    </div>
                </td>
                <td>${IPMS_DATA.formatCurrency(p.budget)}</td>
            </tr>
        `;
    }).join('');
}

function updateQuickStats(analytics) {
    const statItems = document.querySelectorAll('.stat-item p');
    if (statItems[0]) statItems[0].textContent = analytics.avgDuration + ' months';
    if (statItems[1]) statItems[1].textContent = analytics.onTimeRate + '%';
    if (statItems[2]) statItems[2].textContent = (analytics.budgetVariance >= 0 ? '+' : '') + analytics.budgetVariance + '%';
}

// Debounced refresh function
const debouncedLoadDashboardData = debounce(loadDashboardData, 500);

// Auto-refresh dashboard when returning from other pages
function setupAutoRefresh() {
    // Refresh on page load
    loadDashboardData();

    // Refresh when window gains focus (user returns to dashboard)
    window.addEventListener('focus', debouncedLoadDashboardData);

    // Refresh every 30 seconds if page is visible (use debounce to prevent overlapping calls)
    setInterval(() => {
        if (!document.hidden) {
            debouncedLoadDashboardData();
        }
    }, 30000);
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', setupAutoRefresh);

/* Metric card interactions: filter projects and show budget details */
function setupMetricInteractions() {
    const cards = document.querySelectorAll('.metric-card');
    if (!cards || cards.length === 0) return;

    cards.forEach((card, idx) => {
        // add three-dots menu if not present
        if (!card.querySelector('.card-menu')) {
            const btn = document.createElement('button');
            btn.className = 'card-menu';
            btn.type = 'button';
            btn.title = 'More';
            btn.innerText = '⋯';
            card.appendChild(btn);
            // placeholder for menu click
            btn.addEventListener('click', (e) => { e.stopPropagation(); });
        }

        // click behavior on the card itself
        card.style.cursor = 'pointer';
        card.addEventListener('click', function () {
            // animate
            card.classList.add('pulse');
            setTimeout(() => card.classList.remove('pulse'), 380);

            // determine action by index or data attribute
            if (idx === 0) {
                const list = fetchProjectsByFilter('all');
                updateRecentProjectsTable(list);
            } else if (idx === 1) {
                const list = fetchProjectsByFilter('inProgress');
                updateRecentProjectsTable(list);
            } else if (idx === 2) {
                const list = fetchProjectsByFilter('completed');
                updateRecentProjectsTable(list);
            } else if (idx === 3) {
                if (typeof IPMS_DATA !== 'undefined' && IPMS_DATA.getDashboardMetrics) {
                    const metrics = IPMS_DATA.getDashboardMetrics();
                    if (metrics && metrics.budget) {
                        updateBudgetUtilization(metrics.budget);
                    }
                }
            }
        });
    });
}

function fetchProjectsByFilter(filter) {
    if (typeof IPMS_DATA === 'undefined') {
        const all = demoProjects.slice();
        if (filter === 'all') return all;
        if (filter === 'inProgress') return all.filter(p => p.status && /progress|in progress/i.test(p.status));
        if (filter === 'completed') return all.filter(p => p.status && /complete|completed|finished/i.test(p.status));
        return [];
    }

    try {
        if (filter === 'all') {
            if (IPMS_DATA.getAllProjects) return IPMS_DATA.getAllProjects();
            if (IPMS_DATA.getProjects) return IPMS_DATA.getProjects();
            if (IPMS_DATA.getRecentProjects) return IPMS_DATA.getRecentProjects(1000) || [];
        }

        if (filter === 'inProgress') {
            if (IPMS_DATA.getProjectsByStatus) return IPMS_DATA.getProjectsByStatus('In Progress');
            const all = (IPMS_DATA.getAllProjects ? IPMS_DATA.getAllProjects() : (IPMS_DATA.getRecentProjects ? IPMS_DATA.getRecentProjects(1000) : []));
            return all.filter(p => p.status && /progress|in progress/i.test(p.status));
        }

        if (filter === 'completed') {
            if (IPMS_DATA.getProjectsByStatus) return IPMS_DATA.getProjectsByStatus('Completed');
            const all = (IPMS_DATA.getAllProjects ? IPMS_DATA.getAllProjects() : (IPMS_DATA.getRecentProjects ? IPMS_DATA.getRecentProjects(1000) : []));
            return all.filter(p => p.status && /complete|completed|finished/i.test(p.status));
        }
    } catch (err) {
        console.error('Error fetching projects by filter', err);
    }
    return [];
}

// Run interactions setup after initial render
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(setupMetricInteractions, 120);
});


/* ===== File: progress-monitoring/progress-monitoring.js ===== */
console.log('progress-monitoring.js loaded');

// Sidebar toggle - use optional chaining to avoid errors
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

/* Debounce utility */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/* Progress monitoring logic - fetch from database */
let allProjects = [];
let isLoading = false;

function showLoadingState() {
    const container = document.getElementById('projectsList');
    if (container) {
        container.innerHTML = `
            <div class="loading-state">
                <div class="spinner"></div>
                <p>Loading projects...</p>
            </div>
        `;
    }
}

function hideLoadingState() {
    isLoading = false;
}

function updateStatistics() {
    const total = allProjects.length;
    const approved = allProjects.filter(p => p.status === 'Approved').length;
    const inProgress = allProjects.filter(p => {
        const prog = Number(p.progress || 0);
        return prog > 0 && prog < 100;
    }).length;
    const completed = allProjects.filter(p => {
        const prog = Number(p.progress || 0);
        return prog === 100 || p.status === 'Completed';
    }).length;
    
    let totalContractors = 0;
    allProjects.forEach(p => {
        totalContractors += (p.assigned_contractors || []).length;
    });
    
    const statTotal = document.getElementById('statTotal');
    const statApproved = document.getElementById('statApproved');
    const statInProgress = document.getElementById('statInProgress');
    const statCompleted = document.getElementById('statCompleted');
    const statContractors = document.getElementById('statContractors');
    
    if (statTotal) statTotal.textContent = total;
    if (statApproved) statApproved.textContent = approved;
    if (statInProgress) statInProgress.textContent = inProgress;
    if (statCompleted) statCompleted.textContent = completed;
    if (statContractors) statContractors.textContent = totalContractors;
}

function loadProjectsFromDatabase() {
    if (isLoading) return;
    isLoading = true;
    showLoadingState();
    
    console.log('=== LOADING PROJECTS ===');
    console.log('Current URL:', window.location.href);
    console.log('getApiUrl function defined?', typeof window.getApiUrl);
    console.log('APP_ROOT value:', window.APP_ROOT);
    
    // Check if getApiUrl is available
    if (typeof window.getApiUrl !== 'function') {
        console.error('❌ getApiUrl function not available!');
        const container = document.getElementById('projectsList');
        if (container) {
            container.innerHTML = '<div style="color: red; padding: 20px;">Error: API configuration not loaded. Please refresh the page.</div>';
        }
        hideLoadingState();
        return;
    }
    
    console.log('Fetching from: progress_monitoring.php?action=load_projects');
    
    // Use getApiUrl to ensure it works from any location
    const fetchUrl = getApiUrl('admin/progress_monitoring.php?action=load_projects');
    console.log('Full fetch URL:', fetchUrl);
    
    fetch(fetchUrl)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            console.log('Response headers:', {
                'content-type': response.headers.get('content-type')
            });
            if (!response.ok) {
                throw new Error(`Network response was not ok: ${response.status} ${response.statusText}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw response text length:', text.length);
            console.log('First 500 chars of response:', text.substring(0, 500));
            try {
                const data = JSON.parse(text);
                console.log('✅ Parsed JSON data successfully');
                console.log('Data is array?', Array.isArray(data));
                console.log('Data length:', Array.isArray(data) ? data.length : 'N/A');
                console.log('First item:', Array.isArray(data) && data.length > 0 ? data[0] : 'N/A');
                
                if (data.error) {
                    console.error('API returned error:', data.error);
                    allProjects = [];
                } else {
                    allProjects = Array.isArray(data) ? data : [];
                }
                
                console.log('Total projects loaded:', allProjects.length);
                hideLoadingState();
                updateStatistics();
                renderProjects();
            } catch (parseError) {
                console.error('❌ JSON parse error:', parseError);
                console.error('Text that failed to parse:', text);
                allProjects = [];
                hideLoadingState();
                renderProjects();
                const container = document.getElementById('projectsList');
                if (container) {
                    container.innerHTML = `<div style="color: red; padding: 20px; background: #ffe0e0; border-radius: 8px; border: 1px solid red;">JSON Parse Error: ${parseError.message}<br>Response: ${text.substring(0, 200)}</div>`;
                }
            }
        })
        .catch(error => {
            console.error('❌ FETCH ERROR:', error);
            allProjects = [];
            hideLoadingState();
            renderProjects();
            const container = document.getElementById('projectsList');
            if (container) {
                container.innerHTML = `<div style="color: red; padding: 20px; background: #ffe0e0; border-radius: 8px; border: 1px solid red;">Fetch Error: ${error.message}</div>`;
            }
        });
}

function formatCurrency(n) {
    if (!n && n !== 0) return '—';
    return '₱' + Number(n).toLocaleString();
}

function renderContractorsBadges(contractors) {
    if (!contractors || contractors.length === 0) {
        return '<div class="contractors-badge empty">No Contractors</div>';
    }
    
    const badges = contractors.slice(0, 3).map(c => `
        <div class="contractor-badge" title="${c.company}">
            <span class="contractor-name">${c.company}</span>
            ${c.rating ? '<span class="contractor-rating">⭐ ' + c.rating + '</span>' : ''}
        </div>
    `).join('');
    
    const extra = contractors.length > 3 ? `<div class="contractor-badge extra">+${contractors.length - 3} more</div>` : '';
    
    return badges + extra;
}

function getRiskLevel(progress, status, endDate) {
    // Calculate risk level based on progress and dates
    if (status === 'Cancelled' || status === 'On-hold') return 'low';
    
    const today = new Date();
    const end = endDate ? new Date(endDate) : null;
    
    // If no end date, consider medium risk
    if (!end) return 'medium';
    
    const daysRemaining = Math.ceil((end - today) / (1000 * 60 * 60 * 24));
    const prog = Number(progress || 0);
    
    // High risk: behind schedule
    if (daysRemaining <= 30 && prog < 80) return 'high';
    if (daysRemaining <= 0) return 'critical';
    
    // Medium risk
    if (daysRemaining <= 60 && prog < 50) return 'medium';
    
    return 'low';
}

function getProgressColor(progress) {
    const p = Number(progress || 0);
    if (p >= 80) return '#10b981';      // Green
    if (p >= 50) return '#f59e0b';      // Orange
    if (p >= 25) return '#f97316';      // Orange-red
    return '#ef4444';                    // Red
}

function getTimelineStatus(startDate, endDate) {
    if (!startDate || !endDate) return { status: 'unknown', label: '?' };
    
    const today = new Date();
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    if (today < start) return { status: 'upcoming', label: '⏳ Upcoming' };
    if (today > end) return { status: 'overdue', label: '⚠️ Overdue' };
    return { status: 'active', label: '▶️ Active' };
}

function renderProjects() {
    const container = document.getElementById('projectsList');
    if (!container) return;
    
    const projects = allProjects || [];
    const q = (document.getElementById('pmSearch')?.value || '').trim().toLowerCase();
    const status = document.getElementById('pmStatusFilter')?.value || '';
    const sector = document.getElementById('pmSectorFilter')?.value || '';
    const progressFilter = document.getElementById('pmProgressFilter')?.value || '';
    const contractorFilter = document.getElementById('pmContractorFilter')?.value || '';
    const sort = document.getElementById('pmSort')?.value || 'createdAt_desc';

    let filtered = projects.filter(p => {
        if (status && (p.status || '').trim() !== status.trim()) return false;
        if (sector && (p.sector || '').trim() !== sector.trim()) return false;
        
        // Progress filter
        if (progressFilter && p.progress !== undefined) {
            const progress = Number(p.progress || 0);
            if (progressFilter === '0-25' && !(progress >= 0 && progress <= 25)) return false;
            if (progressFilter === '25-50' && !(progress > 25 && progress <= 50)) return false;
            if (progressFilter === '50-75' && !(progress > 50 && progress <= 75)) return false;
            if (progressFilter === '75-100' && !(progress > 75 && progress <= 100)) return false;
        }
        
        // Contractor filter
        if (contractorFilter === 'assigned' && (!p.assigned_contractors || p.assigned_contractors.length === 0)) return false;
        if (contractorFilter === 'unassigned' && (p.assigned_contractors && p.assigned_contractors.length > 0)) return false;
        
        if (!q) return true;
        const searchText = ((p.code || '') + ' ' + (p.name || '') + ' ' + (p.location || '')).toLowerCase();
        return searchText.includes(q);
    });

    if (sort === 'createdAt_desc') filtered.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
    if (sort === 'createdAt_asc') filtered.sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0));
    if (sort === 'progress_desc') filtered.sort((a, b) => Number(b.progress || 0) - Number(a.progress || 0));
    if (sort === 'progress_asc') filtered.sort((a, b) => Number(a.progress || 0) - Number(b.progress || 0));

    if (!filtered.length) {
        container.innerHTML = '';
        const emptyState = document.getElementById('pmEmpty');
        if (emptyState) emptyState.style.display = 'block';
        return;
    } else {
        const emptyState = document.getElementById('pmEmpty');
        if (emptyState) emptyState.style.display = 'none';
    }

    const html = filtered.map((p, idx) => {
        const progress = Number(p.progress || 0);
        const pct = Math.min(100, Math.max(0, progress));
        const statusClass = (p.status || 'draft').replace(/\s+/g, '-').toLowerCase();
        const contractorCount = (p.assigned_contractors || []).length;
        const riskLevel = getRiskLevel(progress, p.status, p.end_date);
        const progressColor = getProgressColor(progress);
        const timeline = getTimelineStatus(p.start_date, p.end_date);
        
        return `
<div class="project-card risk-${riskLevel}" data-project-id="${p.id || idx}">
  <div class="project-header">
    <div class="project-title-section">
      <h4>${p.code || 'N/A'} — ${p.name || 'Unnamed Project'}</h4>
      <span class="timeline-badge timeline-${timeline.status}">${timeline.label}</span>
    </div>
    <div class="project-header-right">
      <span class="risk-badge risk-${riskLevel}">
        ${riskLevel === 'critical' ? '🚨 CRITICAL' : riskLevel === 'high' ? '⚠️ HIGH' : riskLevel === 'medium' ? '⚡ MEDIUM' : '✅ LOW'}
      </span>
      <span class="project-status ${statusClass}">${p.status || 'Draft'}</span>
    </div>
  </div>
  
  <div class="project-meta">
    <div class="project-meta-item">
      <span class="project-meta-label">Location:</span>
      <span class="project-meta-value">${p.location || '—'}</span>
    </div>
    <div class="project-meta-item">
      <span class="project-meta-label">Sector:</span>
      <span class="project-meta-value">${p.sector || '—'}</span>
    </div>
    <div class="project-meta-item">
      <span class="project-meta-label">Budget:</span>
      <span class="project-meta-value">${formatCurrency(p.budget)}</span>
    </div>
    <div class="project-meta-item">
      <span class="project-meta-label">Duration:</span>
      <span class="project-meta-value">${p.duration_months || p.duration || '—'} months</span>
    </div>
  </div>

  <div class="progress-container">
    <div class="progress-label">
      <span>Completion</span>
      <span style="font-weight: 700; color: ${progressColor};">${pct}%</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill" style="width: ${pct}%; background-color: ${progressColor};"></div>
    </div>
  </div>

  <div class="contractors-section">
    <div class="contractors-title">
      <span>👷 Assigned Contractors</span>
      <span class="contractor-count">${contractorCount}</span>
    </div>
    ${renderContractorsBadges(p.assigned_contractors)}
  </div>

  <div class="project-timeline-info">
    <div class="timeline-item">
      <span class="timeline-label">Start:</span>
      <span class="timeline-value">${p.start_date ? new Date(p.start_date).toLocaleDateString() : '—'}</span>
    </div>
    <div class="timeline-item">
      <span class="timeline-label">End:</span>
      <span class="timeline-value">${p.end_date ? new Date(p.end_date).toLocaleDateString() : '—'}</span>
    </div>
  </div>

  <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 0.85rem; color: #9ca3af;">
    <strong>Description:</strong> ${p.description || 'No description available'}
    ${p.project_manager ? '<br><strong>Manager:</strong> ' + p.project_manager : ''}
  </div>
</div>`;
    }).join('');

    container.innerHTML = html;
}


// wire filters/search/sort with debouncing
document.addEventListener('DOMContentLoaded', () => {
    const debouncedRender = debounce(renderProjects, 300);
    
    const controls = ['pmSearch', 'pmStatusFilter', 'pmSectorFilter', 'pmProgressFilter', 'pmContractorFilter', 'pmSort'];
    controls.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const ev = el.tagName === 'INPUT' ? 'input' : 'change';
        el.addEventListener(ev, debouncedRender);
    });

    const exportBtn = document.getElementById('exportCsv');
    exportBtn?.addEventListener('click', () => {
        if (!allProjects.length) { 
            alert('No projects to export'); 
            return; 
        }
        const filtered = allProjects;
        const keys = ['code', 'name', 'sector', 'location', 'budget', 'status', 'progress'];
        const headers = keys.map(k => k.charAt(0).toUpperCase() + k.slice(1)).join(',');
        const rows = filtered.map(p => 
            keys.map(k => {
                let val = p[k] || '';
                if (k === 'budget') val = '₱' + Number(val || 0).toLocaleString();
                return `"${String(val).replace(/"/g, '""')}"`;
            }).join(',')
        );
        const csv = [headers, ...rows].join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `projects_export_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
    });

    // Load projects from database
    loadProjectsFromDatabase();
});


/* ===== File: budget-resources/budget-resources.js ===== */
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

/* ===== Budget & Resources logic ===== */

const BUDGET_KEY = 'lgu_budget_module_v1';

function loadData() {
    return JSON.parse(localStorage.getItem(BUDGET_KEY) || '{"globalBudget":0,"milestones":[],"expenses":[]}');
}
function saveData(data) {
    localStorage.setItem(BUDGET_KEY, JSON.stringify(data));
}
function currency(n) {
    if (n === undefined || n === null || isNaN(n)) return '₱0';
    return '₱' + Number(n).toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
}
function uid() { return 'm' + Math.random().toString(36).slice(2,9); }

function renderAll() {
    const data = loadData();
    document.getElementById('globalBudget').value = data.globalBudget || '';
    renderMilestones(data);
    renderExpenses(data);
    renderSummary(data);
    drawChart(data);
    populateExpenseSelect(data);
}

function renderMilestones(data) {
    const tbody = document.querySelector('#milestonesTable tbody');
    tbody.innerHTML = '';
    data.milestones.forEach(ms => {
        const spent = (data.expenses || []).filter(e=> e.milestoneId === ms.id).reduce((s,e)=> s + Number(e.amount||0), 0);
        const rem = Math.max(0, (ms.allocated||0) - spent);
        const pct = ms.allocated ? Math.round((spent / ms.allocated) * 100) : 0;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${ms.name}</td>
            <td><input data-id="${ms.id}" class="allocInput" type="number" min="0" step="0.01" value="${ms.allocated||0}"></td>
            <td>${currency(spent)}</td>
            <td>${currency(rem)}</td>
            <td>${pct}%</td>
            <td>
                <div class="btn-row">
                    <button data-id="${ms.id}" class="btn-small btnDelete">Delete</button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // wire alloc input and delete buttons
    document.querySelectorAll('.allocInput').forEach(inp=>{
        inp.addEventListener('change', (e)=>{
            const id = inp.dataset.id;
            const val = Number(inp.value || 0);
            const data = loadData();
            const m = data.milestones.find(x=> x.id === id);
            if (m) { m.allocated = val; saveData(data); renderAll(); }
        });
    });
    document.querySelectorAll('.btnDelete').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const id = btn.dataset.id;
            let data = loadData();
            data.milestones = data.milestones.filter(m=> m.id !== id);
            data.expenses = data.expenses.filter(e=> e.milestoneId !== id);
            saveData(data);
            renderAll();
        });
    });
}

function renderExpenses(data) {
    const tbody = document.querySelector('#expensesTable tbody');
    tbody.innerHTML = '';
    (data.expenses || []).slice().reverse().forEach(exp=>{
        const ms = data.milestones.find(m=> m.id === exp.milestoneId);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${new Date(exp.date).toLocaleString()}</td>
            <td>${ms ? ms.name : '(milestone removed)'}</td>
            <td>${exp.description || ''}</td>
            <td>${currency(exp.amount)}</td>
            <td><button data-id="${exp.id}" class="btn-small btn-danger btnExpDel">Delete</button></td>
        `;
        tbody.appendChild(tr);
    });

    document.querySelectorAll('.btnExpDel').forEach(b=>{
        b.addEventListener('click', ()=>{
            const id = b.dataset.id;
            let data = loadData();
            data.expenses = data.expenses.filter(e=> e.id !== id);
            saveData(data);
            renderAll();
        });
    });
}

function populateExpenseSelect(data) {
    const sel = document.getElementById('expenseMilestone');
    sel.innerHTML = '<option value="">Select milestone</option>';
    data.milestones.forEach(m=>{
        const opt = document.createElement('option');
        opt.value = m.id;
        opt.textContent = m.name;
        sel.appendChild(opt);
    });
}

function renderSummary(data) {
    const allocated = data.milestones.reduce((s,m)=> s + Number(m.allocated||0), 0);
    const spent = (data.expenses || []).reduce((s,e)=> s + Number(e.amount||0), 0);
    const remaining = Math.max(0, (Number(data.globalBudget||0) - spent));
    const consumption = allocated ? Math.round((spent / allocated) * 100) : (data.globalBudget ? Math.round((spent / data.globalBudget) * 100) : 0);

    document.getElementById('summaryAllocated').textContent = currency(allocated);
    document.getElementById('summarySpent').textContent = currency(spent);
    document.getElementById('summaryRemaining').textContent = currency(remaining);
    document.getElementById('summaryConsumption').textContent = (consumption||0) + '%';
}

function drawChart(data) {
    const canvas = document.getElementById('consumptionChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    // clear
    ctx.clearRect(0,0,canvas.width,canvas.height);
    const padding = 40;
    const w = canvas.width - padding*2;
    const h = canvas.height - padding*2;
    const ms = data.milestones || [];
    if (!ms.length) {
        ctx.fillStyle = '#6b7280';
        ctx.font = '14px Poppins, sans-serif';
        ctx.fillText('No milestones to display', padding, padding + 20);
        return;
    }

    // compute max base
    const maxVal = Math.max(...ms.map(m => Number(m.allocated||0)), 1);
    const barH = Math.max(18, Math.floor(h / ms.length) - 8);
    ms.forEach((m, i) => {
        const y = padding + i*(barH + 12);
        // allocated bar (bg)
        const allocW = (Number(m.allocated||0) / maxVal) * w;
        ctx.fillStyle = '#eef2ff';
        ctx.fillRect(padding, y, w, barH);
        // allocated fill
        ctx.fillStyle = '#2563eb';
        ctx.fillRect(padding, y, allocW, barH);
        // spent overlay (green) proportional to allocated
        const spent = (data.expenses || []).filter(e=> e.milestoneId === m.id).reduce((s,e)=> s + Number(e.amount||0), 0);
        const spentW = m.allocated ? (spent / m.allocated) * allocW : 0;
        ctx.fillStyle = '#16a34a';
        ctx.fillRect(padding, y, Math.min(spentW, allocW), barH);
        // labels
        ctx.fillStyle = '#0f172a';
        ctx.font = '12px Poppins, sans-serif';
        ctx.fillText(m.name, padding + w + 12, y + barH/2 + 4);
        ctx.fillStyle = '#0f172a';
        ctx.fillText(currency(m.allocated), padding + 6, y + barH/2 + 4);
        ctx.fillStyle = '#065f46';
        ctx.fillText(currency(spent), padding + Math.min(allocW, w) - 50, y + barH/2 + 4);
    });
}

/* wire UI */
document.addEventListener('DOMContentLoaded', ()=>{
    // initial render
    renderAll();

    document.getElementById('milestoneForm').addEventListener('submit', (ev)=>{
        ev.preventDefault();
        const name = document.getElementById('milestoneName').value.trim();
        const alloc = Number(document.getElementById('milestoneAlloc').value || 0);
        if (!name) return;
        const data = loadData();
        const m = { id: uid(), name, allocated: alloc };
        data.milestones.push(m);
        saveData(data);
        document.getElementById('milestoneForm').reset();
        renderAll();
    });

    document.getElementById('expenseForm').addEventListener('submit', (ev)=>{
        ev.preventDefault();
        const milId = document.getElementById('expenseMilestone').value;
        const amount = Number(document.getElementById('expenseAmount').value || 0);
        const desc = document.getElementById('expenseDesc').value.trim();
        if (!milId || !amount) return;
        const data = loadData();
        const e = { id: 'e' + Math.random().toString(36).slice(2,9), milestoneId: milId, amount, description: desc, date: new Date().toISOString() };
        data.expenses = data.expenses || [];
        data.expenses.push(e);
        saveData(data);
        document.getElementById('expenseForm').reset();
        renderAll();
    });

    document.getElementById('globalBudget').addEventListener('change', (ev)=>{
        const val = Number(ev.target.value || 0);
        const data = loadData();
        data.globalBudget = val;
        saveData(data);
        renderAll();
    });

    document.getElementById('btnExportBudget').addEventListener('click', ()=>{
        const data = loadData();
        const rows = [];
        rows.push(['type','milestoneId','milestoneName','allocated','expenseId','expenseAmount','expenseDesc','date'].join(','));
        (data.milestones || []).forEach(m=>{
            const expenses = (data.expenses||[]).filter(e=> e.milestoneId === m.id);
            if (!expenses.length) {
                rows.push(['milestone',m.id, `"${m.name.replace(/"/g,'""')}"`, m.allocated,'','','',''].join(','));
            } else {
                expenses.forEach(ex=>{
                    rows.push(['expense',m.id, `"${m.name.replace(/"/g,'""')}"`, m.allocated, ex.id, ex.amount, `"${(ex.description||'').replace(/"/g,'""')}"`, ex.date].join(','));
                });
            }
        });
        const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'budget_export.csv';
        a.click();
        URL.revokeObjectURL(url);
    });

    document.getElementById('btnImport').addEventListener('click', ()=>{
        // Fetch projects from database
        console.log('Import button clicked');
        fetch(getApiUrl('admin/budget_resources.php?action=load_projects'))
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(projects => {
                console.log('Projects loaded:', projects);
                if (!projects.length) { alert('No projects available to import.'); return; }
                const proj = projects[0];
                const data = loadData();
                if (proj.budget) data.globalBudget = proj.budget;
                saveData(data);
                renderAll();
                alert('Imported budget from project: ' + (proj.name || 'Project ' + proj.code));
            })
            .catch(error => {
                console.error('Error loading projects:', error);
                alert('Failed to import budget from projects.');
            });
    });

    window.addEventListener('resize', ()=> drawChart(loadData()));
});


/* ===== File: contractors/contractors.js ===== */
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
            response = await fetch(getApiUrl('admin/contractors-api.php'), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        } else {
            response = await fetch(getApiUrl('admin/contractors-api.php'), {
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


/* ===== File: project-registration/project-reg.js ===== */
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
    fetch(getApiUrl('admin/project_registration.php?action=load_projects'))
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
    showConfirmation({
        title: 'Delete Project',
        message: 'This action cannot be undone. The project and all associated data will be permanently deleted.',
        itemName: `Project: ${project.name}`,
        icon: '🗑️',
        confirmText: 'Delete Permanently',
        cancelText: 'Cancel',
        onConfirm: () => {
            const formData = new FormData();
            formData.append('action', 'delete_project');
            formData.append('id', project.id);

            fetch(getApiUrl('admin/project_registration.php'), {
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


/* ===== File: project-prioritization/project-prioritization.js ===== */
// Project Prioritization Module
console.log('project-prioritization.js loaded');

// Debounce helper for search operations
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

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
    const inputs = loadInputs();
    const input = inputs.find(i => i.id === id);
    
    showConfirmation({
        title: 'Delete Feedback',
        message: 'This feedback entry will be permanently removed. This action cannot be undone.',
        itemName: `Feedback: ${input ? input.subject : 'Unknown'}`,
        icon: '🗑️',
        confirmText: 'Delete Permanently',
        cancelText: 'Cancel',
        onConfirm: () => {
            const remaining = loadInputs().filter(i => i.id !== id);
            saveInputs(remaining);
            renderInputs();
        }
    });
}

// Filter event listeners with debouncing - safely check if elements exist
const filterElements = ['filterType', 'filterCategory', 'filterUrgency'];
const debouncedRenderInputs = debounce(renderInputs, 300);

filterElements.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('change', debouncedRenderInputs);
    }
});

// Initialize - load projects from database first
document.addEventListener('DOMContentLoaded', loadProjectsFromDatabase);


/* ===== File: task-milestone/task-milestone.js ===== */
// filepath: c:\Users\james\OneDrive\Documents\GitHub\lgu-ipms\task-milestone\task-milestone.js
console.log('task-milestone.js loaded');

// sidebar toggle (keeps existing functionality)
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

/* Task & Milestone module - fetch projects from database */
const TM_KEY = 'lgu_tasks_v1';

function loadTasks() { return JSON.parse(localStorage.getItem(TM_KEY) || '[]'); }
function saveTasks(list) { localStorage.setItem(TM_KEY, JSON.stringify(list)); }
function uid() { return 't' + Math.random().toString(36).slice(2,9); }

let allProjects = [];

function loadProjectsFromDatabase() {
    console.log('Loading projects from database...');
    return fetch(getApiUrl('admin/tasks_milestones.php?action=load_projects'))
        .then(response => {
            console.log('Projects response status:', response.status);
            if (!response.ok) throw new Error('Failed to load projects');
            return response.json();
        })
        .then(projects => {
            console.log('Projects loaded:', projects);
            allProjects = projects;
            renderProjectOptions();
        })
        .catch(error => {
            console.error('Error loading projects:', error);
            allProjects = [];
        });
}

function getProjects() {
    return allProjects;
}

function renderProjectOptions() {
    const projects = getProjects();
    const sel1 = document.getElementById('taskProject');
    const selFilter = document.getElementById('tmFilterProject');
    [sel1, selFilter].forEach(sel => {
        if (!sel) return;
        const current = sel.value || '';
        sel.innerHTML = '<option value="">Select project</option>';
        projects.forEach(p => {
            const o = document.createElement('option'); o.value = p.code || p.name || ''; o.textContent = (p.code ? p.code + ' — ' : '') + (p.name || 'Unnamed');
            sel.appendChild(o);
        });
        sel.value = current;
    });
}

function isOverdue(d) {
    if (!d) return false;
    const today = new Date(); today.setHours(0,0,0,0);
    const dd = new Date(d); dd.setHours(0,0,0,0);
    return dd < today;
}

function dueWithinWeek(d) {
    if (!d) return false;
    const today = new Date(); today.setHours(0,0,0,0);
    const dd = new Date(d); dd.setHours(0,0,0,0);
    const diff = (dd - today)/(1000*60*60*24);
    return diff >=0 && diff <=7;
}
function renderTasks() {
    const tasks = loadTasks();
    const tbody = document.querySelector('#tasksTable tbody');
    tbody.innerHTML = '';
    // For validation, show deliverable, status, and a validated checkbox
    let validatedCount = 0;
    tasks.forEach(t => {
        const isValidated = t.status === 'Completed' || t.validated;
        if (isValidated) validatedCount++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${t.deliverable || ''}</td>
            <td><span class="badge status-${(t.status||'Not Started').replace(/\s+/g,'\\ ')}">${t.status || 'Not Started'}</span></td>
            <td><input type="checkbox" class="validate-checkbox" data-id="${t.id}" ${isValidated ? 'checked' : ''}></td>
        `;
        tbody.appendChild(tr);
    });

    // Calculate and show validation percentage
    const total = tasks.length;
    const percent = total ? Math.round((validatedCount / total) * 100) : 0;
    document.getElementById('validationPercent').textContent = percent + '%';
    document.getElementById('validationProgress').style.width = percent + '%';

    // Wire validation checkboxes
    document.querySelectorAll('.validate-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const id = cb.dataset.id;
            const list = loadTasks();
            const t = list.find(x => x.id === id);
            if (t) {
                t.validated = cb.checked;
                if (cb.checked) t.status = 'Completed';
                saveTasks(list);
                renderTasks();
            }
        });
    });
}

document.getElementById('tmExport')?.addEventListener('click', ()=>{
    const tasks = loadTasks();
    if (!tasks.length) { alert('No tasks to export'); return; }
    const keys = ['id','project','deliverable','taskName','assigneeType','assignee','deadline','priority','status','createdAt'];
    const rows = tasks.map(t => keys.map(k => `"${(t[k]||'').toString().replace(/"/g,'""')}"`).join(','));
    const csv = ['"' + keys.join('","') + '"', ...rows].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'tasks_export.csv'; a.click(); URL.revokeObjectURL(url);
});

// Initialize by loading projects from database
document.addEventListener('DOMContentLoaded', () => {
    loadProjectsFromDatabase();
    renderProjectOptions();
    renderTasks();
});

// filters
['tmSearch','tmFilterStatus','tmFilterProject'].forEach(id => {
    document.getElementById(id)?.addEventListener('input', renderTasks);
    document.getElementById(id)?.addEventListener('change', renderTasks);
});


/* ===== Inline scripts extracted from /admin/manage-employees.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/manage-employees.php')) return;
function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

})();

/* ===== Inline scripts extracted from /admin/settings.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/settings.php')) return;
        document.addEventListener('DOMContentLoaded', function() {
            // ============================================
            // LOGOUT CONFIRMATION
            // ============================================
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '/admin/logout.php';
                        }
                    });
                    return false;
                };
            }

            // ============================================
            // DROPDOWN NAVIGATION
            // ============================================
            const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
            const contractorsToggle = document.getElementById('contractorsToggle');
            const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
            const userMenuToggle = document.getElementById('userMenuToggle');
            const userMenuGroup = userMenuToggle ? userMenuToggle.closest('.nav-item-group') : null;
            
            if (projectRegToggle && projectRegGroup) {
                projectRegToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    projectRegGroup.classList.toggle('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            }
            
            if (contractorsToggle && contractorsGroup) {
                contractorsToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    contractorsGroup.classList.toggle('open');
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            }

            if (userMenuToggle && userMenuGroup) {
                userMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    userMenuGroup.classList.toggle('open');
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                });
            }
            
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.nav-item-group')) {
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                }
            });
            
            document.querySelectorAll('.nav-submenu-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            });
        });

        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active from all buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active to clicked button
            event.target.classList.add('active');
            
            // Update URL
            window.history.pushState({tab: tabName}, '', '?tab=' + tabName);
        }

})();

/* ===== Inline scripts extracted from dashboard/dashboard.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/dashboard.php')) return;
document.addEventListener('DOMContentLoaded', function() {
            // ============================================
            // LOGOUT CONFIRMATION
            // ============================================
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '/admin/logout.php';
                        }
                    });
                    return false;
                };
            }

            // ============================================
            // NAVBAR SEARCH FUNCTIONALITY
            // ============================================
            const navSearch = document.getElementById('navSearch');
            const navLinks = document.querySelector('.nav-links');
            
            if (navSearch && navLinks) {
                navSearch.addEventListener('input', function(e) {
                    const query = e.target.value.toLowerCase();
                    const links = navLinks.querySelectorAll('a');
                    
                    links.forEach(link => {
                        const text = link.textContent.toLowerCase();
                        const parent = link.closest('.nav-item-group') || link.parentElement;
                        
                        if (text.includes(query)) {
                            parent.style.display = '';
                            if (link.classList.contains('nav-main-item')) {
                                link.closest('.nav-item-group').querySelector('.nav-submenu').style.display = 'block';
                            }
                        } else if (query && !text.includes(query)) {
                            parent.style.display = 'none';
                        } else if (!query) {
                            parent.style.display = '';
                            if (link.classList.contains('nav-main-item')) {
                                link.closest('.nav-item-group').querySelector('.nav-submenu').style.display = 'none';
                            }
                        }
                    });
                });
            }

            // ============================================
            // BUDGET VISIBILITY TOGGLE
            // ============================================
            const budgetCard = document.getElementById('budgetCard');
            const budgetValue = document.getElementById('budgetValue');
            const budgetBtn = document.getElementById('budgetVisibilityToggle');
            let budgetRevealTimer;
            let isRevealing = false;

            if (budgetBtn && budgetValue && budgetCard) {
                // Add hover styles
                budgetBtn.addEventListener('mouseenter', function() {
                    this.style.color = '#333';
                    this.style.opacity = '1';
                });
                
                budgetBtn.addEventListener('mouseleave', function() {
                    if (!isRevealing) {
                        this.style.color = '#666';
                        this.style.opacity = '0.7';
                    }
                });
                
                // Mouse down - start timer
                budgetBtn.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    startReveal();
                });
                
                // Touch start
                budgetBtn.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    startReveal();
                });
                
                // Mouse up - end reveal
                document.addEventListener('mouseup', function() {
                    endReveal();
                });
                
                // Touch end
                document.addEventListener('touchend', function() {
                    endReveal();
                });
                
                function startReveal() {
                    clearTimeout(budgetRevealTimer);
                    budgetRevealTimer = setTimeout(() => {
                        if (!isRevealing) {
                            isRevealing = true;
                            const actualBudget = budgetCard.getAttribute('data-budget');
                            budgetValue.textContent = '₱' + actualBudget;
                            budgetBtn.style.color = '#3b82f6';
                            budgetBtn.style.opacity = '1';
                        }
                    }, 300);
                }

                function endReveal() {
                    clearTimeout(budgetRevealTimer);
                    if (isRevealing) {
                        isRevealing = false;
                        budgetValue.textContent = '●●●●●●●●';
                        budgetBtn.style.color = '#666';
                        budgetBtn.style.opacity = '0.7';
                    }
                }
            }

            // ============================================
            // DROPDOWN NAVIGATION
            // ============================================
            const projectRegToggle = document.getElementById('projectRegToggle');
            const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
            const contractorsToggle = document.getElementById('contractorsToggle');
            const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
            const userMenuToggle = document.getElementById('userMenuToggle');
            const userMenuGroup = userMenuToggle ? userMenuToggle.closest('.nav-item-group') : null;
            
            if (projectRegToggle && projectRegGroup) {
                projectRegToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    projectRegGroup.classList.toggle('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            }
            
            if (contractorsToggle && contractorsGroup) {
                contractorsToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    contractorsGroup.classList.toggle('open');
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            }

            if (userMenuToggle && userMenuGroup) {
                userMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    userMenuGroup.classList.toggle('open');
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                });
            }
            
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.nav-item-group')) {
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                }
            });
            
            document.querySelectorAll('.nav-submenu-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (projectRegGroup) projectRegGroup.classList.remove('open');
                    if (contractorsGroup) contractorsGroup.classList.remove('open');
                    if (userMenuGroup) userMenuGroup.classList.remove('open');
                });
            });
        });

})();

/* ===== Inline scripts extracted from admin/progress_monitoring.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/progress_monitoring.php')) return;
// ============================================
        // LOGOUT CONFIRMATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '/admin/logout.php';
                        }
                    });
                    return false;
                };
            }
        });

        // Dropdown toggle handlers - run immediately
        const projectRegToggle = document.getElementById('projectRegToggle');
        const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
        const contractorsToggle = document.getElementById('contractorsToggle');
        const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
        
        if (projectRegToggle && projectRegGroup) {
            projectRegToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                projectRegGroup.classList.toggle('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        }
        
        if (contractorsToggle && contractorsGroup) {
            contractorsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                contractorsGroup.classList.toggle('open');
                if (projectRegGroup) projectRegGroup.classList.remove('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-item-group')) {
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            }
        });
        
        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        });

})();

/* ===== Inline scripts extracted from admin/budget_resources.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/budget_resources.php')) return;
// ============================================
        // LOGOUT CONFIRMATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '/admin/logout.php';
                        }
                    });
                    return false;
                };
            }
        });

        // Dropdown toggle handlers - run immediately
        const projectRegToggle = document.getElementById('projectRegToggle');
        const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
        const contractorsToggle = document.getElementById('contractorsToggle');
        const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
        
        if (projectRegToggle && projectRegGroup) {
            projectRegToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                projectRegGroup.classList.toggle('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        }
        
        if (contractorsToggle && contractorsGroup) {
            contractorsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                contractorsGroup.classList.toggle('open');
                if (projectRegGroup) projectRegGroup.classList.remove('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-item-group')) {
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            }
        });
        
        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        });

})();

/* ===== Inline scripts extracted from contractors/contractors.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/contractors.php')) return;
// ============================================
        // LOGOUT CONFIRMATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '/admin/logout.php';
                        }
                    });
                    return false;
                };
            }
        });

        // Set active submenu item based on current URL
        const currentPage = window.location.pathname;
        const currentFileName = currentPage.split('/').pop() || 'index.php';
        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.classList.remove('active');
            const href = item.getAttribute('href');
            const hrefFileName = href.split('/').pop();
            if (hrefFileName === currentFileName || currentPage.includes(hrefFileName)) {
                item.classList.add('active');
            }
        });

        // Dropdown toggle handlers - run immediately
        const projectRegToggle = document.getElementById('projectRegToggle');
        const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
        const contractorsToggle = document.getElementById('contractorsToggle');
        const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
        
        if (projectRegToggle && projectRegGroup) {
            projectRegToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                projectRegGroup.classList.toggle('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        }
        
        if (contractorsToggle && contractorsGroup) {
            contractorsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                contractorsGroup.classList.toggle('open');
                if (projectRegGroup) projectRegGroup.classList.remove('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-item-group')) {
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            }
        });
        
        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        });

})();

/* ===== Inline scripts extracted from contractors/registered_contractors.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/registered_contractors.php')) return;
// ============================================
        // LOGOUT CONFIRMATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '/admin/logout.php';
                        }
                    });
                    return false;
                };
            }
        });

        // Sidebar toggle handlers
        const sidebarToggle = document.getElementById('toggleSidebar');
        const sidebarShow = document.getElementById('toggleSidebarShow');
        function toggleSidebarHandler(e) {
            e.preventDefault();
            const navbar = document.getElementById('navbar');
            const toggleBtn = document.getElementById('showSidebarBtn');
            if (navbar) navbar.classList.toggle('hidden');
            document.body.classList.toggle('sidebar-hidden');
            if (toggleBtn) toggleBtn.classList.toggle('show');
        }
        if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebarHandler);
        if (sidebarShow) sidebarShow.addEventListener('click', toggleSidebarHandler);

        let allContractors = [];
        let allProjects = [];

        // Load contractors from database
        function loadContractors() {
            console.log('loadContractors called');
            const url = getApiUrl('contractors/registered_contractors.php?action=load_contractors&_=' + Date.now());
            console.log('Fetching from:', url);
            
            fetch(url)
                .then(res => {
                    console.log('Response received, status:', res.status);
                    console.log('Response ok:', res.ok);
                    return res.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const contractors = JSON.parse(text);
                        console.log('Contractors parsed:', contractors);
                        allContractors = contractors;
                        renderContractors(contractors);
                    } catch (e) {
                        console.error('JSON parse error:', e, 'Text was:', text);
                    }
                })
                .catch(error => {
                    console.error('Error loading contractors:', error);
                    const tbody = document.querySelector('#contractorsTable tbody');
                    if (tbody) tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#c00;">Error: ' + error.message + '</td></tr>';
                });
        }

        // Load projects from database
        function loadProjects() {
            console.log('loadProjects called');
            const url = getApiUrl('contractors/registered_contractors.php?action=load_projects&_=' + Date.now());
            console.log('Fetching projects from:', url);
            
            fetch(url)
                .then(res => {
                    console.log('Projects response status:', res.status);
                    return res.text();
                })
                .then(text => {
                    console.log('Projects response text:', text);
                    try {
                        const projects = JSON.parse(text);
                        console.log('Projects parsed:', projects);
                        allProjects = projects;
                        renderProjects(projects);
                    } catch (e) {
                        console.error('Projects JSON parse error:', e);
                    }
                })
                .catch(error => console.error('Error loading projects:', error));
        }

        // Search and filter functionality
        const searchInput = document.getElementById('searchContractors');
        const statusFilter = document.getElementById('filterStatus');

        function filterContractors() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusTerm = statusFilter.value;

            const filtered = allContractors.filter(c => {
                const matchesSearch = !searchTerm || 
                    (c.company || '').toLowerCase().includes(searchTerm) ||
                    (c.license || '').toLowerCase().includes(searchTerm) ||
                    (c.email || '').toLowerCase().includes(searchTerm);
                
                const matchesStatus = !statusTerm || c.status === statusTerm;
                
                return matchesSearch && matchesStatus;
            });

            renderContractors(filtered);
        }

        if (searchInput) searchInput.addEventListener('input', filterContractors);
        if (statusFilter) statusFilter.addEventListener('change', filterContractors);

        // Render contractors table
        function renderContractors(contractors) {
            console.log('renderContractors called with:', contractors);
            const tbody = document.querySelector('#contractorsTable tbody');
            if (!tbody) {
                console.error('Cannot find #contractorsTable tbody');
                return;
            }
            tbody.innerHTML = '';
            
            if (!contractors || !contractors.length) {
                console.log('No contractors to display');
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:20px; color:#6b7280;">No contractors found.</td></tr>';
                return;
            }
            console.log('Rendering', contractors.length, 'contractors');

            contractors.forEach(c => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${c.company || 'N/A'}</strong></td>
                    <td>${c.license || 'N/A'}</td>
                    <td>${c.email || c.phone || 'N/A'}</td>
                    <td><span class="status-badge ${(c.status || '').toLowerCase()}">${c.status || 'N/A'}</span></td>
                    <td>${c.rating ? '⭐ ' + c.rating + '/5' : '—'}</td>
                    <td>
                        <button class="btn-view-projects" data-id="${c.id}" style="padding: 8px 14px; background: #10b981; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85rem; transition: all 0.3s ease;">View Projects</button>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-assign" data-id="${c.id}">Assign Projects</button>
                            <button class="btn-delete" data-id="${c.id}">🗑️ Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Wire up view projects buttons
            document.querySelectorAll('#contractorsTable .btn-view-projects').forEach(btn => {
                btn.addEventListener('click', function() {
                    const contractorId = this.dataset.id;
                    const contractorRow = this.closest('tr');
                    const contractorName = contractorRow.querySelector('td:nth-child(1)').textContent;
                    openProjectsModal(contractorId, contractorName);
                });
            });

            // Wire up assign buttons
            document.querySelectorAll('#contractorsTable .btn-assign').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Assign button clicked');
                    const contractorId = this.dataset.id;
                    const contractorRow = this.closest('tr');
                    const contractorName = contractorRow.querySelector('td:nth-child(1)').textContent;
                    console.log('Opening modal for contractor:', contractorName, 'ID:', contractorId);
                    openAssignModal(contractorId, contractorName);
                });
            });

            // Wire up delete buttons
            document.querySelectorAll('#contractorsTable .btn-delete').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const contractorRow = this.closest('tr');
                    const contractorName = contractorRow.querySelector('td:nth-child(1)').textContent;
                    
                    showConfirmation({
                        title: 'Delete Contractor',
                        message: 'This contractor and all associated records will be permanently deleted. This action cannot be undone.',
                        itemName: `Contractor: ${contractorName}`,
                        icon: '🗑️',
                        confirmText: 'Delete Permanently',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            fetch(getApiUrl('contractors/registered_contractors.php'), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=delete_contractor&id=${encodeURIComponent(id)}`
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    loadContractors();
                                }
                            });
                        }
                    });
                });
            });
        }

        // Render projects table
        function renderProjects(projects) {
            const tbody = document.querySelector('#projectsTable tbody');
            if (!tbody) {
                console.error('Projects table tbody not found');
                return;
            }
            tbody.innerHTML = '';
            
            if (!projects || !projects.length) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#6b7280;">No projects available.</td></tr>';
                return;
            }

            projects.forEach(p => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${p.code || ''}</td>
                    <td>${p.name || ''}</td>
                    <td>${p.type || ''}</td>
                    <td>${p.sector || ''}</td>
                    <td><span class="status-badge ${(p.status || '').toLowerCase()}">${p.status || 'N/A'}</span></td>
                `;
                tbody.appendChild(row);
            });
        }

        // Dropdown navigation toggle
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

        // Assignment Modal Functions
        function openAssignModal(contractorId, contractorName) {
            console.log('openAssignModal called with:', contractorId, contractorName);
            const modal = document.getElementById('assignmentModal');
            console.log('Modal element:', modal);
            if (!modal) {
                console.error('assignmentModal not found in DOM');
                return;
            }
            document.getElementById('assignContractorId').value = contractorId;
            document.getElementById('assignmentTitle').textContent = `Assign "${contractorName}" to Projects`;
            
            // Load available projects
            loadProjectsForAssignment(contractorId);
            modal.style.display = 'flex';
            console.log('Modal display set to flex');
        }

        function closeAssignModal() {
            document.getElementById('assignmentModal').style.display = 'none';
        }

        function openProjectsModal(contractorId, contractorName) {
            console.log('openProjectsModal called for:', contractorName);
            const modal = document.getElementById('projectsViewModal');
            if (!modal) {
                console.error('projectsViewModal not found');
                return;
            }
            
            document.getElementById('projectsViewTitle').textContent = `Projects Assigned to ${contractorName}`;
            const projectsList = document.getElementById('projectsViewList');
            projectsList.innerHTML = '<p style="text-align: center; color: #999;">Loading projects...</p>';
            
            // Get assigned projects
            fetch(getApiUrl(`contractors/registered_contractors.php?action=get_assigned_projects&contractor_id=${contractorId}`))
                .then(res => res.text())
                .then(text => {
                    try {
                        const projects = JSON.parse(text);
                        console.log('Assigned projects:', projects);
                        projectsList.innerHTML = '';
                        
                        if (!projects || projects.length === 0) {
                            projectsList.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">No Projects Assigned</p>';
                            return;
                        }
                        
                        projects.forEach(p => {
                            const div = document.createElement('div');
                            div.style.cssText = 'padding: 12px 16px; margin: 10px 0; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 6px;';
                            div.innerHTML = `
                                <div><strong>${p.code}</strong> - ${p.name}</div>
                                <small style="color: #666;">${p.type || 'N/A'} • ${p.sector || 'N/A'}</small>
                            `;
                            projectsList.appendChild(div);
                        });
                    } catch (e) {
                        console.error('Error parsing projects:', e);
                        projectsList.innerHTML = '<p style="color: red;">Error loading projects</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    projectsList.innerHTML = '<p style="color: red;">Error: ' + error.message + '</p>';
                });
            
            modal.style.display = 'flex';
        }

        function closeProjectsModal() {
            const modal = document.getElementById('projectsViewModal');
            if (modal) modal.style.display = 'none';
        }

        function loadProjectsForAssignment(contractorId) {
            console.log('loadProjectsForAssignment called with contractorId:', contractorId);
            const projectsList = document.getElementById('projectsList');
            projectsList.innerHTML = '<p style="text-align: center; color: #999;">Loading projects...</p>';
            
            // Get already assigned projects
            fetch(getApiUrl(`contractors/registered_contractors.php?action=get_assigned_projects&contractor_id=${contractorId}`))
                .then(res => res.text())
                .then(text => {
                    console.log('Assigned projects response:', text);
                    try {
                        const assignedProjects = JSON.parse(text);
                        const assignedIds = assignedProjects.map(p => p.id);
                        console.log('Assigned IDs:', assignedIds);
                        
                        // Get all available projects
                        return fetch(getApiUrl('contractors/registered_contractors.php?action=load_projects'))
                            .then(res => res.text())
                            .then(text => {
                                console.log('All projects response:', text);
                                const projects = JSON.parse(text);
                                console.log('Projects parsed:', projects);
                                
                                projectsList.innerHTML = '';
                                
                                if (!projects || !projects.length) {
                                    projectsList.innerHTML = '<p style="text-align: center; color: #999;">No projects available</p>';
                                    return;
                                }
                                
                                projects.forEach(p => {
                                    const projectId = String(p.id);
                                    const isAssigned = assignedIds.map(id => String(id)).includes(projectId);
                                    console.log(`Project ${projectId}: assigned=${isAssigned}`);
                                    
                                    const div = document.createElement('div');
                                    div.style.cssText = 'padding: 10px; margin: 8px 0; background: #f9fafb; border-radius: 6px; border-left: 3px solid ' + (isAssigned ? '#10b981' : '#e5e7eb') + ';';
                                    div.innerHTML = `
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <input type="checkbox" class="project-checkbox" data-project-id="${projectId}" ${isAssigned ? 'checked' : ''} style="width: 18px; height: 18px; cursor: pointer;">
                                            <div>
                                                <strong>${p.code}</strong> - ${p.name}
                                                <br><small style="color: #999;">${p.type || 'N/A'} • ${p.sector || 'N/A'}</small>
                                            </div>
                                        </div>
                                    `;
                                    projectsList.appendChild(div);
                                });
                            });
                    } catch (e) {
                        console.error('JSON parse error for assigned projects:', e, 'Text:', text);
                        projectsList.innerHTML = '<p style="text-align: center; color: red;">Error loading assigned projects</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading assigned projects:', error);
                    projectsList.innerHTML = '<p style="text-align: center; color: red;">Error: ' + error.message + '</p>';
                });
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('assignmentModal');
            if (e.target === modal) {
                closeAssignModal();
            }
        });

        // Save assignments handler function
        async function saveAssignmentsHandler() {
            console.log('=== SAVE ASSIGNMENTS HANDLER CALLED ===');
            const saveBtn = document.getElementById('saveAssignments');
            saveBtn.disabled = true;
            saveBtn.textContent = '⏳ Saving...';
            
            const contractorId = document.getElementById('assignContractorId').value;
            console.log('Contractor ID:', contractorId);
            
            if (!contractorId) {
                console.error('No contractor ID');
                alert('Error: No contractor selected');
                saveBtn.disabled = false;
                saveBtn.textContent = '✓ Save Assignments';
                return;
            }
            
            const checkboxes = document.querySelectorAll('.project-checkbox');
            console.log('Found', checkboxes.length, 'checkboxes');
            
            if (checkboxes.length === 0) {
                console.error('No checkboxes found');
                alert('No projects to assign');
                saveBtn.disabled = false;
                saveBtn.textContent = '✓ Save Assignments';
                return;
            }

            let successCount = 0;
            let failCount = 0;
            
            // Process each checkbox
            for (let i = 0; i < checkboxes.length; i++) {
                const checkbox = checkboxes[i];
                const projectId = String(checkbox.getAttribute('data-project-id')).trim();
                const isChecked = checkbox.checked;
                
                console.log(`Processing checkbox ${i}: projectId="${projectId}", checked=${isChecked}`);
                
                if (!projectId) {
                    console.error(`Checkbox ${i} has no projectId`);
                    failCount++;
                    continue;
                }

                const action = isChecked ? 'assign_contractor' : 'unassign_contractor';
                const body = `action=${action}&contractor_id=${contractorId}&project_id=${projectId}`;
                
                console.log(`Sending request: ${action}`, body);
                
                try {
                    const response = await fetch(getApiUrl('contractors/registered_contractors.php'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body
                    });
                    
                    const text = await response.text();
                    console.log(`Response for ${action}:`, text);
                    
                    const data = JSON.parse(text);
                    if (data.success) {
                        successCount++;
                        console.log(`Success: ${action} project ${projectId}`);
                    } else {
                        failCount++;
                        console.error(`Failed: ${action} project ${projectId}:`, data.message);
                    }
                } catch (err) {
                    failCount++;
                    console.error(`Error processing ${action}:`, err);
                }
            }

            console.log('=== ALL PROCESSING COMPLETE ===');
            console.log('Success:', successCount, 'Fail:', failCount);
            
            // Show notification
            showSuccessNotification('✅ Assignments Saved!', `Successfully updated ${successCount} project(s)`);
            
            // Close and refresh
            setTimeout(() => {
                closeAssignModal();
                loadContractors();
            }, 1500);
            
            saveBtn.disabled = false;
            saveBtn.textContent = '✓ Save Assignments';
        }

        // Success notification function
        function showSuccessNotification(title, message) {
            const notification = document.createElement('div');
            notification.id = 'successNotification';
            notification.style.cssText = `
                position: fixed;
                top: 30px;
                right: 30px;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                padding: 20px 30px;
                border-radius: 12px;
                box-shadow: 0 10px 40px rgba(16, 185, 129, 0.3);
                z-index: 2000;
                min-width: 300px;
                animation: slideIn 0.4s ease-out;
                font-family: 'Poppins', sans-serif;
            `;
            
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="font-size: 24px;">✓</div>
                    <div>
                        <div style="font-weight: 700; font-size: 16px;">${title}</div>
                        <div style="font-size: 14px; opacity: 0.95; margin-top: 4px;">${message}</div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Add animation styles
            const style = document.createElement('style');
            if (!document.getElementById('notificationStyles')) {
                style.id = 'notificationStyles';
                style.textContent = `
                    @keyframes slideIn {
                        from {
                            transform: translateX(400px);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    @keyframes slideOut {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(400px);
                            opacity: 0;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            // Auto remove after 4 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.4s ease-out';
                setTimeout(() => {
                    notification.remove();
                }, 400);
            }, 4000);
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                loadContractors();
                loadProjects();
            });
        } else {
            loadContractors();
            loadProjects();
        }

})();

/* ===== Inline scripts extracted from admin/project_registration.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/project_registration.php')) return;
// ============================================
        // LOGOUT CONFIRMATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '/admin/logout.php';
                        }
                    });
                    return false;
                };
            }
        });

        // Set active submenu item based on current URL
        const currentPage = window.location.pathname;
        const currentFileName = currentPage.split('/').pop() || 'index.php';
        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.classList.remove('active');
            const href = item.getAttribute('href');
            const hrefFileName = href.split('/').pop();
            if (hrefFileName === currentFileName || currentPage.includes(hrefFileName)) {
                item.classList.add('active');
            }
        });

        // --- AJAX-based Project Registration ---
        const form = document.getElementById('projectForm');
        const msg = document.getElementById('formMessage');
        const resetBtn = document.getElementById('resetBtn');
        let editProjectId = null;

        // Sidebar toggle handlers (unchanged)
        const sidebarToggle = document.getElementById('toggleSidebar');
        const sidebarShow = document.getElementById('toggleSidebarShow');
        function toggleSidebarHandler(e) {
            e.preventDefault();
            const navbar = document.getElementById('navbar');
            const toggleBtn = document.getElementById('showSidebarBtn');
            if (navbar) navbar.classList.toggle('hidden');
            document.body.classList.toggle('sidebar-hidden');
            if (toggleBtn) toggleBtn.classList.toggle('show');
        }
        if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebarHandler);
        if (sidebarShow) sidebarShow.addEventListener('click', toggleSidebarHandler);

        // Load projects from DB
        function loadSavedProjects() {
            // Add cache-busting param to always get fresh data
            fetch(getApiUrl('admin/project_registration.php?action=load_projects&_=' + Date.now()))
                .then(res => res.json())
                .then(projects => {
                    console.log('Fetched projects:', projects); // DEBUG
                    const tbody = document.querySelector('#projectsTable tbody');
                    const projectCount = document.getElementById('projectCount');
                    
                    // Update project count
                    projectCount.textContent = `${projects.length} ${projects.length === 1 ? 'project' : 'projects'}`;
                    
                    tbody.innerHTML = '';
                    if (!projects.length) {
                        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:20px; color:#6b7280;">No projects registered yet.</td></tr>';
                        return;
                    }
                    projects.forEach((p, i) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${p.code || ''}</td>
                            <td>${p.name || ''}</td>
                            <td>${p.type || ''}</td>
                            <td>${p.sector || ''}</td>
                            <td>${p.priority || 'Medium'}</td>
                            <td>${p.status || 'Draft'}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-edit" data-id="${p.id}">Edit</button>
                                    <button class="btn-delete" data-id="${p.id}">Delete</button>
                                </div>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });

                    // Wire up delete buttons
                    document.querySelectorAll('.btn-delete').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const id = this.dataset.id;
                            const projectRow = this.closest('tr');
                            const projectName = projectRow.querySelector('td:nth-child(2)').textContent;
                            
                            showConfirmation({
                                title: 'Delete Project',
                                message: 'This project and all associated data will be permanently deleted. This action cannot be undone.',
                                itemName: `Project: ${projectName}`,
                                icon: '🗑️',
                                confirmText: 'Delete Permanently',
                                cancelText: 'Cancel',
                                onConfirm: () => {
                                    fetch(getApiUrl('admin/project_registration.php'), {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                        body: `action=delete_project&id=${encodeURIComponent(id)}`
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        msg.textContent = data.message;
                                        msg.style.display = 'block';
                                        msg.style.color = data.success ? '#dc2626' : '#f00';
                                        setTimeout(() => { msg.style.display = 'none'; }, 3000);
                                        loadSavedProjects();
                                    });
                                }
                            });
                        });
                    });

                    // Wire up edit buttons
                    document.querySelectorAll('.btn-edit').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const id = this.dataset.id;
                            // Find project in loaded list
                            const project = projects.find(p => p.id == id);
                            if (!project) return;
                            document.getElementById('projCode').value = project.code || '';
                            document.getElementById('projName').value = project.name || '';
                            document.getElementById('projType').value = project.type || '';
                            document.getElementById('projSector').value = project.sector || '';
                            document.getElementById('projDescription').value = project.description || '';
                            document.getElementById('projPriority').value = project.priority || 'Medium';
                            document.getElementById('province').value = project.province || '';
                            document.getElementById('barangay').value = project.barangay || '';
                            document.getElementById('projLocation').value = project.location || '';
                            document.getElementById('startDate').value = project.start_date || '';
                            document.getElementById('endDate').value = project.end_date || '';
                            document.getElementById('projDuration').value = project.duration_months || '';
                            document.getElementById('projBudget').value = project.budget || '';
                            document.getElementById('projManager').value = project.project_manager || '';
                            document.getElementById('status').value = project.status || 'Draft';
                            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            editProjectId = id;
                            const submitBtn = form.querySelector('button[type="submit"]');
                            submitBtn.innerHTML = 'Update Project';
                        });
                    });
                });
        }

        form.addEventListener('submit', function(e){
            e.preventDefault();
            const fd = new FormData();
            fd.append('action', 'save_project');
            fd.append('code', document.getElementById('projCode').value);
            fd.append('name', document.getElementById('projName').value);
            fd.append('type', document.getElementById('projType').value);
            fd.append('sector', document.getElementById('projSector').value);
            fd.append('description', document.getElementById('projDescription').value);
            fd.append('priority', document.getElementById('projPriority').value);
            fd.append('province', document.getElementById('province').value);
            fd.append('barangay', document.getElementById('barangay').value);
            fd.append('location', document.getElementById('projLocation').value);
            fd.append('start_date', document.getElementById('startDate').value);
            fd.append('end_date', document.getElementById('endDate').value);
            fd.append('duration_months', document.getElementById('projDuration').value);
            fd.append('budget', document.getElementById('projBudget').value);
            fd.append('project_manager', document.getElementById('projManager').value);
            fd.append('status', document.getElementById('status').value);
            if (editProjectId) {
                fd.append('id', editProjectId);
            }
            fetch(getApiUrl('admin/project_registration.php'), {
                method: 'POST',
                body: fd
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('HTTP Error: ' + res.status);
                }
                return res.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    const data = JSON.parse(text);
                    msg.textContent = data.message;
                    msg.style.display = 'block';
                    msg.style.color = data.success ? '#0b5' : '#f00';
                    if (data.success) {
                        form.reset();
                        editProjectId = null;
                        const submitBtn = form.querySelector('button[type="submit"]');
                        submitBtn.innerHTML = 'Create Project';
                        // Reload the projects table without full page reload
                        loadSavedProjects();
                    }
                    setTimeout(() => { msg.style.display = 'none'; }, 3000);
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Raw response:', text);
                    msg.textContent = 'Error: Invalid response from server. Check browser console.';
                    msg.style.display = 'block';
                    msg.style.color = '#f00';
                    setTimeout(() => { msg.style.display = 'none'; }, 5000);
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                msg.textContent = 'Error: ' + error.message;
                msg.style.display = 'block';
                msg.style.color = '#f00';
                setTimeout(() => { msg.style.display = 'none'; }, 3000);
            });
        });

        resetBtn.addEventListener('click', function(){
            form.reset();
            msg.style.display = 'none';
            editProjectId = null;
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = 'Create Project';
        });

        // Load projects on page load
        document.addEventListener('DOMContentLoaded', function(){
            loadSavedProjects();
        });

        // Dropdown toggle handlers
        const projectRegToggle = document.getElementById('projectRegToggle');
        const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
        const contractorsToggle = document.getElementById('contractorsToggle');
        const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;

        if (projectRegToggle && projectRegGroup) {
            projectRegToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                projectRegGroup.classList.toggle('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        }

        if (contractorsToggle && contractorsGroup) {
            contractorsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                contractorsGroup.classList.toggle('open');
                if (projectRegGroup) projectRegGroup.classList.remove('open');
            });
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-item-group')) {
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            }
        });

        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        });

})();

/* ===== Inline scripts extracted from admin/registered_projects.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/registered_projects.php')) return;
// ============================================
        // LOGOUT CONFIRMATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '/admin/logout.php';
                        }
                    });
                    return false;
                };
            }
        });

        // Sidebar toggle handlers
        const sidebarToggle = document.getElementById('toggleSidebar');
        const sidebarShow = document.getElementById('toggleSidebarShow');
        
        function toggleSidebarHandler(e) {
            e.preventDefault();
            const navbar = document.getElementById('navbar');
            const toggleBtn = document.getElementById('showSidebarBtn');
            if (navbar) navbar.classList.toggle('hidden');
            document.body.classList.toggle('sidebar-hidden');
            if (toggleBtn) toggleBtn.classList.toggle('show');
        }
        
        if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebarHandler);
        if (sidebarShow) sidebarShow.addEventListener('click', toggleSidebarHandler);

        // Load projects from DB
        let allProjects = [];
        const msg = document.getElementById('formMessage');

        function loadProjects() {
            console.log('loadProjects called');
            console.log('getApiUrl available?', typeof window.getApiUrl);
            console.log('APP_ROOT value:', window.APP_ROOT);
            
            if (typeof window.getApiUrl !== 'function') {
                console.error('❌ getApiUrl function not available!');
                const tbody = document.querySelector('#projectsTable tbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#c00;">Error: API configuration not loaded. Please refresh the page.</td></tr>';
                return;
            }
            
            fetch(getApiUrl('admin/registered_projects.php?action=load_projects&_=' + Date.now()))
                .then(res => {
                    console.log('Response status:', res.status);
                    return res.json();
                })
                .then(projects => {
                    console.log('Projects loaded:', projects);
                    allProjects = projects;
                    renderProjects(projects);
                })
                .catch(error => {
                    console.error('Error loading projects:', error);
                    const tbody = document.querySelector('#projectsTable tbody');
                    if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#c00;">Error loading projects. Check console.</td></tr>';
                });
        }

        function renderProjects(projects = allProjects) {
            const tbody = document.querySelector('#projectsTable tbody');
            if (!tbody) {
                console.error('Table tbody not found');
                return;
            }
            tbody.innerHTML = '';
            
            if (!projects.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:20px; color:#6b7280;">No projects found.</td></tr>';
                return;
            }
            
            projects.forEach((p) => {
                const row = document.createElement('tr');
                const createdDate = p.created_at ? new Date(p.created_at).toLocaleDateString() : 'N/A';
                row.innerHTML = `
                    <td>${p.code || ''}</td>
                    <td>${p.name || ''}</td>
                    <td>${p.type || ''}</td>
                    <td>${p.sector || ''}</td>
                    <td>${p.priority || 'Medium'}</td>
                    <td>${p.status || 'Draft'}</td>
                    <td>${createdDate}</td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-delete" data-id="${p.id}">Delete</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });

            // Wire up delete buttons
            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const projectRow = this.closest('tr');
                    const projectName = projectRow.querySelector('td:nth-child(2)').textContent;
                    
                    showConfirmation({
                        title: 'Delete Project',
                        message: 'This project and all associated data will be permanently deleted. This action cannot be undone.',
                        itemName: `Project: ${projectName}`,
                        icon: '🗑️',
                        confirmText: 'Delete Permanently',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            fetch(getApiUrl('admin/registered_projects.php'), {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=delete_project&id=${encodeURIComponent(id)}`
                            })
                            .then(res => res.json())
                            .then(data => {
                                msg.textContent = data.message;
                                msg.style.color = data.success ? '#16a34a' : '#dc2626';
                                msg.style.display = 'block';
                                setTimeout(() => { msg.style.display = 'none'; }, 3000);
                                loadProjects();
                            });
                        }
                    });
                });
            });
        }

        // Search and filter functionality
        const searchInput = document.getElementById('searchProjects');
        const statusFilter = document.getElementById('filterStatus');

        function filterProjects() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusTerm = statusFilter.value;

            const filtered = allProjects.filter(p => {
                const matchesSearch = !searchTerm || 
                    (p.code || '').toLowerCase().includes(searchTerm) ||
                    (p.name || '').toLowerCase().includes(searchTerm) ||
                    (p.sector || '').toLowerCase().includes(searchTerm);
                
                const matchesStatus = !statusTerm || p.status === statusTerm;
                
                return matchesSearch && matchesStatus;
            });

            renderProjects(filtered);
        }

        if (searchInput) searchInput.addEventListener('input', filterProjects);
        if (statusFilter) statusFilter.addEventListener('change', filterProjects);

        // Export CSV functionality
        const exportCsvBtn = document.getElementById('exportCsv');
        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', () => {
                if (!allProjects.length) {
                    alert('No projects to export');
                    return;
                }

                const keys = ['code', 'name', 'type', 'sector', 'priority', 'status'];
                const headers = keys.map(k => k.charAt(0).toUpperCase() + k.slice(1)).join(',');
                const rows = allProjects.map(p => 
                    keys.map(k => `"${String(p[k] || '').replace(/"/g, '""')}"`)
                        .join(',')
                );
                
                const csv = [headers, ...rows].join('\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `projects_${new Date().toISOString().slice(0, 10)}.csv`;
                link.click();
            });
        }

        // Dropdown navigation toggle for Project Registration
        const projectRegToggle = document.getElementById('projectRegToggle');
        const projectNavItemGroup = projectRegToggle?.closest('.nav-item-group');
        
        if (projectRegToggle && projectNavItemGroup) {
            // Keep dropdown open by default
            projectNavItemGroup.classList.add('open');
            
            projectRegToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                projectNavItemGroup.classList.toggle('open');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!projectNavItemGroup.contains(e.target)) {
                    projectNavItemGroup.classList.remove('open');
                }
            });
        }

        // Dropdown navigation toggle for Contractors
        const contractorsToggle = document.getElementById('contractorsToggle');
        const contractorsNavItemGroup = contractorsToggle?.closest('.nav-item-group');
        
        if (contractorsToggle && contractorsNavItemGroup) {
            contractorsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                contractorsNavItemGroup.classList.toggle('open');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!contractorsNavItemGroup.contains(e.target)) {
                    contractorsNavItemGroup.classList.remove('open');
                }
            });
        }

        // Load projects when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadProjects);
        } else {
            loadProjects();
        }

})();

/* ===== Inline scripts extracted from project-prioritization/project-prioritization.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/project-prioritization.php')) return;
// ============================================
        // LOGOUT CONFIRMATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '/admin/logout.php';
                        }
                    });
                    return false;
                };
            }
        });

        // Initialize all modals to closed state
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('show');
        });

        // Modal Functions
        function openModal(modalId) {
            // Close all other modals first
            document.querySelectorAll('.modal.show').forEach(m => {
                m.classList.remove('show');
            });
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        }

        // Edit Modal Functions
        function openEditModal(modalId) {
            // Close all other modals first
            document.querySelectorAll('.modal.show').forEach(m => {
                m.classList.remove('show');
            });
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeEditModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal when clicking outside (only on the overlay, not the content)
        window.addEventListener('click', function(event) {
            // Only close if clicking on the modal overlay itself, not on content
            if (event.target.classList && event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
                document.body.style.overflow = 'auto';
            }
        }, true);

        // Prevent event bubbling from modal content
        document.querySelectorAll('.modal-content').forEach(content => {
            content.addEventListener('click', function(event) {
                event.stopPropagation();
            });
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
                document.body.style.overflow = 'auto';
            }
        });

        // Search functionality
        const searchInput = document.getElementById('fbSearch');
        const clearBtn = document.getElementById('clearSearch');
        const table = document.getElementById('inputsTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            Array.from(rows).forEach(row => {
                if (row.querySelector('.no-results')) return;

                const controlNum = row.cells[0]?.textContent.toLowerCase() || '';
                const name = row.cells[2]?.textContent.toLowerCase() || '';

                const matches = searchTerm === '' || 
                               controlNum.includes(searchTerm) || 
                               name.includes(searchTerm);

                row.style.display = matches ? '' : 'none';
                if (matches) visibleCount++;
            });

            // Show no results message if needed
            const noResultsRow = Array.from(rows).find(r => r.querySelector('.no-results'));
            if (noResultsRow) {
                noResultsRow.style.display = visibleCount === 0 ? '' : 'none';
            }
        }

        searchInput.addEventListener('input', filterTable);

        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterTable();
            searchInput.focus();
        });

        // Export CSV function
        document.getElementById('exportData').addEventListener('click', function() {
            let csv = 'Control Number,Date,Name,Subject,Category,Location,Status\n';
            
            Array.from(rows).forEach((row, index) => {
                if (row.querySelector('.no-results')) return;
                
                const cells = row.getElementsByTagName('td');
                if (cells.length > 0 && row.style.display !== 'none') {
                    const rowData = [
                        cells[0]?.textContent || '',
                        cells[1]?.textContent || '',
                        cells[2]?.textContent || '',
                        cells[3]?.textContent || '',
                        cells[4]?.textContent || '',
                        cells[5]?.textContent || '',
                        cells[6]?.textContent || ''
                    ];
                    csv += rowData.map(cell => `"${cell.trim()}"`).join(',') + '\n';
                }
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'feedback_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        });

        // Dropdown handlers
        const projectRegToggle = document.getElementById('projectRegToggle');
        const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
        const contractorsToggle = document.getElementById('contractorsToggle');
        const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
        
        if (projectRegToggle && projectRegGroup) {
            projectRegToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                projectRegGroup.classList.toggle('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        }
        
        if (contractorsToggle && contractorsGroup) {
            contractorsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                contractorsGroup.classList.toggle('open');
                if (projectRegGroup) projectRegGroup.classList.remove('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-item-group')) {
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            }
        });
        
        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        });

})();

/* ===== Inline scripts extracted from admin/tasks_milestones.php ===== */
(function(){
  var p = (window.location.pathname || "").replace(/\\\\/g, "/");
  if (!p.endsWith('/admin/tasks_milestones.php')) return;
// ============================================
        // LOGOUT CONFIRMATION
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showConfirmation({
                        title: 'Logout Confirmation',
                        message: 'Are you sure you want to logout?',
                        icon: '👋',
                        confirmText: 'Logout',
                        cancelText: 'Cancel',
                        onConfirm: () => {
                            window.location.href = '/admin/logout.php';
                        }
                    });
                    return false;
                };
            }
        });

        // Dropdown toggle handlers - run immediately
        const projectRegToggle = document.getElementById('projectRegToggle');
        const projectRegGroup = projectRegToggle ? projectRegToggle.closest('.nav-item-group') : null;
        const contractorsToggle = document.getElementById('contractorsToggle');
        const contractorsGroup = contractorsToggle ? contractorsToggle.closest('.nav-item-group') : null;
        
        if (projectRegToggle && projectRegGroup) {
            projectRegToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                projectRegGroup.classList.toggle('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        }
        
        if (contractorsToggle && contractorsGroup) {
            contractorsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                contractorsGroup.classList.toggle('open');
                if (projectRegGroup) projectRegGroup.classList.remove('open');
            });
        }
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-item-group')) {
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            }
        });
        
        document.querySelectorAll('.nav-submenu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                if (projectRegGroup) projectRegGroup.classList.remove('open');
                if (contractorsGroup) contractorsGroup.classList.remove('open');
            });
        });

})();


/* ===== Generic data-onclick handler (replaces inline onclick) ===== */
document.addEventListener('click', function (event) {
  var el = event.target.closest('[data-onclick]');
  if (!el) return;

  var code = el.getAttribute('data-onclick');
  if (!code) return;

  try {
    var fn = new Function('event', code);
    var result = fn.call(el, event);
    if (result === false) {
      event.preventDefault();
      event.stopPropagation();
    }
  } catch (err) {
    console.error('data-onclick execution error:', err, code);
  }
});




