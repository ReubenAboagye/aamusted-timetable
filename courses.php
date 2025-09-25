<?php
$pageTitle = 'Courses Management';

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
$courses = [];
$departments = [];
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
    
    @media (max-width: 768px) {
        .table-header {
            flex-direction: column;
            align-items: stretch !important;
            gap: 1rem;
        }
        
        .table-header h4 {
            text-align: center;
        }
        
        .table-header .d-flex {
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .search-container {
            order: 1;
            margin-bottom: 0.5rem;
        }
        
        .search-input {
            max-width: 100%;
        }
    }
    
    @media (max-width: 576px) {
        .table-header .d-flex {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .search-container {
            order: 0;
            margin-bottom: 0.5rem;
        }
        
        .btn {
            width: 100%;
            margin-bottom: 0.25rem;
        }
    }
</style>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-book me-2"></i>Courses Management</h4>
            <div class="d-flex gap-2 align-items-center">
                <!-- Search functionality -->
                <div class="search-container me-3">
                    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search courses...">
                </div>
                <button class="btn btn-outline-light me-2" onclick="refreshData()" title="Refresh Data">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="fas fa-plus me-1"></i>Add Course
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="courseTable">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Hours/Week</th>
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
                            <div class="mt-2">Loading course data...</div>
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

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addCourseForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="course_name" class="form-label">Course Name *</label>
                        <input type="text" class="form-control" id="course_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="course_code" class="form-label">Course Code *</label>
                        <input type="text" class="form-control" id="course_code" name="code" required>
                        <div class="form-text">Enter a unique code for this course</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="course_department" class="form-label">Department *</label>
                        <select class="form-select" id="course_department" name="department_id" required>
                            <option value="">Select Department</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="course_hours" class="form-label">Hours Per Week</label>
                        <input type="number" class="form-control" id="course_hours" name="hours_per_week" value="3" min="1" max="20">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="course_is_active" name="is_active" checked>
                            <label class="form-check-label" for="course_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCourseForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_course_id">
                    
                    <div class="mb-3">
                        <label for="edit_course_name" class="form-label">Course Name *</label>
                        <input type="text" class="form-control" id="edit_course_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_course_code" class="form-label">Course Code *</label>
                        <input type="text" class="form-control" id="edit_course_code" name="code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_course_department" class="form-label">Department *</label>
                        <select class="form-select" id="edit_course_department" name="department_id" required>
                            <option value="">Select Department</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_course_hours" class="form-label">Hours Per Week</label>
                        <input type="number" class="form-control" id="edit_course_hours" name="hours_per_week" min="1" max="20">
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_course_is_active" name="is_active">
                            <label class="form-check-label" for="edit_course_is_active">
                                Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                        <i class="fas fa-save me-1"></i>Update Course
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Cache bust: <?php echo time(); ?>
// Global variables
let courses = [];
let departments = [];

// Load initial data from server - defined globally
window.loadInitialData = function() {
        // Set a timeout for loading
        const timeoutId = setTimeout(() => {
            const loadingRow = document.getElementById('loadingRow');
            if (loadingRow) {
                loadingRow.innerHTML = `
                    <td colspan="6" class="text-center py-4">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Loading is taking longer than expected. 
                            <button class="btn btn-sm btn-outline-warning ms-2" onclick="window.loadInitialData()">
                                <i class="fas fa-redo me-1"></i>Retry
                            </button>
                        </div>
                    </td>
                `;
            }
        }, 10000); // 10 second timeout
        
        Promise.all([
            AjaxUtils.makeRequest('course', 'get_list'),
            AjaxUtils.makeRequest('department', 'get_list')
        ])
        .then(([coursesData, departmentsData]) => {
            clearTimeout(timeoutId);
            
            if (coursesData.success && departmentsData.success) {
                courses = coursesData.data;
                departments = departmentsData.data;
                populateDepartmentDropdowns();
                renderTable();
                console.log('Data loaded successfully:', { courses: courses.length, departments: departments.length });
            } else {
                throw new Error(coursesData.message || departmentsData.message);
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
                    <td colspan="6" class="text-center py-4">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Failed to load data: ${error.message}
                            <button class="btn btn-sm btn-outline-danger ms-2" onclick="window.loadInitialData()">
                                <i class="fas fa-redo me-1"></i>Retry
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
}

// Populate department dropdowns - moved to global scope
window.populateDepartmentDropdowns = function() {
    const addSelect = $('#course_department');
    const editSelect = $('#edit_course_department');
    
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

// Render table data - moved to global scope
window.renderTable = function() {
    const tbody = $('#tableBody');
    tbody.empty();

    if (courses.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="6" class="empty-state">
                    <i class="fas fa-info-circle"></i>
                    <p>No courses found</p>
                </td>
            </tr>
        `);
    } else {
        courses.forEach(course => {
            tbody.append(`
                <tr data-id="${course.id}">
                    <td><strong>${AjaxUtils.escapeHtml(course.code)}</strong></td>
                    <td>${AjaxUtils.escapeHtml(course.name)}</td>
                    <td>${AjaxUtils.escapeHtml(course.department_name || 'N/A')}</td>
                    <td><span class="badge bg-info">${course.hours_per_week}</span></td>
                    <td>
                        <span class="badge ${course.is_active ? 'bg-success' : 'bg-secondary'}">
                            ${course.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-warning btn-sm me-1" 
                                onclick="openEditModal(${course.id}, '${AjaxUtils.escapeHtml(course.name)}', '${AjaxUtils.escapeHtml(course.code)}', ${course.department_id}, ${course.hours_per_week}, ${course.is_active})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" 
                                onclick="deleteCourse(${course.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }
}

$(document).ready(function() {
    // Load initial data
    window.loadInitialData();

    // Form submission handlers
    $('#addCourseForm').on('submit', handleAddCourse);
    $('#editCourseForm').on('submit', handleEditCourse);
    
    // Initialize search functionality
    AjaxUtils.initSearch('searchInput', 'tableBody');
});

// Handle add course form submission
function handleAddCourse(e) {
    e.preventDefault();
    
    // Validate form
    if (!AjaxUtils.validateForm('addCourseForm')) {
        AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
        return;
    }
    
    const formData = {
        name: $('#course_name').val(),
        code: $('#course_code').val(),
        department_id: $('#course_department').val(),
        hours_per_week: $('#course_hours').val(),
        is_active: $('#course_is_active').is(':checked') ? 1 : 0
    };
    
    // Show loading state
    AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
    
    AjaxUtils.makeRequest('course', 'add', formData)
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            $('#addCourseModal').modal('hide');
            e.target.reset();
            AjaxUtils.clearFormValidation('addCourseForm');
            
            // Add new course to list
            courses.push({
                id: data.data.id,
                name: formData.name,
                code: formData.code,
                department_id: formData.department_id,
                hours_per_week: formData.hours_per_week,
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
        AjaxUtils.showAlert('Error adding course: ' + error.message, 'danger');
    })
    .finally(() => {
        AjaxUtils.setButtonLoading('addSubmitBtn', false);
    });
}

// Handle edit course form submission
function handleEditCourse(e) {
    e.preventDefault();
    
    // Validate form
    if (!AjaxUtils.validateForm('editCourseForm')) {
        AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
        return;
    }
    
    const formData = {
        id: $('#edit_course_id').val(),
        name: $('#edit_course_name').val(),
        code: $('#edit_course_code').val(),
        department_id: $('#edit_course_department').val(),
        hours_per_week: $('#edit_course_hours').val(),
        is_active: $('#edit_course_is_active').is(':checked') ? 1 : 0
    };
    
    // Show loading state
    AjaxUtils.setButtonLoading('editSubmitBtn', true, 'Updating...');
    
    AjaxUtils.makeRequest('course', 'edit', formData)
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            $('#editCourseModal').modal('hide');
            AjaxUtils.clearFormValidation('editCourseForm');
            
            // Update course in list
            const index = courses.findIndex(course => course.id == formData.id);
            if (index !== -1) {
                courses[index] = {
                    ...courses[index],
                    name: formData.name,
                    code: formData.code,
                    department_id: formData.department_id,
                    hours_per_week: formData.hours_per_week,
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
        AjaxUtils.showAlert('Error updating course: ' + error.message, 'danger');
    })
    .finally(() => {
        AjaxUtils.setButtonLoading('editSubmitBtn', false);
    });
}

// Global functions for button clicks
function refreshData() {
    const refreshBtn = document.querySelector('button[onclick="refreshData()"]');
    const originalContent = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
    refreshBtn.disabled = true;
    
    Promise.all([
        AjaxUtils.makeRequest('course', 'get_list'),
        AjaxUtils.makeRequest('department', 'get_list')
    ])
    .then(([coursesData, departmentsData]) => {
        if (coursesData.success && departmentsData.success) {
            courses = coursesData.data;
            departments = departmentsData.data;
            populateDepartmentDropdowns();
            renderTable();
            AjaxUtils.showAlert('Data refreshed successfully!', 'success');
        } else {
            throw new Error(coursesData.message || departmentsData.message);
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

function openEditModal(id, name, code, departmentId, hoursPerWeek, isActive) {
    document.getElementById('edit_course_id').value = id;
    document.getElementById('edit_course_name').value = name;
    document.getElementById('edit_course_code').value = code;
    document.getElementById('edit_course_department').value = departmentId;
    document.getElementById('edit_course_hours').value = hoursPerWeek;
    document.getElementById('edit_course_is_active').checked = isActive == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editCourseModal'));
    modal.show();
}

function deleteCourse(id) {
    if (!confirm('Are you sure you want to delete this course? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteCourse(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('course', 'delete', { id: id })
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove course from list
            courses = courses.filter(course => course.id != id);
            renderTable();
            
            // Check if table is empty
            if (courses.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No courses found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting course: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>
