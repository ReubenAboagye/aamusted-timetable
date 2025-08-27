<?php
include 'connect.php'; // Include database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new course
                $courseCode = $_POST['courseCode'];
                $courseName = $_POST['courseName'];
                $departmentId = $_POST['departmentId'];
                $credits = $_POST['credits'];
                $hoursPerWeek = $_POST['hoursPerWeek'];
                $level = $_POST['level'];
                $preferredRoomType = $_POST['preferredRoomType'];
                $isActive = isset($_POST['isActive']) ? 1 : 0;

                // Check if course code already exists
                $checkSql = "SELECT id FROM courses WHERE code = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("s", $courseCode);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('Course code already exists!');</script>";
                } else {
                    $sql = "INSERT INTO courses (code, name, department_id, credits, hours_per_week, level, preferred_room_type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssiiissi", $courseCode, $courseName, $departmentId, $credits, $hoursPerWeek, $level, $preferredRoomType, $isActive);
                        if ($stmt->execute()) {
                            echo "<script>alert('Course added successfully!'); window.location.href='courses.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'update':
                // Update existing course
                $id = $_POST['id'];
                $courseCode = $_POST['courseCode'];
                $courseName = $_POST['courseName'];
                $departmentId = $_POST['departmentId'];
                $credits = $_POST['credits'];
                $hoursPerWeek = $_POST['hoursPerWeek'];
                $level = $_POST['level'];
                $preferredRoomType = $_POST['preferredRoomType'];
                $isActive = isset($_POST['isActive']) ? 1 : 0;

                // Check if course code already exists for other courses
                $checkSql = "SELECT id FROM courses WHERE code = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("si", $courseCode, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('Course code already exists!');</script>";
                } else {
                    $sql = "UPDATE courses SET code = ?, name = ?, department_id = ?, credits = ?, hours_per_week = ?, level = ?, preferred_room_type = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssiiissii", $courseCode, $courseName, $departmentId, $credits, $hoursPerWeek, $level, $preferredRoomType, $isActive, $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Course updated successfully!'); window.location.href='courses.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'delete':
                // Delete course
                $id = $_POST['id'];
                
                // Check if course has dependent records
                $checkSql = "SELECT 
                    (SELECT COUNT(*) FROM class_courses WHERE course_id = ?) as class_course_count,
                    (SELECT COUNT(*) FROM lecturer_courses WHERE course_id = ?) as lecturer_course_count,
                    (SELECT COUNT(*) FROM course_session_availability WHERE course_id = ?) as session_availability_count,
                    (SELECT COUNT(*) FROM timetable WHERE course_id = ?) as timetable_count";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("iiii", $id, $id, $id, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $dependencies = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($dependencies['class_course_count'] > 0 || $dependencies['lecturer_course_count'] > 0 || $dependencies['session_availability_count'] > 0 || $dependencies['timetable_count'] > 0) {
                    echo "<script>alert('Cannot delete course: It has dependent class courses, lecturer assignments, session availability, or timetables. Consider deactivating instead.');</script>";
                } else {
                    $sql = "DELETE FROM courses WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Course deleted successfully!'); window.location.href='courses.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                break;
        }
    }
}

// Fetch existing courses for display
$courses = [];
$sql = "SELECT c.*, d.name as department_name, d.code as department_code 
        FROM courses c 
        JOIN departments d ON c.department_id = d.id 
        ORDER BY c.code";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Fetch departments for dropdown
$departments = [];
$sql = "SELECT id, name, code FROM departments WHERE is_active = 1 ORDER BY name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Room type options
$roomTypes = [
    'classroom' => 'Classroom',
    'lecture_hall' => 'Lecture Hall',
    'laboratory' => 'Laboratory',
    'computer_lab' => 'Computer Lab',
    'seminar_room' => 'Seminar Room',
    'auditorium' => 'Auditorium'
];
?>

<?php $pageTitle = 'Manage Courses'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

  <div class="main-content" id="mainContent">
        <h2>Manage Courses</h2>
        
        <!-- Search Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="input-group" style="width: 300px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Search courses...">
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dataEntryModal">Add New Course</button>
            </div>
        </div>
        
        <div class="row">
        <div class="row">
            <!-- Add Course Button -->
            <div class="col-md-12 mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal">
                    <i class="fas fa-plus me-2"></i>Add New Course
                </button>
            </div>
            
            <!-- Display Courses -->
            <div class="col-md-12">
                <div class="table-container">
                    <div class="table-header">
                        <h4><i class="fas fa-book me-2"></i>Existing Courses</h4>
                    </div>
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search courses...">
                    </div>
                    <?php if (empty($courses)): ?>
                        <div class="empty-state">
                            <i class="fas fa-book-open"></i>
                            <h5>No courses found</h5>
                            <p>Start by adding your first course using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" id="coursesTable">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Department</th>
                                            <th>Details</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($course['code']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($course['name']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($course['department_code']); ?></span><br>
                                                    <small><?php echo htmlspecialchars($course['department_name']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong>Level:</strong> <?php echo $course['level']; ?><br>
                                                        <strong>Credits:</strong> <?php echo $course['credits']; ?><br>
                                                        <strong>Hours:</strong> <?php echo $course['hours_per_week']; ?>/week<br>
                                                        <strong>Room:</strong> <?php echo ucwords(str_replace('_', ' ', $course['preferred_room_type'])); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($course['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['code']); ?>', '<?php echo htmlspecialchars($course['name']); ?>', <?php echo $course['department_id']; ?>, <?php echo $course['credits']; ?>, <?php echo $course['hours_per_week']; ?>, <?php echo $course['level']; ?>, '<?php echo $course['preferred_room_type']; ?>', <?php echo $course['is_active']; ?>)">Edit</button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['code']); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
    
        </div>
    </div>
    
<?php include 'includes/footer.php'; ?>

<!-- Course Modal -->
<div class="modal fade" id="courseModal" tabindex="-1" aria-labelledby="courseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="courseModalLabel">Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="courseForm">
                <div class="modal-body">
                    <input type="hidden" id="action" name="action" value="add">
                    <input type="hidden" id="courseId" name="id" value="">
                    
                    <div class="alert alert-info" id="formMode" style="display: none;">
                        <strong>Edit Mode:</strong> You are currently editing an existing course. Click "Cancel Edit" to return to add mode.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="courseCode" class="form-label">Course Code *</label>
                                <input type="text" class="form-control" id="courseCode" name="courseCode" required>
                                <div class="form-text">e.g., "CS101", "MATH201"</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="level" class="form-label">Level *</label>
                                <select class="form-select" id="level" name="level" required>
                                    <option value="">Select Level</option>
                                    <option value="1">Level 100 (First Year)</option>
                                    <option value="2">Level 200 (Second Year)</option>
                                    <option value="3">Level 300 (Third Year)</option>
                                    <option value="4">Level 400 (Fourth Year)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="courseName" class="form-label">Course Name *</label>
                        <input type="text" class="form-control" id="courseName" name="courseName" required>
                        <div class="form-text">e.g., "Introduction to Computer Science", "Calculus I"</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="departmentId" class="form-label">Department *</label>
                        <select class="form-select" id="departmentId" name="departmentId" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?> (<?php echo htmlspecialchars($dept['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="credits" class="form-label">Credit Hours *</label>
                                <input type="number" class="form-control" id="credits" name="credits" value="3" min="1" max="6" required>
                                <div class="form-text">Standard credit units</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="hoursPerWeek" class="form-label">Hours Per Week *</label>
                                <input type="number" class="form-control" id="hoursPerWeek" name="hoursPerWeek" value="3" min="1" max="10" required>
                                <div class="form-text">Total contact hours per week</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="preferredRoomType" class="form-label">Preferred Room Type</label>
                        <select class="form-select" id="preferredRoomType" name="preferredRoomType">
                            <option value="classroom">Classroom</option>
                            <?php foreach ($roomTypes as $value => $label): ?>
                                <?php if ($value !== 'classroom'): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Type of room preferred for this course</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isActive" name="isActive" checked>
                            <label class="form-check-label" for="isActive">
                                Course is Active
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;" onclick="cancelEdit()">Cancel Edit</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Add Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
        function editCourse(id, code, name, departmentId, credits, hoursPerWeek, level, preferredRoomType, isActive) {
            // Set form to edit mode
            document.getElementById('action').value = 'update';
            document.getElementById('courseId').value = id;
            document.getElementById('courseCode').value = code;
            document.getElementById('courseName').value = name;
            document.getElementById('departmentId').value = departmentId;
            document.getElementById('credits').value = credits;
            document.getElementById('hoursPerWeek').value = hoursPerWeek;
            document.getElementById('level').value = level;
            document.getElementById('preferredRoomType').value = preferredRoomType;
            document.getElementById('isActive').checked = isActive == 1;
            
            // Update form appearance
            document.getElementById('courseModalLabel').textContent = 'Edit Course';
            document.getElementById('submitBtn').textContent = 'Update Course';
            document.getElementById('submitBtn').className = 'btn btn-warning';
            document.getElementById('cancelBtn').style.display = 'inline-block';
            document.getElementById('formMode').style.display = 'block';
            
            // Show modal
            var modal = new bootstrap.Modal(document.getElementById('courseModal'));
            modal.show();
        }
        
        function cancelEdit() {
            // Reset form to add mode
            document.getElementById('action').value = 'add';
            document.getElementById('courseId').value = '';
            document.getElementById('courseForm').reset();
            
            // Update form appearance
            document.getElementById('courseModalLabel').textContent = 'Add New Course';
            document.getElementById('submitBtn').textContent = 'Add Course';
            document.getElementById('submitBtn').className = 'btn btn-primary';
            document.getElementById('cancelBtn').style.display = 'none';
            document.getElementById('formMode').style.display = 'none';
            
            // Reset default values
            document.getElementById('credits').value = '3';
            document.getElementById('hoursPerWeek').value = '3';
            document.getElementById('level').value = '';
            document.getElementById('preferredRoomType').value = 'classroom';
        }
        
        // Reset modal when it's closed
        document.addEventListener('DOMContentLoaded', function() {
            var courseModal = document.getElementById('courseModal');
            courseModal.addEventListener('hidden.bs.modal', function () {
                cancelEdit();
            });
        });
        
        function deleteCourse(id, code) {
            if (confirm(`Are you sure you want to delete the course "${code}"?`)) {
                // Create a form to submit the deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Update current time in header
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: true,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        setInterval(updateTime, 1000);
        updateTime();
        
        // Toggle sidebar visibility
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const footer = document.getElementById('footer');
            sidebar.classList.toggle('show');
            if (sidebar.classList.contains('show')) {
                mainContent.classList.add('shift');
                footer.classList.add('shift');
            } else {
                mainContent.classList.remove('shift');
                footer.classList.remove('shift');
            }
        });
        
        // Search functionality for courses table
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let rows = document.querySelectorAll('#coursesTable tbody tr');
            rows.forEach(row => {
                let cells = row.querySelectorAll('td');
                let matchFound = false;
                for (let i = 0; i < cells.length - 1; i++) {
                    if (cells[i].textContent.toLowerCase().includes(searchValue)) {
                        matchFound = true;
                        break;
                    }
                }
                row.style.display = matchFound ? '' : 'none';
            });
        });
        
        // Back to Top Button Setup
        const backToTopButton = document.getElementById("backToTop");
        const progressCircle = document.getElementById("progressCircle");
        const circumference = 2 * Math.PI * 20;
        progressCircle.style.strokeDasharray = circumference;
        progressCircle.style.strokeDashoffset = circumference;
        
        window.addEventListener("scroll", function() {
            const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
            if (scrollTop > 100) {
                backToTopButton.style.display = "block";
            } else {
                backToTopButton.style.display = "none";
            }
            
            const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
            const scrollPercentage = scrollTop / scrollHeight;
            const offset = circumference - (scrollPercentage * circumference);
            progressCircle.style.strokeDashoffset = offset;
        });
        
        backToTopButton.addEventListener("click", function() {
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    </script>
</body>
</html>

