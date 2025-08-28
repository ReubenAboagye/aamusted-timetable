<?php
$pageTitle = 'Rooms Timetable';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'connect.php';

$error_message = '';
$selected_session = isset($_POST['session']) ? (int)$_POST['session'] : 0;

// Sessions
$sessions_result = $conn->query("SELECT id, semester_name, academic_year FROM sessions WHERE is_active = 1 ORDER BY academic_year DESC, semester_number");

// Days and time slots
$days = [];
$res = $conn->query("SELECT id, name FROM days ORDER BY id");
if ($res) { while ($r = $res->fetch_assoc()) { $days[] = $r; } }

$time_slots = [];
$res = $conn->query("SELECT id, start_time, end_time FROM time_slots WHERE is_break = 0 ORDER BY start_time");
if ($res) { while ($r = $res->fetch_assoc()) { $time_slots[] = $r; } }

// Rooms
$rooms = [];
$res = $conn->query("SELECT id, name, building FROM rooms WHERE is_active = 1 ORDER BY building, name");
if ($res) { while ($r = $res->fetch_assoc()) { $rooms[] = $r; } }

// Fetch entries map keyed by room_id|day|time
$entriesMap = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_session > 0) {
    $sql = "SELECT 
                t.room_id, d.name AS day_name, ts.start_time,
                c.name AS course_name, c.code AS course_code,
                l.name AS lecturer_name, cl.name AS class_name
            FROM timetable t
            JOIN days d ON d.id = t.day_id
            JOIN time_slots ts ON ts.id = t.time_slot_id
            JOIN class_courses cc ON cc.id = t.class_course_id
            JOIN courses c ON c.id = cc.course_id
            JOIN lecturer_courses lc ON lc.id = t.lecturer_course_id
            JOIN lecturers l ON l.id = lc.lecturer_id
            JOIN classes cl ON cl.id = cc.class_id
            WHERE t.session_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $selected_session);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $key = $row['room_id'] . '|' . $row['day_name'] . '|' . $row['start_time'];
        $entriesMap[$key] = $row;
    }
    $stmt->close();
}
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-building me-2"></i>Rooms Timetable</h4>
            <?php if (!empty($entriesMap)): ?>
            <div>
                <button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-file-pdf me-2"></i>Export PDF</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="card m-3">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-10">
                        <label class="form-label">Session</label>
                        <select name="session" class="form-select" required>
                            <option value="">Choose a session...</option>
                            <?php if ($sessions_result) { while ($s = $sessions_result->fetch_assoc()) { ?>
                                <option value="<?php echo $s['id']; ?>" <?php echo ($selected_session == $s['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($s['semester_name'] . ' (' . $s['academic_year'] . ')'); ?>
                                </option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>View</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_session > 0): ?>
        <div class="table-responsive m-3">
            <table class="table table-bordered timetable-grid">
                <thead>
                    <tr>
                        <th class="time-header">Time</th>
                        <?php foreach ($days as $day): ?>
                            <?php foreach ($rooms as $room): ?>
                                <th class="day-header"><?php echo htmlspecialchars($day['name']); ?><br><?php echo htmlspecialchars($room['name'] . ' (' . $room['building'] . ')'); ?></th>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($time_slots as $slot): ?>
                        <tr>
                            <td class="time-cell">
                                <div class="time-info">
                                    <div class="start-time"><?php echo date('H:i', strtotime($slot['start_time'])); ?></div>
                                    <div class="end-time"><?php echo date('H:i', strtotime($slot['end_time'])); ?></div>
                                </div>
                            </td>
                            <?php foreach ($days as $day): ?>
                                <?php foreach ($rooms as $room): ?>
                                    <td class="timetable-cell">
                                        <?php
                                            $key = $room['id'] . '|' . $day['name'] . '|' . $slot['start_time'];
                                            if (isset($entriesMap[$key])) {
                                                $e = $entriesMap[$key];
                                        ?>
                                            <div class="timetable-entry">
                                                <div class="course-name"><?php echo htmlspecialchars($e['course_name']); ?></div>
                                                <div class="course-code"><?php echo htmlspecialchars($e['course_code']); ?></div>
                                                <div class="lecturer"><?php echo htmlspecialchars($e['lecturer_name']); ?></div>
                                                <div class="class-name"><?php echo htmlspecialchars($e['class_name']); ?></div>
                                            </div>
                                        <?php } ?>
                                    </td>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

