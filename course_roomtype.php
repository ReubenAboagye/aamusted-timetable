<?php
$pageTitle = 'Course room type';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Handle form submission for adding new Course room type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $course_id = $conn->real_escape_string($_POST['course_id']);
        $session_id = $conn->real_escape_string($_POST['session_id']);
        
        // Check if this combination already exists
        $check_sql = "SELECT COUNT(*) as count FROM course_session_availability WHERE course_id = ? AND session_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $course_id, $session_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $count = $check_result->fetch_assoc()['count'];
        $check_stmt->close();
        
        if ($count > 0) {
            $error_message = "This course is already marked as available for this session.";
        } else {
            $sql = "INSERT INTO course_session_availability (course_id, session_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $course_id, $session_id);
        
        if ($stmt->execute()) {
            $success_message = "Course room type added successfully!";
        } else {
            $error_message = "Error adding Course room type: " . $conn->error;
        }
        $stmt->close();
        }
    } elseif ($_POST['action'] === 'bulk_add') {
        $session_id = $conn->real_escape_string($_POST['session_id']);
        $course_ids = isset($_POST['course_ids']) ? $_POST['course_ids'] : [];
        
        if (empty($course_ids)) {
            $error_message = "Please select at least one course.";
        } else {
            $success_count = 0;
            $error_count = 0;
            $already_exists_count = 0;
            
            foreach ($course_ids as $course_id) {
                $course_id = $conn->real_escape_string($course_id);
                
                // Check if this combination already exists
                $check_sql = "SELECT COUNT(*) as count FROM course_session_availability WHERE course_id = ? AND session_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ii", $course_id, $session_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $count = $check_result->fetch_assoc()['count'];
                $check_stmt->close();
                
                if ($count > 0) {
                    $already_exists_count++;
                } else {
                    $sql = "INSERT INTO course_session_availability (course_id, session_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $course_id, $session_id);
                    
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
                $message_parts[] = "$success_count course(s) added successfully";
            }
            if ($already_exists_count > 0) {
                $message_parts[] = "$already_exists_count course(s) already existed";
            }
            if ($error_count > 0) {
                $message_parts[] = "$error_count course(s) failed to add";
            }
            
            if ($error_count == 0 && $already_exists_count == 0) {
                $success_message = "All selected courses have been marked as available for this session!";
            } elseif ($error_count == 0) {
                $success_message = implode(", ", $message_parts) . ".";
            } else {
                $error_message = implode(", ", $message_parts) . ".";
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['course_id']) && isset($_POST['session_id'])) {
        $course_id = $conn->real_escape_string($_POST['course_id']);
        $session_id = $conn->real_escape_string($_POST['session_id']);
        
        $sql = "DELETE FROM course_session_availability WHERE course_id = ? AND session_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $course_id, $session_id);
        
        if ($stmt->execute()) {
            $success_message = "Course room type deleted successfully!";
        } else {
            $error_message = "Error deleting Course room type: " . $conn->error;
        }
        $stmt->close();
    } elseif ($_POST['action'] === 'edit_room_type' && isset($_POST['edit_course_id']) && isset($_POST['edit_session_id']) && isset($_POST['room_type'])) {
        $course_id = $conn->real_escape_string($_POST['edit_course_id']);
        $session_id = $conn->real_escape_string($_POST['edit_session_id']);
        $room_type = $conn->real_escape_string($_POST['room_type']);
        
        // Update the course's preferred room type
        $sql = "UPDATE courses SET preferred_room_type = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $room_type, $course_id);
        
        if ($stmt->execute()) {
            $success_message = "Room type updated successfully!";
        } else {
            $error_message = "Error updating room type: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch Course room type with related data - Updated to include all available fields from schema
$sql = "SELECT csa.course_id, csa.session_id, 
               c.name as course_name, c.code as course_code, c.credits, c.hours_per_week, c.level, c.preferred_room_type,
               CONCAT(s.academic_year, ' - ', s.semester_name) as session_name,
               s.academic_year, s.semester_number, s.start_date, s.end_date,
               c.department_id, d.name as department_name, d.code as department_code, d.short_name as department_short_name
        FROM course_session_availability csa 
        LEFT JOIN courses c ON csa.course_id = c.id 
        LEFT JOIN sessions s ON csa.session_id = s.id 
        LEFT JOIN departments d ON c.department_id = d.id
        ORDER BY d.name, c.level, c.name, s.academic_year, s.semester_number";
$result = $conn->query($sql);

// Fetch courses for dropdown - Updated to include all relevant fields
$course_sql = "SELECT c.id, c.name, c.code, c.credits, c.hours_per_week, c.level, c.preferred_room_type,
                      d.name as department_name, d.code as department_code, d.short_name as department_short_name
               FROM courses c 
               LEFT JOIN departments d ON c.department_id = d.id 
               WHERE c.is_active = 1 
               ORDER BY d.name, c.level, c.name";
$course_result = $conn->query($course_sql);

// Fetch sessions for dropdown
$sess_sql = "SELECT id, CONCAT(academic_year, ' - ', semester_name) as name, academic_year, semester_number, start_date, end_date FROM sessions WHERE is_active = 1 ORDER BY academic_year, semester_number";
$sess_result = $conn->query($sess_sql);
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-book-clock me-2"></i>Course room type</h4>
            <div>
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#bulkCourseSessionModal">
                    <i class="fas fa-books me-2"></i>Bulk Add Courses
                </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseSessionModal">
                    <i class="fas fa-plus me-2"></i>Add Single Course
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
            <input type="text" class="search-input" placeholder="Search Course room type...">
        </div>

        <div class="table-responsive">
            <table class="table" id="courseSessionTable">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Room Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <code class="text-primary"><?php echo htmlspecialchars($row['course_code'] ?? 'N/A'); ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-dark"><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($row['preferred_room_type'] ?? 'N/A'))); ?></span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="openEditModal('<?php echo $row['course_id']; ?>', '<?php echo $row['session_id']; ?>', '<?php echo htmlspecialchars($row['course_code']); ?>', '<?php echo htmlspecialchars($row['preferred_room_type'] ?? ''); ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="empty-state">
                                <i class="fas fa-book-clock"></i>
                                <p>No Course room type found. Add your first availability to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bulk Add Course room type Modal -->
<div class="modal fade" id="bulkCourseSessionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Add Courses to Session</h5>
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
                                <option value="<?php echo $sess['id']; ?>">
                                    <?php echo htmlspecialchars($sess['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Courses *</label>
                        <div class="course-selection-container" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 10px;">
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllCourses()">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllCourses()">Deselect All</button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="selectByDepartment()">Select by Department</button>
                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="selectByLevel()">Select by Level</button>
                            </div>
                            
                            <?php 
                            // Reset the courses result set for reuse
                            $course_result->data_seek(0);
                            $current_dept = '';
                            while ($course = $course_result->fetch_assoc()): 
                                if ($current_dept != $course['department_name']) {
                                    if ($current_dept != '') echo '</div>';
                                    $current_dept = $course['department_name'];
                                    echo '<div class="department-group mb-2">';
                                    echo '<h6 class="text-muted mb-1">' . htmlspecialchars($current_dept ?? 'No Department') . '</h6>';
                                }
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input course-checkbox" type="checkbox" name="course_ids[]" 
                                           value="<?php echo $course['id']; ?>" id="course_<?php echo $course['id']; ?>">
                                    <label class="form-check-label" for="course_<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['name']); ?> 
                                        (<?php echo htmlspecialchars($course['code']); ?>)
                                        <br><small class="text-muted">
                                            Level <?php echo htmlspecialchars($course['level']); ?> • 
                                            <?php echo htmlspecialchars($course['credits']); ?> credits • 
                                            <?php echo htmlspecialchars($course['hours_per_week']); ?> hrs/week
                                        </small>
                                    </label>
                                </div>
                            <?php endwhile; ?>
                            <?php if ($current_dept != '') echo '</div>'; ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> This will mark all selected courses as available for the entire selected session. 
                        Courses that are already available for this session will be skipped.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Selected Courses</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Single Course room type Modal -->
<div class="modal fade" id="addCourseSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Single Course to Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="course_id" class="form-label">Course *</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php 
                            // Reset the courses result set for reuse
                            $course_result->data_seek(0);
                            while ($course = $course_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['name']); ?> 
                                    (<?php echo htmlspecialchars($course['code']); ?>)
                                    - <?php echo htmlspecialchars($course['department_name'] ?? 'No Department'); ?>
                                    - Level <?php echo htmlspecialchars($course['level']); ?>
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
                        <strong>Note:</strong> This will mark the selected course as available for the entire selected session. 
                        The course will be considered available for all time slots within this academic session.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Course Room Type Modal -->
<div class="modal fade" id="editRoomTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Room Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_room_type">
                    <input type="hidden" name="edit_course_id" id="edit_course_id">
                    <input type="hidden" name="edit_session_id" id="edit_session_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <div class="form-control-plaintext" id="edit_course_display"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Room Type *</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="room_type" id="room_type_lecture" value="lecture_hall" required>
                                <label class="form-check-label" for="room_type_lecture">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>Lecture Hall
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="room_type" id="room_type_lab" value="laboratory" required>
                                <label class="form-check-label" for="room_type_lab">
                                    <i class="fas fa-flask me-2"></i>Laboratory
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> This will update the preferred room type for this course in the selected session.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Room Type</button>
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
    const table = document.getElementById('courseSessionTable');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Bulk selection functions
function selectAllCourses() {
    document.querySelectorAll('.course-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllCourses() {
    document.querySelectorAll('.course-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
}

function selectByDepartment() {
    const sessionId = document.getElementById('bulk_session_id').value;
    if (!sessionId) {
        alert('Please select a session first to see which courses are already available.');
        return;
    }
    
    // This would ideally make an AJAX call to get already available courses
    // For now, just select all
    selectAllCourses();
}

function selectByLevel() {
    const sessionId = document.getElementById('bulk_session_id').value;
    if (!sessionId) {
        alert('Please select a session first to see which courses are already available.');
        return;
    }
    
    // This would ideally make an AJAX call to get already available courses
    // For now, just select all
    selectAllCourses();
}

// Form validation for bulk add
document.querySelector('#bulkCourseSessionModal form').addEventListener('submit', function(e) {
    const selectedCourses = document.querySelectorAll('.course-checkbox:checked');
    if (selectedCourses.length === 0) {
        e.preventDefault();
        alert('Please select at least one course.');
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

// Edit room type modal functions
function openEditModal(courseId, sessionId, courseCode, currentRoomType) {
    // Set the hidden form values
    document.getElementById('edit_course_id').value = courseId;
    document.getElementById('edit_session_id').value = sessionId;
    document.getElementById('edit_course_display').textContent = courseCode;
    
    // Set the current room type selection
    if (currentRoomType === 'lecture_hall') {
        document.getElementById('room_type_lecture').checked = true;
    } else if (currentRoomType === 'laboratory') {
        document.getElementById('room_type_lab').checked = true;
    } else {
        // If no current room type, uncheck both
        document.getElementById('room_type_lecture').checked = false;
        document.getElementById('room_type_lab').checked = false;
    }
    
    // Show the modal
    const editModal = new bootstrap.Modal(document.getElementById('editRoomTypeModal'));
    editModal.show();
}
</script>
