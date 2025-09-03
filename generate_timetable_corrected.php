<?php
/**
 * CORRECTED Timetable Generation
 * Professional implementation with proper stream logic:
 * - Only CLASSES are stream-specific
 * - Courses, lecturers, rooms are GLOBAL
 * - Stream affects only time periods and class scheduling
 */

include 'connect.php';

// Include corrected stream manager
include_once 'includes/stream_manager_corrected.php';
$streamManager = getStreamManager();

// Enhanced error handling
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$pageTitle = 'Professional Timetable Generation';
include 'includes/header.php';
include 'includes/sidebar.php';

$success_message = '';
$error_message = '';
$generation_log = [];

// ============================================================================
// TIMETABLE GENERATION LOGIC
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'generate_timetable') {
        $semester = $_POST['semester'] ?? 'first';
        $academic_year = $_POST['academic_year'] ?? '2024/2025';
        $current_stream_id = $streamManager->getCurrentStreamId();
        
        $generation_log[] = "Starting timetable generation for stream: " . $streamManager->getCurrentStreamName();
        $generation_log[] = "Semester: $semester, Academic Year: $academic_year";
        
        try {
            $start_time = microtime(true);
            
            // STEP 1: Clear existing timetable for this stream/semester/year
            $clear_sql = "DELETE t FROM timetable t 
                         JOIN class_courses cc ON t.class_course_id = cc.id 
                         JOIN classes c ON cc.class_id = c.id 
                         WHERE c.stream_id = ? AND t.semester = ? AND t.academic_year = ?";
            $clear_stmt = $conn->prepare($clear_sql);
            $clear_stmt->bind_param('iss', $current_stream_id, $semester, $academic_year);
            $clear_stmt->execute();
            $cleared_entries = $clear_stmt->affected_rows;
            $clear_stmt->close();
            
            $generation_log[] = "Cleared $cleared_entries existing timetable entries";
            
            // STEP 2: Get stream-specific time slots and days
            $time_slots = [];
            $time_slots_result = $streamManager->getCurrentStreamTimeSlots();
            while ($ts = $time_slots_result->fetch_assoc()) {
                $time_slots[] = $ts;
            }
            $generation_log[] = "Found " . count($time_slots) . " available time slots for this stream";
            
            $days = [];
            $days_result = $streamManager->getCurrentStreamDays();
            while ($day = $days_result->fetch_assoc()) {
                $days[] = $day;
            }
            $generation_log[] = "Found " . count($days) . " available days for this stream";
            
            // STEP 3: Get ALL global rooms (not stream-filtered)
            $rooms_sql = "SELECT r.id, r.name, r.room_type, r.capacity, r.building_name
                         FROM rooms r 
                         WHERE r.is_active = 1 
                         ORDER BY r.room_type, r.capacity DESC";
            $rooms_result = $conn->query($rooms_sql);
            $rooms = [];
            while ($room = $rooms_result->fetch_assoc()) {
                $rooms[] = $room;
            }
            $generation_log[] = "Found " . count($rooms) . " available rooms (global)";
            
            // STEP 4: Get assignments for CURRENT STREAM classes only
            $assignments_sql = "SELECT 
                                    cc.id as class_course_id,
                                    cc.class_id,
                                    cc.course_id,
                                    c.name as class_name,
                                    c.capacity as class_capacity,
                                    c.current_enrollment,
                                    c.divisions_count,
                                    co.course_code,
                                    co.course_name,
                                    co.hours_per_week,
                                    co.preferred_room_type,
                                    co.credits
                               FROM class_courses cc
                               JOIN classes c ON cc.class_id = c.id
                               JOIN courses co ON cc.course_id = co.id
                               WHERE c.stream_id = ? 
                               AND cc.semester = ? 
                               AND cc.academic_year = ? 
                               AND cc.is_active = 1 
                               AND c.is_active = 1 
                               AND co.is_active = 1
                               ORDER BY c.department_id, c.level_id, c.name";
            
            $assignments_stmt = $conn->prepare($assignments_sql);
            $assignments_stmt->bind_param('iss', $current_stream_id, $semester, $academic_year);
            $assignments_stmt->execute();
            $assignments_result = $assignments_stmt->get_result();
            
            $assignments = [];
            while ($assignment = $assignments_result->fetch_assoc()) {
                $assignments[] = $assignment;
            }
            $assignments_stmt->close();
            
            $generation_log[] = "Found " . count($assignments) . " class-course assignments to schedule";
            
            if (empty($assignments)) {
                throw new Exception("No class-course assignments found for the current stream. Please assign courses to classes first.");
            }
            
            // STEP 5: Get ALL available lecturers (global) with their course assignments
            $lecturers_sql = "SELECT 
                                 lc.id as lecturer_course_id,
                                 lc.lecturer_id,
                                 lc.course_id,
                                 l.name as lecturer_name,
                                 l.max_hours_per_week,
                                 l.preferred_time_slots,
                                 l.unavailable_days
                             FROM lecturer_courses lc
                             JOIN lecturers l ON lc.lecturer_id = l.id
                             WHERE lc.is_active = 1 AND l.is_active = 1";
            $lecturers_result = $conn->query($lecturers_sql);
            
            $lecturer_courses = [];
            while ($lc = $lecturers_result->fetch_assoc()) {
                $lecturer_courses[$lc['course_id']][] = $lc;
            }
            $generation_log[] = "Loaded lecturer-course mappings for " . count($lecturer_courses) . " courses";
            
            // STEP 6: Professional scheduling algorithm
            $success_count = 0;
            $error_count = 0;
            $conflict_count = 0;
            
            // Shuffle assignments to avoid bias
            shuffle($assignments);
            
            foreach ($assignments as $assignment) {
                $placed = false;
                $attempts = 0;
                $max_attempts = 50;
                
                // Get available lecturers for this course
                $available_lecturers = $lecturer_courses[$assignment['course_id']] ?? [];
                
                if (empty($available_lecturers)) {
                    $generation_log[] = "No lecturers assigned to course {$assignment['course_code']}";
                    $error_count++;
                    continue;
                }
                
                // Try to place this assignment
                while (!$placed && $attempts < $max_attempts) {
                    $attempts++;
                    
                    // Select random time slot and day from stream-specific options
                    $time_slot = $time_slots[array_rand($time_slots)];
                    $day = $days[array_rand($days)];
                    $lecturer_course = $available_lecturers[array_rand($available_lecturers)];
                    
                    // Find suitable room based on course preferences and class capacity
                    $suitable_rooms = array_filter($rooms, function($room) use ($assignment) {
                        // Check capacity
                        if ($room['capacity'] < $assignment['current_enrollment']) {
                            return false;
                        }
                        
                        // Check room type preference
                        if (!empty($assignment['preferred_room_type']) && 
                            $room['room_type'] !== $assignment['preferred_room_type']) {
                            // Allow fallback to classroom for most course types
                            return $room['room_type'] === 'classroom';
                        }
                        
                        return true;
                    });
                    
                    if (empty($suitable_rooms)) {
                        continue; // No suitable rooms, try again
                    }
                    
                    $room = $suitable_rooms[array_rand($suitable_rooms)];
                    
                    // PROFESSIONAL CONFLICT DETECTION
                    $conflicts = $this->checkProfessionalConflicts(
                        $assignment['class_course_id'],
                        $lecturer_course['lecturer_course_id'],
                        $day['id'],
                        $time_slot['id'],
                        $room['id'],
                        $semester,
                        $academic_year
                    );
                    
                    if (empty($conflicts)) {
                        // No conflicts - insert the timetable entry
                        $insert_sql = "INSERT INTO timetable 
                                      (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, 
                                       semester, academic_year, created_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'TimetableGenerator')";
                        
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bind_param('iiiiiss', 
                            $assignment['class_course_id'],
                            $lecturer_course['lecturer_course_id'],
                            $day['id'],
                            $time_slot['id'],
                            $room['id'],
                            $semester,
                            $academic_year
                        );
                        
                        if ($insert_stmt->execute()) {
                            $placed = true;
                            $success_count++;
                        }
                        $insert_stmt->close();
                    } else {
                        $conflict_count++;
                    }
                }
                
                if (!$placed) {
                    $error_count++;
                    $generation_log[] = "Failed to place: {$assignment['class_name']} - {$assignment['course_code']} after $max_attempts attempts";
                }
            }
            
            $end_time = microtime(true);
            $generation_time = round($end_time - $start_time, 2);
            
            // Log generation results
            $log_sql = "INSERT INTO timetable_generation_log 
                       (stream_id, semester, academic_year, total_assignments, successful_placements, 
                        failed_placements, conflicts_detected, generation_time_seconds, generated_by, generation_notes) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $total_assignments = count($assignments);
            $generated_by = $_SESSION['user_name'] ?? 'System';
            $notes = implode("\n", $generation_log);
            
            $log_stmt->bind_param('issiiiidss', 
                $current_stream_id, $semester, $academic_year, $total_assignments,
                $success_count, $error_count, $conflict_count, $generation_time, $generated_by, $notes
            );
            $log_stmt->execute();
            $log_stmt->close();
            
            // Generate result message
            if ($success_count > 0) {
                $success_message = "Timetable generated successfully! ";
                $success_message .= "$success_count entries created, $error_count failed, $conflict_count conflicts detected. ";
                $success_message .= "Generation completed in {$generation_time}s.";
                
                if ($error_count > 0) {
                    $warning_message = "Some assignments could not be placed. Check the generation log for details.";
                }
            } else {
                $error_message = "Timetable generation failed. No entries could be placed.";
            }
            
        } catch (Exception $e) {
            $error_message = "Timetable generation failed: " . $e->getMessage();
            $generation_log[] = "ERROR: " . $e->getMessage();
        }
    }
}

/**
 * Professional conflict detection method
 */
function checkProfessionalConflicts($class_course_id, $lecturer_course_id, $day_id, $time_slot_id, $room_id, $semester, $academic_year) {
    global $conn;
    
    $conflicts = [];
    
    // 1. Check room conflicts
    $room_check = $conn->prepare("SELECT COUNT(*) as count FROM timetable 
                                 WHERE room_id = ? AND day_id = ? AND time_slot_id = ? 
                                 AND semester = ? AND academic_year = ?");
    $room_check->bind_param('iiiss', $room_id, $day_id, $time_slot_id, $semester, $academic_year);
    $room_check->execute();
    $room_result = $room_check->get_result();
    if ($room_result->fetch_assoc()['count'] > 0) {
        $conflicts[] = 'room_occupied';
    }
    $room_check->close();
    
    // 2. Check lecturer conflicts
    $lecturer_check = $conn->prepare("SELECT COUNT(*) as count FROM timetable t
                                     JOIN lecturer_courses lc1 ON t.lecturer_course_id = lc1.id
                                     JOIN lecturer_courses lc2 ON lc2.id = ?
                                     WHERE lc1.lecturer_id = lc2.lecturer_id 
                                     AND t.day_id = ? AND t.time_slot_id = ?
                                     AND t.semester = ? AND t.academic_year = ?");
    $lecturer_check->bind_param('iiiss', $lecturer_course_id, $day_id, $time_slot_id, $semester, $academic_year);
    $lecturer_check->execute();
    $lecturer_result = $lecturer_check->get_result();
    if ($lecturer_result->fetch_assoc()['count'] > 0) {
        $conflicts[] = 'lecturer_busy';
    }
    $lecturer_check->close();
    
    // 3. Check class conflicts
    $class_check = $conn->prepare("SELECT COUNT(*) as count FROM timetable t
                                  JOIN class_courses cc1 ON t.class_course_id = cc1.id
                                  JOIN class_courses cc2 ON cc2.id = ?
                                  WHERE cc1.class_id = cc2.class_id
                                  AND t.day_id = ? AND t.time_slot_id = ?
                                  AND t.semester = ? AND t.academic_year = ?");
    $class_check->bind_param('iiiss', $class_course_id, $day_id, $time_slot_id, $semester, $academic_year);
    $class_check->execute();
    $class_result = $class_check->get_result();
    if ($class_result->fetch_assoc()['count'] > 0) {
        $conflicts[] = 'class_busy';
    }
    $class_check->close();
    
    return $conflicts;
}

// ============================================================================
// DATA PREPARATION FOR UI
// ============================================================================

$current_stream_id = $streamManager->getCurrentStreamId();
$stream_settings = $streamManager->getCurrentStreamSettings();
$stream_statistics = $streamManager->getStreamStatistics();

// Get current timetable summary
$timetable_summary_sql = "SELECT 
                             COUNT(*) as total_entries,
                             COUNT(DISTINCT cc.class_id) as classes_scheduled,
                             COUNT(DISTINCT cc.course_id) as courses_scheduled,
                             COUNT(DISTINCT lc.lecturer_id) as lecturers_used,
                             COUNT(DISTINCT t.room_id) as rooms_used
                         FROM timetable t
                         JOIN class_courses cc ON t.class_course_id = cc.id
                         JOIN classes c ON cc.class_id = c.id
                         JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                         WHERE c.stream_id = ?";
$timetable_summary_stmt = $conn->prepare($timetable_summary_sql);
$timetable_summary_stmt->bind_param('i', $current_stream_id);
$timetable_summary_stmt->execute();
$timetable_summary = $timetable_summary_stmt->get_result()->fetch_assoc();
$timetable_summary_stmt->close();

// Get recent generation logs
$recent_logs_sql = "SELECT * FROM timetable_generation_log 
                   WHERE stream_id = ? 
                   ORDER BY created_at DESC 
                   LIMIT 5";
$recent_logs_stmt = $conn->prepare($recent_logs_sql);
$recent_logs_stmt->bind_param('i', $current_stream_id);
$recent_logs_stmt->execute();
$recent_logs_result = $recent_logs_stmt->get_result();

?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><?php echo $pageTitle; ?></h2>
                <p class="text-muted">Generate timetables with professional conflict detection and stream-aware scheduling</p>
            </div>
            <div>
                <?php echo $streamManager->getStreamSelector(); ?>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stream Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-stream"></i> Current Stream Settings</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Stream:</strong></td>
                                <td><?php echo $streamManager->getCurrentStreamName(); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Period:</strong></td>
                                <td><?php echo $stream_settings['period_start'] ?? 'N/A'; ?> - <?php echo $stream_settings['period_end'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Break Time:</strong></td>
                                <td><?php echo $stream_settings['break_start'] ?? 'N/A'; ?> - <?php echo $stream_settings['break_end'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Active Days:</strong></td>
                                <td><?php echo implode(', ', array_map('ucfirst', $stream_settings['active_days'] ?? [])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Classes:</strong></td>
                                <td><?php echo $stream_statistics['total_classes'] ?? 0; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Current Timetable Status</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Total Entries:</strong></td>
                                <td><?php echo $timetable_summary['total_entries']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Classes Scheduled:</strong></td>
                                <td><?php echo $timetable_summary['classes_scheduled']; ?> / <?php echo $stream_statistics['total_classes']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Courses Scheduled:</strong></td>
                                <td><?php echo $timetable_summary['courses_scheduled']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Lecturers Used:</strong></td>
                                <td><?php echo $timetable_summary['lecturers_used']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Rooms Used:</strong></td>
                                <td><?php echo $timetable_summary['rooms_used']; ?> / <?php echo count($rooms); ?> (Global)</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Generation Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-cogs"></i> Generate Timetable</h5>
            </div>
            <div class="card-body">
                <form method="POST" onsubmit="return confirmGeneration()">
                    <input type="hidden" name="action" value="generate_timetable">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <label for="semester" class="form-label">Semester <span class="text-danger">*</span></label>
                            <select name="semester" id="semester" class="form-select" required>
                                <option value="first">First Semester</option>
                                <option value="second">Second Semester</option>
                                <option value="summer">Summer Semester</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="academic_year" class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <input type="text" name="academic_year" id="academic_year" class="form-control" 
                                   value="2024/2025" pattern="[0-9]{4}/[0-9]{4}" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-success d-block w-100">
                                <i class="fas fa-play"></i> Generate Timetable
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            This will generate a timetable for <strong><?php echo $streamManager->getCurrentStreamName(); ?></strong> stream only.
                            Global resources (courses, lecturers, rooms) will be used but scheduling will respect stream time periods.
                        </small>
                    </div>
                </form>
            </div>
        </div>

        <!-- Generation Log -->
        <?php if (!empty($generation_log)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-list-alt"></i> Generation Log</h5>
                </div>
                <div class="card-body">
                    <div class="log-container" style="max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 1rem; border-radius: 0.375rem;">
                        <?php foreach ($generation_log as $log_entry): ?>
                            <div class="log-entry"><?php echo htmlspecialchars($log_entry); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Generation History -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Recent Generation History</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_logs_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Semester/Year</th>
                                    <th>Assignments</th>
                                    <th>Success Rate</th>
                                    <th>Generation Time</th>
                                    <th>Generated By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = $recent_logs_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                                        <td><?php echo $log['semester']; ?> <?php echo $log['academic_year']; ?></td>
                                        <td><?php echo $log['total_assignments']; ?></td>
                                        <td>
                                            <?php 
                                            $success_rate = $log['total_assignments'] > 0 ? 
                                                round(($log['successful_placements'] / $log['total_assignments']) * 100, 1) : 0;
                                            $rate_class = $success_rate >= 90 ? 'success' : ($success_rate >= 70 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $rate_class; ?>"><?php echo $success_rate; ?>%</span>
                                            <small class="text-muted">
                                                (<?php echo $log['successful_placements']; ?>/<?php echo $log['total_assignments']; ?>)
                                            </small>
                                        </td>
                                        <td><?php echo $log['generation_time_seconds']; ?>s</td>
                                        <td><?php echo htmlspecialchars($log['generated_by']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                        <p class="text-muted">No generation history available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Enhanced UX -->
<script>
function confirmGeneration() {
    const semester = document.getElementById('semester').value;
    const academicYear = document.getElementById('academic_year').value;
    const streamName = '<?php echo $streamManager->getCurrentStreamName(); ?>';
    
    return confirm(
        `Are you sure you want to generate a new timetable?\n\n` +
        `Stream: ${streamName}\n` +
        `Semester: ${semester}\n` +
        `Academic Year: ${academicYear}\n\n` +
        `This will replace any existing timetable for this stream and semester.`
    );
}

// Stream selector change handler
function changeStream(streamId) {
    if (streamId) {
        // Use the existing change_stream.php or implement AJAX call
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'change_stream.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'stream_id';
        input.value = streamId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
