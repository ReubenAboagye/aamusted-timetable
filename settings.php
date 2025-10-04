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

// Get inactive records from all tables (ALL stream-specific)
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

// Lecturers (stream-specific - only lecturers assigned to courses in this stream)
$result = $conn->query("
    SELECT DISTINCT l.id, l.name, NULL as code, 'lecturers' as table_name 
    FROM lecturers l 
    INNER JOIN lecturer_courses lc ON l.id = lc.lecturer_id 
    INNER JOIN courses c ON lc.course_id = c.id 
    WHERE l.is_active = 0 AND c.stream_id = $current_stream_id 
    ORDER BY l.name
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Departments (stream-specific - only departments with programs/courses in this stream)
$result = $conn->query("
    SELECT DISTINCT d.id, d.name, NULL as code, 'departments' as table_name 
    FROM departments d 
    WHERE d.is_active = 0 
    AND (d.id IN (SELECT DISTINCT department_id FROM programs WHERE stream_id = $current_stream_id) 
         OR d.id IN (SELECT DISTINCT department_id FROM courses WHERE stream_id = $current_stream_id))
    ORDER BY d.name
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Rooms (stream-specific - only rooms used by courses in this stream)
$result = $conn->query("
    SELECT DISTINCT r.id, r.name, NULL as code, 'rooms' as table_name 
    FROM rooms r 
    INNER JOIN course_room_types cr ON r.room_type = rt.name 
    INNER JOIN room_types rt ON cr.room_type_id = rt.id 
    INNER JOIN courses c ON cr.course_id = c.id 
    WHERE r.is_active = 0 AND c.stream_id = $current_stream_id 
    ORDER BY r.name
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $inactive_records[] = $row;
    }
}

// Room Types (stream-specific - only room types used by courses in this stream)
$result = $conn->query("
    SELECT DISTINCT rt.id, rt.name, NULL as code, 'room_types' as table_name 
    FROM room_types rt 
    INNER JOIN course_room_types cr ON rt.id = cr.room_type_id 
    INNER JOIN courses c ON cr.course_id = c.id 
    WHERE rt.is_active = 0 AND c.stream_id = $current_stream_id 
    ORDER BY rt.name
");
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

// Streams (only show the current stream if it's inactive)
$result = $conn->query("SELECT id, name, code, 'streams' as table_name FROM streams WHERE is_active = 0 AND id = $current_stream_id ORDER BY name");
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
                        <p class="text-muted mb-0 mt-2">Reactivate records that were previously set to inactive. <strong>All records shown are linked to Stream ID <?= $current_stream_id ?></strong> - only inactive records associated with this specific stream are displayed.</p>
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
                                            <tr>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?= ucfirst($record['table_name']) ?> (Stream <?= $current_stream_id ?>)
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
                                    Total inactive records linked to Stream ID <?= $current_stream_id ?>: <?= count($inactive_records) ?>
                                    <br>
                                    <i class="fas fa-stream me-1"></i>
                                    All records shown are associated with this stream through direct links or relationships
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
