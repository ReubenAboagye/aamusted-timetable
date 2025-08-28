<?php
// Timetable Generation Page with readiness checks and GA-based generation

$pageTitle = 'Generate Timetable';
include 'connect.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Helper: ensure required 3-hour time slots exist
function ensure_time_slots($conn) {
    $desired = [
        ['07:00:00','10:00:00',180],
        ['10:00:00','13:00:00',180],
        ['14:00:00','17:00:00',180],
        ['17:00:00','20:00:00',180]
    ];
    $created = 0;
    foreach ($desired as [$start, $end, $duration]) {
        $sql = "SELECT id FROM time_slots WHERE start_time = ? AND end_time = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $insert = $conn->prepare("INSERT INTO time_slots (start_time, end_time, duration, is_break, is_mandatory) VALUES (?, ?, ?, 0, 1)");
            $insert->bind_param('ssi', $start, $end, $duration);
            $insert->execute();
            $insert->close();
            $created++;
        }
        $stmt->close();
    }
    return $created;
}

// Helper: readiness checks for selected session
function check_readiness($conn, $sessionId) {
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

    // Classes participating in this session
    $sql = "SELECT COUNT(DISTINCT c.id) AS cnt
            FROM classes c
            JOIN class_courses cc ON cc.class_id = c.id
            WHERE c.is_active = 1 AND cc.session_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $status['classes'] = (int)($row['cnt'] ?? 0);
    $stmt->close();

    // Courses mapped to classes for this session
    $sql = "SELECT COUNT(DISTINCT cc.course_id) AS cnt FROM class_courses cc WHERE cc.session_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $status['courses'] = (int)($row['cnt'] ?? 0);
    $stmt->close();

    // Rooms
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM rooms WHERE is_active = 1");
    $status['rooms'] = (int)($res ? ($res->fetch_assoc()['cnt'] ?? 0) : 0);

    // Lecturers
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM lecturers WHERE is_active = 1");
    $status['lecturers'] = (int)($res ? ($res->fetch_assoc()['cnt'] ?? 0) : 0);

    // Days
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM days");
    $status['days'] = (int)($res ? ($res->fetch_assoc()['cnt'] ?? 0) : 0);

    // Time slots coverage (the 4 target slots)
    $checkSlots = [
        ['07:00:00','10:00:00'],
        ['10:00:00','13:00:00'],
        ['14:00:00','17:00:00'],
        ['17:00:00','20:00:00']
    ];
    $presentAll = true;
    foreach ($checkSlots as [$s,$e]) {
        $stmt = $conn->prepare("SELECT id FROM time_slots WHERE start_time = ? AND end_time = ? LIMIT 1");
        $stmt->bind_param('ss', $s, $e);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) { $presentAll = false; }
        $stmt->close();
    }
    $status['time_slots_present'] = $presentAll;

    // Courses in this session without any lecturer mapping
    $sql = "SELECT COUNT(*) AS cnt FROM (
                SELECT DISTINCT cc.course_id
                FROM class_courses cc
                WHERE cc.session_id = ?
                AND cc.course_id NOT IN (
                    SELECT DISTINCT lc.course_id FROM lecturer_courses lc
                )
            ) AS unmapped";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $sessionId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $status['unassigned_courses'] = (int)($row['cnt'] ?? 0);
    $stmt->close();

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

$selected_session = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
$clear_existing = isset($_POST['clear_existing']);
$success_message = '';
$error_message = '';
$readiness = null;

// Handle generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    if ($selected_session <= 0) {
        $error_message = 'Please select a valid session.';
    } else {
        // Ensure target time slots are present
        ensure_time_slots($conn);

        // Check readiness
        $readiness = check_readiness($conn, $selected_session);
        if (!$readiness['ready']) {
            $error_message = 'Not ready to generate. Please address the items below.';
        } else {
            // Optionally clear existing timetable for session
            if ($clear_existing) {
                $stmt = $conn->prepare('DELETE FROM timetable WHERE session_id = ?');
                $stmt->bind_param('i', $selected_session);
                $stmt->execute();
                $stmt->close();
            }

            // Build data arrays for GA v1
            // Classes participating in this session
            $classes = [];
            $stmt = $conn->prepare("SELECT DISTINCT c.id AS class_id, c.name AS class_name, COALESCE(c.current_enrollment, 0) AS class_size
                                     FROM classes c
                                     JOIN class_courses cc ON cc.class_id = c.id
                                     WHERE c.is_active = 1 AND cc.session_id = ?");
            $stmt->bind_param('i', $selected_session);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) { $classes[] = $row; }
            $stmt->close();

            // Courses for those classes in this session (pick one lecturer per course)
            $courses = [];
            $stmt = $conn->prepare("SELECT cc.class_id,
                                            c.id AS course_id,
                                            c.name AS course_name,
                                            l.id AS lecturer_id,
                                            l.name AS lecturer_name
                                     FROM class_courses cc
                                     JOIN courses c ON c.id = cc.course_id
                                     JOIN lecturer_courses lc ON lc.course_id = c.id
                                     JOIN lecturers l ON l.id = lc.lecturer_id
                                     WHERE cc.session_id = ?
                                     ORDER BY cc.class_id, c.id, l.id");
            $stmt->bind_param('i', $selected_session);
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

                // Time slots map
                $slotsMap = [];
                $res = $conn->query("SELECT id, start_time, end_time FROM time_slots");
                while ($row = $res->fetch_assoc()) {
                    $key = substr($row['start_time'],0,5) . '-' . substr($row['end_time'],0,5);
                    $slotsMap[$key] = (int)$row['id'];
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

                // Insert timetable entries
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

                    // class_course_id
                    $stmt = $conn->prepare("SELECT id FROM class_courses WHERE class_id = ? AND course_id = ? AND session_id = ? LIMIT 1");
                    $stmt->bind_param('iii', $classId, $courseId, $selected_session);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $cc = $res->fetch_assoc();
                    $stmt->close();
                    if (!$cc) { continue; }
                    $classCourseId = (int)$cc['id'];

                    // lecturer_course_id
                    $stmt = $conn->prepare("SELECT id FROM lecturer_courses WHERE lecturer_id = ? AND course_id = ? LIMIT 1");
                    $stmt->bind_param('ii', $lecturerId, $courseId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $lc = $res->fetch_assoc();
                    $stmt->close();
                    if (!$lc) { continue; }
                    $lecturerCourseId = (int)$lc['id'];

                    // Insert, ignore duplicates on unique key
                    $stmt = $conn->prepare("INSERT INTO timetable (session_id, class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, session_type_id)
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('iiiiiii', $selected_session, $classCourseId, $lecturerCourseId, $dayId, $timeSlotId, $roomId, $sessionTypeId);
                    if ($stmt->execute()) { $inserted++; }
                    $stmt->close();
                }

                $success_message = "Timetable generated successfully. Entries inserted: $inserted.";
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_map_all') {
    if ($selected_session <= 0) {
        $error_message = 'Please select a valid session before mapping.';
    } else {
        // Map all active courses to all active classes for the selected session, skipping existing
        $sql = "INSERT INTO class_courses (class_id, course_id, session_id)
                SELECT c.id, co.id, ?
                FROM classes c
                CROSS JOIN courses co
                LEFT JOIN class_courses cc ON cc.class_id = c.id AND cc.course_id = co.id AND cc.session_id = ?
                WHERE c.is_active = 1 AND co.is_active = 1 AND cc.id IS NULL";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ii', $selected_session, $selected_session);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                $success_message = "Mapped $affected course-class pairs for the selected session.";
            } else {
                $error_message = 'Mapping failed: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = 'Prepare failed: ' . $conn->error;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_map_department') {
    if ($selected_session <= 0) {
        $error_message = 'Please select a valid session before mapping.';
    } else {
        $department_id = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0;
        if ($department_id <= 0) {
            $error_message = 'Please choose a department.';
        } else {
            // Map all active courses in department to all active classes in department for the selected session
            $sql = "INSERT INTO class_courses (class_id, course_id, session_id)
                    SELECT c.id, co.id, ?
                    FROM classes c
                    JOIN courses co ON co.department_id = c.department_id
                    LEFT JOIN class_courses cc ON cc.class_id = c.id AND cc.course_id = co.id AND cc.session_id = ?
                    WHERE c.is_active = 1 AND co.is_active = 1 AND c.department_id = ? AND cc.id IS NULL";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('iii', $selected_session, $selected_session, $department_id);
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

// Fetch sessions for dropdown
$sessions_sql = "SELECT id, semester_name, academic_year FROM sessions WHERE is_active = 1 ORDER BY academic_year DESC, semester_number";
$sessions_result = $conn->query($sessions_sql);

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

        <div class="card m-3">
            <div class="card-body">
                <form method="POST" action="generate_timetable.php" class="row g-3" id="generateForm">
                    <input type="hidden" name="action" value="generate" />
                    <div class="col-md-6">
                        <label for="session_id" class="form-label">Select Session *</label>
                        <select name="session_id" id="session_id" class="form-select" required>
                            <option value="">Choose a session...</option>
                            <?php if ($sessions_result && $sessions_result->num_rows > 0): ?>
                                <?php while ($session = $sessions_result->fetch_assoc()): ?>
                                    <option value="<?php echo $session['id']; ?>" <?php echo ($selected_session == $session['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($session['semester_name'] . ' (' . $session['academic_year'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="clear_existing" name="clear_existing" <?php echo $clear_existing ? 'checked' : 'checked'; ?> />
                            <label class="form-check-label" for="clear_existing">
                                Clear existing timetable for session
                            </label>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" id="generateBtn">
                            <i class="fas fa-cogs me-2"></i>Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php
        // Show readiness if session selected (either from POST or default selection)
        if ($selected_session) {
            // Ensure slots before showing status so user sees slots are present next time
            ensure_time_slots($conn);
            $readiness = $readiness ?: check_readiness($conn, $selected_session);
        ?>
            <div class="card m-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Readiness Check</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Classes mapped to session
                            <span class="badge <?php echo ($readiness['classes'] > 0) ? 'bg-success' : 'bg-danger'; ?>"><?php echo $readiness['classes']; ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Courses mapped to session
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
                            Required time slots present (07-10, 10-13, 14-17, 17-20)
                            <span class="badge <?php echo ($readiness['time_slots_present']) ? 'bg-success' : 'bg-warning'; ?>"><?php echo $readiness['time_slots_present'] ? 'Yes' : 'Will be created'; ?></span>
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
                            <input type="hidden" name="session_id" value="<?php echo $selected_session; ?>" />
                            <input type="hidden" name="action" value="quick_map_course_lecturers_all" />
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="fas fa-user-plus me-1"></i>Auto-map Lecturers to Courses
                            </button>
                        </form>
                        <form method="POST" action="generate_timetable.php" class="d-inline">
                            <input type="hidden" name="session_id" value="<?php echo $selected_session; ?>" />
                            <input type="hidden" name="action" value="quick_map_all" />
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-link me-1"></i>Quick Map All Courses to All Classes
                            </button>
                        </form>
                        <form method="POST" action="generate_timetable.php" class="d-inline">
                            <input type="hidden" name="session_id" value="<?php echo $selected_session; ?>" />
                            <input type="hidden" name="action" value="quick_map_department" />
                            <div class="input-group input-group-sm" style="max-width: 420px;">
                                <label class="input-group-text" for="department_id">Dept</label>
                                <select class="form-select" name="department_id" id="department_id">
                                    <option value="">Choose...</option>
                                    <?php if ($departments_result && $departments_result->num_rows > 0): ?>
                                        <?php while ($dept = $departments_result->fetch_assoc()): ?>
                                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
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
// Basic progress animation while request is running
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('generateForm');
  const btn = document.getElementById('generateBtn');
  if (!form || !btn) return;
  form.addEventListener('submit', function() {
    btn.disabled = true;
    const original = btn.innerHTML;
    btn.dataset.original = original;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Generating...';
  });
});
</script>

