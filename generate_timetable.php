<?php
include 'connect.php';
// Ensure flash helper is available for PRG redirects
if (file_exists(__DIR__ . '/includes/flash.php')) include_once __DIR__ . '/includes/flash.php';

// Include genetic algorithm components
require_once __DIR__ . '/ga/GeneticAlgorithm.php';
require_once __DIR__ . '/ga/DBLoader.php';
require_once __DIR__ . '/ga/TimetableRepresentation.php';
require_once __DIR__ . '/ga/ConstraintChecker.php';
require_once __DIR__ . '/ga/FitnessEvaluator.php';

// Register application-wide error/exception handlers (match includes/header.php style)
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($ex) {
    $msg = $ex->getMessage() . " in " . $ex->getFile() . ':' . $ex->getLine();
    $escaped = htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<div id=\"mainContent\" class=\"main-content\">";
    echo "<div class=\"card border-danger mb-3\"><div class=\"card-body\">";
    echo "<div style=\"display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;\">";
    echo "<h5 class=\"card-title text-danger\" style=\"margin:0;\">An error occurred</h5>";
    echo "<div><button class=\"btn btn-sm btn-outline-secondary copyErrorBtn\" title=\"Copy error\"><i class=\"fas fa-copy\"></i></button></div>";
    echo "</div>";
    echo "<pre class=\"error-pre\" style=\"white-space:pre-wrap;color:#a00;margin:0;\">" . $escaped . "</pre>";
    echo "</div></div></div>";
    echo "<script>(function(){var btn=document.querySelector('#mainContent .copyErrorBtn'); if(btn){btn.addEventListener('click',function(){var t=document.querySelector('#mainContent .error-pre').textContent||''; if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(function(){btn.innerHTML='';var chk=document.createElement('i');chk.className='fas fa-check';btn.appendChild(chk);setTimeout(function(){btn.innerHTML='';var ic=document.createElement('i');ic.className='fas fa-copy';btn.appendChild(ic);},1500);});}else{var ta=document.createElement('textarea');ta.value=t;document.body.appendChild(ta);ta.select();try{document.execCommand('copy');btn.innerHTML='';var chk2=document.createElement('i');chk2.className='fas fa-check';btn.appendChild(chk2);setTimeout(function(){btn.innerHTML='';var ic2=document.createElement('i');ic2.className='fas fa-copy';btn.appendChild(ic2);},1500);}catch(e){}document.body.removeChild(ta);}});} })();</script>";
    exit(1);
});

register_shutdown_function(function() {
    $err = error_get_last();
    if (!$err) return;
    $msg = $err['message'] . " in " . $err['file'] . ':' . $err['line'];
    $jsmsg = json_encode($msg);
    echo '<script>document.addEventListener("DOMContentLoaded", function(){'
        . 'var main = document.getElementById("mainContent"); if (!main) { main = document.createElement("div"); main.id = "mainContent"; main.className = "main-content"; document.body.appendChild(main); }'
        . 'var errBox = document.createElement("div"); errBox.className = "card border-danger mb-3"; errBox.innerHTML = "<div class=\"card-body\">'
            . '<div style=\"display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;\">'
            . '<h5 class=\\\"card-title text-danger\\\" style=\\\"margin:0;\\\">An error occurred</h5>'
            . '<div><button class=\\\"btn btn-sm btn-outline-secondary copyErrorBtn\\\" title=\\\"Copy error\\\"><i class=\\\"fas fa-copy\\\"></i></button></div>'
            . '</div>'
            . '<pre class=\\\"error-pre\\\" style=\\\"white-space:pre-wrap;color:#a00;margin:0;\\\">' . $jsmsg . '</pre>'
            . '</div>";'
        . 'if (main.firstChild) main.insertBefore(errBox, main.firstChild); else main.appendChild(errBox);'
        . 'var btn = errBox.querySelector(".copyErrorBtn"); if (btn) { btn.addEventListener("click", function(){ var text = errBox.querySelector(".error-pre").textContent || ""; if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(function(){ btn.innerHTML = "<i class=\\\"fas fa-check\\\"></i>"; setTimeout(function(){ btn.innerHTML = "<i class=\\\"fas fa-copy\\\"></i>"; },1500); }); } else { var ta = document.createElement("textarea"); ta.value = text; document.body.appendChild(ta); ta.select(); try { document.execCommand("copy"); btn.innerHTML = "<i class=\\\"fas fa-check\\\"></i>"; setTimeout(function(){ btn.innerHTML = "<i class=\\\"fas fa-copy\\\"></i>"; },1500); } catch(e){} document.body.removeChild(ta); } }); }'
        . '});</script>';
});

// Include stream manager so generation respects currently selected stream
if (file_exists(__DIR__ . '/includes/stream_manager.php')) include_once __DIR__ . '/includes/stream_manager.php';
$streamManager = getStreamManager();
$current_stream_id = $streamManager->getCurrentStreamId();

// Set current semester from form, URL params, or use defaults
$current_semester = 2; // Default to semester 2

// Check if we have form data or URL parameters
if (isset($_POST['semester']) && !empty($_POST['semester'])) {
    $semester_input = $_POST['semester'];
    if ($semester_input === 'first' || $semester_input === '1') {
        $current_semester = 1;
    } elseif ($semester_input === 'second' || $semester_input === '2') {
        $current_semester = 2;
    }
}

// Also check URL parameters for when page is redirected after generation
if (isset($_GET['semester']) && !empty($_GET['semester'])) {
    $semester_param = $_GET['semester'];
    if ($semester_param === 'first' || $semester_param === '1') {
        $current_semester = 1;
    } elseif ($semester_param === 'second' || $semester_param === '2') {
        $current_semester = 2;
    }
}

$success_message = '';
$error_message = '';
$generation_results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'generate_lecture_timetable') {
        // Read semester from user input
        $semester = trim($_POST['semester'] ?? '');
        $stream_id = $current_stream_id; // Use currently active stream
        
        // Convert semester to integer for genetic algorithm
        $semester_int = 0;
        if ($semester === '1' || strtolower($semester) === 'first' || strtolower($semester) === 'semester 1') {
            $semester_int = 1;
        } elseif ($semester === '2' || strtolower($semester) === 'second' || strtolower($semester) === 'semester 2') {
            $semester_int = 2;
        }
        
        // Update current semester for display
        $current_semester = $semester_int;
        
        // GA parameters (with sensible defaults - hidden from user)
        $population_size = 100;
        $generations = 500;
        $mutation_rate = 0.1;
        $crossover_rate = 0.8;
        $max_runtime = 300;

        if ($semester === '' || $semester_int === 0) {
            $error_message = 'Please specify semester (1 or 2) before generating the timetable.';
        } else {
            try {
                // Clear existing timetable for this period
                clearExistingTimetable($conn, $semester_int, $stream_id);
                
                // Initialize genetic algorithm
                $gaOptions = [
                    'population_size' => $population_size,
                    'generations' => $generations,
                    'mutation_rate' => $mutation_rate,
                    'crossover_rate' => $crossover_rate,
                    'max_runtime' => $max_runtime,
                    'stream_id' => $stream_id,
                    'semester' => $semester_int
                ];
                
                $ga = new GeneticAlgorithm($conn, $gaOptions);
                
                // Validate data before generation
                $loader = new DBLoader($conn);
                $data = $loader->loadAll($gaOptions);
                $validation = $loader->validateDataForGeneration($data);
                
                if (!$validation['valid']) {
                    $error_message = 'Cannot generate timetable: ' . implode(', ', $validation['errors']);
                } else {
                    // Run genetic algorithm
                    $start_time = microtime(true);
                    $results = $ga->run();
                    $end_time = microtime(true);
                    
                    $generation_results = [
                        'runtime' => $end_time - $start_time,
                        'generations_completed' => $results['generations_completed'],
                        'best_solution' => $results['solution'],
                        'statistics' => $results['statistics']
                    ];
                    
                    // Convert solution to database format
                    $timetableEntries = $ga->convertToDatabaseFormat($results['solution']);
                    
                    // Insert into database
                    $inserted_count = insertTimetableEntries($conn, $timetableEntries);
                    
                    if ($inserted_count > 0) {
                        $msg = "Timetable generated successfully! $inserted_count entries created.";
                        if (function_exists('redirect_with_flash')) {
                            // Redirect with parameters so the page shows the correct data
                            $redirect_url = 'generate_timetable.php?semester=' . $semester_int;
                            redirect_with_flash($redirect_url, 'success', $msg);
                        } else {
                            $success_message = $msg;
                        }
                    } else {
                        $error_message = "Timetable generation completed but no entries were inserted.";
                    }
                }
                
            } catch (Exception $e) {
                $error_message = 'Timetable generation failed: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'generate_exams_timetable') {
        // Placeholder: exams timetable generation not implemented yet
        if (function_exists('redirect_with_flash')) {
            redirect_with_flash('generate_timetable.php', 'danger', 'Exams timetable generation is not implemented yet.');
        } else {
            $error_message = 'Exams timetable generation is not implemented yet.';
        }
    }
}

// Helper functions
function clearExistingTimetable($conn, $semester, $stream_id) {
    // Check if timetable table has the new schema
    $has_class_course = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'")->num_rows > 0;
    
    if ($has_class_course) {
        // New schema: clear via class_courses -> classes -> stream
        $sql = "DELETE t FROM timetable t 
                JOIN class_courses cc ON t.class_course_id = cc.id 
                JOIN classes c ON cc.class_id = c.id 
                WHERE t.semester = ? AND c.stream_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $semester, $stream_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Old schema: clear via class_id -> classes -> stream
        $sql = "DELETE t FROM timetable t 
                JOIN classes c ON t.class_id = c.id 
                WHERE t.semester = ? AND c.stream_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $semester, $stream_id);
        $stmt->execute();
        $stmt->close();
    }
}

function insertTimetableEntries($conn, $entries) {
    if (empty($entries)) {
        return 0;
    }
    
    $inserted_count = 0;
    
    foreach ($entries as $entry) {
        $sql = "INSERT INTO timetable (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, division_label, semester) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iiiiiss", 
                $entry['class_course_id'],
                $entry['lecturer_course_id'],
                $entry['day_id'],
                $entry['time_slot_id'],
                $entry['room_id'],
                $entry['division_label'],
                $entry['semester']
            );
            
            if ($stmt->execute()) {
                $inserted_count++;
            }
            $stmt->close();
        }
    }
    
    return $inserted_count;
}

/**
 * Format time to HH:MM format for display
 * @param string $time Time string in HH:MM:SS or HH:MM format
 * @return string Formatted time in HH:MM
 */
function formatTimeForDisplay($time) {
    if (empty($time)) {
        return '';
    }
    // Remove seconds if present and return HH:MM format
    return substr($time, 0, 5);
}

/**
 * Format time to HH:MM:SS format for database storage
 * @param string $time Time string in HH:MM or HH:MM:SS format
 * @return string Formatted time in HH:MM:SS
 */
function formatTimeForDatabase($time) {
    if (empty($time)) {
        return '';
    }
    // Ensure HH:MM:SS format
    if (strlen($time) === 5) {
        return $time . ':00';
    }
    return $time;
}

// Get statistics
$total_assignments = 0;
// Count assignments; respect stream when classes.stream_id exists
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
$has_stream_col = ($col && $col->num_rows > 0);
if ($has_stream_col && !empty($current_stream_id)) {
    // Count class_courses where the class belongs to the selected stream
    $sql = "SELECT COUNT(*) as count FROM class_courses cc JOIN classes c ON cc.class_id = c.id WHERE cc.is_active = 1 AND c.stream_id = " . intval($current_stream_id);
    $total_assignments = $conn->query($sql)->fetch_assoc()['count'];
} else {
    $total_assignments = $conn->query("SELECT COUNT(*) as count FROM class_courses WHERE is_active = 1")->fetch_assoc()['count'];
}
if ($col) $col->close();
$total_timetable_entries = 0;
// Respect stream: count timetable entries for the selected stream when possible
// Support both timetable schema variants: (1) timetable.class_course_id -> class_courses -> classes
// and (2) timetable.class_id -> classes
$total_timetable_entries = 0;
$tcol = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
$has_t_class_course = ($tcol && $tcol->num_rows > 0);
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
$has_stream_col = ($col && $col->num_rows > 0);

if ($has_stream_col && !empty($current_stream_id)) {
    if ($has_t_class_course) {
        // New schema: join via class_course_id -> class_courses -> classes
        $sql = "SELECT COUNT(*) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN classes c ON cc.class_id = c.id WHERE c.stream_id = " . intval($current_stream_id);
    } else {
        // Old schema: timetable stores class_id directly
        $sql = "SELECT COUNT(*) as count FROM timetable t JOIN classes c ON t.class_id = c.id WHERE c.stream_id = " . intval($current_stream_id);
    }
    $res = $conn->query($sql);
    $total_timetable_entries = $res ? $res->fetch_assoc()['count'] : 0;
} else {
    // Fallback to global count
    $total_timetable_entries = $conn->query("SELECT COUNT(*) as count FROM timetable")->fetch_assoc()['count'];
}

if ($tcol) $tcol->close();
if ($col) $col->close();
$total_classes = 0;
// Respect selected stream when counting classes if the classes table has a stream_id column
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
$has_stream_col = ($col && $col->num_rows > 0);
if ($has_stream_col && !empty($current_stream_id)) {
    $total_classes = $conn->query("SELECT COUNT(*) as count FROM classes WHERE is_active = 1 AND stream_id = " . intval($current_stream_id))->fetch_assoc()['count'];
} else {
    $total_classes = $conn->query("SELECT COUNT(*) as count FROM classes WHERE is_active = 1")->fetch_assoc()['count'];
}
if ($col) $col->close();
$total_courses = $conn->query("SELECT COUNT(*) as count FROM courses WHERE is_active = 1")->fetch_assoc()['count'];

// Get available streams
$streams = $conn->query("SELECT id, name, code FROM streams WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-calendar-alt me-2"></i>Generate Timetable</h4>
        </div>

        <div class="m-3">
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
        </div>

        <div class="row m-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Timetable Generation</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">This will generate a new timetable based on current class-course assignments. Any existing timetable entries will be cleared.</p>

                        <form method="POST">
                            <input type="hidden" name="action" value="generate_lecture_timetable">
                            
                            <div class="d-flex flex-column gap-3">
                                <div class="d-flex gap-2 align-items-center">
                                    <select name="semester" class="form-select form-select-sm" required style="max-width:140px">
                                        <option value="">Semester</option>
                                        <option value="first" <?php echo $current_semester == 1 ? 'selected' : ''; ?>>First</option>
                                        <option value="second" <?php echo $current_semester == 2 ? 'selected' : ''; ?>>Second</option>
                                    </select>
                                </div>
                                <div class="d-flex gap-2 align-items-center">
                                    <button type="submit" class="btn btn-primary btn-lg" id="generate-btn">
                                        <i class="fas fa-magic me-2"></i>Generate Lecture Timetable
                                    </button>
                                    <button type="button" class="btn btn-outline-primary btn-lg" onclick="generateExamsTimetable()">
                                        <i class="fas fa-file-alt me-2"></i>Generate Exams Timetable
                                    </button>
                                    <button type="button" class="btn btn-success btn-lg" onclick="saveToSavedTimetables()" id="save-timetable-btn" style="display: none;">
                                        <i class="fas fa-save me-2"></i>Save Timetable
                                    </button>
                                </div>
                            </div>
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
                            <a href="class_courses.php" class="btn btn-outline-primary"><i class="fas fa-link me-2"></i>Manage Assignments</a>
                            <a href="view_timetable.php" class="btn btn-success"><i class="fas fa-eye me-2"></i>View Timetable</a>
                            <a href="export_timetable.php" class="btn btn-outline-info"><i class="fas fa-download me-2"></i>Export Timetable</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($generation_results): ?>
        <div class="row m-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Generation Results</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Performance Metrics</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Runtime:</strong> <?php echo round($generation_results['runtime'], 2); ?> seconds</li>
                                    <li><strong>Generations:</strong> <?php echo $generation_results['generations_completed']; ?></li>
                                    <li><strong>Best Fitness:</strong> <?php echo round($generation_results['best_solution']['fitness']['total_score'], 2); ?></li>
                                    <li><strong>Hard Violations:</strong> <?php echo array_sum(array_map('count', $generation_results['best_solution']['fitness']['hard_violations'])); ?></li>
                                    <li><strong>Soft Violations:</strong> <?php echo array_sum(array_map('count', $generation_results['best_solution']['fitness']['soft_violations'])); ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Solution Quality</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Feasible:</strong> 
                                        <span class="badge <?php echo $generation_results['best_solution']['fitness']['is_feasible'] ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo $generation_results['best_solution']['fitness']['is_feasible'] ? 'Yes' : 'No'; ?>
                                        </span>
                                    </li>
                                    <li><strong>Quality Rating:</strong> 
                                        <?php 
                                        $fitnessEvaluator = new FitnessEvaluator([]);
                                        $qualityRating = $fitnessEvaluator->getQualityRating($generation_results['best_solution']['individual']);
                                        $qualityClass = $qualityRating === 'Excellent' ? 'success' : 
                                                      ($qualityRating === 'Good' ? 'info' : 
                                                      ($qualityRating === 'Fair' ? 'warning' : 'danger'));
                                        ?>
                                        <span class="badge bg-<?php echo $qualityClass; ?>"><?php echo $qualityRating; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Check if timetable has been generated
        $has_timetable = $total_timetable_entries > 0;
        
        // Get readiness conditions for pre-generation
        $total_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE is_active = 1")->fetch_assoc()['count'];
        
        // Get days count - consider stream-specific days if available
        $total_days = 0;
        if (!empty($current_stream_id)) {
            // Check if stream has specific active days
            $stream_days_check = $conn->query("SELECT active_days FROM streams WHERE id = " . intval($current_stream_id));
            if ($stream_days_row = $stream_days_check->fetch_assoc()) {
                $active_days_json = $stream_days_row['active_days'];
                if (!empty($active_days_json)) {
                    $active_days_array = json_decode($active_days_json, true);
                    if (is_array($active_days_array) && count($active_days_array) > 0) {
                        // Count stream-specific active days
                        $stream_days_sql = "SELECT COUNT(*) as count FROM days WHERE is_active = 1 AND name IN ('" . implode("','", $active_days_array) . "')";
                        $total_days = $conn->query($stream_days_sql)->fetch_assoc()['count'];
                    }
                }
            }
        }
        if ($total_days == 0) {
            // Fallback to all active days
            $total_days = $conn->query("SELECT COUNT(*) as count FROM days WHERE is_active = 1")->fetch_assoc()['count'];
        }
        
        // Get stream-specific time slots count (with error handling for missing table)
        $stream_time_slots_count = 0;
        $schema_error = null;
        
        try {
            if (!empty($current_stream_id)) {
                // First try to get stream-specific time slots from stream_time_slots mapping
                $sts_result = $conn->query("SELECT COUNT(*) as count FROM stream_time_slots WHERE stream_id = " . intval($current_stream_id) . " AND is_active = 1");
                $stream_time_slots_count = $sts_result ? $sts_result->fetch_assoc()['count'] : 0;
            }
            
            // If no mapped time slots found, calculate from stream's period settings
            if ($stream_time_slots_count == 0 && !empty($current_stream_id)) {
                // Fetch stream's period settings from database
                $stream_period_sql = "SELECT period_start, period_end, break_start, break_end FROM streams WHERE id = ? AND is_active = 1";
                $stream_period_stmt = $conn->prepare($stream_period_sql);
                $stream_period_stmt->bind_param('i', $current_stream_id);
                $stream_period_stmt->execute();
                $stream_period_result = $stream_period_stmt->get_result();
                
                if ($stream_period_row = $stream_period_result->fetch_assoc()) {
                    $period_start = $stream_period_row['period_start'];
                    $period_end = $stream_period_row['period_end'];
                    $break_start = $stream_period_row['break_start'];
                    $break_end = $stream_period_row['break_end'];
                    
                    // Calculate time slots based on stream's period settings
                    if (!empty($period_start) && !empty($period_end)) {
                        $start_time = new DateTime($period_start);
                        $end_time = new DateTime($period_end);
                        $current_time = clone $start_time;
                        $slot_count = 0;
                        
                        while ($current_time < $end_time) {
                            $slot_start = $current_time->format('H:i');
                            $current_time->add(new DateInterval('PT1H')); // Add 1 hour
                            $slot_end = $current_time->format('H:i');
                            
                            // Don't count a slot that goes beyond the period end
                            if ($current_time > $end_time) {
                                $slot_end = $end_time->format('H:i');
                            }
                            
                            // Check if this slot overlaps with break time
                            $is_break = false;
                            
                            if (!empty($break_start) && !empty($break_end)) {
                                $break_start_time = new DateTime($break_start);
                                $break_end_time = new DateTime($break_end);
                                $slot_start_time = new DateTime($slot_start);
                                $slot_end_time = new DateTime($slot_end);
                                
                                // Check if slot overlaps with break period
                                if (($slot_start_time < $break_end_time) && ($slot_end_time > $break_start_time)) {
                                    $is_break = true;
                                }
                            }
                            
                            // Only count non-break slots for scheduling
                            if (!$is_break) {
                                $slot_count++;
                            }
                            
                            // Safety check to prevent infinite loops
                            if ($slot_count > 24) {
                                break;
                            }
                        }
                        
                        $stream_time_slots_count = $slot_count;
                    }
                }
                $stream_period_stmt->close();
            }
            
            // Fallback to default period range (07:00 to 20:00) - 13 periods if no stream-specific data
            if ($stream_time_slots_count == 0) {
                $stream_time_slots_count = 13;
            }
        } catch (Exception $e) {
            $schema_error = "Database schema issue: " . $e->getMessage();
            // Fallback to default period count
            $stream_time_slots_count = 13;
        }
        
        // Check lecturer-course mappings
        $total_lecturer_courses = $conn->query("SELECT COUNT(*) as count FROM lecturer_courses WHERE is_active = 1")->fetch_assoc()['count'];
        
        // Readiness checks
        $readiness_issues = [];
        if ($schema_error) $readiness_issues[] = $schema_error;
        if ($total_assignments == 0) $readiness_issues[] = "No class-course assignments";
        if ($stream_time_slots_count == 0) $readiness_issues[] = "No time slots available";
        if ($total_rooms == 0) $readiness_issues[] = "No active rooms";
        if ($total_days == 0) $readiness_issues[] = "No active days";
        if ($total_lecturer_courses == 0) $readiness_issues[] = "No lecturer-course assignments";
        
        $is_ready = count($readiness_issues) == 0;
        ?>

        <!-- Pre-Generation Conditions (show when no timetable exists or has issues) -->
        <?php if (!$has_timetable || !$is_ready): ?>
        <div class="row m-3">
            <div class="col-12">
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>Pre-Generation Conditions
                            <?php if ($is_ready): ?>
                                <span class="badge bg-success ms-2">Ready</span>
                            <?php else: ?>
                                <span class="badge bg-warning ms-2">Issues Found</span>
                            <?php endif; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 d-flex">
                                <div class="card theme-card <?php echo $total_assignments > 0 ? 'bg-theme-green text-white' : 'bg-theme-secondary text-dark'; ?> text-center mb-2 w-100 h-100">
                                    <div class="card-body">
                                        <div class="stat-number"><?php echo $total_assignments; ?></div>
                                        <div>
                                            Assignments
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Class-course pairings that need scheduling"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex">
                                <div class="card theme-card <?php echo $stream_time_slots_count > 0 ? 'bg-theme-green text-white' : 'bg-theme-secondary text-dark'; ?> text-center mb-2 w-100 h-100">
                                    <div class="card-body">
                                        <div class="stat-number"><?php echo $stream_time_slots_count; ?></div>
                                        <div>
                                            Time Slots
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Available time periods (calculated from stream's period settings in database)"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex">
                                <div class="card theme-card <?php echo $total_rooms > 0 ? 'bg-theme-green text-white' : 'bg-theme-secondary text-dark'; ?> text-center mb-2 w-100 h-100">
                                    <div class="card-body">
                                        <div class="stat-number"><?php echo $total_rooms; ?></div>
                                        <div>
                                            Rooms
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Active rooms available for scheduling"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex">
                                <div class="card theme-card <?php echo $total_days > 0 ? 'bg-theme-green text-white' : 'bg-theme-secondary text-dark'; ?> text-center mb-2 w-100 h-100">
                                    <div class="card-body">
                                        <div class="stat-number"><?php echo $total_days; ?></div>
                                        <div>
                                            Days
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Active days for current stream (from stream's active_days setting)"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex">
                                <div class="card theme-card <?php echo $total_lecturer_courses > 0 ? 'bg-theme-green text-white' : 'bg-theme-secondary text-dark'; ?> text-center mb-2 w-100 h-100">
                                    <div class="card-body">
                                        <div class="stat-number"><?php echo $total_lecturer_courses; ?></div>
                                        <div>
                                            Lecturers
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Lecturer-course assignments available"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex">
                                <div class="card theme-card <?php echo $is_ready ? 'bg-theme-green text-white' : 'bg-theme-warning text-dark'; ?> text-center mb-2 w-100 h-100">
                                    <div class="card-body">
                                        <div class="stat-number">
                                            <?php if ($is_ready): ?>
                                                <i class="fas fa-check"></i>
                                            <?php else: ?>
                                                <i class="fas fa-exclamation-triangle"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            Status
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Overall readiness for generation"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!$is_ready): ?>
                        <div class="mt-3">
                            <div class="alert alert-warning">
                                <h6 class="mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Issues to Resolve:</h6>
                                <ul class="mb-0">
                                    <?php foreach ($readiness_issues as $issue): ?>
                                        <li><?php echo htmlspecialchars($issue); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        // Get stream-specific data for template (moved outside condition so it's always available)
        $template_days = [];
        $template_time_slots = [];
        $template_rooms = [];
        
        // Get days for this stream
        if (!empty($current_stream_id)) {
            // Get stream's active days preference
            $stream_days_sql = "SELECT active_days FROM streams WHERE id = ?";
            $stream_active_days_stmt = $conn->prepare($stream_days_sql);
            $stream_active_days_stmt->bind_param('i', $current_stream_id);
            $stream_active_days_stmt->execute();
            $stream_result = $stream_active_days_stmt->get_result();
            
            if ($stream_row = $stream_result->fetch_assoc()) {
                $active_days_json = $stream_row['active_days'];
                if (!empty($active_days_json)) {
                    $active_days_array = json_decode($active_days_json, true);
                    if (is_array($active_days_array) && count($active_days_array) > 0) {
                        // Get stream-specific days
                        $stream_days_sql = "SELECT id, name FROM days WHERE is_active = 1 AND name IN ('" . implode("','", $active_days_array) . "') ORDER BY id";
                        $template_days_result = $conn->query($stream_days_sql);
                        while ($day = $template_days_result->fetch_assoc()) {
                            $template_days[] = $day;
                        }
                    }
                }
            }
            $stream_active_days_stmt->close();
        }
        
        // Fallback to all active days if no stream-specific days
        if (empty($template_days)) {
            $all_days_sql = "SELECT id, name FROM days WHERE is_active = 1 ORDER BY id";
            $all_days_result = $conn->query($all_days_sql);
            while ($day = $all_days_result->fetch_assoc()) {
                $template_days[] = $day;
            }
        }
        
        // Get time slots for this stream - stream-aware periods from database
        if (!empty($current_stream_id)) {
            // First try to get stream-specific time slots from stream_time_slots mapping
            $sts_exists = $conn->query("SHOW TABLES LIKE 'stream_time_slots'");
            if ($sts_exists && $sts_exists->num_rows > 0) {
                $ts_rs = $conn->query("SELECT ts.id, ts.start_time, ts.end_time FROM stream_time_slots sts JOIN time_slots ts ON ts.id = sts.time_slot_id WHERE sts.stream_id = " . intval($current_stream_id) . " AND sts.is_active = 1 ORDER BY ts.start_time");
                if ($ts_rs && $ts_rs->num_rows > 0) {
                    while ($slot = $ts_rs->fetch_assoc()) {
                        // Add is_break and break_type fields to ensure consistency
                        $slot['is_break'] = false;
                        $slot['break_type'] = '';
                        $template_time_slots[] = $slot;
                    }
                }
            }
            if ($sts_exists) $sts_exists->close();
            
            // If no mapped time slots found, generate them from stream's period settings
            if (empty($template_time_slots)) {
                // Fetch stream's period settings from database
                $stream_period_sql = "SELECT period_start, period_end, break_start, break_end FROM streams WHERE id = ? AND is_active = 1";
                $stream_period_stmt = $conn->prepare($stream_period_sql);
                $stream_period_stmt->bind_param('i', $current_stream_id);
                $stream_period_stmt->execute();
                $stream_period_result = $stream_period_stmt->get_result();
                
                if ($stream_period_row = $stream_period_result->fetch_assoc()) {
                    $period_start = $stream_period_row['period_start'];
                    $period_end = $stream_period_row['period_end'];
                    $break_start = $stream_period_row['break_start'];
                    $break_end = $stream_period_row['break_end'];
                    
                    // Generate time slots based on stream's period settings
                    if (!empty($period_start) && !empty($period_end)) {
                        $start_time = new DateTime($period_start);
                        $end_time = new DateTime($period_end);
                        $current_time = clone $start_time;
                        $slot_id = 1;
                        
                        while ($current_time < $end_time) {
                            $slot_start = $current_time->format('H:i');
                            $current_time->add(new DateInterval('PT1H')); // Add 1 hour
                            $slot_end = $current_time->format('H:i');
                            
                            // Don't create a slot that goes beyond the period end
                            if ($current_time > $end_time) {
                                $slot_end = $end_time->format('H:i');
                            }
                            
                            // Check if this slot overlaps with break time
                            $is_break = false;
                            $break_type = '';
                            
                            if (!empty($break_start) && !empty($break_end)) {
                                $break_start_time = new DateTime($break_start);
                                $break_end_time = new DateTime($break_end);
                                $slot_start_time = new DateTime($slot_start);
                                $slot_end_time = new DateTime($slot_end);
                                
                                // Check if slot overlaps with break period
                                if (($slot_start_time < $break_end_time) && ($slot_end_time > $break_start_time)) {
                                    $is_break = true;
                                    $break_type = 'break';
                                }
                            }
                            
                            $template_time_slots[] = [
                                'id' => $slot_id,
                                'start_time' => $slot_start,
                                'end_time' => $slot_end,
                                'is_break' => $is_break,
                                'break_type' => $break_type
                            ];
                            
                            $slot_id++;
                            
                            // Safety check to prevent infinite loops
                            if ($slot_id > 24) {
                                break;
                            }
                        }
                    }
                }
                $stream_period_stmt->close();
            }
        }
        
        // Fallback to default period range (07:00 to 20:00) if no stream-specific slots or period settings
        if (empty($template_time_slots)) {
            // Generate default hourly periods from 07:00 to 20:00 with break periods
            $start_hour = 7;
            $end_hour = 20;
            
            for ($hour = $start_hour; $hour < $end_hour; $hour++) {
                $start_time = sprintf('%02d:00', $hour);
                $end_time = sprintf('%02d:00', $hour + 1);
                
                // Define break periods (lunch break and tea break)
                $is_break = false;
                $break_type = '';
                
                if ($hour == 12) {
                    $is_break = true;
                    $break_type = 'break';
                    $end_time = '13:00'; // Break is 12:00-13:00
                } elseif ($hour == 15) {
                    $is_break = true;
                    $break_type = 'break';
                    $end_time = '16:00'; // Break is 15:00-16:00
                }
                
                $template_time_slots[] = [
                    'id' => $hour,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'is_break' => $is_break,
                    'break_type' => $break_type
                ];
            }
        }
        
        // Get all active rooms (rooms are global)
        $rooms_sql = "SELECT id, name, capacity FROM rooms WHERE is_active = 1 ORDER BY capacity";
        $rooms_result = $conn->query($rooms_sql);
        while ($room = $rooms_result->fetch_assoc()) {
            $template_rooms[] = $room;
        }

        // Fetch all available rooms for the edit modal
        $all_rooms = [];
        $rooms_query = "SELECT id, name, capacity, room_type FROM rooms WHERE is_active = 1 ORDER BY name";
        $rooms_result = $conn->query($rooms_query);
        if ($rooms_result) {
            while ($room = $rooms_result->fetch_assoc()) {
                $all_rooms[] = $room;
            }
        }

        // Fetch actual timetable data for the current stream and semester
        $timetable_data = [];
        $course_spans = []; // Track which cells should be spanned
        
        // Debug: Check what values we're using
        $debug_info = "Debug: stream_id=$current_stream_id, semester=$current_semester";
        
        if (!empty($current_stream_id) && !empty($current_semester)) {
            $timetable_query = "
                SELECT 
                    t.id,
                    t.day_id,
                    t.time_slot_id,
                    t.room_id,
                    t.class_course_id,
                    t.lecturer_course_id,
                    t.division_label,
                    t.semester,
                    t.academic_year,
                    d.name as day_name,
                    d.id as day_id,
                    ts.start_time,
                    ts.end_time,
                    ts.id as time_slot_id,
                    r.name as room_name,
                    r.id as room_id,
                    r.capacity,
                    c.name as class_name,
                    c.id as class_id,
                    co.code as course_code,
                    co.name as course_name,
                    co.id as course_id,
                    co.hours_per_week,
                    l.name as lecturer_name,
                    l.id as lecturer_id,
                    s.name as stream_name,
                    s.id as stream_id
                FROM timetable t
                JOIN class_courses cc ON t.class_course_id = cc.id
                JOIN classes c ON cc.class_id = c.id
                JOIN courses co ON cc.course_id = co.id
                JOIN streams s ON c.stream_id = s.id
                JOIN days d ON t.day_id = d.id
                JOIN time_slots ts ON t.time_slot_id = ts.id
                JOIN rooms r ON t.room_id = r.id
                LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                LEFT JOIN lecturers l ON lc.lecturer_id = l.id
                WHERE c.stream_id = ? 
                AND t.semester = ?
                ORDER BY d.id, ts.start_time, r.name
            ";

            $timetable_stmt = $conn->prepare($timetable_query);
            $timetable_stmt->bind_param("ii", $current_stream_id, $current_semester);
            $timetable_stmt->execute();
            $timetable_result = $timetable_stmt->get_result();

            // Group entries by class_course_id to handle multi-hour courses
            $grouped_entries = [];
            while ($row = $timetable_result->fetch_assoc()) {
                $class_course_id = $row['class_course_id'];
                if (!isset($grouped_entries[$class_course_id])) {
                    $grouped_entries[$class_course_id] = [];
                }
                $grouped_entries[$class_course_id][] = $row;
            }

            // Process grouped entries to create proper spans
            foreach ($grouped_entries as $class_course_id => $entries) {
                if (empty($entries)) continue;
                
                // Sort entries by time_slot_id to ensure proper order
                usort($entries, function($a, $b) {
                    return $a['time_slot_id'] - $b['time_slot_id'];
                });
                
                $first_entry = $entries[0];
                $course_duration = $first_entry['hours_per_week'] ?? 1;
                $day_id = $first_entry['day_id'];
                $room_id = $first_entry['room_id'];
                $start_time_slot_id = $first_entry['time_slot_id'];
                
                // Store the main entry (first slot)
                $timetable_data[$day_id][$start_time_slot_id][$room_id] = [
                    'id' => $first_entry['id'],
                    'class_name' => $first_entry['class_name'],
                    'course_code' => $first_entry['course_code'],
                    'course_name' => $first_entry['course_name'],
                    'lecturer_name' => $first_entry['lecturer_name'],
                    'division_label' => $first_entry['division_label'],
                    'hours_per_week' => $course_duration,
                    'day_id' => $day_id,
                    'time_slot_id' => $start_time_slot_id,
                    'room_id' => $room_id,
                    'is_spanned' => false, // This is the main cell
                    'span_count' => $course_duration
                ];
                
                // Mark subsequent cells as spanned (they will be hidden)
                for ($i = 1; $i < $course_duration; $i++) {
                    $next_time_slot_id = $start_time_slot_id + $i;
                    if (isset($timetable_data[$day_id][$next_time_slot_id][$room_id])) {
                        // If there's already an entry here, mark it as spanned
                        $timetable_data[$day_id][$next_time_slot_id][$room_id]['is_spanned'] = true;
                    } else {
                        // Create a placeholder entry to mark as spanned
                        $timetable_data[$day_id][$next_time_slot_id][$room_id] = [
                            'is_spanned' => true,
                            'span_count' => 0
                        ];
                    }
                }
            }
            $timetable_stmt->close();
        }
        ?>

        <!-- Timetable Template Preview -->
        <?php if (!empty($template_days) && !empty($template_time_slots) && !empty($template_rooms)): ?>
        <div class="row m-3">
            <div class="col-12">
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-table me-2"></i>Timetable Preview
                            <?php if (!empty($timetable_data)): ?>
                                <span class="badge bg-success ms-2">Generated Data</span>
                                <span class="badge bg-primary ms-1"><?php 
                                    $total_entries = 0;
                                    if (is_array($timetable_data)) {
                                        foreach ($timetable_data as $day_data) {
                                            if (is_array($day_data)) {
                                                foreach ($day_data as $time_data) {
                                                    if (is_array($time_data)) {
                                                        $total_entries += count($time_data);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    echo $total_entries;
                                ?> Entries</span>
                            <?php else: ?>
                                <span class="badge bg-info ms-2">Skeleton Structure</span>
                            <?php endif; ?>
                        </h6>
                    </div>
                    <div class="card-body">
                        
                        <div class="mb-3">
                            <p class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php if (!empty($timetable_data)): ?>
                                    This timetable shows the actual generated data for the selected stream and semester. 
                                    Blue cells contain scheduled courses with class, course code, and lecturer details.
                                    <strong>Click on any course block to edit the day, time slot, or room assignment.</strong>
                                    Course duration is fixed and cannot be changed. Courses will span multiple periods based on their duration (e.g., 3-hour courses span 3 periods).
                                <?php else: ?>
                                    This template shows the structure that will be used for timetable generation. 
                                    The cells will be filled with class-course assignments when you generate the timetable.
                                <?php endif; ?>
                            </p>
                            <!-- Debug information -->
                            <div class="alert alert-info" style="font-size: 0.8em;">
                                <strong>Debug Info:</strong> <?php echo htmlspecialchars($debug_info); ?><br>
                                <strong>Data Count:</strong> <?php echo is_array($timetable_data) ? count($timetable_data) : 0; ?> days with data
                            </div>
                        </div>
                        
                        <?php if (!empty($template_days) && !empty($template_time_slots) && !empty($template_rooms)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm timetable-template">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 150px;">Day / Room</th>
                                        <?php 
                                        $current_break_span = 0;
                                        foreach ($template_time_slots as $index => $time_slot): 
                                            // Ensure is_break key exists
                                            if (!isset($time_slot['is_break'])) {
                                                $time_slot['is_break'] = false;
                                                $time_slot['break_type'] = '';
                                            }
                                            if ($time_slot['is_break']) {
                                                if ($current_break_span == 0) {
                                                    // Start of break period
                                                    $break_duration = 0;
                                                    // Count how many periods this break spans
                                                    for ($i = $index; $i < count($template_time_slots); $i++) {
                                                        // Ensure is_break key exists for this slot
                                                        if (!isset($template_time_slots[$i]['is_break'])) {
                                                            $template_time_slots[$i]['is_break'] = false;
                                                            $template_time_slots[$i]['break_type'] = '';
                                                        }
                                                        if ($template_time_slots[$i]['is_break'] && $template_time_slots[$i]['break_type'] == $time_slot['break_type']) {
                                                            $break_duration++;
                                                        } else {
                                                            break;
                                                        }
                                                    }
                                                    $current_break_span = $break_duration;
                                                    ?>
                                                    <th class="break-period-header text-center" colspan="<?php echo $break_duration; ?>" style="min-width: <?php echo $break_duration * 100; ?>px;">
                                                        <div class="break-info">
                                                            <span class="break-label">Break</span>
                                                            <div class="break-time">
                                                                <?php echo htmlspecialchars(formatTimeForDisplay($time_slot['start_time'])); ?> - <?php echo htmlspecialchars(formatTimeForDisplay($time_slot['end_time'])); ?>
                                                            </div>
                                                        </div>
                                                    </th>
                                                    <?php
                                                } else {
                                                    // Skip this period as it's part of the break
                                                    $current_break_span--;
                                                }
                                            } else {
                                                ?>
                                                <th class="time-period-header text-center" style="min-width: 100px;">
                                                    <div class="period-time">
                                                        <?php echo htmlspecialchars(formatTimeForDisplay($time_slot['start_time'])); ?>
                                                    </div>
                                                </th>
                                                <?php
                                            }
                                        endforeach; 
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($template_days as $day): ?>
                                        <!-- Day Header Row -->
                                        <tr class="day-header-row">
                                            <td colspan="<?php echo count($template_time_slots) + 1; ?>" class="day-header">
                                                <div class="day-name">
                                                    <i class="fas fa-calendar-day me-2"></i>
                                                    <?php echo htmlspecialchars($day['name']); ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Room Rows for this Day -->
                                        <?php foreach ($template_rooms as $room): ?>
                                            <tr class="room-row">
                                                <td class="room-name-cell">
                                                    <div class="room-info">
                                                        <div class="room-name"><?php echo htmlspecialchars($room['name']); ?></div>
                                                        <small class="text-muted">(<?php echo $room['capacity']; ?> seats)</small>
                                                    </div>
                                                </td>
                                                <?php 
                                                $current_break_span = 0;
                                                foreach ($template_time_slots as $index => $time_slot): 
                                                    // Ensure is_break key exists
                                                    if (!isset($time_slot['is_break'])) {
                                                        $time_slot['is_break'] = false;
                                                        $time_slot['break_type'] = '';
                                                    }
                                                    if ($time_slot['is_break']) {
                                                        if ($current_break_span == 0) {
                                                            // Start of break period
                                                            $break_duration = 0;
                                                            // Count how many periods this break spans
                                                            for ($i = $index; $i < count($template_time_slots); $i++) {
                                                                // Ensure is_break key exists for this slot
                                                                if (!isset($template_time_slots[$i]['is_break'])) {
                                                                    $template_time_slots[$i]['is_break'] = false;
                                                                    $template_time_slots[$i]['break_type'] = '';
                                                                }
                                                                if ($template_time_slots[$i]['is_break'] && $template_time_slots[$i]['break_type'] == $time_slot['break_type']) {
                                                                    $break_duration++;
                                                                } else {
                                                                    break;
                                                                }
                                                            }
                                                            $current_break_span = $break_duration;
                                                            ?>
                                                            <td class="break-cell" colspan="<?php echo $break_duration; ?>" data-break-type="<?php echo $time_slot['break_type']; ?>">
                                                                <div class="break-placeholder">
                                                                    <small class="d-block text-muted">Break</small>
                                                                </div>
                                                            </td>
                                                            <?php
                                                        } else {
                                                            // Skip this period as it's part of the break
                                                            $current_break_span--;
                                                        }
                                                    } else {
                                                        ?>
                                                        <?php 
                                                        $day_id = $day['id'];
                                                        $time_slot_id = $time_slot['id'];
                                                        $room_id = $room['id'];
                                                        
                                                        // Check if this cell should be spanned (hidden)
                                                        $is_spanned = false;
                                                        $span_count = 1;
                                                        $entry = null;
                                                        
                                                        if (isset($timetable_data[$day_id][$time_slot_id][$room_id])) {
                                                            $entry = $timetable_data[$day_id][$time_slot_id][$room_id];
                                                            $is_spanned = $entry['is_spanned'] ?? false;
                                                            $span_count = $entry['span_count'] ?? 1;
                                                        }
                                                        
                                                        // Skip rendering if this cell is spanned (will be covered by colspan)
                                                        if ($is_spanned) {
                                                            continue;
                                                        }
                                                        ?>
                                                        <td class="template-cell" 
                                                            data-period="<?php echo htmlspecialchars(formatTimeForDisplay($time_slot['start_time'])); ?>" 
                                                            data-room="<?php echo htmlspecialchars($room['name']); ?>" 
                                                            data-day="<?php echo htmlspecialchars($day['name']); ?>"
                                                            <?php if ($span_count > 1): ?>colspan="<?php echo $span_count; ?>"<?php endif; ?>>
                                                            <?php 
                                                            if ($entry) {
                                                                ?>
                                                                <div class="course-block editable-course" 
                                                                     style="background-color: #e3f2fd; border: 2px solid #2196f3; border-radius: 4px; padding: 4px; margin: 2px; height: 100%; display: flex; flex-direction: column; justify-content: center; overflow: hidden; cursor: pointer; min-width: <?php echo $span_count * 100; ?>px;"
                                                                     data-entry-id="<?php echo $entry['id']; ?>"
                                                                     data-day-id="<?php echo $entry['day_id']; ?>"
                                                                     data-time-slot-id="<?php echo $entry['time_slot_id']; ?>"
                                                                     data-room-id="<?php echo $entry['room_id']; ?>"
                                                                     data-course-code="<?php echo htmlspecialchars($entry['course_code']); ?>"
                                                                     data-class-name="<?php echo htmlspecialchars($entry['class_name']); ?>"
                                                                     data-lecturer-name="<?php echo htmlspecialchars($entry['lecturer_name']); ?>"
                                                                     data-hours="<?php echo $entry['hours_per_week']; ?>"
                                                                     onclick="editTimetableCell(this)">
                                                                    <div class="fw-bold text-primary" style="font-size: 0.8em;">
                                                                        <?php echo htmlspecialchars($entry['course_code']); ?>
                                                                        <span class="badge bg-warning ms-1" style="font-size: 0.6em;"><?php echo $entry['hours_per_week']; ?>h</span>
                                                                        <?php if ($span_count > 1): ?>
                                                                            <span class="badge bg-info ms-1" style="font-size: 0.6em;">Spans <?php echo $span_count; ?> periods</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div style="font-size: 0.7em;">
                                                                        <strong>Class:</strong> <?php 
                                                                            $display_class_name = htmlspecialchars($entry['class_name']);
                                                                            if (!empty($entry['division_label'])) {
                                                                                $display_class_name .= ' ' . htmlspecialchars($entry['division_label']);
                                                                            }
                                                                            echo $display_class_name;
                                                                        ?>
                                                                    </div>
                                                                    <?php if ($entry['lecturer_name']): ?>
                                                                        <div style="font-size: 0.7em;">
                                                                            <strong>Lecturer:</strong> <?php echo htmlspecialchars($entry['lecturer_name']); ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <?php
                                                            } else {
                                                                ?>
                                                                <div class="template-placeholder">
                                                                    <i class="fas fa-plus-circle text-muted"></i>
                                                                    <small class="d-block text-muted">Empty</small>
                                                                </div>
                                                                <?php
                                                            }
                                                            ?>
                                                        </td>
                                                        <?php
                                                    }
                                                endforeach; 
                                                ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Sample Course Blocks (for demonstration) -->
                        <div class="mt-4">
                            <h6 class="mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                Sample Course Blocks (for demonstration)
                            </h6>
                            <div class="sample-blocks">
                                <div class="sample-block sample-1h" style="width: 100px; height: 60px; background-color: #28a745; color: white; display: inline-flex; align-items: center; justify-content: center; margin: 5px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                                    1h Course
                                </div>
                                <div class="sample-block sample-2h" style="width: 200px; height: 60px; background-color: #007bff; color: white; display: inline-flex; align-items: center; justify-content: center; margin: 5px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                                    2h Course
                                </div>
                                <div class="sample-block sample-3h" style="width: 300px; height: 60px; background-color: #dc3545; color: white; display: inline-flex; align-items: center; justify-content: center; margin: 5px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                                    3h Course
                                </div>
                                <div class="sample-block sample-4h" style="width: 400px; height: 60px; background-color: #6f42c1; color: white; display: inline-flex; align-items: center; justify-content: center; margin: 5px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                                    4h Course
                                </div>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-lightbulb me-1"></i>
                                <?php 
                                // Display stream-specific period information
                                if (!empty($current_stream_id)) {
                                    $stream_period_info_sql = "SELECT period_start, period_end, break_start, break_end FROM streams WHERE id = ? AND is_active = 1";
                                    $stream_period_info_stmt = $conn->prepare($stream_period_info_sql);
                                    $stream_period_info_stmt->bind_param('i', $current_stream_id);
                                    $stream_period_info_stmt->execute();
                                    $stream_period_info_result = $stream_period_info_stmt->get_result();
                                    
                                    if ($stream_period_info_row = $stream_period_info_result->fetch_assoc()) {
                                        $period_start = $stream_period_info_row['period_start'];
                                        $period_end = $stream_period_info_row['period_end'];
                                        $break_start = $stream_period_info_row['break_start'];
                                        $break_end = $stream_period_info_row['break_end'];
                                        
                                        if (!empty($period_start) && !empty($period_end)) {
                                            echo "Stream period range: " . htmlspecialchars(formatTimeForDisplay($period_start)) . " - " . htmlspecialchars(formatTimeForDisplay($period_end));
                                            if (!empty($break_start) && !empty($break_end)) {
                                                echo ". Break period: " . htmlspecialchars(formatTimeForDisplay($break_start)) . " - " . htmlspecialchars(formatTimeForDisplay($break_end));
                                            }
                                            echo ". ";
                                        } else {
                                            echo "Default period range: 07:00 - 20:00 (13 hours). Break periods: 12:00-13:00 and 15:00-16:00. ";
                                        }
                                    } else {
                                        echo "Default period range: 07:00 - 20:00 (13 hours). Break periods: 12:00-13:00 and 15:00-16:00. ";
                                    }
                                    $stream_period_info_stmt->close();
                                } else {
                                    echo "Default period range: 07:00 - 20:00 (13 hours). Break periods: 12:00-13:00 and 15:00-16:00. ";
                                }
                                ?>
                                <strong>NEW:</strong> Courses now properly span multiple periods based on their duration. 
                                A 3-hour course will visually span 3 consecutive time slots using colspan. 
                                Time display has been improved to show HH:MM format only.
                            </small>
                        </div>
                        
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="template-stats">
                                        <span class="badge bg-primary"><?php echo count($template_days); ?> Days</span>
                                        <small class="d-block text-muted mt-1">
                                            <?php echo implode(', ', array_column($template_days, 'name')); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="template-stats">
                                        <span class="badge bg-success"><?php echo count($template_time_slots); ?> Time Slots</span>
                                        <small class="d-block text-muted mt-1">
                                            <?php 
                                            if (!empty($current_stream_id)) {
                                                $stream_period_count_sql = "SELECT period_start, period_end FROM streams WHERE id = ? AND is_active = 1";
                                                $stream_period_count_stmt = $conn->prepare($stream_period_count_sql);
                                                $stream_period_count_stmt->bind_param('i', $current_stream_id);
                                                $stream_period_count_stmt->execute();
                                                $stream_period_count_result = $stream_period_count_stmt->get_result();
                                                
                                                if ($stream_period_count_row = $stream_period_count_result->fetch_assoc()) {
                                                    $period_start = $stream_period_count_row['period_start'];
                                                    $period_end = $stream_period_count_row['period_end'];
                                                    
                                                    if (!empty($period_start) && !empty($period_end)) {
                                                        echo "Stream periods: " . htmlspecialchars(formatTimeForDisplay($period_start)) . " - " . htmlspecialchars(formatTimeForDisplay($period_end));
                                                    } else {
                                                        echo count($template_time_slots) . " periods available";
                                                    }
                                                } else {
                                                    echo count($template_time_slots) . " periods available";
                                                }
                                                $stream_period_count_stmt->close();
                                            } else {
                                                echo count($template_time_slots) . " periods available";
                                            }
                                            ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="template-stats">
                                        <span class="badge bg-info"><?php echo count($template_rooms); ?> Rooms</span>
                                        <small class="d-block text-muted mt-1">
                                            Total capacity: <?php echo array_sum(array_column($template_rooms, 'capacity')); ?> seats
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Unable to generate template preview. Please ensure you have active days, time slots, and rooms configured.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Post-Generation Statistics (show when timetable exists) -->
        <?php if ($has_timetable): ?>
        <div class="row m-3">
            <div class="col-12">
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>Post-Generation Statistics
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 d-flex">
                                <div class="card theme-card bg-theme-primary text-white text-center mb-2 w-100 h-100">
                                    <div class="card-body">
                                        <div class="stat-number"><?php echo $total_classes; ?></div>
                                        <div>
                                            Total Classes (Current stream)
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Number of active classes. Stream-aware when a current stream is selected."></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex">
                                <div class="card theme-card bg-theme-accent text-dark text-center mb-2 w-100 h-100">
                                    <div class="card-body">
                                        <div class="stat-number"><?php echo $total_courses; ?></div>
                                        <div>
                                            Total Courses (Global)
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Number of active courses (global, not stream-specific)."></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex">
                                <div class="card theme-card bg-theme-green text-white text-center mb-2 w-100 h-100">
                                    <div class="card-body">
                                        <div class="stat-number"><?php echo $total_assignments; ?></div>
                                        <div>
                                            Assignments (Current stream)
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Active class–course pairings; stream-aware when a current stream is selected."></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex">
                                <div class="card theme-card bg-theme-primary text-white text-center mb-2 w-100 h-100">
                                    <div class="card-body">
                                        <div class="stat-number"><?php echo $total_timetable_entries; ?></div>
                                        <div>
                                            Timetable Entries (Current stream)
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Scheduled sessions now in the timetable; stream-aware when a current stream is selected."></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                        // Scheduling coverage metrics
                        $scheduled_assignments = 0;
                        $coverage_percent = 0;
                        $unscheduled_assignments = 0;

                        // Determine if we can count distinct class_course_id per stream
                        $has_stream_col_cov = false;
                        $col_cov = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
                        if ($col_cov && $col_cov->num_rows > 0) { $has_stream_col_cov = true; }
                        if ($col_cov) $col_cov->close();

                        $has_t_class_course_cov = false;
                        $tcol_cov = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
                        if ($tcol_cov && $tcol_cov->num_rows > 0) { $has_t_class_course_cov = true; }
                        if ($tcol_cov) $tcol_cov->close();

                        if ($total_assignments > 0) {
                            if ($has_stream_col_cov && !empty($current_stream_id)) {
                                if ($has_t_class_course_cov) {
                                    $sql_cov = "SELECT COUNT(DISTINCT t.class_course_id) AS cnt
                                                FROM timetable t
                                                JOIN class_courses cc ON t.class_course_id = cc.id
                                                JOIN classes c ON cc.class_id = c.id
                                                WHERE c.stream_id = " . intval($current_stream_id);
                                    $res_cov = $conn->query($sql_cov);
                                    $scheduled_assignments = $res_cov ? (int)$res_cov->fetch_assoc()['cnt'] : 0;
                                } else {
                                    // Fallback: use timetable entry count within stream
                                    $scheduled_assignments = min($total_timetable_entries, $total_assignments);
                                }
                            } else {
                                // Global fallback
                                if ($has_t_class_course_cov) {
                                    $sql_cov = "SELECT COUNT(DISTINCT class_course_id) AS cnt FROM timetable";
                                    $res_cov = $conn->query($sql_cov);
                                    $scheduled_assignments = $res_cov ? (int)$res_cov->fetch_assoc()['cnt'] : 0;
                                } else {
                                    $scheduled_assignments = min($total_timetable_entries, $total_assignments);
                                }
                            }

                            $unscheduled_assignments = max(0, $total_assignments - $scheduled_assignments);
                            $coverage_percent = $total_assignments > 0 ? round(($scheduled_assignments / $total_assignments) * 100, 1) : 0;
                        }
                        ?>
                        <div class="mt-3">
                            <h6 class="mb-2">Scheduling Coverage <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Shows how many class–course assignments are currently scheduled in the timetable."></i></h6>
                            <div class="row text-center">
                                <div class="col-md-4 mb-2">
                                    <span class="badge bg-success">Scheduled: <?php echo $scheduled_assignments; ?> of <?php echo $total_assignments; ?></span>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <span class="badge bg-primary">Coverage: <?php echo $coverage_percent; ?>%</span>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <span class="badge bg-secondary">Unscheduled: <?php echo $unscheduled_assignments; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Generated Timetable Display Section -->
        <div id="generated-timetable-section" style="display: none;">
            <div class="row m-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-calendar-check me-2"></i>Generated Timetable
                                <span class="badge bg-success ms-2" id="generated-badge">
                                    <i class="fas fa-check me-1"></i>Generated
                                </span>
                            </h6>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="editTimetable()">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="exportTimetable()">
                                    <i class="fas fa-download me-1"></i>Export
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="timetable-preview">
                                <!-- Timetable will be displayed here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>


<style>
/* Timetable Template Styles */
.timetable-template {
    font-size: 0.875rem;
}

.timetable-template th,
.timetable-template td {
    vertical-align: middle;
    padding: 0.5rem;
}

/* Day Header Row */
.day-header-row {
    background-color: #e9ecef;
}

.day-header {
    background-color: #495057;
    color: white;
    font-weight: bold;
    text-align: center;
    padding: 0.75rem;
}

.day-name {
    font-size: 1.1rem;
    font-weight: 600;
}

/* Room Name Cell */
.room-name-cell {
    background-color: #f8f9fa;
    font-weight: 600;
    min-width: 150px;
    border-right: 2px solid #dee2e6;
}

.room-info {
    text-align: left;
}

.room-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: #495057;
}

/* Time Period Headers */
.time-period-header {
    background-color: white;
    color: #495057;
    font-weight: 600;
    text-align: center;
    min-width: 100px;
    border-bottom: 2px solid #e9ecef;
    border-right: 1px solid #dee2e6;
}

.period-time {
    font-weight: 600;
    font-size: 0.9rem;
    color: #495057;
}

/* Break Period Headers */
.break-period-header {
    background-color: #f8d7da;
    color: #721c24;
    font-weight: 600;
    text-align: center;
    border: 2px solid #f5c6cb;
}

.break-info {
    padding: 0.5rem;
}

.break-label {
    font-weight: 600;
    font-size: 0.9rem;
    display: block;
}

.break-time {
    font-size: 0.8rem;
    opacity: 0.8;
    margin-top: 0.25rem;
}

/* Break Cells */
.break-cell {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    min-height: 80px;
    position: relative;
    text-align: center;
}

.break-placeholder {
    text-align: center;
    color: #721c24;
    padding: 0.5rem;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}



.break-placeholder small {
    font-size: 0.75rem;
    opacity: 0.8;
}

/* Template Cells */
.template-cell {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    min-height: 80px;
    position: relative;
    transition: all 0.2s ease;
    border-right: 1px solid #dee2e6;
}

.template-cell:hover {
    background-color: #e9ecef;
    cursor: pointer;
}

.template-cell:last-child {
    border-right: 1px solid #dee2e6;
}

.template-placeholder {
    text-align: center;
    color: #6c757d;
    padding: 0.5rem;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.template-placeholder i {
    font-size: 1.5rem;
    margin-bottom: 0.25rem;
    opacity: 0.6;
}

.template-placeholder small {
    font-size: 0.75rem;
    opacity: 0.8;
}

/* Sample blocks styling */
.sample-blocks {
    margin-bottom: 1rem;
}

.sample-block {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.sample-block:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Room Row */
.room-row {
    border-bottom: 1px solid #dee2e6;
}

.room-row:hover {
    background-color: #f8f9fa;
}

.template-stats {
    padding: 0.5rem;
}

.template-stats .badge {
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .timetable-template {
        font-size: 0.75rem;
    }
    
    .timetable-template th,
    .timetable-template td {
        padding: 0.25rem;
    }
    
    .room-name {
        font-size: 0.8rem;
    }
    
    .time-range {
        font-size: 0.8rem;
    }
    
    .day-name {
        font-size: 1rem;
    }
    
    .template-cell {
        min-height: 60px;
    }
    
    .template-placeholder i {
        font-size: 1.2rem;
    }
}

/* Editable course blocks */
.editable-course {
    transition: all 0.2s ease;
    position: relative;
}

.editable-course:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    z-index: 10;
}

.editable-course::after {
    content: "Click to edit";
    position: absolute;
    top: 2px;
    right: 2px;
    background: rgba(0,0,0,0.7);
    color: white;
    font-size: 0.6em;
    padding: 1px 3px;
    border-radius: 2px;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.editable-course:hover::after {
    opacity: 1;
}

/* Duration badge styling */
.course-block .badge {
    font-size: 0.6em;
    padding: 0.2em 0.4em;
}

/* Spanned course styling */
.template-cell[colspan] {
    background-color: #f8f9fa;
    border: 2px solid #dee2e6;
}

.template-cell[colspan] .course-block {
    border-left: 4px solid #28a745;
    border-right: 4px solid #28a745;
}

/* Multi-period course indicators */
.course-block .badge.bg-info {
    background-color: #17a2b8 !important;
    color: white;
}

/* Responsive adjustments for spanned courses */
@media (max-width: 768px) {
    .template-cell[colspan] .course-block {
        min-width: auto !important;
        font-size: 0.7em;
    }
    
    .course-block .badge {
        font-size: 0.5em;
        padding: 0.1em 0.3em;
    }
}
</style>

<script>
// Available rooms data for the edit modal
const availableRooms = <?php echo json_encode($all_rooms); ?>;

// Global variables for Option 2 implementation
let generatedTimetableData = null;
let currentGenerationParams = null;

document.addEventListener('DOMContentLoaded', function () {
    // Debug: Log available rooms
    console.log('Available rooms for edit modal:', availableRooms);
    
    try {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            if (window.bootstrap && bootstrap.Tooltip) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            }
        });
    } catch (e) { /* ignore */ }
    
    // Add form submission listener for loading state
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            showLoadingState();
        });
    }
});



// Generate exams timetable function
function generateExamsTimetable() {
    const timetableName = document.querySelector('input[name="timetable_name"]').value;
    const semester = document.querySelector('select[name="semester"]').value;
    
    if (!timetableName || !semester) {
        alert('Please fill in both Timetable Name and Semester fields.');
        return;
    }
    
    showErrorMessage('Exams timetable generation is not implemented yet.');
}

// Fetch generated timetable data from the database
function fetchGeneratedTimetableData() {
    if (!currentGenerationParams) return;
    
    const formData = new FormData();
    formData.append('action', 'get_generated_timetable');
    formData.append('academic_year', currentGenerationParams.academic_year);
    formData.append('semester', currentGenerationParams.semester);
    
    fetch('api_timetable_template.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            generatedTimetableData = data.data;
            displayTimetablePreview(data.data);
        } else {
            console.error('Error fetching timetable data:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Display timetable preview
function displayTimetablePreview(timetableData) {
    const previewContainer = document.getElementById('timetable-preview');
    
    if (!timetableData || timetableData.length === 0) {
        previewContainer.innerHTML = '<div class="alert alert-info">No timetable data available.</div>';
        return;
    }
    
    // Create a detailed table view of the generated timetable
    let html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
    html += '<thead class="table-dark"><tr><th>Day</th><th>Class</th><th>Course</th><th>Lecturer</th><th>Room</th><th>Time</th><th>Actions</th></tr></thead><tbody>';
    
    timetableData.forEach((entry, index) => {
        html += `
            <tr>
                <td>${entry.day_name}</td>
                <td>${entry.class_name}${entry.division_label ? ' ' + entry.division_label : ''}</td>
                <td><strong>${entry.course_code}</strong> - ${entry.course_name}</td>
                <td>${entry.lecturer_name}</td>
                <td>${entry.room_name}</td>
                <td>${entry.start_time} - ${entry.end_time}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="editTimetableEntry(${index})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTimetableEntry(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    
    previewContainer.innerHTML = html;
}

// Show detailed timetable editor
function showDetailedTimetableEditor() {
    const previewContainer = document.getElementById('timetable-preview');
    
    if (!generatedTimetableData || generatedTimetableData.length === 0) {
        showErrorMessage('No timetable data to edit.');
        return;
    }
    
    // Create an editable timetable interface
    let html = '<div class="editable-timetable">';
    html += '<h5 class="mb-3">Edit Timetable Entries</h5>';
    html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
    html += '<thead class="table-primary"><tr><th>Day</th><th>Class</th><th>Course</th><th>Lecturer</th><th>Room</th><th>Time</th><th>Actions</th></tr></thead><tbody>';
    
    generatedTimetableData.forEach((entry, index) => {
        html += `
            <tr id="entry-${index}">
                <td>
                    <select class="form-select form-select-sm" onchange="updateTimetableEntry(${index}, 'day', this.value)">
                        <option value="Monday" ${entry.day_name === 'Monday' ? 'selected' : ''}>Monday</option>
                        <option value="Tuesday" ${entry.day_name === 'Tuesday' ? 'selected' : ''}>Tuesday</option>
                        <option value="Wednesday" ${entry.day_name === 'Wednesday' ? 'selected' : ''}>Wednesday</option>
                        <option value="Thursday" ${entry.day_name === 'Thursday' ? 'selected' : ''}>Thursday</option>
                        <option value="Friday" ${entry.day_name === 'Friday' ? 'selected' : ''}>Friday</option>
                    </select>
                </td>
                <td>${entry.class_name}${entry.division_label ? ' ' + entry.division_label : ''}</td>
                <td><strong>${entry.course_code}</strong> - ${entry.course_name}</td>
                <td>${entry.lecturer_name}</td>
                <td>
                    <select class="form-select form-select-sm" onchange="updateTimetableEntry(${index}, 'room', this.value)">
                        <option value="${entry.room_name}" selected>${entry.room_name}</option>
                        <!-- Room options would be populated from database -->
                    </select>
                </td>
                <td>
                    <select class="form-select form-select-sm" onchange="updateTimetableEntry(${index}, 'time', this.value)">
                        <option value="${entry.start_time}-${entry.end_time}" selected>${entry.start_time} - ${entry.end_time}</option>
                        <!-- Time slot options would be populated from database -->
                    </select>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTimetableEntry(${index})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    html += '<div class="mt-3">';
    html += '<button class="btn btn-success" onclick="saveTimetableChanges()">Save Changes</button>';
    html += '<button class="btn btn-secondary ms-2" onclick="cancelTimetableEdit()">Cancel</button>';
    html += '</div></div>';
    
    previewContainer.innerHTML = html;
}

// Update timetable entry
function updateTimetableEntry(index, field, value) {
    if (generatedTimetableData[index]) {
        if (field === 'day') {
            generatedTimetableData[index].day_name = value;
        } else if (field === 'room') {
            generatedTimetableData[index].room_name = value;
        } else if (field === 'time') {
            const [start, end] = value.split('-');
            generatedTimetableData[index].start_time = start;
            generatedTimetableData[index].end_time = end;
        }
    }
}

// Delete timetable entry
function deleteTimetableEntry(index) {
    if (confirm('Are you sure you want to delete this timetable entry?')) {
        generatedTimetableData.splice(index, 1);
        showDetailedTimetableEditor(); // Refresh the editor
    }
}

// Save timetable changes
function saveTimetableChanges() {
    // Here you would save the changes to the database
    showSuccessMessage('Timetable changes saved successfully!');
    displayTimetablePreview(generatedTimetableData); // Switch back to preview mode
}

// Cancel timetable edit
function cancelTimetableEdit() {
    displayTimetablePreview(generatedTimetableData); // Switch back to preview mode
}

// Save timetable to saved timetables
function saveToSavedTimetables() {
    if (!currentGenerationParams) {
        showErrorMessage('No timetable generated to save.');
        return;
    }
    
    if (!confirm('Are you sure you want to save this timetable to Saved Timetables?')) {
        return;
    }
    
    // Show loading state
    const saveBtn = document.getElementById('save-timetable-btn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
    saveBtn.disabled = true;
    
    // Prepare save data
    const formData = new FormData();
    formData.append('action', 'save_generated_timetable');
    formData.append('academic_year', currentGenerationParams.academic_year);
    formData.append('semester', currentGenerationParams.semester);
    formData.append('type', currentGenerationParams.type);
    
    // Call save API
    fetch('api_timetable_template.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Timetable saved successfully! You can view it in Saved Timetables.');
            // Update badge to show it's saved
            const badge = document.getElementById('generated-badge');
            badge.innerHTML = '<i class="fas fa-check me-1"></i>Saved';
            badge.className = 'badge bg-primary';
        } else {
            showErrorMessage('Failed to save timetable: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error saving timetable. Please try again.');
    })
    .finally(() => {
        // Restore button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

// Edit timetable function
function editTimetable() {
    if (!currentGenerationParams) {
        showErrorMessage('No timetable generated to edit.');
        return;
    }
    
    // Show detailed timetable editing interface on the same page
    showDetailedTimetableEditor();
}

// Export timetable function
function exportTimetable() {
    if (!currentGenerationParams) {
        showErrorMessage('No timetable generated to export.');
        return;
    }
    
    // Redirect to export_timetable.php with the current parameters
    const url = new URL('export_timetable.php', window.location.origin);
    url.searchParams.set('academic_year', currentGenerationParams.academic_year);
    url.searchParams.set('semester', currentGenerationParams.semester);
    url.searchParams.set('type', currentGenerationParams.type);
    
    window.location.href = url.toString();
}

// Show loading state
function showLoadingState() {
    const generateBtn = document.getElementById('generate-btn');
    if (generateBtn) {
        const originalText = generateBtn.innerHTML;
        generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Generating...';
        generateBtn.disabled = true;
        
        // Store original text for restoration
        generateBtn.dataset.originalText = originalText;
    }
}

// Hide loading state
function hideLoadingState() {
    const generateBtn = document.getElementById('generate-btn');
    if (generateBtn && generateBtn.dataset.originalText) {
        generateBtn.innerHTML = generateBtn.dataset.originalText;
        generateBtn.disabled = false;
        delete generateBtn.dataset.originalText;
    }
}

// Show success message
function showSuccessMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show';
    alertDiv.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at the top of main content
    const mainContent = document.getElementById('mainContent');
    mainContent.insertBefore(alertDiv, mainContent.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Show error message
function showErrorMessage(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
    alertDiv.innerHTML = `
        <i class="fas fa-exclamation-circle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at the top of main content
    const mainContent = document.getElementById('mainContent');
    mainContent.insertBefore(alertDiv, mainContent.firstChild);
    
    // Auto-dismiss after 8 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 8000);
}

// Edit timetable cell function
function editTimetableCell(element) {
    const entryId = element.dataset.entryId;
    const dayId = element.dataset.dayId;
    const timeSlotId = element.dataset.timeSlotId;
    const roomId = element.dataset.roomId;
    const courseCode = element.dataset.courseCode;
    const className = element.dataset.className;
    const lecturerName = element.dataset.lecturerName;
    const hours = element.dataset.hours;
    
    // Show edit modal
    showEditModal(entryId, dayId, timeSlotId, roomId, courseCode, className, lecturerName, hours);
}

// Generate room options for the edit modal
function generateRoomOptions(selectedRoomId) {
    let options = '';
    
    if (!availableRooms || availableRooms.length === 0) {
        options = '<option value="">No rooms available</option>';
        return options;
    }
    
    availableRooms.forEach(room => {
        const selected = room.id == selectedRoomId ? 'selected' : '';
        const roomTypeDisplay = room.room_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        options += `<option value="${room.id}" ${selected}>${room.name} (${room.capacity} seats, ${roomTypeDisplay})</option>`;
    });
    return options;
}

// Show edit modal
function showEditModal(entryId, dayId, timeSlotId, roomId, courseCode, className, lecturerName, hours) {
    // Create modal HTML
    const modalHtml = `
        <div class="modal fade" id="editTimetableModal" tabindex="-1" aria-labelledby="editTimetableModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTimetableModalLabel">
                            <i class="fas fa-edit me-2"></i>Edit Course Schedule
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editTimetableForm">
                            <input type="hidden" id="edit_entry_id" name="entry_id" value="${entryId}">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_day" class="form-label">Day</label>
                                        <select class="form-select" id="edit_day" name="day_id" required>
                                            <option value="">Select Day</option>
                                            <option value="1" ${dayId == 1 ? 'selected' : ''}>Monday</option>
                                            <option value="2" ${dayId == 2 ? 'selected' : ''}>Tuesday</option>
                                            <option value="3" ${dayId == 3 ? 'selected' : ''}>Wednesday</option>
                                            <option value="4" ${dayId == 4 ? 'selected' : ''}>Thursday</option>
                                            <option value="5" ${dayId == 5 ? 'selected' : ''}>Friday</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_time_slot" class="form-label">Time Slot</label>
                                        <select class="form-select" id="edit_time_slot" name="time_slot_id" required>
                                            <option value="">Select Time Slot</option>
                                            <option value="1" ${timeSlotId == 1 ? 'selected' : ''}>08:00 - 09:00</option>
                                            <option value="2" ${timeSlotId == 2 ? 'selected' : ''}>09:00 - 10:00</option>
                                            <option value="3" ${timeSlotId == 3 ? 'selected' : ''}>10:00 - 11:00</option>
                                            <option value="4" ${timeSlotId == 4 ? 'selected' : ''}>11:00 - 12:00</option>
                                            <option value="5" ${timeSlotId == 5 ? 'selected' : ''}>13:00 - 14:00</option>
                                            <option value="6" ${timeSlotId == 6 ? 'selected' : ''}>14:00 - 15:00</option>
                                            <option value="7" ${timeSlotId == 7 ? 'selected' : ''}>15:00 - 16:00</option>
                                            <option value="8" ${timeSlotId == 8 ? 'selected' : ''}>16:00 - 17:00</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_room" class="form-label">Room</label>
                                        <select class="form-select" id="edit_room" name="room_id" required>
                                            <option value="">Select Room</option>
                                            ${generateRoomOptions(roomId)}
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Course Duration (Read-only)</label>
                                        <input type="text" class="form-control" value="${hours} Hour(s)" readonly style="background-color: #f8f9fa;">
                                        <input type="hidden" id="edit_hours" name="hours_per_week" value="${hours}">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Current Information</label>
                                <div class="alert alert-info">
                                    <strong>Course:</strong> ${courseCode}<br>
                                    <strong>Class:</strong> ${className}<br>
                                    <strong>Lecturer:</strong> ${lecturerName || 'Not assigned'}<br>
                                    <strong>Duration:</strong> ${hours} hour(s)
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveTimetableEdit()">
                            <i class="fas fa-save me-1"></i>Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('editTimetableModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('editTimetableModal'));
    modal.show();
}

// Save timetable edit
function saveTimetableEdit() {
    const form = document.getElementById('editTimetableForm');
    const formData = new FormData(form);
    
    // Add action
    formData.append('action', 'update_timetable_entry');
    
    // Show loading state
    const saveBtn = document.querySelector('#editTimetableModal .btn-primary');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
    saveBtn.disabled = true;
    
    // Send AJAX request
    fetch('api_timetable_edit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Timetable entry updated successfully!');
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('editTimetableModal'));
            modal.hide();
            // Reload page to show updated data
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showErrorMessage('Failed to update timetable entry: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error updating timetable entry. Please try again.');
    })
    .finally(() => {
        // Restore button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}
</script>