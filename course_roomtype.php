<?php
include 'connect.php';

// Page title and layout includes
$pageTitle = 'Course Room Type Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Initialize empty arrays for data - will be populated via AJAX
$courses = [];
$room_types = [];
$table_data = [];
?>

<!-- Additional CSS for Select2 and custom styling -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    .select2-container {
        width: 100% !important;
    }
    
    /* Enhanced AJAX Loading States */
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
    
    /* Form validation styles */
    .is-invalid {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }
    
    .invalid-feedback {
        display: block;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }
    
    /* Enhanced table animations */
    .table tbody tr {
        transition: all 0.3s ease;
    }
    
    .table tbody tr.fade-in {
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
    
    /* Success/Error message animations */
    .alert {
        animation: slideDown 0.3s ease-out;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-building me-2"></i>Course Room Type Management</h4>
            <div class="d-flex gap-2">
                <!-- Search functionality -->
                <div class="search-container me-3">
                    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search courses...">
                </div>
                <button class="btn btn-outline-light me-2" data-bs-toggle="modal" data-bs-target="#bulkCourseModal">
                    <i class="fas fa-layer-group me-1"></i>Bulk Add
                </button>
                <button class="btn btn-outline-light me-2" onclick="refreshData()" title="Refresh Data">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="fas fa-plus me-1"></i>Add Single
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="courseRoomTypeTable">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Preferred Room Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Table content will be loaded via AJAX -->
                    <tr id="loadingRow">
                        <td colspan="4" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="mt-2">Loading course room type data...</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

    <!-- Bulk Add Modal -->
    <div class="modal fade" id="bulkCourseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Add Course Room Type Preferences</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="bulkAddForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <label for="bulk_course_ids" class="form-label">Courses *</label>
                                <select class="form-select" id="bulk_course_ids" name="course_ids[]" multiple required>
                                    <!-- Options will be loaded via AJAX -->
                                </select>
                                <div class="form-text">Hold Ctrl/Cmd to select multiple courses</div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="bulk_room_type" class="form-label">Room Type *</label>
                                <select class="form-select" id="bulk_room_type" name="room_type" required>
                                    <option value="">Select Room Type</option>
                                    <!-- Options will be loaded via AJAX -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This will assign the selected room type preference to all selected courses.
                            Courses that already have preferences will be skipped.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="bulkSubmitBtn">
                            <i class="fas fa-save me-1"></i>Add Preferences
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Single Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Single Course Room Type Preference</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="singleAddForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Course *</label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="">Select Course</option>
                                <!-- Options will be loaded via AJAX -->
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="room_type" class="form-label">Room Type *</label>
                            <select class="form-select" id="room_type" name="room_type" required>
                                <option value="">Select Room Type</option>
                                <!-- Options will be loaded via AJAX -->
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="singleSubmitBtn">
                            <i class="fas fa-save me-1"></i>Add Preference
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Course Room Type Preference</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="course_id" id="edit_course_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <input type="text" class="form-control" id="edit_course_display" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_room_type" class="form-label">Room Type *</label>
                            <select class="form-select" id="edit_room_type" name="room_type" required>
                                <option value="">Select Room Type</option>
                                <!-- Options will be loaded via AJAX -->
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                            <i class="fas fa-save me-1"></i>Update Preference
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- Enhanced AJAX JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Global variables
    let courses = [];
    let roomTypes = [];
    let tableData = [];

    // Initialize Select2 for better dropdown experience
    $('#bulk_course_ids').select2({
        placeholder: "Select courses...",
        allowClear: true
    });

    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#singleAddForm').on('submit', handleSingleAdd);
    $('#bulkAddForm').on('submit', handleBulkAdd);
    $('#editForm').on('submit', handleEdit);
    
    // Clear validation on input change
    $('input, select').on('input change', function() {
        $(this).removeClass('is-invalid');
    });
    
    // Search functionality
    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        filterTable(searchTerm);
    });
    
    // Filter table based on search term
    function filterTable(searchTerm) {
        const rows = $('#tableBody tr');
        let visibleCount = 0;
        
        rows.each(function() {
            const row = $(this);
            const courseCode = row.find('td:first').text().toLowerCase();
            const courseName = row.find('td:nth-child(2)').text().toLowerCase();
            const roomType = row.find('td:nth-child(3)').text().toLowerCase();
            
            if (searchTerm === '' || 
                courseCode.includes(searchTerm) || 
                courseName.includes(searchTerm) || 
                roomType.includes(searchTerm)) {
                row.show();
                visibleCount++;
            } else {
                row.hide();
            }
        });
        
        // Show "no results" message if no rows are visible
        if (visibleCount === 0 && searchTerm !== '') {
            $('#tableBody').append(`
                <tr id="noResultsRow">
                    <td colspan="4" class="text-center py-4 text-muted">
                        <i class="fas fa-search me-2"></i>No courses found matching "${searchTerm}"
                    </td>
                </tr>
            `);
        } else {
            $('#noResultsRow').remove();
        }
    }

    // Form validation functions
    function validateForm(formId) {
        const form = document.getElementById(formId);
        const inputs = form.querySelectorAll('input[required], select[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                isValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        return isValid;
    }
    
    function clearFormValidation(formId) {
        const form = document.getElementById(formId);
        const inputs = form.querySelectorAll('.is-invalid');
        inputs.forEach(input => {
            input.classList.remove('is-invalid');
        });
    }
    
    function setButtonLoading(buttonId, isLoading, loadingText = 'Processing...') {
        const btn = document.getElementById(buttonId);
        if (isLoading) {
            btn.classList.add('btn-loading');
            btn.disabled = true;
            btn.setAttribute('data-original-text', btn.innerHTML);
            btn.innerHTML = `<i class="fas fa-spinner fa-spin me-1"></i>${loadingText}`;
        } else {
            btn.classList.remove('btn-loading');
            btn.disabled = false;
            btn.innerHTML = btn.getAttribute('data-original-text') || btn.innerHTML;
        }
    }

    // Enhanced AJAX call with retry functionality
    function makeAjaxCall(url, data, retries = 3) {
        return fetch(url, {
            method: 'POST',
            body: data
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .catch(error => {
            if (retries > 0) {
                console.warn(`Request failed, retrying... (${retries} attempts left)`);
                return new Promise(resolve => {
                    setTimeout(() => {
                        resolve(makeAjaxCall(url, data, retries - 1));
                    }, 1000);
                });
            }
            throw error;
        });
    }

    // Load courses data
    function loadCourses() {
        return fetch('ajax_course_roomtype.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_courses'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                courses = data.data;
            } else {
                throw new Error(data.message);
            }
        });
    }

    // Load room types data
    function loadRoomTypes() {
        return fetch('ajax_course_roomtype.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_room_types'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                roomTypes = data.data;
            } else {
                throw new Error(data.message);
            }
        });
    }

    // Load table data
    function loadTableData() {
        return fetch('ajax_course_roomtype.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_table_data'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                tableData = data.data;
            } else {
                throw new Error(data.message);
            }
        });
    }

    // Populate dropdown options
    function populateDropdowns() {
        // Populate single add modal
        const singleCourseSelect = $('#course_id');
        const singleRoomSelect = $('#room_type');
        
        singleCourseSelect.empty().append('<option value="">Select Course</option>');
        courses.forEach(course => {
            singleCourseSelect.append(`<option value="${course.id}">${course.course_code} - ${course.course_name}</option>`);
        });

        singleRoomSelect.empty().append('<option value="">Select Room Type</option>');
        roomTypes.forEach(type => {
            singleRoomSelect.append(`<option value="${type.id}">${type.name}</option>`);
        });

        // Populate bulk add modal
        const bulkCourseSelect = $('#bulk_course_ids');
        const bulkRoomSelect = $('#bulk_room_type');
        
        bulkCourseSelect.empty();
        courses.forEach(course => {
            bulkCourseSelect.append(`<option value="${course.id}">${course.course_code} - ${course.course_name}</option>`);
        });

        bulkRoomSelect.empty().append('<option value="">Select Room Type</option>');
        roomTypes.forEach(type => {
            bulkRoomSelect.append(`<option value="${type.id}">${type.name}</option>`);
        });

        // Populate edit modal
        const editRoomSelect = $('#edit_room_type');
        editRoomSelect.empty().append('<option value="">Select Room Type</option>');
        roomTypes.forEach(type => {
            editRoomSelect.append(`<option value="${type.id}">${type.name}</option>`);
        });

        // Reinitialize Select2
        $('#bulk_course_ids').select2({
            placeholder: "Select courses...",
            allowClear: true
        });
    }

    // Render table data
    function renderTable() {
        const tbody = $('#tableBody');
        tbody.empty();

        if (tableData.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="4" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No course room type preferences found</p>
                    </td>
                </tr>
            `);
        } else {
            tableData.forEach(row => {
                tbody.append(`
                    <tr data-course-id="${row.course_id}">
                        <td>${escapeHtml(row.course_code)}</td>
                        <td>${escapeHtml(row.course_name)}</td>
                        <td>${escapeHtml(row.preferred_room_type)}</td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" 
                                    onclick="openEditModal(${row.course_id}, '${escapeHtml(row.course_code)}', '${escapeHtml(row.preferred_room_type || '')}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteCourseRoomType(${row.course_id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle single add form submission
    function handleSingleAdd(e) {
        e.preventDefault();
        
        // Validate form
        if (!validateForm('singleAddForm')) {
            showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = new FormData(e.target);
        formData.append('action', 'add_single');
        
        // Show loading state
        setButtonLoading('singleSubmitBtn', true, 'Adding...');
        
        makeAjaxCall('ajax_course_roomtype.php', formData)
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                $('#addCourseModal').modal('hide');
                e.target.reset();
                clearFormValidation('singleAddForm');
                
                // Add new row to table with animation
                tableData.push(data.data);
                renderTable();
                addRowAnimation(data.data.course_id);
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error adding course room type: ' + error.message, 'danger');
        })
        .finally(() => {
            setButtonLoading('singleSubmitBtn', false);
        });
    }

    // Handle bulk add form submission
    function handleBulkAdd(e) {
        e.preventDefault();
        
        // Validate form
        if (!validateForm('bulkAddForm')) {
            showAlert('Please select courses and room type.', 'warning');
            return;
        }
        
        const formData = new FormData(e.target);
        formData.append('action', 'add_bulk');
        
        // Show loading state
        setButtonLoading('bulkSubmitBtn', true, 'Adding...');
        
        makeAjaxCall('ajax_course_roomtype.php', formData)
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                $('#bulkCourseModal').modal('hide');
                e.target.reset();
                $('#bulk_course_ids').val(null).trigger('change');
                clearFormValidation('bulkAddForm');
                
                // Reload table data to show new entries
                loadTableData().then(() => {
                    renderTable();
                });
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error adding course room types: ' + error.message, 'danger');
        })
        .finally(() => {
            setButtonLoading('bulkSubmitBtn', false);
        });
    }

    // Handle edit form submission
    function handleEdit(e) {
        e.preventDefault();
        
        // Validate form
        if (!validateForm('editForm')) {
            showAlert('Please select a room type.', 'warning');
            return;
        }
        
        const formData = new FormData(e.target);
        formData.append('action', 'edit');
        
        // Show loading state
        setButtonLoading('editSubmitBtn', true, 'Updating...');
        
        makeAjaxCall('ajax_course_roomtype.php', formData)
        .then(data => {
            if (data.success) {
                showAlert(data.message, 'success');
                $('#editModal').modal('hide');
                clearFormValidation('editForm');
                
                // Update table data
                const index = tableData.findIndex(item => item.course_id == data.data.course_id);
                if (index !== -1) {
                    tableData[index] = data.data;
                    renderTable();
                    addRowAnimation(data.data.course_id);
                }
            } else {
                showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            showAlert('Error updating course room type: ' + error.message, 'danger');
        })
        .finally(() => {
            setButtonLoading('editSubmitBtn', false);
        });
    }

    // Add row animation
    function addRowAnimation(courseId) {
        setTimeout(() => {
            const row = document.querySelector(`tr[data-course-id="${courseId}"]`);
            if (row) {
                row.classList.add('fade-in');
            }
        }, 100);
    }
    
    // Enhanced alert system with different types
    function showAlert(message, type = 'info') {
        const alertContainer = $('#alertContainer');
        const alertId = 'alert-' + Date.now();
        
        // Map alert types to icons
        const iconMap = {
            'success': 'check-circle',
            'danger': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        
        alertContainer.html(`
            <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="fas fa-${iconMap[type] || 'info-circle'} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        // Auto-dismiss after different times based on type
        const dismissTime = type === 'success' ? 3000 : 5000;
        setTimeout(() => {
            $(`#${alertId}`).alert('close');
        }, dismissTime);
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});

// Global functions for button clicks
function refreshData() {
    const refreshBtn = document.querySelector('button[onclick="refreshData()"]');
    const originalContent = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
    refreshBtn.disabled = true;
    
    Promise.all([
        loadCourses(),
        loadRoomTypes(),
        loadTableData()
    ]).then(() => {
        populateDropdowns();
        renderTable();
        showAlert('Data refreshed successfully!', 'success');
    }).catch(error => {
        showAlert('Error refreshing data: ' + error.message, 'danger');
    }).finally(() => {
        refreshBtn.innerHTML = originalContent;
        refreshBtn.disabled = false;
    });
}

function openEditModal(courseId, courseCode, roomType) {
    document.getElementById('edit_course_id').value = courseId;
    document.getElementById('edit_course_display').value = courseCode;
    document.getElementById('edit_room_type').value = roomType;
    
    var el = document.getElementById('editModal');
    if (!el) return console.error('editModal element missing');
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
    bootstrap.Modal.getOrCreateInstance(el).show();
}

function deleteCourseRoomType(courseId) {
    if (!confirm('Are you sure you want to delete this room type preference?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('course_id', courseId);
    
    // Show loading state on delete button
    const deleteBtn = document.querySelector(`button[onclick="deleteCourseRoomType(${courseId})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    fetch('ajax_course_roomtype.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Remove row from table with animation
            const row = document.querySelector(`tr[data-course-id="${courseId}"]`);
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-100%)';
                setTimeout(() => {
                    row.remove();
                    
                    // Check if table is empty
                    const tbody = document.getElementById('tableBody');
                    if (tbody.children.length === 0) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="4" class="empty-state">
                                    <i class="fas fa-info-circle"></i>
                                    <p>No course room type preferences found</p>
                                </td>
                            </tr>
                        `;
                    }
                }, 300);
            }
        } else {
            // Show error message
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>${data.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const alertContainer = document.getElementById('alertContainer');
        alertContainer.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>Error deleting course room type: ${error.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    })
    .finally(() => {
        // Restore button state
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>
