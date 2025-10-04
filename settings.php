<?php
$pageTitle = 'Settings - Manage Inactive Records';
include 'includes/header.php';
include 'includes/sidebar.php';

// Include custom dialog system
echo '<link rel="stylesheet" href="css/custom-dialogs.css">';
echo '<script src="js/custom-dialogs.js"></script>';

// Database connection
include_once 'connect.php';

// Handle reactivation requests
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'bulk_reactivate') {
        $table = $_POST['table'];
        $ids = $_POST['ids'];
        
        try {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $sql = "UPDATE $table SET is_active = 1 WHERE id IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
            
            if ($stmt->execute()) {
                $success_message = count($ids) . " records reactivated successfully!";
            } else {
                $error_message = "Error reactivating records: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    } else {
        $table = $_POST['table'];
        $id = (int)$_POST['id'];
        
        try {
            $sql = "UPDATE $table SET is_active = 1 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = "Record reactivated successfully!";
            } else {
                $error_message = "Error reactivating record: " . $stmt->error;
            }
            $stmt->close();
        } catch (Exception $e) {
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Get inactive records grouped by table
$inactive_data = [];

$tables = [
    'programs' => ['name' => 'Programs', 'icon' => 'fas fa-graduation-cap', 'color' => 'primary'],
    'courses' => ['name' => 'Courses', 'icon' => 'fas fa-book', 'color' => 'info'],
    'lecturers' => ['name' => 'Lecturers', 'icon' => 'fas fa-chalkboard-teacher', 'color' => 'success'],
    'departments' => ['name' => 'Departments', 'icon' => 'fas fa-building', 'color' => 'warning'],
    'rooms' => ['name' => 'Rooms', 'icon' => 'fas fa-door-open', 'color' => 'secondary'],
    'room_types' => ['name' => 'Room Types', 'icon' => 'fas fa-tags', 'color' => 'dark'],
    'classes' => ['name' => 'Classes', 'icon' => 'fas fa-users', 'color' => 'danger'],
    'streams' => ['name' => 'Streams', 'icon' => 'fas fa-stream', 'color' => 'primary']
];

foreach ($tables as $table_name => $table_info) {
    // Define which tables have a 'code' column
    $tables_with_code = ['programs', 'courses', 'departments', 'classes', 'streams'];
    
    if (in_array($table_name, $tables_with_code)) {
        $query = "SELECT id, name, code FROM $table_name WHERE is_active = 0 ORDER BY name";
    } else {
        $query = "SELECT id, name FROM $table_name WHERE is_active = 0 ORDER BY name";
    }
    
    $result = $conn->query($query);
    
    $records = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Add null code for tables that don't have it
            if (!in_array($table_name, $tables_with_code)) {
                $row['code'] = null;
            }
            $records[] = $row;
        }
    }
    
    $inactive_data[$table_name] = [
        'info' => $table_info,
        'records' => $records,
        'count' => count($records)
    ];
}

$conn->close();
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-cog me-2"></i>Settings - Manage Inactive Records</h2>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Data Type Cards -->
                <div class="row mb-4">
                    <?php 
                    $total_inactive = 0;
                    foreach ($inactive_data as $table_name => $data): 
                        $total_inactive += $data['count'];
                    ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
                            <div class="card data-type-card h-100 <?= $data['count'] > 0 ? 'clickable' : '' ?>" 
                                 data-table="<?= $table_name ?>" 
                                 data-count="<?= $data['count'] ?>"
                                 style="cursor: <?= $data['count'] > 0 ? 'pointer' : 'default' ?>;">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="<?= $data['info']['icon'] ?> fa-3x text-<?= $data['info']['color'] ?>"></i>
                                    </div>
                                    <h5 class="card-title text-<?= $data['info']['color'] ?>"><?= $data['info']['name'] ?></h5>
                                    <div class="badge bg-<?= $data['info']['color'] ?> fs-6">
                                        <?= $data['count'] ?> inactive
                                    </div>
                                    <?php if ($data['count'] > 0): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">Click to manage</small>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-2">
                                            <small class="text-success">All active</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Summary Card -->
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <h5 class="card-title">
                            <i class="fas fa-chart-pie me-2"></i>Summary
                        </h5>
                        <p class="card-text">
                            Total inactive records: <span class="badge bg-secondary fs-6"><?= $total_inactive ?></span>
                        </p>
                        <?php if ($total_inactive === 0): ?>
                            <div class="text-success">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <p class="mb-0">All records are currently active!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Details Section (Hidden by default) -->
                <div id="detailsSection" class="card" style="display: none;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i><span id="detailsTitle">Record Details</span>
                        </h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="hideDetails()">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <button type="button" class="btn btn-success btn-sm" id="bulkReactivateBtn" onclick="bulkReactivate()" disabled>
                                    <i class="fas fa-undo me-1"></i>Reactivate Selected
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAll()">
                                    <i class="fas fa-check-square me-1"></i>Select All
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="deselectAll()">
                                    <i class="fas fa-square me-1"></i>Deselect All
                                </button>
                            </div>
                            <div>
                                <span class="text-muted">
                                    <span id="selectedCount">0</span> selected
                                </span>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                                        </th>
                                        <th>Name</th>
                                        <th>Code</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="recordsTableBody">
                                    <!-- Records will be populated here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reactivation Forms -->
<form id="reactivateForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="reactivate">
    <input type="hidden" name="table" id="reactivateTable">
    <input type="hidden" name="id" id="reactivateId">
</form>

<form id="bulkReactivateForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="bulk_reactivate">
    <input type="hidden" name="table" id="bulkReactivateTable">
    <input type="hidden" name="ids" id="bulkReactivateIds">
</form>

<style>
.data-type-card {
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.data-type-card.clickable:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    border-color: #007bff;
}

.data-type-card.clickable:active {
    transform: translateY(-2px);
}

.data-type-card:not(.clickable) {
    opacity: 0.6;
}

#detailsSection {
    animation: slideDown 0.3s ease;
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

<script>
// Store inactive data from PHP
const inactiveData = <?= json_encode($inactive_data) ?>;
let currentTable = null;

// Card click handler
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.data-type-card.clickable');
    cards.forEach(card => {
        card.addEventListener('click', function() {
            const table = this.dataset.table;
            const count = parseInt(this.dataset.count);
            
            if (count > 0) {
                showDetails(table);
            }
        });
    });
});

function showDetails(tableName) {
    currentTable = tableName;
    const data = inactiveData[tableName];
    
    if (!data || data.records.length === 0) {
        return;
    }
    
    // Update title
    document.getElementById('detailsTitle').textContent = `${data.info.name} - Inactive Records`;
    
    // Populate table
    const tbody = document.getElementById('recordsTableBody');
    tbody.innerHTML = '';
    
    data.records.forEach(record => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <input type="checkbox" class="record-checkbox" value="${record.id}" onchange="updateSelection()">
            </td>
            <td>${escapeHtml(record.name)}</td>
            <td>${record.code ? `<code>${escapeHtml(record.code)}</code>` : '<span class="text-muted">-</span>'}</td>
            <td>
                <button class="btn btn-success btn-sm" onclick="reactivateRecord('${tableName}', ${record.id}, '${escapeHtml(record.name)}')">
                    <i class="fas fa-undo me-1"></i>Reactivate
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Show details section
    document.getElementById('detailsSection').style.display = 'block';
    
    // Reset selection
    deselectAll();
    
    // Scroll to details section
    document.getElementById('detailsSection').scrollIntoView({ behavior: 'smooth' });
}

function hideDetails() {
    document.getElementById('detailsSection').style.display = 'none';
    currentTable = null;
}

function updateSelection() {
    const checkboxes = document.querySelectorAll('.record-checkbox');
    const selectedCheckboxes = document.querySelectorAll('.record-checkbox:checked');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const bulkReactivateBtn = document.getElementById('bulkReactivateBtn');
    const selectedCount = document.getElementById('selectedCount');
    
    // Update count
    selectedCount.textContent = selectedCheckboxes.length;
    
    // Enable/disable bulk reactivate button
    bulkReactivateBtn.disabled = selectedCheckboxes.length === 0;
    
    // Update select all checkbox state
    if (selectedCheckboxes.length === 0) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = false;
    } else if (selectedCheckboxes.length === checkboxes.length) {
        selectAllCheckbox.indeterminate = false;
        selectAllCheckbox.checked = true;
    } else {
        selectAllCheckbox.indeterminate = true;
    }
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.record-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateSelection();
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.record-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    updateSelection();
}

function deselectAll() {
    const checkboxes = document.querySelectorAll('.record-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelection();
}

async function bulkReactivate() {
    const selectedCheckboxes = document.querySelectorAll('.record-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        await customAlert('Please select at least one record to reactivate.');
        return;
    }
    
    const ids = Array.from(selectedCheckboxes).map(cb => cb.value);
    const data = inactiveData[currentTable];
    
    const confirmed = await customSuccess(
        `Are you sure you want to reactivate ${ids.length} selected ${data.info.name.toLowerCase()}?<br><br>This will make all selected records active and visible in the ${data.info.name.toLowerCase()} management section.`,
        {
            title: 'Bulk Reactivate Records',
            confirmText: 'Reactivate All',
            cancelText: 'Cancel',
            confirmButtonClass: 'success'
        }
    );
    
    if (confirmed) {
        // Submit bulk reactivate form
        document.getElementById('bulkReactivateTable').value = currentTable;
        document.getElementById('bulkReactivateIds').value = JSON.stringify(ids);
        document.getElementById('bulkReactivateForm').submit();
    }
}

async function reactivateRecord(tableName, id, recordName) {
    const confirmed = await customSuccess(
        `Are you sure you want to reactivate "${recordName}"?<br><br>This will make the record active and visible in the ${tableName} management section.`,
        {
            title: 'Reactivate Record',
            confirmText: 'Reactivate',
            cancelText: 'Cancel',
            confirmButtonClass: 'success'
        }
    );
    
    if (confirmed) {
        // Submit the form
        document.getElementById('reactivateTable').value = tableName;
        document.getElementById('reactivateId').value = id;
        document.getElementById('reactivateForm').submit();
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include 'includes/footer.php'; ?>
