<?php
include 'connect.php';

// Get filter parameters
$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$day_filter = isset($_GET['day_id']) ? (int)$_GET['day_id'] : 0;
$room_filter = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

// Build the main query
$main_query = "
    SELECT 
        t.id,
        c.name as class_name,
        co.course_code,
        co.course_name,
        d.name as day_name,
        ts.start_time,
        ts.end_time,
        r.name as room_name,
        r.capacity,
        l.name as lecturer_name
    FROM timetable t
    JOIN class_courses cc ON t.class_course_id = cc.id
    JOIN classes c ON cc.class_id = c.id
    JOIN courses co ON cc.course_id = co.id
    JOIN working_days d ON t.day_id = d.id
    JOIN time_slots ts ON t.time_slot_id = ts.id
    JOIN rooms r ON t.room_id = r.id
    LEFT JOIN lecturer_courses lc ON co.id = lc.course_id
    LEFT JOIN lecturers l ON lc.lecturer_id = l.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($class_filter > 0) {
    $main_query .= " AND c.id = ?";
    $params[] = $class_filter;
    $types .= "i";
}

if ($day_filter > 0) {
    $main_query .= " AND d.id = ?";
    $params[] = $day_filter;
    $types .= "i";
}

if ($room_filter > 0) {
    $main_query .= " AND r.id = ?";
    $params[] = $room_filter;
    $types .= "i";
}

$main_query .= " ORDER BY d.id, ts.start_time, c.name";

// Execute the main query
$stmt = $conn->prepare($main_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$timetable_result = $stmt->get_result();

// Get filter options
$classes_result = $conn->query("SELECT id, name FROM classes WHERE is_active = 1 ORDER BY name");
$days_result = $conn->query("SELECT id, name FROM working_days WHERE is_active = 1 ORDER BY id");
$rooms_result = $conn->query("SELECT id, name FROM rooms WHERE is_active = 1 ORDER BY name");

// Get statistics
$total_entries = $conn->query("SELECT COUNT(*) as count FROM timetable")->fetch_assoc()['count'];
$total_classes = $conn->query("SELECT COUNT(DISTINCT c.id) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN classes c ON cc.class_id = c.id")->fetch_assoc()['count'];
$total_courses = $conn->query("SELECT COUNT(DISTINCT co.id) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN courses co ON cc.course_id = co.id")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetable</title>
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
        .timetable-table {
            font-size: 0.9rem;
        }
        .timetable-table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .time-slot {
            font-weight: bold;
            color: #495057;
        }
        .course-info {
            font-size: 0.85rem;
        }
        .room-info {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt me-2"></i>View Timetable
                        </h5>
                        <div class="d-flex gap-2">
                            <a href="generate_timetable.php" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-2"></i>Generate Timetable
                            </a>
                            <a href="export_timetable.php" class="btn btn-outline-success">
                                <i class="fas fa-download me-2"></i>Export
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                        <div class="stat-number"><?php echo $total_entries; ?></div>
                                        <div>Total Entries</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-users fa-2x mb-2"></i>
                                        <div class="stat-number"><?php echo $total_classes; ?></div>
                                        <div>Classes</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-book fa-2x mb-2"></i>
                                        <div class="stat-number"><?php echo $total_courses; ?></div>
                                        <div>Courses</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-clock fa-2x mb-2"></i>
                                        <div class="stat-number"><?php echo $days_result->num_rows; ?></div>
                                        <div>Working Days</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">Filters</h6>
                            </div>
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-3">
                                        <label for="class_id" class="form-label">Class</label>
                                        <select name="class_id" id="class_id" class="form-select">
                                            <option value="">All Classes</option>
                                            <?php while ($class = $classes_result->fetch_assoc()): ?>
                                                <option value="<?php echo $class['id']; ?>" 
                                                        <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($class['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="day_id" class="form-label">Day</label>
                                        <select name="day_id" id="day_id" class="form-select">
                                            <option value="">All Days</option>
                                            <?php while ($day = $days_result->fetch_assoc()): ?>
                                                <option value="<?php echo $day['id']; ?>" 
                                                        <?php echo $day_filter == $day['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($day['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="room_id" class="form-label">Room</label>
                                        <select name="room_id" id="room_id" class="form-select">
                                            <option value="">All Rooms</option>
                                            <?php while ($room = $rooms_result->fetch_assoc()): ?>
                                                <option value="<?php echo $room['id']; ?>" 
                                                        <?php echo $room_filter == $room['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($room['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <div class="d-flex gap-2 w-100">
                                            <button type="submit" class="btn btn-primary flex-fill">
                                                <i class="fas fa-filter me-2"></i>Apply Filters
                                            </button>
                                            <a href="view_timetable.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times me-2"></i>Clear
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Timetable Display -->
                        <?php if ($timetable_result->num_rows > 0): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        Timetable Entries (<?php echo $timetable_result->num_rows; ?>)
                                        <?php if ($class_filter || $day_filter || $room_filter): ?>
                                            <span class="badge bg-info ms-2">Filtered</span>
                                        <?php endif; ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover timetable-table">
                                            <thead>
                                                <tr>
                                                    <th>Day</th>
                                                    <th>Time</th>
                                                    <th>Class</th>
                                                    <th>Course</th>
                                                    <th>Room</th>
                                                    <th>Lecturer</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($entry = $timetable_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($entry['day_name']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="time-slot">
                                                            <?php echo htmlspecialchars(substr($entry['start_time'], 0, 5) . ' - ' . substr($entry['end_time'], 0, 5)); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="course-info">
                                                            <?php echo htmlspecialchars($entry['class_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="course-info">
                                                            <strong><?php echo htmlspecialchars($entry['course_code']); ?></strong><br>
                                                            <small><?php echo htmlspecialchars($entry['course_name']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="room-info">
                                                            <?php echo htmlspecialchars($entry['room_name']); ?><br>
                                                            <small>Capacity: <?php echo $entry['capacity']; ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="course-info">
                                                            <?php echo $entry['lecturer_name'] ? htmlspecialchars($entry['lecturer_name']) : '<span class="text-muted">Not assigned</span>'; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="update_timetable.php?id=<?php echo $entry['id']; ?>" 
                                                               class="btn btn-outline-primary btn-sm" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                                                    onclick="deleteEntry(<?php echo $entry['id']; ?>)" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body text-center py-5">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Timetable Entries Found</h5>
                                    <p class="text-muted">
                                        <?php if ($class_filter || $day_filter || $room_filter): ?>
                                            No entries match the current filters. Try adjusting your filters.
                                        <?php else: ?>
                                            No timetable entries have been generated yet. 
                                            <a href="generate_timetable.php">Generate a timetable</a> to get started.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteEntry(id) {
            if (confirm('Are you sure you want to delete this timetable entry? This action cannot be undone.')) {
                // Create a form to submit the delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'update_timetable.php';
                
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
    </script>
</body>
</html>
