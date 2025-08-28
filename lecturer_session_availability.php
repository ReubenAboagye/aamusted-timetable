<?php
$pageTitle = 'Lecturer Session Availability';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Handle form submission for adding new lecturer session availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $lecturer_id = $conn->real_escape_string($_POST['lecturer_id']);
        $session_id = $conn->real_escape_string($_POST['session_id']);
        
        // Check if this combination already exists
        $check_sql = "SELECT COUNT(*) as count FROM lecturer_session_availability WHERE lecturer_id = ? AND session_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $lecturer_id, $session_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $count = $check_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($count > 0) {
            $error_message = "This lecturer is already marked as available for this session.";
        } else {
            $sql = "INSERT INTO lecturer_session_availability (lecturer_id, session_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $lecturer_id, $session_id);
        
        if ($stmt->execute()) {
            $success_message = "Lecturer session availability added successfully!";
        } else {
            $error_message = "Error adding lecturer session availability: " . $conn->error;
        }
        $stmt->close();
        }
    } elseif ($_POST['action'] === 'bulk_add') {
        $session_id = $conn->real_escape_string($_POST['session_id']);
        $lecturer_ids = isset($_POST['lecturer_ids']) ? $_POST['lecturer_ids'] : [];
        
        if (empty($lecturer_ids)) {
            $error_message = "Please select at least one lecturer.";
        } else {
            $success_count = 0;
            $error_count = 0;
            $already_exists_count = 0;
            
            foreach ($lecturer_ids as $lecturer_id) {
                $lecturer_id = $conn->real_escape_string($lecturer_id);
                
                // Check if this combination already exists
                $check_sql = "SELECT COUNT(*) as count FROM lecturer_session_availability WHERE lecturer_id = ? AND session_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $lecturer_id, $session_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $count = $check_result->fetch_assoc()['count'];
                $check_stmt->close();
                
                if ($count > 0) {
                    $already_exists_count++;
                } else {
                    $sql = "INSERT INTO lecturer_session_availability (lecturer_id, session_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $lecturer_id, $session_id);
                    
                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    $stmt->close();
                }
            }
            
            // Build success message
            $message_parts = [];
            if ($success_count > 0) {
                $message_parts[] = "$success_count lecturer(s) added successfully";
            }
            if ($already_exists_count > 0) {
                $message_parts[] = "$already_exists_count lecturer(s) already existed";
            }
            if ($error_count > 0) {
                $message_parts[] = "$error_count lecturer(s) failed to add";
            }
            
            if ($error_count == 0 && $already_exists_count == 0) {
                $success_message = "All selected lecturers have been marked as available for this session!";
            } elseif ($error_count == 0) {
                $success_message = implode(", ", $message_parts) . ".";
            } else {
                $error_message = implode(", ", $message_parts) . ".";
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['lecturer_id']) && isset($_POST['session_id'])) {
        $lecturer_id = $conn->real_escape_string($_POST['lecturer_id']);
        $session_id = $conn->real_escape_string($_POST['session_id']);
        
        $sql = "DELETE FROM lecturer_session_availability WHERE lecturer_id = ? AND session_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $lecturer_id, $session_id);
        
        if ($stmt->execute()) {
            $success_message = "Lecturer session availability deleted successfully!";
        } else {
            $error_message = "Error deleting lecturer session availability: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch lecturer session availability with related data
$sql = "SELECT lsa.lecturer_id, lsa.session_id, l.name as lecturer_name, 
               CONCAT(s.academic_year, ' - ', s.semester_name) as session_name,
               l.department_id, d.name as department_name
        FROM lecturer_session_availability lsa 
        LEFT JOIN lecturers l ON lsa.lecturer_id = l.id 
        LEFT JOIN sessions s ON lsa.session_id = s.id 
        LEFT JOIN departments d ON l.department_id = d.id
        ORDER BY l.name, s.academic_year, s.semester_number";
$result = $conn->query($sql);

// Fetch lecturers for dropdown
$lect_sql = "SELECT l.id, l.name, d.name as department_name 
              FROM lecturers l 
              LEFT JOIN departments d ON l.department_id = d.id 
              WHERE l.is_active = 1 
              ORDER BY l.name";
$lect_result = $conn->query($lect_sql);

// Fetch sessions for dropdown
$sess_sql = "SELECT id, CONCAT(academic_year, ' - ', semester_name) as name FROM sessions WHERE is_active = 1 ORDER BY academic_year, semester_number";
$sess_result = $conn->query($sess_sql);
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-user-clock me-2"></i>Lecturer Session Availability</h4>
            <div>
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#bulkLecturerSessionModal">
                    <i class="fas fa-users me-2"></i>Bulk Add Lecturers
                </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLecturerSessionModal">
                    <i class="fas fa-plus me-2"></i>Add Single Lecturer
            </button>
            </div>
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
            <input type="text" class="search-input" placeholder="Search lecturer session availability...">
        </div>

        <div class="table-responsive">
            <table class="table" id="lecturerSessionTable">
                <thead>
                    <tr>
                        <th>Lecturer</th>
                        <th>Department</th>
                        <th>Session</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['lecturer_name'] ?? 'N/A'); ?></strong></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></span></td>
                                <td><?php echo htmlspecialchars($row['session_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this availability?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="lecturer_id" value="<?php echo $row['lecturer_id']; ?>">
                                        <input type="hidden" name="session_id" value="<?php echo $row['session_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                <i class="fas fa-user-clock"></i>
                                <p>No lecturer session availability found. Add your first availability to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bulk Add Lecturer Session Availability Modal -->
<div class="modal fade" id="bulkLecturerSessionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Add Lecturers to Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_add">
                    
                    <div class="mb-3">
                        <label for="bulk_session_id" class="form-label">Session *</label>
                        <select class="form-select" id="bulk_session_id" name="session_id" required>
                            <option value="">Select Session</option>
                            <?php 
                            // Reset the sessions result set for reuse
                            $sess_result->data_seek(0);
                            while ($sess = $sess_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $sess['id']; ?>"><?php echo htmlspecialchars($sess['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Lecturers *</label>
                        <div class="lecturer-selection-container" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 10px;">
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllLecturers()">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllLecturers()">Deselect All</button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="selectByDepartment()">Select by Department</button>
                            </div>
                            
                            <?php 
                            // Reset the lecturers result set for reuse
                            $lect_result->data_seek(0);
                            $current_dept = '';
                            while ($lect = $lect_result->fetch_assoc()): 
                                if ($current_dept != $lect['department_name']) {
                                    if ($current_dept != '') echo '</div>';
                                    $current_dept = $lect['department_name'];
                                    echo '<div class="department-group mb-2">';
                                    echo '<h6 class="text-muted mb-1">' . htmlspecialchars($current_dept ?? 'No Department') . '</h6>';
                                }
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input lecturer-checkbox" type="checkbox" name="lecturer_ids[]" 
                                           value="<?php echo $lect['id']; ?>" id="lect_<?php echo $lect['id']; ?>">
                                    <label class="form-check-label" for="lect_<?php echo $lect['id']; ?>">
                                        <?php echo htmlspecialchars($lect['name']); ?>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                            <?php if ($current_dept != '') echo '</div>'; ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> This will mark all selected lecturers as available for the entire selected session. 
                        Lecturers who are already available for this session will be skipped.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Selected Lecturers</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Single Lecturer Session Availability Modal -->
<div class="modal fade" id="addLecturerSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Single Lecturer to Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="lecturer_id" class="form-label">Lecturer *</label>
                        <select class="form-select" id="lecturer_id" name="lecturer_id" required>
                            <option value="">Select Lecturer</option>
                            <?php 
                            // Reset the lecturers result set for reuse
                            $lect_result->data_seek(0);
                            while ($lect = $lect_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $lect['id']; ?>">
                                    <?php echo htmlspecialchars($lect['name']); ?> 
                                    (<?php echo htmlspecialchars($lect['department_name'] ?? 'No Department'); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="session_id" class="form-label">Session *</label>
                        <select class="form-select" id="session_id" name="session_id" required>
                            <option value="">Select Session</option>
                            <?php 
                            // Reset the sessions result set for reuse
                            $sess_result->data_seek(0);
                            while ($sess = $sess_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $sess['id']; ?>"><?php echo htmlspecialchars($sess['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> This will mark the selected lecturer as available for the entire selected session. 
                        The lecturer will be considered available for all time slots within this academic session.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Lecturer</button>
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
// Add search functionality
document.querySelector('.search-input').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('lecturerSessionTable');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Bulk selection functions
function selectAllLecturers() {
    document.querySelectorAll('.lecturer-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllLecturers() {
    document.querySelectorAll('.lecturer-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
}

function selectByDepartment() {
    const sessionId = document.getElementById('bulk_session_id').value;
    if (!sessionId) {
        alert('Please select a session first to see which lecturers are already available.');
        return;
    }
    
    // This would ideally make an AJAX call to get already available lecturers
    // For now, just select all
    selectAllLecturers();
}

// Form validation for bulk add
document.querySelector('#bulkLecturerSessionModal form').addEventListener('submit', function(e) {
    const selectedLecturers = document.querySelectorAll('.lecturer-checkbox:checked');
    if (selectedLecturers.length === 0) {
        e.preventDefault();
        alert('Please select at least one lecturer.');
        return false;
    }
    
    const sessionId = document.getElementById('bulk_session_id').value;
    if (!sessionId) {
        e.preventDefault();
        alert('Please select a session.');
        return false;
    }
    
    return true;
});
</script>
