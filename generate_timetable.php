<?php
// Set higher limits for genetic algorithm processing
ini_set('max_execution_time', 1800); // 30 minutes for large timetable generation
ini_set('memory_limit', '1024M');   // 1GB memory limit
set_time_limit(1800);                // Alternative way to set execution time

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

// Check if we're in edit mode (stream_id passed via URL)
$edit_mode = false;
$edit_stream_id = null;
$view_version = null;
$view_semester = null;

if (isset($_GET['edit_stream_id']) && !empty($_GET['edit_stream_id'])) {
    $edit_stream_id = intval($_GET['edit_stream_id']);
    $edit_mode = true;
    $current_stream_id = $edit_stream_id;
    
    // Check if we're viewing a specific version
    if (isset($_GET['version']) && !empty($_GET['version'])) {
        $view_version = $_GET['version'];
    }
    if (isset($_GET['semester']) && !empty($_GET['semester'])) {
        $view_semester = $_GET['semester'];
    }
} else {
    $current_stream_id = $streamManager->getCurrentStreamId();
}

error_log("DEBUG: Current stream ID determined as: $current_stream_id (edit_mode: " . ($edit_mode ? 'true' : 'false') . ")");

// Set current semester from form, URL params, or use defaults
$current_semester = 2; // Default to semester 2

// Set current version from URL params or use default
$current_version = 'regular'; // Default version
if (isset($_GET['version']) && !empty($_GET['version'])) {
    $current_version = trim($_GET['version']);
    // Sanitize version name
    $current_version = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $current_version);
    $current_version = trim($current_version);
    if (empty($current_version)) {
        $current_version = 'regular';
    }
}

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
        
        // Get stream name for version naming
        $stream_name = getStreamName($conn, $stream_id);
        
        // Generate automatic version name based on stream name
        $version = generateStreamBasedVersion($conn, $stream_id, $semester_int, $stream_name);
        
        // Update current semester for display
        $current_semester = $semester_int;
        
        // GA parameters (with sensible defaults - hidden from user)
        $population_size = 50;  // Reduced from 100 to save memory
        $generations = 300;     // Reduced from 500 to save time
        $mutation_rate = 0.1;
        $crossover_rate = 0.8;
        $max_runtime = 300;

        if ($semester === '' || $semester_int === 0) {
            $error_message = 'Please specify semester (1 or 2) before generating the timetable.';
        } else {
            try {
                // For incremental scheduling, we don't clear existing entries
                // We only add new courses to available time slots
                // This allows building upon existing timetables
                
                // Initialize genetic algorithm
                // Determine academic year from stream manager if available
                $academic_year = null;
                if (isset($streamManager) && method_exists($streamManager, 'getCurrentAcademicYear')) {
                    $academic_year = $streamManager->getCurrentAcademicYear();
                }

                $gaOptions = [
                    'population_size' => $population_size,
                    'generations' => $generations,
                    'mutation_rate' => $mutation_rate,
                    'crossover_rate' => $crossover_rate,
                    'max_runtime' => $max_runtime,
                    'stream_id' => $stream_id,
                    'semester' => $semester_int,
                    'academic_year' => $academic_year,
                    'exclude_scheduled' => false  // Generate complete timetable for each version
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
                    
                    // Validate solution for duplicates before conversion
                    $duplicateCheck = validateSolutionForDuplicates($results['solution']);
                    if (!empty($duplicateCheck['duplicates'])) {
                        error_log("Duplicate entries found in GA solution: " . implode(', ', $duplicateCheck['duplicates']));
                        // Log the problematic entries for debugging
                        foreach ($duplicateCheck['duplicate_entries'] as $entry) {
                            error_log("Duplicate entry: " . json_encode($entry));
                        }
                    }
                    
                    // Convert solution to database format
                    $timetableEntries = $ga->convertToDatabaseFormat($results['solution']);
                    
                    // Additional validation after conversion
                    $convertedDuplicates = validateTimetableEntries($timetableEntries);
                    if (!empty($convertedDuplicates)) {
                        error_log("Duplicates found after conversion: " . implode(', ', $convertedDuplicates));
                    }
                    
                    // Insert into database
                    $inserted_count = insertTimetableEntries($conn, $timetableEntries, $version);
                    
                    // Post-process: Try to schedule unscheduled classes in available slots
                    $additional_scheduled = scheduleUnscheduledClasses($conn, $stream_id, $semester_int, $version);
                    
                    if ($inserted_count > 0) {
                        $msg = "Timetable generated successfully! $inserted_count entries created.";
                        $msg .= " Version: <strong>$version</strong>";
                        if ($additional_scheduled > 0) {
                            $msg .= " Additionally scheduled $additional_scheduled unscheduled classes in available slots.";
                        }
                        
                        // Check for lecturer conflicts in current version only
                        $conflictCount = 0;
                        if (session_status() === PHP_SESSION_NONE) {
                            session_start();
                        }
                        
                        // Clear previous conflicts and check only current version
                        $_SESSION['lecturer_conflicts'] = [];
                        
                        // Count conflicts for current version only
                        $conflict_query = "
                            SELECT COUNT(*) as conflict_count
                            FROM (
                                SELECT lc.lecturer_id, t.day_id, t.time_slot_id
                                FROM timetable t
                                JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                                WHERE t.semester = ? AND t.academic_year = ? AND t.version = ?
                                GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id
                                HAVING COUNT(*) > 1
                            ) as conflicts
                        ";
                        
                        $semester_param = is_numeric($semester_int) ? (($semester_int == 1) ? 'first' : 'second') : $semester_int;
                        $academic_year = '2025/2026';
                        
                        $stmt = $conn->prepare($conflict_query);
                        $stmt->bind_param("sss", $semester_param, $academic_year, $version);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        if ($row = $result->fetch_assoc()) {
                            $conflictCount = $row['conflict_count'];
                        }
                        $stmt->close();
                        
                        if ($conflictCount > 0) {
                            $msg .= " <strong>Note:</strong> $conflictCount lecturer conflicts detected in this version. <a href='lecturer_conflicts.php?version=" . urlencode($version) . "' class='alert-link'>Review and resolve conflicts</a>";
                        }
                        
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
    }
}

// Helper functions
function versionExists($conn, $stream_id, $semester, $version) {
    $query = "
        SELECT COUNT(*) as count
        FROM timetable t
        JOIN class_courses cc ON t.class_course_id = cc.id
        JOIN classes c ON cc.class_id = c.id
        WHERE c.stream_id = ? AND t.semester = ? AND t.version = ?
    ";
    
    $semester_param = is_numeric($semester) ? (($semester == 1) ? 'first' : 'second') : $semester;
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $stream_id, $semester_param, $version);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    return $count > 0;
}

function getStreamName($conn, $stream_id) {
    $query = "SELECT name FROM streams WHERE id = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $stream_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stream_name = $row['name'];
        $stmt->close();
        return $stream_name;
    }
    
    $stmt->close();
    return 'Unknown Stream'; // Fallback
}

function generateStreamBasedVersion($conn, $stream_id, $semester, $stream_name) {
    // Clean stream name for version naming (remove special characters)
    $clean_stream_name = preg_replace('/[^a-zA-Z0-9\s]/', '', $stream_name);
    $clean_stream_name = trim($clean_stream_name);
    $clean_stream_name = str_replace(' ', ' ', $clean_stream_name); // Normalize spaces
    
    // Find existing versions for this stream
    $query = "
        SELECT DISTINCT t.version
        FROM timetable t
        JOIN class_courses cc ON t.class_course_id = cc.id
        JOIN classes c ON cc.class_id = c.id
        WHERE c.stream_id = ? AND t.semester = ?
        ORDER BY t.version DESC
    ";
    
    $semester_param = is_numeric($semester) ? (($semester == 1) ? 'first' : 'second') : $semester;
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $stream_id, $semester_param);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $existing_versions = [];
    while ($row = $result->fetch_assoc()) {
        $existing_versions[] = $row['version'];
    }
    $stmt->close();
    
    // Find the highest draft number for this stream
    $max_draft_number = 0;
    
    // Check for exact base name match (like "regular")
    if (in_array($clean_stream_name, $existing_versions)) {
        $max_draft_number = 1;
    }
    
    // Check for draft patterns (like "Regular DRAFT 1", "Regular DRAFT 2")
    $draft_pattern = '/^' . preg_quote($clean_stream_name, '/') . '\s+DRAFT\s+(\d+)$/i';
    foreach ($existing_versions as $existing_version) {
        if (preg_match($draft_pattern, $existing_version, $matches)) {
            $max_draft_number = max($max_draft_number, (int)$matches[1]);
        }
    }
    
    // Generate next draft number
    $next_draft_number = $max_draft_number + 1;
    
    // Return format: "STREAM NAME DRAFT {number}"
    return "{$clean_stream_name} DRAFT {$next_draft_number}";
}

function clearExistingTimetable($conn, $semester, $stream_id, $version = 'regular') {
    // Check if timetable table has the new schema
    $has_class_course = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'")->num_rows > 0;
    $has_version = $conn->query("SHOW COLUMNS FROM timetable LIKE 'version'")->num_rows > 0;
    
    // Determine if timetable.semester is enum('first','second',...) or numeric and normalize input
    $semTypeRes = $conn->query("SHOW COLUMNS FROM timetable LIKE 'semester'");
    $semester_param = $semester;
    if ($semTypeRes && $semTypeRow = $semTypeRes->fetch_assoc()) {
        $semType = strtolower($semTypeRow['Type'] ?? '');
        $isEnum = strpos($semType, 'enum(') !== false;
        if ($isEnum) {
            // Map various inputs to enum values
            $sv = is_string($semester) ? strtolower(trim($semester)) : $semester;
            if ($sv === 1 || $sv === '1' || $sv === 'first' || $sv === 'semester 1') { $semester_param = 'first'; }
            elseif ($sv === 2 || $sv === '2' || $sv === 'second' || $sv === 'semester 2') { $semester_param = 'second'; }
            elseif ($sv === 'summer') { $semester_param = 'summer'; }
        }
    }
    if ($semTypeRes) { $semTypeRes->close(); }

    if ($has_class_course) {
        // New schema: clear via class_courses -> classes -> stream
        if ($has_version) {
            // Include version in the delete query
            $sql = "DELETE t FROM timetable t 
                    JOIN class_courses cc ON t.class_course_id = cc.id 
                    JOIN classes c ON cc.class_id = c.id 
                    WHERE t.semester = ? AND t.version = ? AND c.stream_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $semester_param, $version, $stream_id);
        } else {
            // Fallback for tables without version field
        $sql = "DELETE t FROM timetable t 
                JOIN class_courses cc ON t.class_course_id = cc.id 
                JOIN classes c ON cc.class_id = c.id 
                WHERE t.semester = ? AND c.stream_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $semester_param, $stream_id);
        }
        $stmt->execute();
        $stmt->close();
        
        // Also clear any entries with the same semester and academic year to be safe
        $academic_year_param = $_POST['academic_year'] ?? null;
        if ($academic_year_param) {
            $sql = "DELETE FROM timetable WHERE semester = ? AND academic_year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $semester_param, $academic_year_param);
            $stmt->execute();
            $stmt->close();
        } else {
            // Fallback: clear all entries with the same semester
            $sql = "DELETE FROM timetable WHERE semester = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $semester_param);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // Old schema: clear via class_id -> classes -> stream
        $sql = "DELETE t FROM timetable t 
                JOIN classes c ON t.class_id = c.id 
                WHERE t.semester = ? AND c.stream_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $semester_param, $stream_id);
        $stmt->execute();
        $stmt->close();
        
        // Also clear any entries with the same semester and academic year to be safe
        $academic_year_param = $_POST['academic_year'] ?? null;
        if ($academic_year_param) {
            $sql = "DELETE FROM timetable WHERE semester = ? AND academic_year = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $semester_param, $academic_year_param);
            $stmt->execute();
            $stmt->close();
        } else {
            // Fallback: clear all entries with the same semester
            $sql = "DELETE FROM timetable WHERE semester = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $semester_param);
            $stmt->execute();
            $stmt->close();
        }
    }
}

function insertTimetableEntries($conn, $entries, $version = 'regular') {
    if (empty($entries)) {
        return 0;
    }
    
    error_log("Starting insertion of " . count($entries) . " timetable entries");
    
    // Pre-filter duplicates before database insertion
    $uniqueEntries = [];
    $duplicateKeys = [];
    $entryKeys = [];
    
    foreach ($entries as $entry) {
        $key = $entry['class_course_id'] . '-' . $entry['day_id'] . '-' . $entry['time_slot_id'] . '-' . $entry['division_label'];
        
        if (!isset($entryKeys[$key])) {
            $entryKeys[$key] = true;
            $uniqueEntries[] = $entry;
        } else {
            $duplicateKeys[] = $key;
            error_log("Pre-insertion duplicate detected: " . $key . " for entry: " . json_encode($entry));
        }
    }
    
    if (!empty($duplicateKeys)) {
        error_log("Removed " . count($duplicateKeys) . " duplicate entries before database insertion");
    }
    
    $inserted_count = 0;
    $db_duplicate_keys = []; // Track duplicate keys for debugging
    $occupiedSlotKeys = [];  // Track room-day-time occupied slots to avoid uq_timetable_slot violations
    
    // Prepare caches to validate FK references and repair lecturer_course_id when needed
    $lecturerCourseIds = [];
    $lecturerCourseByCourse = [];
    $res = $conn->query("SELECT id, course_id FROM lecturer_courses WHERE is_active = 1");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $lecturerCourseIds[(int)$row['id']] = true;
            $cid = (int)$row['course_id'];
            if (!isset($lecturerCourseByCourse[$cid])) { $lecturerCourseByCourse[$cid] = (int)$row['id']; }
        }
        $res->close();
    }
    // Map class_course_id -> course_id for fallback resolution
    $classCourseToCourse = [];
    $res2 = $conn->query("SELECT id, course_id FROM class_courses WHERE is_active = 1");
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $classCourseToCourse[(int)$row['id']] = (int)$row['course_id'];
        }
        $res2->close();
    }
    // Build FK existence maps to avoid FK constraint errors during insert
    $validClassCourses = [];
    $res3 = $conn->query("SELECT id FROM class_courses WHERE is_active = 1");
    if ($res3) {
        while ($row = $res3->fetch_assoc()) { $validClassCourses[(int)$row['id']] = true; }
        $res3->close();
    }
    $validDays = [];
    $res4 = $conn->query("SELECT id FROM days WHERE is_active = 1");
    if ($res4) {
        while ($row = $res4->fetch_assoc()) { $validDays[(int)$row['id']] = true; }
        $res4->close();
    }
    $validTimeSlots = [];
    $res5 = $conn->query("SELECT id FROM time_slots");
    if ($res5) {
        while ($row = $res5->fetch_assoc()) { $validTimeSlots[(int)$row['id']] = true; }
        $res5->close();
    }
    $validRooms = [];
    $res6 = $conn->query("SELECT id FROM rooms WHERE is_active = 1");
    if ($res6) {
        while ($row = $res6->fetch_assoc()) { $validRooms[(int)$row['id']] = true; }
        $res6->close();
    }
    $skipped_invalid_fk = 0;
    
    // Process entries in batches for better performance
    $batchSize = 1000; // Process 1000 entries at a time
    $totalEntries = count($uniqueEntries);
    $processedEntries = 0;
    
    for ($batchStart = 0; $batchStart < $totalEntries; $batchStart += $batchSize) {
        $batchEnd = min($batchStart + $batchSize, $totalEntries);
        $batch = array_slice($uniqueEntries, $batchStart, $batchEnd - $batchStart);
        
        error_log("Processing batch " . ($batchStart / $batchSize + 1) . " of " . ceil($totalEntries / $batchSize) . " (" . count($batch) . " entries)");
        
        // Prepare batch insert data
        $batchValues = [];
        $batchParams = [];
        $batchTypes = '';
        
        foreach ($batch as $entry) {
        // Normalize semester for enum schemas: map numeric to names if needed
        $semesterVal = $entry['semester'] ?? '';
        if (is_numeric($semesterVal)) {
            $semesterVal = ((int)$semesterVal === 1) ? 'first' : (((int)$semesterVal === 2) ? 'second' : (string)$semesterVal);
        } else {
            $sv = strtolower((string)$semesterVal);
            if ($sv === '1' || $sv === 'first' || $sv === 'semester 1') { $semesterVal = 'first'; }
            elseif ($sv === '2' || $sv === 'second' || $sv === 'semester 2') { $semesterVal = 'second'; }
        }
        
        $lecturerCourseId = $entry['lecturer_course_id'] ?? null;
        if (!$lecturerCourseId || !isset($lecturerCourseIds[(int)$lecturerCourseId])) {
            // Attempt to find a valid lecturer_course for this class_course's course
            $classCourseId = (int)$entry['class_course_id'];
            $courseId = $classCourseToCourse[$classCourseId] ?? null;
            if ($courseId !== null && isset($lecturerCourseByCourse[$courseId])) {
                $lecturerCourseId = $lecturerCourseByCourse[$courseId];
            } else {
                // Skip entry to avoid FK violation
                error_log("Skipping entry due to missing lecturer_course_id mapping for class_course_id=$classCourseId (course_id=" . ($courseId ?? 'null') . ")");
                continue;
            }
        }
            
        // Ensure academic_year is set; if missing compute default like "2025/2026"
        $academicYearVal = $entry['academic_year'] ?? null;
        if (empty($academicYearVal)) {
            $m = (int)date('n');
            $y = (int)date('Y');
            if ($m >= 8) {
                $academicYearVal = $y . '/' . ($y + 1);
            } else {
                $academicYearVal = ($y - 1) . '/' . $y;
            }
        }

        // Validate FK presence before attempting insert
        if (!isset($validClassCourses[(int)$entry['class_course_id']]) ||
            !isset($validDays[(int)$entry['day_id']]) ||
            !isset($validTimeSlots[(int)$entry['time_slot_id']]) ||
            !isset($validRooms[(int)$entry['room_id']])) {
            $skipped_invalid_fk++;
            error_log("Skipping entry due to invalid FK(s): " . json_encode($entry));
            continue;
        }
            
        // Prevent duplicate room/day/time slot collisions (uq_timetable_slot)
        $slotKey = $entry['room_id'] . '-' . $entry['day_id'] . '-' . $entry['time_slot_id'] . '-' . $semesterVal . '-' . $academicYearVal . '-lecture';
        if (isset($occupiedSlotKeys[$slotKey])) {
            error_log("Skipping entry due to occupied slot (room/day/time): " . $slotKey . " entry=" . json_encode($entry));
            continue;
        }
            
            // Add to batch
            $batchValues[] = "(" . (int)$entry['class_course_id'] . ", " . (int)$lecturerCourseId . ", " . (int)$entry['day_id'] . ", " . (int)$entry['time_slot_id'] . ", " . (int)$entry['room_id'] . ", '" . $conn->real_escape_string($entry['division_label']) . "', '" . $conn->real_escape_string($semesterVal) . "', '" . $conn->real_escape_string($academicYearVal) . "', '" . $conn->real_escape_string($version) . "')";
            $occupiedSlotKeys[$slotKey] = true;
        }
        
        // Validate batch for lecturer conflicts before insert
        $conflicts = validateBatchForLecturerConflicts($batch, $conn);
        if (!empty($conflicts)) {
            error_log("Batch contains " . count($conflicts) . " lecturer conflicts - proceeding with insertion");
        }
        
        // Execute batch insert if we have valid entries
        if (!empty($batchValues)) {
            $sql = "INSERT INTO timetable (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, division_label, semester, academic_year, version) VALUES " . implode(', ', $batchValues);
            
            if ($conn->query($sql)) {
                $batchInserted = $conn->affected_rows;
                $inserted_count += $batchInserted;
                error_log("Batch inserted $batchInserted entries successfully");
            } else {
                error_log("Batch insert failed: " . $conn->error);
                
                // Check if it's a duplicate key error and handle gracefully
                if (strpos($conn->error, 'Duplicate entry') !== false) {
                    error_log("Duplicate entry detected, skipping batch to prevent constraint violation");
                    continue; // Skip this batch entirely
                }
                
                // Fallback to individual inserts for this batch
                error_log("Falling back to individual inserts for this batch");
                foreach ($batch as $entry) {
                    // Individual insert logic here (simplified version)
                    $semesterVal = $entry['semester'] ?? '';
                    if (is_numeric($semesterVal)) {
                        $semesterVal = ((int)$semesterVal === 1) ? 'first' : (((int)$semesterVal === 2) ? 'second' : (string)$semesterVal);
                    } else {
                        $sv = strtolower((string)$semesterVal);
                        if ($sv === '1' || $sv === 'first' || $sv === 'semester 1') { $semesterVal = 'first'; }
                        elseif ($sv === '2' || $sv === 'second' || $sv === 'semester 2') { $semesterVal = 'second'; }
                    }
                    
                    $lecturerCourseId = $entry['lecturer_course_id'] ?? null;
                    if (!$lecturerCourseId || !isset($lecturerCourseIds[(int)$lecturerCourseId])) {
                        $classCourseId = (int)$entry['class_course_id'];
                        $courseId = $classCourseToCourse[$classCourseId] ?? null;
                        if ($courseId !== null && isset($lecturerCourseByCourse[$courseId])) {
                            $lecturerCourseId = $lecturerCourseByCourse[$courseId];
                        } else {
                            continue;
                        }
                    }
                    
                    $academicYearVal = $entry['academic_year'] ?? null;
                    if (empty($academicYearVal)) {
                        $m = (int)date('n');
                        $y = (int)date('Y');
                        if ($m >= 8) {
                            $academicYearVal = $y . '/' . ($y + 1);
                        } else {
                            $academicYearVal = ($y - 1) . '/' . $y;
                        }
                    }
                    
                    // Note: Lecturer conflicts are now allowed and will be reported for manual review
                    
        $sql = "INSERT INTO timetable (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, division_label, semester, academic_year) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iiiiisss", 
                $entry['class_course_id'],
                $lecturerCourseId,
                $entry['day_id'],
                $entry['time_slot_id'],
                $entry['room_id'],
                $entry['division_label'],
                $semesterVal,
                $academicYearVal
            );
            
            if ($stmt->execute()) {
                $inserted_count++;
            }
            $stmt->close();
                    }
                }
            }
        }
        
        $processedEntries += count($batch);
        error_log("Processed $processedEntries of $totalEntries entries (" . round(($processedEntries / $totalEntries) * 100, 2) . "%)");
        
        // Memory optimization: clear batch data and force garbage collection
        unset($batch, $batchValues, $batchParams);
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Log memory usage every 10 batches
        if (($batchStart / $batchSize) % 10 == 0) {
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            error_log("Memory usage: " . round($memoryUsage / 1024 / 1024, 2) . "MB (Peak: " . round($memoryPeak / 1024 / 1024, 2) . "MB)");
        }
    }
    
    // If we had duplicates, log them
    if (!empty($db_duplicate_keys)) {
        error_log("Database duplicate keys found: " . implode(', ', $db_duplicate_keys));
    }
    
    if ($skipped_invalid_fk > 0) {
        error_log("Skipped entries due to invalid FKs: " . $skipped_invalid_fk);
    }
    error_log("Successfully inserted " . $inserted_count . " out of " . count($uniqueEntries) . " unique entries");
    
    return $inserted_count;
}

/**
 * Validate batch for lecturer conflicts
 */
function validateBatchForLecturerConflicts($batch, $conn) {
    $lecturerSlots = [];
    $conflicts = [];
    $semester = $batch[0]['semester'] ?? 'first';
    $academicYear = $batch[0]['academic_year'] ?? null;
    
    // Set default academic year if not provided
    if (empty($academicYear)) {
        $m = (int)date('n');
        $y = (int)date('Y');
        if ($m >= 8) {
            $academicYear = $y . '/' . ($y + 1);
        } else {
            $academicYear = ($y - 1) . '/' . $y;
        }
    }
    
    // Check for conflicts within the current batch
    foreach ($batch as $entry) {
        if (!$entry['lecturer_course_id']) {
            continue;
        }
        
        // Create lecturer slot key for batch validation
        $lecturerSlotKey = $entry['lecturer_course_id'] . '|' . $entry['day_id'] . '|' . $entry['time_slot_id'];
        
        if (isset($lecturerSlots[$lecturerSlotKey])) {
            $conflicts[] = [
                'lecturer_course_id' => $entry['lecturer_course_id'],
                'day_id' => $entry['day_id'],
                'time_slot_id' => $entry['time_slot_id'],
                'class_course_id' => $entry['class_course_id']
            ];
        } else {
            $lecturerSlots[$lecturerSlotKey] = $entry['class_course_id'];
        }
    }
    
    // Log conflicts but don't throw exceptions - allow generation to continue
    if (!empty($conflicts)) {
        error_log("Lecturer conflicts detected in batch (will be logged for review):");
        foreach ($conflicts as $conflict) {
            error_log("CONFLICT: Lecturer course {$conflict['lecturer_course_id']} has multiple classes at day {$conflict['day_id']}, time slot {$conflict['time_slot_id']} - class_course_id: {$conflict['class_course_id']}");
        }
        
        // Store conflicts in session for later review
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['lecturer_conflicts'])) {
            $_SESSION['lecturer_conflicts'] = [];
        }
        $_SESSION['lecturer_conflicts'] = array_merge($_SESSION['lecturer_conflicts'], $conflicts);
    }
    
    return $conflicts; // Return conflicts for optional handling
}

/**
 * Validate GA solution for duplicate entries
 */
function validateSolutionForDuplicates($solution) {
    $duplicates = [];
    $duplicateEntries = [];
    $seenKeys = [];
    
    if (!isset($solution['individual']) || !is_array($solution['individual'])) {
        return ['duplicates' => [], 'duplicate_entries' => []];
    }
    
    foreach ($solution['individual'] as $geneKey => $gene) {
        $key = $gene['class_course_id'] . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
        
        if (isset($seenKeys[$key])) {
            $duplicates[] = $key;
            $duplicateEntries[] = $gene;
        } else {
            $seenKeys[$key] = true;
        }
    }
    
    return [
        'duplicates' => $duplicates,
        'duplicate_entries' => $duplicateEntries
    ];
}

/**
 * Validate timetable entries for duplicates
 */
function validateTimetableEntries($entries) {
    $duplicates = [];
    $seenKeys = [];
    
    foreach ($entries as $entry) {
        $key = $entry['class_course_id'] . '-' . $entry['day_id'] . '-' . $entry['time_slot_id'] . '-' . $entry['division_label'];
        
        if (isset($seenKeys[$key])) {
            $duplicates[] = $key;
        } else {
            $seenKeys[$key] = true;
        }
    }
    
    return $duplicates;
}

/**
 * Format time to HH:MM AM/PM format for display
 * @param string $time Time string in HH:MM:SS or HH:MM format
 * @return string Formatted time in HH:MM AM/PM
 */
function formatTimeForDisplay($time) {
    if (empty($time)) {
        return '';
    }
    // Remove seconds if present
    $time = substr($time, 0, 5);
    
    // Convert to 12-hour format with AM/PM
    $time_obj = DateTime::createFromFormat('H:i', $time);
    if ($time_obj) {
        return $time_obj->format('g:i A');
    }
    
    return $time; // Fallback to original format
}

/**
 * Format time range for display (start - end)
 * @param string $start_time Start time
 * @param string $end_time End time
 * @return string Formatted time range
 */
function formatTimeRangeForDisplay($start_time, $end_time) {
    if (empty($start_time) || empty($end_time)) {
        return '';
    }
    
    $start_formatted = formatTimeForDisplay($start_time);
    $end_formatted = formatTimeForDisplay($end_time);
    
    return $start_formatted . ' - ' . $end_formatted;
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

/**
 * Schedule unscheduled classes in available slots after GA generation
 * @param mysqli $conn Database connection
 * @param int $stream_id Stream ID
 * @param int $semester Semester (1 or 2)
 * @return int Number of additional classes scheduled
 */
function scheduleUnscheduledClasses($conn, $stream_id, $semester) {
    $additional_scheduled = 0;
    
    try {
        // Get unscheduled class courses for this stream and semester
        $unscheduled_query = "
            SELECT 
                cc.id as class_course_id,
                cc.class_id,
                cc.course_id,
                cc.lecturer_id,
                c.name as class_name,
                co.code as course_code,
                co.name as course_name,
                co.hours_per_week,
                l.name as lecturer_name,
                c.total_capacity as class_size
            FROM class_courses cc
            LEFT JOIN classes c ON cc.class_id = c.id
            LEFT JOIN courses co ON cc.course_id = co.id
            LEFT JOIN lecturers l ON cc.lecturer_id = l.id
            WHERE cc.is_active = 1 
            AND c.stream_id = ?
            AND cc.id NOT IN (
                SELECT DISTINCT t.class_course_id 
                FROM timetable t 
                WHERE t.class_course_id IS NOT NULL
            )
            ORDER BY 
                CASE 
                    WHEN cc.lecturer_id IS NOT NULL THEN 1  -- Prioritize courses with assigned lecturers
                    ELSE 2 
                END,
                c.total_capacity DESC,  -- Prioritize larger classes
                co.code ASC
        ";
        
        $stmt = $conn->prepare($unscheduled_query);
        $stmt->bind_param("i", $stream_id);
        $stmt->execute();
        $unscheduled_result = $stmt->get_result();
        $unscheduled_classes = $unscheduled_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($unscheduled_classes)) {
            return 0; // No unscheduled classes
        }
        
        // Get available time slots and rooms for this stream
        $slots_query = "
            SELECT DISTINCT d.id as day_id, ts.id as time_slot_id, ts.start_time, ts.end_time, ts.is_break
            FROM days d
            CROSS JOIN stream_time_slots sts
            JOIN time_slots ts ON sts.time_slot_id = ts.id
            WHERE sts.stream_id = ? AND sts.is_active = 1
            ORDER BY d.id, ts.start_time
        ";
        
        $stmt = $conn->prepare($slots_query);
        $stmt->bind_param("i", $stream_id);
        $stmt->execute();
        $slots_result = $stmt->get_result();
        $available_slots = $slots_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get available rooms with capacity and type information
        $rooms_query = "
            SELECT r.id, r.name, r.capacity, rt.name as room_type
            FROM rooms r
            LEFT JOIN room_types rt ON r.room_type_id = rt.id
            WHERE r.is_active = 1 
            ORDER BY r.capacity DESC, r.name ASC
        ";
        $rooms_result = $conn->query($rooms_query);
        $available_rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);
        
        if (empty($available_slots) || empty($available_rooms)) {
            return 0; // No available slots or rooms
        }
        
        // Get course-room type preferences
        $room_preferences_query = "
            SELECT course_id, room_type
            FROM course_room_types 
            WHERE is_active = 1
        ";
        $prefs_result = $conn->query($room_preferences_query);
        $room_preferences = [];
        while ($pref = $prefs_result->fetch_assoc()) {
            $room_preferences[$pref['course_id']] = $pref['room_type'];
        }
        
        // Get currently scheduled entries to check for conflicts
        $scheduled_query = "
            SELECT t.day_id, t.time_slot_id, t.room_id, t.class_course_id, t.lecturer_course_id,
                   cc.class_id, cc.course_id, lc.lecturer_id
            FROM timetable t
            JOIN class_courses cc ON t.class_course_id = cc.id
            JOIN classes c ON cc.class_id = c.id
            LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
            WHERE c.stream_id = ? AND t.semester = ?
        ";
        
        $stmt = $conn->prepare($scheduled_query);
        $stmt->bind_param("ii", $stream_id, $semester);
        $stmt->execute();
        $scheduled_result = $stmt->get_result();
        $scheduled_entries = $scheduled_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Create comprehensive conflict maps
        $room_conflicts = [];
        $class_conflicts = [];
        $lecturer_conflicts = [];
        $room_usage = []; // Track room usage for optimization
        
        foreach ($scheduled_entries as $entry) {
            $room_key = $entry['day_id'] . '|' . $entry['time_slot_id'] . '|' . $entry['room_id'];
            $room_conflicts[$room_key] = true;
            
            $class_key = $entry['day_id'] . '|' . $entry['time_slot_id'] . '|' . $entry['class_id'];
            $class_conflicts[$class_key] = true;
            
            if ($entry['lecturer_id']) {
                $lecturer_key = $entry['day_id'] . '|' . $entry['time_slot_id'] . '|' . $entry['lecturer_id'];
                $lecturer_conflicts[$lecturer_key] = true;
            }
            
            // Track room usage for load balancing
            $room_usage[$entry['room_id']] = ($room_usage[$entry['room_id']] ?? 0) + 1;
        }
        
        // Try to schedule each unscheduled class
        foreach ($unscheduled_classes as $class_course) {
            $scheduled = false;
            
            // Get lecturer course for this class course
            $lecturer_course_id = null;
            $lecturer_id = null;
            
            if ($class_course['lecturer_id']) {
                $lec_query = "SELECT id FROM lecturer_courses WHERE course_id = ? AND lecturer_id = ? AND is_active = 1 LIMIT 1";
                $lec_stmt = $conn->prepare($lec_query);
                $lec_stmt->bind_param("ii", $class_course['course_id'], $class_course['lecturer_id']);
                $lec_stmt->execute();
                $lec_result = $lec_stmt->get_result();
                if ($lec_row = $lec_result->fetch_assoc()) {
                    $lecturer_course_id = $lec_row['id'];
                    $lecturer_id = $class_course['lecturer_id'];
                }
                $lec_stmt->close();
            } else {
                // Try to find any lecturer for this course
                $lec_query = "SELECT id, lecturer_id FROM lecturer_courses WHERE course_id = ? AND is_active = 1 LIMIT 1";
                $lec_stmt = $conn->prepare($lec_query);
                $lec_stmt->bind_param("i", $class_course['course_id']);
                $lec_stmt->execute();
                $lec_result = $lec_stmt->get_result();
                if ($lec_row = $lec_result->fetch_assoc()) {
                    $lecturer_course_id = $lec_row['id'];
                    $lecturer_id = $lec_row['lecturer_id'];
                }
                $lec_stmt->close();
            }
            
            // Filter rooms by preference and capacity
            $suitable_rooms = $available_rooms;
            
            // Apply room type preference if available
            if (isset($room_preferences[$class_course['course_id']])) {
                $preferred_type = $room_preferences[$class_course['course_id']];
                $filtered_rooms = array_filter($suitable_rooms, function($room) use ($preferred_type) {
                    return strtolower($room['room_type'] ?? '') === strtolower($preferred_type);
                });
                if (!empty($filtered_rooms)) {
                    $suitable_rooms = array_values($filtered_rooms);
                }
            }
            
            // Filter by capacity
            $class_size = (int)$class_course['class_size'];
            if ($class_size > 0) {
                $suitable_rooms = array_filter($suitable_rooms, function($room) use ($class_size) {
                    return (int)$room['capacity'] >= $class_size;
                });
            }
            
            // Sort rooms by capacity (ascending) and usage (ascending) for load balancing
            usort($suitable_rooms, function($a, $b) use ($room_usage) {
                $usage_a = $room_usage[$a['id']] ?? 0;
                $usage_b = $room_usage[$b['id']] ?? 0;
                
                if ($usage_a === $usage_b) {
                    return $a['capacity'] - $b['capacity']; // Prefer smaller rooms if usage is equal
                }
                return $usage_a - $usage_b; // Prefer less used rooms
            });
            
            // Try each available slot
            foreach ($available_slots as $slot) {
                // Skip break slots
                if (isset($slot['is_break']) && $slot['is_break']) {
                    continue;
                }
                
                // Try each suitable room
                foreach ($suitable_rooms as $room) {
                    $room_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $room['id'];
                    $class_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $class_course['class_id'];
                    
                    // Check for conflicts
                    if (isset($room_conflicts[$room_key]) || isset($class_conflicts[$class_key])) {
                        continue;
                    }
                    
                    // Check lecturer conflict if we have a lecturer
                    if ($lecturer_id) {
                        $lecturer_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $lecturer_id;
                        if (isset($lecturer_conflicts[$lecturer_key])) {
                            continue;
                        }
                    }
                    
                    // Additional constraint: Check if lecturer has too many classes on the same day
                    if ($lecturer_id) {
                        $daily_load_query = "
                            SELECT COUNT(*) as daily_count
                            FROM timetable t
                            JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                            WHERE lc.lecturer_id = ? AND t.day_id = ? AND t.semester = ?
                        ";
                        $load_stmt = $conn->prepare($daily_load_query);
                        $load_stmt->bind_param("iii", $lecturer_id, $slot['day_id'], $semester);
                        $load_stmt->execute();
                        $load_result = $load_stmt->get_result();
                        $daily_count = $load_result->fetch_assoc()['daily_count'];
                        $load_stmt->close();
                        
                        // Limit lecturer to maximum 4 classes per day
                        if ($daily_count >= 4) {
                            continue;
                        }
                    }
                    
                    // Additional constraint: Check if class has too many classes on the same day
                    $class_daily_load_query = "
                        SELECT COUNT(*) as daily_count
                        FROM timetable t
                        JOIN class_courses cc ON t.class_course_id = cc.id
                        WHERE cc.class_id = ? AND t.day_id = ? AND t.semester = ?
                    ";
                    $class_load_stmt = $conn->prepare($class_daily_load_query);
                    $class_load_stmt->bind_param("iii", $class_course['class_id'], $slot['day_id'], $semester);
                    $class_load_stmt->execute();
                    $class_load_result = $class_load_stmt->get_result();
                    $class_daily_count = $class_load_result->fetch_assoc()['daily_count'];
                    $class_load_stmt->close();
                    
                    // Limit class to maximum 3 classes per day
                    if ($class_daily_count >= 3) {
                        continue;
                    }
                    
                    // Schedule this class
                    $insert_query = "
                        INSERT INTO timetable (
                            class_course_id, lecturer_course_id, day_id, time_slot_id, 
                            room_id, division_label, semester, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, '', ?, NOW(), NOW())
                    ";
                    
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bind_param("iiiiii", 
                        $class_course['class_course_id'],
                        $lecturer_course_id,
                        $slot['day_id'],
                        $slot['time_slot_id'],
                        $room['id'],
                        $semester
                    );
                    
                    if ($insert_stmt->execute()) {
                        $additional_scheduled++;
                        $scheduled = true;
                        
                        // Update conflict maps
                        $room_conflicts[$room_key] = true;
                        $class_conflicts[$class_key] = true;
                        if ($lecturer_id) {
                            $lecturer_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $lecturer_id;
                            $lecturer_conflicts[$lecturer_key] = true;
                        }
                        
                        // Update room usage for load balancing
                        $room_usage[$room['id']] = ($room_usage[$room['id']] ?? 0) + 1;
                        
                        error_log("Successfully scheduled unscheduled class: {$class_course['class_name']} - {$class_course['course_code']} in room {$room['name']} at slot {$slot['start_time']}-{$slot['end_time']}");
                        break;
                    }
                    $insert_stmt->close();
                }
                
                if ($scheduled) {
                    break;
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error in scheduleUnscheduledClasses: " . $e->getMessage());
    }
    
    return $additional_scheduled;
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
    error_log("DEBUG: Current stream ID: $current_stream_id, Timetable entries for this stream: $total_timetable_entries");
} else {
    // Fallback to global count
    $total_timetable_entries = $conn->query("SELECT COUNT(*) as count FROM timetable")->fetch_assoc()['count'];
    error_log("DEBUG: No current stream ID, using global count: $total_timetable_entries");
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
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1055;">
        <div id="successToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-success text-white">
                <i class="fas fa-check-circle me-2"></i>
                <strong class="me-auto">Success</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="successToastBody">
                <!-- Toast message will be inserted here -->
            </div>
        </div>
        <div id="errorToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-danger text-white">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong class="me-auto">Error</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="errorToastBody">
                <!-- Toast message will be inserted here -->
            </div>
        </div>
    </div>
    
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4>
                <i class="fas fa-calendar-alt me-2"></i>
                <?php if ($edit_mode): ?>
                    Edit Timetable
                    <?php 
                    // Get stream name for display
                    $stream_name = '';
                    if ($edit_stream_id) {
                        $stream_stmt = $conn->prepare("SELECT name FROM streams WHERE id = ?");
                        $stream_stmt->bind_param("i", $edit_stream_id);
                        $stream_stmt->execute();
                        $stream_result = $stream_stmt->get_result();
                        if ($stream_row = $stream_result->fetch_assoc()) {
                            $stream_name = $stream_row['name'];
                        }
                        $stream_stmt->close();
                    }
                    ?>
                    <span class="badge bg-primary ms-2"><?php echo htmlspecialchars($stream_name); ?></span>
                <?php else: ?>
                    Generate Timetable
                <?php endif; ?>
            </h4>
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

        <?php if ($edit_mode): ?>
        <!-- Edit Mode Message -->
        <div class="row m-3">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Edit Mode:</strong> You are currently editing the timetable for <strong><?php echo htmlspecialchars($stream_name); ?></strong>. 
                    Click on any timetable cell to edit the course schedule. Changes are saved automatically.
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Generation Form (only show in generate mode) -->
        <div class="row m-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Generate New Timetable</h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">This will generate a completely new timetable version. Previous versions will be preserved.</p>

                        <form method="POST">
                            <input type="hidden" name="action" value="generate_lecture_timetable">
                            
                            <div class="d-flex flex-column gap-3">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Semester</label>
                                        <select name="semester" class="form-select" required>
                                            <option value="">Select Semester</option>
                                            <option value="first" <?php echo $current_semester == 1 ? 'selected' : ''; ?>>First Semester</option>
                                            <option value="second" <?php echo $current_semester == 2 ? 'selected' : ''; ?>>Second Semester</option>
                                    </select>
                                </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Version Naming</label>
                                        <div class="form-control-plaintext">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Creates new version: <strong><?php echo htmlspecialchars(getStreamName($conn, $current_stream_id)); ?> DRAFT {1, 2, 3...}</strong>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2 align-items-center">
                                    <button type="submit" class="btn btn-primary btn-lg" id="generate-btn">
                                        <i class="fas fa-magic me-2"></i>Generate New Timetable
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
                        <h6 class="mb-0">Timetable Versions</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get existing versions for this stream and semester
                        $versions_query = "
                            SELECT DISTINCT t.version, COUNT(*) as entry_count, MAX(t.created_at) as last_updated
                            FROM timetable t
                            JOIN class_courses cc ON t.class_course_id = cc.id
                            JOIN classes c ON cc.class_id = c.id
                            WHERE c.stream_id = ? AND t.semester = ?
                            GROUP BY t.version
                            ORDER BY MAX(t.created_at) DESC
                        ";
                        
                        $versions_stmt = $conn->prepare($versions_query);
                        $versions_stmt->bind_param("ii", $current_stream_id, $current_semester);
                        $versions_stmt->execute();
                        $versions_result = $versions_stmt->get_result();
                        $existing_versions = $versions_result->fetch_all(MYSQLI_ASSOC);
                        $versions_stmt->close();
                        ?>
                        
                        <?php if (!empty($existing_versions)): ?>
                        <div class="mb-3">
                            <h6>Existing Versions:</h6>
                            <div class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 0.375rem;">
                                <?php foreach ($existing_versions as $version_info): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center" style="padding-left: 15px;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($version_info['version']); ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo $version_info['entry_count']; ?> entries
                                            <br>
                                            Updated: <?php echo date('M j, Y g:i A', strtotime($version_info['last_updated'])); ?>
                                        </small>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary btn-sm" onclick="loadVersion('<?php echo htmlspecialchars($version_info['version']); ?>')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="deleteVersion('<?php echo htmlspecialchars($version_info['version']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="text-muted">
                            <i class="fas fa-info-circle me-2"></i>
                            No timetable versions found for this stream and semester.
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <button class="btn btn-outline-info btn-sm w-100" onclick="refreshVersions()">
                                <i class="fas fa-sync me-2"></i>Refresh Versions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row m-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="class_courses.php" class="btn btn-outline-primary"><i class="fas fa-link me-2"></i>Manage Assignments</a>
                            <a href="extract_timetable.php" class="btn btn-success"><i class="fas fa-eye me-2"></i>View Timetable</a>
                            <a href="saved_timetable.php" class="btn btn-outline-info"><i class="fas fa-list me-2"></i>Saved Timetables</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
        
        // Helper function to validate stream active days
        function validateStreamDays($conn, $streamId) {
            $streamSql = "SELECT active_days FROM streams WHERE id = ?";
            $stmt = $conn->prepare($streamSql);
            $stmt->bind_param("i", $streamId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($streamRow = $result->fetch_assoc()) {
                $activeDaysJson = $streamRow['active_days'];
                if (!empty($activeDaysJson)) {
                    $activeDaysArray = json_decode($activeDaysJson, true);
                    if (is_array($activeDaysArray) && count($activeDaysArray) > 0) {
                        // Validate each day name exists in database
                        $validDays = [];
                        foreach ($activeDaysArray as $dayName) {
                            $checkSql = "SELECT id FROM days WHERE name = ? AND is_active = 1";
                            $checkStmt = $conn->prepare($checkSql);
                            $checkStmt->bind_param("s", $dayName);
                            $checkStmt->execute();
                            if ($checkStmt->get_result()->num_rows > 0) {
                                $validDays[] = $dayName;
                            }
                            $checkStmt->close();
                        }
                        $stmt->close();
                        return $validDays;
                    }
                }
            }
            $stmt->close();
            return [];
        }

        // Get readiness conditions for pre-generation
        $total_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE is_active = 1")->fetch_assoc()['count'];
        
        // Get days count - consider stream-specific days if available
        $total_days = 0;
        $stream_days_used = false;
        if (!empty($current_stream_id)) {
            // Validate and get stream-specific active days
            $valid_stream_days = validateStreamDays($conn, $current_stream_id);
            if (!empty($valid_stream_days)) {
                // Count stream-specific active days using prepared statement
                $placeholders = str_repeat('?,', count($valid_stream_days) - 1) . '?';
                $stream_days_sql = "SELECT COUNT(*) as count FROM days WHERE is_active = 1 AND name IN ($placeholders)";
                $stmt = $conn->prepare($stream_days_sql);
                $stmt->bind_param(str_repeat('s', count($valid_stream_days)), ...$valid_stream_days);
                $stmt->execute();
                $total_days = $stmt->get_result()->fetch_assoc()['count'];
                $stmt->close();
                $stream_days_used = true;
                error_log("Stream ID $current_stream_id using specific days: " . implode(', ', $valid_stream_days) . " (count: $total_days)");
            } else {
                error_log("Stream ID $current_stream_id has no valid active days, falling back to all days");
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
                            
                            // Try to get duration from existing time slots, default to 1 hour
                            $slot_duration = 60; // Default 60 minutes
                            
                            // Check if there's a matching time slot in the database
                            $existing_slot_sql = "SELECT duration, is_break FROM time_slots WHERE start_time = ? LIMIT 1";
                            $existing_slot_stmt = $conn->prepare($existing_slot_sql);
                            $existing_slot_stmt->bind_param('s', $slot_start);
                            $existing_slot_stmt->execute();
                            $existing_slot_result = $existing_slot_stmt->get_result();
                            
                            $is_break_from_db = false;
                            if ($existing_slot_row = $existing_slot_result->fetch_assoc()) {
                                $slot_duration = (int)$existing_slot_row['duration'];
                                $is_break_from_db = (bool)$existing_slot_row['is_break'];
                            }
                            $existing_slot_stmt->close();
                            
                            $current_time->add(new DateInterval('PT' . $slot_duration . 'M')); // Add duration minutes
                            $slot_end = $current_time->format('H:i');
                            
                            // Don't count a slot that goes beyond the period end
                            if ($current_time > $end_time) {
                                $slot_end = $end_time->format('H:i');
                            }
                            
                            // Check if this slot overlaps with break time OR is marked as break in database
                            $is_break = $is_break_from_db;
                            
                            if (!$is_break_from_db && !empty($break_start) && !empty($break_end)) {
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

        <!-- Version Viewing/Editing Section -->
        <?php if ($edit_mode && $view_version): ?>
        <div class="row m-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="fas fa-eye me-2"></i>Viewing Timetable Version: <strong><?php echo htmlspecialchars($view_version); ?></strong>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Stream:</strong> <?php echo htmlspecialchars(getStreamName($conn, $current_stream_id)); ?></p>
                                <p><strong>Semester:</strong> <?php echo htmlspecialchars($view_semester ?: 'Second'); ?></p>
                                <p><strong>Version:</strong> <?php echo htmlspecialchars($view_version); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Actions:</strong></p>
                                <div class="btn-group">
                                    <a href="manual_timetable_editor.php?stream_id=<?php echo $current_stream_id; ?>&version=<?php echo urlencode($view_version); ?>&semester=<?php echo urlencode($view_semester); ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-edit me-1"></i>Manual Edit
                                    </a>
                                    <a href="lecturer_conflicts.php?version=<?php echo urlencode($view_version); ?>&semester=<?php echo urlencode($view_semester); ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-exclamation-triangle me-1"></i>Review Conflicts
                                    </a>
                                    <a href="saved_timetable.php" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-arrow-left me-1"></i>Back to Versions
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (true): ?>
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
        // If exams weeks were set by POST earlier, use that to repeat skeletons
        $exam_weeks_to_render = isset($generated_exam_weeks) ? intval($generated_exam_weeks) : 0;
        
        // Get days for this stream
        if (!empty($current_stream_id)) {
            // Validate and get stream-specific active days
            $valid_stream_days = validateStreamDays($conn, $current_stream_id);
            if (!empty($valid_stream_days)) {
                // Get stream-specific days using prepared statement
                $placeholders = str_repeat('?,', count($valid_stream_days) - 1) . '?';
                $stream_days_sql = "SELECT id, name FROM days WHERE is_active = 1 AND name IN ($placeholders) ORDER BY id";
                $stmt = $conn->prepare($stream_days_sql);
                $stmt->bind_param(str_repeat('s', count($valid_stream_days)), ...$valid_stream_days);
                $stmt->execute();
                $template_days_result = $stmt->get_result();
                while ($day = $template_days_result->fetch_assoc()) {
                    $template_days[] = $day;
                }
                $stmt->close();
                error_log("Template generation using stream-specific days for stream $current_stream_id: " . implode(', ', $valid_stream_days));
            } else {
                error_log("Template generation falling back to all days for stream $current_stream_id");
            }
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
                $ts_rs = $conn->query("SELECT ts.id, ts.start_time, ts.end_time, ts.duration, ts.is_break FROM stream_time_slots sts JOIN time_slots ts ON ts.id = sts.time_slot_id WHERE sts.stream_id = " . intval($current_stream_id) . " AND sts.is_active = 1 ORDER BY ts.start_time");
                if ($ts_rs && $ts_rs->num_rows > 0) {
                    while ($slot = $ts_rs->fetch_assoc()) {
                        // Ensure is_break is properly set from database
                        $slot['is_break'] = (bool)$slot['is_break'];
                        $slot['break_type'] = $slot['is_break'] ? 'break' : '';
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
                            
                            // Try to get duration from existing time slots, default to 1 hour
                            $slot_duration = 60; // Default 60 minutes
                            
                            // Check if there's a matching time slot in the database
                            $existing_slot_sql = "SELECT duration, is_break FROM time_slots WHERE start_time = ? LIMIT 1";
                            $existing_slot_stmt = $conn->prepare($existing_slot_sql);
                            $existing_slot_stmt->bind_param('s', $slot_start);
                            $existing_slot_stmt->execute();
                            $existing_slot_result = $existing_slot_stmt->get_result();
                            
                            $is_break_from_db = false;
                            if ($existing_slot_row = $existing_slot_result->fetch_assoc()) {
                                $slot_duration = (int)$existing_slot_row['duration'];
                                $is_break_from_db = (bool)$existing_slot_row['is_break'];
                            }
                            $existing_slot_stmt->close();
                            
                            $current_time->add(new DateInterval('PT' . $slot_duration . 'M')); // Add duration minutes
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
                                'break_type' => $is_break ? 'break' : ''
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
                
                // Try to get duration from existing time slots, default to 1 hour
                $slot_duration = 60; // Default 60 minutes
                
                // Check if there's a matching time slot in the database
                $existing_slot_sql = "SELECT duration, is_break FROM time_slots WHERE start_time = ? LIMIT 1";
                $existing_slot_stmt = $conn->prepare($existing_slot_sql);
                $existing_slot_stmt->bind_param('s', $start_time);
                $existing_slot_stmt->execute();
                $existing_slot_result = $existing_slot_stmt->get_result();
                
                $is_break_from_db = false; // Default value
                if ($existing_slot_row = $existing_slot_result->fetch_assoc()) {
                    $slot_duration = (int)$existing_slot_row['duration'];
                    $is_break_from_db = (bool)$existing_slot_row['is_break'];
                }
                $existing_slot_stmt->close();
                
                // Calculate end time based on duration
                $start_time_obj = new DateTime($start_time);
                $start_time_obj->add(new DateInterval('PT' . $slot_duration . 'M'));
                $end_time = $start_time_obj->format('H:i');
                
                // Check if this slot is marked as break in database, or define default break periods
                $is_break = $is_break_from_db;
                $break_type = '';
                
                if ($is_break_from_db) {
                    $break_type = 'break';
                } elseif ($hour == 12) {
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

        // If exam generation was requested, proceed to generate exam timetable entries
        if (!empty($exam_weeks_to_render)) {
            // Load active class_course records grouped by program and course
            $class_courses_sql = "SELECT cc.id AS cc_id, cc.class_id, cc.course_id, cc.lecturer_id, c.name AS class_name, c.program_id, co.code AS course_code, co.name AS course_name
                                   FROM class_courses cc
                                   JOIN classes c ON cc.class_id = c.id
                                   JOIN courses co ON cc.course_id = co.id
                                   WHERE cc.is_active = 1
                                   ORDER BY c.program_id, co.code";
            $cc_rs = $conn->query($class_courses_sql);
            // Group by course: all classes offering the same course must sit the exam together
            $groups = [];
            if ($cc_rs) {
                while ($r = $cc_rs->fetch_assoc()) {
                    $course = $r['course_id'];
                    if (!isset($groups[$course])) $groups[$course] = [];

                    // Expand class divisions: create one item per division so each division is scheduled separately
                    $divisionsCount = max(1, (int)($r['divisions_count'] ?? 1));
                    for ($di = 0; $di < $divisionsCount; $di++) {
                        $label = '';
                        $n = $di;
                        while (true) {
                            $label = chr(65 + ($n % 26)) . $label;
                            $n = intdiv($n, 26) - 1;
                            if ($n < 0) break;
                        }

                        $copy = $r;
                        $copy['division_label'] = $label;
                        // keep cc_id as original class_course id (used later when inserting timetable rows)
                        $groups[$course][] = $copy;
                    }
                }
            }

            // Map course_id -> an available lecturer_course id(s) to satisfy FK
            $lect_map = [];
            $lec_rs = $conn->query("SELECT id, course_id, lecturer_id FROM lecturer_courses WHERE is_active = 1");
            if ($lec_rs) {
                while ($lr = $lec_rs->fetch_assoc()) {
                    $cid = $lr['course_id'];
                    $lid = $lr['id'];
                    $lect_map[$cid][] = ['id' => $lid, 'lecturer_id' => $lr['lecturer_id']];
                }
            }

            // Build available time slots across weeks (skip break slots)
            $slots = [];
            for ($wk = 1; $wk <= $exam_weeks_to_render; $wk++) {
                foreach ($template_days as $day) {
                    foreach ($template_time_slots as $ts) {
                        if (isset($ts['is_break']) && $ts['is_break']) continue;
                        $slots[] = [
                            'week' => $wk,
                            'day_id' => $day['id'],
                            'time_slot_id' => $ts['id']
                        ];
                    }
                }
            }

            $room_ids = array_map(function($r){ return $r['id']; }, $template_rooms);
            $room_ids = array_values($room_ids);

            $roomOccupied = []; // slot_index => array of room ids used
            $classOccupied = []; // class_id => slot_index
            $exam_entries = [];
            $unscheduled_groups = [];

            // Greedy scheduling: for each course, find first slot where none of the classes have conflicts and enough rooms available
            foreach ($groups as $courseId => $items) {
                // items are class_course rows for this course across all classes/programs
                $scheduled = false;
                $needed_rooms = count($items);
                for ($sidx = 0; $sidx < count($slots); $sidx++) {
                    $slot = $slots[$sidx];
                    $used_rooms = $roomOccupied[$sidx] ?? [];

                    // check class conflicts: no class should already have an exam in this same slot
                    $conflict = false;
                    foreach ($items as $it) {
                        if (isset($classOccupied[$it['class_id']]) && $classOccupied[$it['class_id']] == $sidx) { $conflict = true; break; }
                    }
                    if ($conflict) continue;

                    // check room availability
                    $available_rooms = array_values(array_diff($room_ids, $used_rooms));
                    if (count($available_rooms) < $needed_rooms) continue;

                    // assign each class to a room in this slot
                    for ($i = 0; $i < $needed_rooms; $i++) {
                        $room_id = $available_rooms[$i];
                        $it = $items[$i];
                        $lec_id = null;
                        if (isset($lect_map[$it['course_id']]) && count($lect_map[$it['course_id']]) > 0) {
                            // Prefer a lecturer_course with matching lecturer_id if class has lecturer_id
                            if (!empty($it['lecturer_id'])) {
                                foreach ($lect_map[$it['course_id']] as $lc) {
                                    if ($lc['lecturer_id'] == $it['lecturer_id']) { $lec_id = $lc['id']; break; }
                                }
                            }
                            // fallback to first available
                            if ($lec_id === null) $lec_id = $lect_map[$it['course_id']][0]['id'];
                        }
                        $exam_entries[] = [
                            'class_course_id' => $it['cc_id'],
                            'lecturer_course_id' => $lec_id,
                            'day_id' => $slot['day_id'],
                            'time_slot_id' => $slot['time_slot_id'],
                            'room_id' => $room_id,
                            'division_label' => '',
                            'semester' => $current_semester
                        ];
                        $used_rooms[] = $room_id;
                        $classOccupied[$it['class_id']] = $sidx;
                    }

                    $roomOccupied[$sidx] = $used_rooms;
                    $scheduled = true;
                    break;
                }

                if (!$scheduled) {
                    $unscheduled_groups[] = ['course_id' => $courseId];
                }
            }

            // Insert generated exam entries into database
            if (!empty($exam_entries)) {
                $inserted = insertTimetableEntries($conn, $exam_entries);
                if ($inserted > 0) {
                    $success_message = "Generated exams timetable: $inserted entries created for $exam_weeks_to_render week(s).";
                } else {
                    $error_message = 'Exam generation completed but no entries were inserted (possible duplicates or DB constraint).';
                }
            }

            if (!empty($unscheduled_groups)) {
                error_log('Some exam groups could not be scheduled due to insufficient slots/rooms: ' . json_encode($unscheduled_groups));
                if (empty($error_message)) $error_message = 'Some exams could not be scheduled automatically; check available rooms/time slots.';
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
                    s.id as stream_id,
                    (SELECT COUNT(*) FROM lecturer_courses lc2 WHERE lc2.course_id = co.id AND lc2.is_active = 1) as lecturer_count
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

            // Process entries directly - no more spanning logic needed with flexible duration slots
            while ($row = $timetable_result->fetch_assoc()) {
                $day_id = $row['day_id'];
                $time_slot_id = $row['time_slot_id'];
                $room_id = $row['room_id'];
                
                // Determine lecturer display based on lecturer count
                $lecturer_display = '';
                if ($row['lecturer_count'] > 1) {
                    $lecturer_display = 'Lecturer: multiple lecturers';
                } elseif ($row['lecturer_name']) {
                    $lecturer_display = $row['lecturer_name'];
                } else {
                    $lecturer_display = 'Not assigned';
                }

                // Store the entry directly in its slot (no spanning needed)
                $timetable_data[$day_id][$time_slot_id][$room_id] = [
                    'id' => $row['id'],
                    'class_name' => $row['class_name'],
                    'course_code' => $row['course_code'],
                    'course_name' => $row['course_name'],
                    'lecturer_name' => $lecturer_display,
                    'lecturer_count' => $row['lecturer_count'],
                    'course_id' => $row['course_id'],
                    'division_label' => $row['division_label'],
                    'hours_per_week' => $row['hours_per_week'] ?? 1,
                    'day_id' => $day_id,
                    'time_slot_id' => $time_slot_id,
                    'room_id' => $room_id,
                    'is_spanned' => false, // No spanning with flexible duration slots
                    'span_count' => 1 // Always 1 since courses fit in single slots
                ];
            }
            $timetable_stmt->close();
        }
        ?>

        <!-- Timetable Template Preview -->
        <?php if (!empty($template_days) && !empty($template_time_slots) && !empty($template_rooms)): ?>
        <?php
            $weeks_to_loop = $exam_weeks_to_render > 0 ? $exam_weeks_to_render : 1;
            for ($w = 1; $w <= $weeks_to_loop; $w++):
        ?>
        <div class="row m-3">
            <div class="col-12">
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            <?php if ($edit_mode): ?>
                                Editable Timetable
                            <?php else: ?>
                                Timetable Preview
                            <?php endif; ?>
                            <?php if ($weeks_to_loop > 1): ?>
                                <span class="badge bg-secondary ms-2">Week <?php echo $w; ?> / <?php echo $weeks_to_loop; ?></span>
                            <?php endif; ?>
                            <?php if (!empty($timetable_data)): ?>
                                <?php if ($edit_mode): ?>
                                    <span class="badge bg-warning ms-2">Edit Mode</span>
                                <?php else: ?>
                                    <span class="badge bg-success ms-2">Generated Data</span>
                                <?php endif; ?>
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
                        <?php if ($edit_mode): ?>
                            <div>
                                <a href="saved_timetable.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Saved Timetables
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        
                        <div class="mb-3">
                            <p class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <?php if ($edit_mode): ?>
                                    <?php if (!empty($timetable_data)): ?>
                                        You are editing the existing timetable for <strong><?php echo htmlspecialchars($stream_name); ?></strong>. 
                                        Blue cells contain scheduled courses with class, course code, and lecturer details.
                                        <strong>Click on any course block to edit the day, time slot, or room assignment.</strong>
                                        Changes are saved automatically when you submit the edit form.
                                    <?php else: ?>
                                        No timetable data found for this stream. Please generate a timetable first.
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (!empty($timetable_data)): ?>
                                        This timetable shows the actual generated data for the selected stream and semester. 
                                        <strong>Blue cells contain scheduled courses</strong> with class, course code, and lecturer details.
                                        <strong>Click on any course block to edit</strong> the day, time slot, or room assignment.
                                        Course duration is fixed and cannot be changed. Courses will span multiple periods based on their duration (e.g., 3-hour courses span 3 periods).
                                        
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                <strong>Version:</strong> <?php echo htmlspecialchars($current_version); ?> | 
                                                <strong>Total Courses:</strong> <?php echo count($timetable_data); ?> | 
                                                <strong>Last Updated:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        This template shows the structure that will be used for timetable generation. 
                                        The cells will be filled with class-course assignments when you generate the timetable.
                                    <?php endif; ?>
                                <?php endif; ?>
                            </p>
                            <!-- Enhanced Debug information -->
                            <div class="alert alert-light border" style="font-size: 0.85em;">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-cog me-1"></i>System Info:</strong><br>
                                        <small class="text-muted">
                                            Stream ID: <?php echo htmlspecialchars($current_stream_id); ?> | 
                                            Semester: <?php echo htmlspecialchars($current_semester); ?> | 
                                            Version: <?php echo htmlspecialchars($current_version); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-chart-bar me-1"></i>Data Stats:</strong><br>
                                        <small class="text-muted">
                                            Total Entries: <?php echo is_array($timetable_data) ? count($timetable_data) : 0; ?> | 
                                            Days Active: <?php echo count(array_unique(array_column($timetable_data, 'day_id'))); ?> | 
                                            Rooms Used: <?php echo count(array_unique(array_column($timetable_data, 'room_id'))); ?>
                                        </small>
                                    </div>
                                </div>
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
                                                    <th class="break-period-header text-center" colspan="<?php echo $break_duration; ?>" style="min-width: <?php echo $break_duration * 120; ?>px;">
                                                        <div class="break-info">
                                                            <span class="break-label">Break Period</span>
                                                            <div class="break-time">
                                                                <?php echo htmlspecialchars(formatTimeRangeForDisplay($time_slot['start_time'], $time_slot['end_time'])); ?>
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
                                                <th class="time-period-header text-center" style="min-width: 120px;">
                                                    <div class="period-time">
                                                        <?php echo htmlspecialchars(formatTimeRangeForDisplay($time_slot['start_time'], $time_slot['end_time'])); ?>
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
                                                                    <i class="fas fa-coffee text-muted mb-1"></i>
                                                                    <small class="d-block text-muted fw-bold">Break</small>
                                                                    <small class="d-block text-muted" style="font-size: 0.7em;">
                                                                        <?php echo htmlspecialchars(formatTimeRangeForDisplay($time_slot['start_time'], $time_slot['end_time'])); ?>
                                                                    </small>
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
                                                        
                                                        // Check if this cell has an entry
                                                        $entry = null;
                                                        
                                                        if (isset($timetable_data[$day_id][$time_slot_id][$room_id])) {
                                                            $entry = $timetable_data[$day_id][$time_slot_id][$room_id];
                                                        }
                                                        ?>
                                                        <td class="template-cell" 
                                                            data-period="<?php echo htmlspecialchars(formatTimeForDisplay($time_slot['start_time'])); ?>" 
                                                            data-room="<?php echo htmlspecialchars($room['name']); ?>" 
                                                            data-day="<?php echo htmlspecialchars($day['name']); ?>">
                                                            <?php 
                                                            if ($entry) {
                                                                ?>
                                                                <div class="course-block editable-course" 
                                                                     style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 2px solid #2196f3; border-radius: 6px; padding: 6px; margin: 2px; height: 100%; display: flex; flex-direction: column; justify-content: center; overflow: hidden; cursor: pointer; box-shadow: 0 2px 4px rgba(33, 150, 243, 0.2); transition: all 0.2s ease;"
                                                                     onmouseover="this.style.transform='scale(1.02)'; this.style.boxShadow='0 4px 8px rgba(33, 150, 243, 0.3)'"
                                                                     onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 4px rgba(33, 150, 243, 0.2)'"
                                                                     data-entry-id="<?php echo $entry['id']; ?>"
                                                                     data-day-id="<?php echo $entry['day_id']; ?>"
                                                                     data-time-slot-id="<?php echo $entry['time_slot_id']; ?>"
                                                                     data-room-id="<?php echo $entry['room_id']; ?>"
                                                                     data-course-code="<?php echo htmlspecialchars($entry['course_code']); ?>"
                                                                     data-class-name="<?php echo htmlspecialchars($entry['class_name']); ?>"
                                                                     data-lecturer-name="<?php echo htmlspecialchars($entry['lecturer_name']); ?>"
                                                                     data-lecturer-count="<?php echo $entry['lecturer_count']; ?>"
                                                                     data-course-id="<?php echo $entry['course_id']; ?>"
                                                                     data-hours="<?php echo $entry['hours_per_week']; ?>"
                                                                     onclick="editTimetableCell(this)">
                                                                    <div class="fw-bold text-primary" style="font-size: 0.8em;">
                                                                        <?php echo htmlspecialchars($entry['course_code']); ?>
                                                                        <span class="badge bg-warning ms-1" style="font-size: 0.6em;"><?php echo $entry['hours_per_week']; ?>h</span>
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
                        <?php endfor; ?>
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
                        
                        <?php if ($unscheduled_assignments > 0): ?>
                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Unscheduled Courses
                                <span class="badge bg-warning ms-2"><?php echo $unscheduled_assignments; ?></span>
                            </h6>
                                <?php if ($unscheduled_assignments > 0): ?>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-primary btn-sm" id="autoScheduleBtn" onclick="autoScheduleUnscheduledCourses()">
                                        <i class="fas fa-magic me-1"></i>Auto-Schedule All
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm" id="assignLecturersBtn" onclick="openLecturerAssignmentModal()">
                                        <i class="fas fa-user-plus me-1"></i>Assign Lecturers
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php
                            // Get unscheduled courses with details and reasons
                            $unscheduled_query = "
                                SELECT 
                                    cc.id as class_course_id,
                                    c.name as class_name,
                                    co.code as course_code,
                                    co.name as course_name,
                                    co.hours_per_week,
                                    l.name as lecturer_name,
                c.total_capacity as class_size,
                                    (SELECT COUNT(*) FROM lecturer_courses lc2 WHERE lc2.course_id = co.id AND lc2.is_active = 1) as lecturer_count,
                                    CASE 
                                        WHEN (SELECT COUNT(*) FROM lecturer_courses lc3 WHERE lc3.course_id = co.id AND lc3.is_active = 1) = 0 THEN 'No lecturer assigned'
                                        WHEN (SELECT COUNT(*) FROM lecturer_courses lc3 WHERE lc3.course_id = co.id AND lc3.is_active = 1) > 1 THEN 'Multiple lecturers assigned'
                                        WHEN (SELECT COUNT(*) FROM rooms r WHERE r.is_active = 1) = 0 THEN 'No rooms available'
                                        WHEN (SELECT COUNT(*) FROM stream_time_slots sts WHERE sts.stream_id = c.stream_id AND sts.is_active = 1) = 0 THEN 'No time slots available'
                                        ELSE 'Scheduling conflict or insufficient resources'
                                    END as reason
                                FROM class_courses cc
                                LEFT JOIN classes c ON cc.class_id = c.id
                                LEFT JOIN courses co ON cc.course_id = co.id
                                LEFT JOIN lecturers l ON cc.lecturer_id = l.id
                                WHERE cc.is_active = 1 
                                AND c.stream_id = ?
                                AND cc.id NOT IN (
                                    SELECT DISTINCT t.class_course_id 
                                    FROM timetable t 
                                    WHERE t.class_course_id IS NOT NULL
                                )
                                ORDER BY c.name, co.code
                            ";
                            
                            $unscheduled_stmt = $conn->prepare($unscheduled_query);
                            $unscheduled_stmt->bind_param("i", $current_stream_id);
                            $unscheduled_stmt->execute();
                            $unscheduled_result = $unscheduled_stmt->get_result();
                            ?>
                            
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Class</th>
                                            <th>Course</th>
                                            <th>Lecturer</th>
                                            <th>Hours</th>
                                            <th>Reason</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($unscheduled = $unscheduled_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($unscheduled['class_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-primary"><?php echo htmlspecialchars($unscheduled['course_code']); ?></span><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($unscheduled['course_name']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($unscheduled['lecturer_count'] > 1): ?>
                                                    <span class="badge bg-info">Multiple lecturers</span>
                                                <?php elseif ($unscheduled['lecturer_name']): ?>
                                                    <?php echo htmlspecialchars($unscheduled['lecturer_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo $unscheduled['hours_per_week']; ?>h</span>
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-info" 
                                                        data-bs-toggle="tooltip" 
                                                        data-bs-placement="top" 
                                                        data-class-course-id="<?php echo $unscheduled['class_course_id']; ?>"
                                                        data-course-code="<?php echo htmlspecialchars($unscheduled['course_code']); ?>"
                                                        data-class-name="<?php echo htmlspecialchars($unscheduled['class_name']); ?>"
                                                        onclick="showConstraintDetails(<?php echo $unscheduled['class_course_id']; ?>)"
                                                        title="Click to view detailed constraint information">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="openManualSchedulingModal(<?php echo $unscheduled['class_course_id']; ?>, '<?php echo htmlspecialchars($unscheduled['class_name']); ?>', '<?php echo htmlspecialchars($unscheduled['course_code']); ?>', '<?php echo htmlspecialchars($unscheduled['course_name']); ?>', <?php echo $unscheduled['hours_per_week']; ?>, <?php echo $unscheduled['lecturer_count']; ?>)">
                                                    <i class="fas fa-plus me-1"></i>Add to Timetable
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php
                            $unscheduled_stmt->close();
                            ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Available Slots Section -->
                        <div class="mt-4">
                            <h6 class="mb-3">
                                <i class="fas fa-clock me-2 text-info"></i>Available Slots
                                <span class="badge bg-info ms-2" id="available-slots-count">0</span>
                            </h6>
                            
                            <?php
                            // Get available slots for this stream
                            // First, get days that actually have classes for this stream
                            $stream_days_query = "
                                SELECT DISTINCT d.id as day_id
                                FROM days d
                                JOIN timetable t ON d.id = t.day_id
                                JOIN class_courses cc ON t.class_course_id = cc.id
                                JOIN classes c ON cc.class_id = c.id
                                WHERE c.stream_id = ? AND t.semester = ?
                            ";
                            
                            $stream_days_stmt = $conn->prepare($stream_days_query);
                            $stream_days_stmt->bind_param("ii", $current_stream_id, $current_semester);
                            $stream_days_stmt->execute();
                            $stream_days_result = $stream_days_stmt->get_result();
                            $stream_day_ids = [];
                            while ($row = $stream_days_result->fetch_assoc()) {
                                $stream_day_ids[] = $row['day_id'];
                            }
                            $stream_days_stmt->close();
                            
                            // If no days found, use all days (fallback)
                            if (empty($stream_day_ids)) {
                                $stream_day_ids = [1, 2, 3, 4, 5, 6, 7]; // All days
                            }
                            
                            $days_placeholder = str_repeat('?,', count($stream_day_ids) - 1) . '?';
                            
                            $available_slots_query = "
                                SELECT DISTINCT 
                                    d.id as day_id,
                                    d.name as day_name,
                                    ts.id as time_slot_id,
                                    ts.start_time,
                                    ts.end_time,
                                    ts.is_break,
                                    COUNT(r.id) as available_rooms
                                FROM days d
                                CROSS JOIN stream_time_slots sts
                                JOIN time_slots ts ON sts.time_slot_id = ts.id
                                LEFT JOIN rooms r ON r.is_active = 1
                                LEFT JOIN timetable t ON (
                                    t.day_id = d.id 
                                    AND t.time_slot_id = ts.id 
                                    AND t.room_id = r.id
                                    AND t.semester = ?
                                )
                                WHERE sts.stream_id = ? 
                                AND sts.is_active = 1 
                                AND ts.is_break = 0
                                AND t.id IS NULL
                                AND d.id IN ($days_placeholder)
                                GROUP BY d.id, ts.id, d.name, ts.start_time, ts.end_time, ts.is_break
                                ORDER BY d.id, ts.start_time
                            ";
                            
                            $params = array_merge([$current_semester, $current_stream_id], $stream_day_ids);
                            $available_stmt = $conn->prepare($available_slots_query);
                            $available_stmt->bind_param(str_repeat('i', count($params)), ...$params);
                            $available_stmt->execute();
                            $available_result = $available_stmt->get_result();
                            $available_slots = $available_result->fetch_all(MYSQLI_ASSOC);
                            $available_stmt->close();
                            
                            // Group slots by day
                            $slots_by_day = [];
                            foreach ($available_slots as $slot) {
                                $day_name = $slot['day_name'];
                                if (!isset($slots_by_day[$day_name])) {
                                    $slots_by_day[$day_name] = [];
                                }
                                $slots_by_day[$day_name][] = $slot;
                            }
                            
                            $total_available_slots = count($available_slots);
                            ?>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong><?php echo $total_available_slots; ?></strong> time slots have available rooms for additional scheduling.
                                These slots can be used to schedule unscheduled classes or for manual adjustments.
                    </div>
                            
                            <?php if ($total_available_slots > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Day</th>
                                            <th>Available Time Slots</th>
                                            <th>Total Rooms</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($slots_by_day as $day_name => $day_slots): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-primary"><?php echo htmlspecialchars($day_name); ?></strong>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <?php foreach ($day_slots as $slot): ?>
                                                    <div class="badge bg-info text-dark">
                                                        <?php echo formatTimeForDisplay($slot['start_time']); ?> - 
                                                        <?php echo formatTimeForDisplay($slot['end_time']); ?>
                                                        <span class="ms-1">(<?php echo $slot['available_rooms']; ?> rooms)</span>
                </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo array_sum(array_column($day_slots, 'available_rooms')); ?> total
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No available slots found. All time slots are fully utilized.
                            </div>
                            <?php endif; ?>
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
    min-width: 120px;
    border-bottom: 2px solid #e9ecef;
    border-right: 1px solid #dee2e6;
}

.period-time {
    font-weight: 600;
    font-size: 0.85rem;
    color: #495057;
    line-height: 1.2;
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
    font-size: 0.85rem;
    display: block;
    margin-bottom: 0.25rem;
}

.break-time {
    font-size: 0.75rem;
    opacity: 0.9;
    font-weight: 500;
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

.break-placeholder i {
    font-size: 1.2rem;
    margin-bottom: 0.25rem;
    opacity: 0.7;
}

.break-placeholder small {
    font-size: 0.75rem;
    opacity: 0.8;
    line-height: 1.2;
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

/* Course block styling for single-slot courses */
.course-block {
    transition: all 0.2s ease;
}

.course-block:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Responsive adjustments for course blocks */
@media (max-width: 768px) {
    .course-block {
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

// Current stream ID for modal functionality
const currentStreamId = <?php echo $current_stream_id; ?>;

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
    
    // Add form submission listener for loading state and splash overlay
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            ensureGenerationOverlay();
            showLoadingState();
        });
    }
});



// Exams generation removed — function intentionally left out

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
    // Show loading state
    const saveBtn = document.querySelector('.btn-success');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
    saveBtn.disabled = true;
    
    // Send all changes to the database
    const changes = generatedTimetableData.map(entry => ({
        id: entry.id,
        day_id: entry.day_id,
        time_slot_id: entry.time_slot_id,
        room_id: entry.room_id
    }));
    
    fetch('api_timetable_edit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'bulk_update_timetable_entries',
            changes: changes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Timetable changes saved successfully!');
            displayTimetablePreview(generatedTimetableData); // Switch back to preview mode
        } else {
            showErrorMessage('Failed to save timetable changes: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error saving timetable changes. Please try again.');
    })
    .finally(() => {
        // Restore button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
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
    
    // Redirect to extract_timetable.php with the current parameters
    const url = new URL('extract_timetable.php', window.location.origin);
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

    showGenerationOverlay('Generating timetable... This may take a few minutes.');
}

// Hide loading state
function hideLoadingState() {
    const generateBtn = document.getElementById('generate-btn');
    if (generateBtn && generateBtn.dataset.originalText) {
        generateBtn.innerHTML = generateBtn.dataset.originalText;
        generateBtn.disabled = false;
        delete generateBtn.dataset.originalText;
    }

    hideGenerationOverlay();
}

// Ensure generation overlay and styles are present
function ensureGenerationOverlay() {
    if (!document.getElementById('generation-overlay-styles')) {
        const style = document.createElement('style');
        style.id = 'generation-overlay-styles';
        style.textContent = `
            .generation-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.65); display: none; align-items: center; justify-content: center; z-index: 2000; }
            .generation-overlay__card { background: rgba(20,20,20,0.85); border-radius: 12px; padding: 24px 28px; color: #fff; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.4); width: min(520px, 92vw); }
            .generation-overlay__title { font-size: 1.1rem; margin-top: 12px; font-weight: 600; }
            .generation-overlay__subtitle { font-size: .9rem; opacity: .85; margin-top: 6px; }
            .generation-overlay__progress { height: 10px; border-radius: 6px; overflow: hidden; background: rgba(255,255,255,0.15); margin-top: 16px; }
            .generation-overlay__bar { height: 100%; width: 40%; background: linear-gradient(90deg, #0d6efd, #20c997); animation: goIndef 1.6s infinite ease-in-out; border-radius: 6px; }
            @keyframes goIndef { 0% { transform: translateX(-60%);} 50% { transform: translateX(40%);} 100% { transform: translateX(120%);} }
        `;
        document.head.appendChild(style);
    }

    if (!document.getElementById('generation-overlay')) {
        const overlay = document.createElement('div');
        overlay.id = 'generation-overlay';
        overlay.className = 'generation-overlay';
        overlay.innerHTML = `
            <div class="generation-overlay__card">
                <div class="d-flex justify-content-center">
                    <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status" aria-hidden="true"></div>
                </div>
                <div class="generation-overlay__title">Generating Timetable</div>
                <div class="generation-overlay__subtitle" id="generation-overlay-message">Preparing data, optimizing schedules, assigning rooms…</div>
                <div class="generation-overlay__progress"><div class="generation-overlay__bar"></div></div>
                <div class="generation-overlay__subtitle mt-2">Please keep this tab open.</div>
            </div>`;
        document.body.appendChild(overlay);
    }
}

function showGenerationOverlay(message) {
    const overlay = document.getElementById('generation-overlay');
    if (overlay) {
        overlay.style.display = 'flex';
        const msg = document.getElementById('generation-overlay-message');
        if (msg && message) msg.textContent = message;
    }
}

function hideGenerationOverlay() {
    const overlay = document.getElementById('generation-overlay');
    if (overlay) overlay.style.display = 'none';
}

// Show success message
function showSuccessMessage(message) {
    const toastBody = document.getElementById('successToastBody');
    const toastElement = document.getElementById('successToast');
    
    if (toastBody && toastElement) {
        toastBody.textContent = message;
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
    }
}

// Show error message
function showErrorMessage(message) {
    const toastBody = document.getElementById('errorToastBody');
    const toastElement = document.getElementById('errorToast');
    
    if (toastBody && toastElement) {
        toastBody.textContent = message;
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
    }
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
    const lecturerCount = element.dataset.lecturerCount || 1;
    const courseId = element.dataset.courseId;
    
    // Show edit modal
    showEditModal(entryId, dayId, timeSlotId, roomId, courseCode, className, lecturerName, hours, lecturerCount, courseId);
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
function showEditModal(entryId, dayId, timeSlotId, roomId, courseCode, className, lecturerName, hours, lecturerCount, courseId) {
    // Fetch time slots from database
    fetch('get_stream_time_slots.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const timeSlots = data.data;
                showEditModalWithTimeSlots(entryId, dayId, timeSlotId, roomId, courseCode, className, lecturerName, hours, lecturerCount, courseId, timeSlots);
            } else {
                console.error('Failed to fetch time slots:', data.error);
                // Fallback to hardcoded time slots
                showEditModalWithTimeSlots(entryId, dayId, timeSlotId, roomId, courseCode, className, lecturerName, hours, lecturerCount, courseId, []);
            }
        })
        .catch(error => {
            console.error('Error fetching time slots:', error);
            // Fallback to hardcoded time slots
            showEditModalWithTimeSlots(entryId, dayId, timeSlotId, roomId, courseCode, className, lecturerName, hours, lecturerCount, courseId, []);
        });
}

// Show edit modal with time slots data
function showEditModalWithTimeSlots(entryId, dayId, timeSlotId, roomId, courseCode, className, lecturerName, hours, lecturerCount, courseId, timeSlots) {
    // Generate time slot options
    let timeSlotOptions = '<option value="">Select Time Slot</option>';
    if (timeSlots.length > 0) {
        timeSlots.forEach(slot => {
            const selected = timeSlotId == slot.id ? 'selected' : '';
            const startTime = slot.start_time.substring(0, 5); // Remove seconds
            const endTime = slot.end_time.substring(0, 5); // Remove seconds
            timeSlotOptions += `<option value="${slot.id}" ${selected}>${startTime} - ${endTime}</option>`;
        });
    } else {
        // Fallback hardcoded options
        timeSlotOptions += `
            <option value="1" ${timeSlotId == 1 ? 'selected' : ''}>08:00 - 09:00</option>
            <option value="2" ${timeSlotId == 2 ? 'selected' : ''}>09:00 - 10:00</option>
            <option value="3" ${timeSlotId == 3 ? 'selected' : ''}>10:00 - 11:00</option>
            <option value="4" ${timeSlotId == 4 ? 'selected' : ''}>11:00 - 12:00</option>
            <option value="5" ${timeSlotId == 5 ? 'selected' : ''}>13:00 - 14:00</option>
            <option value="6" ${timeSlotId == 6 ? 'selected' : ''}>14:00 - 15:00</option>
            <option value="7" ${timeSlotId == 7 ? 'selected' : ''}>15:00 - 16:00</option>
            <option value="8" ${timeSlotId == 8 ? 'selected' : ''}>16:00 - 17:00</option>
        `;
    }

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
                                            ${timeSlotOptions}
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
                            
                            ${lecturerCount > 1 ? `
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="mb-3">
                                        <label for="edit_lecturer" class="form-label">Select Lecturer</label>
                                        <select class="form-select" id="edit_lecturer" name="lecturer_course_id">
                                            <option value="">Loading lecturers...</option>
                                        </select>
                                        <small class="form-text text-muted">This course has multiple lecturers assigned. Please select one.</small>
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                            
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
    
    // Load lecturers if multiple lecturers are available
    if (lecturerCount > 1 && courseId) {
        loadCourseLecturers(courseId);
    }
}

// Load lecturers for a course
function loadCourseLecturers(courseId) {
    fetch(`get_lecturer_courses.php?course_id=${courseId}`)
        .then(response => response.json())
        .then(data => {
            const lecturerSelect = document.getElementById('edit_lecturer');
            if (lecturerSelect && data.success) {
                lecturerSelect.innerHTML = '<option value="">Select Lecturer</option>';
                data.lecturers.forEach(lecturer => {
                    lecturerSelect.innerHTML += `<option value="${lecturer.lecturer_course_id}">${lecturer.lecturer_name}</option>`;
                });
            } else {
                console.error('Failed to load lecturers:', data.error);
                lecturerSelect.innerHTML = '<option value="">Error loading lecturers</option>';
            }
        })
        .catch(error => {
            console.error('Error loading lecturers:', error);
            const lecturerSelect = document.getElementById('edit_lecturer');
            if (lecturerSelect) {
                lecturerSelect.innerHTML = '<option value="">Error loading lecturers</option>';
            }
        });
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
            // Reload page immediately to show updated data
            location.reload();
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

// Manual scheduling modal functions
function openManualSchedulingModal(classCourseId, className, courseCode, courseName, hoursPerWeek, lecturerCount) {
    // Auto-detect semester from course code
    let detectedSemester = 1; // Default to first semester
    if (courseCode) {
        const match = courseCode.match(/(\d{3})/);
        if (match) {
            const threeDigit = match[1];
            const secondDigit = parseInt(threeDigit.charAt(1));
            detectedSemester = (secondDigit % 2 === 1) ? 1 : 2;
        }
    }
    
    // Get currently assigned lecturer info
    fetch(`get_current_lecturer.php?class_course_id=${classCourseId}`)
        .then(response => response.json())
        .then(data => {
            let lecturerInfo = '';
            if (data.success && data.lecturer) {
                lecturerInfo = `<br><strong>Current Lecturer:</strong> ${data.lecturer.name}`;
            }
            
            // Create modal HTML with lecturer info
            const modalHtml = `
                <div class="modal fade" id="manualSchedulingModal" tabindex="-1" aria-labelledby="manualSchedulingModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="manualSchedulingModalLabel">
                                    <i class="fas fa-plus-circle me-2"></i>Add Course to Timetable
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="manualSchedulingForm">
                                    <input type="hidden" id="manual_class_course_id" name="class_course_id" value="${classCourseId}">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Course Information</label>
                                        <div class="alert alert-info">
                                            <strong>Class:</strong> ${className}<br>
                                            <strong>Course:</strong> ${courseCode} - ${courseName}<br>
                                            <strong>Duration:</strong> ${hoursPerWeek} hour(s)<br>
                                            <strong>Detected Semester:</strong> ${detectedSemester === 1 ? 'First Semester' : 'Second Semester'}${lecturerInfo}
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="manual_day" class="form-label">Day</label>
                                                <select class="form-select" id="manual_day" name="day_id" required>
                                                    <option value="">Select Day</option>
                                                    <option value="1">Monday</option>
                                                    <option value="2">Tuesday</option>
                                                    <option value="3">Wednesday</option>
                                                    <option value="4">Thursday</option>
                                                    <option value="5">Friday</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="manual_time_slot" class="form-label">Time Slot</label>
                                                <select class="form-select" id="manual_time_slot" name="time_slot_id" required>
                                                    <option value="">Select Time Slot</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="manual_room" class="form-label">Room</label>
                                                <select class="form-select" id="manual_room" name="room_id" required>
                                                    <option value="">Select Room</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="manual_lecturer" class="form-label">Lecturer</label>
                                                <select class="form-select" id="manual_lecturer" name="lecturer_course_id">
                                                    <option value="">Loading lecturers...</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="manual_semester" class="form-label">Semester</label>
                                        <select class="form-select" id="manual_semester" name="semester" required>
                                            <option value="1" ${detectedSemester === 1 ? 'selected' : ''}>First Semester</option>
                                            <option value="2" ${detectedSemester === 2 ? 'selected' : ''}>Second Semester</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-success" onclick="saveManualScheduling()">
                                    <i class="fas fa-plus me-1"></i>Add to Timetable
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('manualSchedulingModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Initialize lecturer dropdown - will be populated when time slot is selected
            document.getElementById('manual_lecturer').innerHTML = '<option value="">Select a time slot first</option>';
            
            // Add event listeners for day and time slot changes
            document.getElementById('manual_day').addEventListener('change', function() {
                const dayId = this.value;
                if (dayId) {
                    loadTimeSlotsForManualScheduling(dayId);
                    // Clear room and lecturer selection when day changes
                    document.getElementById('manual_room').innerHTML = '<option value="">Select Room</option>';
                    document.getElementById('manual_lecturer').innerHTML = '<option value="">Select a time slot first</option>';
                } else {
                    document.getElementById('manual_time_slot').innerHTML = '<option value="">Select Time Slot</option>';
                    document.getElementById('manual_room').innerHTML = '<option value="">Select Room</option>';
                    document.getElementById('manual_lecturer').innerHTML = '<option value="">Select a time slot first</option>';
                }
            });
            
            document.getElementById('manual_time_slot').addEventListener('change', function() {
                const dayId = document.getElementById('manual_day').value;
                const timeSlotId = this.value;
                if (dayId && timeSlotId) {
                    loadRoomsForManualScheduling(dayId, timeSlotId);
                } else {
                    document.getElementById('manual_room').innerHTML = '<option value="">Select Room</option>';
                    document.getElementById('manual_lecturer').innerHTML = '<option value="">Select a time slot first</option>';
                }
            });
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('manualSchedulingModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error loading lecturer info:', error);
            // Show modal without lecturer info if there's an error
            showModalWithoutLecturerInfo();
        });
    
    function showModalWithoutLecturerInfo() {
        // Create modal HTML without lecturer info
        const modalHtml = `
            <div class="modal fade" id="manualSchedulingModal" tabindex="-1" aria-labelledby="manualSchedulingModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="manualSchedulingModalLabel">
                                <i class="fas fa-plus-circle me-2"></i>Add Course to Timetable
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="manualSchedulingForm">
                                <input type="hidden" id="manual_class_course_id" name="class_course_id" value="${classCourseId}">
                                
                                <div class="mb-3">
                                    <label class="form-label">Course Information</label>
                                    <div class="alert alert-info">
                                        <strong>Class:</strong> ${className}<br>
                                        <strong>Course:</strong> ${courseCode} - ${courseName}<br>
                                        <strong>Duration:</strong> ${hoursPerWeek} hour(s)<br>
                                        <strong>Detected Semester:</strong> ${detectedSemester === 1 ? 'First Semester' : 'Second Semester'}
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="manual_day" class="form-label">Day</label>
                                            <select class="form-select" id="manual_day" name="day_id" required>
                                                <option value="">Select Day</option>
                                                <option value="1">Monday</option>
                                                <option value="2">Tuesday</option>
                                                <option value="3">Wednesday</option>
                                                <option value="4">Thursday</option>
                                                <option value="5">Friday</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="manual_time_slot" class="form-label">Time Slot</label>
                                            <select class="form-select" id="manual_time_slot" name="time_slot_id" required>
                                                <option value="">Select Time Slot</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="manual_room" class="form-label">Room</label>
                                            <select class="form-select" id="manual_room" name="room_id" required>
                                                <option value="">Select Room</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="manual_lecturer" class="form-label">Lecturer</label>
                                            <select class="form-select" id="manual_lecturer" name="lecturer_course_id">
                                                <option value="">Loading lecturers...</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="manual_semester" class="form-label">Semester</label>
                                    <select class="form-select" id="manual_semester" name="semester" required>
                                        <option value="1" ${detectedSemester === 1 ? 'selected' : ''}>First Semester</option>
                                        <option value="2" ${detectedSemester === 2 ? 'selected' : ''}>Second Semester</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-success" onclick="saveManualScheduling()">
                                <i class="fas fa-plus me-1"></i>Add to Timetable
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('manualSchedulingModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Initialize lecturer dropdown - will be populated when time slot is selected
        document.getElementById('manual_lecturer').innerHTML = '<option value="">Select a time slot first</option>';
        
        // Add event listeners for day and time slot changes
        document.getElementById('manual_day').addEventListener('change', function() {
            const dayId = this.value;
            if (dayId) {
                loadTimeSlotsForManualScheduling(dayId);
                // Clear room and lecturer selection when day changes
                document.getElementById('manual_room').innerHTML = '<option value="">Select Room</option>';
                document.getElementById('manual_lecturer').innerHTML = '<option value="">Select a time slot first</option>';
            } else {
                document.getElementById('manual_time_slot').innerHTML = '<option value="">Select Time Slot</option>';
                document.getElementById('manual_room').innerHTML = '<option value="">Select Room</option>';
                document.getElementById('manual_lecturer').innerHTML = '<option value="">Select a time slot first</option>';
            }
        });
        
        document.getElementById('manual_time_slot').addEventListener('change', function() {
            const dayId = document.getElementById('manual_day').value;
            const timeSlotId = this.value;
            if (dayId && timeSlotId) {
                loadRoomsForManualScheduling(dayId, timeSlotId);
            } else {
                document.getElementById('manual_room').innerHTML = '<option value="">Select Room</option>';
                document.getElementById('manual_lecturer').innerHTML = '<option value="">Select a time slot first</option>';
            }
        });
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('manualSchedulingModal'));
        modal.show();
    }
}
</script>
<script type="text/javascript">
function loadTimeSlotsForManualScheduling(dayId = null) {
    const timeSlotSelect = document.getElementById('manual_time_slot');
    if (timeSlotSelect) {
        timeSlotSelect.innerHTML = '<option value="">Loading time slots...</option>';
    }
    
    let url = 'get_stream_time_slots.php';
    if (dayId) {
        url += `?day_id=${dayId}`;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (timeSlotSelect && data.success) {
                // Show all time slots with availability information
                if (data.data && data.data.length > 0) {
                    timeSlotSelect.innerHTML = '<option value="">Select Time Slot</option>';
                    data.data.forEach(slot => {
                        const startTime = slot.start_time.substring(0, 5);
                        const endTime = slot.end_time.substring(0, 5);
                        const roomsCount = slot.available_rooms_count || 0;
                        const availabilityText = roomsCount > 0 ? ` (${roomsCount} rooms available)` : ' (No rooms available)';
                        const disabledAttr = roomsCount === 0 ? ' disabled' : '';
                        timeSlotSelect.innerHTML += `<option value="${slot.id}"${disabledAttr}>${startTime} - ${endTime}${availabilityText}</option>`;
                    });
                } else {
                    timeSlotSelect.innerHTML = '<option value="">No time slots available</option>';
                }
            } else {
                timeSlotSelect.innerHTML = '<option value="">Error loading time slots</option>';
            }
        })
        .catch(error => {
            console.error('Error loading time slots:', error);
            if (timeSlotSelect) {
                timeSlotSelect.innerHTML = '<option value="">Error loading time slots</option>';
            }
        });
}

function loadRoomsForManualScheduling(dayId = null, timeSlotId = null) {
    const roomSelect = document.getElementById('manual_room');
    const lecturerSelect = document.getElementById('manual_lecturer');
    const classCourseId = document.getElementById('manual_class_course_id').value;
    
    if (roomSelect) {
        roomSelect.innerHTML = '<option value="">Loading rooms...</option>';
    }
    if (lecturerSelect) {
        lecturerSelect.innerHTML = '<option value="">Loading lecturers...</option>';
    }
    
    // Load rooms
    let roomUrl = 'api_timetable_template.php?action=get_rooms';
    if (dayId && timeSlotId) {
        roomUrl += `&day_id=${dayId}&time_slot_id=${timeSlotId}`;
    }
    
    fetch(roomUrl)
        .then(response => response.json())
        .then(data => {
            if (roomSelect && data.success) {
                if (data.data && data.data.length > 0) {
                    roomSelect.innerHTML = '<option value="">Select Room</option>';
                    data.data.forEach(room => {
                        roomSelect.innerHTML += `<option value="${room.id}">${room.name}</option>`;
                    });
                } else {
                    roomSelect.innerHTML = '<option value="">No rooms available</option>';
                }
            } else {
                roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
            }
        })
        .catch(error => {
            console.error('Error loading rooms:', error);
            if (roomSelect) {
                roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
            }
        });
    
    // Load available lecturers for this time slot
    if (dayId && timeSlotId && classCourseId) {
        const lecturerUrl = `check_lecturer_availability.php?day_id=${dayId}&time_slot_id=${timeSlotId}&class_course_id=${classCourseId}`;
        
        fetch(lecturerUrl)
            .then(response => response.json())
            .then(data => {
                if (lecturerSelect && data.success) {
                    if (data.data && data.data.length > 0) {
                        lecturerSelect.innerHTML = '<option value="">Select Lecturer</option>';
                        let assignedLecturerId = null;
                        
                        data.data.forEach(lecturer => {
                            const selectedAttr = lecturer.is_currently_assigned ? ' selected' : '';
                            const assignedText = lecturer.is_currently_assigned ? ' (Currently Assigned)' : '';
                            lecturerSelect.innerHTML += `<option value="${lecturer.lecturer_course_id}"${selectedAttr}>${lecturer.lecturer_name}${assignedText}</option>`;
                            
                            if (lecturer.is_currently_assigned) {
                                assignedLecturerId = lecturer.lecturer_course_id;
                            }
                        });
                        
                        // If there's an assigned lecturer, show a note
                        if (assignedLecturerId) {
                            const note = document.createElement('small');
                            note.className = 'text-info';
                            note.innerHTML = '<i class="fas fa-info-circle me-1"></i>Currently assigned lecturer is pre-selected';
                            lecturerSelect.parentNode.appendChild(note);
                        }
                    } else {
                        lecturerSelect.innerHTML = '<option value="">No lecturers available at this time</option>';
                    }
                } else {
                    lecturerSelect.innerHTML = '<option value="">Error loading lecturers</option>';
                }
            })
            .catch(error => {
                console.error('Error loading lecturers:', error);
                if (lecturerSelect) {
                    lecturerSelect.innerHTML = '<option value="">Error loading lecturers</option>';
                }
            });
    }
}

function loadLecturersForManualScheduling(classCourseId) {
    fetch(`get_lecturer_courses.php?class_course_id=${classCourseId}`)
        .then(response => response.json())
        .then(data => {
            const lecturerSelect = document.getElementById('manual_lecturer');
            if (lecturerSelect && data.success) {
                lecturerSelect.innerHTML = '<option value="">Select Lecturer</option>';
                data.lecturers.forEach(lecturer => {
                    lecturerSelect.innerHTML += `<option value="${lecturer.lecturer_course_id}">${lecturer.lecturer_name}</option>`;
                });
            } else {
                lecturerSelect.innerHTML = '<option value="">No lecturers available</option>';
            }
        })
        .catch(error => {
            console.error('Error loading lecturers:', error);
            const lecturerSelect = document.getElementById('manual_lecturer');
            if (lecturerSelect) {
                lecturerSelect.innerHTML = '<option value="">Error loading lecturers</option>';
            }
        });
}

function saveManualScheduling() {
    const form = document.getElementById('manualSchedulingForm');
    const formData = new FormData(form);
    
    // Add action
    formData.append('action', 'add_manual_timetable_entry');
    
    // Show loading state
    const saveBtn = document.querySelector('#manualSchedulingModal .btn-success');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Adding...';
    saveBtn.disabled = true;
    
    // Send AJAX request
    fetch('api_timetable_edit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage('Course added to timetable successfully!');
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('manualSchedulingModal'));
            modal.hide();
            // Reload page to show updated data
            location.reload();
        } else {
            showErrorMessage('Failed to add course to timetable: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error adding course to timetable. Please try again.');
    })
    .finally(() => {
        // Restore button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

// Open lecturer assignment modal
function openLecturerAssignmentModal() {
    const currentStreamId = <?php echo $current_stream_id; ?>;
    const currentSemester = <?php echo $current_semester; ?>;
    
    // Create modal HTML for lecturer assignment
    const modalHtml = `
        <div class="modal fade" id="lecturerAssignmentModal" tabindex="-1" aria-labelledby="lecturerAssignmentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="lecturerAssignmentModalLabel">
                            <i class="fas fa-user-plus me-2"></i>Assign Lecturers to Unscheduled Courses
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Assign lecturers to courses that don't have lecturers assigned. After assignment, use the "Auto-Schedule All" button to schedule these courses.
                        </div>
                        
                        <div id="lecturerAssignmentContent">
                            <div class="text-center">
                                <div class="spinner-border" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading unscheduled courses...</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-success" onclick="saveLecturerAssignments()">
                            <i class="fas fa-save me-1"></i>Save Assignments
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('lecturerAssignmentModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('lecturerAssignmentModal'));
    modal.show();
    
    // Load unscheduled courses
    loadUnscheduledCoursesForAssignment(currentStreamId, currentSemester);
}

// Load unscheduled courses for lecturer assignment
function loadUnscheduledCoursesForAssignment(streamId, semester) {
    fetch('api_lecturer_assignment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_unscheduled_courses',
            stream_id: streamId,
            semester: semester
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayUnscheduledCoursesForAssignment(data.courses);
        } else {
            document.getElementById('lecturerAssignmentContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading courses: ${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('lecturerAssignmentContent').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Error loading courses. Please try again.
            </div>
        `;
    });
}

// Display unscheduled courses for assignment
function displayUnscheduledCoursesForAssignment(courses) {
    if (courses.length === 0) {
        document.getElementById('lecturerAssignmentContent').innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i>
                All courses have lecturers assigned!
            </div>
        `;
        return;
    }
    
    let html = '<div class="row">';
    
    courses.forEach(course => {
        html += `
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <strong>${course.course_code}</strong> - ${course.course_name}
                        </h6>
                        <p class="card-text">
                            <small class="text-muted">Class: ${course.class_name}</small>
                        </p>
                        <div class="mb-2">
                            <label class="form-label">Select Lecturer:</label>
                            <select class="form-select lecturer-select" data-course-id="${course.course_id}" data-class-course-id="${course.class_course_id}">
                                <option value="">Choose lecturer...</option>
                            </select>
                        </div>
                        <div class="lecturer-options" id="lecturer-options-${course.class_course_id}" style="display: none;">
                            <small class="text-muted">Available lecturers for this course:</small>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    
    document.getElementById('lecturerAssignmentContent').innerHTML = html;
    
    // Load lecturers for each course
    courses.forEach(course => {
        loadLecturersForCourse(course.course_id, course.class_course_id);
    });
}

// Load lecturers for a specific course
function loadLecturersForCourse(courseId, classCourseId) {
    fetch('api_lecturer_assignment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_course_lecturers',
            course_id: courseId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.querySelector(`select[data-course-id="${courseId}"]`);
            const optionsDiv = document.getElementById(`lecturer-options-${classCourseId}`);
            
            if (data.lecturers.length > 0) {
                data.lecturers.forEach(lecturer => {
                    const option = document.createElement('option');
                    option.value = lecturer.lecturer_course_id;
                    option.textContent = lecturer.lecturer_name;
                    select.appendChild(option);
                });
                
                optionsDiv.innerHTML = `<small class="text-success">${data.lecturers.length} lecturer(s) available</small>`;
                optionsDiv.style.display = 'block';
            } else {
                select.innerHTML = '<option value="">No lecturers available for this course</option>';
                optionsDiv.innerHTML = '<small class="text-warning">No lecturers available for this course</small>';
                optionsDiv.style.display = 'block';
            }
        }
    })
    .catch(error => {
        console.error('Error loading lecturers:', error);
    });
}

// Save lecturer assignments
function saveLecturerAssignments() {
    const assignments = [];
    const selects = document.querySelectorAll('.lecturer-select');
    
    selects.forEach(select => {
        if (select.value) {
            assignments.push({
                class_course_id: select.dataset.classCourseId,
                lecturer_course_id: select.value
            });
        }
    });
    
    if (assignments.length === 0) {
        showErrorMessage('Please select at least one lecturer assignment.');
        return;
    }
    
    const currentStreamId = <?php echo $current_stream_id; ?>;
    const currentSemester = <?php echo $current_semester; ?>;
    
    fetch('api_lecturer_assignment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'save_assignments',
            assignments: assignments,
            stream_id: currentStreamId,
            semester: currentSemester
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(`Successfully assigned lecturers to ${data.assigned_count} courses!`);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('lecturerAssignmentModal'));
            modal.hide();
            
            // Reload page to show updated data
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showErrorMessage('Failed to save assignments: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error saving assignments. Please try again.');
    });
}

// Show constraint details for a specific course
function showConstraintDetails(classCourseId) {
    const currentStreamId = <?php echo $current_stream_id; ?>;
    const currentSemester = <?php echo $current_semester; ?>;
    
    // Show loading state
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
    button.disabled = true;
    
    // Fetch constraint details
    fetch('api_constraint_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get_constraint_details',
            class_course_id: classCourseId,
            stream_id: currentStreamId,
            semester: currentSemester
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showConstraintModal(data.constraint_details);
        } else {
            showErrorMessage('Failed to load constraint details: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error loading constraint details. Please try again.');
    })
    .finally(() => {
        // Restore button state
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}

// Show constraint details modal
function showConstraintModal(constraintDetails) {
    const modalHtml = `
        <div class="modal fade" id="constraintDetailsModal" tabindex="-1" aria-labelledby="constraintDetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="constraintDetailsModalLabel">
                            <i class="fas fa-info-circle me-2"></i>Constraint Analysis
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Course:</strong> ${constraintDetails.course_code} - ${constraintDetails.course_name}
                            </div>
                            <div class="col-md-6">
                                <strong>Class:</strong> ${constraintDetails.class_name}
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Lecturer:</strong> ${constraintDetails.lecturer_name || 'Not assigned'}
                            </div>
                            <div class="col-md-6">
                                <strong>Hours:</strong> ${constraintDetails.hours_per_week}h
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="alert alert-${constraintDetails.reason === 'No lecturer assigned' ? 'warning' : 'danger'}">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Reason: ${constraintDetails.reason}</h6>
                            <p class="mb-0">${constraintDetails.details}</p>
                        </div>
                        
                        ${constraintDetails.attempts > 0 ? `
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary">${constraintDetails.attempts}</h5>
                                        <p class="card-text">Slot/Room Combinations Attempted</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title text-info">${constraintDetails.suitable_rooms}</h5>
                                        <p class="card-text">Suitable Rooms Found</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title text-success">${constraintDetails.available_slots}</h5>
                                        <p class="card-text">Available Time Slots</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                        
                        ${constraintDetails.total_rooms ? `
                        <div class="mt-3">
                            <small class="text-muted">
                                Total rooms available: ${constraintDetails.total_rooms} | 
                                Total time slots: ${constraintDetails.total_slots}
                            </small>
                        </div>
                        ` : ''}
                        
                        <div class="mt-4">
                            <h6>Recommended Actions:</h6>
                            <ul class="list-unstyled">
                                ${constraintDetails.reason === 'No lecturer assigned' ? 
                                    '<li><i class="fas fa-user-plus text-warning me-2"></i>Assign a lecturer to this course using the "Assign Lecturers" button</li>' : 
                                    '<li><i class="fas fa-calendar-plus text-info me-2"></i>Try manual scheduling using the "Add to Timetable" button</li>' +
                                    '<li><i class="fas fa-clock text-secondary me-2"></i>Consider adjusting existing schedule to free up time slots</li>' +
                                    '<li><i class="fas fa-door-open text-primary me-2"></i>Check if room capacity or type requirements can be adjusted</li>'
                                }
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        ${constraintDetails.reason === 'No lecturer assigned' ? 
                            '<button type="button" class="btn btn-warning" onclick="openLecturerAssignmentModal()"><i class="fas fa-user-plus me-1"></i>Assign Lecturers</button>' :
                            '<button type="button" class="btn btn-primary" onclick="openManualSchedulingModal(' + constraintDetails.class_course_id + ', \'' + constraintDetails.class_name + '\', \'' + constraintDetails.course_code + '\', \'' + constraintDetails.course_name + '\', ' + constraintDetails.hours_per_week + ', 1)"><i class="fas fa-calendar-plus me-1"></i>Manual Schedule</button>'
                        }
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('constraintDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('constraintDetailsModal'));
    modal.show();
}

// Version management functions
function loadVersion(versionName) {
    // Add version parameter to URL and reload
    const url = new URL(window.location);
    url.searchParams.set('version', versionName);
    window.location.href = url.toString();
}

function deleteVersion(versionName) {
    if (!confirm(`Are you sure you want to delete the "${versionName}" version? This action cannot be undone.`)) {
        return;
    }
    
    const currentStreamId = <?php echo $current_stream_id; ?>;
    const currentSemester = <?php echo $current_semester; ?>;
    
    fetch('api_version_management.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'delete_version',
            stream_id: currentStreamId,
            semester: currentSemester,
            version: versionName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessMessage(`Version "${versionName}" deleted successfully!`);
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showErrorMessage('Failed to delete version: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error deleting version. Please try again.');
    });
}

function refreshVersions() {
    location.reload();
}

// Auto-schedule unscheduled courses function
function autoScheduleUnscheduledCourses() {
    const button = document.getElementById('autoScheduleBtn');
    const originalText = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Auto-Scheduling...';
    button.disabled = true;
    
    // Get current stream and semester from the page
    const currentStreamId = <?php echo $current_stream_id; ?>;
    const currentSemester = <?php echo $current_semester; ?>;
    
    // Send AJAX request to auto-schedule
    fetch('api_auto_schedule.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'auto_schedule_unscheduled',
            stream_id: currentStreamId,
            semester: currentSemester
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let message = `Successfully scheduled ${data.scheduled_count} additional courses!`;
            
            // Add detailed feedback about remaining courses
            if (data.remaining_unscheduled > 0) {
                message += `\n\nRemaining unscheduled courses: ${data.remaining_unscheduled}`;
                
                if (data.remaining_details) {
                    if (data.remaining_details.no_lecturer_assigned > 0) {
                        message += `\n• ${data.remaining_details.no_lecturer_assigned} courses have no lecturer assigned`;
                    }
                    if (data.remaining_details.lecturer_not_found > 0) {
                        message += `\n• ${data.remaining_details.lecturer_not_found} courses have lecturer issues`;
                    }
                    if (data.remaining_details.other_constraints > 0) {
                        message += `\n• ${data.remaining_details.other_constraints} courses blocked by other constraints`;
                    }
                }
                
                message += '\n\nPlease assign lecturers to courses before attempting to schedule them.';
            }
            
            // Add detailed constraint failure information
            if (data.constraint_failures && data.constraint_failures.length > 0) {
                message += `\n\n📋 Detailed Constraint Analysis:\n`;
                data.constraint_failures.forEach(failure => {
                    message += `\n• ${failure.course_code} - ${failure.class_name}: ${failure.reason}`;
                    if (failure.details) {
                        message += `\n  Details: ${failure.details}`;
                    }
                    if (failure.attempts) {
                        message += `\n  Attempted ${failure.attempts} slot/room combinations`;
                    }
                });
            }
            
            showSuccessMessage(message);
            
            // Update the unscheduled count
            const unscheduledBadge = document.querySelector('.badge.bg-warning');
            if (unscheduledBadge) {
                const newCount = data.remaining_unscheduled;
                unscheduledBadge.textContent = newCount;
                
                if (newCount === 0) {
                    // Hide the auto-schedule button if no more unscheduled courses
                    button.style.display = 'none';
                    // Update the section title
                    const sectionTitle = document.querySelector('h6 .text-warning');
                    if (sectionTitle) {
                        sectionTitle.textContent = 'All Courses Scheduled';
                        sectionTitle.className = 'fas fa-check-circle me-2 text-success';
                    }
                }
            }
            
            // Reload the page to show updated data
            setTimeout(() => {
                location.reload();
            }, 3000); // Increased timeout to allow user to read the detailed message
            
        } else {
            showErrorMessage('Failed to auto-schedule courses: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error auto-scheduling courses. Please try again.');
    })
    .finally(() => {
        // Restore button state
        button.innerHTML = originalText;
        button.disabled = false;
    });
}
</script>

</body>
</html>