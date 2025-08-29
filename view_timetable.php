<?php
$pageTitle = 'View Timetable';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Initialize variables
$selected_class = '';
$selected_session = '';
$timetable_data = [];
$error_message = '';
$success_message = '';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_class = $_POST['class'] ?? '';
    $selected_session = $_POST['session'] ?? '';
    
    if ($selected_class && $selected_session) {
        // Query timetable data based on actual schema
        $query = "SELECT 
                    t.id,
                    d.name as day_name,
                    c.name as course_name,
                    c.code as course_code,
                    l.name as lecturer_name,
                    r.name as room_name,
                    r.building as room_building,
                    st.name as session_type
                  FROM timetable t
                  JOIN days d ON t.day_id = d.id
                  JOIN class_courses cc ON t.class_course_id = cc.id
                  JOIN courses c ON cc.course_id = c.id
                  JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                  JOIN lecturers l ON lc.lecturer_id = l.id
                  JOIN rooms r ON t.room_id = r.id
                  JOIN session_types st ON t.session_type_id = st.id
                  WHERE cc.class_id = ? AND cc.session_id = ?
                  ORDER BY d.id";
        
        if ($stmt = $conn->prepare($query)) {
            $stmt->bind_param("ii", $selected_class, $selected_session);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result) {
                $timetable_data = $result->fetch_all(MYSQLI_ASSOC);
            } else {
                $error_message = "Error fetching timetable data: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = "Prepare failed: " . $conn->error;
        }
    }
}

// Fetch classes for dropdown
$classes_sql = "SELECT c.id, c.name, d.name as department_name 
                FROM classes c 
                JOIN departments d ON c.department_id = d.id 
                WHERE c.is_active = 1 
                ORDER BY c.name";
$classes_result = $conn->query($classes_sql);

// Fetch sessions for dropdown
$sessions_sql = "SELECT id, semester_name, academic_year 
                 FROM sessions 
                 WHERE is_active = 1 
                 ORDER BY academic_year DESC, semester_number";
$sessions_result = $conn->query($sessions_sql);

// Get unique days and time slots for grid layout
$days_sql = "SELECT id, name FROM days ORDER BY id";
$days_result = $conn->query($days_sql);
$days = [];
if ($days_result) {
    while ($day = $days_result->fetch_assoc()) {
        $days[] = $day;
    }
}

// Generate time slots programmatically (8 AM to 6 PM)
$time_slots = [];
for ($hour = 8; $hour <= 18; $hour++) {
    $start_time = sprintf('%02d:00:00', $hour);
    $end_time = sprintf('%02d:00:00', $hour + 1);
    $time_slots[] = [
        'id' => $hour,
        'start_time' => $start_time,
        'end_time' => $end_time
    ];
}
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-calendar-alt me-2"></i>Timetable Viewer</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportPDF()">
                    <i class="fas fa-file-pdf me-2"></i>Export PDF
                </button>
                <button class="btn btn-outline-secondary" onclick="printTimetableContents()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
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

        <!-- Timetable Selection Form -->
        <div class="card m-3">
            <div class="card-body">
                <form method="POST" action="view_timetable.php" class="row g-3">
                    <div class="col-md-5">
                        <label for="class" class="form-label">Select Class *</label>
                        <select name="class" id="class" class="form-select" required>
                            <option value="">Choose a class...</option>
                            <?php if ($classes_result && $classes_result->num_rows > 0): ?>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class == $class['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name'] . ' - ' . $class['department_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-5">
                        <label for="session" class="form-label">Select Session *</label>
                        <select name="session" id="session" class="form-select" required>
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
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>View
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($timetable_data)): ?>
            <!-- Timetable Grid: Rows = Time slots, Columns = Day/Venue -->
            <div class="timetable-container m-3" id="printArea">
                <div class="table-responsive">
                    <table class="table table-bordered timetable-grid" id="timetableTable">
                        <thead>
                            <tr>
                                <th class="time-header">Time</th>
                                <?php foreach ($days as $day): ?>
                                    <?php 
                                    // Fetch rooms once for columns header
                                    $rooms_cols = [];
                                    if (!isset($__rooms_cache)) {
                                        $__rooms_cache = [];
                                        $__res = $conn->query("SELECT id, name, building FROM rooms WHERE is_active = 1 ORDER BY building, name");
                                        if ($__res) { while ($__r = $__res->fetch_assoc()) { $__rooms_cache[] = $__r; } }
                                    }
                                    $rooms_cols = $__rooms_cache;
                                    ?>
                                    <?php foreach ($rooms_cols as $room): ?>
                                        <th class="day-header"><?php echo htmlspecialchars($day['name']); ?><br><?php echo htmlspecialchars($room['name'] . ' (' . $room['building'] . ')'); ?></th>
                                    <?php endforeach; ?>
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
                                        <?php foreach ($rooms_cols as $room): ?>
                                            <td class="timetable-cell">
                                                <?php
                                                $entry = null;
                                                foreach ($timetable_data as $data) {
                                                    if ($data['day_name'] === $day['name'] && 
                                                        $data['start_time'] === $time_slot['start_time'] &&
                                                        $data['room_name'] === $room['name'] && $data['room_building'] === $room['building']) {
                                                        $entry = $data; break;
                                                    }
                                                }
                                                if ($entry): ?>
                                                    <div class="timetable-entry" data-bs-toggle="tooltip" title="Click for details">
                                                        <div class="course-name"><?php echo htmlspecialchars($entry['course_name']); ?></div>
                                                        <div class="course-code"><?php echo htmlspecialchars($entry['course_code']); ?></div>
                                                        <div class="lecturer"><?php echo htmlspecialchars($entry['lecturer_name']); ?></div>
                                                        <div class="session-type"><?php echo htmlspecialchars($entry['session_type']); ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Timetable Summary -->
            <div class="card m-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Timetable Summary</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="summary-item">
                                <span class="summary-label">Total Classes:</span>
                                <span class="summary-value"><?php echo count($timetable_data); ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-item">
                                <span class="summary-label">Days per Week:</span>
                                <span class="summary-value"><?php echo count(array_unique(array_column($timetable_data, 'day_name'))); ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-item">
                                <span class="summary-label">Time Slots:</span>
                                <span class="summary-value"><?php echo count(array_unique(array_column($timetable_data, 'start_time'))); ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="summary-item">
                                <span class="summary-label">Courses:</span>
                                <span class="summary-value"><?php echo count(array_unique(array_column($timetable_data, 'course_id'))); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($_SERVER["REQUEST_METHOD"] == "POST"): ?>
            <div class="alert alert-info m-3">
                <i class="fas fa-info-circle me-2"></i>
                No timetable entries found for the selected class and session. 
                Please ensure the timetable has been generated for this combination.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<style>
.timetable-grid {
    font-size: 0.85rem;
}

.time-header, .day-header {
    background: linear-gradient(135deg, var(--primary-color), var(--hover-color));
    color: white;
    text-align: center;
    font-weight: 600;
    padding: 12px 8px;
    min-width: 120px;
}

.time-cell {
    background-color: #f8f9fa;
    text-align: center;
    vertical-align: middle;
    min-width: 80px;
    font-weight: 600;
}

.time-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.start-time {
    color: var(--primary-color);
    font-weight: 700;
}

.end-time {
    color: #6c757d;
    font-size: 0.8rem;
}

.timetable-cell {
    min-height: 80px;
    vertical-align: top;
    padding: 4px;
    border: 1px solid #dee2e6;
}

.timetable-entry {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border: 1px solid #2196f3;
    border-radius: 6px;
    padding: 8px;
    margin: 2px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.75rem;
}

.timetable-entry:hover {
    background: linear-gradient(135deg, #bbdefb, #90caf9);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(33, 150, 243, 0.3);
}

.course-name {
    font-weight: 700;
    color: #1565c0;
    margin-bottom: 2px;
}

.course-code {
    font-weight: 600;
    color: #1976d2;
    font-size: 0.7rem;
    margin-bottom: 2px;
}

.lecturer {
    color: #424242;
    font-weight: 500;
    margin-bottom: 2px;
}

.room {
    color: #616161;
    font-size: 0.7rem;
    margin-bottom: 2px;
}

.session-type {
    background-color: #2196f3;
    color: white;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 600;
    text-align: center;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.summary-label {
    font-weight: 600;
    color: #6c757d;
}

.summary-value {
    font-weight: 700;
    color: var(--primary-color);
    font-size: 1.1rem;
}

@media print {
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: absolute; left: 0; top: 0; width: 100%; }
    .table-header, .card, .btn, .search-container, .sidebar, .navbar, .footer {
        display: none !important;
    }
    
    .timetable-grid {
        font-size: 0.75rem;
        border: 1px solid #000 !important;
    }
    
    .timetable-entry {
        background: white !important;
        border: 1px solid #000 !important;
        color: #000 !important;
    }
}
</style>

<script>
function printTimetableContents() {
    window.print();
}

function exportPDF() {
    // Uses browser print to PDF, but with print-only CSS to include only the timetable
    window.print();
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
