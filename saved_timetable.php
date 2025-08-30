<?php
// Saved Timetable Page - Display saved timetables from the database

$pageTitle = 'Saved Timetable';
include 'connect.php';
// Ensure flash helper available
if (file_exists(__DIR__ . '/includes/flash.php')) include_once __DIR__ . '/includes/flash.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Get filter parameters
$selected_stream = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$selected_department = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;

// Fetch available streams for filter
$streams_sql = "SELECT id, name, code FROM streams WHERE is_active = 1 ORDER BY name";
$streams_result = $conn->query($streams_sql);

// Fetch available departments for filter
$departments_sql = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name";
$departments_result = $conn->query($departments_sql);

// Build the main query to fetch saved timetables
$where_conditions = ["t.session_id IS NOT NULL"];
$params = [];
$param_types = "";

// Stream filtering for saved timetables is optional; keep ability but do not force stream on other tables
$join_programs = '';
if ($selected_stream > 0) {
    $where_conditions[] = "c.stream_id = ?";
    $params[] = $selected_stream;
    $param_types .= "i";
}

// Department filter: classes belong to programs which belong to departments in this schema
if ($selected_department > 0) {
    $where_conditions[] = "p.department_id = ?";
    $params[] = $selected_department;
    $param_types .= "i";
    $join_programs = "\n    JOIN programs p ON c.program_id = p.id";
}

$where_clause = implode(" AND ", $where_conditions);

$main_query = "
    SELECT DISTINCT
        t.session_id,
        s.semester_name,
        s.academic_year,
        s.semester_number,
        COUNT(DISTINCT t.id) as total_entries,
        COUNT(DISTINCT c.id) as total_classes,
        COUNT(DISTINCT co.id) as total_courses,
        COUNT(DISTINCT l.id) as total_lecturers,
        COUNT(DISTINCT r.id) as total_rooms,
        MAX(t.created_at) as last_updated
    FROM timetable t
    JOIN sessions s ON t.session_id = s.id
    JOIN class_courses cc ON t.class_course_id = cc.id
    JOIN classes c ON cc.class_id = c.id
    JOIN courses co ON cc.course_id = co.id
    JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    JOIN lecturers l ON lc.lecturer_id = l.id
    JOIN rooms r ON t.room_id = r.id" . $join_programs . "
    WHERE $where_clause
    GROUP BY t.session_id, s.semester_name, s.academic_year, s.semester_number
    ORDER BY s.academic_year DESC, s.semester_number DESC
";

$stmt = $conn->prepare($main_query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$timetables_result = $stmt->get_result();
$stmt->close();

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    
    if ($session_id > 0) {
        // Delete timetable entries for the session
        $delete_stmt = $conn->prepare("DELETE FROM timetable WHERE session_id = ?");
        $delete_stmt->bind_param('i', $session_id);
        
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            $location = 'saved_timetable.php';
            $params = [];
            if ($selected_stream) $params['stream_id'] = $selected_stream;
            if ($selected_department) $params['department_id'] = $selected_department;
            if (!empty($params)) $location .= '?' . http_build_query($params);
            redirect_with_flash($location, 'success', 'Timetable for the selected session has been deleted successfully.');
        } else {
            $error_message = "Error deleting timetable: " . $conn->error;
        }
        $delete_stmt->close();
    }
}

// Handle edit/update session action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_session') {
    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    $semester_name = isset($_POST['semester_name']) ? $conn->real_escape_string($_POST['semester_name']) : '';
    $academic_year = isset($_POST['academic_year']) ? $conn->real_escape_string($_POST['academic_year']) : '';
    $semester_number = isset($_POST['semester_number']) ? intval($_POST['semester_number']) : 0;

    if ($session_id > 0 && $semester_name !== '' && $academic_year !== '' && $semester_number > 0) {
        $update_stmt = $conn->prepare("UPDATE sessions SET semester_name = ?, academic_year = ?, semester_number = ? WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param('ssii', $semester_name, $academic_year, $semester_number, $session_id);
            if ($update_stmt->execute()) {
                $success_message = 'Session updated successfully.';
            } else {
                $error_message = 'Error updating session: ' . $conn->error;
            }
            $update_stmt->close();
        } else {
            $error_message = 'Prepare failed: ' . $conn->error;
        }
    } else {
        $error_message = 'Invalid session data provided.';
    }

    // Refresh timetables result after update
    header('Location: saved_timetable.php');
    exit();
}

// Get success/error messages from session or URL
$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-save me-2"></i>Saved Timetables</h4>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary" href="generate_timetable.php">
                    <i class="fas fa-cogs me-2"></i>Generate New Timetable
                </a>
                <a class="btn btn-outline-secondary" href="view_timetable.php">
                    <i class="fas fa-eye me-2"></i>View Timetable
                </a>
                <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#editSessionModal">
                    <i class="fas fa-edit me-2"></i>Edit Session
                </button>
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

        <!-- Filters -->
        <div class="card m-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="saved_timetable.php" class="row g-3">
                    <div class="col-md-5">
                        <label for="stream_id" class="form-label">Stream</label>
                        <select name="stream_id" id="stream_id" class="form-select">
                            <option value="">All Streams</option>
                            <?php if ($streams_result && $streams_result->num_rows > 0): ?>
                                <?php while ($stream = $streams_result->fetch_assoc()): ?>
                                    <option value="<?php echo $stream['id']; ?>" 
                                            <?php echo ($selected_stream == $stream['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stream['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="department_id" class="form-label">Department</label>
                        <select name="department_id" id="department_id" class="form-select">
                            <option value="">All Departments</option>
                            <?php if ($departments_result && $departments_result->num_rows > 0): ?>
                                <?php while ($dept = $departments_result->fetch_assoc()): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo ($selected_department == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

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
                                    <th>Academic Year</th>
                                    <th>Semester</th>
                                    <th>Statistics</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($timetable = $timetables_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($timetable['semester_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($timetable['academic_year']); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $semester_names = ['1' => 'First', '2' => 'Second', '3' => 'Summer'];
                                            $semester_name = $semester_names[$timetable['semester_number']] ?? 'Unknown';
                                            ?>
                                            <span class="badge bg-secondary"><?php echo $semester_name; ?></span>
                                        </td>
                                        <td>
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Entries: <strong><?php echo $timetable['total_entries']; ?></strong></small>
                                                    <small class="text-muted d-block">Classes: <strong><?php echo $timetable['total_classes']; ?></strong></small>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted d-block">Courses: <strong><?php echo $timetable['total_courses']; ?></strong></small>
                                                    <small class="text-muted d-block">Lecturers: <strong><?php echo $timetable['total_lecturers']; ?></strong></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($timetable['last_updated'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <div class="btn-group" role="group" aria-label="Actions">
                                                    <a href="view_timetable.php?session_id=<?php echo $timetable['session_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Timetable">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="export_timetable.php?session_id=<?php echo $timetable['session_id']; ?>" class="btn btn-sm btn-outline-success" title="Export" target="_blank">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-info" title="Edit Session" onclick="openEditSessionModal(<?php echo $timetable['session_id']; ?>, '<?php echo addslashes($timetable['semester_name']); ?>', '<?php echo addslashes($timetable['academic_year']); ?>', <?php echo (int)$timetable['semester_number']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete Timetable" onclick="confirmDelete(<?php echo $timetable['session_id']; ?>, '<?php echo htmlspecialchars($timetable['semester_name'] . ' (' . $timetable['academic_year'] . ')'); ?>')">
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
                             <?php if ($selected_stream || $selected_department): ?>
                                 No timetables match your current filters. Try adjusting your search criteria.
                             <?php else: ?>
                                 No timetables have been generated yet. 
                                 <a href="generate_timetable.php" class="text-decoration-none">Generate your first timetable</a>.
                             <?php endif; ?>
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
                    <input type="hidden" name="session_id" id="deleteSessionId">
                    <button type="submit" class="btn btn-danger">Delete Timetable</button>
                </form>
            </div>
        </div>
    </div>

<!-- Edit Session Modal -->
<div class="modal fade" id="editSessionModal" tabindex="-1" aria-labelledby="editSessionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="editSessionModalLabel">Edit Session</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="update_session">
          <div class="mb-3">
            <label for="session_id">Session ID</label>
            <input type="number" class="form-control" id="session_id" name="session_id" required>
          </div>
          <div class="mb-3">
            <label for="semester_name">Semester Name</label>
            <input type="text" class="form-control" id="semester_name" name="semester_name" required>
          </div>
          <div class="mb-3">
            <label for="academic_year">Academic Year</label>
            <input type="text" class="form-control" id="academic_year" name="academic_year" placeholder="2024/2025" required>
          </div>
          <div class="mb-3">
            <label for="semester_number">Semester Number</label>
            <select class="form-select" id="semester_number" name="semester_number" required>
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="3">3</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
</div>

<script>
function confirmDelete(sessionId, sessionName) {
    document.getElementById('deleteSessionId').value = sessionId;
    document.getElementById('sessionName').textContent = sessionName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll('#stream_id, #department_id');
    
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Small delay to allow user to make multiple selections
            setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    });
});
// Open edit session modal prefilled
function openEditSessionModal(id, semesterName, academicYear, semesterNumber) {
    document.getElementById('session_id').value = id;
    document.getElementById('semester_name').value = semesterName;
    document.getElementById('academic_year').value = academicYear;
    document.getElementById('semester_number').value = semesterNumber;
    var modal = new bootstrap.Modal(document.getElementById('editSessionModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>
