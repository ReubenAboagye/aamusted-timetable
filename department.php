<?php
$pageTitle = 'Department Management';
$show_admin_jobs_modal = false; // Disable admin jobs modal to prevent fetchJobs errors

// Database connection and flash functionality
include 'connect.php';
include 'includes/flash.php';

// Start session for CSRF protection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'includes/header.php';
include 'includes/sidebar.php';

// Initialize empty arrays for data - will be populated via AJAX
$departments = [];
?>

<!-- Additional CSS for enhanced styling -->
<style>
    .search-container {
        margin-bottom: 20px;
    }
    
    .search-input {
        border: 2px solid #e9ecef;
        border-radius: 25px;
        padding: 10px 20px;
        width: 100%;
        max-width: 400px;
        transition: all 0.3s ease;
    }
    
    .search-input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(128, 0, 32, 0.25);
        outline: none;
    }
    
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
</style>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-building me-2"></i>Department Management</h4>
            <div class="d-flex gap-2">
                <!-- Search functionality -->
                <div class="search-container me-3">
                    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search departments...">
                </div>
                <button class="btn btn-outline-light me-2" onclick="refreshData()" title="Refresh Data">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="fas fa-plus me-1"></i>Add Department
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="departmentTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Courses</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Table content will be loaded via AJAX -->
                    <tr id="loadingRow">
                        <td colspan="6" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2">Loading department data...</div>
                            <div class="mt-2">
                                <small class="text-muted">If this takes too long, check the browser console for errors</small>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addDepartmentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="dept_name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="dept_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dept_code" class="form-label">Department Code *</label>
                        <input type="text" class="form-control" id="dept_code" name="code" required>
                        <div class="form-text">Enter a unique code for this department</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="dept_description" class="form-label">Description</label>
                        <textarea class="form-control" id="dept_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dept_is_active" name="is_active" checked>
                            <label class="form-check-label" for="dept_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDepartmentForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_dept_id">
                    
                    <div class="mb-3">
                        <label for="edit_dept_name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="edit_dept_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_dept_code" class="form-label">Department Code *</label>
                        <input type="text" class="form-control" id="edit_dept_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_dept_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_dept_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_dept_is_active" name="is_active">
                            <label class="form-check-label" for="edit_dept_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                        <i class="fas fa-save me-1"></i>Update Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
let departments = [];

$(document).ready(function() {

    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addDepartmentForm').on('submit', handleAddDepartment);
    $('#editDepartmentForm').on('submit', handleEditDepartment);
    
    // Initialize search functionality
    AjaxUtils.initSearch('searchInput', 'tableBody');

    // Load initial data from server
    function loadInitialData() {
        // Set a timeout for loading
        const timeoutId = setTimeout(() => {
            const loadingRow = document.getElementById('loadingRow');
            if (loadingRow) {
                loadingRow.innerHTML = `
                    <td colspan="6" class="text-center py-4">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Loading is taking longer than expected. 
                            <button class="btn btn-sm btn-outline-warning ms-2" onclick="loadInitialData()">
                                <i class="fas fa-redo me-1"></i>Retry
                            </button>
                        </div>
                    </td>
                `;
            }
        }, 10000); // 10 second timeout
        
        AjaxUtils.makeRequest('department', 'get_list', {}, 3, 'ajax_test_simple.php')
        .then(data => {
            clearTimeout(timeoutId);
            if (data.success) {
                departments = data.data;
                renderTable();
                console.log('Departments loaded successfully:', departments.length);
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Error loading departments:', error);
            AjaxUtils.showAlert('Error loading departments: ' + error.message, 'danger');
            
            // Show error in table
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to load departments: ${error.message}
                            <button class="btn btn-sm btn-outline-danger ms-2" onclick="loadInitialData()">
                                <i class="fas fa-redo me-1"></i>Retry
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
    }

    // Render table data
    function renderTable() {
        const tbody = $('#tableBody');
        tbody.empty();

        if (departments.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="6" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No departments found</p>
                    </td>
                </tr>
            `);
        } else {
            departments.forEach(dept => {
                tbody.append(`
                    <tr data-id="${dept.id}">
                        <td><strong>${AjaxUtils.escapeHtml(dept.code)}</strong></td>
                        <td>${AjaxUtils.escapeHtml(dept.name)}</td>
                        <td>${AjaxUtils.escapeHtml(dept.description || '')}</td>
                        <td><span class="badge bg-info">${dept.course_count || 0}</span></td>
                        <td>
                            <span class="badge ${dept.is_active ? 'bg-success' : 'bg-secondary'}">
                                ${dept.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" 
                                    onclick="openEditModal(${dept.id}, '${AjaxUtils.escapeHtml(dept.name)}', '${AjaxUtils.escapeHtml(dept.code)}', '${AjaxUtils.escapeHtml(dept.description || '')}', ${dept.is_active})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteDepartment(${dept.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle add department form submission
    function handleAddDepartment(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('addDepartmentForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            name: $('#dept_name').val(),
            code: $('#dept_code').val(),
            description: $('#dept_description').val(),
            is_active: $('#dept_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('department', 'add', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#addDepartmentModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('addDepartmentForm');
                
                // Add new department to list
                departments.push({
                    id: data.data.id,
                    name: formData.name,
                    code: formData.code,
                    description: formData.description,
                    is_active: formData.is_active,
                    course_count: 0
                });
                renderTable();
                AjaxUtils.addRowAnimation(data.data.id);
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error adding department: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('addSubmitBtn', false);
        });
    }

    // Handle edit department form submission
    function handleEditDepartment(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('editDepartmentForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            id: $('#edit_dept_id').val(),
            name: $('#edit_dept_name').val(),
            code: $('#edit_dept_code').val(),
            description: $('#edit_dept_description').val(),
            is_active: $('#edit_dept_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('editSubmitBtn', true, 'Updating...');
        
        AjaxUtils.makeRequest('department', 'edit', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#editDepartmentModal').modal('hide');
                AjaxUtils.clearFormValidation('editDepartmentForm');
                
                // Update department in list
                const index = departments.findIndex(dept => dept.id == formData.id);
                if (index !== -1) {
                    departments[index] = {
                        ...departments[index],
                        name: formData.name,
                        code: formData.code,
                        description: formData.description,
                        is_active: formData.is_active
                    };
                    renderTable();
                    AjaxUtils.addRowAnimation(formData.id);
                }
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error updating department: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('editSubmitBtn', false);
        });
    }
});

// Global functions for button clicks
function loadInitialData() {
    // Set a timeout for loading
    const timeoutId = setTimeout(() => {
        const loadingRow = document.getElementById('loadingRow');
        if (loadingRow) {
            loadingRow.innerHTML = `
                <td colspan="6" class="text-center py-4">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Loading is taking longer than expected. 
                        <button class="btn btn-sm btn-outline-warning ms-2" onclick="loadInitialData()">
                            <i class="fas fa-redo me-1"></i>Retry
                        </button>
                    </td>
                </div>
            `;
        }
    }, 10000); // 10 second timeout
    
    AjaxUtils.makeRequest('department', 'get_list', {}, 3, 'ajax_test_simple.php')
    .then(data => {
        clearTimeout(timeoutId);
        if (data.success) {
            departments = data.data;
            renderTable();
            console.log('Departments loaded successfully:', departments.length);
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Error loading departments:', error);
        AjaxUtils.showAlert('Error loading departments: ' + error.message, 'danger');
        
        // Show error in table
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center py-4">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to load departments: ${error.message}
                        <button class="btn btn-sm btn-outline-danger ms-2" onclick="loadInitialData()">
                            <i class="fas fa-redo me-1"></i>Retry
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
}

function renderTable() {
    const tbody = $('#tableBody');
    tbody.empty();

    if (departments.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="6" class="empty-state">
                    <i class="fas fa-info-circle"></i>
                    <p>No departments found</p>
                </td>
            </tr>
        `);
    } else {
        departments.forEach(dept => {
            tbody.append(`
                <tr data-id="${dept.id}">
                    <td><strong>${AjaxUtils.escapeHtml(dept.code)}</strong></td>
                    <td>${AjaxUtils.escapeHtml(dept.name)}</td>
                    <td>${AjaxUtils.escapeHtml(dept.description || '')}</td>
                    <td><span class="badge bg-info">${dept.course_count || 0}</span></td>
                    <td>
                        <span class="badge ${dept.is_active ? 'bg-success' : 'bg-secondary'}">
                            ${dept.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-warning btn-sm me-1" 
                                onclick="openEditModal(${dept.id}, '${AjaxUtils.escapeHtml(dept.name)}', '${AjaxUtils.escapeHtml(dept.code)}', '${AjaxUtils.escapeHtml(dept.description || '')}', ${dept.is_active})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" 
                                onclick="deleteDepartment(${dept.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }
}

function refreshData() {
    const refreshBtn = document.querySelector('button[onclick="refreshData()"]');
    const originalContent = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
    refreshBtn.disabled = true;
    
    AjaxUtils.makeRequest('department', 'get_list', {}, 3, 'ajax_test_simple.php')
    .then(data => {
        if (data.success) {
            departments = data.data;
            renderTable();
            AjaxUtils.showAlert('Data refreshed successfully!', 'success');
        } else {
            throw new Error(data.message);
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error refreshing data: ' + error.message, 'danger');
    })
    .finally(() => {
        refreshBtn.innerHTML = originalContent;
        refreshBtn.disabled = false;
    });
}

function openEditModal(id, name, code, description, isActive) {
    document.getElementById('edit_dept_id').value = id;
    document.getElementById('edit_dept_name').value = name;
    document.getElementById('edit_dept_code').value = code;
    document.getElementById('edit_dept_description').value = description;
    document.getElementById('edit_dept_is_active').checked = isActive == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
    modal.show();
}

function deleteDepartment(id) {
    if (!confirm('Are you sure you want to delete this department? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteDepartment(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('department', 'delete', { id: id }, 3, 'ajax_test_simple.php')
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove department from list
            departments = departments.filter(dept => dept.id != id);
            renderTable();
            
            // Check if table is empty
            if (departments.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No departments found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting department: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>