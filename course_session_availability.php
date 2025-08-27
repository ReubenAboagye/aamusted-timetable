<?php
include 'connect.php'; // Include database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new course-session availability
                $courseId = $_POST['courseId'];
                $sessionId = $_POST['sessionId'];

                // Check if this assignment already exists
                $checkSql = "SELECT course_id FROM course_session_availability WHERE course_id = ? AND session_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $courseId, $sessionId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('This course is already offered in the selected session!');</script>";
                } else {
                    $sql = "INSERT INTO course_session_availability (course_id, session_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ii", $courseId, $sessionId);
                        if ($stmt->execute()) {
                            echo "<script>alert('Course assigned to session successfully!'); window.location.href='course_session_availability.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'update':
                // Update existing course-session availability
                $oldCourseId = $_POST['oldCourseId'];
                $oldSessionId = $_POST['oldSessionId'];
                $newCourseId = $_POST['courseId'];
                $newSessionId = $_POST['sessionId'];

                // Check if the new combination already exists
                $checkSql = "SELECT course_id FROM course_session_availability WHERE course_id = ? AND session_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $newCourseId, $newSessionId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0 && ($oldCourseId != $newCourseId || $oldSessionId != $newSessionId)) {
                    echo "<script>alert('This course is already offered in the selected session!');</script>";
                } else {
                    // Delete old record and insert new one (since it's a composite key table)
                    $deleteSql = "DELETE FROM course_session_availability WHERE course_id = ? AND session_id = ?";
                    $deleteStmt = $conn->prepare($deleteSql);
                    if ($deleteStmt) {
                        $deleteStmt->bind_param("ii", $oldCourseId, $oldSessionId);
                        if ($deleteStmt->execute()) {
                            // Insert new record
                            $insertSql = "INSERT INTO course_session_availability (course_id, session_id) VALUES (?, ?)";
                            $insertStmt = $conn->prepare($insertSql);
                            if ($insertStmt) {
                                $insertStmt->bind_param("ii", $newCourseId, $newSessionId);
                                if ($insertStmt->execute()) {
                                    echo "<script>alert('Availability updated successfully!'); window.location.href='course_session_availability.php';</script>";
                                } else {
                                    echo "Error: " . $insertStmt->error;
                                }
                                $insertStmt->close();
                            }
                        } else {
                            echo "Error: " . $deleteStmt->error;
                        }
                        $deleteStmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'delete':
                // Delete course-session availability
                $courseId = $_POST['courseId'];
                $sessionId = $_POST['sessionId'];
                
                // Check if assignment has dependent records
                $checkSql = "SELECT 
                    (SELECT COUNT(*) FROM timetable WHERE course_id = ? AND session_id = ?) as timetable_count,
                    (SELECT COUNT(*) FROM class_courses WHERE course_id = ?) as class_course_count";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("iii", $courseId, $sessionId, $courseId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $dependencies = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($dependencies['timetable_count'] > 0 || $dependencies['class_course_count'] > 0) {
                    echo "<script>alert('Cannot delete availability: It has dependent timetables or class-course assignments. Consider removing timetables first.');</script>";
                } else {
                    $sql = "DELETE FROM course_session_availability WHERE course_id = ? AND session_id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ii", $courseId, $sessionId);
                        if ($stmt->execute()) {
                            echo "<script>alert('Availability removed successfully!'); window.location.href='course_session_availability.php';</script>";
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

// Fetch existing course-session assignments for display
$courseSessions = [];
$sql = "SELECT csa.*, c.code as course_code, c.name as course_name, c.credits, c.hours_per_week, c.level as course_level,
               CONCAT(s.academic_year, ' - Semester ', s.semester) as session_name, 
               'Regular' as session_type, s.start_date, s.end_date,
               d.name as department_name, d.code as department_code
        FROM course_session_availability csa 
        JOIN courses c ON csa.course_id = c.id 
        JOIN sessions s ON csa.session_id = s.id 
        JOIN departments d ON c.department_id = d.id
        ORDER BY c.code, s.academic_year, s.semester";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courseSessions[] = $row;
    }
}

// Fetch courses for dropdown
$courses = [];
$sql = "SELECT c.id, c.code, c.name, c.credits, c.hours_per_week, c.level, c.preferred_room_type,
               d.name as department_name, d.code as department_code 
        FROM courses c 
        JOIN departments d ON c.department_id = d.id 
        WHERE c.is_active = 1 
        ORDER BY c.code";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Fetch sessions for dropdown
$sessions = [];
$sql = "SELECT id, CONCAT(academic_year, ' - Semester ', semester) as name, 
               'Regular' as type, start_date as start_time, end_date as end_time 
        FROM sessions WHERE is_active = 1 ORDER BY academic_year, semester";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
}

// Get statistics
$totalAssignments = count($courseSessions);
$uniqueCourses = count(array_unique(array_column($courseSessions, 'course_id')));
$uniqueSessions = count(array_unique(array_column($courseSessions, 'session_id')));

// Group by session type for better analysis
$sessionTypeCounts = [];
foreach ($courseSessions as $assignment) {
    $type = $assignment['session_type'];
    if (!isset($sessionTypeCounts[$type])) {
        $sessionTypeCounts[$type] = 0;
    }
    $sessionTypeCounts[$type]++;
}

// Group by course level for better analysis
$courseLevelCounts = [];
foreach ($courseSessions as $assignment) {
    $level = $assignment['course_level'];
    if (!isset($courseLevelCounts[$level])) {
        $courseLevelCounts[$level] = 0;
    }
    $courseLevelCounts[$level]++;
}

// Group by department for better analysis
$departmentCounts = [];
foreach ($courseSessions as $assignment) {
    $dept = $assignment['department_code'];
    if (!isset($departmentCounts[$dept])) {
        $departmentCounts[$dept] = 0;
    }
    $departmentCounts[$dept]++;
}
?>
<?php $pageTitle = 'Course Session Availability'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

  <div class="main-content" id="mainContent">
        <h2>Manage Course Session Availability</h2>
        
        <!-- Search Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="input-group" style="width: 300px;">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" id="searchInput" placeholder="Search availability...">
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#dataEntryModal">Add New Availability</button>
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
                        <h4 class="mb-0"><?php echo $uniqueCourses; ?></h4>
                        <small>Courses Offered</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $uniqueSessions; ?></h4>
                        <small>Sessions Covered</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo count($sessionTypeCounts); ?></h4>
                        <small>Session Types</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analysis Breakdowns -->
        <div class="row mb-4">
            <!-- Session Type Distribution -->
            <?php if (!empty($sessionTypeCounts)): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Session Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($sessionTypeCounts as $type => $count): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="card bg-light">
                                        <div class="card-body text-center p-2">
                                            <h6 class="mb-0"><?php echo ucfirst($type); ?></h6>
                                            <small class="text-muted"><?php echo $count; ?> courses</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Course Level Distribution -->
            <?php if (!empty($courseLevelCounts)): ?>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Course Level Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($courseLevelCounts as $level => $count): ?>
                                <div class="col-md-4 mb-2">
                                    <div class="card bg-light">
                                        <div class="card-body text-center p-2">
                                            <h6 class="mb-0">Level <?php echo $level; ?></h6>
                                            <small class="text-muted"><?php echo $count; ?> courses</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Department Distribution -->
        <?php if (!empty($departmentCounts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Department Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($departmentCounts as $dept => $count): ?>
                                <div class="col-md-2 mb-2">
                                    <div class="card bg-light">
                                        <div class="card-body text-center p-2">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($dept); ?></h6>
                                            <small class="text-muted"><?php echo $count; ?> courses</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Course-Session Assignment Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 id="formHeader">Assign Course to Session</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="courseSessionForm">
                            <input type="hidden" id="action" name="action" value="add">
                            <input type="hidden" id="oldCourseId" name="oldCourseId" value="">
                            <input type="hidden" id="oldSessionId" name="oldSessionId" value="">
                            
                            <div class="alert alert-info" id="formMode" style="display: none;">
                                <strong>Edit Mode:</strong> You are currently editing an existing assignment. Click "Cancel Edit" to return to add mode.
                            </div>
                            <div class="mb-3">
                                <label for="courseId" class="form-label">Course *</label>
                                <select class="form-select" id="courseId" name="courseId" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['code']); ?> - <?php echo htmlspecialchars($course['name']); ?><br>
                                            <small class="text-muted">
                                                <?php echo $course['credits']; ?> credits, Level <?php echo $course['level']; ?>, <?php echo $course['hours_per_week']; ?> hrs/week<br>
                                                <?php echo htmlspecialchars($course['department_code']); ?> - <?php echo htmlspecialchars($course['department_name']); ?><br>
                                                Room: <?php echo ucfirst(str_replace('_', ' ', $course['preferred_room_type'])); ?>
                                            </small>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the course to offer in a session</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sessionId" class="form-label">Session *</label>
                                <select class="form-select" id="sessionId" name="sessionId" required>
                                    <option value="">Select Session</option>
                                    <?php foreach ($sessions as $session): ?>
                                        <option value="<?php echo $session['id']; ?>">
                                            <?php echo htmlspecialchars($session['name']); ?> (<?php echo ucfirst($session['type']); ?>)<br>
                                            <small class="text-muted">
                                                <?php echo date('g:i A', strtotime($session['start_time'])); ?> - <?php echo date('g:i A', strtotime($session['end_time'])); ?>
                                            </small>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the session this course will be offered in</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitBtn">Assign Course to Session</button>
                                <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;" onclick="cancelEdit()">Cancel Edit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Display Course-Session Assignments -->
            <div class="col-md-6">
                <div class="card">
                    <div class="table-header">
                        <h4><i class="fas fa-link me-2"></i>Existing Assignments</h4>
                    </div>
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search assignments...">
                    </div>
                    <?php if (empty($courseSessions)): ?>
                            <div class="empty-state">
                                <i class="fas fa-link"></i>
                                <h5>No session assignments found</h5>
                                <p>Start by assigning courses to sessions using the form on the left.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table" id="courseSessionTable">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Session</th>
                                            <th>Details</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courseSessions as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignment['course_code']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['course_name']); ?></small><br>
                                                    <span class="badge bg-secondary">Level <?php echo $assignment['course_level']; ?></span>
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($assignment['department_code']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($assignment['session_name']); ?></span><br>
                                                    <small class="text-muted"><?php echo ucfirst($assignment['session_type']); ?></small><br>
                                                    <small class="text-muted">
                                                        <?php echo date('g:i A', strtotime($assignment['start_time'])); ?> - <?php echo date('g:i A', strtotime($assignment['end_time'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong>Credits:</strong> <?php echo $assignment['credits']; ?><br>
                                                        <strong>Hours/Week:</strong> <?php echo $assignment['hours_per_week']; ?><br>
                                                        <strong>Department:</strong> <?php echo htmlspecialchars($assignment['department_name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?php echo $assignment['course_id']; ?>, <?php echo $assignment['session_id']; ?>, '<?php echo htmlspecialchars($assignment['course_code']); ?>', '<?php echo htmlspecialchars($assignment['session_name']); ?>')">Edit</button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAssignment(<?php echo $assignment['course_id']; ?>, <?php echo $assignment['session_id']; ?>, '<?php echo htmlspecialchars($assignment['course_code']); ?> - <?php echo htmlspecialchars($assignment['session_name']); ?>')">Delete</button>
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
    
<?php include 'includes/footer.php'; ?>

    <script>
        function editAssignment(courseId, sessionId, courseCode, sessionName) {
            // Set form to edit mode
            document.getElementById('action').value = 'update';
            document.getElementById('oldCourseId').value = courseId;
            document.getElementById('oldSessionId').value = sessionId;
            document.getElementById('courseId').value = courseId;
            document.getElementById('sessionId').value = sessionId;
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Edit Assignment';
            document.getElementById('submitBtn').textContent = 'Update Assignment';
            document.getElementById('submitBtn').className = 'btn btn-warning';
            document.getElementById('cancelBtn').style.display = 'block';
            document.getElementById('formMode').style.display = 'block';
            
            // Scroll to form
            document.getElementById('courseSessionForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function cancelEdit() {
            // Reset form to add mode
            document.getElementById('action').value = 'add';
            document.getElementById('oldCourseId').value = '';
            document.getElementById('oldSessionId').value = '';
            document.getElementById('courseSessionForm').reset();
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Assign Course to Session';
            document.getElementById('submitBtn').textContent = 'Assign Course to Session';
            document.getElementById('submitBtn').className = 'btn btn-primary';
            document.getElementById('cancelBtn').style.display = 'none';
            document.getElementById('formMode').style.display = 'none';
        }
        
        function deleteAssignment(courseId, sessionId, assignmentName) {
            if (confirm(`Are you sure you want to delete the assignment "${assignmentName}"?`)) {
                // Create a form to submit the deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const courseInput = document.createElement('input');
                courseInput.type = 'hidden';
                courseInput.name = 'courseId';
                courseInput.value = courseId;
                
                const sessionInput = document.createElement('input');
                sessionInput.type = 'hidden';
                sessionInput.name = 'sessionId';
                sessionInput.value = sessionId;
                
                form.appendChild(actionInput);
                form.appendChild(courseInput);
                form.appendChild(sessionInput);
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
        
        // Search functionality for course session table
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let searchValue = this.value.toLowerCase();
            let rows = document.querySelectorAll('#courseSessionTable tbody tr');
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

