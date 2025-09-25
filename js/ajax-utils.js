/**
 * Shared AJAX Utilities for the entire project
 * Provides consistent AJAX functionality across all pages
 */

// Global AJAX utilities
window.AjaxUtils = {
    // Configuration
    config: {
        apiUrl: 'ajax_api.php',
        retryAttempts: 3,
        retryDelay: 1000,
        timeout: 30000
    },

    // CSRF token management
    csrfToken: null,

    // Enhanced AJAX call with retry functionality
    makeRequest: function(module, action, data = {}, retries = this.config.retryAttempts, customUrl = null) {
        const formData = new FormData();
        formData.append('module', module);
        formData.append('action', action);
        formData.append('csrf_token', this.csrfToken);
        
        // Add additional data
        Object.keys(data).forEach(key => {
            if (Array.isArray(data[key])) {
                data[key].forEach(value => {
                    formData.append(key + '[]', value);
                });
            } else {
                formData.append(key, data[key]);
            }
        });

        const url = customUrl || this.config.apiUrl;
        return fetch(url, {
            method: 'POST',
            body: formData,
            signal: AbortSignal.timeout(this.config.timeout)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Response is not JSON');
            }
            
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
            });
        })
        .catch(error => {
            if (retries > 0 && !error.name === 'AbortError') {
                console.warn(`Request failed, retrying... (${retries} attempts left)`);
                return new Promise(resolve => {
                    setTimeout(() => {
                        resolve(this.makeRequest(module, action, data, retries - 1));
                    }, this.config.retryDelay);
                });
            }
            throw error;
        });
    },

    // Show alert message
    showAlert: function(message, type = 'info', container = 'alertContainer') {
        const alertContainer = document.getElementById(container);
        if (!alertContainer) return;
        
        const alertId = 'alert-' + Date.now();
        
        // Map alert types to icons
        const iconMap = {
            'success': 'check-circle',
            'danger': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        
        alertContainer.innerHTML = `
            <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="fas fa-${iconMap[type] || 'info-circle'} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Auto-dismiss after different times based on type
        const dismissTime = type === 'success' ? 3000 : 5000;
        setTimeout(() => {
            const alert = document.getElementById(alertId);
            if (alert) {
                alert.remove();
            }
        }, dismissTime);
    },

    // Set button loading state
    setButtonLoading: function(buttonId, isLoading, loadingText = 'Processing...') {
        const btn = document.getElementById(buttonId);
        if (!btn) return;
        
        if (isLoading) {
            btn.classList.add('btn-loading');
            btn.disabled = true;
            btn.setAttribute('data-original-text', btn.innerHTML);
            btn.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i>${loadingText}`;
        } else {
            btn.classList.remove('btn-loading');
            btn.disabled = false;
            btn.innerHTML = btn.getAttribute('data-original-text') || btn.innerHTML;
        }
    },

    // Form validation
    validateForm: function(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;
        
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    },

    // Clear form validation
    clearFormValidation: function(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        const inputs = form.querySelectorAll('.is-invalid');
        inputs.forEach(input => {
            input.classList.remove('is-invalid');
        });
    },

    // Escape HTML to prevent XSS
    escapeHtml: function(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    },

    // Add row animation
    addRowAnimation: function(rowId) {
        setTimeout(() => {
            const row = document.getElementById(rowId) || document.querySelector(`[data-id="${rowId}"]`);
            if (row) {
                row.classList.add('fade-in');
            }
        }, 100);
    },

    // Remove row with animation
    removeRowWithAnimation: function(rowId) {
        const row = document.getElementById(rowId) || document.querySelector(`[data-id="${rowId}"]`);
        if (row) {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(-100%)';
            setTimeout(() => {
                row.remove();
            }, 300);
        }
    },

    // Search/filter functionality
    filterTable: function(searchTerm, tableBodyId = 'tableBody') {
        const tbody = document.getElementById(tableBodyId);
        if (!tbody) return;
        
        const rows = tbody.querySelectorAll('tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (searchTerm === '' || text.includes(searchTerm.toLowerCase())) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show "no results" message if no rows are visible
        if (visibleCount === 0 && searchTerm !== '') {
            const existingNoResults = tbody.querySelector('#noResultsRow');
            if (!existingNoResults) {
                tbody.insertAdjacentHTML('beforeend', `
                    <tr id="noResultsRow">
                        <td colspan="100%" class="text-center py-4 text-muted">
                            <i class="fas fa-search me-2"></i>No results found matching "${searchTerm}"
                        </td>
                    </tr>
                `);
            }
        } else {
            const noResultsRow = tbody.querySelector('#noResultsRow');
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    },

    // Initialize search functionality
    initSearch: function(searchInputId, tableBodyId = 'tableBody') {
        const searchInput = document.getElementById(searchInputId);
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.filterTable(e.target.value, tableBodyId);
            });
        }
    },

    // Initialize form validation clearing
    initFormValidation: function() {
        document.addEventListener('input', (e) => {
            if (e.target.classList.contains('is-invalid')) {
                e.target.classList.remove('is-invalid');
            }
        });
    },

    // Initialize CSRF token
    initCSRF: function() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 
                        document.querySelector('input[name="csrf_token"]')?.value;
    },

    // Initialize all common functionality
    init: function() {
        this.initCSRF();
        this.initFormValidation();
        
        // Add loading button styles if not already present
        if (!document.querySelector('#ajax-loading-styles')) {
            const style = document.createElement('style');
            style.id = 'ajax-loading-styles';
            style.textContent = `
                .btn-loading {
                    position: relative;
                    pointer-events: none;
                }
                
                .btn-loading::after {
                    content: "";
                    position: absolute;
                    width: 16px;
                    height: 16px;
                    top: 50%;
                    left: 50%;
                    margin-left: -8px;
                    margin-top: -8px;
                    border: 2px solid transparent;
                    border-top-color: #ffffff;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .fade-in {
                    animation: fadeInRow 0.5s ease-in;
                }
                
                @keyframes fadeInRow {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                .is-invalid {
                    border-color: #dc3545;
                    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
                }
            `;
            document.head.appendChild(style);
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    AjaxUtils.init();
});