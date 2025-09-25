<?php
$pageTitle = 'Map Courses to Lecturers';
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
$mappings = [];
$lecturers = [];
$courses = [];
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-link me-2"></i>Map Courses to Lecturers</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMappingModal">
                    <i class="fas fa-plus me-1"></i>Add Mapping
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkAddModal">
                    <i class="fas fa-layer-group me-1"></i>Bulk Add
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="mappingTable">
                <thead>
                    <tr>
                        <th>Lecturer</th>
                        <th>Department</th>
                        <th>Course</th>
                        <th>Course Code</th>
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
                            <div class="mt-2">Loading mapping data...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Mapping Modal -->
<div class="modal fade" id="addMappingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Lecturer-Course Mapping</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addMappingForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="mapping_lecturer" class="form-label">Lecturer *</label>
                        <select class="form-select" id="mapping_lecturer" name="lecturer_id" required>
                            <option value="">Select Lecturer</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="mapping_course" class="form-label">Course *</label>
                        <select class="form-select" id="mapping_course" name="course_id" required>
                            <option value="">Select Course</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Mapping
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Add Modal -->
<div class="modal fade" id="bulkAddModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Add Lecturer-Course Mappings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkAddForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="bulk_lecturer" class="form-label">Lecturer *</label>
                        <select class="form-select" id="bulk_lecturer" name="lecturer_id" required>
                            <option value="">Select Lecturer</option>
                            <!-- Options will be loaded via AJAX -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Courses *</label>
                        <div id="coursesList" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <!-- Course checkboxes will be loaded via AJAX -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="bulkSubmitBtn">
                        <i class="fas fa-save me-1"></i>Bulk Add Mappings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global variables
let mappings = [];
let lecturers = [];
let courses = [];

$(document).ready(function() {
    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addMappingForm').on('submit', handleAddMapping);
    $('#bulkAddForm').on('submit', handleBulkAdd);

    // Load initial data from server
    function loadInitialData() {
        Promise.all([
            AjaxUtils.makeRequest('lecturer_course', 'get_list'),
            AjaxUtils.makeRequest('lecturer_course', 'get_lecturers'),
            AjaxUtils.makeRequest('lecturer_course', 'get_courses')
        ])
        .then(([mappingsData, lecturersData, coursesData]) => {
            if (mappingsData.success && lecturersData.success && coursesData.success) {
                mappings = mappingsData.data;
                lecturers = lecturersData.data;
                courses = coursesData.data;
                populateDropdowns();
                renderTable();
                console.log('Data loaded successfully:', { mappings: mappings.length, lecturers: lecturers.length, courses: courses.length });
            } else {
                throw new Error(mappingsData.message || lecturersData.message || coursesData.message);
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

    // Populate dropdowns
    function populateDropdowns() {
        const addLecturerSelect = $('#mapping_lecturer');
        const bulkLecturerSelect = $('#bulk_lecturer');
        const addCourseSelect = $('#mapping_course');
        
        // Clear existing options except the first one
        addLecturerSelect.find('option:not(:first)').remove();
        bulkLecturerSelect.find('option:not(:first)').remove();
        addCourseSelect.find('option:not(:first)').remove();
        
        lecturers.forEach(lecturer => {
            addLecturerSelect.append(`<option value="${lecturer.id}">${AjaxUtils.escapeHtml(lecturer.name)} (${AjaxUtils.escapeHtml(lecturer.department_name || 'N/A')})</option>`);
            bulkLecturerSelect.append(`<option value="${lecturer.id}">${AjaxUtils.escapeHtml(lecturer.name)} (${AjaxUtils.escapeHtml(lecturer.department_name || 'N/A')})</option>`);
        });
        
        courses.forEach(course => {
            addCourseSelect.append(`<option value="${course.id}">${AjaxUtils.escapeHtml(course.name)} (${AjaxUtils.escapeHtml(course.code)})</option>`);
        });
        
        // Populate bulk courses list
        populateBulkCoursesList();
    }

    // Populate bulk courses list
    function populateBulkCoursesList() {
        const coursesList = $('#coursesList');
        coursesList.empty();
        
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

        if (mappings.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="5" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No mappings found</p>
                    </td>
                </tr>
            `);
        } else {
            mappings.forEach(mapping => {
                tbody.append(`
                    <tr data-id="${mapping.id}">
                        <td>${AjaxUtils.escapeHtml(mapping.lecturer_name || 'N/A')}</td>
                        <td>${AjaxUtils.escapeHtml(mapping.department_name || 'N/A')}</td>
                        <td>${AjaxUtils.escapeHtml(mapping.course_name || 'N/A')}</td>
                        <td><strong>${AjaxUtils.escapeHtml(mapping.course_code || 'N/A')}</strong></td>
                        <td>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteMapping(${mapping.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle add mapping form submission
    function handleAddMapping(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('addMappingForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            lecturer_id: $('#mapping_lecturer').val(),
            course_id: $('#mapping_course').val()
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('lecturer_course', 'add', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#addMappingModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('addMappingForm');
                
                // Reload data to get updated mappings
                loadInitialData();
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error adding mapping: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('addSubmitBtn', false);
        });
    }

    // Handle bulk add form submission
    function handleBulkAdd(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('bulkAddForm')) {
            AjaxUtils.showAlert('Please select a lecturer.', 'warning');
            return;
        }
        
        const selectedCourses = $('input[name="course_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selectedCourses.length === 0) {
            AjaxUtils.showAlert('Please select at least one course.', 'warning');
            return;
        }
        
        const formData = {
            lecturer_id: $('#bulk_lecturer').val(),
            course_ids: selectedCourses
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('bulkSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('lecturer_course', 'bulk_add', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#bulkAddModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('bulkAddForm');
                
                // Uncheck all course checkboxes
                $('input[name="course_ids[]"]').prop('checked', false);
                
                // Reload data to get updated mappings
                loadInitialData();
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error adding bulk mappings: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('bulkSubmitBtn', false);
        });
    }
});

// Global functions for button clicks
function deleteMapping(id) {
    if (!confirm('Are you sure you want to delete this lecturer-course mapping? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteMapping(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('lecturer_course', 'delete', { id: id })
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove mapping from list
            mappings = mappings.filter(mapping => mapping.id != id);
            renderTable();
            
            // Check if table is empty
            if (mappings.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No mappings found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting mapping: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>