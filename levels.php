<?php
$pageTitle = 'Manage Levels';
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
$levels = [];
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-layer-group me-2"></i>Manage Levels</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLevelModal">
                    <i class="fas fa-plus me-1"></i>Add Level
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="levelTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Table content will be loaded via AJAX -->
                    <tr id="loadingRow">
                        <td colspan="5" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2">Loading level data...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Level Modal -->
<div class="modal fade" id="addLevelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addLevelForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="level_name" class="form-label">Level Name *</label>
                        <input type="text" class="form-control" id="level_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="level_code" class="form-label">Level Code *</label>
                        <input type="text" class="form-control" id="level_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="level_description" class="form-label">Description</label>
                        <textarea class="form-control" id="level_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="level_is_active" name="is_active" checked>
                            <label class="form-check-label" for="level_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Level Modal -->
<div class="modal fade" id="editLevelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Level</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editLevelForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_level_id">
                    
                    <div class="mb-3">
                        <label for="edit_level_name" class="form-label">Level Name *</label>
                        <input type="text" class="form-control" id="edit_level_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_level_code" class="form-label">Level Code *</label>
                        <input type="text" class="form-control" id="edit_level_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_level_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_level_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_level_is_active" name="is_active">
                            <label class="form-check-label" for="edit_level_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                        <i class="fas fa-save me-1"></i>Update Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
let levels = [];

$(document).ready(function() {
    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addLevelForm').on('submit', handleAddLevel);
    $('#editLevelForm').on('submit', handleEditLevel);

    // Load initial data from server
    function loadInitialData() {
        AjaxUtils.makeRequest('level', 'get_list')
        .then(data => {
            if (data.success) {
                levels = data.data;
                renderTable();
                console.log('Levels loaded successfully:', levels.length);
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            console.error('Error loading data:', error);
            AjaxUtils.showAlert('Error loading data: ' + error.message, 'danger');
            
            // Show error in table
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to load data: ${error.message}
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

        if (levels.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="5" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No levels found</p>
                    </td>
                </tr>
            `);
        } else {
            levels.forEach(level => {
                tbody.append(`
                    <tr data-id="${level.id}">
                        <td><strong>${AjaxUtils.escapeHtml(level.code)}</strong></td>
                        <td>${AjaxUtils.escapeHtml(level.name)}</td>
                        <td>${AjaxUtils.escapeHtml(level.description || '')}</td>
                        <td>
                            <span class="badge ${level.is_active ? 'bg-success' : 'bg-secondary'}">
                                ${level.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" 
                                    onclick="openEditModal(${level.id}, '${AjaxUtils.escapeHtml(level.name)}', '${AjaxUtils.escapeHtml(level.code)}', '${AjaxUtils.escapeHtml(level.description || '')}', ${level.is_active})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteLevel(${level.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle add level form submission
    function handleAddLevel(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('addLevelForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            name: $('#level_name').val(),
            code: $('#level_code').val(),
            description: $('#level_description').val(),
            is_active: $('#level_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('level', 'add', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#addLevelModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('addLevelForm');
                
                // Add new level to list
                levels.push({
                    id: data.data.id,
                    name: formData.name,
                    code: formData.code,
                    description: formData.description,
                    is_active: formData.is_active
                });
                renderTable();
                AjaxUtils.addRowAnimation(data.data.id);
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error adding level: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('addSubmitBtn', false);
        });
    }

    // Handle edit level form submission
    function handleEditLevel(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('editLevelForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            id: $('#edit_level_id').val(),
            name: $('#edit_level_name').val(),
            code: $('#edit_level_code').val(),
            description: $('#edit_level_description').val(),
            is_active: $('#edit_level_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('editSubmitBtn', true, 'Updating...');
        
        AjaxUtils.makeRequest('level', 'edit', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#editLevelModal').modal('hide');
                AjaxUtils.clearFormValidation('editLevelForm');
                
                // Update level in list
                const index = levels.findIndex(level => level.id == formData.id);
                if (index !== -1) {
                    levels[index] = {
                        ...levels[index],
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
            AjaxUtils.showAlert('Error updating level: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('editSubmitBtn', false);
        });
    }
});

// Global functions for button clicks
function openEditModal(id, name, code, description, isActive) {
    document.getElementById('edit_level_id').value = id;
    document.getElementById('edit_level_name').value = name;
    document.getElementById('edit_level_code').value = code;
    document.getElementById('edit_level_description').value = description;
    document.getElementById('edit_level_is_active').checked = isActive == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editLevelModal'));
    modal.show();
}

function deleteLevel(id) {
    if (!confirm('Are you sure you want to delete this level? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteLevel(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('level', 'delete', { id: id })
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove level from list
            levels = levels.filter(level => level.id != id);
            renderTable();
            
            // Check if table is empty
            if (levels.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No levels found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting level: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>