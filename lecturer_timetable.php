<?php
$pageTitle = 'Lecturer Timetable';
include 'includes/header.php';
include 'includes/sidebar.php';
include 'connect.php';

$error_message = '';

$selected_session = isset($_POST['session']) ? (int)$_POST['session'] : 0;
$selected_lecturer = isset($_POST['lecturer']) ? (int)$_POST['lecturer'] : 0;

// Fetch sessions and lecturers
$sessions_result = $conn->query("SELECT id, semester_name, academic_year FROM sessions WHERE is_active = 1 ORDER BY academic_year DESC, semester_number");
$lecturers_result = $conn->query("SELECT id, name FROM lecturers WHERE is_active = 1 ORDER BY name");

// Fetch days and hourly time slots
$days = [];
$res = $conn->query("SELECT id, name FROM days ORDER BY id");
if ($res) { while ($r = $res->fetch_assoc()) { $days[] = $r; } }

$time_slots = [];
$res = $conn->query("SELECT id, start_time, end_time FROM time_slots WHERE is_break = 0 ORDER BY start_time");
if ($res) { while ($r = $res->fetch_assoc()) { $time_slots[] = $r; } }

$entries = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_session > 0 && $selected_lecturer > 0) {
    $sql = "SELECT 
                d.name AS day_name,
                ts.start_time,
                ts.end_time,
                c.name AS course_name,
                c.code AS course_code,
                cl.name AS class_name,
                r.name AS room_name,
                r.building AS room_building
            FROM timetable t
            JOIN days d ON d.id = t.day_id
            JOIN time_slots ts ON ts.id = t.time_slot_id
            JOIN lecturer_courses lc ON lc.id = t.lecturer_course_id
            JOIN courses c ON c.id = lc.course_id
            JOIN class_courses cc ON cc.id = t.class_course_id
            JOIN classes cl ON cl.id = cc.class_id
            JOIN rooms r ON r.id = t.room_id
            WHERE t.session_id = ? AND lc.lecturer_id = ?
            ORDER BY d.id, ts.start_time";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $selected_session, $selected_lecturer);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) { $entries = $res->fetch_all(MYSQLI_ASSOC); }
    $stmt->close();
}
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-user me-2"></i>Lecturer Timetable</h4>
            <?php if (!empty($entries)): ?>
            <div>
                <button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="card m-3">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-5">
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
                    <div class="col-md-5">
                        <label class="form-label">Lecturer</label>
                        <select name="lecturer" class="form-select" required>
                            <option value="">Choose a lecturer...</option>
                            <?php if ($lecturers_result) { while ($l = $lecturers_result->fetch_assoc()) { ?>
                                <option value="<?php echo $l['id']; ?>" <?php echo ($selected_lecturer == $l['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($l['name']); ?>
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

        <?php if (!empty($entries)): ?>
        <div class="timetable-container m-3">
            <div class="table-responsive">
                <table class="table table-bordered timetable-grid">
                    <thead>
                        <tr>
                            <th class="time-header">Time</th>
                            <?php foreach ($days as $day): ?>
                                <th class="day-header"><?php echo htmlspecialchars($day['name']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($time_slots as $time_slot): ?>
                            <tr>
                                <td class="time-cell">
                                    <div class="time-info">
                                        <div class="start-time"><?php echo date('H:i', strtotime($time_slot['start_time'])); ?></div>
                                        <div class="end-time"><?php echo date('H:i', strtotime($time_slot['end_time'])); ?></div>
                                    </div>
                                </td>
                                <?php foreach ($days as $day): ?>
                                    <td class="timetable-cell">
                                        <?php
                                        $entry = null;
                                        foreach ($entries as $data) {
                                            if ($data['day_name'] === $day['name'] && $data['start_time'] === $time_slot['start_time']) {
                                                $entry = $data; break;
                                            }
                                        }
                                        if ($entry): ?>
                                            <div class="timetable-entry">
                                                <div class="course-name"><?php echo htmlspecialchars($entry['course_name']); ?></div>
                                                <div class="course-code"><?php echo htmlspecialchars($entry['course_code']); ?></div>
                                                <div class="room"><?php echo htmlspecialchars($entry['room_name'] . ' (' . $entry['room_building'] . ')'); ?></div>
                                                <div class="class-name"><?php echo htmlspecialchars($entry['class_name']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="alert alert-info m-3"><i class="fas fa-info-circle me-2"></i>No entries found.</div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

