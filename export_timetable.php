<?php
include 'connect.php';

// Start output buffering to allow exporting (headers) even if included files emit output
if (!ob_get_level()) {
    ob_start();
}

// Page title and layout includes
$pageTitle = 'Export Timetable';
include 'includes/header.php';

// Get filter parameters
$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$day_filter = isset($_GET['day_id']) ? (int)$_GET['day_id'] : 0;
$room_filter = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Build the main query (support both timetable schema variants)
$has_class_course = false;
$has_lecturer_course = false;
$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
if ($col && $col->num_rows > 0) { $has_class_course = true; }
$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'lecturer_course_id'");
if ($col && $col->num_rows > 0) { $has_lecturer_course = true; }

$select_parts = [
    "c.name as class_name",
    "t.division_label",
    "co.`code` AS course_code",
    "co.`name` AS course_name",
    "d.name as day_name",
    "ts.start_time",
    "ts.end_time",
    "r.name as room_name",
    "r.capacity",
    "l.name as lecturer_name"
];
$join_parts = [];

if ($has_class_course) {
    $join_parts[] = "JOIN class_courses cc ON t.class_course_id = cc.id";
    $join_parts[] = "JOIN classes c ON cc.class_id = c.id";
    $join_parts[] = "JOIN courses co ON cc.course_id = co.id";
} else {
    $join_parts[] = "JOIN classes c ON t.class_id = c.id";
    $join_parts[] = "JOIN courses co ON t.course_id = co.id";
}

if ($has_lecturer_course) {
    $join_parts[] = "LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id";
    $join_parts[] = "LEFT JOIN lecturers l ON lc.lecturer_id = l.id";
} else {
    $join_parts[] = "LEFT JOIN lecturers l ON t.lecturer_id = l.id";
}

$main_query = "SELECT " . implode(",\n        ", $select_parts) . "\n    FROM timetable t\n        " . implode("\n        ", $join_parts) . "\n        JOIN days d ON t.day_id = d.id\n        JOIN time_slots ts ON t.time_slot_id = ts.id\n        JOIN rooms r ON t.room_id = r.id\n    WHERE 1=1";

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

$main_query .= " ORDER BY d.id, ts.start_time, c.name, t.division_label, co.code";

// Execute the main query
$stmt = $conn->prepare($main_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$timetable_result = $stmt->get_result();

// Get filter options
$classes_result = $conn->query("SELECT id, name FROM classes WHERE is_active = 1 ORDER BY name");
$days_result = $conn->query("SELECT id, name FROM days WHERE is_active = 1 ORDER BY id");
$rooms_result = $conn->query("SELECT id, name FROM rooms WHERE is_active = 1 ORDER BY name");

// Handle export
if (isset($_GET['export']) && $_GET['export'] === '1') {
    if ($format === 'csv') {
        exportCSV($timetable_result);
    } else {
        exportExcel($timetable_result);
    }
    exit;
}

function exportCSV($result) {
    // Clear any prior output and headers
    if (ob_get_length()) ob_clean();
    if (!headers_sent()) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="timetable_' . date('Y-m-d_H-i-s') . '.csv"');
    }
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, ['Day', 'Time', 'Class', 'Division', 'Course Code', 'Course Name', 'Room', 'Capacity', 'Lecturer']);
    
    // Add data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['day_name'],
            substr($row['start_time'], 0, 5) . ' - ' . substr($row['end_time'], 0, 5),
            $row['class_name'],
            $row['division_label'] ?: '',
            $row['course_code'],
            $row['course_name'],
            $row['room_name'],
            $row['capacity'],
            $row['lecturer_name'] ?: 'Not assigned'
        ]);
    }
    
    fclose($output);
}

function exportExcel($result) {
    require_once 'vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers
    $sheet->setCellValue('A1', 'Day');
    $sheet->setCellValue('B1', 'Time');
    $sheet->setCellValue('C1', 'Class');
    $sheet->setCellValue('D1', 'Division');
    $sheet->setCellValue('E1', 'Course Code');
    $sheet->setCellValue('F1', 'Course Name');
    $sheet->setCellValue('G1', 'Room');
    $sheet->setCellValue('H1', 'Capacity');
    $sheet->setCellValue('I1', 'Lecturer');
    
    // Style headers
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E2E3E5']
        ]
    ];
    $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
    
    // Add data
    $row = 2;
    while ($data = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $data['day_name']);
        $sheet->setCellValue('B' . $row, substr($data['start_time'], 0, 5) . ' - ' . substr($data['end_time'], 0, 5));
        $sheet->setCellValue('C' . $row, $data['class_name']);
        $sheet->setCellValue('D' . $row, $data['division_label'] ?: '');
        $sheet->setCellValue('E' . $row, $data['course_code']);
        $sheet->setCellValue('F' . $row, $data['course_name']);
        $sheet->setCellValue('G' . $row, $data['room_name']);
        $sheet->setCellValue('H' . $row, $data['capacity']);
        $sheet->setCellValue('I' . $row, $data['lecturer_name'] ?: 'Not assigned');
        $row++;
    }
    
    // Auto-size columns
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Create writer and output
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    // Clear any prior output and set headers if possible
    if (ob_get_length()) ob_clean();
    if (!headers_sent()) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="timetable_' . date('Y-m-d_H-i-s') . '.xlsx"');
        header('Cache-Control: max-age=0');
    }
    
    $writer->save('php://output');
}

// Get statistics
$total_entries = $conn->query("SELECT COUNT(*) as count FROM timetable")->fetch_assoc()['count'];

// Support both timetable schema variants when computing stats
$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
$has_t_class_course = ($col && $col->num_rows > 0);
if ($has_t_class_course) {
    $total_classes = $conn->query("SELECT COUNT(DISTINCT c.id) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN classes c ON cc.class_id = c.id")->fetch_assoc()['count'];
    $total_courses = $conn->query("SELECT COUNT(DISTINCT co.id) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN courses co ON cc.course_id = co.id")->fetch_assoc()['count'];
} else {
    $total_classes = $conn->query("SELECT COUNT(DISTINCT c.id) as count FROM timetable t JOIN classes c ON t.class_id = c.id")->fetch_assoc()['count'];
    $total_courses = $conn->query("SELECT COUNT(DISTINCT co.id) as count FROM timetable t JOIN courses co ON t.course_id = co.id")->fetch_assoc()['count'];
}
if ($col) $col->close();
?>

<!-- Bootstrap CSS and JS are included globally in includes/header.php -->
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
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-download me-2"></i>Export Timetable
                        </h5>
                        <div class="d-flex gap-2">
                            <a href="view_timetable.php" class="btn btn-outline-primary">
                                <i class="fas fa-eye me-2"></i>View Timetable
                            </a>
                            <a href="generate_timetable.php" class="btn btn-outline-success">
                                <i class="fas fa-plus me-2"></i>Generate Timetable
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

                        <!-- Export Options -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Export Options</h6>
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
                                            <div class="col-md-3">
                                                <label for="format" class="form-label">Format</label>
                                                <select name="format" id="format" class="form-select">
                                                    <option value="excel" <?php echo $format === 'excel' ? 'selected' : ''; ?>>Excel (.xlsx)</option>
                                                    <option value="csv" <?php echo $format === 'csv' ? 'selected' : ''; ?>>CSV</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex gap-2">
                                                    <button type="submit" name="export" value="1" class="btn btn-primary btn-lg">
                                                        <i class="fas fa-download me-2"></i>Export Timetable
                                                    </button>
                                                    <a href="export_timetable.php" class="btn btn-outline-secondary">
                                                        <i class="fas fa-times me-2"></i>Clear Filters
                                                    </a>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Export Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <h6 class="text-primary">Excel Export</h6>
                                            <p class="text-muted small">
                                                Exports timetable data to an Excel file (.xlsx) with proper formatting, 
                                                column sizing, and styling.
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="text-success">CSV Export</h6>
                                            <p class="text-muted small">
                                                Exports timetable data to a CSV file that can be opened in Excel, 
                                                Google Sheets, or any spreadsheet application.
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="text-info">Filtered Exports</h6>
                                            <p class="text-muted small">
                                                Use the filters above to export specific subsets of the timetable 
                                                (by class, day, or room).
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preview -->
                        <?php if ($timetable_result->num_rows > 0): ?>
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        Export Preview (<?php echo $timetable_result->num_rows; ?> entries)
                                        <?php if ($class_filter || $day_filter || $room_filter): ?>
                                            <span class="badge bg-info ms-2">Filtered</span>
                                        <?php endif; ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Day</th>
                                                    <th>Time</th>
                                                    <th>Class</th>
                                                    <th>Division</th>
                                                    <th>Course</th>
                                                    <th>Room</th>
                                                    <th>Lecturer</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                // Reset result pointer for preview
                                                $timetable_result->data_seek(0);
                                                $preview_count = 0;
                                                while (($entry = $timetable_result->fetch_assoc()) && $preview_count < 10): 
                                                    $preview_count++;
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($entry['day_name']); ?></td>
                                                    <td><?php echo htmlspecialchars(substr($entry['start_time'], 0, 5) . ' - ' . substr($entry['end_time'], 0, 5)); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['class_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['division_label'] ?: ''); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['course_code'] . ' - ' . $entry['course_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($entry['room_name']); ?></td>
                                                    <td><?php echo $entry['lecturer_name'] ? htmlspecialchars($entry['lecturer_name']) : '<span class="text-muted">Not assigned</span>'; ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                                <?php if ($timetable_result->num_rows > 10): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">
                                                        <em>Showing first 10 entries. Total: <?php echo $timetable_result->num_rows; ?> entries.</em>
                                                    </td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="card mt-4">
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

</body>
</html>
