<?php
include 'connect.php';

// Page title and layout includes
$pageTitle = 'Timetable Template';
include 'includes/header.php';
include 'includes/sidebar.php';

$stream_filter = isset($_GET['stream_id']) ? (int)$_GET['stream_id'] : 0;
$semester_filter = isset($_GET['semester']) ? (int)$_GET['semester'] : 1;
$timetable_type = isset($_GET['type']) ? $_GET['type'] : 'lecture';

// Get streams for dropdown
$streams_query = "SELECT id, name, code FROM streams WHERE is_active = 1 ORDER BY name";
$streams_result = $conn->query($streams_query);
$streams = [];
if ($streams_result) {
    while ($row = $streams_result->fetch_assoc()) {
        $streams[] = $row;
    }
}

// Get rooms for the timetable
$rooms_query = "SELECT id, name, building, capacity, room_type FROM rooms ORDER BY building, name";
$rooms_result = $conn->query($rooms_query);
$rooms = [];
if ($rooms_result) {
    while ($row = $rooms_result->fetch_assoc()) {
        $rooms[] = $row;
    }
}

// Get time slots
$timeslots_query = "SELECT id, start_time, end_time FROM time_slots ORDER BY start_time";
$timeslots_result = $conn->query($timeslots_query);
$time_slots = [];
if ($timeslots_result) {
    while ($row = $timeslots_result->fetch_assoc()) {
        $time_slots[] = $row;
    }
}

// Get days
$days_query = "SELECT id, name FROM days ORDER BY id";
$days_result = $conn->query($days_query);
$days = [];
if ($days_result) {
    while ($row = $days_result->fetch_assoc()) {
        $days[] = $row;
    }
}

// // Sample course data for demonstration (in real implementation, this would come from database)
// $sample_courses = [
//     ['id' => '1', 'name' => 'Mathematics 101', 'code' => 'MATH101', 'duration' => 2, 'color' => 'bg-blue-100 text-blue-800', 'lecturer_name' => 'Dr. Johnson', 'classes' => ['Year 1 CS', 'Year 1 IT']],
//     ['id' => '2', 'name' => 'Physics 101', 'code' => 'PHY101', 'duration' => 3, 'color' => 'bg-green-100 text-green-800', 'lecturer_name' => 'Dr. Smith', 'classes' => ['Year 1 CS', 'Year 1 Physics']],
//     ['id' => '3', 'name' => 'Chemistry 101', 'code' => 'CHEM101', 'duration' => 2, 'color' => 'bg-purple-100 text-purple-800', 'lecturer_name' => 'Dr. Brown', 'classes' => ['Year 1 CS', 'Year 1 Chemistry']],
//     ['id' => '4', 'name' => 'Biology 101', 'code' => 'BIO101', 'duration' => 2, 'color' => 'bg-yellow-100 text-yellow-800', 'lecturer_name' => 'Dr. Wilson', 'classes' => ['Year 1 CS', 'Year 1 Biology']],
//     ['id' => '5', 'name' => 'Computer Science 101', 'code' => 'CS101', 'duration' => 3, 'color' => 'bg-red-100 text-red-800', 'lecturer_name' => 'Dr. Davis', 'classes' => ['Year 1 CS']],
//     ['id' => '6', 'name' => 'English 101', 'code' => 'ENG101', 'duration' => 1, 'color' => 'bg-indigo-100 text-indigo-800', 'lecturer_name' => 'Dr. Miller', 'classes' => ['Year 1 CS', 'Year 1 IT', 'Year 1 Engineering']],
// ];

// Generate sample timetable data
function generateSampleTimetableData($days, $rooms, $time_slots) {
    global $sample_courses;
    
    $data = [];
    foreach ($days as $day) {
        $data[$day['name']] = [];
        foreach ($rooms as $room) {
            $data[$day['name']][$room['name']] = [];
        }
    }
    
    // Add sample courses to demonstrate the layout
    if (!empty($rooms) && !empty($days)) {
        $available_rooms = array_slice($rooms, 0, 3);
        
        // Monday - Room A: Full day schedule
        if (isset($available_rooms[0])) {
            $data['Monday'][$available_rooms[0]['name']] = [
                ['course' => $sample_courses[1], 'start_time' => 0, 'spans' => 3], // PHY101: 7:00-10:00
                ['course' => $sample_courses[3], 'start_time' => 3, 'spans' => 2], // BIO101: 10:00-12:00
                ['course' => $sample_courses[5], 'start_time' => 6, 'spans' => 1], // ENG101: 13:00-14:00
                ['course' => $sample_courses[0], 'start_time' => 7, 'spans' => 2], // MATH101: 14:00-16:00
            ];
        }
        
        // Monday - Room B: Morning and afternoon
        if (isset($available_rooms[1])) {
            $data['Monday'][$available_rooms[1]['name']] = [
                ['course' => $sample_courses[2], 'start_time' => 1, 'spans' => 2], // CHEM101: 8:00-10:00
                ['course' => $sample_courses[4], 'start_time' => 4, 'spans' => 3], // CS101: 11:00-14:00
            ];
        }
        
        // Tuesday - Room A: Sequential courses
        if (isset($available_rooms[0])) {
            $data['Tuesday'][$available_rooms[0]['name']] = [
                ['course' => $sample_courses[0], 'start_time' => 0, 'spans' => 2], // MATH101: 7:00-9:00
                ['course' => $sample_courses[1], 'start_time' => 2, 'spans' => 1], // PHY101: 9:00-10:00
                ['course' => $sample_courses[3], 'start_time' => 3, 'spans' => 2], // BIO101: 10:00-12:00
            ];
        }
        
        // Wednesday - Room A: Morning intensive
        if (isset($available_rooms[0])) {
            $data['Wednesday'][$available_rooms[0]['name']] = [
                ['course' => $sample_courses[3], 'start_time' => 1, 'spans' => 1], // BIO101: 8:00-9:00
                ['course' => $sample_courses[0], 'start_time' => 2, 'spans' => 3], // MATH101: 9:00-12:00
            ];
        }
        
        // Thursday - Room A: Morning intensive
        if (isset($available_rooms[0])) {
            $data['Thursday'][$available_rooms[0]['name']] = [
                ['course' => $sample_courses[0], 'start_time' => 0, 'spans' => 3], // MATH101: 7:00-10:00
                ['course' => $sample_courses[1], 'start_time' => 3, 'spans' => 2], // PHY101: 10:00-12:00
            ];
        }
        
        // Friday - Room A: Light schedule
        if (isset($available_rooms[0])) {
            $data['Friday'][$available_rooms[0]['name']] = [
                ['course' => $sample_courses[3], 'start_time' => 0, 'spans' => 1], // BIO101: 7:00-8:00
            ];
        }
    }
    
    return $data;
}

$table_data = generateSampleTimetableData($days, $rooms, $time_slots);
$selected_rooms = array_slice($rooms, 0, 5); // Use first 5 rooms for demo
?>

<style>
/* Additional styles for the timetable template */
.timetable-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.timetable-header {
    background: linear-gradient(135deg, var(--primary-color), var(--hover-color));
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.timetable-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.timetable-table th {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    padding: 8px 4px;
    text-align: center;
    font-weight: 600;
    color: #495057;
    min-width: 100px;
}

.timetable-table td {
    border: 1px solid #dee2e6;
    padding: 2px;
    vertical-align: top;
    min-height: 60px;
    position: relative;
}

.day-header {
    background: #e3f2fd;
    color: #1976d2;
    font-weight: bold;
    text-align: center;
    padding: 8px;
}

.room-header {
    background: #f5f5f5;
    font-weight: 600;
    color: #424242;
    padding: 8px;
    min-width: 150px;
}

.course-cell {
    padding: 4px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 11px;
    line-height: 1.2;
}

.course-cell:hover {
    opacity: 0.8;
    transform: scale(1.02);
}

.empty-cell {
    color: #6c757d;
    text-align: center;
    cursor: pointer;
    padding: 8px;
    transition: background-color 0.2s ease;
}

.empty-cell:hover {
    background-color: #f8f9fa;
}

.reserved-cell {
    background: #dc3545 !important;
    color: white !important;
    text-align: center;
    cursor: not-allowed;
    font-weight: bold;
}

.multi-hour-course {
    border: 2px solid currentColor;
}

.course-code {
    font-weight: bold;
    font-size: 10px;
}

.course-lecturer {
    font-size: 9px;
    opacity: 0.9;
}

.course-classes {
    font-size: 9px;
    opacity: 0.8;
}

.course-duration {
    font-size: 8px;
    opacity: 0.7;
    margin-top: 2px;
}

.multi-hour-badge {
    background: rgba(255,255,255,0.5);
    padding: 1px 4px;
    border-radius: 2px;
    font-size: 8px;
    font-weight: bold;
    margin-top: 2px;
}

/* Modal styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1050;
}

.modal-content {
    background: white;
    border-radius: 8px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    padding: 20px 20px 0 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 0 20px 20px 20px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Filter controls */
.filter-controls {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.filter-row {
    display: flex;
    gap: 20px;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-weight: 600;
    color: #495057;
    font-size: 14px;
}

.filter-group select,
.filter-group input {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    min-width: 150px;
}

.btn-primary {
    background: var(--brand-blue);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.btn-primary:hover {
    background: #0b5ed7;
}

.btn-secondary {
    background: #6c757d;
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.btn-secondary:hover {
    background: #5c636a;
}

.btn-success {
    background: var(--brand-green);
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.btn-success:hover {
    background: #157347;
}

.btn-danger {
    background: #dc3545;
    border: none;
    color: white;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.btn-danger:hover {
    background: #bb2d3b;
}

/* Responsive design */
@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .filter-group select,
    .filter-group input {
        min-width: 100%;
    }
    
    .timetable-table {
        font-size: 10px;
    }
    
    .timetable-table th,
    .timetable-table td {
        padding: 4px 2px;
    }
}
</style>

<div class="space-y-6">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-4">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>
                Back
            </a>
            <div>
                <h1 class="h2 mb-1">Timetable Template</h1>
                <p class="text-muted mb-0">Interactive timetable management interface</p>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-success" onclick="showGenerateModal()">
                <i class="fas fa-play me-2"></i>
                Generate Timetable
            </button>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="filter-controls">
        <div class="filter-row">
            <div class="filter-group">
                <label for="stream-select">Stream</label>
                <select id="stream-select" class="form-select" onchange="updateTimetable()">
                    <option value="">All Streams</option>
                    <?php foreach ($streams as $stream): ?>
                        <option value="<?php echo $stream['id']; ?>" <?php echo $stream_filter == $stream['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($stream['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="semester-select">Semester</label>
                <select id="semester-select" class="form-select" onchange="updateTimetable()">
                    <option value="1" <?php echo $semester_filter == 1 ? 'selected' : ''; ?>>Semester 1</option>
                    <option value="2" <?php echo $semester_filter == 2 ? 'selected' : ''; ?>>Semester 2</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="type-select">Timetable Type</label>
                <select id="type-select" class="form-select" onchange="updateTimetable()">
                    <option value="lecture" <?php echo $timetable_type == 'lecture' ? 'selected' : ''; ?>>Lecture Timetable</option>
                    <option value="exam" <?php echo $timetable_type == 'exam' ? 'selected' : ''; ?>>Exam Timetable</option>
                </select>
            </div>
            
            <div class="filter-group">
                <button class="btn btn-primary" onclick="updateTimetable()">
                    <i class="fas fa-sync-alt me-2"></i>
                    Update View
                </button>
            </div>
        </div>
    </div>

    <!-- Timetable Preview -->
    <div class="timetable-container">
        <div class="timetable-header">
            <h3 class="mb-0">
                <?php echo ucfirst($timetable_type); ?> Timetable - 
                <?php 
                $selected_stream = array_filter($streams, function($s) use ($stream_filter) { return $s['id'] == $stream_filter; });
                $selected_stream = reset($selected_stream);
                echo $selected_stream ? htmlspecialchars($selected_stream['name']) : 'All Streams';
                ?> 
                (Semester <?php echo $semester_filter; ?>)
            </h3>
        </div>
        
        <div class="table-responsive">
            <table class="timetable-table">
                <thead>
                    <tr>
                        <th class="room-header">Day / Room</th>
                        <?php foreach ($time_slots as $slot): ?>
                            <th><?php echo htmlspecialchars($slot['start_time']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($days as $day): ?>
                        <!-- Day header row -->
                        <tr>
                            <td colspan="<?php echo count($time_slots) + 1; ?>" class="day-header">
                                <?php echo htmlspecialchars($day['name']); ?>
                            </td>
                        </tr>
                        
                        <!-- Room rows for this day -->
                        <?php foreach ($selected_rooms as $room): ?>
                            <tr>
                                <td class="room-header">
                                    <?php echo htmlspecialchars($room['name']); ?>
                                </td>
                                <?php 
                                $day_name = $day['name'];
                                $room_name = $room['name'];
                                $room_courses = $table_data[$day_name][$room_name] ?? [];
                                
                                foreach ($time_slots as $index => $slot): 
                                    $should_render = true;
                                    $course_at_time = null;
                                    
                                    // Check if this time slot is occupied by any course
                                    foreach ($room_courses as $course_data) {
                                        if ($index >= $course_data['start_time'] && $index < $course_data['start_time'] + $course_data['spans']) {
                                            $course_at_time = $course_data;
                                            // If this is a multi-hour course, only render the first cell
                                            if ($course_data['spans'] > 1 && $index > $course_data['start_time']) {
                                                $should_render = false;
                                            }
                                            break;
                                        }
                                    }
                                    
                                    if (!$should_render) continue;
                                ?>
                                    <td class="<?php echo $course_at_time && $course_at_time['spans'] > 1 ? 'multi-hour-course' : ''; ?>"
                                        onclick="handleCellClick('<?php echo $day_name; ?>', '<?php echo $room_name; ?>', <?php echo $index; ?>)">
                                        <?php if ($course_at_time): ?>
                                            <div class="course-cell <?php echo $course_at_time['course']['color']; ?>">
                                                <div class="course-code"><?php echo htmlspecialchars($course_at_time['course']['code']); ?></div>
                                                <div class="course-lecturer"><?php echo htmlspecialchars($course_at_time['course']['lecturer_name']); ?></div>
                                                <div class="course-classes"><?php echo htmlspecialchars(implode(', ', $course_at_time['course']['classes'])); ?></div>
                                                <div class="course-duration">
                                                    <?php if ($course_at_time['spans'] > 1): ?>
                                                        <?php echo $time_slots[$course_at_time['start_time']]['start_time']; ?> - 
                                                        <?php echo $time_slots[$course_at_time['start_time'] + $course_at_time['spans'] - 1]['start_time']; ?>
                                                    <?php else: ?>
                                                        <?php echo $course_at_time['course']['duration']; ?>h
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($course_at_time['spans'] > 1): ?>
                                                    <div class="multi-hour-badge"><?php echo $course_at_time['spans']; ?>h course</div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="empty-cell">
                                                Click to add course
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Generate Timetable Modal -->
<div id="generateModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="mb-0">Generate Timetable</h4>
            <button type="button" class="btn-close" onclick="hideGenerateModal()"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Academic Session</label>
                <select id="generate-session" class="form-select">
                    <option value="">Select Session</option>
                    <?php foreach ($sessions as $session): ?>
                        <option value="<?php echo $session['id']; ?>">
                            <?php echo htmlspecialchars($session['name']); ?> (<?php echo htmlspecialchars($session['academic_year']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Semester</label>
                <select id="generate-semester" class="form-select">
                    <option value="1">Semester 1</option>
                    <option value="2">Semester 2</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Timetable Type</label>
                <select id="generate-type" class="form-select">
                    <option value="lecture">Lecture Timetable</option>
                    <option value="exam">Exam Timetable</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Time Exclusions</label>
                <div class="d-flex gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="showExclusionModal()">
                        <i class="fas fa-plus me-1"></i>
                        Add Exclusion
                    </button>
                </div>
                <div id="exclusions-list">
                    <p class="text-muted small">No exclusions set. All time slots will be available for scheduling.</p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideGenerateModal()">Cancel</button>
            <button type="button" class="btn btn-success" onclick="generateTimetable()">Generate Timetable</button>
        </div>
    </div>
</div>

<!-- Add Exclusion Modal -->
<div id="exclusionModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="mb-0">Add Time Exclusion</h4>
            <button type="button" class="btn-close" onclick="hideExclusionModal()"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Day of Week</label>
                <select id="exclusion-day" class="form-select">
                    <option value="monday">Monday</option>
                    <option value="tuesday">Tuesday</option>
                    <option value="wednesday">Wednesday</option>
                    <option value="thursday">Thursday</option>
                    <option value="friday">Friday</option>
                    <option value="saturday">Saturday</option>
                    <option value="sunday">Sunday</option>
                </select>
            </div>
            
            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">Start Time</label>
                    <input type="time" id="exclusion-start" class="form-control" value="08:00">
                </div>
                <div class="col">
                    <label class="form-label">End Time</label>
                    <input type="time" id="exclusion-end" class="form-control" value="09:00">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Reason</label>
                <select id="exclusion-reason-type" class="form-select" onchange="toggleCustomReason()">
                    <option value="african_studies">African Studies</option>
                    <option value="liberal_courses">Liberal Courses</option>
                    <option value="other">Other</option>
                </select>
                <div id="custom-reason-input" style="display: none;" class="mt-2">
                    <input type="text" id="exclusion-custom-reason" class="form-control" placeholder="Enter custom reason">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideExclusionModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="addExclusion()">Add Exclusion</button>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div id="editModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="mb-0" id="edit-modal-title">Add Course</h4>
            <button type="button" class="btn-close" onclick="hideEditModal()"></button>
        </div>
        <div class="modal-body">
            <div class="row mb-3">
                <div class="col">
                    <label class="form-label">Day</label>
                    <input type="text" id="edit-day" class="form-control" readonly>
                </div>
                <div class="col">
                    <label class="form-label">Room</label>
                    <input type="text" id="edit-room" class="form-control" readonly>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Course Code</label>
                <input type="text" id="edit-course-code" class="form-control" placeholder="e.g., MATH101">
            </div>
            
            <div class="mb-3">
                <label class="form-label">Start Time</label>
                <select id="edit-start-time" class="form-select">
                    <?php foreach ($time_slots as $index => $slot): ?>
                        <option value="<?php echo $index; ?>"><?php echo htmlspecialchars($slot['start_time']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Classes</label>
                <div id="classes-container">
                    <div class="d-flex gap-2 mb-2">
                        <input type="text" class="form-control class-input" placeholder="Class 1">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeClass(this)">Remove</button>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addClass()">
                    <i class="fas fa-plus me-1"></i>
                    Add Another Class
                </button>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-danger" id="delete-course-btn" onclick="deleteCourse()" style="display: none;">Delete</button>
            <button type="button" class="btn btn-secondary" onclick="hideEditModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveCourse()">Save</button>
        </div>
    </div>
</div>

<script>
// Global variables
let exclusions = [];
let editingCell = null;
let tableData = <?php echo json_encode($table_data); ?>;

// Modal functions
function showGenerateModal() {
    document.getElementById('generateModal').style.display = 'flex';
}

function hideGenerateModal() {
    document.getElementById('generateModal').style.display = 'none';
}

function showExclusionModal() {
    document.getElementById('exclusionModal').style.display = 'flex';
}

function hideExclusionModal() {
    document.getElementById('exclusionModal').style.display = 'none';
}

function showEditModal() {
    document.getElementById('editModal').style.display = 'flex';
}

function hideEditModal() {
    document.getElementById('editModal').style.display = 'none';
    editingCell = null;
}

// Update timetable view
function updateTimetable() {
    const streamId = document.getElementById('stream-select').value;
    const semester = document.getElementById('semester-select').value;
    const type = document.getElementById('type-select').value;
    
    // Load timetable data from API
    loadTimetableData(streamId, semester, type);
}

// Load timetable data from API
function loadTimetableData(streamId, semester, type) {
    const url = new URL('api_timetable_template.php', window.location.origin);
    url.searchParams.set('action', 'get_timetable_data');
    if (streamId) url.searchParams.set('stream_id', streamId);
    if (semester) url.searchParams.set('semester', semester);
    if (type) url.searchParams.set('type', type);
    
    fetch(url)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            tableData = data.data;
            // Update the page URL to reflect the new filters
            const pageUrl = new URL(window.location);
            if (streamId) pageUrl.searchParams.set('stream_id', streamId);
            if (semester) pageUrl.searchParams.set('semester', semester);
            if (type) pageUrl.searchParams.set('type', type);
            window.history.pushState({}, '', pageUrl);
            
            // Reload the page to show updated data
            location.reload();
        } else {
            console.error('Error loading timetable data:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Fallback to page reload
        const url = new URL(window.location);
        if (sessionId) url.searchParams.set('session_id', sessionId);
        if (semester) url.searchParams.set('semester', semester);
        if (type) url.searchParams.set('type', type);
        window.location.href = url.toString();
    });
}

// Exclusion management
function toggleCustomReason() {
    const reasonType = document.getElementById('exclusion-reason-type').value;
    const customInput = document.getElementById('custom-reason-input');
    
    if (reasonType === 'other') {
        customInput.style.display = 'block';
    } else {
        customInput.style.display = 'none';
    }
}

function addExclusion() {
    const day = document.getElementById('exclusion-day').value;
    const startTime = document.getElementById('exclusion-start').value;
    const endTime = document.getElementById('exclusion-end').value;
    const reasonType = document.getElementById('exclusion-reason-type').value;
    const customReason = document.getElementById('exclusion-custom-reason').value;
    
    if (!startTime || !endTime) {
        alert('Please fill in all exclusion details');
        return;
    }
    
    if (startTime >= endTime) {
        alert('Start time must be before end time');
        return;
    }
    
    let reason = '';
    switch (reasonType) {
        case 'african_studies':
            reason = 'African Studies';
            break;
        case 'liberal_courses':
            reason = 'Liberal Courses';
            break;
        case 'other':
            if (!customReason.trim()) {
                alert('Please enter a custom reason');
                return;
            }
            reason = customReason.trim();
            break;
    }
    
    const exclusion = {
        dayOfWeek: day,
        startTime: startTime,
        endTime: endTime,
        reason: reason
    };
    
    exclusions.push(exclusion);
    updateExclusionsList();
    hideExclusionModal();
    
    // Reset form
    document.getElementById('exclusion-day').value = 'monday';
    document.getElementById('exclusion-start').value = '08:00';
    document.getElementById('exclusion-end').value = '09:00';
    document.getElementById('exclusion-reason-type').value = 'african_studies';
    document.getElementById('exclusion-custom-reason').value = '';
    document.getElementById('custom-reason-input').style.display = 'none';
}

function updateExclusionsList() {
    const container = document.getElementById('exclusions-list');
    
    if (exclusions.length === 0) {
        container.innerHTML = '<p class="text-muted small">No exclusions set. All time slots will be available for scheduling.</p>';
        return;
    }
    
    let html = '';
    exclusions.forEach((exclusion, index) => {
        let reasonColor = 'bg-gray-100 text-gray-700';
        if (exclusion.reason === 'African Studies') {
            reasonColor = 'bg-success text-white';
        } else if (exclusion.reason === 'Liberal Courses') {
            reasonColor = 'bg-primary text-white';
        }
        
        html += `
            <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded mb-2">
                <div class="d-flex align-items-center gap-3">
                    <span class="badge ${reasonColor}">${exclusion.reason}</span>
                    <span class="fw-medium text-capitalize">${exclusion.dayOfWeek}</span>
                    <span>•</span>
                    <span>${exclusion.startTime} - ${exclusion.endTime}</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeExclusion(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function removeExclusion(index) {
    exclusions.splice(index, 1);
    updateExclusionsList();
}

// Generate timetable
function generateTimetable() {
    const sessionId = document.getElementById('generate-session').value;
    const semester = document.getElementById('generate-semester').value;
    const type = document.getElementById('generate-type').value;
    
    if (!sessionId || !semester) {
        alert('Please select both session and semester');
        return;
    }
    
    let message = `${type === 'lecture' ? 'Lecture' : 'Exam'} timetable generation initiated for Semester ${semester}`;
    
    if (exclusions.length > 0) {
        message += '\n\nTime exclusions set:';
        exclusions.forEach(exclusion => {
            message += `\n• ${exclusion.dayOfWeek.charAt(0).toUpperCase() + exclusion.dayOfWeek.slice(1)}: ${exclusion.startTime} - ${exclusion.endTime} (${exclusion.reason})`;
        });
        message += '\n\nThese time slots will be reserved for special courses.';
    }
    
    alert(message);
    hideGenerateModal();
}

// Cell click handling
function handleCellClick(day, room, timeIndex) {
    editingCell = { day, room, timeIndex };
    
    // Check if there's an existing course at this time
    const existingCourse = findCourseAtTime(day, room, timeIndex);
    
    if (existingCourse) {
        // Editing existing course
        document.getElementById('edit-modal-title').textContent = 'Edit Course';
        document.getElementById('edit-course-code').value = existingCourse.course.code;
        document.getElementById('edit-start-time').value = existingCourse.start_time;
        document.getElementById('delete-course-btn').style.display = 'block';
        
        // Set classes
        const classesContainer = document.getElementById('classes-container');
        classesContainer.innerHTML = '';
        existingCourse.course.classes.forEach((className, index) => {
            addClassInput(className, index === 0);
        });
    } else {
        // Adding new course
        document.getElementById('edit-modal-title').textContent = 'Add Course';
        document.getElementById('edit-course-code').value = '';
        document.getElementById('edit-start-time').value = timeIndex;
        document.getElementById('delete-course-btn').style.display = 'none';
        
        // Reset classes
        const classesContainer = document.getElementById('classes-container');
        classesContainer.innerHTML = `
            <div class="d-flex gap-2 mb-2">
                <input type="text" class="form-control class-input" placeholder="Class 1">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeClass(this)">Remove</button>
            </div>
        `;
    }
    
    document.getElementById('edit-day').value = day;
    document.getElementById('edit-room').value = room;
    
    showEditModal();
}

function findCourseAtTime(day, room, timeIndex) {
    if (!tableData[day] || !tableData[day][room]) return null;
    
    return tableData[day][room].find(course => 
        timeIndex >= course.start_time && timeIndex < course.start_time + course.spans
    );
}

// Class management
function addClass() {
    const container = document.getElementById('classes-container');
    const classCount = container.children.length;
    
    const div = document.createElement('div');
    div.className = 'd-flex gap-2 mb-2';
    div.innerHTML = `
        <input type="text" class="form-control class-input" placeholder="Class ${classCount + 1}">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeClass(this)">Remove</button>
    `;
    
    container.appendChild(div);
}

function addClassInput(className, isFirst = false) {
    const container = document.getElementById('classes-container');
    
    const div = document.createElement('div');
    div.className = 'd-flex gap-2 mb-2';
    div.innerHTML = `
        <input type="text" class="form-control class-input" value="${className}" placeholder="Class">
        ${!isFirst ? '<button type="button" class="btn btn-sm btn-outline-danger" onclick="removeClass(this)">Remove</button>' : ''}
    `;
    
    container.appendChild(div);
}

function removeClass(button) {
    const container = document.getElementById('classes-container');
    if (container.children.length > 1) {
        button.parentElement.remove();
    }
}

// Save course
function saveCourse() {
    if (!editingCell) return;
    
    const courseCode = document.getElementById('edit-course-code').value.trim();
    if (!courseCode) {
        alert('Please enter a course code');
        return;
    }
    
    const startTime = parseInt(document.getElementById('edit-start-time').value);
    const classes = Array.from(document.querySelectorAll('.class-input'))
        .map(input => input.value.trim())
        .filter(value => value !== '');
    
    if (classes.length === 0) {
        alert('Please enter at least one class');
        return;
    }
    
    const { day, room, timeIndex } = editingCell;
    
    // Prepare form data for API call
    const formData = new FormData();
    formData.append('action', 'save_course');
    formData.append('day', day);
    formData.append('room', room);
    formData.append('course_code', courseCode);
    formData.append('start_time', startTime);
    formData.append('lecturer_name', 'Dr. Smith'); // Default lecturer
    
    // Add classes as array
    classes.forEach((className, index) => {
        formData.append(`classes[${index}]`, className);
    });
    
    // Make API call to save course
    fetch('api_timetable_template.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideEditModal();
            // Reload the page to show updated data
            location.reload();
        } else {
            alert('Error saving course: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving course. Please try again.');
    });
}

// Delete course
function deleteCourse() {
    if (!editingCell) return;
    
    if (!confirm('Are you sure you want to delete this course?')) return;
    
    const { day, room, timeIndex } = editingCell;
    
    // Prepare form data for API call
    const formData = new FormData();
    formData.append('action', 'delete_course');
    formData.append('day', day);
    formData.append('room', room);
    formData.append('start_time', timeIndex);
    
    // Make API call to delete course
    fetch('api_timetable_template.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideEditModal();
            // Reload the page to show updated data
            location.reload();
        } else {
            alert('Error deleting course: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting course. Please try again.');
    });
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
