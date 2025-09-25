<?php
$pageTitle = 'Programs Management';
$show_admin_jobs_modal = false; // Disable admin jobs modal to prevent fetchJobs errors

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
$programs = [];
$departments = [];
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-graduation-cap me-2"></i>Programs Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                    <i class="fas fa-plus me-1"></i>Add Program
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="programTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Duration</th>
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
                            <div class="mt-2">Loading program data...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Program Modal -->
<div class="modal fade" id="addProgramModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addProgramForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="program_name" class="form-label">Program Name *</label>
                        <input type="text" class="form-control" id="program_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="program_code" class="form-label">Program Code *</label>
                        <input type="text" class="form-control" id="program_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="program_department" class="form-label">Department *</label>
                        <select class="form-select" id="program_department" name="department_id" required>
                            <option value="">Select Department</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="program_duration" class="form-label">Duration (Years)</label>
                        <input type="number" class="form-control" id="program_duration" name="duration_years" value="4" min="1" max="10">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="program_is_active" name="is_active" checked>
                            <label class="form-check-label" for="program_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Program Modal -->
<div class="modal fade" id="editProgramModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Program</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProgramForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_program_id">
                    
                    <div class="mb-3">
                        <label for="edit_program_name" class="form-label">Program Name *</label>
                        <input type="text" class="form-control" id="edit_program_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_program_code" class="form-label">Program Code *</label>
                        <input type="text" class="form-control" id="edit_program_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_program_department" class="form-label">Department *</label>
                        <select class="form-select" id="edit_program_department" name="department_id" required>
                            <option value="">Select Department</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_program_duration" class="form-label">Duration (Years)</label>
                        <input type="number" class="form-control" id="edit_program_duration" name="duration_years" min="1" max="10">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_program_is_active" name="is_active">
                            <label class="form-check-label" for="edit_program_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                        <i class="fas fa-save me-1"></i>Update Program
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
let programs = [];
let departments = [];

$(document).ready(function() {
    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addProgramForm').on('submit', handleAddProgram);
    $('#editProgramForm').on('submit', handleEditProgram);

    // Load initial data from server
    function loadInitialData() {
        Promise.all([
            AjaxUtils.makeRequest('program', 'get_list'),
            AjaxUtils.makeRequest('department', 'get_list')
        ])
        .then(([programsData, departmentsData]) => {
            if (programsData.success && departmentsData.success) {
                programs = programsData.data;
                departments = departmentsData.data;
                populateDepartmentDropdowns();
                renderTable();
                console.log('Data loaded successfully:', { programs: programs.length, departments: departments.length });
            } else {
                throw new Error(programsData.message || departmentsData.message);
            }
        })
        .catch(error => {
            console.error('Error loading data:', error);
            AjaxUtils.showAlert('Error loading data: ' + error.message, 'danger');
            
            // Show error in table
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to load data: ${error.message}
                        </div>
                    </td>
                </tr>
            `;
        });
    }

    // Populate department dropdowns
    function populateDepartmentDropdowns() {
        const addSelect = $('#program_department');
        const editSelect = $('#edit_program_department');
        
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

        if (programs.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="6" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No programs found</p>
                    </td>
                </tr>
            `);
        } else {
            programs.forEach(program => {
                tbody.append(`
                    <tr data-id="${program.id}">
                        <td><strong>${AjaxUtils.escapeHtml(program.code)}</strong></td>
                        <td>${AjaxUtils.escapeHtml(program.name)}</td>
                        <td>${AjaxUtils.escapeHtml(program.department_name || 'N/A')}</td>
                        <td><span class="badge bg-info">${program.duration_years} years</span></td>
                        <td>
                            <span class="badge ${program.is_active ? 'bg-success' : 'bg-secondary'}">
                                ${program.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" 
                                    onclick="openEditModal(${program.id}, '${AjaxUtils.escapeHtml(program.name)}', '${AjaxUtils.escapeHtml(program.code)}', ${program.department_id}, ${program.duration_years}, ${program.is_active})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteProgram(${program.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle add program form submission
    function handleAddProgram(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('addProgramForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            name: $('#program_name').val(),
            code: $('#program_code').val(),
            department_id: $('#program_department').val(),
            duration_years: $('#program_duration').val(),
            is_active: $('#program_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('program', 'add', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#addProgramModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('addProgramForm');
                
                // Add new program to list
                programs.push({
                    id: data.data.id,
                    name: formData.name,
                    code: formData.code,
                    department_id: formData.department_id,
                    duration_years: formData.duration_years,
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
            AjaxUtils.showAlert('Error adding program: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('addSubmitBtn', false);
        });
    }

    // Handle edit program form submission
    function handleEditProgram(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('editProgramForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            id: $('#edit_program_id').val(),
            name: $('#edit_program_name').val(),
            code: $('#edit_program_code').val(),
            department_id: $('#edit_program_department').val(),
            duration_years: $('#edit_program_duration').val(),
            is_active: $('#edit_program_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('editSubmitBtn', true, 'Updating...');
        
        AjaxUtils.makeRequest('program', 'edit', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#editProgramModal').modal('hide');
                AjaxUtils.clearFormValidation('editProgramForm');
                
                // Update program in list
                const index = programs.findIndex(program => program.id == formData.id);
                if (index !== -1) {
                    programs[index] = {
                        ...programs[index],
                        name: formData.name,
                        code: formData.code,
                        department_id: formData.department_id,
                        duration_years: formData.duration_years,
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
            AjaxUtils.showAlert('Error updating program: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('editSubmitBtn', false);
        });
    }
});

// Global functions for button clicks
function openEditModal(id, name, code, departmentId, durationYears, isActive) {
    document.getElementById('edit_program_id').value = id;
    document.getElementById('edit_program_name').value = name;
    document.getElementById('edit_program_code').value = code;
    document.getElementById('edit_program_department').value = departmentId;
    document.getElementById('edit_program_duration').value = durationYears;
    document.getElementById('edit_program_is_active').checked = isActive == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editProgramModal'));
    modal.show();
}

function deleteProgram(id) {
    if (!confirm('Are you sure you want to delete this program? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteProgram(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('program', 'delete', { id: id })
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove program from list
            programs = programs.filter(program => program.id != id);
            renderTable();
            
            // Check if table is empty
            if (programs.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No programs found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting program: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>