<?php
$pageTitle = 'Classes Management';
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
$classes = [];
$programs = [];
$streams = [];
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-users me-2"></i>Classes Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                    <i class="fas fa-plus me-1"></i>Add Class
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="classTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Program</th>
                        <th>Stream</th>
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
                            <div class="mt-2">Loading class data...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addClassForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="class_name" class="form-label">Class Name *</label>
                        <input type="text" class="form-control" id="class_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="class_code" class="form-label">Class Code *</label>
                        <input type="text" class="form-control" id="class_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="class_program" class="form-label">Program *</label>
                        <select class="form-select" id="class_program" name="program_id" required>
                            <option value="">Select Program</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="class_stream" class="form-label">Stream *</label>
                        <select class="form-select" id="class_stream" name="stream_id" required>
                            <option value="">Select Stream</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="class_is_active" name="is_active" checked>
                            <label class="form-check-label" for="class_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editClassForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_class_id">
                    
                    <div class="mb-3">
                        <label for="edit_class_name" class="form-label">Class Name *</label>
                        <input type="text" class="form-control" id="edit_class_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_class_code" class="form-label">Class Code *</label>
                        <input type="text" class="form-control" id="edit_class_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_class_program" class="form-label">Program *</label>
                        <select class="form-select" id="edit_class_program" name="program_id" required>
                            <option value="">Select Program</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_class_stream" class="form-label">Stream *</label>
                        <select class="form-select" id="edit_class_stream" name="stream_id" required>
                            <option value="">Select Stream</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_class_is_active" name="is_active">
                            <label class="form-check-label" for="edit_class_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                        <i class="fas fa-save me-1"></i>Update Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
let classes = [];
let programs = [];
let streams = [];

$(document).ready(function() {
    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addClassForm').on('submit', handleAddClass);
    $('#editClassForm').on('submit', handleEditClass);

    // Load initial data from server
    function loadInitialData() {
        Promise.all([
            AjaxUtils.makeRequest('class', 'get_list'),
            AjaxUtils.makeRequest('program', 'get_list'),
            AjaxUtils.makeRequest('stream', 'get_list')
        ])
        .then(([classesData, programsData, streamsData]) => {
            if (classesData.success && programsData.success && streamsData.success) {
                classes = classesData.data;
                programs = programsData.data;
                streams = streamsData.data;
                populateDropdowns();
                renderTable();
                console.log('Data loaded successfully:', { classes: classes.length, programs: programs.length, streams: streams.length });
            } else {
                throw new Error(classesData.message || programsData.message || streamsData.message);
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

    // Populate dropdowns
    function populateDropdowns() {
        const addProgramSelect = $('#class_program');
        const editProgramSelect = $('#edit_class_program');
        const addStreamSelect = $('#class_stream');
        const editStreamSelect = $('#edit_class_stream');
        
        // Clear existing options except the first one
        addProgramSelect.find('option:not(:first)').remove();
        editProgramSelect.find('option:not(:first)').remove();
        addStreamSelect.find('option:not(:first)').remove();
        editStreamSelect.find('option:not(:first)').remove();
        
        programs.forEach(program => {
            if (program.is_active) {
                addProgramSelect.append(`<option value="${program.id}">${AjaxUtils.escapeHtml(program.name)}</option>`);
                editProgramSelect.append(`<option value="${program.id}">${AjaxUtils.escapeHtml(program.name)}</option>`);
            }
        });
        
        streams.forEach(stream => {
            if (stream.is_active) {
                addStreamSelect.append(`<option value="${stream.id}">${AjaxUtils.escapeHtml(stream.name)}</option>`);
                editStreamSelect.append(`<option value="${stream.id}">${AjaxUtils.escapeHtml(stream.name)}</option>`);
            }
        });
    }

    // Render table data
    function renderTable() {
        const tbody = $('#tableBody');
        tbody.empty();

        if (classes.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="6" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No classes found</p>
                    </td>
                </tr>
            `);
        } else {
            classes.forEach(cls => {
                tbody.append(`
                    <tr data-id="${cls.id}">
                        <td><strong>${AjaxUtils.escapeHtml(cls.code)}</strong></td>
                        <td>${AjaxUtils.escapeHtml(cls.name)}</td>
                        <td>${AjaxUtils.escapeHtml(cls.program_name || 'N/A')}</td>
                        <td>${AjaxUtils.escapeHtml(cls.stream_name || 'N/A')}</td>
                        <td>
                            <span class="badge ${cls.is_active ? 'bg-success' : 'bg-secondary'}">
                                ${cls.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" 
                                    onclick="openEditModal(${cls.id}, '${AjaxUtils.escapeHtml(cls.name)}', '${AjaxUtils.escapeHtml(cls.code)}', ${cls.program_id}, ${cls.stream_id}, ${cls.is_active})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteClass(${cls.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle add class form submission
    function handleAddClass(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('addClassForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            name: $('#class_name').val(),
            code: $('#class_code').val(),
            program_id: $('#class_program').val(),
            stream_id: $('#class_stream').val(),
            is_active: $('#class_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('class', 'add', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#addClassModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('addClassForm');
                
                // Add new class to list
                classes.push({
                    id: data.data.id,
                    name: formData.name,
                    code: formData.code,
                    program_id: formData.program_id,
                    stream_id: formData.stream_id,
                    is_active: formData.is_active,
                    program_name: programs.find(p => p.id == formData.program_id)?.name || 'N/A',
                    stream_name: streams.find(s => s.id == formData.stream_id)?.name || 'N/A'
                });
                renderTable();
                AjaxUtils.addRowAnimation(data.data.id);
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error adding class: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('addSubmitBtn', false);
        });
    }

    // Handle edit class form submission
    function handleEditClass(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('editClassForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            id: $('#edit_class_id').val(),
            name: $('#edit_class_name').val(),
            code: $('#edit_class_code').val(),
            program_id: $('#edit_class_program').val(),
            stream_id: $('#edit_class_stream').val(),
            is_active: $('#edit_class_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('editSubmitBtn', true, 'Updating...');
        
        AjaxUtils.makeRequest('class', 'edit', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#editClassModal').modal('hide');
                AjaxUtils.clearFormValidation('editClassForm');
                
                // Update class in list
                const index = classes.findIndex(cls => cls.id == formData.id);
                if (index !== -1) {
                    classes[index] = {
                        ...classes[index],
                        name: formData.name,
                        code: formData.code,
                        program_id: formData.program_id,
                        stream_id: formData.stream_id,
                        is_active: formData.is_active,
                        program_name: programs.find(p => p.id == formData.program_id)?.name || 'N/A',
                        stream_name: streams.find(s => s.id == formData.stream_id)?.name || 'N/A'
                    };
                    renderTable();
                    AjaxUtils.addRowAnimation(formData.id);
                }
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error updating class: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('editSubmitBtn', false);
        });
    }
});

// Global functions for button clicks
function openEditModal(id, name, code, programId, streamId, isActive) {
    document.getElementById('edit_class_id').value = id;
    document.getElementById('edit_class_name').value = name;
    document.getElementById('edit_class_code').value = code;
    document.getElementById('edit_class_program').value = programId;
    document.getElementById('edit_class_stream').value = streamId;
    document.getElementById('edit_class_is_active').checked = isActive == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editClassModal'));
    modal.show();
}

function deleteClass(id) {
    if (!confirm('Are you sure you want to delete this class? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteClass(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('class', 'delete', { id: id })
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove class from list
            classes = classes.filter(cls => cls.id != id);
            renderTable();
            
            // Check if table is empty
            if (classes.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No classes found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting class: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>