<?php
$pageTitle = 'Class Course Management';
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
$assignments = [];
$classes = [];
$courses = [];
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-link me-2"></i>Class Course Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                    <i class="fas fa-plus me-1"></i>Add Assignment
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkAssignModal">
                    <i class="fas fa-layer-group me-1"></i>Bulk Assign
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="assignmentTable">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Program</th>
                        <th>Stream</th>
                        <th>Course</th>
                        <th>Course Code</th>
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
                            <div class="mt-2">Loading assignment data...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Assignment Modal -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Class-Course Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addAssignmentForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="assignment_class" class="form-label">Class *</label>
                        <select class="form-select" id="assignment_class" name="class_id" required>
                            <option value="">Select Class</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assignment_course" class="form-label">Course *</label>
                        <select class="form-select" id="assignment_course" name="course_id" required>
                            <option value="">Select Course</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Assign Modal -->
<div class="modal fade" id="bulkAssignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Assign Classes to Courses</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkAssignForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Classes *</label>
                                <div id="classesList" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                    <!-- Class checkboxes will be loaded via AJAX -->
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Courses *</label>
                                <div id="coursesList" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                    <!-- Course checkboxes will be loaded via AJAX -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="bulkSubmitBtn">
                        <i class="fas fa-save me-1"></i>Bulk Assign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
let assignments = [];
let classes = [];
let courses = [];

$(document).ready(function() {
    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addAssignmentForm').on('submit', handleAddAssignment);
    $('#bulkAssignForm').on('submit', handleBulkAssign);

    // Load initial data from server
    function loadInitialData() {
        Promise.all([
            AjaxUtils.makeRequest('class_course', 'get_list'),
            AjaxUtils.makeRequest('class_course', 'get_classes'),
            AjaxUtils.makeRequest('class_course', 'get_courses')
        ])
        .then(([assignmentsData, classesData, coursesData]) => {
            if (assignmentsData.success && classesData.success && coursesData.success) {
                assignments = assignmentsData.data;
                classes = classesData.data;
                courses = coursesData.data;
                populateDropdowns();
                renderTable();
                console.log('Data loaded successfully:', { assignments: assignments.length, classes: classes.length, courses: courses.length });
            } else {
                throw new Error(assignmentsData.message || classesData.message || coursesData.message);
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
        const addClassSelect = $('#assignment_class');
        const addCourseSelect = $('#assignment_course');
        
        // Clear existing options except the first one
        addClassSelect.find('option:not(:first)').remove();
        addCourseSelect.find('option:not(:first)').remove();
        
        classes.forEach(cls => {
            addClassSelect.append(`<option value="${cls.id}">${AjaxUtils.escapeHtml(cls.name)} (${AjaxUtils.escapeHtml(cls.code)}) - ${AjaxUtils.escapeHtml(cls.program_name || 'N/A')}</option>`);
        });
        
        courses.forEach(course => {
            addCourseSelect.append(`<option value="${course.id}">${AjaxUtils.escapeHtml(course.name)} (${AjaxUtils.escapeHtml(course.code)}) - ${AjaxUtils.escapeHtml(course.department_name || 'N/A')}</option>`);
        });
        
        // Populate bulk lists
        populateBulkLists();
    }

    // Populate bulk lists
    function populateBulkLists() {
        const classesList = $('#classesList');
        const coursesList = $('#coursesList');
        
        classesList.empty();
        coursesList.empty();
        
        classes.forEach(cls => {
            classesList.append(`
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${cls.id}" id="class_${cls.id}" name="class_ids[]">
                    <label class="form-check-label" for="class_${cls.id}">
                        ${AjaxUtils.escapeHtml(cls.name)} (${AjaxUtils.escapeHtml(cls.code)}) - ${AjaxUtils.escapeHtml(cls.program_name || 'N/A')}
                    </label>
                </div>
            `);
        });
        
        courses.forEach(course => {
            coursesList.append(`
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${course.id}" id="course_${course.id}" name="course_ids[]">
                    <label class="form-check-label" for="course_${course.id}">
                        ${AjaxUtils.escapeHtml(course.name)} (${AjaxUtils.escapeHtml(course.code)}) - ${AjaxUtils.escapeHtml(course.department_name || 'N/A')}
                    </label>
                </div>
            `);
        });
    }

    // Render table data
    function renderTable() {
        const tbody = $('#tableBody');
        tbody.empty();

        if (assignments.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="6" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No assignments found</p>
                    </td>
                </tr>
            `);
        } else {
            assignments.forEach(assignment => {
                tbody.append(`
                    <tr data-id="${assignment.id}">
                        <td>${AjaxUtils.escapeHtml(assignment.class_name || 'N/A')}</td>
                        <td>${AjaxUtils.escapeHtml(assignment.program_name || 'N/A')}</td>
                        <td>${AjaxUtils.escapeHtml(assignment.stream_name || 'N/A')}</td>
                        <td>${AjaxUtils.escapeHtml(assignment.course_name || 'N/A')}</td>
                        <td><strong>${AjaxUtils.escapeHtml(assignment.course_code || 'N/A')}</strong></td>
                        <td>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteAssignment(${assignment.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle add assignment form submission
    function handleAddAssignment(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('addAssignmentForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            class_id: $('#assignment_class').val(),
            course_id: $('#assignment_course').val()
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('class_course', 'assign_single', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#addAssignmentModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('addAssignmentForm');
                
                // Reload data to get updated assignments
                loadInitialData();
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error adding assignment: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('addSubmitBtn', false);
        });
    }

    // Handle bulk assign form submission
    function handleBulkAssign(e) {
        e.preventDefault();
        
        const selectedClasses = $('input[name="class_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        const selectedCourses = $('input[name="course_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selectedClasses.length === 0) {
            AjaxUtils.showAlert('Please select at least one class.', 'warning');
            return;
        }
        
        if (selectedCourses.length === 0) {
            AjaxUtils.showAlert('Please select at least one course.', 'warning');
            return;
        }
        
        const formData = {
            class_ids: selectedClasses,
            course_ids: selectedCourses
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('bulkSubmitBtn', true, 'Assigning...');
        
        AjaxUtils.makeRequest('class_course', 'assign_bulk', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#bulkAssignModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('bulkAssignForm');
                
                // Uncheck all checkboxes
                $('input[name="class_ids[]"], input[name="course_ids[]"]').prop('checked', false);
                
                // Reload data to get updated assignments
                loadInitialData();
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error adding bulk assignments: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('bulkSubmitBtn', false);
        });
    }
});

// Global functions for button clicks
function deleteAssignment(id) {
    if (!confirm('Are you sure you want to delete this class-course assignment? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteAssignment(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('class_course', 'delete', { id: id })
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove assignment from list
            assignments = assignments.filter(assignment => assignment.id != id);
            renderTable();
            
            // Check if table is empty
            if (assignments.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No assignments found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting assignment: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>