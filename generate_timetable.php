<?php
include 'connect.php';
// Ensure flash helper is available for PRG redirects
if (file_exists(__DIR__ . '/includes/flash.php')) include_once __DIR__ . '/includes/flash.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_timetable') {
        // Clear existing timetable
        $clear_sql = "DELETE FROM timetable";
        $conn->query($clear_sql);

        // Get available time slots
        $time_slots_sql = "SELECT id, start_time, end_time FROM time_slots WHERE is_mandatory = 1 ORDER BY start_time";
        $time_slots_result = $conn->query($time_slots_sql);
        $time_slots = [];
        while ($slot = $time_slots_result->fetch_assoc()) {
            $time_slots[] = $slot;
        }

        // Get all days (used as a fallback if a stream has no specific days)
        $days_sql = "SELECT id, name FROM days WHERE is_active = 1 ORDER BY id";
        $days_result = $conn->query($days_sql);
        $all_days = [];
        while ($day = $days_result->fetch_assoc()) {
            $all_days[] = $day;
        }

        // Get available rooms (rooms are global / not stream-aware)
        $rooms_sql = "SELECT id, name, capacity FROM rooms WHERE is_active = 1 ORDER BY capacity";
        $rooms_result = $conn->query($rooms_sql);
        $rooms = [];
        while ($room = $rooms_result->fetch_assoc()) {
            $rooms[] = $room;
        }

        // Get active streams to generate per-stream
        $streams_sql = "SELECT id, name FROM streams WHERE is_active = 1 ORDER BY id";
        $streams_result = $conn->query($streams_sql);

        if ($streams_result && $streams_result->num_rows > 0) {
            $success_count = 0;
            $error_count = 0;

            // Prepare assignment statement (we will bind stream_id per-iteration)
            $assignments_sql = "SELECT cc.id, cc.class_id, cc.course_id, c.name as class_name, co.course_code, co.course_name
                               FROM class_courses cc
                               JOIN classes c ON cc.class_id = c.id
                               JOIN courses co ON cc.course_id = co.id
                               WHERE cc.is_active = 1 AND c.stream_id = ?";
            $assignments_stmt = $conn->prepare($assignments_sql);

            // Prepare day lookup statement (if stream_days exists)
            $stream_days_enabled = false;
            $sd_check = $conn->query("SHOW TABLES LIKE 'stream_days'");
            if ($sd_check && $sd_check->num_rows > 0) {
                $stream_days_enabled = true;
                $stream_days_sql = "SELECT d.id, d.name FROM stream_days sd JOIN days d ON sd.day_id = d.id WHERE sd.stream_id = ? AND d.is_active = 1 ORDER BY d.id";
                $stream_days_stmt = $conn->prepare($stream_days_sql);
            }

            // prepare conflict-check statements once
            $check_room_sql = "SELECT COUNT(*) as count FROM timetable WHERE day_id = ? AND time_slot_id = ? AND room_id = ?";
            $check_room_stmt = $conn->prepare($check_room_sql);

            // check if class (via class_courses->class_id) already has an entry at the same day/time
            $check_class_sql = "SELECT COUNT(*) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id WHERE cc.class_id = ? AND t.day_id = ? AND t.time_slot_id = ?";
            $check_class_stmt = $conn->prepare($check_class_sql);

            // check if lecturer_course is already teaching at that time
            $check_lecturer_sql = "SELECT COUNT(*) as count FROM timetable WHERE lecturer_course_id = ? AND day_id = ? AND time_slot_id = ?";
            $check_lecturer_stmt = $conn->prepare($check_lecturer_sql);

            while ($stream = $streams_result->fetch_assoc()) {
                $stream_id = $stream['id'];

                // Determine days for this stream (use stream_days if available otherwise fallback to all days)
                $days = $all_days;
                if ($stream_days_enabled) {
                    $stream_days_stmt->bind_param('i', $stream_id);
                    $stream_days_stmt->execute();
                    $sd_result = $stream_days_stmt->get_result();
                    $stream_specific_days = [];
                    while ($d = $sd_result->fetch_assoc()) {
                        $stream_specific_days[] = $d;
                    }
                    if (count($stream_specific_days) > 0) {
                        $days = $stream_specific_days;
                    }
                }

                // Fetch assignments for classes in this stream
                $assignments_stmt->bind_param('i', $stream_id);
                $assignments_stmt->execute();
                $assignments_result = $assignments_stmt->get_result();

                // Load assignments into array and shuffle to avoid placement bias
                $assignments_list = [];
                while ($r = $assignments_result->fetch_assoc()) {
                    $assignments_list[] = $r;
                }
                if (count($assignments_list) > 1) shuffle($assignments_list);

                foreach ($assignments_list as $assignment) {
                    // Assign to a random time slot and a day from this stream's allowed days
                    if (empty($time_slots) || empty($days) || empty($rooms)) {
                        $error_count++;
                        continue;
                    }
                    // We'll attempt multiple retries to place this assignment in case of conflicts
                    $placed = false;
                    $max_attempts = 10;
                    for ($attempt = 0; $attempt < $max_attempts && !$placed; $attempt++) {
                        // pick random slot/day/room (shuffle sources to reduce bias)
                        $time_slot = $time_slots[array_rand($time_slots)];
                        $day = $days[array_rand($days)];
                        $room = $rooms[array_rand($rooms)];

                        // Check room/class/lecturer conflicts
                        $check_room_stmt->bind_param("iii", $day['id'], $time_slot['id'], $room['id']);
                        $check_room_stmt->execute();
                        $room_count = $check_room_stmt->get_result()->fetch_assoc()['count'];

                        // class id: need to fetch class_id for this assignment (we have class_id in the assignment row)
                        $check_class_stmt->bind_param("iii", $assignment['class_id'], $day['id'], $time_slot['id']);
                        $check_class_stmt->execute();
                        $class_count = $check_class_stmt->get_result()->fetch_assoc()['count'];

                        // we'll lookup lecturer_course later; for now assume lecturer might conflict after we fetch it
                        if ($room_count == 0 && $class_count == 0) {
                            // Get a default lecturer_course_id for this course
                            $lecturer_course_sql = "SELECT lc.id FROM lecturer_courses lc WHERE lc.course_id = ? LIMIT 1";
                            $lecturer_course_stmt = $conn->prepare($lecturer_course_sql);
                            $lecturer_course_stmt->bind_param("i", $assignment['course_id']);
                            $lecturer_course_stmt->execute();
                            $lecturer_course_result = $lecturer_course_stmt->get_result();
                            $lecturer_course = $lecturer_course_result->fetch_assoc();
                            $lecturer_course_stmt->close();

                            if ($lecturer_course) {
                                // Ensure lecturer is free at this day/time
                                $check_lecturer_stmt->bind_param("iii", $lecturer_course['id'], $day['id'], $time_slot['id']);
                                $check_lecturer_stmt->execute();
                                $lect_count = $check_lecturer_stmt->get_result()->fetch_assoc()['count'];
                                if ($lect_count > 0) {
                                    // lecturer busy, try another slot
                                    continue;
                                }

                                // Insert timetable entry with lecturer_course_id
                                $insert_sql = "INSERT INTO timetable (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id) VALUES (?, ?, ?, ?, ?)";
                                $insert_stmt = $conn->prepare($insert_sql);
                                $insert_stmt->bind_param("iiiii", $assignment['id'], $lecturer_course['id'], $day['id'], $time_slot['id'], $room['id']);

                                if ($insert_stmt->execute()) {
                                    $placed = true;
                                    $insert_stmt->close();
                                    break; // placed successfully
                                }
                                $insert_stmt->close();
                            } else {
                                // No lecturer assigned to this course, cannot place
                                break;
                            }
                        }
                    }
                    // after attempts
                    if (!$placed) {
                        $error_count++;
                    } else {
                        $success_count++;
                    }
                }
            }

            // close prepared statements if they exist
            if (isset($assignments_stmt) && $assignments_stmt) $assignments_stmt->close();
            if (isset($stream_days_stmt) && $stream_days_stmt) $stream_days_stmt->close();

            if ($success_count > 0) {
                $msg = "Timetable generated successfully! $success_count entries created.";
                if ($error_count > 0) {
                    $msg .= " $error_count entries failed.";
                }
                redirect_with_flash('generate_timetable.php', 'success', $msg);
            } else {
                $error_message = "No timetable entries could be generated.";
            }
        } else {
            $error_message = "No active streams found. Please create streams first.";
        }
    }
}

// Get statistics
$total_assignments = $conn->query("SELECT COUNT(*) as count FROM class_courses WHERE is_active = 1")->fetch_assoc()['count'];
$total_timetable_entries = $conn->query("SELECT COUNT(*) as count FROM timetable")->fetch_assoc()['count'];
$total_classes = $conn->query("SELECT COUNT(*) as count FROM classes WHERE is_active = 1")->fetch_assoc()['count'];
$total_courses = $conn->query("SELECT COUNT(*) as count FROM courses WHERE is_active = 1")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Timetable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card .card-body {
            padding: 1.5rem;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>Generate Timetable
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-2x mb-2"></i>
                                        <div class="stat-number"><?php echo $total_classes; ?></div>
                                        <div>Total Classes</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-book fa-2x mb-2"></i>
                                        <div class="stat-number"><?php echo $total_courses; ?></div>
                                        <div>Total Courses</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-link fa-2x mb-2"></i>
                                        <div class="stat-number"><?php echo $total_assignments; ?></div>
                                        <div>Assignments</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                        <div class="stat-number"><?php echo $total_timetable_entries; ?></div>
                                        <div>Timetable Entries</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Generation Controls -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Timetable Generation</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">
                                            This will generate a new timetable based on current class-course assignments.
                                            Any existing timetable entries will be cleared.
                                        </p>
                                        
                                        <form method="POST">
                                            <input type="hidden" name="action" value="generate_timetable">
                                            <button type="submit" class="btn btn-primary btn-lg" 
                                                    onclick="return confirm('This will clear the existing timetable and generate a new one. Continue?')">
                                                <i class="fas fa-magic me-2"></i>Generate New Timetable
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Quick Actions</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-grid gap-2">
                                            <a href="class_courses.php" class="btn btn-outline-primary">
                                                <i class="fas fa-link me-2"></i>Manage Assignments
                                            </a>
                                            <a href="view_timetable.php" class="btn btn-outline-success">
                                                <i class="fas fa-eye me-2"></i>View Timetable
                                            </a>
                                            <a href="export_timetable.php" class="btn btn-outline-info">
                                                <i class="fas fa-download me-2"></i>Export Timetable
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Assignments -->
                        <?php if ($total_assignments > 0): ?>
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Recent Class-Course Assignments</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Class</th>
                                                    <th>Course</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $recent_sql = "SELECT c.name as class_name, co.course_code, co.course_name
                                                              FROM class_courses cc
                                                              JOIN classes c ON cc.class_id = c.id
                                                              JOIN courses co ON cc.course_id = co.id
                                                              WHERE cc.is_active = 1
                                                              ORDER BY cc.created_at DESC
                                                              LIMIT 10";
                                                $recent_result = $conn->query($recent_sql);
                                                while ($row = $recent_result->fetch_assoc()):
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['course_code'] . ' - ' . $row['course_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-success">Active</span>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

