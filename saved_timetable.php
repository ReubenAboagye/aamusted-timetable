<?php
// Saved Timetable Page - Display saved timetables from the database

$pageTitle = 'Saved Timetable';
include 'connect.php';
// Ensure flash helper available
if (file_exists(__DIR__ . '/includes/flash.php')) include_once __DIR__ . '/includes/flash.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// No filter parameters on this page

// Build the main query to fetch saved timetables
// timetable no longer stores session_id; classes reference streams via c.stream_id
$where_conditions = ["c.stream_id IS NOT NULL"];
$params = [];
$param_types = "";

// No active filters: keep join_programs empty
$join_programs = '';

$where_clause = implode(" AND ", $where_conditions);

// Aggregate timetables by stream (classes.stream_id -> streams.id)
// Detect whether timetable stores lecturer_course_id (newer schema) or lecturer_id (older schema)
// Detect whether timetable stores lecturer_course_id (newer schema) or lecturer_id (older schema)
$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'lecturer_course_id'");
$has_lecturer_course = ($col && $col->num_rows > 0);
if ($col) $col->close();

// Detect whether timetable stores class_course_id (newer schema) or class_id/course_id (older schema)
$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
$has_class_course = ($col && $col->num_rows > 0);
if ($col) $col->close();

$lecturer_join = $has_lecturer_course
    ? "\n    JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id\n    JOIN lecturers l ON lc.lecturer_id = l.id"
    : "\n    JOIN lecturers l ON t.lecturer_id = l.id";

$class_join = $has_class_course
    ? "\n    JOIN class_courses cc ON t.class_course_id = cc.id\n    JOIN classes c ON cc.class_id = c.id\n    JOIN courses co ON cc.course_id = co.id"
    : "\n    JOIN classes c ON t.class_id = c.id\n    JOIN courses co ON t.course_id = co.id";

$main_query = "
    SELECT DISTINCT
        c.stream_id AS stream_id,
        s.name AS stream_name,
        s.code AS stream_code,
        COALESCE(t.academic_year, '') as academic_year,
        t.semester as semester,
        COALESCE(t.version, 'regular') as version,
        MAX(t.created_at) as last_updated,
        COUNT(t.id) as entry_count
    FROM timetable t
    " . $class_join . "
    JOIN streams s ON c.stream_id = s.id
    " . $lecturer_join . "
    JOIN rooms r ON t.room_id = r.id" . $join_programs . "
    WHERE $where_clause
    GROUP BY c.stream_id, s.name, s.code, t.academic_year, t.semester, t.version
    ORDER BY last_updated DESC
    ";

$stmt = $conn->prepare($main_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$timetables_result = $stmt->get_result();
$stmt->close();

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'delete') {
        $stream_id = isset($_POST['stream_id']) ? intval($_POST['stream_id']) : 0;
        $version = isset($_POST['version']) ? $_POST['version'] : '';
        $semester = isset($_POST['semester']) ? $_POST['semester'] : '';

        if ($stream_id > 0 && $version && $semester) {
            // Delete timetable entries for the specific version
            $delete_stmt = $conn->prepare(
                "DELETE t FROM timetable t
                 JOIN class_courses cc ON t.class_course_id = cc.id
                 JOIN classes c ON cc.class_id = c.id
                 WHERE c.stream_id = ? AND t.version = ? AND t.semester = ?"
            );
            $delete_stmt->bind_param('iss', $stream_id, $version, $semester);

            if ($delete_stmt->execute()) {
                $delete_stmt->close();
                $location = 'saved_timetable.php';
                redirect_with_flash($location, 'success', "Timetable version '{$version}' for the selected stream has been deleted successfully.");
            } else {
                $error_message = "Error deleting timetable: " . $conn->error;
            }
            $delete_stmt->close();
        }
    }
}

// update_session handling removed — sessions are not edited here

// Get success/error messages from session or URL
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';
?>

<style>
/* Enhanced Saved Timetable Page Styling */
.timetable-card {
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-md);
    transition: all 0.2s ease;
}

.timetable-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-1px);
}

.timetable-header {
    background: linear-gradient(135deg, var(--primary-color), var(--hover-color));
    color: white;
    padding: var(--spacing-md) var(--spacing-lg);
    border-radius: var(--radius-md) var(--radius-md) 0 0;
}

.timetable-body {
    padding: var(--spacing-lg);
}

.timetable-actions {
    display: flex;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
}

.timetable-meta {
    display: flex;
    gap: var(--spacing-md);
    margin-bottom: var(--spacing-md);
    flex-wrap: wrap;
}

.timetable-meta .badge {
    font-size: var(--font-size-xs);
    padding: var(--spacing-xs) var(--spacing-sm);
}

/* Mobile optimizations */
@media (max-width: 768px) {
    .timetable-actions {
        flex-direction: column;
    }
    
    .timetable-actions .btn {
        width: 100%;
        margin-bottom: var(--spacing-xs);
    }
    
    .timetable-meta {
        flex-direction: column;
        gap: var(--spacing-xs);
    }
    
    .table-header .d-flex {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--spacing-sm);
    }
}

@media (max-width: 576px) {
    .timetable-header {
        padding: var(--spacing-sm) var(--spacing-md);
    }
    
    .timetable-body {
        padding: var(--spacing-md);
    }
    
    .timetable-header h5 {
        font-size: var(--font-size-base);
    }
}
</style>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-save me-2"></i>Saved Timetables</h4>
            <div class="d-flex gap-2">
                <a class="btn btn-primary" href="generate_timetable.php">
                    <i class="fas fa-cogs me-2"></i>Generate New Timetable
                </a>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters removed -->

        <!-- Saved Timetables List -->
        <div class="card m-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Saved Timetables 
                    <?php if ($timetables_result && $timetables_result->num_rows > 0): ?>
                        <span class="badge bg-primary ms-2"><?php echo $timetables_result->num_rows; ?></span>
                    <?php endif; ?>
                </h6>
            </div>
            <div class="card-body">
                <?php if ($timetables_result && $timetables_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Session</th>
                                    <th>Version</th>
                                    <th>Academic Year</th>
                                    <th>Semester</th>
                                    <th>Entries</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($timetable = $timetables_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($timetable['stream_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning"><?php echo htmlspecialchars($timetable['version']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($timetable['academic_year'] ?? '—'); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            // Display semester as 1 or 2 based on database value
                                            $semester_display = '';
                                            if ($timetable['semester'] === 'first') {
                                                $semester_display = '1';
                                            } elseif ($timetable['semester'] === 'second') {
                                                $semester_display = '2';
                                            } else {
                                                $semester_display = ucfirst($timetable['semester']);
                                            }
                                            ?>
                                            <span class="badge bg-secondary"><?php echo $semester_display; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $timetable['entry_count']; ?></span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($timetable['last_updated'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <div class="btn-group" role="group" aria-label="Actions">
                                                <a href="generate_timetable.php?edit_stream_id=<?php echo $timetable['stream_id']; ?>&version=<?php echo urlencode($timetable['version']); ?>&semester=<?php echo urlencode($timetable['semester']); ?>" class="btn btn-sm btn-outline-primary" title="View Timetable">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="manual_timetable_editor.php?stream_id=<?php echo $timetable['stream_id']; ?>&version=<?php echo urlencode($timetable['version']); ?>&semester=<?php echo urlencode($timetable['semester']); ?>" class="btn btn-sm btn-outline-success" title="Manual Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                    <!-- Edit Stream button removed -->
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete Timetable" onclick="confirmDelete(<?php echo $timetable['stream_id']; ?>, '<?php echo htmlspecialchars($timetable['stream_name']); ?>', '<?php echo htmlspecialchars($timetable['version']); ?>', '<?php echo htmlspecialchars($timetable['semester']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Saved Timetables Found</h5>
                                                 <p class="text-muted">
                                No saved timetables have been generated yet. 
                                <a href="generate_timetable.php" class="text-decoration-none">Generate your first timetable</a>.
                         </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>


    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the timetable for <strong id="sessionName"></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone. All timetable entries for this session will be permanently removed.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="saved_timetable.php" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="stream_id" id="deleteStreamId">
                    <input type="hidden" name="version" id="deleteVersion">
                    <input type="hidden" name="semester" id="deleteSemester">
                    <button type="submit" class="btn btn-danger">Delete Timetable Version</button>
                </form>
            </div>
        </div>
    </div>

<!-- Edit Session modal removed -->

<script>
function confirmDelete(streamId, streamName, version, semester) {
    var streamInput = document.getElementById('deleteStreamId');
    var versionInput = document.getElementById('deleteVersion');
    var semesterInput = document.getElementById('deleteSemester');
    
    if (streamInput) streamInput.value = streamId;
    if (versionInput) versionInput.value = version;
    if (semesterInput) semesterInput.value = semester;
    
    document.getElementById('sessionName').textContent = streamName + ' (' + version + ')';
    var el = document.getElementById('deleteModal');
    if (!el) return console.error('deleteModal element missing');
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return console.error('Bootstrap Modal not available');
    bootstrap.Modal.getOrCreateInstance(el).show();
}

// Filter and edit-session JS removed
</script>

<?php include 'includes/footer.php'; ?>
