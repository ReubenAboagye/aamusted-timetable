<?php
include 'connect.php'; // Include database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new lecturer-course assignment
                $lecturerId = $_POST['lecturerId'];
                $courseId = $_POST['courseId'];
                $isActive = isset($_POST['isActive']) ? 1 : 0;

                // Check if this assignment already exists
                $checkSql = "SELECT id FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $lecturerId, $courseId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('This lecturer is already assigned to teach this course!');</script>";
                } else {
                    $sql = "INSERT INTO lecturer_courses (lecturer_id, course_id, is_active) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("iii", $lecturerId, $courseId, $isActive);
                        if ($stmt->execute()) {
                            echo "<script>alert('Course assigned to lecturer successfully!'); window.location.href='lecturer_courses.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'update':
                // Update existing lecturer-course assignment
                $id = $_POST['id'];
                $lecturerId = $_POST['lecturerId'];
                $courseId = $_POST['courseId'];
                $isActive = isset($_POST['isActive']) ? 1 : 0;

                // Check if this assignment already exists for other records
                $checkSql = "SELECT id FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("iii", $lecturerId, $courseId, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('This lecturer is already assigned to teach this course!');</script>";
                } else {
                    $sql = "UPDATE lecturer_courses SET lecturer_id = ?, course_id = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("iiii", $lecturerId, $courseId, $isActive, $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Assignment updated successfully!'); window.location.href='lecturer_courses.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'delete':
                // Delete lecturer-course assignment
                $id = $_POST['id'];
                
                // Check if assignment has dependent records
                $checkSql = "SELECT 
                    (SELECT COUNT(*) FROM timetable WHERE lecturer_id = ?) as timetable_count,
                    (SELECT COUNT(*) FROM timetable_lecturers WHERE lecturer_id = ?) as team_teaching_count";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $id, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $dependencies = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($dependencies['timetable_count'] > 0 || $dependencies['team_teaching_count'] > 0) {
                    echo "<script>alert('Cannot delete assignment: It has dependent timetables or team teaching assignments. Consider deactivating instead.');</script>";
                } else {
                    $sql = "DELETE FROM lecturer_courses WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Assignment deleted successfully!'); window.location.href='lecturer_courses.php';</script>";
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

// Fetch existing lecturer-course assignments for display
$lecturerCourses = [];
$sql = "SELECT lc.*, l.name as lecturer_name, l.rank,
               co.code as course_code, co.name as course_name, co.credits, co.level as course_level,
               d.name as department_name, d.code as department_code
        FROM lecturer_courses lc 
        JOIN lecturers l ON lc.lecturer_id = l.id 
        JOIN courses co ON lc.course_id = co.id 
        JOIN departments d ON l.department_id = d.id
        ORDER BY l.name, co.code";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lecturerCourses[] = $row;
    }
}

// Fetch lecturers for dropdown
$lecturers = [];
$sql = "SELECT l.id, l.name, l.rank, d.name as department_name, d.code as department_code 
        FROM lecturers l 
        JOIN departments d ON l.department_id = d.id 
        WHERE l.is_active = 1 
        ORDER BY l.name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $lecturers[] = $row;
    }
}

// Fetch courses for dropdown
$courses = [];
$sql = "SELECT id, code, name, credits, level FROM courses WHERE is_active = 1 ORDER BY code";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Get statistics
$totalAssignments = count($lecturerCourses);
$activeAssignments = count(array_filter($lecturerCourses, function($a) { return $a['is_active']; }));
$uniqueLecturers = count(array_unique(array_column($lecturerCourses, 'lecturer_id')));
$uniqueCourses = count(array_unique(array_column($lecturerCourses, 'course_id')));
?>

<?php $pageTitle = 'Lecturer-Course Assignments'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

  <div class="main-content" id="mainContent">
        <h2>Manage Lecturer-Course Assignments</h2>
        
        <!-- Search Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="input-group" style="width: 300px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Search assignments...">
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dataEntryModal">Add New Assignment</button>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $totalAssignments; ?></h4>
                        <small>Total Assignments</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $activeAssignments; ?></h4>
                        <small>Active Assignments</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $uniqueLecturers; ?></h4>
                        <small>Lecturers Teaching</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $uniqueCourses; ?></h4>
                        <small>Courses Assigned</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Add Lecturer-Course Assignment Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 id="formHeader">Assign Course to Lecturer</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="lecturerCourseForm">
                            <input type="hidden" id="action" name="action" value="add">
                            <input type="hidden" id="assignmentId" name="id" value="">
                            
                            <div class="alert alert-info" id="formMode" style="display: none;">
                                <strong>Edit Mode:</strong> You are currently editing an existing assignment. Click "Cancel Edit" to return to add mode.
                            </div>
                            <div class="mb-3">
                                <label for="lecturerId" class="form-label">Lecturer *</label>
                                <select class="form-select" id="lecturerId" name="lecturerId" required>
                                    <option value="">Select Lecturer</option>
                                    <?php foreach ($lecturers as $lecturer): ?>
                                        <option value="<?php echo $lecturer['id']; ?>">
                                            <?php echo htmlspecialchars($lecturer['name']); ?> 
                                            <?php if ($lecturer['rank']): ?>
                            (<?php echo htmlspecialchars($lecturer['rank']); ?>)
                        <?php endif; ?>
                                            - <?php echo htmlspecialchars($lecturer['department_code']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the lecturer to assign a course to</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="courseId" class="form-label">Course *</label>
                                <select class="form-select" id="courseId" name="courseId" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['code']); ?> - <?php echo htmlspecialchars($course['name']); ?> 
                                            (<?php echo $course['credits']; ?> credits, Level <?php echo $course['level']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the course this lecturer can teach</div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isActive" name="isActive" checked>
                                    <label class="form-check-label" for="isActive">
                                        Assignment is Active
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitBtn">Assign Course to Lecturer</button>
                                <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;" onclick="cancelEdit()">Cancel Edit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Display Lecturer-Course Assignments -->
            <div class="col-md-6">
                <div class="card">
                    <div class="table-header">
                        <h4><i class="fas fa-chalkboard-teacher me-2"></i>Existing Assignments</h4>
                    </div>
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search assignments...">
                    </div>
                    <?php if (empty($lecturerCourses)): ?>
                        <div class="empty-state">
                            <i class="fas fa-link"></i>
                            <h5>No course assignments found</h5>
                            <p>Start by assigning lecturers to courses using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table" id="lecturerCoursesTable">
                                    <thead>
                                        <tr>
                                            <th>Lecturer</th>
                                            <th>Course</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lecturerCourses as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignment['lecturer_name']); ?></strong><br>
                                                    <?php if ($assignment['rank']): ?>
                                <small class="text-muted"><?php echo htmlspecialchars($assignment['rank']); ?></small><br>
                            <?php endif; ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['department_name']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignment['course_code']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['course_name']); ?></small><br>
                                                    <small class="text-muted"><?php echo $assignment['credits']; ?> credits, Level <?php echo $assignment['course_level']; ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($assignment['department_code']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($assignment['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?php echo $assignment['id']; ?>, <?php echo $assignment['lecturer_id']; ?>, <?php echo $assignment['course_id']; ?>, <?php echo $assignment['is_active']; ?>)">Edit</button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAssignment(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['lecturer_name']); ?> - <?php echo htmlspecialchars($assignment['course_code']); ?>')">Delete</button>
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
        </div>
        
        <div class="mt-3">
    
        </div>
    </div>
    
    <!-- Footer -->
    <div class="footer" id="footer">
        &copy; 2025 University Timetable Generator
    </div>
    
    <!-- Back to Top Button -->
    <button id="backToTop">
        <svg width="50" height="50" viewBox="0 0 50 50">
            <circle id="progressCircle" cx="25" cy="25" r="20" fill="none" stroke="#FFD700" stroke-width="4" stroke-dasharray="126" stroke-dashoffset="126"/>
        </svg>
        <i class="fas fa-arrow-up arrow-icon"></i>
    </button>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editAssignment(id, lecturerId, courseId, isActive) {
            // Set form to edit mode
            document.getElementById('action').value = 'update';
            document.getElementById('assignmentId').value = id;
            document.getElementById('lecturerId').value = lecturerId;
            document.getElementById('courseId').value = courseId;
            document.getElementById('isActive').checked = isActive == 1;
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Edit Assignment';
            document.getElementById('submitBtn').textContent = 'Update Assignment';
            document.getElementById('submitBtn').className = 'btn btn-warning';
            document.getElementById('cancelBtn').style.display = 'block';
            document.getElementById('formMode').style.display = 'block';
            
            // Scroll to form
            document.getElementById('lecturerCourseForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function cancelEdit() {
            // Reset form to add mode
            document.getElementById('action').value = 'add';
            document.getElementById('assignmentId').value = '';
            document.getElementById('lecturerCourseForm').reset();
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Assign Course to Lecturer';
            document.getElementById('submitBtn').textContent = 'Assign Course to Lecturer';
            document.getElementById('submitBtn').className = 'btn btn-primary';
            document.getElementById('cancelBtn').style.display = 'none';
            document.getElementById('formMode').style.display = 'none';
            
            // Reset default values
            document.getElementById('isActive').checked = true;
        }
        
        function deleteAssignment(id, assignmentName) {
            if (confirm(`Are you sure you want to delete the assignment "${assignmentName}"?`)) {
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
        
        // Search functionality for lecturer courses table
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let rows = document.querySelectorAll('#lecturerCoursesTable tbody tr');
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
