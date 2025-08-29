<?php
// Timetable Generation Page with readiness checks and GA-based generation

$pageTitle = 'Generate Timetable';
include 'connect.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Helper: generate time slots programmatically (8 AM to 6 PM)
function generate_time_slots() {
    $time_slots = [];
    
    for ($hour = 8; $hour <= 18; $hour++) {
        $start_time = sprintf('%02d:00:00', $hour);
        $end_time = sprintf('%02d:00:00', $hour + 1);
        $time_slots[] = [
            'id' => $hour,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'key' => substr($start_time, 0, 5) . '-' . substr($end_time, 0, 5)
        ];
    }
    
    return $time_slots;
}

// Helper: readiness checks for system readiness
function check_readiness($conn) {
    $status = [
        'classes' => 0,
        'courses' => 0,
        'rooms' => 0,
        'lecturers' => 0,
        'days' => 0,
        'time_slots_present' => false,
        'unassigned_courses' => 0,
        'ready' => false,
    ];

    // Active classes
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM classes WHERE is_active = 1");
    $status['classes'] = (int)($res ? ($res->fetch_assoc()['cnt'] ?? 0) : 0);

    // Active courses
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM courses WHERE is_active = 1");
    $status['courses'] = (int)($res ? ($res->fetch_assoc()['cnt'] ?? 0) : 0);

    // Rooms
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE is_active = 1");
    $status['rooms'] = (int)($res ? ($res->fetch_assoc()['cnt'] ?? 0) : 0);

    // Lecturers
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM lecturers WHERE is_active = 1");
    $status['lecturers'] = (int)($res ? ($res->fetch_assoc()['cnt'] ?? 0) : 0);

    // Days
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM days");
    $status['days'] = (int)($res ? ($res->fetch_assoc()['cnt'] ?? 0) : 0);

    // Time slots are now generated programmatically (8 AM to 6 PM, hourly)
    $status['time_slots_present'] = true;

    // Courses without any lecturer mapping
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM courses c LEFT JOIN lecturer_courses lc ON lc.course_id = c.id WHERE c.is_active = 1 AND lc.id IS NULL");
    $status['unassigned_courses'] = (int)($res ? ($res->fetch_assoc()['cnt'] ?? 0) : 0);

    // Final readiness flag
    $status['ready'] = (
        $status['classes'] > 0 &&
        $status['courses'] > 0 &&
        $status['rooms'] > 0 &&
        $status['lecturers'] > 0 &&
        $status['days'] >= 5 &&
        $status['unassigned_courses'] === 0
    );

    return $status;
}

$selected_semester = isset($_POST['semester']) ? $_POST['semester'] : '';
$selected_type = isset($_POST['timetable_type']) ? $_POST['timetable_type'] : '';
$success_message = '';
$error_message = '';
$readiness = null;

// Handle generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    if (empty($selected_semester) || empty($selected_type)) {
        $error_message = 'Please select both semester and timetable type.';
    } else {


        // Check readiness
        $readiness = check_readiness($conn);
        if (!$readiness['ready']) {
            $error_message = 'Not ready to generate. Please address the items below.';
        } else {
            // Note: For now, we'll generate a new timetable without clearing existing ones
            // This can be enhanced later to handle semester/type specific clearing
            // All timetables will be generated with session_id = 1 for compatibility

            // Build data arrays for GA v1
            // All active classes
            $classes = [];
            $stmt = $conn->prepare("SELECT DISTINCT c.id AS class_id, c.name AS class_name, COALESCE(c.current_enrollment, 0) AS class_size
                                     FROM classes c
                                     WHERE c.is_active = 1");
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) { $classes[] = $row; }
            $stmt->close();

            // All active courses with lecturers (pick one lecturer per course)
            $courses = [];
            $stmt = $conn->prepare("SELECT c.id AS class_id,
                                            co.id AS course_id,
                                            co.name AS course_name,
                                            l.id AS lecturer_id,
                                            l.name AS lecturer_name
                                     FROM classes c
                                     CROSS JOIN courses co
                                     JOIN lecturer_courses lc ON lc.course_id = co.id
                                     JOIN lecturers l ON l.id = lc.lecturer_id
                                     WHERE c.is_active = 1 AND co.is_active = 1
                                     ORDER BY c.id, co.id, l.id");
            $stmt->execute();
            $res = $stmt->get_result();
            // Deduplicate by class_id + course_id (pick the first lecturer mapping)
            $seen = [];
            while ($row = $res->fetch_assoc()) {
                $key = $row['class_id'] . '-' . $row['course_id'];
                if (!isset($seen[$key])) {
                    $courses[] = $row;
                    $seen[$key] = true;
                }
            }
            $stmt->close();

            // Rooms
            $rooms = [];
            $res = $conn->query("SELECT id AS room_id, name AS room_name, capacity FROM rooms WHERE is_active = 1");
            while ($row = $res->fetch_assoc()) { $rooms[] = $row; }

            if (empty($classes) || empty($courses) || empty($rooms)) {
                $error_message = 'Insufficient data to generate timetable.';
            } else {
                // Run GA v1 with requested time slots (already defined in GA class)
                include 'ga_timetable_generator.php';
                $ga = new GeneticAlgorithm($classes, $courses, $rooms);
                // Reduce workload to avoid timeouts on large data sets
                $ga->initializePopulation(12);
                $ga->setProgressReporter(function($gen, $total, $fitness) {
                    // No-op in sync mode; future: store progress in DB or session
                });
                // Lower generations and add time budget to decrease execution time
                $ga->setTimeBudgetSeconds(20);
                $bestTimetable = $ga->evolve(25);

                // Map names to IDs
                // Days map
                $daysMap = [];
                $res = $conn->query("SELECT id, name FROM days");
                while ($row = $res->fetch_assoc()) { $daysMap[$row['name']] = (int)$row['id']; }

                // Time slots map (generated programmatically)
                $slotsMap = [];
                $time_slots = generate_time_slots();
                foreach ($time_slots as $slot) {
                    $slotsMap[$slot['key']] = (int)$slot['id'];
                }

                // Session type: default to Lecture
                $sessionTypeId = null;
                $res = $conn->query("SELECT id FROM session_types WHERE name = 'Lecture' LIMIT 1");
                if ($res && $res->num_rows > 0) {
                    $sessionTypeId = (int)$res->fetch_assoc()['id'];
                } else {
                    // Create if missing
                    $conn->query("INSERT INTO session_types (name) VALUES ('Lecture')");
                    $sessionTypeId = (int)$conn->insert_id;
                }

                // Insert timetable entries with duplicate-safe handling
                $inserted = 0;
                foreach ($bestTimetable as $entry) {
                    $dayName = $entry['day'];
                    $timeSlotStr = $entry['time_slot']; // e.g., 07:00-10:00
                    $classId = (int)$entry['class_id'];
                    $courseId = (int)$entry['course_id'];
                    $lecturerId = (int)$entry['lecturer_id'];
                    $roomId = (int)$entry['room_id'];

                    // Resolve IDs
                    $dayId = $daysMap[$dayName] ?? null;
                    $timeSlotId = $slotsMap[$timeSlotStr] ?? null;
                    if (!$dayId || !$timeSlotId) { continue; }

                    // Create or get class_course_id (without session dependency)
                    $stmt = $conn->prepare("SELECT id FROM class_courses WHERE class_id = ? AND course_id = ? LIMIT 1");
                    $stmt->bind_param('ii', $classId, $courseId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $cc = $res->fetch_assoc();
                    $stmt->close();
                    
                    if (!$cc) {
                        // Create the class_course mapping if it doesn't exist
                        $stmt = $conn->prepare("INSERT INTO class_courses (class_id, course_id, session_id) VALUES (?, ?, 1)");
                        $stmt->bind_param('ii', $classId, $courseId);
                        $stmt->execute();
                        $classCourseId = $conn->insert_id;
                        $stmt->close();
                    } else {
                        $classCourseId = (int)$cc['id'];
                    }

                    // lecturer_course_id
                    $stmt = $conn->prepare("SELECT id FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ? LIMIT 1");
                    $stmt->bind_param('ii', $lecturerId, $courseId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $lc = $res->fetch_assoc();
                    $stmt->close();
                    if (!$lc) { continue; }
                    $lecturerCourseId = (int)$lc['id'];

                    // Check if a record already exists in this unique slot (day, slot, room)
                    $check = $conn->prepare("SELECT id FROM timetable WHERE day_id = ? AND time_slot_id = ? AND room_id = ? LIMIT 1");
                    $check->bind_param('iii', $dayId, $timeSlotId, $roomId);
                    $check->execute();
                    $existsRes = $check->get_result();
                    $existsRow = $existsRes ? $existsRes->fetch_assoc() : null;
                    $check->close();

                    if ($existsRow) {
                        // Skip conflicting entry to avoid room conflicts
                        continue;
                    }

                    // Enforce lecturer cannot teach two classes at the same time across rooms
                    $lecturerConflict = $conn->prepare("SELECT t.id
                                                        FROM timetable t
                                                        JOIN lecturer_courses lc2 ON lc2.id = t.lecturer_course_id
                                                        WHERE t.day_id = ? AND t.time_slot_id = ? AND lc2.lecturer_id = ?
                                                        LIMIT 1");
                    $lecturerConflict->bind_param('iii', $dayId, $timeSlotId, $lecturerId);
                    $lecturerConflict->execute();
                    $confRes = $lecturerConflict->get_result();
                    $hasLecturerConflict = $confRes && $confRes->num_rows > 0;
                    $lecturerConflict->close();
                    if ($hasLecturerConflict) {
                        // Skip to avoid assigning lecturer to multiple rooms at the same time
                        continue;
                    }

                    // Insert (using default session_id = 1)
                    $stmt = $conn->prepare("INSERT INTO timetable (session_id, class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, session_type_id)
                                              VALUES (1, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('iiiiii', $classCourseId, $lecturerCourseId, $dayId, $timeSlotId, $roomId, $sessionTypeId);
                    if ($stmt->execute()) { $inserted++; }
                    $stmt->close();
                }

                $success_message = "Timetable generated successfully for $selected_semester semester ($selected_type). Entries inserted: $inserted.";
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_map_all') {
    // Map all active courses to all active classes, skipping existing
    $sql = "INSERT INTO class_courses (class_id, course_id, session_id)
            SELECT c.id, co.id, 1
            FROM classes c
            CROSS JOIN courses co
            LEFT JOIN class_courses cc ON cc.class_id = c.id AND cc.course_id = co.id AND cc.session_id = 1
            WHERE c.is_active = 1 AND co.is_active = 1 AND cc.id IS NULL";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            $success_message = "Mapped $affected course-class pairs.";
        } else {
            $error_message = 'Mapping failed: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error_message = 'Prepare failed: ' . $conn->error;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_map_department') {
    $department_id = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0;
    if ($department_id <= 0) {
        $error_message = 'Please choose a department.';
    } else {
        // Map all active courses in department to all active classes in department
        $sql = "INSERT INTO class_courses (class_id, course_id, session_id)
                SELECT c.id, co.id, 1
                FROM classes c
                JOIN courses co ON co.department_id = c.department_id
                LEFT JOIN class_courses cc ON cc.class_id = c.id AND cc.course_id = co.id AND cc.session_id = 1
                WHERE c.is_active = 1 AND co.is_active = 1 AND c.department_id = ? AND cc.id IS NULL";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $department_id);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                $success_message = "Mapped $affected pairs for the selected department.";
            } else {
                $error_message = 'Mapping failed: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = 'Prepare failed: ' . $conn->error;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_map_course_lecturers_all') {
    // Ensure each active course has at least one lecturer mapped
    // Step 1: map by department using the lowest-id active lecturer per department
    $sql = "INSERT INTO lecturer_courses (lecturer_id, course_id)
            SELECT lmin.id AS lecturer_id, co.id AS course_id
            FROM courses co
            JOIN (
                SELECT department_id, MIN(id) AS id
                FROM lecturers
                WHERE is_active = 1
                GROUP BY department_id
            ) lmin ON lmin.department_id = co.department_id
            LEFT JOIN lecturer_courses lc ON lc.course_id = co.id
            WHERE co.is_active = 1 AND lc.id IS NULL";
    $affected1 = 0; $affected2 = 0;
    if ($conn->query($sql) === TRUE) {
        $affected1 = $conn->affected_rows;
    }

    // Step 2: fallback - map remaining unmapped courses to the first active lecturer
    $fallbackLectId = null;
    $res = $conn->query("SELECT id FROM lecturers WHERE is_active = 1 ORDER BY id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $fallbackLectId = (int)$res->fetch_assoc()['id'];
    }
    if ($fallbackLectId) {
        $stmt = $conn->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id)
                                SELECT ?, co.id
                                FROM courses co
                                LEFT JOIN lecturer_courses lc ON lc.course_id = co.id
                                WHERE co.is_active = 1 AND lc.id IS NULL");
        if ($stmt) {
            $stmt->bind_param('i', $fallbackLectId);
            if ($stmt->execute()) { $affected2 = $stmt->affected_rows; }
            $stmt->close();
        }
    }
    $total = $affected1 + $affected2;
    if ($total > 0) {
        $success_message = "Created lecturer-course mappings for $total courses.";
    } else {
        $success_message = "All courses already have lecturer mappings.";
    }
}

// Departments for optional mapping by department
$departments_result = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");

?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-calendar-plus me-2"></i>Generate Timetable</h4>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="view_timetable.php">
                    <i class="fas fa-eye me-2"></i>View Timetable
                </a>
                <a class="btn btn-outline-success" href="saved_timetable.php">
                    <i class="fas fa-save me-2"></i>Saved Timetables
                </a>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($selected_semester && $selected_type): ?>
            <div class="alert alert-info m-3" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle me-2"></i>
                    <div>
                        <strong>Selected Filters:</strong>
                        <?php 
                        $semester_names = ['1' => 'First Semester', '2' => 'Second Semester', '3' => 'Summer Semester'];
                        $type_names = ['lecture' => 'Lecture Timetable', 'exam' => 'Exam Timetable'];
                        echo $semester_names[$selected_semester] . ' - ' . $type_names[$selected_type];
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card m-3">
            <div class="card-body">
                <form method="POST" action="generate_timetable.php" class="row g-3" id="generateForm">
                    <input type="hidden" name="action" value="generate" />
                    <div class="col-md-5">
                        <label for="semester" class="form-label">Select Semester *</label>
                        <select name="semester" id="semester" class="form-select" required>
                            <option value="">Choose semester...</option>
                            <option value="1" <?php echo (isset($_POST['semester']) && $_POST['semester'] == '1') ? 'selected' : ''; ?>>First Semester</option>
                            <option value="2" <?php echo (isset($_POST['semester']) && $_POST['semester'] == '2') ? 'selected' : ''; ?>>Second Semester</option>
                            <option value="3" <?php echo (isset($_POST['semester']) && $_POST['semester'] == '3') ? 'selected' : ''; ?>>Summer Semester</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="timetable_type" class="form-label">Timetable Type *</label>
                        <select name="timetable_type" id="timetable_type" class="form-select" required>
                            <option value="">Choose type...</option>
                            <option value="lecture" <?php echo (isset($_POST['timetable_type']) && $_POST['timetable_type'] == 'lecture') ? 'selected' : ''; ?>>Lecture Timetable</option>
                            <option value="exam" <?php echo (isset($_POST['timetable_type']) && $_POST['timetable_type'] == 'exam') ? 'selected' : ''; ?>>Exam Timetable</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" id="generateBtn">
                            <i class="fas fa-cogs me-2"></i>Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php
        // Show readiness if semester and type are selected
        $selected_semester = isset($_POST['semester']) ? $_POST['semester'] : '';
        $selected_type = isset($_POST['timetable_type']) ? $_POST['timetable_type'] : '';
        
        if ($selected_semester && $selected_type) {
            // Show readiness check for the system
            $readiness = [
                'classes' => 0,
                'courses' => 0,
                'rooms' => 0,
                'lecturers' => 0,
                'days' => 0,
                'time_slots_present' => true,
                'unassigned_courses' => 0,
                'ready' => false,
            ];
            
            // Use the centralized readiness check function
            $readiness = check_readiness($conn);
        ?>
            <div class="card m-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Readiness Check</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Active classes
                            <span class="badge <?php echo ($readiness['classes'] > 0) ? 'bg-success' : 'bg-danger'; ?>"><?php echo $readiness['classes']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Active courses
                            <span class="badge <?php echo ($readiness['courses'] > 0) ? 'bg-success' : 'bg-danger'; ?>"><?php echo $readiness['courses']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Active rooms
                            <span class="badge <?php echo ($readiness['rooms'] > 0) ? 'bg-success' : 'bg-danger'; ?>"><?php echo $readiness['rooms']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Active lecturers
                            <span class="badge <?php echo ($readiness['lecturers'] > 0) ? 'bg-success' : 'bg-danger'; ?>"><?php echo $readiness['lecturers']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Days configured
                            <span class="badge <?php echo ($readiness['days'] >= 5) ? 'bg-success' : 'bg-danger'; ?>"><?php echo $readiness['days']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Time slots (8 AM to 6 PM, hourly)
                            <span class="badge bg-success">Generated programmatically</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Courses without lecturer mapping
                            <span class="badge <?php echo ($readiness['unassigned_courses'] === 0) ? 'bg-success' : 'bg-danger'; ?>"><?php echo $readiness['unassigned_courses']; ?></span>
                        </li>
                    </ul>
                    <div class="mt-3 d-flex gap-2 flex-wrap">
                        <?php if ($readiness['ready']) : ?>
                            <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Ready to generate</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> Not ready</span>
                        <?php endif; ?>
                        <form method="POST" action="generate_timetable.php" class="d-inline">
                            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selected_semester); ?>" />
                            <input type="hidden" name="timetable_type" value="<?php echo htmlspecialchars($selected_type); ?>" />
                            <input type="hidden" name="action" value="quick_map_course_lecturers_all" />
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-user-plus me-1"></i>Auto-map Lecturers to Courses
                            </button>
                        </form>
                        <form method="POST" action="generate_timetable.php" class="d-inline">
                            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selected_semester); ?>" />
                            <input type="hidden" name="timetable_type" value="<?php echo htmlspecialchars($selected_type); ?>" />
                            <input type="hidden" name="action" value="quick_map_all" />
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-link me-1"></i>Quick Map All Courses to All Classes
                            </button>
                        </form>
                        <form method="POST" action="generate_timetable.php" class="d-inline">
                            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($selected_semester); ?>" />
                            <input type="hidden" name="timetable_type" value="<?php echo htmlspecialchars($selected_type); ?>" />
                            <input type="hidden" name="action" value="quick_map_department" />
                            <div class="input-group input-group-sm" style="max-width: 420px;">
                                <label class="input-group-text" for="department_id">Dept</label>
                                <select class="form-select" name="department_id" id="department_id">
                                    <option value="">Choose...</option>
                                    <?php if ($departments_result && $departments_result->num_rows > 0): ?>
                                        <?php while ($dept = $departments_result->fetch_assoc()): ?>
                                            <option value="<?php echo htmlspecialchars($dept['id']); ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-link me-1"></i>Quick Map by Dept
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Enhanced form handling and UI interactions
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('generateForm');
  const btn = document.getElementById('generateBtn');
  const semesterSelect = document.getElementById('semester');
  const typeSelect = document.getElementById('timetable_type');
  
  if (!form || !btn) return;
  
  // Form submission with progress animation
  form.addEventListener('submit', function() {
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.dataset.original = original;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating...';
  });
  
  // Auto-submit form when all required fields are filled to show readiness check
  function checkFormCompletion() {
    const allFilled = semesterSelect.value && typeSelect.value;
    if (allFilled) {
      // Create a temporary form submission to show readiness check
      const tempForm = document.createElement('form');
      tempForm.method = 'POST';
      tempForm.action = 'generate_timetable.php';
      
      const semesterInput = document.createElement('input');
      semesterInput.type = 'hidden';
      semesterInput.name = 'semester';
      semesterInput.value = semesterSelect.value;
      
      const typeInput = document.createElement('input');
      typeInput.type = 'hidden';
      typeInput.name = 'timetable_type';
      typeInput.value = typeSelect.value;
      
      tempForm.appendChild(semesterInput);
      tempForm.appendChild(typeInput);
      
      document.body.appendChild(tempForm);
      tempForm.submit();
    }
  }
  
  // Add event listeners for auto-submission
  semesterSelect.addEventListener('change', checkFormCompletion);
  typeSelect.addEventListener('change', checkFormCompletion);
});
</script>

