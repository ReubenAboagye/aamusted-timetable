<?php
$pageTitle = 'Programs Management';

try {
    include 'includes/header.php';
    include 'includes/sidebar.php';

    // Include custom dialog system
    echo '<link rel="stylesheet" href="css/custom-dialogs.css">';
    echo '<script src="js/custom-dialogs.js"></script>';
    include 'includes/flash.php';

    // Database connection (header may already include this)
    include_once 'connect.php';
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Remove server-side form processing - now handled by AJAX
    // All CRUD operations are now handled by ajax_programs.php

        } catch (Exception $e) {
    // Log the exception
    error_log("Exception in programs.php: " . $e->getMessage());
    
    // Display user-friendly error message
    echo '<div class="alert alert-danger" role="alert">';
    echo '<h4>An error occurred</h4>';
    echo '<p>Please contact the administrator or try refreshing the page.</p>';
    echo '<small>Error details have been logged.</small>';
    echo '</div>';
        } finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>

<div class="main-content" id="mainContent">
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;">
        <div id="toastNotification" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="fas fa-info-circle me-2"></i>
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <!-- Toast message will be inserted here -->
            </div>
        </div>
    </div>
    
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-graduation-cap me-2"></i>Programs Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-upload me-2"></i>Import
                </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                <i class="fas fa-plus me-2"></i>Add New Program
            </button>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="search-container m-3">
            <input type="text" class="search-input" placeholder="Search programs...">
        </div>

        <div class="table-responsive">
            <table class="table" id="programsTable">
                <thead>
                    <tr>
                        <th>Program Name</th>
                        <th>Code</th>
                        <th>Duration (yrs)</th>
                        <th>Department</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="programsTableBody">
                    <tr>
                        <td colspan="5" class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading programs...</p>
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
                        <label for="add_name" class="form-label">Program Name *</label>
                        <input type="text" class="form-control" id="add_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_code" class="form-label">Program Code *</label>
                        <input type="text" class="form-control" id="add_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_department_id" class="form-label">Department *</label>
                        <select class="form-select" id="add_department_id" name="department_id" required>
                            <option value="">Loading departments...</option>
                        </select>
                    </div>
                    <div id="addFormAlert"></div>
                    
                    <div class="mb-3">
                        <label for="add_duration" class="form-label">Duration (Years) *</label>
                        <select class="form-select" id="add_duration" name="duration" required>
                            <option value="">Select Duration</option>
                            <option value="1">1 Year</option>
                            <option value="2">2 Years</option>
                            <option value="3">3 Years</option>
                            <option value="4">4 Years</option>
                            <option value="5">5 Years</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addProgramBtn">
                        <span class="btn-text">Add Program</span>
                        <span class="btn-spinner d-none">
                            <span class="spinner-border spinner-border-sm me-2"></span>Adding...
                        </span>
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
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Program Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_code" class="form-label">Program Code *</label>
                        <input type="text" class="form-control" id="edit_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_department_id" class="form-label">Department *</label>
                        <select class="form-select" id="edit_department_id" name="department_id" required>
                            <option value="">Loading departments...</option>
                        </select>
                    </div>
                    <div id="editFormAlert"></div>
                    
                    <div class="mb-3">
                        <label for="edit_duration" class="form-label">Duration (Years) *</label>
                        <select class="form-select" id="edit_duration" name="duration" required>
                            <option value="">Select Duration</option>
                            <option value="1">1 Year</option>
                            <option value="2">2 Years</option>
                            <option value="3">3 Years</option>
                            <option value="4">4 Years</option>
                            <option value="5">5 Years</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editProgramBtn">
                        <span class="btn-text">Update Program</span>
                        <span class="btn-spinner d-none">
                            <span class="spinner-border spinner-border-sm me-2"></span>Updating...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Programs (CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div id="uploadArea" class="upload-area p-4 text-center border rounded" style="cursor:pointer;">
                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                        <p class="mb-1">Drop CSV file here or <strong>click to browse</strong></p>
                        <small class="text-muted">Expected headers: name,department_id,code,duration,is_active</small>
                    </div>
                    <input type="file" class="form-control d-none" id="csvFile" accept=".csv,text/csv">
                </div>

                <div class="mb-3">
                    <h6>Preview (first 10 rows)</h6>
                    <div class="table-responsive" style="max-height:300px;overflow:auto">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Dept ID</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Validation</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted">Upload a CSV file to preview</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="importSummary"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="processBtn" disabled>Process File</button>
            </div>
        </div>
    </div>
</div>

<script>
// AJAX Programs Management
let programs = [];
let departments = [];

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
    loadDepartments();
    loadPrograms();
    setupEventListeners();
});

// Load departments for dropdowns
async function loadDepartments() {
    try {
        const response = await fetch('ajax_programs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_departments'
        });
        
        const result = await response.json();
        
        if (result.success) {
            departments = result.data;
            populateDepartmentDropdowns();
        } else {
            showAlert('error', 'Failed to load departments: ' + result.message);
        }
    } catch (error) {
        console.error('Error loading departments:', error);
        showAlert('error', 'Error loading departments');
    }
}

// Load programs and populate table
async function loadPrograms() {
    try {
        const response = await fetch('ajax_programs.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_programs'
        });
        
        const result = await response.json();
        
        if (result.success) {
            programs = result.data;
            renderProgramsTable();
        } else {
            showAlert('error', 'Failed to load programs: ' + result.message);
        }
    } catch (error) {
        console.error('Error loading programs:', error);
        showAlert('error', 'Error loading programs');
    }
}

// Populate department dropdowns
function populateDepartmentDropdowns() {
    const addSelect = document.getElementById('add_department_id');
    const editSelect = document.getElementById('edit_department_id');
    
    if (departments.length === 0) {
        addSelect.innerHTML = '<option value="">No departments found</option>';
        editSelect.innerHTML = '<option value="">No departments found</option>';
        return;
    }
    
    const options = '<option value="">Select Department</option>' +
        departments.map(dept => `<option value="${dept.id}">${escapeHtml(dept.name)}</option>`).join('');
    
    addSelect.innerHTML = options;
    editSelect.innerHTML = options;
}

// Render programs table
function renderProgramsTable() {
    const tbody = document.getElementById('programsTableBody');
    
    if (programs.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="empty-state text-center">
                    <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                    <p>No programs found. Add your first program to get started!</p>
                </td>
            </tr>
        `;
        return;
    }
    
    tbody.innerHTML = programs.map(program => `
        <tr data-id="${program.id}">
            <td><strong>${escapeHtml(program.name)}</strong></td>
            <td>${escapeHtml(program.code || '')}</td>
            <td>${program.duration_years || program.duration || ''}</td>
            <td>${escapeHtml(program.department_name || '')}</td>
            <td>
                <button class="btn btn-sm btn-outline-primary me-1" onclick="editProgram(${program.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger delete-btn" data-id="${program.id}" onclick="deleteProgram(${program.id})">
                    <span class="btn-text">
                        <i class="fas fa-trash"></i>
                    </span>
                    <span class="btn-spinner d-none">
                        <i class="fas fa-spinner fa-spin"></i>
                    </span>
                </button>
            </td>
        </tr>
    `).join('');
}

// Setup event listeners
function setupEventListeners() {
    // Add program form
    document.getElementById('addProgramForm').addEventListener('submit', handleAddProgram);
    
    // Edit program form
    document.getElementById('editProgramForm').addEventListener('submit', handleEditProgram);
    
    // Search functionality
    document.querySelector('.search-input').addEventListener('input', handleSearch);
}

// Handle add program
async function handleAddProgram(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('action', 'add');
    
    const btn = document.getElementById('addProgramBtn');
    setButtonLoading(btn, true);
    
    try {
        const response = await fetch('ajax_programs.php', {
            method: 'POST',
            body: formData
        });
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            throw new Error('Server returned non-JSON response');
        }
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', result.message);
            bootstrap.Modal.getInstance(document.getElementById('addProgramModal')).hide();
            e.target.reset();
            loadPrograms(); // Reload the table
            } else {
            showToast('error', result.message);
        }
    } catch (error) {
        console.error('Error adding program:', error);
        showToast('error', 'Error adding program: ' + error.message);
    } finally {
        setButtonLoading(btn, false);
    }
}

// Handle edit program
async function handleEditProgram(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('action', 'edit');
    
    const btn = document.getElementById('editProgramBtn');
    setButtonLoading(btn, true);
    
    try {
        const response = await fetch('ajax_edit_only.php', {
            method: 'POST',
            body: formData
        });
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            throw new Error('Server returned non-JSON response');
        }
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', result.message);
            bootstrap.Modal.getInstance(document.getElementById('editProgramModal')).hide();
            loadPrograms(); // Reload the table
        } else {
            showToast('error', result.message);
        }
    } catch (error) {
        console.error('Error updating program:', error);
        showToast('error', 'Error updating program: ' + error.message);
    } finally {
        setButtonLoading(btn, false);
    }
}

// Edit program function
function editProgram(id) {
    const program = programs.find(p => p.id == id);
    if (!program) return;
    
    document.getElementById('edit_id').value = program.id;
    document.getElementById('edit_name').value = program.name;
    document.getElementById('edit_code').value = program.code || '';
    document.getElementById('edit_department_id').value = program.department_id;
    document.getElementById('edit_duration').value = program.duration_years || program.duration || '';
    
    // Clear any previous alerts
    document.getElementById('editFormAlert').innerHTML = '';
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editProgramModal'));
    modal.show();
}

// Delete program function
async function deleteProgram(id) {
    const program = programs.find(p => p.id == id);
    if (!program) return;
    
    // Find the delete button for this program
    const deleteBtn = document.querySelector(`button[data-id="${id}"]`);
    
    const confirmed = await customDanger(
        `Are you sure you want to delete "${program.name}"?<br><br><strong>This action cannot be undone!</strong><br><br>This will permanently remove the program and all associated data from the system.`,
        {
            title: 'Delete Program',
            confirmText: 'Delete Permanently',
            cancelText: 'Cancel',
            confirmButtonClass: 'danger'
        }
    );
    
    if (!confirmed) {
        return;
    }
    
    // Show loading state
    if (deleteBtn) {
        setButtonLoading(deleteBtn, true);
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        const response = await fetch('ajax_programs.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        // Check if response is ok
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response:', text);
            throw new Error('Server returned non-JSON response');
        }
        
        const result = await response.json();
        
        if (result.success) {
            showToast('success', result.message);
            loadPrograms(); // Reload the table
        } else {
            showToast('error', result.message);
        }
    } catch (error) {
        console.error('Error deleting program:', error);
        showToast('error', 'Error deleting program: ' + error.message);
    } finally {
        // Hide loading state
        if (deleteBtn) {
            setButtonLoading(deleteBtn, false);
        }
    }
}

// Handle search
function handleSearch(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#programsTableBody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// Utility functions
function setButtonLoading(btn, loading) {
    const text = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.btn-spinner');
    
    if (loading) {
        text.classList.add('d-none');
        spinner.classList.remove('d-none');
        btn.disabled = true;
    } else {
        text.classList.remove('d-none');
        spinner.classList.add('d-none');
        btn.disabled = false;
    }
}

function showToast(type, message) {
    const toast = document.getElementById('toastNotification');
    const toastBody = toast.querySelector('.toast-body');
    const toastHeader = toast.querySelector('.toast-header');
    
    // Set the message
    toastBody.textContent = message;
    
    // Set the icon and color based on type
    const icon = toastHeader.querySelector('i');
    if (type === 'success') {
        icon.className = 'fas fa-check-circle me-2 text-success';
        toast.classList.remove('bg-danger');
        toast.classList.add('bg-success');
    } else {
        icon.className = 'fas fa-exclamation-circle me-2 text-danger';
        toast.classList.remove('bg-success');
        toast.classList.add('bg-danger');
    }
    
    // Show the toast
    const bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: 5000
    });
    bsToast.show();
}

function showAlert(type, message, containerId = null) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    if (containerId) {
        document.getElementById(containerId).innerHTML = alertHtml;
    } else {
        // Show at top of page
        const container = document.querySelector('.table-container');
        const existingAlert = container.querySelector('.alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        container.insertAdjacentHTML('afterbegin', alertHtml);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include 'includes/footer.php'; ?>
