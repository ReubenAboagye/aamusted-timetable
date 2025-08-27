<?php
include 'connect.php'; // Include database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new timetable entry
                $classId = $_POST['classId'];
                $courseId = $_POST['courseId'];
                $lecturerId = $_POST['lecturerId'];
                $roomId = $_POST['roomId'];
                $timeSlotId = $_POST['timeSlotId'];
                $day = $_POST['day'];
                $semesterId = $_POST['semesterId'];
                $sessionId = $_POST['sessionId'];
                $activityType = $_POST['activityType'];
                $notes = $_POST['notes'];

                // Validate that the class is actually taking this course in this semester
                $classCourseCheck = "SELECT id FROM class_courses WHERE class_id = ? AND course_id = ? AND semester_id = ? AND is_active = 1";
                $classCourseStmt = $conn->prepare($classCourseCheck);
                $classCourseStmt->bind_param("iii", $classId, $courseId, $semesterId);
                $classCourseStmt->execute();
                $classCourseResult = $classCourseStmt->get_result();
                
                if ($classCourseResult->num_rows == 0) {
                    echo "<script>alert('This class is not taking this course in the selected semester!');</script>";
                    $classCourseStmt->close();
                    break;
                }
                $classCourseStmt->close();

                // Validate that the lecturer can teach this course
                $lecturerCourseCheck = "SELECT id FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ? AND is_active = 1";
                $lecturerCourseStmt = $conn->prepare($lecturerCourseCheck);
                $lecturerCourseStmt->bind_param("ii", $lecturerId, $courseId);
                $lecturerCourseStmt->execute();
                $lecturerCourseResult = $lecturerCourseStmt->get_result();
                
                if ($lecturerCourseResult->num_rows == 0) {
                    echo "<script>alert('This lecturer is not assigned to teach this course!');</script>";
                    $lecturerCourseStmt->close();
                    break;
                }
                $lecturerCourseStmt->close();

                // Validate that the lecturer is available in this session
                $lecturerSessionCheck = "SELECT lecturer_id FROM lecturer_session_availability WHERE lecturer_id = ? AND session_id = ?";
                $lecturerSessionStmt = $conn->prepare($lecturerSessionCheck);
                $lecturerSessionStmt->bind_param("ii", $lecturerId, $sessionId);
                $lecturerSessionStmt->execute();
                $lecturerSessionResult = $lecturerSessionStmt->get_result();
                
                if ($lecturerSessionResult->num_rows == 0) {
                    echo "<script>alert('This lecturer is not available in the selected session!');</script>";
                    $lecturerSessionStmt->close();
                    break;
                }
                $lecturerSessionStmt->close();

                // Validate that the course is offered in this session
                $courseSessionCheck = "SELECT course_id FROM course_session_availability WHERE course_id = ? AND session_id = ?";
                $courseSessionStmt = $conn->prepare($courseSessionCheck);
                $courseSessionStmt->bind_param("ii", $courseId, $sessionId);
                $courseSessionStmt->execute();
                $courseSessionResult = $courseSessionStmt->get_result();
                
                if ($courseSessionResult->num_rows == 0) {
                    echo "<script>alert('This course is not offered in the selected session!');</script>";
                    $courseSessionStmt->close();
                    break;
                }
                $courseSessionStmt->close();

                // Validate that the class belongs to this session
                $classSessionCheck = "SELECT id FROM classes WHERE id = ? AND session_id = ? AND is_active = 1";
                $classSessionStmt = $conn->prepare($classSessionCheck);
                $classSessionStmt->bind_param("ii", $classId, $sessionId);
                $classSessionStmt->execute();
                $classSessionResult = $classSessionStmt->get_result();
                
                if ($classSessionResult->num_rows == 0) {
                    echo "<script>alert('This class does not belong to the selected session!');</script>";
                    $classSessionStmt->close();
                    break;
                }
                $classSessionStmt->close();

                // Check for conflicts (class, room, lecturer cannot collide in same day+slot+semester+session)
                $conflictCheck = "SELECT id FROM timetable WHERE 
                                  (class_id = ? OR room_id = ? OR lecturer_id = ?) 
                                  AND day = ? AND time_slot_id = ? AND semester_id = ? AND session_id = ?";
                $conflictStmt = $conn->prepare($conflictCheck);
                $conflictStmt->bind_param("iiisiii", $classId, $roomId, $lecturerId, $day, $timeSlotId, $semesterId, $sessionId);
                $conflictStmt->execute();
                $conflictResult = $conflictStmt->get_result();
                
                if ($conflictResult->num_rows > 0) {
                    echo "<script>alert('Conflict detected! Class, room, or lecturer is already scheduled at this time.');</script>";
                    $conflictStmt->close();
                    break;
                }
                $conflictStmt->close();

                // Check if this is a break time slot
                $breakCheck = "SELECT is_break FROM time_slots WHERE id = ?";
                $breakStmt = $conn->prepare($breakCheck);
                $breakStmt->bind_param("i", $timeSlotId);
                $breakStmt->execute();
                $breakResult = $breakStmt->get_result();
                $breakData = $breakResult->fetch_assoc();
                
                if ($breakData['is_break'] == 1) {
                    echo "<script>alert('Cannot schedule classes during break time slots!');</script>";
                    $breakStmt->close();
                    break;
                }
                $breakStmt->close();

                // All validations passed, insert the timetable entry
                $sql = "INSERT INTO timetable (class_id, course_id, lecturer_id, room_id, time_slot_id, day, semester_id, session_id, activity_type, notes, is_confirmed) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("iiiiississ", $classId, $courseId, $lecturerId, $roomId, $timeSlotId, $day, $semesterId, $sessionId, $activityType, $notes);
                    
                    if ($stmt->execute()) {
                        $timetableId = $conn->insert_id;
                        
                        // Insert into timetable_lecturers for co-teaching support
                        $lecturerInsert = "INSERT INTO timetable_lecturers (timetable_id, lecturer_id) VALUES (?, ?)";
                        $lecturerStmt = $conn->prepare($lecturerInsert);
                        $lecturerStmt->bind_param("ii", $timetableId, $lecturerId);
                        $lecturerStmt->execute();
                        $lecturerStmt->close();
                        
                        echo "<script>alert('Class scheduled successfully!'); window.location.href='timetable.php';</script>";
                    } else {
                        echo "Error: " . $stmt->error;
                    }
                    $stmt->close();
                }
                break;

            case 'update':
                // Update existing timetable entry
                $id = $_POST['id'];
                $classId = $_POST['classId'];
                $courseId = $_POST['courseId'];
                $lecturerId = $_POST['lecturerId'];
                $roomId = $_POST['roomId'];
                $timeSlotId = $_POST['timeSlotId'];
                $day = $_POST['day'];
                $semesterId = $_POST['semesterId'];
                $sessionId = $_POST['sessionId'];
                $activityType = $_POST['activityType'];
                $notes = $_POST['notes'];
                $isConfirmed = isset($_POST['isConfirmed']) ? 1 : 0;

                // Validate that the class is actually taking this course in this semester
                $classCourseCheck = "SELECT id FROM class_courses WHERE class_id = ? AND course_id = ? AND semester_id = ? AND is_active = 1";
                $classCourseStmt = $conn->prepare($classCourseCheck);
                $classCourseStmt->bind_param("iii", $classId, $courseId, $semesterId);
                $classCourseStmt->execute();
                $classCourseResult = $classCourseStmt->get_result();
                
                if ($classCourseResult->num_rows == 0) {
                    echo "<script>alert('This class is not taking this course in the selected semester!');</script>";
                    $classCourseStmt->close();
                    break;
                }
                $classCourseStmt->close();

                // Validate that the lecturer can teach this course
                $lecturerCourseCheck = "SELECT id FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ? AND is_active = 1";
                $lecturerCourseStmt = $conn->prepare($lecturerCourseCheck);
                $lecturerCourseStmt->bind_param("ii", $lecturerId, $courseId);
                $lecturerCourseStmt->execute();
                $lecturerCourseResult = $lecturerCourseStmt->get_result();
                
                if ($lecturerCourseResult->num_rows == 0) {
                    echo "<script>alert('This lecturer is not assigned to teach this course!');</script>";
                    $lecturerCourseStmt->close();
                    break;
                }
                $lecturerCourseStmt->close();

                // Validate that the lecturer is available in this session
                $lecturerSessionCheck = "SELECT lecturer_id FROM lecturer_session_availability WHERE lecturer_id = ? AND session_id = ?";
                $lecturerSessionStmt = $conn->prepare($lecturerSessionCheck);
                $lecturerSessionStmt->bind_param("ii", $lecturerId, $sessionId);
                $lecturerSessionStmt->execute();
                $lecturerSessionResult = $lecturerSessionStmt->get_result();
                
                if ($lecturerSessionResult->num_rows == 0) {
                    echo "<script>alert('This lecturer is not available in the selected session!');</script>";
                    $lecturerSessionStmt->close();
                    break;
                }
                $lecturerSessionStmt->close();

                // Validate that the course is offered in this session
                $courseSessionCheck = "SELECT course_id FROM course_session_availability WHERE course_id = ? AND session_id = ?";
                $courseSessionStmt = $conn->prepare($courseSessionCheck);
                $courseSessionStmt->bind_param("ii", $courseId, $sessionId);
                $courseSessionStmt->execute();
                $courseSessionResult = $courseSessionStmt->get_result();
                
                if ($courseSessionResult->num_rows == 0) {
                    echo "<script>alert('This course is not offered in the selected session!');</script>";
                    $courseSessionStmt->close();
                    break;
                }
                $courseSessionStmt->close();

                // Validate that the class belongs to this session
                $classSessionCheck = "SELECT id FROM classes WHERE id = ? AND session_id = ? AND is_active = 1";
                $classSessionStmt = $conn->prepare($classSessionCheck);
                $classSessionStmt->bind_param("ii", $classId, $sessionId);
                $classSessionStmt->execute();
                $classSessionResult = $classSessionStmt->get_result();
                
                if ($classSessionResult->num_rows == 0) {
                    echo "<script>alert('This class does not belong to the selected session!');</script>";
                    $classSessionStmt->close();
                    break;
                }
                $classSessionStmt->close();

                // Check for conflicts (excluding current entry)
                $conflictCheck = "SELECT id FROM timetable WHERE 
                                  (class_id = ? OR room_id = ? OR lecturer_id = ?) 
                                  AND day = ? AND time_slot_id = ? AND semester_id = ? AND session_id = ? AND id != ?";
                $conflictStmt = $conn->prepare($conflictCheck);
                $conflictStmt->bind_param("iiisiiii", $classId, $roomId, $lecturerId, $day, $timeSlotId, $semesterId, $sessionId, $id);
                $conflictStmt->execute();
                $conflictResult = $conflictStmt->get_result();
                
                if ($conflictResult->num_rows > 0) {
                    echo "<script>alert('Conflict detected! Class, room, or lecturer is already scheduled at this time.');</script>";
                    $conflictStmt->close();
                    break;
                }
                $conflictStmt->close();

                // Check if this is a break time slot
                $breakCheck = "SELECT is_break FROM time_slots WHERE id = ?";
                $breakStmt = $conn->prepare($breakCheck);
                $breakStmt->bind_param("i", $timeSlotId);
                $breakStmt->execute();
                $breakResult = $breakStmt->get_result();
                $breakData = $breakResult->fetch_assoc();
                
                if ($breakData['is_break'] == 1) {
                    echo "<script>alert('Cannot schedule classes during break time slots!');</script>";
                    $breakStmt->close();
                    break;
                }
                $breakStmt->close();

                // All validations passed, update the timetable entry
                $sql = "UPDATE timetable SET class_id = ?, course_id = ?, lecturer_id = ?, room_id = ?, time_slot_id = ?, 
                        day = ?, semester_id = ?, session_id = ?, activity_type = ?, notes = ?, is_confirmed = ? 
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("iiiiississi", $classId, $courseId, $lecturerId, $roomId, $timeSlotId, $day, $semesterId, $sessionId, $activityType, $notes, $isConfirmed, $id);
                    
                    if ($stmt->execute()) {
                        // Update timetable_lecturers for co-teaching support
                        $lecturerUpdate = "UPDATE timetable_lecturers SET lecturer_id = ? WHERE timetable_id = ?";
                        $lecturerStmt = $conn->prepare($lecturerUpdate);
                        $lecturerStmt->bind_param("ii", $lecturerId, $id);
                        $lecturerStmt->execute();
                        $lecturerStmt->close();
                        
                        echo "<script>alert('Schedule updated successfully!'); window.location.href='timetable.php';</script>";
                    } else {
                        echo "Error: " . $stmt->error;
                    }
                    $stmt->close();
                }
                break;

            case 'delete':
                // Delete timetable entry
                $id = $_POST['id'];
                
                // Check if entry has dependent records in timetable_lecturers
                $checkSql = "SELECT COUNT(*) as count FROM timetable_lecturers WHERE timetable_id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("i", $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $dependencies = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($dependencies['count'] > 0) {
                    // Delete dependent records first
                    $deleteLecturers = "DELETE FROM timetable_lecturers WHERE timetable_id = ?";
                    $lecturerStmt = $conn->prepare($deleteLecturers);
                    $lecturerStmt->bind_param("i", $id);
                    $lecturerStmt->execute();
                    $lecturerStmt->close();
                }
                
                // Delete the main timetable entry
                $sql = "DELETE FROM timetable WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        echo "<script>alert('Schedule deleted successfully!'); window.location.href='timetable.php';</script>";
                    } else {
                        echo "Error: " . $stmt->error;
                    }
                    $stmt->close();
                }
                break;
        }
    }
}

// Fetch existing timetable entries for display
$timetableEntries = [];
$sql = "SELECT t.*, c.name as class_name, co.code as course_code, co.name as course_name, co.credits,
               l.name as lecturer_name, l.rank, r.name as room_name, r.building,
               ts.start_time, ts.end_time, ts.duration, CONCAT(s.academic_year, ' - Semester ', s.semester) as session_name, 
               'Regular' as session_type,
               sem.name as semester_name, d.name as department_name, d.code as department_code
        FROM timetable t
        JOIN classes c ON t.class_id = c.id
        JOIN courses co ON t.course_id = co.id
        JOIN lecturers l ON t.lecturer_id = l.id
        JOIN rooms r ON t.room_id = r.id
        JOIN time_slots ts ON t.time_slot_id = ts.id
        JOIN sessions s ON t.session_id = s.id
        JOIN semesters sem ON t.semester_id = sem.id
        JOIN departments d ON c.department_id = d.id
        ORDER BY t.day, ts.start_time, t.semester_id, t.session_id";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $timetableEntries[] = $row;
    }
}

// Fetch classes for dropdown
$classes = [];
$sql = "SELECT c.id, c.name, c.level, c.capacity, c.current_enrollment,
               s.name as session_name, s.type as session_type,
               d.name as department_name, d.code as department_code
        FROM classes c
        JOIN sessions s ON c.session_id = s.id
        JOIN departments d ON c.department_id = d.id
        WHERE c.is_active = 1
        ORDER BY c.name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Fetch courses for dropdown
$courses = [];
$sql = "SELECT id, code, name, credits, hours_per_week, level FROM courses WHERE is_active = 1 ORDER BY code";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Fetch lecturers for dropdown
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

// Fetch rooms for dropdown
$rooms = [];
$sql = "SELECT id, name, building, room_type, capacity FROM rooms WHERE is_active = 1 ORDER BY building, name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
}

// Fetch time slots for dropdown (excluding breaks)
$timeSlots = [];
$sql = "SELECT id, start_time, end_time, duration FROM time_slots WHERE is_break = 0 ORDER BY start_time";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $timeSlots[] = $row;
    }
}

// Fetch semesters for dropdown
$semesters = [];
$sql = "SELECT id, name, start_date, end_date FROM semesters WHERE is_active = 1 ORDER BY start_date";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $semesters[] = $row;
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
$totalEntries = count($timetableEntries);
$confirmedEntries = count(array_filter($timetableEntries, function($e) { return $e['is_confirmed']; }));
$pendingEntries = $totalEntries - $confirmedEntries;

// Group by day for better analysis
$dayCounts = [];
foreach ($timetableEntries as $entry) {
    $day = $entry['day'];
    if (!isset($dayCounts[$day])) {
        $dayCounts[$day] = 0;
    }
    $dayCounts[$day]++;
}

// Group by session type for better analysis
$sessionTypeCounts = [];
foreach ($timetableEntries as $entry) {
    $type = $entry['session_type'];
    if (!isset($sessionTypeCounts[$type])) {
        $sessionTypeCounts[$type] = 0;
    }
    $sessionTypeCounts[$type]++;
}

// Group by activity type for better analysis
$activityTypeCounts = [];
foreach ($timetableEntries as $entry) {
    $type = $entry['activity_type'];
    if (!isset($activityTypeCounts[$type])) {
        $activityTypeCounts[$type] = 0;
    }
    $activityTypeCounts[$type]++;
}
?>

<?php $pageTitle = 'Manage Timetable'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

  <div class="main-content" id="mainContent">
    <div class="container mt-3">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $totalEntries; ?></h4>
                        <small>Total Entries</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $confirmedEntries; ?></h4>
                        <small>Confirmed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo $pendingEntries; ?></h4>
                        <small>Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h4 class="mb-0"><?php echo count($sessionTypeCounts); ?></h4>
                        <small>Session Types</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analysis Breakdowns -->
        <div class="row mb-4">
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
                                            <small class="text-muted"><?php echo $count; ?> classes</small>
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
                                            <small class="text-muted"><?php echo $count; ?> classes</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

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
                                            <small class="text-muted"><?php echo $count; ?> classes</small>
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
            <!-- Add Timetable Entry Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 id="formHeader">Schedule Class</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="timetableForm">
                            <input type="hidden" id="action" name="action" value="add">
                            <input type="hidden" id="timetableId" name="id" value="">
                            
                            <div class="alert alert-info" id="formMode" style="display: none;">
                                <strong>Edit Mode:</strong> You are currently editing an existing schedule. Click "Cancel Edit" to return to add mode.
                            </div>
                            <div class="mb-3">
                                <label for="classId" class="form-label">Class *</label>
                                <select class="form-select" id="classId" name="classId" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['name']); ?> (Level <?php echo $class['level']; ?>)<br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($class['session_name']); ?> (<?php echo ucfirst($class['session_type']); ?>)<br>
                                                <?php echo htmlspecialchars($class['department_code']); ?> - <?php echo htmlspecialchars($class['department_name']); ?><br>
                                                Capacity: <?php echo $class['current_enrollment']; ?>/<?php echo $class['capacity']; ?>
                                            </small>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the class to schedule</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="courseId" class="form-label">Course *</label>
                                <select class="form-select" id="courseId" name="courseId" required>
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['code']); ?> - <?php echo htmlspecialchars($course['name']); ?><br>
                                            <small class="text-muted">
                                                <?php echo $course['credits']; ?> credits, Level <?php echo $course['level']; ?>, <?php echo $course['hours_per_week']; ?> hrs/week
                                            </small>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the course to schedule</div>
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
                        <?php endif; ?><br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($lecturer['department_code']); ?> - <?php echo htmlspecialchars($lecturer['department_name']); ?><br>
                                                Department: <?php echo htmlspecialchars($lecturer['department_name']); ?>
                                            </small>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the lecturer to teach this class</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="roomId" class="form-label">Room *</label>
                                <select class="form-select" id="roomId" name="roomId" required>
                                    <option value="">Select Room</option>
                                    <?php foreach ($rooms as $room): ?>
                                        <option value="<?php echo $room['id']; ?>">
                                            <?php echo htmlspecialchars($room['name']); ?> (<?php echo htmlspecialchars($room['building']); ?>)<br>
                                            <small class="text-muted">
                                                <?php echo ucfirst(str_replace('_', ' ', $room['room_type'])); ?>, Capacity: <?php echo $room['capacity']; ?>
                                            </small>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the room for this class</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="timeSlotId" class="form-label">Time Slot *</label>
                                        <select class="form-select" id="timeSlotId" name="timeSlotId" required>
                                            <option value="">Select Time</option>
                                            <?php foreach ($timeSlots as $slot): ?>
                                                <option value="<?php echo $slot['id']; ?>">
                                                    <?php echo date('g:i A', strtotime($slot['start_time'])); ?> - <?php echo date('g:i A', strtotime($slot['end_time'])); ?><br>
                                                    <small class="text-muted"><?php echo $slot['duration']; ?> minutes</small>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Select the time slot</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="day" class="form-label">Day *</label>
                                        <select class="form-select" id="day" name="day" required>
                                            <option value="">Select Day</option>
                                            <option value="monday">Monday</option>
                                            <option value="tuesday">Tuesday</option>
                                            <option value="wednesday">Wednesday</option>
                                            <option value="thursday">Thursday</option>
                                            <option value="friday">Friday</option>
                                            <option value="saturday">Saturday</option>
                                            <option value="sunday">Sunday</option>
                                        </select>
                                        <div class="form-text">Select the day of the week</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="semesterId" class="form-label">Semester *</label>
                                        <select class="form-select" id="semesterId" name="semesterId" required>
                                            <option value="">Select Semester</option>
                                            <?php foreach ($semesters as $semester): ?>
                                                <option value="<?php echo $semester['id']; ?>">
                                                    <?php echo htmlspecialchars($semester['name']); ?><br>
                                                    <small class="text-muted">
                                                        <?php echo date('M j', strtotime($semester['start_date'])); ?> - <?php echo date('M j', strtotime($semester['end_date'])); ?>
                                                    </small>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Select the semester</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
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
                                        <div class="form-text">Select the session</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="activityType" class="form-label">Activity Type</label>
                                <select class="form-select" id="activityType" name="activityType">
                                    <option value="lecture">Lecture</option>
                                    <option value="tutorial">Tutorial</option>
                                    <option value="laboratory">Laboratory</option>
                                    <option value="seminar">Seminar</option>
                                </select>
                                <div class="form-text">Select the type of activity</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Optional notes about this schedule"></textarea>
                                <div class="form-text">Additional information about this schedule</div>
                            </div>
                            
                            <div class="mb-3" id="confirmationDiv" style="display: none;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="isConfirmed" name="isConfirmed">
                                    <label class="form-check-label" for="isConfirmed">
                                        Mark as Confirmed
                                    </label>
                                </div>
                                <div class="form-text">Check this to confirm the schedule is finalized</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitBtn">Schedule Class</button>
                                <button type="button" class="btn btn-secondary" id="cancelBtn" style="display: none;" onclick="cancelEdit()">Cancel Edit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Display Timetable Entries -->
            <div class="col-md-6">
                <div class="table-container">
                    <div class="table-header">
                        <h4><i class="fas fa-calendar-check me-2"></i>Current Schedule</h4>
                    </div>
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search schedule...">
                    </div>
                    <?php if (empty($timetableEntries)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h5>No scheduled classes found</h5>
                            <p>Start by scheduling your first class using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Class</th>
                                            <th>Course</th>
                                            <th>Schedule</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($timetableEntries as $entry): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($entry['class_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($entry['department_code']); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($entry['course_code']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($entry['course_name']); ?></small><br>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($entry['activity_type']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <strong><?php echo ucfirst($entry['day']); ?></strong><br>
                                                        <?php echo date('g:i A', strtotime($entry['start_time'])); ?> - <?php echo date('g:i A', strtotime($entry['end_time'])); ?><br>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($entry['room_name']); ?> (<?php echo htmlspecialchars($entry['building']); ?>)<br>
                                                            <?php echo htmlspecialchars($entry['session_name']); ?> - <?php echo htmlspecialchars($entry['semester_name']); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($entry['is_confirmed']): ?>
                                                        <span class="badge bg-success">Confirmed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editEntry(<?php echo $entry['id']; ?>, <?php echo $entry['class_id']; ?>, <?php echo $entry['course_id']; ?>, <?php echo $entry['lecturer_id']; ?>, <?php echo $entry['room_id']; ?>, <?php echo $entry['time_slot_id']; ?>, '<?php echo $entry['day']; ?>', <?php echo $entry['semester_id']; ?>, <?php echo $entry['session_id']; ?>, '<?php echo $entry['activity_type']; ?>', '<?php echo htmlspecialchars($entry['notes']); ?>', <?php echo $entry['is_confirmed']; ?>)">Edit</button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteEntry(<?php echo $entry['id']; ?>, '<?php echo htmlspecialchars($entry['class_name']); ?> - <?php echo htmlspecialchars($entry['course_code']); ?>')">Delete</button>
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
