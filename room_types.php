<?php
$pageTitle = 'Room Types Management';

// Database connection and flash functionality
include 'connect.php';
include 'includes/flash.php';

// Start session for CSRF protection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include CSRF helper
include 'includes/csrf_helper.php';

// Generate CSRF token if not exists
generateCSRFToken();

include 'includes/header.php';
include 'includes/sidebar.php';

// Include custom dialog system
echo '<link rel="stylesheet" href="css/custom-dialogs.css">';
echo '<script src="js/custom-dialogs.js"></script>';

// Initialize empty arrays for data - will be populated via AJAX
$room_types = [];
?>

<!-- Additional CSS for enhanced styling -->
<style>
    .search-container {
        margin-bottom: 20px;
        flex-shrink: 0;
        min-width: 200px;
        display: flex;
        align-items: center;
    }
    
    .search-input {
        border: 2px solid #e9ecef;
        border-radius: 25px;
        padding: 10px 20px;
        width: 100%;
        max-width: 400px;
        height: 38px; /* Match button height */
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
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
    
    /* Enhanced header styling for better alignment */
    .table-header {
        min-height: 60px;
        flex-wrap: wrap;
    }
    
    .table-header h4 {
        margin-bottom: 0;
        flex-shrink: 0;
    }
    
    .table-header .d-flex {
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    /* Ensure buttons and search input have consistent height */
    .table-header .btn {
        height: 38px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .table-header .search-input {
        height: 38px;
    }
    
    /* Status badge styling */
    .status-badge {
        font-size: 0.8em;
        padding: 0.4em 0.8em;
    }
    
    /* Action buttons styling */
    .action-buttons {
        white-space: nowrap;
    }
    
    .action-buttons .btn {
        margin-right: 0.25rem;
    }
    
    /* Empty state styling */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6c757d;
    }
    
    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    /* Loading state */
    .loading-state {
        text-align: center;
        padding: 2rem;
        color: #6c757d;
    }
    
    .loading-state i {
        font-size: 2rem;
        animation: spin 1s linear infinite;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .table-header {
            flex-direction: column;
            align-items: stretch;
        }
        
        .table-header .d-flex {
            justify-content: center;
            margin-top: 1rem;
        }
        
        .search-container {
            margin-bottom: 1rem;
        }
    }
</style>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-door-open me-2"></i>Room Types Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomTypeModal">
                    <i class="fas fa-plus me-2"></i>Add New Room Type
                </button>
            </div>
        </div>
        
        <!-- Flash Messages -->
        <div id="flashMessages"></div>
        
        <!-- Search and Filter Container -->
        <div class="search-container m-3">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="search-input form-control" placeholder="Search room types..." id="searchInput">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="statusFilter">
                        <option value="all">All Types</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <!-- Bulk edit functionality removed -->
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="table-responsive">
            <table class="table" id="roomTypesTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="roomTypesTableBody">
                    <tr>
                        <td colspan="4" class="loading-state">
                            <i class="fas fa-spinner"></i>
                            <p>Loading room types...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Room Type Modal -->
<div class="modal fade" id="addRoomTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Room Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addRoomTypeForm">
                <div class="modal-body">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="module" value="room_type">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Room Type Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">
                            Please provide a valid room type name.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Room Type
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Room Type Modal -->
<div class="modal fade" id="editRoomTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Room Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editRoomTypeForm">
                <div class="modal-body">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="module" value="room_type">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Room Type Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="invalid-feedback">
                            Please provide a valid room type name.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Room Type
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the page
    loadRoomTypes();
    setupEventListeners();
});

// Load room types data
function loadRoomTypes() {
    const tbody = document.getElementById('roomTypesTableBody');
    tbody.innerHTML = '<tr><td colspan="4" class="loading-state"><i class="fas fa-spinner"></i><p>Loading room types...</p></td></tr>';
    
    fetch('ajax_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'module': 'room_type',
            'action': 'list',
            'csrf_token': getCSRFToken()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayRoomTypes(data.data);
        } else {
            showAlert('error', data.message);
            tbody.innerHTML = '<tr><td colspan="4" class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading room types: ' + data.message + '</p></td></tr>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Failed to load room types');
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load room types</p></td></tr>';
    });
}

// Display room types in table
function displayRoomTypes(roomTypes) {
    const tbody = document.getElementById('roomTypesTableBody');
    
    if (!roomTypes || roomTypes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state"><i class="fas fa-door-open"></i><p>No room types found. Add your first room type to get started!</p></td></tr>';
        return;
    }
    
    tbody.innerHTML = roomTypes.map(roomType => `
        <tr class="fade-in">
            <td><strong>${escapeHtml(roomType.name)}</strong></td>
            <td>${escapeHtml(roomType.description || '')}</td>
            <td>
                <span class="badge ${roomType.is_active ? 'bg-success' : 'bg-secondary'} status-badge">
                    ${roomType.is_active ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td class="action-buttons">
                <button class="btn btn-sm btn-outline-primary me-1" onclick="editRoomType(${roomType.id}, '${escapeHtml(roomType.name)}', '${escapeHtml(roomType.description || '')}', ${roomType.is_active})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteRoomType(${roomType.id}, '${escapeHtml(roomType.name)}')">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// Setup event listeners
function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', filterRoomTypes);
    
    // Status filter
    const statusFilter = document.getElementById('statusFilter');
    statusFilter.addEventListener('change', filterRoomTypes);
    
    // Add form
    const addForm = document.getElementById('addRoomTypeForm');
    addForm.addEventListener('submit', handleAddRoomType);
    
    // Edit form
    const editForm = document.getElementById('editRoomTypeForm');
    editForm.addEventListener('submit', handleEditRoomType);
}

// Filter room types
function filterRoomTypes() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('#roomTypesTableBody tr');
    
    rows.forEach(row => {
        if (row.querySelector('.loading-state') || row.querySelector('.empty-state')) {
            return; // Skip loading/empty state rows
        }
        
        const text = row.textContent.toLowerCase();
        const statusCell = row.querySelector('td:nth-child(3)'); // Status is now column 3
        const isActive = statusCell && statusCell.textContent.includes('Active');
        
        let showBySearch = text.includes(searchTerm);
        let showByStatus = true;
        
        if (statusFilter === 'active') {
            showByStatus = isActive;
        } else if (statusFilter === 'inactive') {
            showByStatus = !isActive;
        }
        
        row.style.display = (showBySearch && showByStatus) ? '' : 'none';
    });
}

// Handle add room type
function handleAddRoomType(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
    submitBtn.disabled = true;
    
    fetch('ajax_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            form.reset();
            bootstrap.Modal.getInstance(document.getElementById('addRoomTypeModal')).hide();
            loadRoomTypes();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Failed to add room type');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Handle edit room type
function handleEditRoomType(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
    submitBtn.disabled = true;
    
    fetch('ajax_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            bootstrap.Modal.getInstance(document.getElementById('editRoomTypeModal')).hide();
            loadRoomTypes();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('error', 'Failed to update room type');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Edit room type function
function editRoomType(id, name, description, isActive) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_is_active').checked = !!isActive;
    
    const modal = new bootstrap.Modal(document.getElementById('editRoomTypeModal'));
    modal.show();
}

// Delete room type function
async function deleteRoomType(id, name) {
    const confirmed = await customDanger(
        `Are you sure you want to delete the room type "${name}"?<br><br><strong>This action cannot be undone!</strong><br><br>This will permanently remove the room type from the system.`,
        {
            title: 'Delete Room Type',
            confirmText: 'Delete Permanently',
            cancelText: 'Cancel',
            confirmButtonClass: 'danger'
        }
    );
    
    if (!confirmed) {
        return;
    }
    
    const formData = new FormData();
        formData.append('module', 'room_type');
        formData.append('action', 'delete');
        formData.append('id', id);
        formData.append('csrf_token', getCSRFToken());
        
        fetch('ajax_api.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                loadRoomTypes();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('error', 'Failed to delete room type');
        });
}

// Utility functions
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show m-3" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const flashContainer = document.getElementById('flashMessages');
    flashContainer.innerHTML = alertHtml;
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = flashContainer.querySelector('.alert');
        if (alert) {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        }
    }, 5000);
}
</script>
