<?php
/**
 * FINAL Professional Timetable Generation
 * Based on your actual schema where only CLASSES are stream-specific
 * Uses global courses, lecturers, and rooms with stream-aware scheduling
 */

include 'connect.php';
include_once 'includes/stream_manager_final.php';

$streamManager = getStreamManager();
$pageTitle = 'Professional Timetable Generation';
include 'includes/header.php';
include 'includes/sidebar.php';

$success_message = '';
$error_message = '';
$warning_message = '';
$generation_log = [];

// ============================================================================
// PROFESSIONAL TIMETABLE GENERATION LOGIC
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'generate_professional_timetable') {
        $semester = $_POST['semester'] ?? 'first';
        $academic_year = $_POST['academic_year'] ?? '2024/2025';
        $clear_existing = isset($_POST['clear_existing']) ? true : false;
        $max_attempts_per_assignment = (int)($_POST['max_attempts'] ?? 50);
        $current_stream_id = $streamManager->getCurrentStreamId();
        
        $generation_log[] = "🚀 Starting professional timetable generation";
        $generation_log[] = "📊 Stream: " . $streamManager->getCurrentStreamName();
        $generation_log[] = "📅 Academic Period: $semester $academic_year";
        $generation_log[] = "🔄 Clear Existing: " . ($clear_existing ? 'Yes' : 'No');
        
        try {
            $start_time = microtime(true);
            
            // STEP 1: Clear existing timetable if requested
            if ($clear_existing) {
                $clear_sql = "DELETE t FROM timetable t 
                             JOIN classes c ON t.class_id = c.id 
                             WHERE c.stream_id = ? AND t.semester = ? AND t.academic_year = ?";
                $clear_stmt = $conn->prepare($clear_sql);
                $clear_stmt->bind_param('iss', $current_stream_id, $semester, $academic_year);
                $clear_stmt->execute();
                $cleared_entries = $clear_stmt->affected_rows;
                $clear_stmt->close();
                
                $generation_log[] = "🗑️ Cleared $cleared_entries existing entries";
            }
            
            // STEP 2: Get stream-specific time slots and days
            $time_slots = [];
            $time_slots_result = $streamManager->getStreamTimeSlots();
            while ($ts = $time_slots_result->fetch_assoc()) {
                $time_slots[] = $ts;
            }
            $generation_log[] = "⏰ Available time slots: " . count($time_slots);
            
            $days = [];
            $days_result = $streamManager->getStreamDays();
            while ($day = $days_result->fetch_assoc()) {
                $days[] = $day;
            }
            $generation_log[] = "📅 Available days: " . count($days);
            
            if (empty($time_slots) || empty($days)) {
                throw new Exception("No time slots or days configured for current stream. Please configure stream settings first.");
            }
            
            // STEP 3: Get ALL global rooms (not stream-filtered)
            $rooms_sql = "SELECT r.id, r.name, r.room_type, r.capacity, b.name as building_name
                         FROM rooms r 
                         LEFT JOIN buildings b ON r.building_id = b.id
                         WHERE r.is_active = 1 
                         ORDER BY r.room_type, r.capacity DESC";
            $rooms_result = $conn->query($rooms_sql);
            $rooms = [];
            while ($room = $rooms_result->fetch_assoc()) {
                $rooms[] = $room;
            }
            $generation_log[] = "🏢 Global rooms available: " . count($rooms);
            
            // STEP 4: Get class-course assignments for CURRENT STREAM only
            $assignments_sql = "SELECT 
                                    cc.id as class_course_id,
                                    cc.class_id,
                                    cc.course_id,
                                    cc.lecturer_id,
                                    cc.quality_score,
                                    c.name as class_name,
                                    c.code as class_code,
                                    c.total_capacity,
                                    c.current_enrollment,
                                    c.divisions_count,
                                    co.code as course_code,
                                    co.name as course_name,
                                    co.hours_per_week,
                                    co.preferred_room_type,
                                    co.credits,
                                    co.course_type,
                                    l.name as lecturer_name,
                                    l.max_hours_per_week as lecturer_max_hours,
                                    d.name as department_name
                               FROM class_courses cc
                               JOIN classes c ON cc.class_id = c.id
                               JOIN courses co ON cc.course_id = co.id
                               LEFT JOIN lecturers l ON cc.lecturer_id = l.id
                               JOIN programs p ON c.program_id = p.id
                               JOIN departments d ON p.department_id = d.id
                               WHERE c.stream_id = ? 
                               AND cc.semester = ? 
                               AND cc.academic_year = ? 
                               AND cc.is_active = 1 
                               AND c.is_active = 1 
                               AND co.is_active = 1
                               ORDER BY cc.quality_score DESC, d.name, c.level_id, c.name";
            
            $assignments_stmt = $conn->prepare($assignments_sql);
            $assignments_stmt->bind_param('iss', $current_stream_id, $semester, $academic_year);
            $assignments_stmt->execute();
            $assignments_result = $assignments_stmt->get_result();
            
            $assignments = [];
            while ($assignment = $assignments_result->fetch_assoc()) {
                $assignments[] = $assignment;
            }
            $assignments_stmt->close();
            
            $generation_log[] = "📚 Class-course assignments to schedule: " . count($assignments);
            
            if (empty($assignments)) {
                throw new Exception("No class-course assignments found for the current stream and academic period. Please create assignments first using the Class-Course Management.");
            }
            
            // STEP 5: Professional scheduling algorithm
            $success_count = 0;
            $error_count = 0;
            $conflict_count = 0;
            $quality_issues = 0;
            
            foreach ($assignments as $assignment) {
                $placed = false;
                $attempts = 0;
                
                // Skip low-quality assignments
                if ($assignment['quality_score'] !== null && $assignment['quality_score'] < 10) {
                    $generation_log[] = "⚠️ Skipping low-quality assignment: {$assignment['class_name']} - {$assignment['course_code']} (Quality: {$assignment['quality_score']})";
                    $quality_issues++;
                    continue;
                }
                
                // Get lecturer if not assigned
                $lecturer_id = $assignment['lecturer_id'];
                if (!$lecturer_id) {
                    // Find suitable lecturer from lecturer_courses (global)
                    $lecturer_sql = "SELECT lc.lecturer_id, l.name 
                                    FROM lecturer_courses lc 
                                    JOIN lecturers l ON lc.lecturer_id = l.id
                                    WHERE lc.course_id = ? AND lc.is_active = 1 AND l.is_active = 1
                                    ORDER BY lc.is_primary DESC, lc.competency_level DESC
                                    LIMIT 1";
                    $lecturer_stmt = $conn->prepare($lecturer_sql);
                    $lecturer_stmt->bind_param('i', $assignment['course_id']);
                    $lecturer_stmt->execute();
                    $lecturer_result = $lecturer_stmt->get_result();
                    
                    if ($lecturer_row = $lecturer_result->fetch_assoc()) {
                        $lecturer_id = $lecturer_row['lecturer_id'];
                        $generation_log[] = "👨‍🏫 Auto-assigned lecturer: {$lecturer_row['name']} for {$assignment['course_code']}";
                    } else {
                        $generation_log[] = "❌ No lecturer available for {$assignment['course_code']}";
                        $error_count++;
                        continue;
                    }
                    $lecturer_stmt->close();
                }
                
                // Try to place this assignment
                while (!$placed && $attempts < $max_attempts_per_assignment) {
                    $attempts++;
                    
                    // Select random time slot and day from stream-specific options
                    $time_slot = $time_slots[array_rand($time_slots)];
                    $day = $days[array_rand($days)];
                    
                    // Find suitable room based on course preferences and class capacity
                    $suitable_rooms = array_filter($rooms, function($room) use ($assignment) {
                        // Check capacity
                        if ($room['capacity'] < $assignment['current_enrollment']) {
                            return false;
                        }
                        
                        // Check room type preference
                        if (!empty($assignment['preferred_room_type']) && 
                            $room['room_type'] !== $assignment['preferred_room_type']) {
                            // Allow classroom as fallback for most course types
                            return $room['room_type'] === 'classroom';
                        }
                        
                        return true;
                    });
                    
                    if (empty($suitable_rooms)) {
                        continue; // No suitable rooms, try again
                    }
                    
                    $room = $suitable_rooms[array_rand($suitable_rooms)];
                    
                    // PROFESSIONAL CONFLICT DETECTION using your schema
                    $conflicts = $this->checkConflictsProfessional(
                        $assignment['class_id'],
                        $lecturer_id,
                        $room['id'],
                        $day['id'],
                        $time_slot['id'],
                        $semester,
                        $academic_year
                    );
                    
                    if (empty($conflicts)) {
                        // No conflicts - insert the timetable entry
                        $insert_sql = "INSERT INTO timetable 
                                      (class_id, course_id, lecturer_id, room_id, day_id, time_slot_id, 
                                       semester, academic_year, timetable_type, created_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'lecture', 'Professional Generator')";
                        
                        $insert_stmt = $conn->prepare($insert_sql);
                        $insert_stmt->bind_param('iiiiiiiss', 
                            $assignment['class_id'],
                            $assignment['course_id'],
                            $lecturer_id,
                            $room['id'],
                            $day['id'],
                            $time_slot['id'],
                            $semester,
                            $academic_year
                        );
                        
                        if ($insert_stmt->execute()) {
                            $placed = true;
                            $success_count++;
                        } else {
                            $generation_log[] = "💾 Database error placing {$assignment['class_name']} - {$assignment['course_code']}: " . $conn->error;
                        }
                        $insert_stmt->close();
                    } else {
                        $conflict_count++;
                    }
                }
                
                if (!$placed) {
                    $error_count++;
                    $generation_log[] = "❌ Failed to place: {$assignment['class_name']} - {$assignment['course_code']} after $max_attempts_per_assignment attempts";
                }
            }
            
            $end_time = microtime(true);
            $generation_time = round($end_time - $start_time, 3);
            
            $generation_log[] = "⏱️ Generation completed in {$generation_time} seconds";
            $generation_log[] = "📊 Results: $success_count successful, $error_count failed, $conflict_count conflicts, $quality_issues quality issues";
            
            // Log generation results in database
            if (class_exists('PDO') || method_exists($conn, 'prepare')) {
                $log_sql = "INSERT INTO timetable_generation_log 
                           (stream_id, semester, academic_year, total_assignments, successful_placements, 
                            failed_placements, conflicts_detected, generation_time_seconds, generated_by, generation_notes) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                try {
                    $log_stmt = $conn->prepare($log_sql);
                    $total_assignments = count($assignments);
                    $generated_by = $_SESSION['user_name'] ?? 'System';
                    $notes = implode("\n", $generation_log);
                    
                    $log_stmt->bind_param('issiiidss', 
                        $current_stream_id, $semester, $academic_year, $total_assignments,
                        $success_count, $error_count, $conflict_count, $generation_time, $generated_by, $notes
                    );
                    $log_stmt->execute();
                    $log_stmt->close();
                } catch (Exception $e) {
                    $generation_log[] = "⚠️ Could not log generation results: " . $e->getMessage();
                }
            }
            
            // Generate professional result message
            if ($success_count > 0) {
                $success_rate = round(($success_count / count($assignments)) * 100, 1);
                $success_message = "✅ Professional timetable generated successfully! ";
                $success_message .= "$success_count/$total_assignments assignments placed ($success_rate% success rate). ";
                $success_message .= "Generation time: {$generation_time}s.";
                
                if ($error_count > 0) {
                    $warning_message = "⚠️ $error_count assignments could not be placed. ";
                    $warning_message .= "$conflict_count conflicts detected. ";
                    if ($quality_issues > 0) {
                        $warning_message .= "$quality_issues low-quality assignments skipped.";
                    }
                }
            } else {
                $error_message = "❌ Timetable generation failed. No assignments could be placed. Check the generation log for details.";
            }
            
        } catch (Exception $e) {
            $error_message = "💥 Timetable generation failed: " . $e->getMessage();
            $generation_log[] = "💥 CRITICAL ERROR: " . $e->getMessage();
        }
    }
}

/**
 * Professional conflict detection based on your schema
 */
function checkConflictsProfessional($class_id, $lecturer_id, $room_id, $day_id, $time_slot_id, $semester, $academic_year) {
    global $conn;
    
    $conflicts = [];
    
    // 1. Room conflict check
    $room_check = $conn->prepare("SELECT COUNT(*) as count, 
                                         (SELECT CONCAT(c.name, ' - ', co.code) FROM classes c JOIN courses co ON 1=1 LIMIT 1) as assignment
                                 FROM timetable t
                                 JOIN classes c ON t.class_id = c.id
                                 JOIN courses co ON t.course_id = co.id
                                 WHERE t.room_id = ? AND t.day_id = ? AND t.time_slot_id = ? 
                                 AND t.semester = ? AND t.academic_year = ? AND t.is_active = 1");
    $room_check->bind_param('iiiss', $room_id, $day_id, $time_slot_id, $semester, $academic_year);
    $room_check->execute();
    $room_result = $room_check->get_result();
    $room_data = $room_result->fetch_assoc();
    if ($room_data['count'] > 0) {
        $conflicts[] = 'room_occupied';
    }
    $room_check->close();
    
    // 2. Lecturer conflict check
    $lecturer_check = $conn->prepare("SELECT COUNT(*) as count
                                     FROM timetable t
                                     WHERE t.lecturer_id = ? AND t.day_id = ? AND t.time_slot_id = ?
                                     AND t.semester = ? AND t.academic_year = ? AND t.is_active = 1");
    $lecturer_check->bind_param('iiiss', $lecturer_id, $day_id, $time_slot_id, $semester, $academic_year);
    $lecturer_check->execute();
    $lecturer_result = $lecturer_check->get_result();
    if ($lecturer_result->fetch_assoc()['count'] > 0) {
        $conflicts[] = 'lecturer_busy';
    }
    $lecturer_check->close();
    
    // 3. Class conflict check
    $class_check = $conn->prepare("SELECT COUNT(*) as count
                                  FROM timetable t
                                  WHERE t.class_id = ? AND t.day_id = ? AND t.time_slot_id = ?
                                  AND t.semester = ? AND t.academic_year = ? AND t.is_active = 1");
    $class_check->bind_param('iiiss', $class_id, $day_id, $time_slot_id, $semester, $academic_year);
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

// Get current timetable summary for this stream
$timetable_summary_sql = "SELECT 
                             COUNT(*) as total_entries,
                             COUNT(DISTINCT t.class_id) as classes_scheduled,
                             COUNT(DISTINCT t.course_id) as courses_scheduled,
                             COUNT(DISTINCT t.lecturer_id) as lecturers_used,
                             COUNT(DISTINCT t.room_id) as rooms_used,
                             AVG(cc.quality_score) as avg_quality_score
                         FROM timetable t
                         JOIN classes c ON t.class_id = c.id
                         LEFT JOIN class_courses cc ON t.class_id = cc.class_id AND t.course_id = cc.course_id
                         WHERE c.stream_id = ? AND t.is_active = 1";
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

// Get assignment quality distribution
$quality_dist_sql = "SELECT 
                        quality_rating,
                        COUNT(*) as count,
                        ROUND(AVG(quality_score), 1) as avg_score
                     FROM assignment_quality_professional aqp
                     WHERE stream_name = (SELECT name FROM streams WHERE id = ?)
                     GROUP BY quality_rating
                     ORDER BY avg_score DESC";
$quality_dist_stmt = $conn->prepare($quality_dist_sql);
$quality_dist_stmt->bind_param('i', $current_stream_id);
$quality_dist_stmt->execute();
$quality_dist_result = $quality_dist_stmt->get_result();

?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Professional Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-calendar-alt"></i> <?php echo $pageTitle; ?></h2>
                <p class="text-muted">
                    Stream-aware professional timetable generation with quality validation
                    <br><small class="text-info">
                        <i class="fas fa-info-circle"></i>
                        Only classes are stream-specific • Global resources: courses, lecturers, rooms
                    </small>
                </p>
            </div>
            <div>
                <?php echo $streamManager->getStreamSelector(); ?>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($warning_message)): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <?php echo $warning_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Professional Dashboard -->
        <div class="row mb-4">
            <!-- Stream Information -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header" style="background-color: <?php echo $stream_settings['color_code'] ?? '#007bff'; ?>; color: white;">
                        <h5><i class="fas fa-stream"></i> Current Stream</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td><?php echo htmlspecialchars($streamManager->getCurrentStreamName()); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Period:</strong></td>
                                <td><?php echo $stream_settings['period_start'] ?? 'N/A'; ?> - <?php echo $stream_settings['period_end'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Break:</strong></td>
                                <td><?php echo $stream_settings['break_start'] ?? 'N/A'; ?> - <?php echo $stream_settings['break_end'] ?? 'N/A'; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Classes:</strong></td>
                                <td><?php echo $stream_statistics['total_classes'] ?? 0; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Assignments:</strong></td>
                                <td><?php echo $stream_statistics['total_assignments'] ?? 0; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Timetable Status -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-chart-bar"></i> Timetable Status</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td><strong>Total Entries:</strong></td>
                                <td><?php echo $timetable_summary['total_entries']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Classes Scheduled:</strong></td>
                                <td><?php echo $timetable_summary['classes_scheduled']; ?> / <?php echo $stream_statistics['total_classes']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Courses Used:</strong></td>
                                <td><?php echo $timetable_summary['courses_scheduled']; ?> (Global)</td>
                            </tr>
                            <tr>
                                <td><strong>Lecturers Used:</strong></td>
                                <td><?php echo $timetable_summary['lecturers_used']; ?> (Global)</td>
                            </tr>
                            <tr>
                                <td><strong>Rooms Used:</strong></td>
                                <td><?php echo $timetable_summary['rooms_used']; ?> / <?php echo count($rooms); ?> (Global)</td>
                            </tr>
                            <tr>
                                <td><strong>Avg Quality:</strong></td>
                                <td>
                                    <?php 
                                    $avg_quality = round($timetable_summary['avg_quality_score'] ?? 0, 1);
                                    $quality_class = $avg_quality >= 35 ? 'success' : ($avg_quality >= 25 ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $quality_class; ?>"><?php echo $avg_quality; ?>/50</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Assignment Quality Distribution -->
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header bg-secondary text-white">
                        <h5><i class="fas fa-award"></i> Assignment Quality</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($quality_dist_result->num_rows > 0): ?>
                            <table class="table table-sm mb-0">
                                <?php while ($quality = $quality_dist_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $quality['quality_rating'] === 'excellent' ? 'success' : 
                                                    ($quality['quality_rating'] === 'good' ? 'primary' : 
                                                    ($quality['quality_rating'] === 'acceptable' ? 'info' : 'warning')); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $quality['quality_rating'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $quality['count']; ?> assignments</td>
                                        <td><small class="text-muted">Avg: <?php echo $quality['avg_score']; ?></small></td>
                                    </tr>
                                <?php endwhile; ?>
                            </table>
                        <?php else: ?>
                            <p class="text-muted text-center">No assignment data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Professional Generation Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5><i class="fas fa-cogs"></i> Professional Timetable Generation</h5>
            </div>
            <div class="card-body">
                <form method="POST" onsubmit="return confirmProfessionalGeneration()">
                    <input type="hidden" name="action" value="generate_professional_timetable">
                    
                    <div class="row">
                        <div class="col-md-3">
                            <label for="semester" class="form-label">Semester <span class="text-danger">*</span></label>
                            <select name="semester" id="semester" class="form-select" required>
                                <option value="first">First Semester</option>
                                <option value="second">Second Semester</option>
                                <option value="summer">Summer Semester</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="academic_year" class="form-label">Academic Year <span class="text-danger">*</span></label>
                            <input type="text" name="academic_year" id="academic_year" class="form-control" 
                                   value="2024/2025" pattern="[0-9]{4}/[0-9]{4}" required>
                        </div>
                        
                        <div class="col-md-2">
                            <label for="max_attempts" class="form-label">Max Attempts</label>
                            <input type="number" name="max_attempts" id="max_attempts" class="form-control" 
                                   value="50" min="10" max="200">
                        </div>
                        
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="clear_existing" id="clear_existing" class="form-check-input">
                                <label for="clear_existing" class="form-check-label">
                                    Clear Existing
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-success d-block w-100">
                                <i class="fas fa-play"></i> Generate
                            </button>
                        </div>
                    </div>
                    
                    <div class="mt-3 p-3 bg-light rounded">
                        <h6><i class="fas fa-info-circle"></i> Professional Generation Process:</h6>
                        <ul class="mb-0 small">
                            <li><strong>Stream-Aware:</strong> Only schedules classes from current stream (<?php echo $streamManager->getCurrentStreamName(); ?>)</li>
                            <li><strong>Global Resources:</strong> Uses all available courses, lecturers, and rooms</li>
                            <li><strong>Quality-Based:</strong> Prioritizes high-quality assignments first</li>
                            <li><strong>Conflict Detection:</strong> Prevents room, lecturer, and class conflicts</li>
                            <li><strong>Period Compliance:</strong> Respects stream time periods (<?php echo $stream_settings['period_start']; ?> - <?php echo $stream_settings['period_end']; ?>)</li>
                        </ul>
                    </div>
                </form>
            </div>
        </div>

        <!-- Generation Log -->
        <?php if (!empty($generation_log)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-terminal"></i> Generation Log</h5>
                </div>
                <div class="card-body">
                    <div class="log-container p-3 bg-dark text-light rounded" style="max-height: 300px; overflow-y: auto; font-family: 'Courier New', monospace;">
                        <?php foreach ($generation_log as $log_entry): ?>
                            <div class="log-entry"><?php echo htmlspecialchars($log_entry); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Professional Generation History -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Professional Generation History</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_logs_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Academic Period</th>
                                    <th>Assignments</th>
                                    <th>Success Rate</th>
                                    <th>Performance</th>
                                    <th>Generated By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = $recent_logs_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($log['created_at'])); ?>
                                            <br><small class="text-muted"><?php echo date('g:i A', strtotime($log['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo ucfirst($log['semester']); ?></strong>
                                            <br><small class="text-muted"><?php echo $log['academic_year']; ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo $log['total_assignments']; ?></strong> total
                                            <br><small class="text-muted">
                                                <?php echo $log['successful_placements']; ?> success, 
                                                <?php echo $log['failed_placements']; ?> failed
                                            </small>
                                        </td>
                                        <td>
                                            <?php 
                                            $success_rate = $log['total_assignments'] > 0 ? 
                                                round(($log['successful_placements'] / $log['total_assignments']) * 100, 1) : 0;
                                            $rate_class = $success_rate >= 90 ? 'success' : ($success_rate >= 70 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge bg-<?php echo $rate_class; ?> fs-6"><?php echo $success_rate; ?>%</span>
                                            <?php if ($log['conflicts_detected'] > 0): ?>
                                                <br><small class="text-warning"><?php echo $log['conflicts_detected']; ?> conflicts</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo $log['generation_time_seconds']; ?>s</strong>
                                            <br><small class="text-muted"><?php echo $log['algorithm_used'] ?? 'Professional'; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['generated_by']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" onclick="showGenerationDetails(<?php echo $log['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Generation History</h5>
                        <p class="text-muted">Generate your first professional timetable to see history here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Professional JavaScript -->
<script>
function confirmProfessionalGeneration() {
    const semester = document.getElementById('semester').value;
    const academicYear = document.getElementById('academic_year').value;
    const clearExisting = document.getElementById('clear_existing').checked;
    const streamName = '<?php echo $streamManager->getCurrentStreamName(); ?>';
    
    let message = `Generate professional timetable?\n\n`;
    message += `Stream: ${streamName}\n`;
    message += `Semester: ${semester}\n`;
    message += `Academic Year: ${academicYear}\n`;
    message += `Clear Existing: ${clearExisting ? 'Yes' : 'No'}\n\n`;
    message += `This will use professional validation and quality scoring.`;
    
    return confirm(message);
}

function changeStream(streamId) {
    if (streamId) {
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

function showGenerationDetails(logId) {
    // This would show detailed generation notes in a modal
    alert('Generation details for log ID: ' + logId + '\n\nThis would show detailed generation notes in a professional implementation.');
}
</script>

<?php include 'includes/footer.php'; ?>
