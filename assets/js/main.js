// ========== LGU IPMS - Main JavaScript ==========/

(function() {
    'use strict';
    
    // Application constants
    const API_BASE = '/api';
    const DEBUG = true;
    
    // Log helper
    function log(message, data = null) {
        if (DEBUG) {
            console.log(`[IPMS] ${message}`, data || '');
        }
    }
    
    // Initialize when DOM is ready
    function init() {
        log('Initializing application');
        
        setupEventListeners();
        setupFormValidation();
        setupAjaxDefaults();
    }
    
    // Setup event listeners
    function setupEventListeners() {
        // Navbar mobile toggle
        const navToggle = document.getElementById('navToggle');
        if (navToggle) {
            navToggle.addEventListener('click', function() {
                const navLinks = document.getElementById('navLinks');
                if (navLinks) {
                    navLinks.classList.toggle('active');
                }
            });
        }
        
        // Close mobile menu when link is clicked
        document.addEventListener('click', function(e) {
            if (e.target.closest('.nav-item')) {
                const navLinks = document.getElementById('navLinks');
                if (navLinks && window.innerWidth < 768) {
                    navLinks.classList.remove('active');
                }
            }
        });
    }
    
    // Setup form validation
    function setupFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!validateForm(this)) {
                    e.preventDefault();
                    log('Form validation failed');
                }
            });
        });
    }
    
    // Validate form
    function validateForm(form) {
        const inputs = form.querySelectorAll('[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                showFieldError(input, 'This field is required');
                isValid = false;
            } else {
                clearFieldError(input);
            }
        });
        
        return isValid;
    }
    
    // Show field error
    function showFieldError(field, message) {
        field.classList.add('is-invalid');
        
        let errorEl = field.nextElementSibling;
        if (!errorEl || !errorEl.classList.contains('error-message')) {
            errorEl = document.createElement('div');
            errorEl.className = 'error-message';
            field.parentNode.insertBefore(errorEl, field.nextSibling);
        }
        errorEl.textContent = message;
    }
    
    // Clear field error
    function clearFieldError(field) {
        field.classList.remove('is-invalid');
        
        const errorEl = field.nextElementSibling;
        if (errorEl && errorEl.classList.contains('error-message')) {
            errorEl.remove();
        }
    }
    
    // Setup AJAX defaults
    function setupAjaxDefaults() {
        // Add CSRF token to all AJAX requests if available
        // This can be enhanced with actual CSRF token handling
    }
    
    // API Helper functions
    window.API = {
        async get(endpoint) {
            return this.request('GET', endpoint);
        },
        
        async post(endpoint, data = {}) {
            return this.request('POST', endpoint, data);
        },
        
        async put(endpoint, data = {}) {
            return this.request('PUT', endpoint, data);
        },
        
        async delete(endpoint) {
            return this.request('DELETE', endpoint);
        },
        
        async request(method, endpoint, data = {}) {
            try {
                const options = {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                };
                
                if (method !== 'GET' && Object.keys(data).length > 0) {
                    options.body = JSON.stringify(data);
                }
                
                const response = await fetch(API_BASE + endpoint, options);
                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.error || `HTTP ${response.status}`);
                }
                
                return result;
            } catch (error) {
                log(`API Error (${method} ${endpoint}):`, error.message);
                throw error;
            }
        }
    };
    
    // Utility functions
    window.UI = {
        showAlert(message, type = 'info') {
            const alertEl = document.createElement('div');
            alertEl.className = `alert alert-${type}`;
            alertEl.textContent = message;
            
            const container = document.querySelector('.container') || document.body;
            container.insertBefore(alertEl, container.firstChild);
            
            setTimeout(() => alertEl.remove(), 5000);
        },
        
        showSuccess(message) {
            this.showAlert(message, 'success');
        },
        
        showError(message) {
            this.showAlert(message, 'danger');
        },
        
        showLoading(element) {
            element.innerHTML = '<div class="spinner"></div> Loading...';
        },
        
        showToast(message, duration = 3000) {
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.remove(), duration);
        }
    };
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
