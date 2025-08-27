<?php
include 'connect.php'; // Include database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new co-lecturer assignment
                $timetableId = $_POST['timetableId'];
                $lecturerId = $_POST['lecturerId'];

                // Check if this assignment already exists
                $checkSql = "SELECT id FROM timetable_lecturers WHERE timetable_id = ? AND lecturer_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("ii", $timetableId, $lecturerId);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('This lecturer is already assigned to this timetable entry!');</script>";
                } else {
                    // Validate that the lecturer can teach the course in this timetable entry
                    $validationSql = "SELECT t.course_id, t.session_id, lc.lecturer_id, lsa.lecturer_id as session_available
                                     FROM timetable t
                                     LEFT JOIN lecturer_courses lc ON lc.lecturer_id = ? AND lc.course_id = t.course_id AND lc.is_active = 1
                                     LEFT JOIN lecturer_session_availability lsa ON lsa.lecturer_id = ? AND lsa.session_id = t.session_id
                                     WHERE t.id = ?";
                    $validationStmt = $conn->prepare($validationSql);
                    $validationStmt->bind_param("iii", $lecturerId, $lecturerId, $timetableId);
                    $validationStmt->execute();
                    $validationResult = $validationStmt->get_result();
                    
                    if ($validationResult->num_rows == 0) {
                        echo "<script>alert('Cannot find timetable entry for validation!');</script>";
                    } else {
                        $validationData = $validationResult->fetch_assoc();
                        
                        if (!$validationData['lecturer_id']) {
                            echo "<script>alert('This lecturer is not assigned to teach this course!');</script>";
                        } elseif (!$validationData['session_available']) {
                            echo "<script>alert('This lecturer is not available in the session of this timetable entry!');</script>";
                        } else {
                            // All validations passed, insert the timetable lecturer entry
                            $sql = "INSERT INTO timetable_lecturers (timetable_id, lecturer_id) VALUES (?, ?)";
                            
                            $stmt = $conn->prepare($sql);
                            if ($stmt) {
                                $stmt->bind_param("ii", $timetableId, $lecturerId);
                                
                                if ($stmt->execute()) {
                                    echo "<script>alert('Co-lecturer assigned successfully!'); window.location.href='timetable_lecturers.php';</script>";
                                } else {
                                    echo "Error: " . $stmt->error;
                                }
                                $stmt->close();
                            }
                        }
                    }
                    $validationStmt->close();
                }
                $checkStmt->close();
                break;

            case 'update':
                // Update existing co-lecturer assignment
                $id = $_POST['id'];
                $timetableId = $_POST['timetableId'];
                $lecturerId = $_POST['lecturerId'];

                // Check if the new combination already exists (excluding current assignment)
                $checkSql = "SELECT id FROM timetable_lecturers WHERE timetable_id = ? AND lecturer_id = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("iii", $timetableId, $lecturerId, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('This lecturer is already assigned to this timetable entry!');</script>";
                } else {
                    // Validate that the lecturer can teach the course in this timetable entry
                    $validationSql = "SELECT t.course_id, t.session_id, lc.lecturer_id, lsa.lecturer_id as session_available
                                     FROM timetable t
                                     LEFT JOIN lecturer_courses lc ON lc.lecturer_id = ? AND lc.course_id = t.course_id AND lc.is_active = 1
                                     LEFT JOIN lecturer_session_availability lsa ON lsa.lecturer_id = ? AND lsa.session_id = t.session_id
                                     WHERE t.id = ?";
                    $validationStmt = $conn->prepare($validationSql);
                    $validationStmt->bind_param("iii", $lecturerId, $lecturerId, $timetableId);
                    $validationStmt->execute();
                    $validationResult = $validationStmt->get_result();
                    
                    if ($validationResult->num_rows == 0) {
                        echo "<script>alert('Cannot find timetable entry for validation!');</script>";
                    } else {
                        $validationData = $validationResult->fetch_assoc();
                        
                        if (!$validationData['lecturer_id']) {
                            echo "<script>alert('This lecturer is not assigned to teach this course!');</script>";
                        } elseif (!$validationData['session_available']) {
                            echo "<script>alert('This lecturer is not available in the session of this timetable entry!');</script>";
                        } else {
                            // All validations passed, update the assignment
                            $sql = "UPDATE timetable_lecturers SET timetable_id = ?, lecturer_id = ? WHERE id = ?";
                            
                            $stmt = $conn->prepare($sql);
                            if ($stmt) {
                                $stmt->bind_param("iii", $timetableId, $lecturerId, $id);
                                
                                if ($stmt->execute()) {
                                    echo "<script>alert('Co-lecturer assignment updated successfully!'); window.location.href='timetable_lecturers.php';</script>";
                                } else {
                                    echo "Error: " . $stmt->error;
                                }
                                $stmt->close();
                            }
                        }
                    }
                    $validationStmt->close();
                }
                $checkStmt->close();
                break;

            case 'delete':
                // Delete co-lecturer assignment
                $id = $_POST['id'];
                
                $sql = "DELETE FROM timetable_lecturers WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        echo "<script>alert('Co-lecturer assignment removed successfully!'); window.location.href='timetable_lecturers.php';</script>";
                    } else {
                        echo "Error: " . $stmt->error;
                    }
                    $stmt->close();
                }
                break;
        }
    }
}

// Fetch existing timetable lecturer assignments for display
$timetableLecturers = [];
$sql = "SELECT tl.*, d.name as day_name,
               c.name as class_name, co.code as course_code, co.name as course_name,
               l.name as lecturer_name,
               ts.start_time, ts.end_time, 
               CONCAT(s.academic_year, ' - Semester ', s.semester) as session_name,
               dept.name as department_name, dept.code as department_code,
               r.name as room_name, r.building
        FROM timetable_lecturers tl
        JOIN timetable t ON tl.timetable_id = t.id
        JOIN class_courses cc ON t.class_course_id = cc.id
        JOIN classes c ON cc.class_id = c.id
        JOIN courses co ON cc.course_id = co.id
        JOIN lecturers l ON tl.lecturer_id = l.id
        JOIN time_slots ts ON t.time_slot_id = ts.id
        JOIN sessions s ON t.session_id = s.id
        JOIN days d ON t.day_id = d.id
        JOIN departments dept ON c.department_id = dept.id
        JOIN rooms r ON t.room_id = r.id
        ORDER BY d.name, ts.start_time, s.semester, s.academic_year, l.name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $timetableLecturers[] = $row;
    }
}

// Fetch timetable entries for dropdown (only confirmed ones to avoid confusion)
$timetableEntries = [];
$sql = "SELECT t.id, d.name as day_name, 
               c.name as class_name, co.code as course_code, co.name as course_name,
               ts.start_time, ts.end_time, 
               CONCAT(s.academic_year, ' - Semester ', s.semester) as session_name,
               r.name as room_name, r.building
        FROM timetable t
        JOIN class_courses cc ON t.class_course_id = cc.id
        JOIN classes c ON cc.class_id = c.id
        JOIN courses co ON cc.course_id = co.id
        JOIN time_slots ts ON t.time_slot_id = ts.id
        JOIN sessions s ON t.session_id = s.id
        JOIN days d ON t.day_id = d.id
        JOIN rooms r ON t.room_id = r.id
        ORDER BY d.name, ts.start_time, s.semester, s.academic_year";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $timetableEntries[] = $row;
    }
}

// Fetch lecturers for dropdown (only active ones)
$lecturers = [];
$sql = "SELECT l.id, l.name, l.rank,
               d.name as department_name, d.code as department_code
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

// Get statistics
$totalAssignments = count($timetableLecturers);
$uniqueTimetables = count(array_unique(array_column($timetableLecturers, 'timetable_id')));
$uniqueLecturers = count(array_unique(array_column($timetableLecturers, 'lecturer_id')));

// Group by day for better analysis
$dayCounts = [];
foreach ($timetableLecturers as $assignment) {
    $day = $assignment['day_name'];
    if (!isset($dayCounts[$day])) {
        $dayCounts[$day] = 0;
    }
    $dayCounts[$day]++;
}

// Group by session type for better analysis
$sessionTypeCounts = [];
foreach ($timetableLecturers as $assignment) {
    $type = 'Regular'; // Since we're using 'Regular' as default session type
    if (!isset($sessionTypeCounts[$type])) {
        $sessionTypeCounts[$type] = 0;
    }
    $sessionTypeCounts[$type]++;
}

// Group by activity type for better analysis (placeholder - activity_type not in current data)
$activityTypeCounts = [];
?>

<?php $pageTitle = 'Co-Teaching Assignments'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

  <div class="main-content" id="mainContent">
    <div class="container mt-0">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $totalAssignments; ?></h4>
                        <small>Total Co-Teaching Assignments</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $uniqueTimetables; ?></h4>
                        <small>Classes with Co-Teachers</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $uniqueLecturers; ?></h4>
                        <small>Co-Teaching Lecturers</small>
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
            <!-- Activity Type Distribution -->
            <?php if (!empty($activityTypeCounts)): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Activity Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($activityTypeCounts as $type => $count): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="card bg-light">
                                        <div class="card-body text-center p-2">
                                            <h6 class="mb-0"><?php echo ucfirst($type); ?></h6>
                                            <small class="text-muted"><?php echo $count; ?> assignments</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Session Type Distribution -->
            <?php if (!empty($sessionTypeCounts)): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Session Type Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($sessionTypeCounts as $type => $count): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="card bg-light">
                                        <div class="card-body text-center p-2">
                                            <h6 class="mb-0"><?php echo ucfirst($type); ?></h6>
                                            <small class="text-muted"><?php echo $count; ?> assignments</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Day Distribution -->
            <?php if (!empty($dayCounts)): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Daily Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($dayCounts as $day => $count): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="card bg-light">
                                        <div class="card-body text-center p-2">
                                            <h6 class="mb-0"><?php echo ucfirst($day); ?></h6>
                                            <small class="text-muted"><?php echo $count; ?> assignments</small>
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

        <div class="row">
            <!-- Add Co-Lecturer Assignment Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 id="formHeader">Assign Co-Lecturer</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="timetableLecturerForm">
                            <input type="hidden" id="action" name="action" value="add">
                            <input type="hidden" id="assignmentId" name="id" value="">
                            
                            <div class="alert alert-info" id="formMode" style="display: none;">
                                <strong>Edit Mode:</strong> You are currently editing an existing co-lecturer assignment. Click "Cancel Edit" to return to add mode.
                            </div>
                            <div class="mb-3">
                                <label for="timetableId" class="form-label">Timetable Entry *</label>
                                <select class="form-select" id="timetableId" name="timetableId" required>
                                    <option value="">Select Timetable Entry</option>
                                    <?php foreach ($timetableEntries as $entry): ?>
                                        <option value="<?php echo $entry['id']; ?>">
                                            <?php echo htmlspecialchars($entry['class_name']); ?> - <?php echo htmlspecialchars($entry['course_code']); ?><br>
                                            <small class="text-muted">
                                                <?php echo ucfirst($entry['day']); ?>, <?php echo date('g:i A', strtotime($entry['start_time'])); ?> - <?php echo date('g:i A', strtotime($entry['end_time'])); ?><br>
                                                <?php echo htmlspecialchars($entry['session_name']); ?> (<?php echo ucfirst($entry['session_type']); ?>) - <?php echo htmlspecialchars($entry['semester_name']); ?><br>
                                                <?php echo htmlspecialchars($entry['room_name']); ?> (<?php echo htmlspecialchars($entry['building']); ?>)<br>
                                                Main Lecturer: <?php echo htmlspecialchars($entry['main_lecturer_name']); ?><br>
                                                Activity: <?php echo ucfirst($entry['activity_type']); ?>
                                            </small>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the timetable entry to add a co-lecturer to</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="lecturerId" class="form-label">Co-Lecturer *</label>
                                <select class="form-select" id="lecturerId" name="lecturerId" required>
                                    <option value="">Select Co-Lecturer</option>
                                    <?php foreach ($lecturers as $lecturer): ?>
                                        <option value="<?php echo $lecturer['id']; ?>">
                                            <?php echo htmlspecialchars($lecturer['name']); ?>
                                            <?php if ($lecturer['specialization']): ?>
                                                (<?php echo htmlspecialchars($lecturer['specialization']); ?>)
                                            <?php endif; ?><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($lecturer['department_code']); ?> - <?php echo htmlspecialchars($lecturer['department_name']); ?><br>
                                                Department: <?php echo htmlspecialchars($lecturer['department_name']); ?>
                                            </small>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the co-lecturer to assign</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitBtn">Assign Co-Lecturer</button>
                                <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;" onclick="cancelEdit()">Cancel Edit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Display Co-Teaching Assignments -->
            <div class="col-md-6">
                <div class="card">
                    <div class="table-header">
                        <h4><i class="fas fa-users-cog me-2"></i>Existing Co-Teaching Assignments</h4>
                    </div>
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search assignments...">
                    </div>
                    <?php if (empty($timetableLecturers)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h5>No co-teaching assignments found</h5>
                            <p>Start by assigning co-lecturers to classes using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Class & Course</th>
                                            <th>Schedule</th>
                                            <th>Co-Lecturer</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($timetableLecturers as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignment['class_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['course_code']); ?> - <?php echo htmlspecialchars($assignment['course_name']); ?></small><br>
                                                    <span class="badge bg-info"><?php echo ucfirst($assignment['activity_type']); ?></span>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($assignment['department_code']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo ucfirst($assignment['day']); ?></strong><br>
                                                        <?php echo date('g:i A', strtotime($assignment['start_time'])); ?> - <?php echo date('g:i A', strtotime($assignment['end_time'])); ?><br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($assignment['room_name']); ?> (<?php echo htmlspecialchars($assignment['building']); ?>)<br>
                                                            <?php echo htmlspecialchars($assignment['session_name']); ?> - <?php echo htmlspecialchars($assignment['semester_name']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignment['lecturer_name']); ?></strong><br>
                                                    <?php if ($assignment['specialization']): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($assignment['specialization']); ?></small><br>
                                                    <?php endif; ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['department_name']); ?></small>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editAssignment(<?php echo $assignment['id']; ?>, <?php echo $assignment['timetable_id']; ?>, <?php echo $assignment['lecturer_id']; ?>, '<?php echo htmlspecialchars($assignment['class_name']); ?> - <?php echo htmlspecialchars($assignment['course_code']); ?>', '<?php echo htmlspecialchars($assignment['lecturer_name']); ?>')">Edit</button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteAssignment(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['class_name']); ?> - <?php echo htmlspecialchars($assignment['course_code']); ?> (<?php echo htmlspecialchars($assignment['lecturer_name']); ?>)')">Delete</button>
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
  </div>

<?php include 'includes/footer.php'; ?>
  <script>
    // Update current time in header
    function updateTime() {
      const now = new Date();
      const timeString = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
      const el = document.getElementById('currentTime'); if(el) el.textContent = timeString;
    }
    setInterval(updateTime, 1000); updateTime();
    // Toggle sidebar
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const footer = document.getElementById('footer');
        sidebar.classList.toggle('show');
        if (sidebar.classList.contains('show')) {
          mainContent.classList.add('shift'); footer.classList.add('shift');
        } else { mainContent.classList.remove('shift'); footer.classList.remove('shift'); }
      });
    }
    // Dashboard grid search (only if element exists)
    const dashboardSearchInput = document.getElementById('dashboardSearchInput');
    if (dashboardSearchInput) {
      dashboardSearchInput.addEventListener('keyup', function() {
        const searchValue = this.value.toLowerCase();
        const gridButtons = document.querySelectorAll('#dashboardGrid .grid-button');
        gridButtons.forEach(button => {
          const text = button.textContent.toLowerCase();
          button.parentElement.style.display = text.includes(searchValue) ? '' : 'none';
        });
      });
    }
    // Back to Top Button
    const backToTopButton = document.getElementById('backToTop');
    const progressCircle = document.getElementById('progressCircle');
    if (progressCircle) {
      const circumference = 2 * Math.PI * 20;
      progressCircle.style.strokeDasharray = circumference;
      progressCircle.style.strokeDashoffset = circumference;
      window.addEventListener('scroll', function() {
        const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
        if (backToTopButton) backToTopButton.style.display = scrollTop > 100 ? 'block' : 'none';
        const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        const scrollPercentage = scrollTop / (scrollHeight || 1);
        const offset = circumference - (scrollPercentage * circumference);
        progressCircle.style.strokeDashoffset = offset;
      });
      if (backToTopButton) backToTopButton.addEventListener('click', function() { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    }
  </script>

  <script>
        function editAssignment(id, timetableId, lecturerId, assignmentName, lecturerName) {
            // Set form to edit mode
            document.getElementById('action').value = 'update';
            document.getElementById('assignmentId').value = id;
            document.getElementById('timetableId').value = timetableId;
            document.getElementById('lecturerId').value = lecturerId;
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Edit Co-Lecturer Assignment';
            document.getElementById('submitBtn').textContent = 'Update Assignment';
            document.getElementById('submitBtn').className = 'btn btn-warning';
            document.getElementById('cancelBtn').style.display = 'block';
            document.getElementById('formMode').style.display = 'block';
            
            // Scroll to form
            document.getElementById('timetableLecturerForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        function cancelEdit() {
            // Reset form to add mode
            document.getElementById('action').value = 'add';
            document.getElementById('assignmentId').value = '';
            document.getElementById('timetableLecturerForm').reset();
            
            // Update form appearance
            document.getElementById('formHeader').textContent = 'Assign Co-Lecturer';
            document.getElementById('submitBtn').textContent = 'Assign Co-Lecturer';
            document.getElementById('submitBtn').className = 'btn btn-primary';
            document.getElementById('cancelBtn').style.display = 'none';
            document.getElementById('formMode').style.display = 'none';
        }
        
        function deleteAssignment(assignmentId, assignmentName) {
            if (confirm(`Are you sure you want to delete the co-lecturer assignment "${assignmentName}"?`)) {
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
                idInput.value = assignmentId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
