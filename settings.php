<?php
$pageTitle = 'Settings - Manage Inactive Records';
include 'includes/header.php';
include 'includes/sidebar.php';

// Include custom dialog system
echo '<link rel="stylesheet" href="css/custom-dialogs.css">';
echo '<script src="js/custom-dialogs.js"></script>';

// Database connection
include_once 'connect.php';

// Get inactive records grouped by table
$inactive_data = [];
$total_inactive = 0; // Initialize total counter

$tables = [
    'programs' => ['name' => 'Programs', 'icon' => 'fas fa-graduation-cap', 'color' => 'maroon'],
    'courses' => ['name' => 'Courses', 'icon' => 'fas fa-book', 'color' => 'blue'],
    'lecturers' => ['name' => 'Lecturers', 'icon' => 'fas fa-chalkboard-teacher', 'color' => 'green'],
    'departments' => ['name' => 'Departments', 'icon' => 'fas fa-building', 'color' => 'gold'],
    'rooms' => ['name' => 'Rooms', 'icon' => 'fas fa-door-open', 'color' => 'maroon'],
    'room_types' => ['name' => 'Room Types', 'icon' => 'fas fa-tags', 'color' => 'blue'],
    'classes' => ['name' => 'Classes', 'icon' => 'fas fa-users', 'color' => 'green'],
    'streams' => ['name' => 'Streams', 'icon' => 'fas fa-stream', 'color' => 'gold']
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
    
    $record_count = count($records);
    $total_inactive += $record_count; // Add to total counter
    
    $inactive_data[$table_name] = [
        'info' => $table_info,
        'records' => $records,
        'count' => $record_count
    ];
}

$conn->close();
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-header mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="page-title mb-1">
                                <i class="fas fa-cog me-2"></i>Settings
                            </h1>
                            <p class="page-subtitle mb-0">Manage inactive records across all modules</p>
                        </div>
                        <div class="header-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?= $total_inactive ?></span>
                                <span class="stat-label">Inactive</span>
                            </div>
                            <button class="btn btn-outline-light btn-sm ms-3" onclick="refreshData()" title="Refresh Data">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- AJAX Messages Container -->
                <div id="ajaxMessages" class="ajax-messages"></div>

                <!-- Data Type Cards -->
                <div class="modules-grid mb-4">
                    <?php 
                    $total_inactive = 0;
                    foreach ($inactive_data as $table_name => $data): 
                        $total_inactive += $data['count'];
                    ?>
                        <div class="module-card <?= $data['count'] > 0 ? 'has-inactive' : 'all-active' ?>" 
                             data-table="<?= $table_name ?>" 
                             data-count="<?= $data['count'] ?>">
                            <div class="module-icon">
                                <i class="<?= $data['info']['icon'] ?>"></i>
                            </div>
                            <div class="module-content">
                                <h6 class="module-name"><?= $data['info']['name'] ?></h6>
                                <div class="module-status">
                                    <?php if ($data['count'] > 0): ?>
                                        <span class="status-badge inactive"><?= $data['count'] ?> inactive</span>
                                        <small class="status-text">Click to manage</small>
                                    <?php else: ?>
                                        <span class="status-badge active">All active</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Summary Section -->
                <?php if ($total_inactive > 0): ?>
                <div class="summary-section mb-4">
                    <div class="summary-card">
                        <div class="summary-content">
                            <div class="summary-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="summary-text">
                                <h6 class="summary-title">Action Required</h6>
                                <p class="summary-description">You have <strong><?= $total_inactive ?></strong> inactive records that need attention</p>
                            </div>
                        </div>
                        <div class="summary-actions">
                            <button class="btn btn-primary btn-sm" onclick="showAllInactive()">
                                <i class="fas fa-list me-1"></i>View All
                            </button>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="summary-section mb-4">
                    <div class="summary-card success">
                        <div class="summary-content">
                            <div class="summary-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="summary-text">
                                <h6 class="summary-title">All Systems Active</h6>
                                <p class="summary-description">All records are currently active and properly configured</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Details Section (Hidden by default) -->
                <div id="detailsSection" class="details-section" style="display: none;">
                    <div class="details-header">
                        <div class="details-title">
                            <i class="fas fa-list me-2"></i>
                            <span id="detailsTitle">Record Details</span>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="hideDetails()">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                    </div>
                    <div class="details-content">
                        <div class="details-toolbar">
                            <div class="toolbar-left">
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
                            <div class="toolbar-right">
                                <span class="selection-count">
                                    <span id="selectedCount">0</span> selected
                                </span>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                                        </th>
                                        <th>Name</th>
                                        <th>Code</th>
                                        <th width="120">Actions</th>
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

<style>
/* Settings Page Redesign - Professional & Compact */
:root {
    --primary-maroon: #800020;
    --hover-maroon: #600010;
    --brand-blue: #0d6efd;
    --brand-gold: #FFD700;
    --brand-green: #198754;
    --text-muted: #6c757d;
    --border-light: #e9ecef;
    --bg-light: #f8f9fa;
}

/* Page Header */
.page-header {
    background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--hover-maroon) 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 12px rgba(128, 0, 32, 0.15);
}

.page-title {
    font-size: 1.75rem;
    font-weight: 600;
    margin: 0;
    color: white;
}

.page-subtitle {
    font-size: 0.95rem;
    opacity: 0.9;
    margin: 0;
}

.header-stats {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-stats .btn {
    border-radius: 6px;
    transition: all 0.2s ease;
}

.header-stats .btn:hover {
    transform: scale(1.05);
}

.stat-item {
    text-align: center;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.75rem 1rem;
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.stat-number {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
}

.stat-label {
    font-size: 0.8rem;
    opacity: 0.9;
}

/* Modules Grid */
.modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.module-card {
    background: white;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    transition: all 0.3s ease;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.module-card.has-inactive:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    border-color: var(--brand-blue);
}

.module-card.all-active {
    opacity: 0.7;
    cursor: default;
}

.module-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.module-card.has-inactive .module-icon {
    background: var(--primary-maroon);
}

.module-card.all-active .module-icon {
    background: var(--brand-green);
}

.module-content {
    flex: 1;
}

.module-name {
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: #333;
}

.module-status {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-weight: 500;
}

.status-badge.inactive {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-badge.active {
    background: #d1edff;
    color: #0c5460;
    border: 1px solid #b8daff;
}

.status-text {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin: 0;
}

/* Summary Section */
.summary-section {
    margin-bottom: 2rem;
}

.summary-card {
    background: white;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.summary-card.success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border-color: var(--brand-green);
}

.summary-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.summary-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.summary-card:not(.success) .summary-icon {
    background: var(--brand-gold);
}

.summary-card.success .summary-icon {
    background: var(--brand-green);
}

.summary-text {
    flex: 1;
}

.summary-title {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: #333;
}

.summary-description {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin: 0;
}

.summary-actions {
    display: flex;
    gap: 0.5rem;
}

/* Details Section */
.details-section {
    background: white;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    animation: slideDown 0.3s ease;
}

.details-header {
    background: var(--primary-maroon);
    color: white;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.details-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0;
}

.details-content {
    padding: 1.25rem;
}

.details-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border-light);
}

.toolbar-left {
    display: flex;
    gap: 0.5rem;
}

.toolbar-right {
    display: flex;
    align-items: center;
}

.selection-count {
    font-size: 0.9rem;
    color: var(--text-muted);
    background: var(--bg-light);
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    border: 1px solid var(--border-light);
}

.table-container {
    overflow-x: auto;
    border-radius: 8px;
    border: 1px solid var(--border-light);
}

.records-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.records-table thead {
    background: var(--primary-maroon);
    color: white;
}

.records-table th {
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.9rem;
    border: none;
}

.records-table td {
    padding: 0.75rem;
    border-bottom: 1px solid var(--border-light);
    font-size: 0.9rem;
}

.records-table tbody tr:hover {
    background: var(--bg-light);
}

.records-table tbody tr:last-child td {
    border-bottom: none;
}

/* Button Styling */
.btn-primary {
    background-color: var(--brand-blue) !important;
    border-color: var(--brand-blue) !important;
}

.btn-primary:hover {
    background-color: #0b5ed7 !important;
    border-color: #0b5ed7 !important;
}

.btn-success {
    background-color: var(--brand-green) !important;
    border-color: var(--brand-green) !important;
}

.btn-success:hover {
    background-color: #157347 !important;
    border-color: #157347 !important;
}

/* Animations */
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

/* AJAX Messages */
.ajax-messages {
    margin-bottom: 1rem;
}

.ajax-message {
    padding: 0.75rem 1rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    animation: slideInDown 0.3s ease;
}

.ajax-message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.ajax-message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.ajax-message .message-content {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ajax-message .close-btn {
    background: none;
    border: none;
    font-size: 1.2rem;
    cursor: pointer;
    opacity: 0.7;
    transition: opacity 0.2s ease;
}

.ajax-message .close-btn:hover {
    opacity: 1;
}

/* Loading States */
.loading {
    opacity: 0.6;
    pointer-events: none;
    position: relative;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid var(--primary-maroon);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.btn.loading i {
    animation: spin 1s linear infinite;
}

.btn.loading {
    position: relative;
}

.btn.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 16px;
    height: 16px;
    margin: -8px 0 0 -8px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .modules-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .summary-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .details-toolbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .toolbar-left {
        flex-wrap: wrap;
    }
}
</style>

<script>
// Store inactive data from PHP
const inactiveData = <?= json_encode($inactive_data) ?>;
let currentTable = null;

// AJAX Helper Functions
function showAjaxMessage(message, type = 'success') {
    const messagesContainer = document.getElementById('ajaxMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `ajax-message ${type}`;
    messageDiv.innerHTML = `
        <div class="message-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="close-btn" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    messagesContainer.appendChild(messageDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (messageDiv.parentElement) {
            messageDiv.remove();
        }
    }, 5000);
}

function setLoading(element, loading = true) {
    if (loading) {
        element.classList.add('loading');
        element.disabled = true;
    } else {
        element.classList.remove('loading');
        element.disabled = false;
    }
}

async function makeAjaxRequest(data) {
    try {
        const response = await fetch('ajax_reactivate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('AJAX Error:', error);
        return { success: false, message: 'Network error occurred' };
    }
}

async function refreshData(event = null) {
    const refreshBtn = event ? event.target.closest('button') : document.querySelector('button[onclick="refreshData()"]');
    if (refreshBtn) setLoading(refreshBtn, true);
    
    try {
        const response = await fetch('ajax_get_data.php');
        if (response.ok) {
            const result = await response.json();
            if (result.success) {
                // Update the data
                Object.assign(inactiveData, result.data);
                
                // Update UI with error handling
                try {
                    updateModuleCounts();
                } catch (error) {
                    console.error('Error updating module counts:', error);
                    showAjaxMessage('Data refreshed but UI update failed', 'error');
                    return;
                }
                
                // Show success message
                showAjaxMessage('Data refreshed successfully!', 'success');
                
                // If details are open, refresh the current table
                if (currentTable && inactiveData[currentTable]) {
                    try {
                        showDetails(currentTable);
                    } catch (error) {
                        console.error('Error refreshing details:', error);
                    }
                }
            } else {
                showAjaxMessage('Failed to refresh data: ' + (result.message || 'Unknown error'), 'error');
            }
        } else {
            showAjaxMessage('Network error while refreshing data (HTTP ' + response.status + ')', 'error');
        }
    } catch (error) {
        console.error('Error refreshing data:', error);
        showAjaxMessage('Error refreshing data: ' + error.message, 'error');
    } finally {
        if (refreshBtn) setLoading(refreshBtn, false);
    }
}

// Auto-refresh data when page loads and periodically
document.addEventListener('DOMContentLoaded', function() {
    // Initial data load
    refreshData();
    
    // Auto-refresh every 30 seconds to catch changes from other modules
    setInterval(() => {
        refreshData();
    }, 30000);
    
    // Listen for focus events to refresh when user returns to tab
    window.addEventListener('focus', () => {
        refreshData();
    });
});

// Card click handler
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.module-card.has-inactive');
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
                <button class="btn btn-success btn-sm reactivate-btn" onclick="reactivateRecord('${tableName}', ${record.id}, '${escapeHtml(record.name)}', this)">
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
        const bulkBtn = document.getElementById('bulkReactivateBtn');
        setLoading(bulkBtn, true);
        
        const result = await makeAjaxRequest({
            action: 'bulk_reactivate',
            table: currentTable,
            ids: ids
        });
        
        setLoading(bulkBtn, false);
        
        if (result.success) {
            showAjaxMessage(result.message, 'success');
            // Remove reactivated records from the table with animation
            selectedCheckboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                row.style.transition = 'opacity 0.3s ease';
                row.style.opacity = '0';
            });
            
            setTimeout(() => {
                // Update the data structure - remove the reactivated records
                const reactivatedIds = ids.map(id => parseInt(id));
                inactiveData[currentTable].records = inactiveData[currentTable].records.filter(record => !reactivatedIds.includes(record.id));
                inactiveData[currentTable].count = inactiveData[currentTable].records.length;
                
                selectedCheckboxes.forEach(checkbox => {
                    const row = checkbox.closest('tr');
                    row.remove();
                });
                
                // Update counts
                updateModuleCounts();
                updateSelection();
                
                // Hide details if no more records
                const remainingCheckboxes = document.querySelectorAll('.record-checkbox');
                if (remainingCheckboxes.length === 0) {
                    hideDetails();
                }
            }, 300);
        } else {
            showAjaxMessage(result.message, 'error');
        }
    }
}

async function reactivateRecord(tableName, id, recordName, buttonElement) {
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
        const button = buttonElement || event.target.closest('.reactivate-btn');
        setLoading(button, true);
        
        const result = await makeAjaxRequest({
            action: 'reactivate',
            table: tableName,
            id: id
        });
        
        setLoading(button, false);
        
        if (result.success) {
            showAjaxMessage(result.message, 'success');
            // Remove the reactivated record from the table with animation
            const row = button.closest('tr');
            row.style.transition = 'opacity 0.3s ease';
            row.style.opacity = '0';
            setTimeout(() => {
                row.remove();
                
                // Update the data structure - remove the reactivated record
                const recordId = parseInt(id);
                inactiveData[tableName].records = inactiveData[tableName].records.filter(record => record.id !== recordId);
                inactiveData[tableName].count = inactiveData[tableName].records.length;
                
                // Update counts
                updateModuleCounts();
                updateSelection();
                
                // Hide details if no more records
                const remainingCheckboxes = document.querySelectorAll('.record-checkbox');
                if (remainingCheckboxes.length === 0) {
                    hideDetails();
                }
            }, 300);
        } else {
            showAjaxMessage(result.message, 'error');
        }
    }
}

function updateModuleCounts() {
    // Update the module cards with counts from the data structure (not DOM elements)
    Object.keys(inactiveData).forEach(tableName => {
        const data = inactiveData[tableName];
        const newCount = data.count; // Use the actual count from data, not DOM elements
        
        // Update the UI - check if elements exist first
        const moduleCard = document.querySelector(`[data-table="${tableName}"]`);
        if (moduleCard) {
            const statusBadge = moduleCard.querySelector('.status-badge');
            const statusText = moduleCard.querySelector('.status-text');
            
            if (statusBadge) {
                if (newCount > 0) {
                    statusBadge.textContent = `${newCount} inactive`;
                    statusBadge.className = 'status-badge inactive';
                    if (statusText) statusText.textContent = 'Click to manage';
                    moduleCard.className = 'module-card has-inactive';
                    moduleCard.dataset.count = newCount;
                } else {
                    statusBadge.textContent = 'All active';
                    statusBadge.className = 'status-badge active';
                    if (statusText) statusText.textContent = '';
                    moduleCard.className = 'module-card all-active';
                    moduleCard.dataset.count = 0;
                }
            }
        }
    });
    
    // Update header stats - check if element exists
    const totalInactive = Object.values(inactiveData).reduce((sum, data) => sum + data.count, 0);
    const statNumber = document.querySelector('.stat-number');
    if (statNumber) {
        statNumber.textContent = totalInactive;
    }
    
    // Update summary section
    updateSummarySection(totalInactive);
}

function updateSummarySection(totalInactive) {
    const summarySection = document.querySelector('.summary-section');
    
    if (!summarySection) {
        console.warn('Summary section not found');
        return;
    }
    
    if (totalInactive > 0) {
        summarySection.innerHTML = `
            <div class="summary-card">
                <div class="summary-content">
                    <div class="summary-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="summary-text">
                        <h6 class="summary-title">Action Required</h6>
                        <p class="summary-description">You have <strong>${totalInactive}</strong> inactive records that need attention</p>
                    </div>
                </div>
                <div class="summary-actions">
                    <button class="btn btn-primary btn-sm" onclick="showAllInactive()">
                        <i class="fas fa-list me-1"></i>View All
                    </button>
                </div>
            </div>
        `;
    } else {
        summarySection.innerHTML = `
            <div class="summary-card success">
                <div class="summary-content">
                    <div class="summary-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="summary-text">
                        <h6 class="summary-title">All Systems Active</h6>
                        <p class="summary-description">All records are currently active and properly configured</p>
                    </div>
                </div>
            </div>
        `;
    }
}

function showAllInactive() {
    // Find the first module with inactive records
    const firstInactiveModule = Object.keys(inactiveData).find(table => inactiveData[table].count > 0);
    if (firstInactiveModule) {
        showDetails(firstInactiveModule);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Debug function to check DOM elements
function debugDOM() {
    console.log('=== DOM Debug Info ===');
    console.log('Module cards found:', document.querySelectorAll('.module-card').length);
    console.log('Summary section found:', !!document.querySelector('.summary-section'));
    console.log('Stat number found:', !!document.querySelector('.stat-number'));
    
    Object.keys(inactiveData).forEach(tableName => {
        const card = document.querySelector(`[data-table="${tableName}"]`);
        const badge = card ? card.querySelector('.status-badge') : null;
        const text = card ? card.querySelector('.status-text') : null;
        console.log(`${tableName}: card=${!!card}, badge=${!!badge}, text=${!!text}`);
    });
}

// Add debug function to window for console access
window.debugDOM = debugDOM;
</script>

<?php include 'includes/footer.php'; ?>
