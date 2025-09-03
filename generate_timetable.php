<?php
include 'connect.php';
// Ensure flash helper is available for PRG redirects
if (file_exists(__DIR__ . '/includes/flash.php')) include_once __DIR__ . '/includes/flash.php';

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
            . '<h5 class=\\"card-title text-danger\\" style=\\"margin:0;\\">An error occurred</h5>'
            . '<div><button class=\\"btn btn-sm btn-outline-secondary copyErrorBtn\\" title=\\"Copy error\\"><i class=\\"fas fa-copy\\"></i></button></div>'
            . '</div>'
            . '<pre class=\\"error-pre\\" style=\\"white-space:pre-wrap;color:#a00;margin:0;\\"></pre>'
            . '</div>";'
        . 'if (main.firstChild) main.insertBefore(errBox, main.firstChild); else main.appendChild(errBox);'
        . 'var errText = ' . $jsmsg . '; var preEl = errBox.querySelector(".error-pre"); if (preEl) preEl.textContent = errText;'
        . 'var btn = errBox.querySelector(".copyErrorBtn"); if (btn) { btn.addEventListener("click", function(){ var text = errBox.querySelector(".error-pre").textContent || ""; if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(function(){ btn.innerHTML = "<i class=\\"fas fa-check\\"></i>"; setTimeout(function(){ btn.innerHTML = "<i class=\\"fas fa-copy\\"></i>"; },1500); }); } else { var ta = document.createElement("textarea"); ta.value = text; document.body.appendChild(ta); ta.select(); try { document.execCommand("copy"); btn.innerHTML = "<i class=\\"fas fa-check\\"></i>"; setTimeout(function(){ btn.innerHTML = "<i class=\\"fas fa-copy\\"></i>"; },1500); } catch(e){} document.body.removeChild(ta); } }); }'
        . '});</script>';
});

// Include stream manager so generation respects currently selected stream
if (file_exists(__DIR__ . '/includes/stream_manager.php')) include_once __DIR__ . '/includes/stream_manager.php';
$streamManager = getStreamManager();
$current_stream_id = $streamManager->getCurrentStreamId();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'generate_lecture_timetable') {
        // Read academic year and semester from user input
        $academic_year = trim($_POST['academic_year'] ?? '');
        $semester = trim($_POST['semester'] ?? '');

        // Normalize academic_year to match DB width and expected format (e.g. "2024/2025").
        // If the user provided a longer descriptive name in the Timetable Name field,
        // extract a YYYY/YYYY fragment or truncate to the 9-char DB column limit.
        $normalizeAcademicYear = function($input) {
            $s = trim((string)$input);
            if ($s === '') return $s;
            // Prefer explicit 4-digit/4-digit formats like 2024/2025
            if (preg_match('/(\d{4}\/\d{4})/', $s, $m)) {
                return $m[1];
            }
            // Accept 4-digit-4-digit variants and normalize to slash
            if (preg_match('/(\d{4}[-]\d{4})/', $s, $m)) {
                return str_replace('-', '/', $m[1]);
            }
            // Accept shortened second year like 2024/25 -> expand if possible
            if (preg_match('/(\d{4}\/\d{2})/', $s, $m)) {
                $parts = explode('/', $m[1]);
                $start = $parts[0];
                $end = $parts[1];
                if (strlen($end) === 2) {
                    $century = substr($start, 0, 2);
                    $end = $century . $end;
                }
                return $start . '/' . $end;
            }
            // Fallback: take the first whitespace-separated token and truncate to 9 chars
            $parts = preg_split('/\s+/', $s);
            $tok = $parts[0] ?? $s;
            if (strlen($tok) > 9) $tok = substr($tok, 0, 9);
            return $tok;
        };

        $academic_year = $normalizeAcademicYear($academic_year);
        if ($academic_year === '' || $semester === '') {
            $error_message = 'Please specify academic year and semester before generating the timetable.';
        }

        // Detect if timetable stores academic_year / semester columns so we can clear only that offering
        $tcol_year = $conn->query("SHOW COLUMNS FROM timetable LIKE 'academic_year'");
        $tcol_sem = $conn->query("SHOW COLUMNS FROM timetable LIKE 'semester'");
        $has_t_academic_year = ($tcol_year && $tcol_year->num_rows > 0);
        $has_t_semester = ($tcol_sem && $tcol_sem->num_rows > 0);
        if ($tcol_year) $tcol_year->close();
        if ($tcol_sem) $tcol_sem->close();

        if (empty($error_message)) {
            // Clear existing timetable entries only for this academic year/semester when supported
            if ($has_t_academic_year && $has_t_semester) {
                $del_stmt = $conn->prepare("DELETE FROM timetable WHERE academic_year = ? AND semester = ?");
                if ($del_stmt) {
                    $del_stmt->bind_param("ss", $academic_year, $semester);
                    $del_stmt->execute();
                    $del_stmt->close();
                }
            } else {
                // Fallback: clear entire timetable
                $conn->query("DELETE FROM timetable");
            }
        }

        // Prepare to fetch time slots per stream using mapping
        $time_slots_by_stream = [];

        // Get all days (used as a fallback if a stream has no specific days)
        $days_sql = "SELECT id, name FROM days WHERE is_active = 1 ORDER BY id";
        $days_result = $conn->query($days_sql);
        $all_days = [];
        while ($day = $days_result->fetch_assoc()) {
            $all_days[] = $day;
        }

        // Get available rooms (rooms are global / not stream-aware)
        $rooms_sql = "SELECT id, name, capacity FROM rooms WHERE is_active = 1 ORDER BY capacity";
        $rooms_result = $conn->query($rooms_sql);
        $rooms = [];
        while ($room = $rooms_result->fetch_assoc()) {
            $rooms[] = $room;
        }

        // Get active streams to generate per-stream. Respect currently selected stream if set.
        if (!empty($current_stream_id) && is_numeric($current_stream_id)) {
            $streams_sql = "SELECT id, name FROM streams WHERE is_active = 1 AND id = " . intval($current_stream_id) . " ORDER BY id";
        } else {
            $streams_sql = "SELECT id, name FROM streams WHERE is_active = 1 ORDER BY id";
        }
        $streams_result = $conn->query($streams_sql);

        if ($streams_result && $streams_result->num_rows > 0) {
            $success_count = 0;
            $error_count = 0;

            // Prepare assignment statement (we will bind stream_id per-iteration)
            // Note: courses table uses `code` and `name` columns. Alias them to match expected keys.
            $assignments_sql = "SELECT cc.id, cc.class_id, cc.course_id, c.name as class_name, co.`code` AS course_code, co.`name` AS course_name
                               FROM class_courses cc
                               JOIN classes c ON cc.class_id = c.id
                               JOIN courses co ON cc.course_id = co.id
                               WHERE cc.is_active = 1 AND c.stream_id = ?";
            $assignments_stmt = $conn->prepare($assignments_sql);

            // Prepare day lookup statement (if stream_days exists)
            $stream_days_enabled = false;
            $sd_check = $conn->query("SHOW TABLES LIKE 'stream_days'");
            if ($sd_check && $sd_check->num_rows > 0) {
                $stream_days_enabled = true;
                $stream_days_sql = "SELECT d.id, d.name FROM stream_days sd JOIN days d ON sd.day_id = d.id WHERE sd.stream_id = ? AND d.is_active = 1 ORDER BY d.id";
                $stream_days_stmt = $conn->prepare($stream_days_sql);
            }

            // prepare conflict-check statements once
            $check_room_sql = "SELECT COUNT(*) as count FROM timetable WHERE day_id = ? AND time_slot_id = ? AND room_id = ?";
            $check_room_stmt = $conn->prepare($check_room_sql);

            // detect if timetable has a division_label column to allow per-division scheduling
            $div_col_check = $conn->query("SHOW COLUMNS FROM timetable LIKE 'division_label'");
            $has_division_col = ($div_col_check && $div_col_check->num_rows > 0);
            if ($div_col_check) $div_col_check->close();

            // check if classes table contains divisions_count
            $col_div = $conn->query("SHOW COLUMNS FROM classes LIKE 'divisions_count'");
            $has_classes_divisions = ($col_div && $col_div->num_rows > 0);
            if ($col_div) $col_div->close();

            // check if class (via class_courses->class_id) already has an entry at the same day/time
            // Detect whether timetable stores class_course_id (newer schema) or class_id (older schema)
            $tcol_check_for_class_course = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
            $has_t_class_course = ($tcol_check_for_class_course && $tcol_check_for_class_course->num_rows > 0);
            if ($tcol_check_for_class_course) $tcol_check_for_class_course->close();

            if ($has_t_class_course) {
                if ($has_division_col) {
                    // check per-division (allow different divisions of same class at same time)
                    $check_class_sql = "SELECT COUNT(*) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id WHERE cc.class_id = ? AND t.division_label = ? AND t.day_id = ? AND t.time_slot_id = ?";
                } else {
                    $check_class_sql = "SELECT COUNT(*) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id WHERE cc.class_id = ? AND t.day_id = ? AND t.time_slot_id = ?";
                }
            } else {
                // timetable stores class_id directly
                if ($has_division_col) {
                    $check_class_sql = "SELECT COUNT(*) as count FROM timetable t WHERE t.class_id = ? AND t.division_label = ? AND t.day_id = ? AND t.time_slot_id = ?";
                } else {
                    $check_class_sql = "SELECT COUNT(*) as count FROM timetable t WHERE t.class_id = ? AND t.day_id = ? AND t.time_slot_id = ?";
                }
            }
            $check_class_stmt = $conn->prepare($check_class_sql);

            // check if lecturer_course is already teaching at that time
            $tcol_check_for_lecturer_course = $conn->query("SHOW COLUMNS FROM timetable LIKE 'lecturer_course_id'");
            $has_t_lecturer_course = ($tcol_check_for_lecturer_course && $tcol_check_for_lecturer_course->num_rows > 0);
            if ($tcol_check_for_lecturer_course) $tcol_check_for_lecturer_course->close();

            if ($has_t_lecturer_course) {
                $check_lecturer_sql = "SELECT COUNT(*) as count FROM timetable WHERE lecturer_course_id = ? AND day_id = ? AND time_slot_id = ?";
            } else {
                $check_lecturer_sql = "SELECT COUNT(*) as count FROM timetable WHERE lecturer_id = ? AND day_id = ? AND time_slot_id = ?";
            }
            $check_lecturer_stmt = $conn->prepare($check_lecturer_sql);

            while ($stream = $streams_result->fetch_assoc()) {
                $stream_id = $stream['id'];

                // Determine days for this stream using active_days JSON from streams table
                $days = $all_days; // Default to all active days
                
                // Get stream's active days preference
                $stream_days_sql = "SELECT active_days FROM streams WHERE id = ?";
                $stream_active_days_stmt = $conn->prepare($stream_days_sql);
                $stream_active_days_stmt->bind_param('i', $stream_id);
                $stream_active_days_stmt->execute();
                $stream_result = $stream_active_days_stmt->get_result();

                if ($stream_row = $stream_result->fetch_assoc()) {
                    $active_days_json = $stream_row['active_days'];
                    if (!empty($active_days_json)) {
                        $active_days_array = json_decode($active_days_json, true);
                        if (is_array($active_days_array) && count($active_days_array) > 0) {
                            // Filter all_days to only include stream's selected days
                            $stream_specific_days = [];
                            foreach ($all_days as $day) {
                                if (in_array($day['name'], $active_days_array)) {
                                    $stream_specific_days[] = $day;
                                }
                            }
                            if (count($stream_specific_days) > 0) {
                                $days = $stream_specific_days;
                            }
                        }
                    }
                }
                $stream_active_days_stmt->close();

                // Load time slots for this stream from mapping (fallback to all time_slots if none selected)
                $stream_slots = [];
                // Check existence of mapping table first to avoid schema warnings being converted to exceptions
                $sts_exists = $conn->query("SHOW TABLES LIKE 'stream_time_slots'");
                if ($sts_exists && $sts_exists->num_rows > 0) {
                    $ts_rs = $conn->query("SELECT ts.id, ts.start_time, ts.end_time FROM stream_time_slots sts JOIN time_slots ts ON ts.id = sts.time_slot_id WHERE sts.stream_id = " . intval($stream_id) . " AND sts.is_active = 1 ORDER BY ts.start_time");
                    if ($ts_rs && $ts_rs->num_rows > 0) {
                        while ($s = $ts_rs->fetch_assoc()) { $stream_slots[] = $s; }
                    }
                }
                // Fallback to global time slots if none found or mapping table missing
                if (empty($stream_slots)) {
                    $ts_rs2 = $conn->query("SELECT id, start_time, end_time FROM time_slots WHERE is_break = 0 ORDER BY start_time");
                    while ($s2 = $ts_rs2->fetch_assoc()) { $stream_slots[] = $s2; }
                }
                if ($sts_exists) $sts_exists->close();

                // Fetch assignments for classes in this stream
                $assignments_stmt->bind_param('i', $stream_id);
                $assignments_stmt->execute();
                $assignments_result = $assignments_stmt->get_result();

                // Load assignments into array and shuffle to avoid placement bias
                $assignments_list = [];
                while ($r = $assignments_result->fetch_assoc()) {
                    $assignments_list[] = $r;
                }
                if (count($assignments_list) > 1) shuffle($assignments_list);

                foreach ($assignments_list as $assignment) {
                    // Assign to a random time slot and a day from this stream's allowed days
                    if (empty($stream_slots) || empty($days) || empty($rooms)) {
                        $error_count++;
                        continue;
                    }
                    // We'll attempt multiple retries to place this assignment in case of conflicts
                    $placed = false;
                    $max_attempts = 10;
                    for ($attempt = 0; $attempt < $max_attempts && !$placed; $attempt++) {
                        // pick random slot/day/room (shuffle sources to reduce bias)
                        $time_slot = $stream_slots[array_rand($stream_slots)];
                        $day = $days[array_rand($days)];
                        $room = $rooms[array_rand($rooms)];

                        // Check room/class/lecturer conflicts
                        $check_room_stmt->bind_param("iii", $day['id'], $time_slot['id'], $room['id']);
                        $check_room_stmt->execute();
                        $room_count = $check_room_stmt->get_result()->fetch_assoc()['count'];

                        // class id: need to fetch class_id for this assignment (we have class_id in the assignment row)
                        // If timetable supports division_label, we will try to schedule into a specific division label
                        $class_count = 0;
                        $division_label_to_use = null;
                        if ($has_division_col && $has_classes_divisions) {
                            // Fetch class divisions_count
                            $cd_stmt = $conn->prepare("SELECT divisions_count, name FROM classes WHERE id = ? LIMIT 1");
                            if ($cd_stmt) {
                                $cd_stmt->bind_param("i", $assignment['class_id']);
                                $cd_stmt->execute();
                                $cd_res = $cd_stmt->get_result();
                                $cd_row = $cd_res->fetch_assoc();
                                $cd_stmt->close();
                                $div_count = $cd_row ? (int)$cd_row['divisions_count'] : 1;
                                $class_base_name = $cd_row ? $cd_row['name'] : '';
                            } else {
                                $div_count = 1;
                                $class_base_name = '';
                            }

                            // attempt to find a free division label slot (A..Z, AA..)
                            for ($didx = 0; $didx < $div_count; $didx++) {
                                $n = $didx;
                                $label = '';
                                while (true) {
                                    $label = chr(65 + ($n % 26)) . $label;
                                    $n = intdiv($n, 26) - 1;
                                    if ($n < 0) break;
                                }
                                // check this specific division hasn't an entry
                                $check_class_stmt->bind_param("isii", $assignment['class_id'], $label, $day['id'], $time_slot['id']);
                                $check_class_stmt->execute();
                                $class_count = $check_class_stmt->get_result()->fetch_assoc()['count'];
                                if ($class_count == 0) { $division_label_to_use = $label; break; }
                            }
                            // if none free, class_count will be >0
                        } else {
                            $check_class_stmt->bind_param("iii", $assignment['class_id'], $day['id'], $time_slot['id']);
                            $check_class_stmt->execute();
                            $class_count = $check_class_stmt->get_result()->fetch_assoc()['count'];
                        }

                        // we'll lookup lecturer_course later; for now assume lecturer might conflict after we fetch it
                        if ($room_count == 0 && $class_count == 0) {
                            // Get a default lecturer_course (and lecturer) for this course
                            $lecturer_course_sql = "SELECT lc.id, lc.lecturer_id FROM lecturer_courses lc WHERE lc.course_id = ? LIMIT 1";
                            $lecturer_course_stmt = $conn->prepare($lecturer_course_sql);
                            $lecturer_course_stmt->bind_param("i", $assignment['course_id']);
                            $lecturer_course_stmt->execute();
                            $lecturer_course_result = $lecturer_course_stmt->get_result();
                            $lecturer_course = $lecturer_course_result->fetch_assoc();
                            $lecturer_course_stmt->close();

                            if ($lecturer_course) {
                                // Determine which id to use when checking conflicts/insert depending on schema
                                $lect_param = $has_t_lecturer_course ? $lecturer_course['id'] : $lecturer_course['lecturer_id'];

                                // Ensure lecturer (or lecturer_course) is free at this day/time
                                $check_lecturer_stmt->bind_param("iii", $lect_param, $day['id'], $time_slot['id']);
                                $check_lecturer_stmt->execute();
                                $lect_count = $check_lecturer_stmt->get_result()->fetch_assoc()['count'];
                                if ($lect_count > 0) {
                                    // lecturer busy, try another slot
                                    continue;
                                }

                                // Insert timetable entry using appropriate columns for current schema
                                if ($has_t_class_course && $has_t_lecturer_course) {
                                    // Newer schema: use class_course_id and lecturer_course_id — include semester and academic_year
                                    if ($has_division_col && $division_label_to_use !== null) {
                                        $insert_sql = "INSERT INTO timetable (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, division_label, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                                        $insert_stmt = $conn->prepare($insert_sql);
                                        if ($insert_stmt) {
                                            $p_class_course_id = $assignment['id'];
                                            $p_lecturer_course_id = $lecturer_course['id'];
                                            $p_day_id = $day['id'];
                                            $p_time_slot_id = $time_slot['id'];
                                            $p_room_id = $room['id'];
                                            $p_div_label = $division_label_to_use;
                                            $p_semester = $semester;
                                            $p_academic_year = $academic_year;
                                            $insert_stmt->bind_param("iiiiisss", $p_class_course_id, $p_lecturer_course_id, $p_day_id, $p_time_slot_id, $p_room_id, $p_div_label, $p_semester, $p_academic_year);
                                        }
                                    } else {
                                        $insert_sql = "INSERT INTO timetable (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?)";
                                        $insert_stmt = $conn->prepare($insert_sql);
                                        if ($insert_stmt) {
                                            $p_class_course_id = $assignment['id'];
                                            $p_lecturer_course_id = $lecturer_course['id'];
                                            $p_day_id = $day['id'];
                                            $p_time_slot_id = $time_slot['id'];
                                            $p_room_id = $room['id'];
                                            $p_semester = $semester;
                                            $p_academic_year = $academic_year;
                                            $insert_stmt->bind_param("iiiiiss", $p_class_course_id, $p_lecturer_course_id, $p_day_id, $p_time_slot_id, $p_room_id, $p_semester, $p_academic_year);
                                        }
                                    }
                                } else {
                                    // Older schema: use class_id and lecturer_id — ensure we also populate course_id to satisfy NOT NULL constraint
                                    if ($has_division_col && $division_label_to_use !== null) {
                                        $insert_sql = "INSERT INTO timetable (class_id, course_id, lecturer_id, day_id, time_slot_id, room_id, division_label, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                        $insert_stmt = $conn->prepare($insert_sql);
                                        if ($insert_stmt) {
                                            $p_class_id = $assignment['class_id'];
                                            $p_course_id = $assignment['course_id'];
                                            $p_lecturer_id = $lecturer_course['lecturer_id'];
                                            $p_day_id = $day['id'];
                                            $p_time_slot_id = $time_slot['id'];
                                            $p_room_id = $room['id'];
                                            $p_div_label = $division_label_to_use;
                                            $p_semester = $semester;
                                            $p_academic_year = $academic_year;
                                            $insert_stmt->bind_param("iiiiiisss", $p_class_id, $p_course_id, $p_lecturer_id, $p_day_id, $p_time_slot_id, $p_room_id, $p_div_label, $p_semester, $p_academic_year);
                                        }
                                    } else {
                                        $insert_sql = "INSERT INTO timetable (class_id, course_id, lecturer_id, day_id, time_slot_id, room_id, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                                        $insert_stmt = $conn->prepare($insert_sql);
                                        if ($insert_stmt) {
                                            $p_class_id = $assignment['class_id'];
                                            $p_course_id = $assignment['course_id'];
                                            $p_lecturer_id = $lecturer_course['lecturer_id'];
                                            $p_day_id = $day['id'];
                                            $p_time_slot_id = $time_slot['id'];
                                            $p_room_id = $room['id'];
                                            $p_semester = $semester;
                                            $p_academic_year = $academic_year;
                                            $insert_stmt->bind_param("iiiiiiss", $p_class_id, $p_course_id, $p_lecturer_id, $p_day_id, $p_time_slot_id, $p_room_id, $p_semester, $p_academic_year);
                                        }
                                    }
                                }

                                if (isset($insert_stmt) && $insert_stmt && $insert_stmt->execute()) {
                                    $placed = true;
                                    $insert_stmt->close();
                                    break; // placed successfully
                                }
                                if (isset($insert_stmt) && $insert_stmt) $insert_stmt->close();
                            } else {
                                // No lecturer assigned to this course, cannot place
                                break;
                            }
                        }
                    }
                    // after attempts
                    if (!$placed) {
                        $error_count++;
                    } else {
                        $success_count++;
                    }
                }
            }

            // close prepared statements if they exist
            if (isset($assignments_stmt) && $assignments_stmt) $assignments_stmt->close();
            if (isset($stream_days_stmt) && $stream_days_stmt) $stream_days_stmt->close();

            if ($success_count > 0) {
                $msg = "Timetable generated successfully! $success_count entries created.";
                if ($error_count > 0) {
                    $msg .= " $error_count entries failed.";
                }
                redirect_with_flash('generate_timetable.php', 'success', $msg);
            } else {
                $error_message = "No timetable entries could be generated.";
            }
        } else {
            $error_message = "No active streams found. Please create streams first.";
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

                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex gap-2 align-items-center">
                                <input type="text" name="timetable_name" class="form-control form-control-sm" placeholder="Timetable name e.g. 2024/2025 First Semester" required style="max-width:300px">
                                <select name="semester" class="form-select form-select-sm" required style="max-width:140px">
                                    <option value="">Semester</option>
                                    <option value="first">First</option>
                                    <option value="second">Second</option>
                                </select>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <form method="POST" class="d-flex gap-2 align-items-center">
                                    <input type="hidden" name="action" value="generate_lecture_timetable">
                                    <input type="hidden" name="academic_year">
                                    <input type="hidden" name="semester">
                                    <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('This will clear existing timetable for the selected year/semester and generate a new lecture timetable. Continue?')">
                                        <i class="fas fa-magic me-2"></i>Generate Lecture Timetable
                                    </button>
                                </form>
                                <form method="POST" class="d-flex gap-2 align-items-center">
                                    <input type="hidden" name="action" value="generate_exams_timetable">
                                    <input type="hidden" name="academic_year">
                                    <input type="hidden" name="semester">
                                    <button type="submit" class="btn btn-outline-primary btn-lg">
                                        <i class="fas fa-file-alt me-2"></i>Generate Exams Timetable
                                    </button>
                                </form>
                            </div>
                        </div>
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
            if ($stream_time_slots_count == 0) {
                // Fallback to all time slots from time_slots table (not just mandatory ones)
                $stream_time_slots_count = $conn->query("SELECT COUNT(*) as count FROM time_slots WHERE is_break = 0")->fetch_assoc()['count'];
            }
        } catch (Exception $e) {
            $schema_error = "Database schema issue: " . $e->getMessage();
            // Fallback to all time slots check
            try {
                $stream_time_slots_count = $conn->query("SELECT COUNT(*) as count FROM time_slots WHERE is_break = 0")->fetch_assoc()['count'];
            } catch (Exception $e2) {
                $stream_time_slots_count = 0;
                $schema_error = "Critical database error: " . $e2->getMessage();
            }
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
                                            <i class="fas fa-info-circle ms-1" data-bs-toggle="tooltip" title="Available time periods (stream-specific or all time slots if none mapped)"></i>
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

        <?php if ($total_assignments > 0): ?>
            <div class="row m-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Recent Class-Course Assignments</h6></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Class</th>
                                            <th>Course</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Build recent assignments query; respect selected stream if classes.stream_id exists
                                        $recent_where = "cc.is_active = 1";
                                        $col = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
                                        $has_stream_col = ($col && $col->num_rows > 0);
                                        if ($has_stream_col && !empty($current_stream_id)) {
                                            $recent_where .= " AND c.stream_id = " . intval($current_stream_id);
                                        }
                                        if ($col) $col->close();

                                        $recent_sql = "SELECT c.name as class_name, co.`code` AS course_code, co.`name` AS course_name
                                                      FROM class_courses cc
                                                      JOIN classes c ON cc.class_id = c.id
                                                      JOIN courses co ON cc.course_id = co.id
                                                      WHERE " . $recent_where . "
                                                      ORDER BY cc.created_at DESC
                                                      LIMIT 10";
                                        $recent_result = $conn->query($recent_sql);
                                        while ($row = $recent_result->fetch_assoc()):
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['course_code'] . ' - ' . $row['course_name']); ?></td>
                                            <td><span class="badge bg-success">Active</span></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    try {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            if (window.bootstrap && bootstrap.Tooltip) {
                new bootstrap.Tooltip(tooltipTriggerEl);
            }
        });
    } catch (e) { /* ignore */ }
    
    // Handle form submission to populate hidden fields
    document.querySelectorAll('form[method="POST"]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var timetableName = document.querySelector('input[name="timetable_name"]').value;
            var semester = document.querySelector('select[name="semester"]').value;
            
            // Populate hidden fields in this form
            var academicYearField = form.querySelector('input[name="academic_year"]');
            var semesterField = form.querySelector('input[name="semester"]');
            
            if (academicYearField) academicYearField.value = timetableName;
            if (semesterField) semesterField.value = semester;
            
            // Validate required fields
            if (!timetableName || !semester) {
                e.preventDefault();
                alert('Please fill in both Timetable Name and Semester fields.');
                return false;
            }
        });
    });
});
</script>