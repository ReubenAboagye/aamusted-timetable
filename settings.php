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

// Get current stream ID
$current_stream_id = $_SESSION['current_stream_id'] ?? 1;

// Get inactive records from all tables (stream-specific where applicable)
$inactive_records = [];

// Programs (stream-specific)
$result = $conn->query("SELECT id, name, code, 'programs' as table_name FROM programs WHERE is_active = 0 AND stream_id = $current_stream_id ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Courses (stream-specific)
$result = $conn->query("SELECT id, name, code, 'courses' as table_name FROM courses WHERE is_active = 0 AND stream_id = $current_stream_id ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Lecturers (not stream-specific)
$result = $conn->query("SELECT id, name, NULL as code, 'lecturers' as table_name FROM lecturers WHERE is_active = 0 ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Departments (not stream-specific)
$result = $conn->query("SELECT id, name, NULL as code, 'departments' as table_name FROM departments WHERE is_active = 0 ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Rooms (not stream-specific)
$result = $conn->query("SELECT id, name, NULL as code, 'rooms' as table_name FROM rooms WHERE is_active = 0 ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Room Types (not stream-specific)
$result = $conn->query("SELECT id, name, NULL as code, 'room_types' as table_name FROM room_types WHERE is_active = 0 ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Classes (stream-specific)
$result = $conn->query("SELECT id, name, NULL as code, 'classes' as table_name FROM classes WHERE is_active = 0 AND stream_id = $current_stream_id ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Streams (not stream-specific - show all inactive streams)
$result = $conn->query("SELECT id, name, code, 'streams' as table_name FROM streams WHERE is_active = 0 ORDER BY name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Sort by table name, then by name
usort($inactive_records, function($a, $b) {
    if ($a['table_name'] === $b['table_name']) {
        return strcmp($a['name'], $b['name']);
    }
    return strcmp($a['table_name'], $b['table_name']);
});

$conn->close();
?>

<div class="main-content" id="mainContent">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-cog me-2"></i>Settings - Manage Inactive Records</h2>
                    <div class="text-muted">
                        <small>Stream ID: <?= $current_stream_id ?></small>
                    </div>
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

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-eye-slash me-2"></i>Inactive Records Management
                        </h5>
                        <p class="text-muted mb-0 mt-2">Reactivate records that were previously set to inactive. Stream-specific records (Programs, Courses, Classes) show only for the current stream (ID: <?= $current_stream_id ?>). Global records (Lecturers, Departments, Rooms, Room Types, Streams) show across all streams.</p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($inactive_records)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                <h4 class="mt-3 text-success">All Records Are Active</h4>
                                <p class="text-muted">There are no inactive records to manage for Stream ID <?= $current_stream_id ?> at this time.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Type</th>
                                            <th>Name</th>
                                            <th>Code</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inactive_records as $record): ?>
                                            <?php 
                                            $is_stream_specific = in_array($record['table_name'], ['programs', 'courses', 'classes']);
                                            $badge_class = $is_stream_specific ? 'bg-primary' : 'bg-secondary';
                                            $badge_text = $is_stream_specific ? ucfirst($record['table_name']) . ' (Stream)' : ucfirst($record['table_name']) . ' (Global)';
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="badge <?= $badge_class ?>">
                                                        <?= $badge_text ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($record['name']) ?></td>
                                                <td>
                                                    <?php if ($record['code']): ?>
                                                        <code><?= htmlspecialchars($record['code']) ?></code>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-success btn-sm" 
                                                            onclick="reactivateRecord('<?= $record['table_name'] ?>', <?= $record['id'] ?>, '<?= htmlspecialchars($record['name']) ?>')">
                                                        <i class="fas fa-undo me-1"></i>Reactivate
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Total inactive records for Stream ID <?= $current_stream_id ?>: <?= count($inactive_records) ?>
                                    <br>
                                    <i class="fas fa-stream me-1"></i>
                                    Stream-specific: Programs, Courses, Classes | 
                                    <i class="fas fa-globe me-1"></i>
                                    Global: Lecturers, Departments, Rooms, Room Types, Streams
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reactivation Form -->
<form id="reactivateForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="reactivate">
    <input type="hidden" name="table" id="reactivateTable">
    <input type="hidden" name="id" id="reactivateId">
</form>

<script>
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
</script>

<?php include 'includes/footer.php'; ?>
