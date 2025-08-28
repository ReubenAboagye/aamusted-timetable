<?php
// Database connection (needed early for AJAX handlers)
include 'connect.php';

// AJAX: return session data as JSON for the edit modal
if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
    $id = $conn->real_escape_string($_GET['id']);
    $sql = "SELECT * FROM sessions WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode($row ?: []);
    $stmt->close();
    $conn->close();
    exit;
}

$pageTitle = 'Sessions Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Handle form submission for adding new session
// Handle POST actions: add, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $academic_year = $conn->real_escape_string($_POST['academic_year']);
        $semester_number = $conn->real_escape_string($_POST['semester_number']);
        $semester_name = $conn->real_escape_string($_POST['semester_name']);
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $sql = "INSERT INTO sessions (academic_year, semester_number, semester_name, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $academic_year, $semester_number, $semester_name, $start_date, $end_date, $is_active);
        
        if ($stmt->execute()) {
            $success_message = "Session added successfully!";
        } else {
            $error_message = "Error adding session: " . $conn->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'update' && isset($_POST['id'])) {
        // Update existing session
        $id = (int)$_POST['id'];
        $academic_year = $conn->real_escape_string($_POST['academic_year']);
        $semester_number = $conn->real_escape_string($_POST['semester_number']);
        $semester_name = $conn->real_escape_string($_POST['semester_name']);
        $start_date = $conn->real_escape_string($_POST['start_date']);
        $end_date = $conn->real_escape_string($_POST['end_date']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $sql = "UPDATE sessions SET academic_year = ?, semester_number = ?, semester_name = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        // 5 strings (academic_year, semester_number, semester_name, start_date, end_date) then 2 ints (is_active, id)
        $stmt->bind_param("sssssii", $academic_year, $semester_number, $semester_name, $start_date, $end_date, $is_active, $id);

        if ($stmt->execute()) {
            $success_message = "Session updated successfully!";
        } else {
            $error_message = "Error updating session: " . $conn->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $sql = "UPDATE sessions SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success_message = "Session deleted successfully!";
        } else {
            $error_message = "Error deleting session: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch sessions
$sql = "SELECT * FROM sessions WHERE is_active IS NOT NULL ORDER BY academic_year DESC, semester_number";
$result = $conn->query($sql);
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-clock me-2"></i>Sessions Management</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSessionModal">
                <i class="fas fa-plus me-2"></i>Add New Session
            </button>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="search-container m-3">
            <input type="text" class="search-input" placeholder="Search sessions...">
        </div>

        <div class="table-responsive">
            <table class="table" id="sessionsTable">
                <thead>
                    <tr>
                        <th>Academic Year</th>
                        <th>Semester</th>
                        <th>Semester Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php 
                            $start = new DateTime($row['start_date']);
                            $end = new DateTime($row['end_date']);
                            $duration = $start->diff($end)->days;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['academic_year']); ?></strong></td>
                                <td>
                                    <?php 
                                    $semester_badge = 'bg-secondary';
                                    switch($row['semester_number']) {
                                        case '1': $semester_badge = 'bg-primary'; break;
                                        case '2': $semester_badge = 'bg-success'; break;
                                        case '3': $semester_badge = 'bg-warning'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $semester_badge; ?>">Semester <?php echo htmlspecialchars($row['semester_number']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['semester_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($row['end_date'])); ?></td>
                                <td><span class="badge bg-info"><?php echo $duration; ?> days</span></td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editSession(<?php echo $row['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this session?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-clock"></i>
                                <p>No sessions found. Add your first session to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Session Modal -->
<div class="modal fade" id="addSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="academic_year" class="form-label">Academic Year *</label>
                                <input type="text" class="form-control" id="academic_year" name="academic_year" required placeholder="e.g., 2024/2025">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="semester_number" class="form-label">Semester Number *</label>
                                <select class="form-select" id="semester_number" name="semester_number" required>
                                    <option value="">Select Semester</option>
                                    <option value="1">1 - First Semester</option>
                                    <option value="2">2 - Second Semester</option>
                                    <option value="3">3 - Third Semester</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="semester_name" class="form-label">Semester Name *</label>
                        <input type="text" class="form-control" id="semester_name" name="semester_name" required placeholder="e.g., First Semester 2024/2025">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active Session
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Session Modal (static) -->
<div class="modal fade" id="editSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editSessionForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_session_id">

                    <div class="mb-3">
                        <label for="edit_academic_year" class="form-label">Academic Year *</label>
                        <input type="text" class="form-control" id="edit_academic_year" name="academic_year" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_semester_number" class="form-label">Semester Number *</label>
                        <select class="form-select" id="edit_semester_number" name="semester_number" required>
                            <option value="">Select Semester</option>
                            <option value="1">1 - First Semester</option>
                            <option value="2">2 - Second Semester</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_semester_name" class="form-label">Semester Name *</label>
                        <input type="text" class="form-control" id="edit_semester_name" name="semester_name" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_start_date" class="form-label">Start Date *</label>
                                <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_end_date" class="form-label">End Date *</label>
                                <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">Active Session</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
function editSession(id) {
    // Fetch session data via AJAX and populate the edit modal
    fetch('?action=get&id=' + encodeURIComponent(id))
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data || !data.id) {
                alert('Could not load session data.');
                return;
            }

            // Use the static modal in the page and attach submit handler once
            var existing = document.getElementById('editSessionModal');
            if (existing && !existing.__editHandlerAttached) {
                var editForm = document.getElementById('editSessionForm');
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(editForm);
                    fetch('', { method: 'POST', body: formData })
                        .then(function(resp) { return resp.text(); })
                        .then(function() { location.reload(); })
                        .catch(function() { alert('Failed to update session.'); });
                });
                existing.__editHandlerAttached = true;
            }

            // Populate fields
            document.getElementById('edit_session_id').value = data.id;
            document.getElementById('edit_academic_year').value = data.academic_year || '';
            document.getElementById('edit_semester_number').value = data.semester_number || '';
            document.getElementById('edit_semester_name').value = data.semester_name || '';
            document.getElementById('edit_start_date').value = data.start_date || '';
            document.getElementById('edit_end_date').value = data.end_date || '';
            document.getElementById('edit_is_active').checked = data.is_active == 1 || data.is_active === '1';

            // Show modal using Bootstrap's modal API
            var editModalEl = document.getElementById('editSessionModal');
            var modal = new bootstrap.Modal(editModalEl);
            modal.show();
        })
        .catch(function() { alert('Failed to fetch session data.'); });
}
</script>
