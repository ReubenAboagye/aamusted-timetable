<?php
$pageTitle = 'Rooms Management';
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
$rooms = [];
$room_types = [];
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-door-open me-2"></i>Rooms Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                    <i class="fas fa-plus me-1"></i>Add Room
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="roomTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Capacity</th>
                        <th>Type</th>
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
                            <div class="mt-2">Loading room data...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addRoomForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="room_name" class="form-label">Room Name *</label>
                        <input type="text" class="form-control" id="room_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="room_code" class="form-label">Room Code *</label>
                        <input type="text" class="form-control" id="room_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="room_capacity" class="form-label">Capacity *</label>
                        <input type="number" class="form-control" id="room_capacity" name="capacity" required min="1" max="1000">
                    </div>
                    
                    <div class="mb-3">
                        <label for="room_type" class="form-label">Room Type *</label>
                        <select class="form-select" id="room_type" name="room_type_id" required>
                            <option value="">Select Room Type</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="room_is_active" name="is_active" checked>
                            <label class="form-check-label" for="room_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Room
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editRoomForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_room_id">
                    
                    <div class="mb-3">
                        <label for="edit_room_name" class="form-label">Room Name *</label>
                        <input type="text" class="form-control" id="edit_room_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_room_code" class="form-label">Room Code *</label>
                        <input type="text" class="form-control" id="edit_room_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_room_capacity" class="form-label">Capacity *</label>
                        <input type="number" class="form-control" id="edit_room_capacity" name="capacity" required min="1" max="1000">
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_room_type" class="form-label">Room Type *</label>
                        <select class="form-select" id="edit_room_type" name="room_type_id" required>
                            <option value="">Select Room Type</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_room_is_active" name="is_active">
                            <label class="form-check-label" for="edit_room_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                        <i class="fas fa-save me-1"></i>Update Room
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
let rooms = [];
let room_types = [];

$(document).ready(function() {
    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addRoomForm').on('submit', handleAddRoom);
    $('#editRoomForm').on('submit', handleEditRoom);

    // Load initial data from server
    function loadInitialData() {
        Promise.all([
            AjaxUtils.makeRequest('room', 'get_list'),
            AjaxUtils.makeRequest('room', 'get_room_types')
        ])
        .then(([roomsData, roomTypesData]) => {
            if (roomsData.success && roomTypesData.success) {
                rooms = roomsData.data;
                room_types = roomTypesData.data;
                populateRoomTypeDropdowns();
                renderTable();
                console.log('Data loaded successfully:', { rooms: rooms.length, room_types: room_types.length });
            } else {
                throw new Error(roomsData.message || roomTypesData.message);
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

    // Populate room type dropdowns
    function populateRoomTypeDropdowns() {
        const addSelect = $('#room_type');
        const editSelect = $('#edit_room_type');
        
        // Clear existing options except the first one
        addSelect.find('option:not(:first)').remove();
        editSelect.find('option:not(:first)').remove();
        
        room_types.forEach(roomType => {
            addSelect.append(`<option value="${roomType.id}">${AjaxUtils.escapeHtml(roomType.name)}</option>`);
            editSelect.append(`<option value="${roomType.id}">${AjaxUtils.escapeHtml(roomType.name)}</option>`);
        });
    }

    // Render table data
    function renderTable() {
        const tbody = $('#tableBody');
        tbody.empty();

        if (rooms.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="6" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No rooms found</p>
                    </td>
                </tr>
            `);
        } else {
            rooms.forEach(room => {
                tbody.append(`
                    <tr data-id="${room.id}">
                        <td><strong>${AjaxUtils.escapeHtml(room.code)}</strong></td>
                        <td>${AjaxUtils.escapeHtml(room.name)}</td>
                        <td><span class="badge bg-info">${room.capacity}</span></td>
                        <td>${AjaxUtils.escapeHtml(room.room_type_name || 'N/A')}</td>
                        <td>
                            <span class="badge ${room.is_active ? 'bg-success' : 'bg-secondary'}">
                                ${room.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" 
                                    onclick="openEditModal(${room.id}, '${AjaxUtils.escapeHtml(room.name)}', '${AjaxUtils.escapeHtml(room.code)}', ${room.capacity}, ${room.room_type_id}, ${room.is_active})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteRoom(${room.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle add room form submission
    function handleAddRoom(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('addRoomForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            name: $('#room_name').val(),
            code: $('#room_code').val(),
            capacity: $('#room_capacity').val(),
            room_type_id: $('#room_type').val(),
            is_active: $('#room_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('room', 'add', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#addRoomModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('addRoomForm');
                
                // Add new room to list
                rooms.push({
                    id: data.data.id,
                    name: formData.name,
                    code: formData.code,
                    capacity: formData.capacity,
                    room_type_id: formData.room_type_id,
                    is_active: formData.is_active,
                    room_type_name: room_types.find(rt => rt.id == formData.room_type_id)?.name || 'N/A'
                });
                renderTable();
                AjaxUtils.addRowAnimation(data.data.id);
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error adding room: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('addSubmitBtn', false);
        });
    }

    // Handle edit room form submission
    function handleEditRoom(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('editRoomForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            id: $('#edit_room_id').val(),
            name: $('#edit_room_name').val(),
            code: $('#edit_room_code').val(),
            capacity: $('#edit_room_capacity').val(),
            room_type_id: $('#edit_room_type').val(),
            is_active: $('#edit_room_is_active').is(':checked') ? 1 : 0
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('editSubmitBtn', true, 'Updating...');
        
        AjaxUtils.makeRequest('room', 'edit', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#editRoomModal').modal('hide');
                AjaxUtils.clearFormValidation('editRoomForm');
                
                // Update room in list
                const index = rooms.findIndex(room => room.id == formData.id);
                if (index !== -1) {
                    rooms[index] = {
                        ...rooms[index],
                        name: formData.name,
                        code: formData.code,
                        capacity: formData.capacity,
                        room_type_id: formData.room_type_id,
                        is_active: formData.is_active,
                        room_type_name: room_types.find(rt => rt.id == formData.room_type_id)?.name || 'N/A'
                    };
                    renderTable();
                    AjaxUtils.addRowAnimation(formData.id);
                }
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error updating room: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('editSubmitBtn', false);
        });
    }
});

// Global functions for button clicks
function openEditModal(id, name, code, capacity, roomTypeId, isActive) {
    document.getElementById('edit_room_id').value = id;
    document.getElementById('edit_room_name').value = name;
    document.getElementById('edit_room_code').value = code;
    document.getElementById('edit_room_capacity').value = capacity;
    document.getElementById('edit_room_type').value = roomTypeId;
    document.getElementById('edit_room_is_active').checked = isActive == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editRoomModal'));
    modal.show();
}

function deleteRoom(id) {
    if (!confirm('Are you sure you want to delete this room? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteRoom(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('room', 'delete', { id: id })
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove room from list
            rooms = rooms.filter(room => room.id != id);
            renderTable();
            
            // Check if table is empty
            if (rooms.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No rooms found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting room: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>