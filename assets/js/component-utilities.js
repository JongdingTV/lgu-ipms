/**
 * LGU IPMS - Component Utilities
 * Sidebar Toggle, Dropdowns, Logout, Modal, Toast Notifications
 */

'use strict';

// ============================================
// DROPDOWN MANAGER
// ============================================

class DropdownManager {
    constructor() {
        this.dropdowns = [];
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => this.setupDropdowns());
    }

    setupDropdowns() {
        const dropdownGroups = document.querySelectorAll('.nav-dropdown, .nav-item-group');
        
        dropdownGroups.forEach((group) => {
            const toggle = group.querySelector('.nav-dropdown-toggle, .nav-main-item');
            const menu = group.querySelector('.nav-dropdown-menu, .nav-submenu');
            
            if (toggle && menu) {
                toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleDropdown(group, toggle);
                });
                
                // Keyboard navigation
                toggle.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        this.toggleDropdown(group, toggle);
                    }
                });
            }
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            dropdownGroups.forEach((group) => {
                if (!group.contains(e.target)) {
                    group.classList.remove('open');
                }
            });
        });
    }

    toggleDropdown(dropdownEl, toggleEl) {
        // Close other dropdowns at same level
        const parent = toggleEl.closest('.nav-links') || toggleEl.parentElement.parentElement;
        if (parent) {
            parent.querySelectorAll('.nav-dropdown, .nav-item-group').forEach((el) => {
                if (el !== dropdownEl && el.classList.contains('open')) {
                    el.classList.remove('open');
                }
            });
        }

        // Toggle this one
        dropdownEl.classList.toggle('open');
    }

    closeAll() {
        document.querySelectorAll('.nav-dropdown.open, .nav-item-group.open').forEach((el) => {
            el.classList.remove('open');
        });
    }
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.dropdownManager = new DropdownManager();
    });
} else {
    window.dropdownManager = new DropdownManager();
}

// ============================================
// LOGOUT CONFIRMATION MANAGER
// ============================================

class LogoutConfirmationManager {
    constructor() {
        this.modal = null;
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupLogout());
        } else {
            this.setupLogout();
        }
    }

    setupLogout() {
        // Wire up all logout buttons/links
        const logoutSelectors = [
            '.nav-logout',
            '.logout-btn',
            '[data-action="logout"]',
            'a[href*="logout"]'
        ];

        logoutSelectors.forEach((selector) => {
            document.querySelectorAll(selector).forEach((el) => {
                // Only add if it's actually for logout
                if (el.href && el.href.includes('logout')) {
                    el.addEventListener('click', (e) => this.handleLogoutClick(e, el));
                }
            });
        });
    }

    handleLogoutClick(e, element) {
        e.preventDefault();
        e.stopPropagation();

        const logoutUrl = element.href || element.getAttribute('data-logout-url');

        ModalManager.showConfirmation({
            title: 'Confirm Logout',
            message: 'You will be logged out of your account. Are you sure?',
            icon: '🚪',
            confirmButtonText: 'Logout',
            cancelButtonText: 'Cancel',
            confirmButtonClass: 'modal-btn-danger',
            onConfirm: () => {
                // Disable next logout attempts during redirect
                element.disabled = true;
                window.location.href = logoutUrl;
            }
        });
    }
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.logoutManager = new LogoutConfirmationManager();
    });
} else {
    window.logoutManager = new LogoutConfirmationManager();
}

// ============================================
// MODAL MANAGER (Confirmation & Custom Modals)
// ============================================

class ModalManager {
    static backdrop = null;
    static currentModal = null;

    static init() {
        // Create backdrop
        if (!this.backdrop) {
            this.backdrop = document.createElement('div');
            this.backdrop.className = 'modal-backdrop';
            document.body.appendChild(this.backdrop);

            // Close modal when clicking backdrop
            this.backdrop.addEventListener('click', () => this.closeCurrentModal());
        }
    }

    static showConfirmation(options = {}) {
        const {
            title = 'Confirm',
            message = 'Are you sure?',
            icon = '⚠️',
            itemName = null,
            confirmButtonText = 'Confirm',
            cancelButtonText = 'Cancel',
            confirmButtonClass = '',
            onConfirm = () => {},
            onCancel = () => {}
        } = options;

        this.init();

        let modal = document.getElementById('confirmationModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'confirmationModal';
            modal.className = 'confirmation-modal';
            document.body.appendChild(modal);
        }

        modal.innerHTML = `
            <div class="confirmation-content">
                <div class="confirmation-icon">${icon}</div>
                <h2 class="confirmation-title">${title}</h2>
                <p class="confirmation-message">${message}</p>
                ${itemName ? `<div class="confirmation-item">${itemName}</div>` : ''}
                <div class="confirmation-buttons">
                    <button class="confirmation-btn btn-confirm-cancel" id="modalBtnCancel">${cancelButtonText}</button>
                    <button class="confirmation-btn btn-confirm-delete ${confirmButtonClass}" id="modalBtnConfirm">${confirmButtonText}</button>
                </div>
            </div>
        `;

        const confirmBtn = document.getElementById('modalBtnConfirm');
        const cancelBtn = document.getElementById('modalBtnCancel');

        confirmBtn.addEventListener('click', () => {
            this.closeCurrentModal();
            onConfirm();
        });

        cancelBtn.addEventListener('click', () => {
            this.closeCurrentModal();
            onCancel();
        });

        // Close on Escape key
        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                this.closeCurrentModal();
                document.removeEventListener('keydown', handleKeyDown);
            }
        };
        document.addEventListener('keydown', handleKeyDown);

        // Show modal
        this.currentModal = modal;
        this.backdrop.classList.add('show');
        modal.classList.add('show');

        // Focus confirm button
        confirmBtn.focus();
    }

    static closeCurrentModal() {
        if (this.currentModal) {
            this.currentModal.classList.remove('show');
            this.currentModal = null;
        }
        if (this.backdrop) {
            this.backdrop.classList.remove('show');
        }
    }
}

// Make available globally
window.ModalManager = ModalManager;

// ============================================
// TOAST NOTIFICATION SYSTEM
// ============================================

class ToastManager {
    constructor() {
        this.container = null;
        this.toasts = [];
        this.init();
    }

    init() {
        // Create toast container
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    }

    show(options = {}) {
        const {
            title = 'Notification',
            message = '',
            type = 'info', // success, error, warning, info
            duration = 4000,
            icon = this.getIconForType(type),
            action = null,
            actionText = 'Undo'
        } = options;

        this.init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let actionHtml = '';
        if (action) {
            actionHtml = `<button class="toast-action">${actionText}</button>`;
        }

        toast.innerHTML = `
            <div class="toast-icon">${icon}</div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                ${message ? `<div class="toast-message">${message}</div>` : ''}
            </div>
            <button class="toast-close" aria-label="Close">&times;</button>
            ${actionHtml}
        `;

        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.remove(toast));

        if (action) {
            const actionBtn = toast.querySelector('.toast-action');
            actionBtn.addEventListener('click', () => {
                action();
                this.remove(toast);
            });
        }

        this.container.appendChild(toast);
        this.toasts.push(toast);

        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => this.remove(toast), duration);
        }

        return toast;
    }

    remove(toastEl) {
        toastEl.classList.add('removing');
        setTimeout(() => {
            toastEl.remove();
            this.toasts = this.toasts.filter((t) => t !== toastEl);
        }, 300);
    }

    success(title, message = '', duration = 3000) {
        return this.show({ title, message, type: 'success', duration, icon: '✓' });
    }

    error(title, message = '', duration = 5000) {
        return this.show({ title, message, type: 'error', duration, icon: '✕' });
    }

    warning(title, message = '', duration = 4000) {
        return this.show({ title, message, type: 'warning', duration, icon: '⚠' });
    }

    info(title, message = '', duration = 3000) {
        return this.show({ title, message, type: 'info', duration, icon: 'ℹ' });
    }

    getIconForType(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }

    clear() {
        this.toasts.forEach((toast) => this.remove(toast));
    }
}

// Initialize and make globally available
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.toastManager = new ToastManager();
    });
} else {
    window.toastManager = new ToastManager();
}

// Convenience functions
window.showToast = (title, message, type = 'info') => {
    if (!window.toastManager) window.toastManager = new ToastManager();
    return window.toastManager.show({ title, message, type });
};

window.showSuccess = (title, message) => {
    if (!window.toastManager) window.toastManager = new ToastManager();
    return window.toastManager.success(title, message);
};

window.showError = (title, message) => {
    if (!window.toastManager) window.toastManager = new ToastManager();
    return window.toastManager.error(title, message);
};

// ============================================
// IMPROVED SIDEBAR TOGGLE
// ============================================

class SidebarToggleManager {
    constructor() {
        this.sidebarHidden = localStorage.getItem('sidebar-hidden') === 'true';
        this.init();
    }

    init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
    }

    setup() {
        // Apply saved state
        if (this.sidebarHidden) {
            document.body.classList.add('sidebar-hidden');
        }

        // Find toggle buttons inside sidebar
        const togglesBtnInSidebar = document.querySelectorAll('.sidebar-toggle-btn');
        togglesBtnInSidebar.forEach((btn) => {
            btn.addEventListener('click', (e) => this.toggle(e));
        });

        // Floating toggle button (shown when sidebar is hidden)
        const toggleBtn = document.querySelector('.sidebar-toggle-wrapper .sidebar-toggle-btn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', (e) => this.toggle(e));
        }

        // Navbar menu icon (mobile/hidden sidebar)
        const navbarMenuIcon = document.getElementById('navbarMenuIcon');
        if (navbarMenuIcon) {
            navbarMenuIcon.addEventListener('click', (e) => this.toggle(e));
        }

        // Keyboard shortcut (Alt+S)
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                this.toggle();
            }
        });
    }

    toggle(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        document.body.classList.toggle('sidebar-hidden');
        this.sidebarHidden = document.body.classList.contains('sidebar-hidden');
        localStorage.setItem('sidebar-hidden', this.sidebarHidden.toString());

        // Close dropdowns when toggling sidebar
        if (window.dropdownManager) {
            window.dropdownManager.closeAll();
        }
    }
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.sidebarToggleManager = new SidebarToggleManager();
    });
} else {
    window.sidebarToggleManager = new SidebarToggleManager();
}

// ============================================
// FORM VALIDATION HELPER
// ============================================

class FormValidator {
    static validate(form, rules) {
        const errors = {};

        for (const [fieldName, fieldRules] of Object.entries(rules)) {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (!field) continue;

            const value = field.value.trim();

            if (fieldRules.required && !value) {
                errors[fieldName] = fieldRules.required;
                this.markFieldError(field, fieldRules.required);
            } else if (fieldRules.minLength && value.length < fieldRules.minLength) {
                errors[fieldName] = fieldRules.minLength;
                this.markFieldError(field, fieldRules.minLength);
            } else if (fieldRules.pattern && !fieldRules.pattern.test(value)) {
                errors[fieldName] = fieldRules.pattern.message || 'Invalid format';
                this.markFieldError(field, fieldRules.pattern.message);
            } else {
                this.clearFieldError(field);
            }
        }

        return errors;
    }

    static markFieldError(field, message) {
        field.classList.add('is-invalid');
        let errorDiv = field.nextElementSibling;
        if (!errorDiv || !errorDiv.classList.contains('error-message')) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            field.parentNode.insertBefore(errorDiv, field.nextSibling);
        }
        errorDiv.textContent = message;
    }

    static clearFieldError(field) {
        field.classList.remove('is-invalid');
        const errorDiv = field.nextElementSibling;
        if (errorDiv && errorDiv.classList.contains('error-message')) {
            errorDiv.remove();
        }
    }

    static clearAllErrors(form) {
        form.querySelectorAll('.is-invalid').forEach((field) => {
            this.clearFieldError(field);
        });
    }
}

window.FormValidator = FormValidator;

// ============================================
// UTILITY: LOCAL STORAGE MANAGEMENT
// ============================================

const Storage = {
    set(key, value, expireMs = null) {
        const data = {
            value: value,
            timestamp: Date.now()
        };
        if (expireMs) {
            data.expire = Date.now() + expireMs;
        }
        localStorage.setItem(key, JSON.stringify(data));
    },

    get(key) {
        const item = localStorage.getItem(key);
        if (!item) return null;

        try {
            const data = JSON.parse(item);
            if (data.expire && Date.now() > data.expire) {
                localStorage.removeItem(key);
                return null;
            }
            return data.value;
        } catch {
            return null;
        }
    },

    remove(key) {
        localStorage.removeItem(key);
    },

    clear() {
        localStorage.clear();
    }
};

window.Storage = Storage;

// ============================================
// EXPORT FOR MODULES (if needed)
// ============================================

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        DropdownManager,
        LogoutConfirmationManager,
        ModalManager,
        ToastManager,
        SidebarToggleManager,
        FormValidator,
        Storage
    };
}
