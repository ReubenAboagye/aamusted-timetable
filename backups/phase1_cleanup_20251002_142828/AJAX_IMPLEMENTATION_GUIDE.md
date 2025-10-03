# AJAX Implementation Template and Documentation

## 🎉 **AJAX Implementation Progress**

### ✅ **Completed Pages:**
1. **course_roomtype.php** - ✅ Fully converted with advanced features
2. **department.php** - ✅ Converted to AJAX
3. **courses.php** - ✅ Converted to AJAX  
4. **lecturers.php** - ✅ Converted to AJAX

### 📋 **Remaining Pages to Convert:**
- programs.php
- classes.php
- rooms.php
- levels.php
- streams.php
- lecturer_courses.php
- class_courses.php

## 🏗️ **AJAX Infrastructure Created:**

### 1. **Centralized AJAX API** (`ajax_api.php`)
- ✅ Handles all CRUD operations across modules
- ✅ CSRF protection
- ✅ Input sanitization
- ✅ Error handling
- ✅ JSON responses

### 2. **Shared JavaScript Utilities** (`js/ajax-utils.js`)
- ✅ Enhanced AJAX calls with retry logic
- ✅ Form validation
- ✅ Loading states
- ✅ Alert system
- ✅ Search/filter functionality
- ✅ Animation utilities

### 3. **Updated Header** (`includes/header.php`)
- ✅ jQuery included
- ✅ AJAX utilities loaded
- ✅ CSRF token meta tag

## 📝 **Template for Converting Remaining Pages:**

### **Step 1: Update AJAX API Handler**
Add the module handler to `ajax_api.php`:

```php
// Example for programs module
function handleProgramActions($action, $conn) {
    switch ($action) {
        case 'add':
            $name = sanitizeInput($_POST['name'] ?? '');
            $code = sanitizeInput($_POST['code'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($name) || empty($code)) {
                sendResponse(false, 'Name and code are required');
            }
            
            // Check if program code already exists
            $check_sql = "SELECT id FROM programs WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $code);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendResponse(false, 'Program code already exists');
            }
            
            $sql = "INSERT INTO programs (name, code, is_active) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $name, $code, $is_active);
            
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                sendResponse(true, 'Program added successfully!', ['id' => $new_id]);
            } else {
                sendResponse(false, 'Error adding program: ' . $stmt->error);
            }
            break;
            
        case 'edit':
            // Edit logic here
            break;
            
        case 'delete':
            // Delete logic here
            break;
            
        case 'get_list':
            $sql = "SELECT * FROM programs ORDER BY name";
            $result = $conn->query($sql);
            
            $programs = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $programs[] = $row;
                }
            }
            
            sendResponse(true, 'Programs retrieved successfully', $programs);
            break;
            
        default:
            sendResponse(false, 'Invalid program action');
    }
}
```

### **Step 2: Create AJAX Page Template**
Use this template for each page:

```php
<?php
$pageTitle = 'Module Management';

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
$items = [];
$relatedData = []; // If needed for dropdowns
?>

<!-- Additional CSS for enhanced styling -->
<style>
    .search-container {
        margin-bottom: 20px;
    }
    
    .search-input {
        border: 2px solid #e9ecef;
        border-radius: 25px;
        padding: 10px 20px;
        width: 100%;
        max-width: 400px;
        transition: all 0.3s ease;
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
</style>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-icon me-2"></i>Module Management</h4>
            <div class="d-flex gap-2">
                <!-- Search functionality -->
                <div class="search-container me-3">
                    <input type="text" id="searchInput" class="form-control search-input" placeholder="Search items...">
                </div>
                <button class="btn btn-outline-light me-2" onclick="refreshData()" title="Refresh Data">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="fas fa-plus me-1"></i>Add Item
                </button>
            </div>
        </div>
        
        <!-- Dynamic Alert Container -->
        <div id="alertContainer" class="m-3"></div>

        <div class="table-responsive">
            <table class="table" id="itemTable">
                <thead>
                    <tr>
                        <th>Column 1</th>
                        <th>Column 2</th>
                        <th>Status</th>
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
                            <div class="mt-2">Loading data...</div>
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

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addItemForm">
                <div class="modal-body">
                    <!-- Form fields here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="addSubmitBtn">
                        <i class="fas fa-save me-1"></i>Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editItemForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_item_id">
                    <!-- Form fields here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="editSubmitBtn">
                        <i class="fas fa-save me-1"></i>Update Item
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Global variables
    let items = [];
    let relatedData = []; // If needed

    // Load initial data
    loadInitialData();

    // Form submission handlers
    $('#addItemForm').on('submit', handleAddItem);
    $('#editItemForm').on('submit', handleEditItem);
    
    // Initialize search functionality
    AjaxUtils.initSearch('searchInput', 'tableBody');

    // Load initial data from server
    function loadInitialData() {
        // Set a timeout for loading
        const timeoutId = setTimeout(() => {
            const loadingRow = document.getElementById('loadingRow');
            if (loadingRow) {
                loadingRow.innerHTML = `
                    <td colspan="4" class="text-center py-4">
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
        }, 10000);
        
        AjaxUtils.makeRequest('module_name', 'get_list')
        .then(data => {
            clearTimeout(timeoutId);
            if (data.success) {
                items = data.data;
                renderTable();
                console.log('Items loaded successfully:', items.length);
            } else {
                throw new Error(data.message);
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
                    <td colspan="4" class="text-center py-4">
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

    // Render table data
    function renderTable() {
        const tbody = $('#tableBody');
        tbody.empty();

        if (items.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="4" class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>No items found</p>
                    </td>
                </tr>
            `);
        } else {
            items.forEach(item => {
                tbody.append(`
                    <tr data-id="${item.id}">
                        <td>${AjaxUtils.escapeHtml(item.field1)}</td>
                        <td>${AjaxUtils.escapeHtml(item.field2)}</td>
                        <td>
                            <span class="badge ${item.is_active ? 'bg-success' : 'bg-secondary'}">
                                ${item.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm me-1" 
                                    onclick="openEditModal(${item.id}, '${AjaxUtils.escapeHtml(item.field1)}', '${AjaxUtils.escapeHtml(item.field2)}', ${item.is_active})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" 
                                    onclick="deleteItem(${item.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }

    // Handle add item form submission
    function handleAddItem(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('addItemForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            // Collect form data
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('addSubmitBtn', true, 'Adding...');
        
        AjaxUtils.makeRequest('module_name', 'add', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#addItemModal').modal('hide');
                e.target.reset();
                AjaxUtils.clearFormValidation('addItemForm');
                
                // Add new item to list
                items.push({
                    id: data.data.id,
                    // ... other fields
                });
                renderTable();
                AjaxUtils.addRowAnimation(data.data.id);
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error adding item: ' + error.message, 'danger');
        })
        .finally(() => {
            AjaxUtils.setButtonLoading('addSubmitBtn', false);
        });
    }

    // Handle edit item form submission
    function handleEditItem(e) {
        e.preventDefault();
        
        // Validate form
        if (!AjaxUtils.validateForm('editItemForm')) {
            AjaxUtils.showAlert('Please fill in all required fields.', 'warning');
            return;
        }
        
        const formData = {
            id: $('#edit_item_id').val(),
            // Collect form data
        };
        
        // Show loading state
        AjaxUtils.setButtonLoading('editSubmitBtn', true, 'Updating...');
        
        AjaxUtils.makeRequest('module_name', 'edit', formData)
        .then(data => {
            if (data.success) {
                AjaxUtils.showAlert(data.message, 'success');
                $('#editItemModal').modal('hide');
                AjaxUtils.clearFormValidation('editItemForm');
                
                // Update item in list
                const index = items.findIndex(item => item.id == formData.id);
                if (index !== -1) {
                    items[index] = {
                        ...items[index],
                        // ... updated fields
                    };
                    renderTable();
                    AjaxUtils.addRowAnimation(formData.id);
                }
            } else {
                AjaxUtils.showAlert(data.message, 'danger');
            }
        })
        .catch(error => {
            AjaxUtils.showAlert('Error updating item: ' + error.message, 'danger');
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
    
    AjaxUtils.makeRequest('module_name', 'get_list')
    .then(data => {
        if (data.success) {
            items = data.data;
            renderTable();
            AjaxUtils.showAlert('Data refreshed successfully!', 'success');
        } else {
            throw new Error(data.message);
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

function openEditModal(id, field1, field2, isActive) {
    document.getElementById('edit_item_id').value = id;
    document.getElementById('edit_field1').value = field1;
    document.getElementById('edit_field2').value = field2;
    document.getElementById('edit_is_active').checked = isActive == 1;
    
    const modal = new bootstrap.Modal(document.getElementById('editItemModal'));
    modal.show();
}

function deleteItem(id) {
    if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
        return;
    }
    
    const deleteBtn = document.querySelector(`button[onclick="deleteItem(${id})"]`);
    const originalContent = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    deleteBtn.disabled = true;
    
    AjaxUtils.makeRequest('module_name', 'delete', { id: id })
    .then(data => {
        if (data.success) {
            AjaxUtils.showAlert(data.message, 'success');
            
            // Remove item from list
            items = items.filter(item => item.id != id);
            renderTable();
            
            // Check if table is empty
            if (items.length === 0) {
                const tbody = document.getElementById('tableBody');
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>No items found</p>
                        </td>
                    </tr>
                `;
            }
        } else {
            AjaxUtils.showAlert(data.message, 'danger');
        }
    })
    .catch(error => {
        AjaxUtils.showAlert('Error deleting item: ' + error.message, 'danger');
    })
    .finally(() => {
        deleteBtn.innerHTML = originalContent;
        deleteBtn.disabled = false;
    });
}
</script>

<?php include 'includes/footer.php'; ?>
```

## 🚀 **Benefits of AJAX Implementation:**

### **User Experience:**
- ✅ **No Page Refreshes** - All operations happen seamlessly
- ✅ **Real-time Feedback** - Immediate success/error messages
- ✅ **Smooth Animations** - Professional transitions
- ✅ **Search Functionality** - Instant filtering
- ✅ **Loading States** - Clear visual feedback

### **Performance:**
- ✅ **Faster Interactions** - No full page reloads
- ✅ **Reduced Server Load** - Only necessary data transferred
- ✅ **Better Caching** - Static assets cached separately
- ✅ **Parallel Loading** - Multiple operations can run simultaneously

### **Maintainability:**
- ✅ **Centralized API** - Single endpoint for all operations
- ✅ **Reusable Components** - Shared utilities across pages
- ✅ **Consistent Error Handling** - Uniform error management
- ✅ **Easy Testing** - AJAX endpoints can be tested independently

## 📋 **Next Steps:**

1. **Use the template above** to convert remaining pages
2. **Add module handlers** to `ajax_api.php` for each page
3. **Test each conversion** thoroughly
4. **Update any dependent pages** that reference the converted pages

The infrastructure is now in place to quickly convert the remaining pages using the established patterns and utilities!