<?php
include 'connect.php';
if (file_exists(__DIR__ . '/includes/flash.php')) include_once __DIR__ . '/includes/flash.php';

$success_message = '';
$error_message = '';

// Allow auto-run via GET (redirect from generate_timetable.php) or via POST form
$auto_run = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auto_run = true;
    $request_source = 'POST';
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['exam_weeks']) || isset($_GET['semester']))) {
    // Run immediately when redirected with query params
    $_POST['exam_weeks'] = $_GET['exam_weeks'] ?? null;
    $_POST['semester'] = $_GET['semester'] ?? null;
    $_POST['action'] = 'generate_exams_timetable';
    $auto_run = true;
    $request_source = 'GET';
}

if ($auto_run && ($_SERVER['REQUEST_METHOD'] === 'POST' || $request_source === 'GET')) {
    $action = $_POST['action'] ?? null;
    if ($action === 'generate_exams_timetable') {
        $weeks = isset($_POST['exam_weeks']) ? intval($_POST['exam_weeks']) : 0;
        if ($weeks < 1 || $weeks > 3) {
            $error_message = 'Please specify a valid number of weeks for exams (1-3).';
        } else {
            $exam_weeks = $weeks;
            $semester_input = trim($_POST['semester'] ?? '');
            $semester_int = 0;
            if ($semester_input === '1' || strtolower($semester_input) === 'first') $semester_int = 1;
            if ($semester_input === '2' || strtolower($semester_input) === 'second') $semester_int = 2;
            $semester = $semester_int ?: 0;

            // Load days, time_slots, rooms
            $template_days = [];
            $rs = $conn->query("SELECT id, name FROM days WHERE is_active = 1 ORDER BY id");
            while ($r = $rs->fetch_assoc()) $template_days[] = $r;

            $template_time_slots = [];
            $ts_rs = $conn->query("SELECT id, start_time, end_time FROM time_slots ORDER BY start_time");
            while ($t = $ts_rs->fetch_assoc()) $template_time_slots[] = $t;

            $template_rooms = [];
            $rooms_rs = $conn->query("SELECT id, name, capacity FROM rooms WHERE is_active = 1 ORDER BY capacity");
            while ($rm = $rooms_rs->fetch_assoc()) $template_rooms[] = $rm;

            if (empty($template_days) || empty($template_time_slots) || empty($template_rooms)) {
                $error_message = 'Missing configuration: ensure active days, time slots and rooms exist.';
            } else {
                // Load class_courses
                $class_courses_sql = "SELECT cc.id AS cc_id, cc.class_id, cc.course_id, cc.lecturer_id, c.name AS class_name, co.code AS course_code
                                      FROM class_courses cc
                                      JOIN classes c ON cc.class_id = c.id
                                      JOIN courses co ON cc.course_id = co.id
                                      WHERE cc.is_active = 1
                                      ORDER BY co.code";
                $cc_rs = $conn->query($class_courses_sql);
                $groups = [];
                if ($cc_rs) {
                    while ($r = $cc_rs->fetch_assoc()) {
                        $course = $r['course_id'];
                        if (!isset($groups[$course])) $groups[$course] = [];
                        $groups[$course][] = $r;
                    }
                }

                // Map lecturer_courses per course
                $lect_map = [];
                $lec_rs = $conn->query("SELECT id, course_id, lecturer_id FROM lecturer_courses WHERE is_active = 1");
                if ($lec_rs) {
                    while ($lr = $lec_rs->fetch_assoc()) {
                        $cid = $lr['course_id'];
                        $lect_map[$cid][] = ['id' => $lr['id'], 'lecturer_id' => $lr['lecturer_id']];
                    }
                }

                // If lecturers are not used, ensure every course has at least one lecturer_course placeholder
                // so timetable inserts can satisfy the FK. We'll create inactive placeholders with lecturer_id = 0.
                foreach (array_keys($groups) as $gcid) {
                    if (empty($lect_map[$gcid])) {
                        $ins_sql = "INSERT INTO lecturer_courses (lecturer_id, course_id, is_active) VALUES (0, " . intval($gcid) . ", 0)";
                        if ($conn->query($ins_sql)) {
                            $newId = $conn->insert_id;
                            $lect_map[$gcid][] = ['id' => $newId, 'lecturer_id' => 0];
                        } else {
                            error_log('Failed to create placeholder lecturer_course for course ' . intval($gcid) . ': ' . $conn->error);
                        }
                    }
                }

                if (empty($groups)) {
                    $error_message = 'No class-course assignments found.';
                } elseif (empty($lect_map)) {
                    $error_message = 'No lecturer-course mappings found. Create lecturer_course records before generating exams.';
                } else {
                    // Build slots across weeks
                    $slots = [];
                    for ($wk = 1; $wk <= $exam_weeks; $wk++) {
                        foreach ($template_days as $day) {
                            foreach ($template_time_slots as $ts) {
                                $slots[] = ['week' => $wk, 'day_id' => $day['id'], 'time_slot_id' => $ts['id']];
                            }
                        }
                    }

                    $room_ids = array_values(array_map(function($r){ return $r['id']; }, $template_rooms));
                    $roomOccupied = [];
                    $classOccupied = [];
                    $exam_entries = [];
                    $unscheduled = [];

                    // Schedule by course: all classes offering a course sit together
                    foreach ($groups as $courseId => $items) {
                        // ensure there is at least one lecturer_course for this course; otherwise skip
                        $lec_candidates = $lect_map[$courseId] ?? [];
                        if (empty($lec_candidates)) {
                            $unscheduled[] = $courseId; // no lecturer_course mapping for this course
                            continue;
                        }
                        $scheduled = false;
                        $needed_rooms = count($items);
                        for ($sidx = 0; $sidx < count($slots); $sidx++) {
                            $slot = $slots[$sidx];
                            $used_rooms = $roomOccupied[$sidx] ?? [];

                            // class conflicts
                            $conflict = false;
                            foreach ($items as $it) {
                                if (isset($classOccupied[$it['class_id']]) && $classOccupied[$it['class_id']] == $sidx) { $conflict = true; break; }
                            }
                            if ($conflict) continue;

                            $available_rooms = array_values(array_diff($room_ids, $used_rooms));
                            if (count($available_rooms) < $needed_rooms) continue;

                            // choose lecturer_course id for this course
                            // $lec_candidates already set above
                            $default_lec_id = isset($lec_candidates[0]) ? $lec_candidates[0]['id'] : null;

                            for ($i = 0; $i < $needed_rooms; $i++) {
                                $room_id = $available_rooms[$i];
                                $it = $items[$i];
                                $lec_id = $default_lec_id;
                                // prefer matching lecturer
                                if (!empty($it['lecturer_id']) && !empty($lec_candidates)) {
                                    foreach ($lec_candidates as $lc) { if ($lc['lecturer_id'] == $it['lecturer_id']) { $lec_id = $lc['id']; break; } }
                                }

                                $exam_entries[] = [
                                    'class_course_id' => $it['cc_id'],
                                    'lecturer_course_id' => $lec_id,
                                    'day_id' => $slot['day_id'],
                                    'time_slot_id' => $slot['time_slot_id'],
                                    'room_id' => $room_id,
                                    'division_label' => '',
                                    'semester' => $semester
                                ];
                                $used_rooms[] = $room_id;
                                $classOccupied[$it['class_id']] = $sidx;
                            }

                            $roomOccupied[$sidx] = $used_rooms;
                            $scheduled = true;
                            break;
                        }
                        if (!$scheduled) $unscheduled[] = $courseId;
                    }

                    // Insert into DB
                    if (!empty($exam_entries)) {
                        // prefilter duplicates
                        $unique = [];
                        $seen = [];
                        foreach ($exam_entries as $e) {
                            $div = isset($e['division_label']) ? $e['division_label'] : '';
                            $key = $e['class_course_id'] . '-' . $e['day_id'] . '-' . $e['time_slot_id'] . '-' . $div;
                            if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $e; }
                        }

                        $inserted = 0;
                        $stmt = $conn->prepare("INSERT INTO timetable (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, division_label, semester, timetable_type) VALUES (?, ?, ?, ?, ?, ?, ?, 'exam')");
                        if ($stmt) {
                            foreach ($unique as $ue) {
                                // Ensure lecturer_course_id is not null; if null, try to pick any available mapping
                                if (empty($ue['lecturer_course_id'])) {
                                    $courseIdForUe = $conn->query('SELECT course_id FROM class_courses WHERE id = ' . intval($ue['class_course_id']))->fetch_assoc()['course_id'] ?? null;
                                    if ($courseIdForUe && isset($lect_map[$courseIdForUe]) && count($lect_map[$courseIdForUe]) > 0) {
                                        $ue['lecturer_course_id'] = $lect_map[$courseIdForUe][0]['id'];
                                    }
                                }

                                // If still null, skip to avoid FK error
                                if (empty($ue['lecturer_course_id'])) {
                                    error_log('Skipping exam insert due to missing lecturer_course_id for class_course_id: ' . intval($ue['class_course_id']));
                                    continue;
                                }

                                $stmt->bind_param('iiiiiss', $ue['class_course_id'], $ue['lecturer_course_id'], $ue['day_id'], $ue['time_slot_id'], $ue['room_id'], $ue['division_label'], $ue['semester']);
                                try {
                                    $stmt->execute();
                                    $inserted++;
                                } catch (mysqli_sql_exception $ex) {
                                    // Duplicate entry -> skip, other errors -> log
                                    if ($ex->getCode() == 1062) {
                                        error_log('Duplicate timetable entry skipped: ' . json_encode($ue));
                                        continue;
                                    } else {
                                        error_log('Exam insert error: ' . $ex->getMessage() . ' data:' . json_encode($ue));
                                    }
                                }
                            }
                            $stmt->close();
                        }

                        if ($inserted > 0) {
                            $success_message = "Generated exam timetable: $inserted entries for $exam_weeks week(s).";
                        } else {
                            $error_message = 'No exam entries inserted (possible duplicates or constraint failures).';
                        }
                    }

                    if (!empty($unscheduled)) {
                        error_log('Unscheduled courses: ' . json_encode($unscheduled));
                        if (empty($error_message)) $error_message = 'Some courses could not be scheduled automatically due to insufficient slots/rooms.';
                    }
                }
            }
        }
    }
}

// Ensure template arrays exist for rendering (fallback when page is opened without auto-run)
if (!isset($template_days) || !is_array($template_days) || count($template_days) === 0) {
    $template_days = [];
    $rs = $conn->query("SELECT id, name FROM days WHERE is_active = 1 ORDER BY id");
    if ($rs) {
        while ($r = $rs->fetch_assoc()) $template_days[] = $r;
    }
}
if (!isset($template_time_slots) || !is_array($template_time_slots) || count($template_time_slots) === 0) {
    $template_time_slots = [];
    $ts_rs = $conn->query("SELECT id, start_time, end_time FROM time_slots ORDER BY start_time");
    if ($ts_rs) {
        while ($t = $ts_rs->fetch_assoc()) $template_time_slots[] = $t;
    }
}
if (!isset($template_rooms) || !is_array($template_rooms) || count($template_rooms) === 0) {
    $template_rooms = [];
    $rooms_rs = $conn->query("SELECT id, name, capacity FROM rooms WHERE is_active = 1 ORDER BY capacity");
    if ($rooms_rs) {
        while ($rm = $rooms_rs->fetch_assoc()) $template_rooms[] = $rm;
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content" id="mainContent">
    <div class="table-container m-3">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-file-alt me-2"></i>Generate Exams Timetable</h4>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="generate_exams_timetable">
                    <div class="row g-3 align-items-center">
                        <div class="col-auto">
                            <label class="form-label">Semester</label>
                            <select name="semester" class="form-select">
                                <option value="">Select</option>
                                <option value="1">First</option>
                                <option value="2">Second</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <label class="form-label">Weeks (1-3)</label>
                            <input type="number" name="exam_weeks" class="form-control" min="1" max="3" value="1" required style="width:90px">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Generate Exams Timetable</button>
                        </div>
                    </div>
                </form>
                
                <?php
                // Pre-generation stats and readiness checks (similar to lecture generation)
                $total_assignments = $conn->query("SELECT COUNT(*) as count FROM class_courses WHERE is_active = 1")->fetch_assoc()['count'];
                $total_rooms = isset($template_rooms) && is_array($template_rooms) ? count($template_rooms) : 0;
                $total_days = isset($template_days) && is_array($template_days) ? count($template_days) : 0;
                $total_time_slots = isset($template_time_slots) && is_array($template_time_slots) ? count($template_time_slots) : 0;
                // Lecturers are not required for exams; hide this metric
                $total_lecturer_courses = 0;

                $readiness_issues = [];
                if ($total_assignments == 0) $readiness_issues[] = 'No class-course assignments';
                if ($total_time_slots == 0) $readiness_issues[] = 'No time slots available';
                if ($total_rooms == 0) $readiness_issues[] = 'No active rooms';
                if ($total_days == 0) $readiness_issues[] = 'No active days';
                // skip lecturer-course readiness check for exams

                $is_ready = count($readiness_issues) == 0;
                ?>

                <div class="mt-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Pre-Generation Conditions</h6>
                            <?php if ($is_ready): ?>
                                <span class="badge bg-success">Ready</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Issues Found</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-2">
                                    <div class="card theme-card <?php echo $total_assignments > 0 ? 'bg-theme-green text-white' : 'bg-theme-secondary text-dark'; ?> mb-2">
                                        <div class="card-body">
                                            <div class="stat-number"><?php echo $total_assignments; ?></div>
                                            <div>Assignments</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="card theme-card <?php echo $total_time_slots > 0 ? 'bg-theme-green text-white' : 'bg-theme-secondary text-dark'; ?> mb-2">
                                        <div class="card-body">
                                            <div class="stat-number"><?php echo $total_time_slots; ?></div>
                                            <div>Time Slots</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="card theme-card <?php echo $total_rooms > 0 ? 'bg-theme-green text-white' : 'bg-theme-secondary text-dark'; ?> mb-2">
                                        <div class="card-body">
                                            <div class="stat-number"><?php echo $total_rooms; ?></div>
                                            <div>Rooms</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="card theme-card <?php echo $total_days > 0 ? 'bg-theme-green text-white' : 'bg-theme-secondary text-dark'; ?> mb-2">
                                        <div class="card-body">
                                            <div class="stat-number"><?php echo $total_days; ?></div>
                                            <div>Days</div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Lecturers metric removed for exams -->
                                <div class="col-md-2">
                                    <div class="card theme-card <?php echo $is_ready ? 'bg-theme-green text-white' : 'bg-theme-warning text-dark'; ?> mb-2">
                                        <div class="card-body">
                                            <div class="stat-number"><?php echo $is_ready ? '<i class="fas fa-check"></i>' : '<i class="fas fa-exclamation-triangle"></i>'; ?></div>
                                            <div>Status</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!$is_ready): ?>
                                <div class="mt-3 alert alert-warning">
                                    <h6>Issues to Resolve:</h6>
                                    <ul>
                                        <?php foreach ($readiness_issues as $issue): ?>
                                            <li><?php echo htmlspecialchars($issue); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Template skeleton preview -->
                <div class="mt-4">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Timetable Template (Skeleton)</h6></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm timetable-template">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width:150px;">Day / Room</th>
                                            <?php foreach ($template_time_slots as $ts): ?>
                                                <th class="text-center"><?php echo htmlspecialchars(substr($ts['start_time'],0,5)); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($template_days as $day): ?>
                                            <tr class="day-header-row"><td colspan="<?php echo count($template_time_slots) + 1; ?>" class="day-header"><?php echo htmlspecialchars($day['name']); ?></td></tr>
                                            <?php foreach ($template_rooms as $room): ?>
                                                <tr>
                                                    <td class="room-name-cell"><?php echo htmlspecialchars($room['name']); ?> <small class="text-muted">(<?php echo $room['capacity']; ?>)</small></td>
                                                    <?php foreach ($template_time_slots as $ts): ?>
                                                        <td class="template-cell"><div class="template-placeholder"><small class="text-muted">Empty</small></div></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


