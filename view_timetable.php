<?php
// Timetable View Page with real database data and editable functionality

$pageTitle = 'View Timetable';
include 'connect.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Get filter parameters
$selected_department = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$selected_lecturer = isset($_GET['lecturer_id']) ? intval($_GET['lecturer_id']) : 0;
$selected_room = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Fetch available departments for filter
$departments_sql = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name";
$departments_result = $conn->query($departments_sql);

// Fetch available lecturers for filter
$lecturers_sql = "SELECT id, name FROM lecturers WHERE is_active = 1 ORDER BY name";
$lecturers_result = $conn->query($lecturers_sql);

// Fetch available rooms for filter
$rooms_sql = "SELECT id, name, building FROM rooms WHERE is_active = 1 ORDER BY building, name";
$rooms_result = $conn->query($rooms_sql);

// Fetch available classes for filter
$classes_sql = "SELECT id, name FROM classes WHERE is_active = 1 ORDER BY name";
$classes_result = $conn->query($classes_sql);

// Fetch days
$days_sql = "SELECT id, name FROM days ORDER BY 
    CASE name 
        WHEN 'Monday' THEN 1 
        WHEN 'Tuesday' THEN 2 
        WHEN 'Wednesday' THEN 3 
        WHEN 'Thursday' THEN 4 
        WHEN 'Friday' THEN 5 
        WHEN 'Saturday' THEN 6 
        WHEN 'Sunday' THEN 7 
    END";
$days_result = $conn->query($days_sql);
$days = [];
while ($day = $days_result->fetch_assoc()) {
    $days[] = $day;
}

// Fetch rooms
$rooms_sql = "SELECT id, name, building, capacity FROM rooms WHERE is_active = 1 ORDER BY building, name";
$rooms_result = $conn->query($rooms_sql);
$rooms = [];
while ($room = $rooms_result->fetch_assoc()) {
    $rooms[] = $room;
}

// Generate time slots (8 AM to 6 PM, hourly)
$timeSlots = [];
for ($hour = 8; $hour <= 18; $hour++) {
    $timeSlots[] = sprintf('%02d:00', $hour);
}

// Fetch timetable data based on filters
$timetableData = [];

// Build WHERE conditions for filters
$where_conditions = [];
$params = [];
$param_types = "";

if ($selected_department > 0) {
    $where_conditions[] = "c.department_id = ?";
    $params[] = $selected_department;
    $param_types .= "i";
}

if ($selected_lecturer > 0) {
    $where_conditions[] = "l.id = ?";
    $params[] = $selected_lecturer;
    $param_types .= "i";
}

if ($selected_room > 0) {
    $where_conditions[] = "r.id = ?";
    $params[] = $selected_room;
    $param_types .= "i";
}

if ($selected_class > 0) {
    $where_conditions[] = "c.id = ?";
    $params[] = $selected_class;
    $param_types .= "i";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$timetable_sql = "
    SELECT 
        t.id as timetable_id,
        d.name as day_name,
        d.id as day_id,
        r.name as room_name,
        r.id as room_id,
        c.name as class_name,
        co.name as course_name,
        co.code as course_code,
        COALESCE(co.hours, 1) as course_hours,
        l.name as lecturer_name,
        st.name as session_type,
        t.created_at,
        t.updated_at
    FROM timetable t
    JOIN sessions s ON t.session_id = s.id
    JOIN class_courses cc ON t.class_course_id = cc.id
    JOIN classes c ON cc.class_id = c.id
    JOIN courses co ON cc.course_id = co.id
    JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    JOIN lecturers l ON lc.lecturer_id = l.id
    JOIN rooms r ON t.room_id = r.id
    JOIN days d ON t.day_id = d.id
    JOIN session_types st ON t.session_type_id = st.id
    $where_clause
    ORDER BY d.id, r.id
";

$stmt = $conn->prepare($timetable_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$timetable_result = $stmt->get_result();

while ($row = $timetable_result->fetch_assoc()) {
    $day = $row['day_name'];
    $room = $row['room_name'];
    
    if (!isset($timetableData[$day])) {
        $timetableData[$day] = [];
    }
    if (!isset($timetableData[$day][$room])) {
        $timetableData[$day][$room] = [];
    }
    
            $timetableData[$day][$room][] = [
            'timetable_id' => $row['timetable_id'],
            'day_id' => $row['day_id'],
            'room_id' => $row['room_id'],
            'class_name' => $row['class_name'],
            'course_name' => $row['course_name'],
            'course_code' => $row['course_code'],
            'course_hours' => $row['course_hours'],
            'lecturer_name' => $row['lecturer_name'],
            'session_type' => $row['session_type'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
}
$stmt->close();

// Helper function to get cell content
function getCellContent($day, $room, $timeIndex, $timetableData, $timeSlots) {
    if (!isset($timetableData[$day][$room])) {
        return '<div class="text-xs text-gray-400 text-center p-1 empty-cell" data-day="' . htmlspecialchars($day) . '" data-room="' . htmlspecialchars($room) . '" data-time="' . $timeIndex . '">Empty</div>';
    }
    
    // Check if this time slot is already covered by a multi-hour course
    foreach ($timetableData[$day][$room] as $entry) {
        $course_hours = max(1, intval($entry['course_hours'] ?? 1)); // Default to 1 hour if not set
        
        // Check if this time slot is the start of a course
        if ($timeIndex == 0 || !isTimeSlotCovered($day, $room, $timeIndex - 1, $timetableData)) {
            // This is the start of a course
            $html = '<div class="text-xs p-1 rounded bg-primary text-white font-medium cursor-pointer hover:opacity-80 timetable-cell" 
                         data-timetable-id="' . $entry['timetable_id'] . '"
                         data-day="' . htmlspecialchars($day) . '"
                         data-room="' . htmlspecialchars($room) . '"
                         data-time="' . $timeIndex . '"
                         data-class="' . htmlspecialchars($entry['class_name']) . '"
                         data-course="' . htmlspecialchars($entry['course_name']) . '"
                         data-lecturer="' . htmlspecialchars($entry['lecturer_name']) . '"
                         data-session-type="' . htmlspecialchars($entry['session_type']) . '"
                         data-hours="' . $course_hours . '"
                         onclick="editTimetableEntry(this)">';
            $html .= '<div class="font-bold">' . htmlspecialchars($entry['course_code']) . '</div>';
            $html .= '<div class="truncate">' . htmlspecialchars($entry['lecturer_name']) . '</div>';
            $html .= '<div class="truncate">' . htmlspecialchars($entry['class_name']) . '</div>';
            $html .= '<div class="text-xs opacity-75">' . htmlspecialchars($entry['session_type']) . '</div>';
            if ($course_hours > 1) {
                $html .= '<div class="text-xs opacity-75">(' . $course_hours . ' hrs)</div>';
            }
            $html .= '</div>';
            
            return $html;
        }
    }
    
    // Check if this time slot is covered by a multi-hour course
    if (isTimeSlotCovered($day, $room, $timeIndex, $timetableData)) {
        return '<div class="text-xs text-center p-1 bg-primary text-white" style="opacity: 0.3;">...</div>';
    }
    
    return '<div class="text-xs text-gray-400 text-center p-1 empty-cell" data-day="' . htmlspecialchars($day) . '" data-room="' . htmlspecialchars($room) . '" data-time="' . $timeIndex . '">Empty</div>';
}

// Helper function to check if a time slot is covered by a multi-hour course
function isTimeSlotCovered($day, $room, $timeIndex, $timetableData) {
    if (!isset($timetableData[$day][$room])) {
        return false;
    }
    
    foreach ($timetableData[$day][$room] as $entry) {
        $course_hours = max(1, intval($entry['course_hours'] ?? 1));
        
        // Check if this time slot falls within the range of a multi-hour course
        for ($i = 0; $i < $course_hours; $i++) {
            if (($timeIndex - $i) >= 0 && !isTimeSlotStart($day, $room, $timeIndex - $i, $timetableData)) {
                return true;
            }
        }
    }
    
    return false;
}

// Helper function to check if a time slot is the start of a course
function isTimeSlotStart($day, $room, $timeIndex, $timetableData) {
    if (!isset($timetableData[$day][$room])) {
        return false;
    }
    
    foreach ($timetableData[$day][$room] as $entry) {
        $course_hours = max(1, intval($entry['course_hours'] ?? 1));
        
        // Check if this time slot is the start of a course
        if ($timeIndex == 0 || !isTimeSlotCovered($day, $room, $timeIndex - 1, $timetableData)) {
            return true;
        }
    }
    
    return false;
}
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-calendar-alt me-2"></i>View Timetable</h4>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-primary" href="generate_timetable.php">
                    <i class="fas fa-cogs me-2"></i>Generate Timetable
                </a>
                <a class="btn btn-outline-success" href="saved_timetable.php">
                    <i class="fas fa-save me-2"></i>Saved Timetables
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card m-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Timetable</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="view_timetable.php" class="row g-3">
                    <div class="col-md-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select name="department_id" id="department_id" class="form-select">
                            <option value="">All Departments</option>
                            <?php if ($departments_result && $departments_result->num_rows > 0): ?>
                                <?php while ($dept = $departments_result->fetch_assoc()): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo ($selected_department == $dept['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="lecturer_id" class="form-label">Lecturer</label>
                        <select name="lecturer_id" id="lecturer_id" class="form-select">
                            <option value="">All Lecturers</option>
                            <?php if ($lecturers_result && $lecturers_result->num_rows > 0): ?>
                                <?php while ($lecturer = $lecturers_result->fetch_assoc()): ?>
                                    <option value="<?php echo $lecturer['id']; ?>" 
                                            <?php echo ($selected_lecturer == $lecturer['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lecturer['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="room_id" class="form-label">Room</label>
                        <select name="room_id" id="room_id" class="form-select">
                            <option value="">All Rooms</option>
                            <?php if ($rooms_result && $rooms_result->num_rows > 0): ?>
                                <?php while ($room = $rooms_result->fetch_assoc()): ?>
                                    <option value="<?php echo $room['id']; ?>" 
                                            <?php echo ($selected_room == $room['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($room['name'] . ' (' . $room['building'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="class_id" class="form-label">Class</label>
                        <select name="class_id" id="class_id" class="form-select">
                            <option value="">All Classes</option>
                            <?php if ($classes_result && $classes_result->num_rows > 0): ?>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                    <option value="<?php echo $class['id']; ?>" 
                                            <?php echo ($selected_class == $class['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-center">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Filter Timetable
                            </button>
                            <a href="view_timetable.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($_GET['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($timetableData) && !($selected_department || $selected_lecturer || $selected_room || $selected_class)): ?>
            <!-- Welcome Message -->
            <div class="card m-3">
                <div class="card-body text-center py-5">
                    <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Welcome to Timetable View</h5>
                    <p class="text-muted">Use the filters above to search for specific timetable entries by department, lecturer, room, or class.</p>
                </div>
            </div>
        <?php elseif (!empty($timetableData)): ?>
            <!-- Timetable Table -->
            <div class="card m-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-table me-2"></i>
                        Timetable Results
                        <?php if ($selected_department || $selected_lecturer || $selected_room || $selected_class): ?>
                            <small class="text-muted">(Filtered)</small>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($timetableData)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Timetable Data Found</h5>
                            <p class="text-muted">
                                <?php if ($selected_department || $selected_lecturer || $selected_room || $selected_class): ?>
                                    No timetable entries match your current filters. Try adjusting your search criteria.
                                <?php else: ?>
                                    No timetable entries found. Use the filters above to search for specific data.
                                <?php endif; ?>
                            </p>
                            <?php if (!($selected_department || $selected_lecturer || $selected_room || $selected_class)): ?>
                                <a href="generate_timetable.php" class="btn btn-primary">
                                    <i class="fas fa-cogs me-2"></i>Generate Timetable
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="min-width: 150px;">Day / Room</th>
                                        <?php foreach ($timeSlots as $time): ?>
                                            <th class="text-center" style="min-width: 100px;"><?php echo $time; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($days as $day): ?>
                                        <!-- Day header row -->
                                        <tr class="table-primary">
                                            <td colspan="<?php echo count($timeSlots) + 1; ?>" class="fw-bold text-primary">
                                                <?php echo htmlspecialchars($day['name']); ?>
                                            </td>
                                        </tr>
                                        <!-- Room rows for this day -->
                                        <?php foreach ($rooms as $room): ?>
                                            <tr>
                                                <td class="table-secondary fw-medium" style="min-width: 150px;">
                                                    <?php echo htmlspecialchars($room['name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($room['building']); ?> (<?php echo $room['capacity']; ?> seats)</small>
                                                </td>
                                                <?php 
                                                $timeIndex = 0;
                                                while ($timeIndex < count($timeSlots)): 
                                                    $cellContent = getCellContent($day['name'], $room['name'], $timeIndex, $timetableData, $timeSlots);
                                                    
                                                    // Check if this cell should span multiple rows
                                                    $rowspan = 1;
                                                    if (isset($timetableData[$day['name']][$room['name']])) {
                                                        foreach ($timetableData[$day['name']][$room['name']] as $entry) {
                                                            $course_hours = max(1, intval($entry['course_hours'] ?? 1));
                                                            if ($timeIndex == 0 || !isTimeSlotCovered($day['name'], $room['name'], $timeIndex - 1, $timetableData)) {
                                                                // This is the start of a course
                                                                $rowspan = $course_hours;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    
                                                    if ($rowspan > 1) {
                                                        echo '<td class="p-1" style="min-height: ' . (60 * $rowspan) . 'px;" rowspan="' . $rowspan . '">';
                                                        echo $cellContent;
                                                        echo '</td>';
                                                        $timeIndex += $rowspan;
                                                    } else {
                                                        echo '<td class="p-1" style="min-height: 60px;">';
                                                        echo $cellContent;
                                                        echo '</td>';
                                                        $timeIndex++;
                                                    }
                                                endwhile; 
                                                ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        

                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Instructions -->
            <div class="card m-3">
                <div class="card-body text-center py-5">
                    <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Select a Session to View Timetable</h5>
                    <p class="text-muted">Choose a session from the dropdown above to view and edit the timetable.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.table td {
    vertical-align: top;
    padding: 0.25rem !important;
}

.table th {
    padding: 0.5rem !important;
    font-size: 0.875rem;
}

.table-responsive {
    overflow-x: auto;
    max-width: 100%;
}

/* Timetable cell styling */
.timetable-cell {
    transition: all 0.2s ease;
    border: 1px solid rgba(0,0,0,0.1);
}

.timetable-cell:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    z-index: 10;
    position: relative;
}

.empty-cell {
    transition: all 0.2s ease;
    cursor: pointer;
}

.empty-cell:hover {
    background-color: #f8f9fa !important;
    border: 2px dashed #dee2e6;
    transform: scale(1.02);
}

/* Modal styling */
.modal-lg {
    max-width: 800px;
}

/* Form validation styling */
.form-select.is-invalid {
    border-color: #dc3545;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.form-select.is-valid {
    border-color: #198754;
    box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
}

/* Loading states */
.btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

/* Success/error message styling */
.alert {
    border-radius: 0.5rem;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Table improvements */
.table-bordered {
    border: 1px solid #dee2e6;
}

.table-bordered td,
.table-bordered th {
    border: 1px solid #dee2e6;
}

.table-primary {
    background-color: #cfe2ff !important;
    border-color: #9ec5fe !important;
}

.table-secondary {
    background-color: #e2e3e5 !important;
    border-color: #c4c7c9 !important;
}

/* Multi-hour course styling */
.timetable-cell[data-hours] {
    position: relative;
}

.timetable-cell[data-hours="2"] {
    background: linear-gradient(135deg, #007bff, #0056b3) !important;
}

.timetable-cell[data-hours="3"] {
    background: linear-gradient(135deg, #007bff, #004085) !important;
}

.timetable-cell[data-hours="4"] {
    background: linear-gradient(135deg, #007bff, #002752) !important;
}

/* Extended cell indicator */
.timetable-cell[data-hours]::after {
    content: '';
    position: absolute;
    right: 5px;
    bottom: 5px;
    width: 8px;
    height: 8px;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 50%;
}
</style>

<!-- Edit Timetable Entry Modal -->
<div class="modal fade" id="editTimetableModal" tabindex="-1" aria-labelledby="editTimetableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTimetableModalLabel">Edit Timetable Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editTimetableForm">
                    <input type="hidden" id="edit_timetable_id" name="timetable_id">
                    <input type="hidden" id="edit_session_id" name="session_id" value="<?php echo $selected_session; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_day" class="form-label">Day *</label>
                            <select class="form-select" id="edit_day" name="day_id" required>
                                <option value="">Select day...</option>
                                <?php foreach ($days as $day): ?>
                                    <option value="<?php echo $day['id']; ?>"><?php echo htmlspecialchars($day['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_time_slot" class="form-label">Time Slot *</label>
                            <select class="form-select" id="edit_time_slot" name="time_slot" required>
                                <option value="">Select time...</option>
                                <?php foreach ($timeSlots as $index => $time): ?>
                                    <option value="<?php echo $index; ?>"><?php echo $time; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_room" class="form-label">Room *</label>
                            <select class="form-select" id="edit_room" name="room_id" required>
                                <option value="">Select room...</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['name'] . ' (' . $room['building'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_class" class="form-label">Class *</label>
                            <select class="form-select" id="edit_class" name="class_id" required>
                                <option value="">Select class...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_course" class="form-label">Course *</label>
                            <select class="form-select" id="edit_course" name="course_id" required>
                                <option value="">Select course...</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_lecturer" class="form-label">Lecturer *</label>
                            <select class="form-select" id="edit_lecturer" name="lecturer_id" required>
                                <option value="">Select lecturer...</option>
                            </select>
                        </div>
                                                 <div class="col-md-6">
                             <label for="edit_session_type" class="form-label">Session Type *</label>
                             <select class="form-select" id="edit_session_type" name="session_type_id" required>
                                 <option value="">Select type...</option>
                             </select>
                         </div>
                         <div class="col-md-6">
                             <label for="edit_course_hours" class="form-label">Course Hours *</label>
                             <select class="form-select" id="edit_course_hours" name="course_hours" required>
                                 <option value="1">1 Hour</option>
                                 <option value="2">2 Hours</option>
                                 <option value="3">3 Hours</option>
                                 <option value="4">4 Hours</option>
                             </select>
                         </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="deleteEntryBtn" style="display: none;">Delete Entry</button>
                <button type="button" class="btn btn-primary" id="saveEntryBtn">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentEditData = null;
let editModal = null;

// Initialize modal
document.addEventListener('DOMContentLoaded', function() {
    editModal = new bootstrap.Modal(document.getElementById('editTimetableModal'));
    
    // Load initial data for dropdowns
    loadClasses();
    loadCourses();
    loadLecturers();
    loadSessionTypes();
    
    // Add click handlers for empty cells
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('empty-cell')) {
            addNewTimetableEntry(e.target);
        }
    });
});

// Function to edit timetable entry
function editTimetableEntry(cell) {
    currentEditData = {
        timetable_id: cell.dataset.timetableId,
        day: cell.dataset.day,
        room: cell.dataset.room,
        time: cell.dataset.time,
        class: cell.dataset.class,
        course: cell.dataset.course,
        lecturer: cell.dataset.lecturer,
        session_type: cell.dataset.sessionType,
        hours: cell.dataset.hours || 1
    };
    
    // Populate form fields
    document.getElementById('edit_timetable_id').value = currentEditData.timetable_id;
    
    // Set day (find by name)
    const daySelect = document.getElementById('edit_day');
    for (let i = 0; i < daySelect.options.length; i++) {
        if (daySelect.options[i].text === currentEditData.day) {
            daySelect.selectedIndex = i;
            break;
        }
    }
    
    // Set time slot
    document.getElementById('edit_time_slot').value = currentEditData.time;
    
    // Set room (find by name)
    const roomSelect = document.getElementById('edit_room');
    for (let i = 0; i < roomSelect.options.length; i++) {
        if (roomSelect.options[i].text.includes(currentEditData.room)) {
            roomSelect.selectedIndex = i;
            break;
        }
    }
    
    // Set class (find by name)
    const classSelect = document.getElementById('edit_class');
    for (let i = 0; i < classSelect.options.length; i++) {
        if (classSelect.options[i].text === currentEditData.class) {
            classSelect.selectedIndex = i;
            break;
        }
    }
    
    // Set course (find by name)
    const courseSelect = document.getElementById('edit_course');
    for (let i = 0; i < courseSelect.options.length; i++) {
        if (courseSelect.options[i].text === currentEditData.course) {
            courseSelect.selectedIndex = i;
            break;
        }
    }
    
    // Set lecturer (find by name)
    const lecturerSelect = document.getElementById('edit_lecturer');
    for (let i = 0; i < lecturerSelect.options.length; i++) {
        if (lecturerSelect.options[i].text === currentEditData.lecturer) {
            lecturerSelect.selectedIndex = i;
            break;
        }
    }
    
         // Set session type (find by name)
     const sessionTypeSelect = document.getElementById('edit_session_type');
     for (let i = 0; i < sessionTypeSelect.options.length; i++) {
         if (sessionTypeSelect.options[i].text === currentEditData.session_type) {
             sessionTypeSelect.selectedIndex = i;
             break;
         }
     }
     
     // Set course hours
     document.getElementById('edit_course_hours').value = currentEditData.hours;
     
     // Show delete button for existing entries
     document.getElementById('deleteEntryBtn').style.display = 'inline-block';
     
     // Show modal
     editModal.show();
}

// Function to add new timetable entry
function addNewTimetableEntry(cell) {
    currentEditData = {
        timetable_id: null,
        day: cell.dataset.day,
        room: cell.dataset.room,
        time: cell.dataset.time
    };
    
    // Clear form
    document.getElementById('editTimetableForm').reset();
    document.getElementById('edit_timetable_id').value = '';
    
    // Set day (find by name)
    const daySelect = document.getElementById('edit_day');
    for (let i = 0; i < daySelect.options.length; i++) {
        if (daySelect.options[i].text === currentEditData.day) {
            daySelect.selectedIndex = i;
            break;
        }
    }
    
    // Set time slot
    document.getElementById('edit_time_slot').value = currentEditData.time;
    
    // Set room (find by name)
    const roomSelect = document.getElementById('edit_room');
    for (let i = 0; i < roomSelect.options.length; i++) {
        if (roomSelect.options[i].text.includes(currentEditData.room)) {
            roomSelect.selectedIndex = i;
            break;
        }
    }
    
    // Hide delete button for new entries
    document.getElementById('deleteEntryBtn').style.display = 'none';
    
    // Show modal
    editModal.show();
}

// Load classes for dropdown
function loadClasses() {
    fetch('get_filtered_classes.php?session_id=<?php echo $selected_session; ?>')
        .then(response => response.json())
        .then(data => {
            const classSelect = document.getElementById('edit_class');
            classSelect.innerHTML = '<option value="">Select class...</option>';
            data.forEach(cls => {
                const option = document.createElement('option');
                option.value = cls.id;
                option.textContent = cls.name;
                classSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading classes:', error));
}

// Load courses for dropdown
function loadCourses() {
    fetch('get_courses.php')
        .then(response => response.json())
        .then(data => {
            const courseSelect = document.getElementById('edit_course');
            courseSelect.innerHTML = '<option value="">Select course...</option>';
            data.forEach(course => {
                const option = document.createElement('option');
                option.value = course.id;
                option.textContent = course.code + ' - ' + course.name;
                courseSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading courses:', error));
}

// Load lecturers for dropdown
function loadLecturers() {
    fetch('lecturers.php?action=get_all')
        .then(response => response.json())
        .then(data => {
            const lecturerSelect = document.getElementById('edit_lecturer');
            lecturerSelect.innerHTML = '<option value="">Select lecturer...</option>';
            data.forEach(lecturer => {
                const option = document.createElement('option');
                option.value = lecturer.id;
                option.textContent = lecturer.name;
                lecturerSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading lecturers:', error));
}

// Load session types for dropdown
function loadSessionTypes() {
    fetch('get_session_types.php')
        .then(response => response.json())
        .then(data => {
            const sessionTypeSelect = document.getElementById('edit_session_type');
            sessionTypeSelect.innerHTML = '<option value="">Select type...</option>';
            data.forEach(type => {
                const option = document.createElement('option');
                option.value = type.id;
                option.textContent = type.name;
                sessionTypeSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading session types:', error));
}

// Save entry
document.getElementById('saveEntryBtn').addEventListener('click', function() {
    const form = document.getElementById('editTimetableForm');
    const formData = new FormData(form);
    
    // Add action
    formData.append('action', currentEditData.timetable_id ? 'update' : 'create');
    
    fetch('update_timetable.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            editModal.hide();
            // Reload page to show updated data
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the entry.');
    });
});

// Delete entry
document.getElementById('deleteEntryBtn').addEventListener('click', function() {
    if (confirm('Are you sure you want to delete this timetable entry?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('timetable_id', currentEditData.timetable_id);
        
        fetch('update_timetable.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                editModal.hide();
                // Reload page to show updated data
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the entry.');
        });
    }
});

// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterSelects = document.querySelectorAll('#department_id, #lecturer_id, #room_id, #class_id');
    
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Small delay to allow user to make multiple selections
            setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
