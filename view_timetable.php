<?php
include 'connect.php';

// Page title and layout includes
$pageTitle = 'View Timetable';
include 'includes/header.php';
include 'includes/sidebar.php';

// Get filter parameters
$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$day_filter = isset($_GET['day_id']) ? (int)$_GET['day_id'] : 0;
$room_filter = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
// Stream filter from GET (can override session)
$stream_filter = isset($_GET['stream_id']) ? (int)$_GET['stream_id'] : 0;

// Respect header/session stream selection by default
if (file_exists(__DIR__ . '/includes/stream_manager.php')) {
    include_once __DIR__ . '/includes/stream_manager.php';
    $streamManager = getStreamManager();
    $session_stream_id = $streamManager->getCurrentStreamId();
} else {
    $session_stream_id = 0;
}

// Resolved stream: GET overrides session
$resolved_stream_id = $stream_filter > 0 ? $stream_filter : $session_stream_id;

// Build the main query for timetable data. Support both schema variants:
// - New schema: timetable.class_course_id, timetable.lecturer_course_id
// - Old schema: timetable.class_id, timetable.course_id, timetable.lecturer_id

$has_class_course = false;
$has_lecturer_course = false;
$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
if ($col && $col->num_rows > 0) { $has_class_course = true; }
$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'lecturer_course_id'");
if ($col && $col->num_rows > 0) { $has_lecturer_course = true; }

// Build select and join clauses depending on schema
$select_parts = [
    "t.id",
    "t.day_id",
    "t.time_slot_id",
    "d.name as day_name",
    "ts.start_time",
    "ts.end_time",
    "r.name as room_name",
    "r.capacity"
];
$join_parts = [];

if ($has_class_course) {
    // timetable stores class_course_id
    $select_parts[] = "c.name as class_name";
    $select_parts[] = "co.`code` AS course_code";
    $select_parts[] = "co.`name` AS course_name";
    $select_parts[] = "cc.id as class_course_id";
    $join_parts[] = "JOIN class_courses cc ON t.class_course_id = cc.id";
    $join_parts[] = "JOIN classes c ON cc.class_id = c.id";
    $join_parts[] = "JOIN courses co ON cc.course_id = co.id";
} else {
    // timetable stores class_id and course_id directly
    $select_parts[] = "c.name as class_name";
    $select_parts[] = "co.`code` AS course_code";
    $select_parts[] = "co.`name` AS course_name";
    $select_parts[] = "NULL as class_course_id";
    $join_parts[] = "JOIN classes c ON t.class_id = c.id";
    $join_parts[] = "JOIN courses co ON t.course_id = co.id";
}

if ($has_lecturer_course) {
    $select_parts[] = "l.name as lecturer_name";
    $select_parts[] = "l.id as lecturer_id";
    $select_parts[] = "lc.id as lecturer_course_id";
    $join_parts[] = "LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id";
    $join_parts[] = "LEFT JOIN lecturers l ON lc.lecturer_id = l.id";
} else {
    $select_parts[] = "l.name as lecturer_name";
    $select_parts[] = "l.id as lecturer_id";
    $select_parts[] = "NULL as lecturer_course_id";
    $join_parts[] = "LEFT JOIN lecturers l ON t.lecturer_id = l.id";
}

$main_query = "SELECT " . implode(",\n        ", $select_parts) . "\n    FROM timetable t\n        " . implode("\n        ", $join_parts) . "\n        JOIN days d ON t.day_id = d.id\n        JOIN time_slots ts ON t.time_slot_id = ts.id\n        JOIN rooms r ON t.room_id = r.id\n    WHERE 1=1";

// Apply stream filter when classes have a stream_id column and a resolved stream is set
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
$has_stream_col = ($col && $col->num_rows > 0);
if ($has_stream_col && !empty($resolved_stream_id)) {
    // All joins include classes as alias `c` (either via cc->c or t->c), so we can safely filter on c.stream_id
    $main_query .= " AND c.stream_id = " . intval($resolved_stream_id);
}
if ($col) $col->close();

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

// Note: courses are global (no stream-specific filtering) so we do not apply stream filter here.

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
$days_result = $conn->query("SELECT id, name FROM days ORDER BY id");
$rooms_result = $conn->query("SELECT id, name FROM rooms WHERE is_active = 1 ORDER BY name");
$streams_result = $conn->query("SELECT id, name FROM streams WHERE is_active = 1 ORDER BY name");
// Use selected stream's slots via mapping; fallback to mandatory global slots when no stream filter
if (!empty($resolved_stream_id)) {
    $time_slots_result = $conn->query("SELECT ts.id, ts.start_time, ts.end_time FROM stream_time_slots sts JOIN time_slots ts ON ts.id = sts.time_slot_id WHERE sts.stream_id = " . intval($resolved_stream_id) . " AND sts.is_active = 1 ORDER BY ts.start_time");
    if (!$time_slots_result || $time_slots_result->num_rows === 0) {
        $time_slots_result = $conn->query("SELECT id, start_time, end_time FROM time_slots WHERE is_mandatory = 1 ORDER BY start_time");
    }
} else {
    $time_slots_result = $conn->query("SELECT id, start_time, end_time FROM time_slots WHERE is_mandatory = 1 ORDER BY start_time");
}

// Get statistics (respect resolved stream when possible)
$total_entries = 0;
$total_classes = 0;
$total_courses = 0;

// Detect schema variants
$tcol = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
$has_t_class_course = ($tcol && $tcol->num_rows > 0);
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
$has_stream_col = ($col && $col->num_rows > 0);

// Total entries: if classes are stream-aware and we have a resolved stream, join through classes and filter
if ($has_stream_col && !empty($resolved_stream_id)) {
    if ($has_t_class_course) {
        $sql = "SELECT COUNT(*) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN classes c ON cc.class_id = c.id WHERE c.stream_id = " . intval($resolved_stream_id);
    } else {
        $sql = "SELECT COUNT(*) as count FROM timetable t JOIN classes c ON t.class_id = c.id WHERE c.stream_id = " . intval($resolved_stream_id);
    }
    $res = $conn->query($sql);
    $total_entries = $res ? (int)$res->fetch_assoc()['count'] : 0;
} else {
    $total_entries = $conn->query("SELECT COUNT(*) as count FROM timetable")->fetch_assoc()['count'];
}

// Total classes / courses in timetable (distinct) with optional stream filter
if ($has_t_class_course) {
    if ($has_stream_col && !empty($resolved_stream_id)) {
        $total_classes = $conn->query("SELECT COUNT(DISTINCT c.id) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN classes c ON cc.class_id = c.id WHERE c.stream_id = " . intval($resolved_stream_id))->fetch_assoc()['count'];
        $total_courses = $conn->query("SELECT COUNT(DISTINCT co.id) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN classes c ON cc.class_id = c.id JOIN courses co ON cc.course_id = co.id WHERE c.stream_id = " . intval($resolved_stream_id))->fetch_assoc()['count'];
    } else {
        $total_classes = $conn->query("SELECT COUNT(DISTINCT c.id) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN classes c ON cc.class_id = c.id")->fetch_assoc()['count'];
        $total_courses = $conn->query("SELECT COUNT(DISTINCT co.id) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN courses co ON cc.course_id = co.id")->fetch_assoc()['count'];
    }
} else {
    if ($has_stream_col && !empty($resolved_stream_id)) {
        $total_classes = $conn->query("SELECT COUNT(DISTINCT c.id) as count FROM timetable t JOIN classes c ON t.class_id = c.id WHERE c.stream_id = " . intval($resolved_stream_id))->fetch_assoc()['count'];
        $total_courses = $conn->query("SELECT COUNT(DISTINCT co.id) as count FROM timetable t JOIN classes c ON t.class_id = c.id JOIN courses co ON t.course_id = co.id WHERE c.stream_id = " . intval($resolved_stream_id))->fetch_assoc()['count'];
    } else {
        $total_classes = $conn->query("SELECT COUNT(DISTINCT c.id) as count FROM timetable t JOIN classes c ON t.class_id = c.id")->fetch_assoc()['count'];
        $total_courses = $conn->query("SELECT COUNT(DISTINCT co.id) as count FROM timetable t JOIN courses co ON t.course_id = co.id")->fetch_assoc()['count'];
    }
}

if ($tcol) $tcol->close();
if ($col) $col->close();

// Get statistics about multiple classes and overlapping courses
$multiple_classes_count = 0;
$overlapping_courses_count = 0;

// Ensure timetable_grid is defined (may be built later) to avoid warnings
if (!isset($timetable_grid) || !is_array($timetable_grid)) {
    $timetable_grid = [];
}

if (!empty($timetable_grid)) {
    foreach ($timetable_grid as $day_id => $day_slots) {
        foreach ($day_slots as $time_slot_id => $entries) {
            if (count($entries) > 1) {
                $multiple_classes_count++;
                
                // Check for overlapping courses
                $course_codes = array_column($entries, 'course_code');
                if (count(array_unique($course_codes)) < count($course_codes)) {
                    $overlapping_courses_count++;
                }
            }
        }
    }
}

// Organize timetable data by day and time slot
$timetable_grid = [];
$days = [];
$time_slots = [];

while ($day = $days_result->fetch_assoc()) {
    $days[$day['id']] = $day['name'];
}

while ($slot = $time_slots_result->fetch_assoc()) {
    $time_slots[$slot['id']] = $slot;
}

// Reset result sets for reuse
$days_result->data_seek(0);
$time_slots_result->data_seek(0);

// Populate timetable grid
while ($entry = $timetable_result->fetch_assoc()) {
    $day_id = $entry['day_id'];
    $time_slot_id = $entry['time_slot_id'];
    
    if (!isset($timetable_grid[$day_id][$time_slot_id])) {
        $timetable_grid[$day_id][$time_slot_id] = [];
    }
    
    $timetable_grid[$day_id][$time_slot_id][] = $entry;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Timetable</title>
    <!-- Bootstrap CSS and JS are included globally in includes/header.php -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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
        .timetable-grid {
            overflow-x: auto;
        }
        .timetable-header {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .time-slot-header {
            background-color: #e9ecef;
            font-weight: bold;
            text-align: center;
            padding: 0.5rem;
            border: 1px solid #dee2e6;
        }
        .day-header {
            background-color: #495057;
            color: white;
            font-weight: bold;
            text-align: center;
            padding: 1rem;
            border: 1px solid #dee2e6;
        }
        .timetable-cell {
            min-height: 120px;
            padding: 0.5rem;
            border: 1px solid #dee2e6;
            background-color: #f8f9fa;
        }
        .course-card {
            /* use site theme maroon */
            background: linear-gradient(135deg, #7a1533 0%, #800020 100%);
            color: white;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            padding: 0.5rem;
            border-radius: 0.375rem;
        }
        .course-card:hover {
            transform: scale(1.02);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.2);
        }
        .course-card.multiple {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        }
        .course-card.overlapping {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
        }
        .course-card.overlapping .lecturer-name {
            color: #212529;
        }
        .timetable-cell {
            min-height: 120px;
            padding: 0.5rem;
            border: 1px solid #dee2e6;
            background-color: #f8f9fa;
            position: relative;
        }
        .timetable-cell.has-multiple {
            background-color: #fff3cd;
        }
        .timetable-cell.has-overlap {
            background-color: #f8d7da;
        }
        .course-card .class-name {
            font-weight: bold;
            font-size: 0.9rem;
        }
        .course-card .course-code {
            font-size: 0.8rem;
            opacity: 0.9;
        }
        .course-card .lecturer-name {
            font-size: 0.75rem;
            opacity: 0.8;
            font-style: italic;
        }
        .empty-cell {
            background-color: #f8f9fa;
            color: #6c757d;
            text-align: center;
            padding: 2rem 0;
            font-style: italic;
        }
        .filters-section {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .view-toggle {
            margin-bottom: 1rem;
        }
        .view-toggle .btn {
            margin-right: 0.5rem;
        }
        .view-toggle .btn.active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>

    <div class="main-content" id="mainContent">
        <div class="table-container">
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
                                    <div class="stat-number"><?php echo count($days); ?></div>
                                    <div>Working Days</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Statistics Row -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card stat-card" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                                <div class="card-body text-center">
                                    <i class="fas fa-layer-group fa-2x mb-2"></i>
                                    <div class="stat-number"><?php echo $multiple_classes_count; ?></div>
                                    <div>Multiple Class Slots</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #212529;">
                                <div class="card-body text-center">
                                    <i class="fas fa-copy fa-2x mb-2"></i>
                                    <div class="stat-number"><?php echo $overlapping_courses_count; ?></div>
                                    <div>Overlapping Courses</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stat-card" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                                <div class="card-body text-center">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <div class="stat-number"><?php echo count($time_slots); ?></div>
                                    <div>Time Periods</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- View Toggle -->
                    <div class="view-toggle">
                        <button class="btn btn-outline-primary active" onclick="showGridView()">
                            <i class="fas fa-th me-2"></i>Grid View
                        </button>
                        <button class="btn btn-outline-secondary" onclick="showListView()">
                            <i class="fas fa-list me-2"></i>List View
                        </button>
                    </div>

                    <!-- Filters -->
                    <div class="filters-section">
                        <h6 class="mb-3">Filters</h6>
                        <form method="GET" class="row g-3">
                            <div class="col-md-2">
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
                            <div class="col-md-2">
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
                            <div class="col-md-2">
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
                            <div class="col-md-2">
                                <label for="stream_id" class="form-label">Stream</label>
                                <select name="stream_id" id="stream_id" class="form-select">
                                    <option value="">All Streams</option>
                                    <?php while ($stream = $streams_result->fetch_assoc()): ?>
                                        <option value="<?php echo $stream['id']; ?>" 
                                                <?php echo $stream_filter == $stream['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($stream['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
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

                    <!-- Grid View -->
                    <div id="grid-view">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-th me-2"></i>Timetable Grid View
                                    <?php if ($class_filter || $day_filter || $room_filter || $stream_filter): ?>
                                        <span class="badge bg-info ms-2">Filtered</span>
                                    <?php endif; ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="timetable-grid">
                                    <table class="table table-bordered">
                                        <thead class="timetable-header">
                                            <tr>
                                                <th class="time-slot-header">Time</th>
                                                <?php foreach ($days as $day_id => $day_name): ?>
                                                    <th class="day-header"><?php echo htmlspecialchars($day_name); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($time_slots as $time_slot_id => $time_slot): ?>
                                            <tr>
                                                <td class="time-slot-header">
                                                    <?php echo htmlspecialchars(substr($time_slot['start_time'], 0, 5) . ' - ' . substr($time_slot['end_time'], 0, 5)); ?>
                                                </td>
                                                <?php foreach ($days as $day_id => $day_name): ?>
                                                    <td class="timetable-cell <?php 
                                                        if (isset($timetable_grid[$day_id][$time_slot_id]) && !empty($timetable_grid[$day_id][$time_slot_id])) {
                                                            $entries = $timetable_grid[$day_id][$time_slot_id];
                                                            if (count($entries) > 1) {
                                                                echo 'has-multiple';
                                                            }
                                                            // Check for overlapping courses (same course in multiple classes)
                                                            $course_ids = array_column($entries, 'course_code');
                                                            if (count(array_unique($course_ids)) < count($course_ids)) {
                                                                echo ' has-overlap';
                                                            }
                                                        }
                                                    ?>">
                                                        <?php 
                                                        if (isset($timetable_grid[$day_id][$time_slot_id]) && !empty($timetable_grid[$day_id][$time_slot_id])): 
                                                            $entries = $timetable_grid[$day_id][$time_slot_id];
                                                            $course_counts = array_count_values(array_column($entries, 'course_code'));
                                                            foreach ($entries as $entry): 
                                                                $is_overlapping = $course_counts[$entry['course_code']] > 1;
                                                        ?>
                                                            <div class="course-card <?php 
                                                                if (count($entries) > 1) echo 'multiple';
                                                                if ($is_overlapping) echo ' overlapping';
                                                            ?>" 
                                                                     onclick="editTimetableEntry(<?php echo $entry['id']; ?>)"
                                                                     title="Click to edit - <?php echo htmlspecialchars($entry['class_name'] . ' - ' . $entry['course_code']); ?>">
                                                                <div class="class-name"><?php echo htmlspecialchars($entry['class_name']); ?></div>
                                                                <div class="course-code"><?php echo htmlspecialchars($entry['course_code']); ?></div>
                                                                <?php if ($entry['lecturer_name']): ?>
                                                                    <div class="lecturer-name"><?php echo htmlspecialchars($entry['lecturer_name']); ?></div>
                                                                <?php endif; ?>
                                                                <?php if (count($entries) > 1): ?>
                                                                    <div class="badge bg-warning text-dark">Multiple Classes</div>
                                                                <?php endif; ?>
                                                                <?php if ($is_overlapping): ?>
                                                                    <div class="badge bg-info text-dark">Same Course</div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php 
                                                            endforeach;
                                                        else: 
                                                        ?>
                                                            <div class="empty-cell">
                                                                <i class="fas fa-plus text-muted"></i><br>
                                                                <small>No classes</small>
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
                        </div>
                    </div>

                    <!-- List View (Hidden by default) -->
                    <div id="list-view" style="display: none;">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-list me-2"></i>Timetable List View
                                    <?php if ($class_filter || $day_filter || $room_filter || $stream_filter): ?>
                                        <span class="badge bg-info ms-2">Filtered</span>
                                    <?php endif; ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
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
                                            <?php 
                                            // Reset result set for list view
                                            $stmt->execute();
                                            $list_result = $stmt->get_result();
                                            while ($entry = $list_result->fetch_assoc()): 
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($entry['day_name']); ?></strong></td>
                                                <td>
                                                    <span class="text-primary">
                                                        <?php echo htmlspecialchars(substr($entry['start_time'], 0, 5) . ' - ' . substr($entry['end_time'], 0, 5)); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($entry['class_name']); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($entry['course_code']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($entry['course_name']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($entry['room_name']); ?><br>
                                                    <small>Capacity: <?php echo $entry['capacity']; ?></small>
                                                </td>
                                                <td>
                                                    <?php echo $entry['lecturer_name'] ? htmlspecialchars($entry['lecturer_name']) : '<span class="text-muted">Not assigned</span>'; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                                onclick="editTimetableEntry(<?php echo $entry['id']; ?>)" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
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
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showGridView() {
            document.getElementById('grid-view').style.display = 'block';
            document.getElementById('list-view').style.display = 'none';
            document.querySelectorAll('.view-toggle .btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        function showListView() {
            document.getElementById('grid-view').style.display = 'none';
            document.getElementById('list-view').style.display = 'block';
            document.querySelectorAll('.view-toggle .btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
        }

        function editTimetableEntry(id) {
            window.location.href = 'update_timetable.php?id=' + id;
        }

        function deleteEntry(id) {
            if (confirm('Are you sure you want to delete this timetable entry? This action cannot be undone.')) {
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
