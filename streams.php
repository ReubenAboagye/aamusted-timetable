<?php
$pageTitle = 'Manage Streams';
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
$streams = [];
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-stream me-2"></i>Manage Streams</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStreamModal">
                    <i class="fas fa-plus me-1"></i>Add Stream
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="streamTable">
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
                            <div class="mt-2">Loading stream data...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Stream Modal -->
<div class="modal fade" id="addStreamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Stream</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStreamForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="stream_name" class="form-label">Stream Name *</label>
                        <input type="text" class="form-control" id="stream_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="stream_code" class="form-label">Stream Code *</label>
                        <input type="text" class="form-control" id="stream_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="stream_description" class="form-label">Description</label>
                        <textarea class="form-control" id="stream_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="stream_is_active" name="is_active">
                            <label class="form-check-label" for="stream_is_active">
                                Active (Only one stream can be active at a time)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Stream
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Stream Modal -->
<div class="modal fade" id="editStreamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Stream</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editStreamForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_stream_id">
                    
                    <div class="mb-3">
                        <label for="edit_stream_name" class="form-label">Stream Name *</label>
                        <input type="text" class="form-control" id="edit_stream_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_stream_code" class="form-label">Stream Code *</label>
                        <input type="text" class="form-control" id="edit_stream_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_stream_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_stream_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_stream_is_active" name="is_active">
                            <label class="form-check-label" for="edit_stream_is_active">
                                Active (Only one stream can be active at a time)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                        <i class="fas fa-save me-1"></i>Update Stream
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
let streams = [];

$(document).ready(function() {
    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addStreamForm').on('submit', handleAddStream);
    $('#editStreamForm').on('submit', handleEditStream);

    // Load initial data from server
    function loadInitialData() {
        AjaxUtils.makeRequest('stream', 'get_list')
        .then(data => {
            if (data.success) {
                streams = data.data;
                renderTable();
                console.log('Streams loaded successfully:', streams.length);
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

        if (streams.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="5" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No streams found</p>
                    </td>
                </tr>
            `);
        } else {
            streams.forEach(stream => {
                tbody.append(`
                    <tr data-id="${stream.id}">
                        <td><strong>${AjaxUtils.escapeHtml(stream.code)}</strong></td>
                        <td>${AjaxUtils.escapeHtml(stream.name)}</td>
                        <td>${AjaxUtils.escapeHtml(stream.description || '')}</td>
                        <td>
                            <span class="badge ${stream.is_active ? 'bg-success' : 'bg-secondary'}">
                                ${stream.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" 
                                    onclick="openEditModal(${stream.id}, '${AjaxUtils.escapeHtml(stream.name)}', '${AjaxUtils.escapeHtml(stream.code)}', '${AjaxUtils.escapeHtml(stream.description || '')}', ${stream.is_active})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteStream(${stream.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle add stream form submission
    function handleAddStream(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('addStreamForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            name: $('#stream_name').val(),
            code: $('#stream_code').val(),
            description: $('#stream_description').val(),
            is_active: $('#stream_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('stream', 'add', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#addStreamModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('addStreamForm');
                
                // Add new stream to list
                streams.push({
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
            AjaxUtils.showAlert('Error adding stream: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('addSubmitBtn', false);
        });
    }

    // Handle edit stream form submission
    function handleEditStream(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('editStreamForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            id: $('#edit_stream_id').val(),
            name: $('#edit_stream_name').val(),
            code: $('#edit_stream_code').val(),
            description: $('#edit_stream_description').val(),
            is_active: $('#edit_stream_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('editSubmitBtn', true, 'Updating...');
        
        AjaxUtils.makeRequest('stream', 'edit', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#editStreamModal').modal('hide');
                AjaxUtils.clearFormValidation('editStreamForm');
                
                // Update stream in list
                const index = streams.findIndex(stream => stream.id == formData.id);
                if (index !== -1) {
                    streams[index] = {
                        ...streams[index],
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
            AjaxUtils.showAlert('Error updating stream: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('editSubmitBtn', false);
        });
    }
});

// Global functions for button clicks
function openEditModal(id, name, code, description, isActive) {
    document.getElementById('edit_stream_id').value = id;
    document.getElementById('edit_stream_name').value = name;
    document.getElementById('edit_stream_code').value = code;
    document.getElementById('edit_stream_description').value = description;
    document.getElementById('edit_stream_is_active').checked = isActive == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editStreamModal'));
    modal.show();
}

function deleteStream(id) {
    if (!confirm('Are you sure you want to delete this stream? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteStream(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('stream', 'delete', { id: id })
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove stream from list
            streams = streams.filter(stream => stream.id != id);
            renderTable();
            
            // Check if table is empty
            if (streams.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No streams found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting stream: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>