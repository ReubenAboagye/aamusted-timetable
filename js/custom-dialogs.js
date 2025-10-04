/**
 * Custom Dialog System
 * Replaces browser confirm/alert dialogs with custom styled dialogs
 */

class CustomDialog {
    constructor() {
        this.overlay = null;
        this.dialog = null;
        this.resolve = null;
        this.reject = null;
        this.init();
    }

    init() {
        // Create overlay if it doesn't exist
        if (!document.getElementById('custom-dialog-overlay')) {
            this.createOverlay();
        } else {
            this.overlay = document.getElementById('custom-dialog-overlay');
            this.dialog = this.overlay.querySelector('.custom-dialog');
        }
    }

    createOverlay() {
        this.overlay = document.createElement('div');
        this.overlay.id = 'custom-dialog-overlay';
        this.overlay.className = 'custom-dialog-overlay';
        
        this.dialog = document.createElement('div');
        this.dialog.className = 'custom-dialog';
        
        this.overlay.appendChild(this.dialog);
        document.body.appendChild(this.overlay);
        
        // Close on overlay click
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.close(false);
            }
        });
        
        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.overlay.classList.contains('show')) {
                this.close(false);
            }
        });
    }

    show(options = {}) {
        return new Promise((resolve, reject) => {
            this.resolve = resolve;
            this.reject = reject;
            
            const {
                title = 'Confirm',
                message = 'Are you sure?',
                type = 'warning', // warning, danger, info, success
                confirmText = 'Confirm',
                cancelText = 'Cancel',
                showCancel = true,
                confirmButtonClass = 'primary',
                onConfirm = null,
                onCancel = null,
                allowClose = true
            } = options;

            // Clear previous content
            this.dialog.innerHTML = '';

            // Create close button if allowed
            if (allowClose) {
                const closeBtn = document.createElement('button');
                closeBtn.className = 'custom-dialog-close';
                closeBtn.innerHTML = '×';
                closeBtn.onclick = () => this.close(false);
                this.dialog.appendChild(closeBtn);
            }

            // Create header
            const header = document.createElement('div');
            header.className = 'custom-dialog-header';
            
            const icon = document.createElement('div');
            icon.className = `custom-dialog-icon ${type}`;
            icon.textContent = this.getIconText(type);
            
            const titleEl = document.createElement('h3');
            titleEl.className = 'custom-dialog-title';
            titleEl.textContent = title;
            
            header.appendChild(icon);
            header.appendChild(titleEl);
            this.dialog.appendChild(header);

            // Create body
            const body = document.createElement('div');
            body.className = 'custom-dialog-body';
            body.innerHTML = message;
            this.dialog.appendChild(body);

            // Create footer
            const footer = document.createElement('div');
            footer.className = 'custom-dialog-footer';
            
            if (showCancel) {
                const cancelBtn = document.createElement('button');
                cancelBtn.className = 'custom-dialog-btn custom-dialog-btn-secondary';
                cancelBtn.textContent = cancelText;
                cancelBtn.onclick = () => {
                    if (onCancel) onCancel();
                    this.close(false);
                };
                footer.appendChild(cancelBtn);
            }
            
            const confirmBtn = document.createElement('button');
            confirmBtn.className = `custom-dialog-btn custom-dialog-btn-${confirmButtonClass}`;
            confirmBtn.textContent = confirmText;
            confirmBtn.onclick = () => {
                if (onConfirm) onConfirm();
                this.close(true);
            };
            footer.appendChild(confirmBtn);
            
            this.dialog.appendChild(footer);

            // Show dialog
            this.overlay.classList.add('show');
            
            // Focus on confirm button
            setTimeout(() => {
                confirmBtn.focus();
            }, 100);
        });
    }

    close(result) {
        this.overlay.classList.remove('show');
        
        setTimeout(() => {
            if (this.resolve) {
                this.resolve(result);
                this.resolve = null;
                this.reject = null;
            }
        }, 300);
    }

    getIconText(type) {
        const icons = {
            warning: '!',
            danger: '×',
            info: 'i',
            success: '✓'
        };
        return icons[type] || '!';
    }

    // Static methods for easy use
    static confirm(message, options = {}) {
        const dialog = new CustomDialog();
        return dialog.show({
            title: 'Confirm Action',
            message: message,
            type: 'warning',
            ...options
        });
    }

    static alert(message, options = {}) {
        const dialog = new CustomDialog();
        return dialog.show({
            title: 'Information',
            message: message,
            type: 'info',
            showCancel: false,
            confirmText: 'OK',
            ...options
        });
    }

    static warning(message, options = {}) {
        const dialog = new CustomDialog();
        return dialog.show({
            title: 'Warning',
            message: message,
            type: 'warning',
            ...options
        });
    }

    static danger(message, options = {}) {
        const dialog = new CustomDialog();
        return dialog.show({
            title: 'Dangerous Action',
            message: message,
            type: 'danger',
            confirmButtonClass: 'danger',
            ...options
        });
    }

    static success(message, options = {}) {
        const dialog = new CustomDialog();
        return dialog.show({
            title: 'Success',
            message: message,
            type: 'success',
            showCancel: false,
            confirmText: 'OK',
            confirmButtonClass: 'success',
            ...options
        });
    }
}

// Global functions for backward compatibility
window.customConfirm = CustomDialog.confirm;
window.customAlert = CustomDialog.alert;
window.customWarning = CustomDialog.warning;
window.customDanger = CustomDialog.danger;
window.customSuccess = CustomDialog.success;

// Replace browser confirm with custom dialog
window.originalConfirm = window.confirm;
window.confirm = function(message) {
    return CustomDialog.confirm(message);
};

// Replace browser alert with custom dialog
window.originalAlert = window.alert;
window.alert = function(message) {
    return CustomDialog.alert(message);
};

// Utility function to show loading state on buttons
window.showButtonLoading = function(button, text = 'Loading...') {
    const originalText = button.textContent;
    button.classList.add('loading');
    button.disabled = true;
    button.dataset.originalText = originalText;
    button.textContent = text;
    
    return function() {
        button.classList.remove('loading');
        button.disabled = false;
        button.textContent = originalText;
    };
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Pre-create the dialog overlay for better performance
    new CustomDialog();
});
