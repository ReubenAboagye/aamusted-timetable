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

/* Upload area styling */
.upload-area {
    transition: all 0.3s ease;
    border: 2px dashed #dee2e6 !important;
}

.upload-area:hover {
    border-color: var(--primary-color) !important;
    background-color: rgba(128, 0, 32, 0.05);
}

.upload-area.border-primary {
    border-color: var(--primary-color) !important;
    background-color: rgba(128, 0, 32, 0.1);
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
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importCourseModal" title="Import Courses">
                    <i class="fas fa-file-import me-1"></i>Import
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

<!-- Import Course Modal -->
<div class="modal fade" id="importCourseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Courses (CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div id="uploadArea" class="upload-area p-4 text-center border rounded" style="cursor:pointer;">
                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                        <p class="mb-1">Drop CSV file here or <strong>click to browse</strong></p>
                        <small class="text-muted">Expected headers: name,code,department_id,hours_per_week,is_active</small>
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
                                    <th>Hours</th>
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
$(document).ready(function() {
    // Global variables
    let courses = [];
    let departments = [];
    let importDataCourses = [];
    let currentImportStep = 1;

    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addCourseForm').on('submit', handleAddCourse);
    $('#editCourseForm').on('submit', handleEditCourse);
    
    // Initialize search functionality
    AjaxUtils.initSearch('searchInput', 'tableBody');

    // Setup import functionality
    setupImportFunctionality();

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

    // Render table data
    function renderTable() {
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
});

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

// Import functionality
let importDataCourses = [];

// Setup import functionality
function setupImportFunctionality() {
    const uploadArea = document.getElementById('uploadArea');
    const csvFile = document.getElementById('csvFile');
    const processBtn = document.getElementById('processBtn');

    // Click to browse
    uploadArea.addEventListener('click', () => csvFile.click());

    // File input change
    csvFile.addEventListener('change', handleFileSelect);

    // Drag and drop
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('border-primary');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('border-primary');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('border-primary');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            csvFile.files = files;
            handleFileSelect();
        }
    });

    // Process button
    processBtn.addEventListener('click', processCourseImport);
}

function handleFileSelect() {
    const file = document.getElementById('csvFile').files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = parseCourseCSV(e.target.result);
            if (data.length > 0) {
                importDataCourses = validateCourseData(data);
                showCoursePreview();
                document.getElementById('processBtn').disabled = false;
            } else {
                showImportAlert('No data found in the file.', 'warning');
            }
        } catch (error) {
            showImportAlert('Error processing file: ' + error.message, 'danger');
        }
    };
    reader.readAsText(file);
}

function parseCourseCSV(csvText) {
    const lines = csvText.split('\n').filter(l => l.trim());
    if (lines.length < 2) {
        throw new Error('CSV file must contain at least a header row and one data row');
    }
    
    // Parse header row
    const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, '').toLowerCase());
    
    // Expected headers
    const expectedHeaders = ['name', 'code', 'department_id', 'hours_per_week', 'is_active'];
    const missingHeaders = expectedHeaders.filter(h => !headers.includes(h));
    
    if (missingHeaders.length > 0) {
        throw new Error(`Missing required headers: ${missingHeaders.join(', ')}`);
    }
    
    const data = [];
    
    // Parse data rows (skip header)
    for (let i = 1; i < lines.length; i++) {
        const values = lines[i].split(',').map(v => v.trim().replace(/"/g, ''));
        if (values.length >= 3) {
            const row = {};
            headers.forEach((header, index) => {
                row[header] = values[index] || '';
            });
            data.push(row);
        }
    }
    
    return data;
}

function validateCourseData(data) {
    return data.map((row, index) => {
        const validated = {
            name: row.name.trim(),
            code: row.code.trim(),
            department_id: parseInt(row.department_id) || 0,
            hours_per_week: parseInt(row.hours_per_week) || 3,
            is_active: (row.is_active === '1' || row.is_active === 'true' || row.is_active === '') ? 1 : 0
        };
        
        validated.valid = true;
        validated.errors = [];
        
        if (!validated.name) {
            validated.valid = false;
            validated.errors.push('Name required');
        }
        
        if (!validated.code) {
            validated.valid = false;
            validated.errors.push('Code required');
        }
        
        if (!validated.department_id || validated.department_id <= 0) {
            validated.valid = false;
            validated.errors.push('Valid department ID required');
        } else {
            // Check if department exists
            const dept = departments.find(d => d.id == validated.department_id);
            if (!dept) {
                validated.valid = false;
                validated.errors.push('Department does not exist');
            } else {
                validated.department_name = dept.name;
            }
        }
        
        if (validated.hours_per_week < 1 || validated.hours_per_week > 20) {
            validated.valid = false;
            validated.errors.push('Hours per week must be between 1 and 20');
        }
        
        // Check for duplicate code
        const existingCourse = courses.find(c => 
            c.code.toLowerCase() === validated.code.toLowerCase()
        );
        if (existingCourse) {
            validated.valid = false;
            validated.errors.push('Course code already exists');
        }
        
        return validated;
    });
}

function showCoursePreview() {
    const tbody = document.getElementById('previewBody');
    tbody.innerHTML = '';
    const previewRows = importDataCourses.slice(0, 10);
    let validCount = 0;
    let errorCount = 0;

    previewRows.forEach((row, index) => {
        const rowClass = row.valid ? 'table-success' : 'table-danger';
        tbody.innerHTML += `
            <tr class="${rowClass}">
                <td>${index + 1}</td>
                <td>${AjaxUtils.escapeHtml(row.name)}</td>
                <td><strong>${AjaxUtils.escapeHtml(row.code)}</strong></td>
                <td>${row.department_id}</td>
                <td><span class="badge bg-info">${row.hours_per_week}</span></td>
                <td>${row.is_active ? 'Active' : 'Inactive'}</td>
                <td>
                    <span class="badge ${row.valid ? 'bg-success' : 'bg-danger'}">
                        ${row.valid ? 'Valid' : 'Invalid'}
                    </span>
                    ${row.errors.length > 0 ? '<br><small>' + row.errors.join(', ') + '</small>' : ''}
                </td>
            </tr>
        `;
        
        if (row.valid) validCount++;
        else errorCount++;
    });

    // Show summary
    const summaryDiv = document.getElementById('importSummary');
    summaryDiv.innerHTML = `
        <div class="alert alert-info">
            <strong>Summary:</strong> 
            <span class="text-success">${validCount} valid</span>, 
            <span class="text-danger">${errorCount} invalid</span> records
            ${importDataCourses.length > 10 ? ` (showing first 10 of ${importDataCourses.length})` : ''}
        </div>
    `;
}

async function processCourseImport() {
    const validRecords = importDataCourses.filter(row => row.valid);
    
    if (validRecords.length === 0) {
        showImportAlert('No valid records to import.', 'warning');
        return;
    }
    
    const processBtn = document.getElementById('processBtn');
    const originalText = processBtn.innerHTML;
    processBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
    processBtn.disabled = true;
    
    try {
        // Prepare data for import
        const importData = validRecords.map(row => ({
            name: row.name,
            code: row.code,
            department_id: row.department_id,
            hours_per_week: row.hours_per_week,
            is_active: row.is_active
        }));
        
        const response = await fetch('ajax_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `module=course&action=bulk_import&import_data=${encodeURIComponent(JSON.stringify(importData))}&csrf_token=${getCSRFToken()}`
        });
        
        const data = await response.json();
        
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Add new courses to the list
            if (data.data && data.data.added_courses) {
                data.data.added_courses.forEach(course => {
                    courses.push(course);
                });
                renderTable();
            }
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('importCourseModal')).hide();
            
            // Reset form
            document.getElementById('csvFile').value = '';
            document.getElementById('previewBody').innerHTML = '<tr><td colspan="7" class="text-center text-muted">Upload a CSV file to preview</td></tr>';
            document.getElementById('importSummary').innerHTML = '';
            processBtn.disabled = true;
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    } catch (error) {
        AjaxUtils.showAlert('Error importing courses: ' + error.message, 'danger');
    } finally {
        processBtn.innerHTML = originalText;
        processBtn.disabled = false;
    }
}

function showImportAlert(message, type) {
    const alertClass = type === 'warning' ? 'alert-warning' : 'alert-danger';
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${AjaxUtils.escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    const container = document.querySelector('#importCourseModal .modal-body');
    const existingAlert = container.querySelector('.alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    container.insertAdjacentHTML('afterbegin', alertHtml);
}

function getCSRFToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}
</script>

<?php include 'includes/footer.php'; ?>
