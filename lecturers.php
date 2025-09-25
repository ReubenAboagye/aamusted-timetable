<?php
$pageTitle = 'Lecturers Management';

// Database connection and stream manager must be loaded before any output
include 'connect.php';
include 'includes/stream_manager.php';
$streamManager = getStreamManager();

include 'includes/header.php';
include 'includes/sidebar.php';

// Initialize empty arrays for data - will be populated via AJAX
$lecturers = [];
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
            <h4><i class="fas fa-chalkboard-teacher me-2"></i>Lecturers Management</h4>
            <div class="d-flex gap-2">
                <!-- Search functionality -->
                <div class="search-container me-3">
                    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search lecturers...">
                </div>
                <button class="btn btn-outline-light me-2" onclick="refreshData()" title="Refresh Data">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addLecturerModal">
                    <i class="fas fa-plus me-1"></i>Add Lecturer
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="lecturerTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Table content will be loaded via AJAX -->
                    <tr id="loadingRow">
                        <td colspan="4" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2">Loading lecturer data...</div>
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

<!-- Add Lecturer Modal -->
<div class="modal fade" id="addLecturerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addLecturerForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="lecturer_name" class="form-label">Lecturer Name *</label>
                        <input type="text" class="form-control" id="lecturer_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="lecturer_department" class="form-label">Department *</label>
                        <select class="form-select" id="lecturer_department" name="department_id" required>
                            <option value="">Select Department</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="lecturer_is_active" name="is_active" checked>
                            <label class="form-check-label" for="lecturer_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Lecturer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Lecturer Modal -->
<div class="modal fade" id="editLecturerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Lecturer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editLecturerForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_lecturer_id">
                    
                    <div class="mb-3">
                        <label for="edit_lecturer_name" class="form-label">Lecturer Name *</label>
                        <input type="text" class="form-control" id="edit_lecturer_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_lecturer_department" class="form-label">Department *</label>
                        <select class="form-select" id="edit_lecturer_department" name="department_id" required>
                            <option value="">Select Department</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_lecturer_is_active" name="is_active">
                            <label class="form-check-label" for="edit_lecturer_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                        <i class="fas fa-save me-1"></i>Update Lecturer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Global variables
    let lecturers = [];
    let departments = [];

    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addLecturerForm').on('submit', handleAddLecturer);
    $('#editLecturerForm').on('submit', handleEditLecturer);
    
    // Initialize search functionality
    AjaxUtils.initSearch('searchInput', 'tableBody');

    // Load initial data from server
    function loadInitialData() {
        // Set a timeout for loading
        const timeoutId = setTimeout(() => {
            const loadingRow = document.getElementById('loadingRow');
            if (loadingRow) {
                loadingRow.innerHTML = `
                    <td colspan="4" class="text-center py-4">
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
        
        Promise.all([
            AjaxUtils.makeRequest('lecturer', 'get_list'),
            AjaxUtils.makeRequest('department', 'get_list')
        ])
        .then(([lecturersData, departmentsData]) => {
            clearTimeout(timeoutId);
            
            if (lecturersData.success && departmentsData.success) {
                lecturers = lecturersData.data;
                departments = departmentsData.data;
                populateDepartmentDropdowns();
                renderTable();
                console.log('Data loaded successfully:', { lecturers: lecturers.length, departments: departments.length });
            } else {
                throw new Error(lecturersData.message || departmentsData.message);
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Error loading data:', error);
            AjaxUtils.showAlert('Error loading data: ' + error.message, 'danger');
            
            // Show error in table
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-4">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to load data: ${error.message}
                            <button class="btn btn-sm btn-outline-danger ms-2" onclick="loadInitialData()">
                                <i class="fas fa-redo me-1"></i>Retry
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
    }

    // Populate department dropdowns
    function populateDepartmentDropdowns() {
        const addSelect = $('#lecturer_department');
        const editSelect = $('#edit_lecturer_department');
        
        // Clear existing options except the first one
        addSelect.find('option:not(:first)').remove();
        editSelect.find('option:not(:first)').remove();
        
        departments.forEach(dept => {
            if (dept.is_active) {
                addSelect.append(`<option value="${dept.id}">${AjaxUtils.escapeHtml(dept.name)}</option>`);
                editSelect.append(`<option value="${dept.id}">${AjaxUtils.escapeHtml(dept.name)}</option>`);
            }
        });
    }

    // Render table data
    function renderTable() {
        const tbody = $('#tableBody');
        tbody.empty();

        if (lecturers.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="4" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No lecturers found</p>
                    </td>
                </tr>
            `);
        } else {
            lecturers.forEach(lecturer => {
                tbody.append(`
                    <tr data-id="${lecturer.id}">
                        <td><strong>${AjaxUtils.escapeHtml(lecturer.name)}</strong></td>
                        <td>${AjaxUtils.escapeHtml(lecturer.department_name || 'N/A')}</td>
                        <td>
                            <span class="badge ${lecturer.is_active ? 'bg-success' : 'bg-secondary'}">
                                ${lecturer.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" 
                                    onclick="openEditModal(${lecturer.id}, '${AjaxUtils.escapeHtml(lecturer.name)}', ${lecturer.department_id}, ${lecturer.is_active})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteLecturer(${lecturer.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle add lecturer form submission
    function handleAddLecturer(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('addLecturerForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            name: $('#lecturer_name').val(),
            department_id: $('#lecturer_department').val(),
            is_active: $('#lecturer_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('lecturer', 'add', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#addLecturerModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('addLecturerForm');
                
                // Add new lecturer to list
                lecturers.push({
                    id: data.data.id,
                    name: formData.name,
                    department_id: formData.department_id,
                    is_active: formData.is_active,
                    department_name: departments.find(d => d.id == formData.department_id)?.name || 'N/A'
                });
                renderTable();
                AjaxUtils.addRowAnimation(data.data.id);
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error adding lecturer: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('addSubmitBtn', false);
        });
    }

    // Handle edit lecturer form submission
    function handleEditLecturer(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('editLecturerForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            id: $('#edit_lecturer_id').val(),
            name: $('#edit_lecturer_name').val(),
            department_id: $('#edit_lecturer_department').val(),
            is_active: $('#edit_lecturer_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('editSubmitBtn', true, 'Updating...');
        
        AjaxUtils.makeRequest('lecturer', 'edit', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#editLecturerModal').modal('hide');
                AjaxUtils.clearFormValidation('editLecturerForm');
                
                // Update lecturer in list
                const index = lecturers.findIndex(lecturer => lecturer.id == formData.id);
                if (index !== -1) {
                    lecturers[index] = {
                        ...lecturers[index],
                        name: formData.name,
                        department_id: formData.department_id,
                        is_active: formData.is_active,
                        department_name: departments.find(d => d.id == formData.department_id)?.name || 'N/A'
                    };
                    renderTable();
                    AjaxUtils.addRowAnimation(formData.id);
                }
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error updating lecturer: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('editSubmitBtn', false);
        });
    }
});

// Global functions for button clicks
function refreshData() {
    const refreshBtn = document.querySelector('button[onclick="refreshData()"]');
    const originalContent = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
    refreshBtn.disabled = true;
    
    Promise.all([
        AjaxUtils.makeRequest('lecturer', 'get_list'),
        AjaxUtils.makeRequest('department', 'get_list')
    ])
    .then(([lecturersData, departmentsData]) => {
        if (lecturersData.success && departmentsData.success) {
            lecturers = lecturersData.data;
            departments = departmentsData.data;
            populateDepartmentDropdowns();
            renderTable();
            AjaxUtils.showAlert('Data refreshed successfully!', 'success');
        } else {
            throw new Error(lecturersData.message || departmentsData.message);
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

function openEditModal(id, name, departmentId, isActive) {
    document.getElementById('edit_lecturer_id').value = id;
    document.getElementById('edit_lecturer_name').value = name;
    document.getElementById('edit_lecturer_department').value = departmentId;
    document.getElementById('edit_lecturer_is_active').checked = isActive == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editLecturerModal'));
    modal.show();
}

function deleteLecturer(id) {
    if (!confirm('Are you sure you want to delete this lecturer? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteLecturer(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('lecturer', 'delete', { id: id })
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove lecturer from list
            lecturers = lecturers.filter(lecturer => lecturer.id != id);
            renderTable();
            
            // Check if table is empty
            if (lecturers.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No lecturers found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting lecturer: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>