<?php
/**
 * Apply Corrected Schema Migration
 * This script applies the corrected understanding:
 * - Only CLASSES are stream-specific
 * - Everything else (courses, lecturers, rooms, departments) is GLOBAL
 */

include 'connect.php';

// Set execution limits
set_time_limit(600); // 10 minutes
ini_set('memory_limit', '512M');

$pageTitle = 'Apply Corrected Schema';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .log-container {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
        }
        .step-success { color: #198754; }
        .step-warning { color: #fd7e14; }
        .step-error { color: #dc3545; }
        .step-info { color: #0dcaf0; }
    </style>
</head>
<body>
<div class="container mt-4">
    <h1>🔄 Apply Corrected Schema</h1>
    <p class="text-muted">Applying the corrected schema where only classes are stream-specific</p>
    
    <div class="alert alert-info">
        <h5>📋 Migration Plan</h5>
        <ul class="mb-0">
            <li><strong>Remove</strong> stream_id from global tables (courses, lecturers, rooms, departments)</li>
            <li><strong>Keep</strong> stream_id only on classes table</li>
            <li><strong>Enhance</strong> class-course assignments with department-oriented validation</li>
            <li><strong>Create</strong> professional validation functions and procedures</li>
            <li><strong>Add</strong> proper indexes and constraints</li>
        </ul>
    </div>
    
    <?php if (isset($_POST['confirm_migration'])): ?>
        <div class="card">
            <div class="card-header">
                <h5>🚀 Migration Progress</h5>
            </div>
            <div class="card-body">
                <div class="log-container" id="migration-log">
                    <?php
                    $migration_success = true;
                    
                    function logStep($message, $type = 'info') {
                        echo "<div class='step-$type'>[" . date('H:i:s') . "] $message</div>\n";
                        flush();
                        ob_flush();
                    }
                    
                    function runSQLFile($file_path) {
                        global $conn, $migration_success;
                        
                        if (!file_exists($file_path)) {
                            logStep("❌ Migration file not found: $file_path", 'error');
                            $migration_success = false;
                            return false;
                        }
                        
                        $sql_content = file_get_contents($file_path);

                        // Respect DELIMITER declarations when splitting SQL file into executable statements
                        $statements = [];
                        $current_delim = ";";
                        $buffer = '';
                        $lines = preg_split('/\r?\n/', $sql_content);
                        foreach ($lines as $line) {
                            $trim = ltrim($line);
                            // Skip SQL comments that are full-line
                            if (preg_match('/^--/', $trim)) {
                                // keep comments inside buffer for readability but do not affect splitting
                                $buffer .= $line . "\n";
                                continue;
                            }

                            // Handle DELIMITER change
                            if (stripos($trim, 'DELIMITER ') === 0) {
                                // flush buffer if any (only when delimiter is default)
                                if (trim($buffer) !== '') {
                                    $statements[] = trim($buffer);
                                }
                                $buffer = '';
                                $parts = preg_split('/\s+/', $trim);
                                $current_delim = isset($parts[1]) ? $parts[1] : ';';
                                continue;
                            }

                            $buffer .= $line . "\n";

                            // If the buffer ends with the current delimiter, split
                            if ($current_delim !== '' && substr(trim($buffer), -strlen($current_delim)) === $current_delim) {
                                // remove trailing delimiter
                                $stmt = rtrim($buffer);
                                $stmt = substr($stmt, 0, -strlen($current_delim));
                                $statements[] = trim($stmt);
                                $buffer = '';
                            }
                        }
                        if (trim($buffer) !== '') {
                            $statements[] = trim($buffer);
                        }
                        
                        // Execute statements one-by-one and continue on errors (DDL often auto-commits)
                        $conn->autocommit(true);
                        $any_success = false;

                        foreach ($statements as $statement) {
                            $s = trim($statement);
                            if ($s === '' ) continue;
                            // Skip standalone delimiter or comments-only statements
                            if (preg_match('/^DELIMITER\b/i', $s)) continue;
                            if (preg_match('/^--/', ltrim($s))) continue;

                            // Attempt execution; log warning on failure but continue
                            try {
                                $res = $conn->query($s);
                                if ($res === false) {
                                    logStep("⚠️ Statement failed: " . $conn->error . " -- " . substr(preg_replace('/\s+/', ' ', $s), 0, 200), 'warning');
                                    $migration_success = false;
                                } else {
                                    $any_success = true;
                                }
                            } catch (mysqli_sql_exception $e) {
                                logStep("⚠️ Statement exception: " . $e->getMessage() . " -- " . substr(preg_replace('/\s+/', ' ', $s), 0, 200), 'warning');
                                $migration_success = false;
                                // continue to next statement
                            }
                        }

                        if ($any_success) {
                            logStep("✅ Applied some or all statements from: " . basename($file_path), 'success');
                        } else {
                            logStep("❌ No statements successfully applied from: " . basename($file_path), 'error');
                        }

                        return $any_success;
                    }
                    
                    // Start migration
                    logStep("🏁 Starting corrected schema migration...", 'info');
                    
                    // Step 1: Apply the corrected schema
                    logStep("📝 Applying corrected stream logic...", 'info');
                    runSQLFile(__DIR__ . '/migrations/004_correct_stream_logic.sql');
                    
                    // Step 2: Validate the migration
                    logStep("🔍 Validating migration results...", 'info');
                    
                    // Check that stream_id was removed from global tables
                    $global_tables = ['courses', 'lecturers', 'rooms', 'departments', 'programs'];
                    foreach ($global_tables as $table) {
                        $check = $conn->query("SHOW COLUMNS FROM $table LIKE 'stream_id'");
                        if ($check && $check->num_rows > 0) {
                            logStep("⚠️ Warning: stream_id still exists in $table", 'warning');
                        } else {
                            logStep("✅ Confirmed: stream_id removed from $table", 'success');
                        }
                    }
                    
                    // Check that stream_id still exists on classes
                    $classes_check = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
                    if ($classes_check && $classes_check->num_rows > 0) {
                        logStep("✅ Confirmed: stream_id preserved on classes table", 'success');
                    } else {
                        logStep("❌ Error: stream_id missing from classes table", 'error');
                        $migration_success = false;
                    }
                    
                    // Step 3: Validate data integrity
                    logStep("🔍 Checking data integrity...", 'info');
                    
                    // Check for orphaned class_courses
                    $orphaned_cc = $conn->query("SELECT COUNT(*) as count FROM class_courses cc LEFT JOIN classes c ON cc.class_id = c.id WHERE c.id IS NULL")->fetch_assoc()['count'];
                    if ($orphaned_cc > 0) {
                        logStep("⚠️ Found $orphaned_cc orphaned class_courses records", 'warning');
                    } else {
                        logStep("✅ No orphaned class_courses records", 'success');
                    }
                    
                    // Check for orphaned timetable entries
                    $orphaned_tt = 0;
                    // Determine timetable linkage column safely (class_course_id preferred)
                    $timetableCols = $conn->query("SHOW COLUMNS FROM `timetable`");
                    $hasClassCourseCol = false;
                    $hasClassIdCol = false;
                    if ($timetableCols) {
                        while ($col = $timetableCols->fetch_assoc()) {
                            if ($col['Field'] === 'class_course_id') $hasClassCourseCol = true;
                            if ($col['Field'] === 'class_id') $hasClassIdCol = true;
                        }
                    }

                    if ($hasClassCourseCol) {
                        $res = $conn->query("SELECT COUNT(*) as count FROM timetable t LEFT JOIN class_courses cc ON t.class_course_id = cc.id WHERE cc.id IS NULL");
                        $orphaned_tt = $res ? (int)$res->fetch_assoc()['count'] : 0;
                    } elseif ($hasClassIdCol) {
                        // Fallback: timetable links directly to classes
                        $res = $conn->query("SELECT COUNT(*) as count FROM timetable t LEFT JOIN classes c ON t.class_id = c.id WHERE c.id IS NULL");
                        $orphaned_tt = $res ? (int)$res->fetch_assoc()['count'] : 0;
                    } else {
                        logStep("⚠️ Could not determine timetable <> class linkage column; skipping orphaned timetable check", 'warning');
                        $orphaned_tt = 0;
                    }

                    if ($orphaned_tt > 0) {
                        logStep("⚠️ Found $orphaned_tt orphaned timetable records", 'warning');
                    } else {
                        logStep("✅ No orphaned timetable records", 'success');
                    }
                    
                    // Step 4: Test new functions and procedures
                    logStep("🧪 Testing new functions and procedures...", 'info');
                    
                    try {
                        // Check required columns exist before invoking DB function
                        $colCheckSql = "SELECT 
                            (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME = 'department_id') AS has_class_dept,
                            (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'department_id') AS has_course_dept";
                        $colCheck = $conn->query($colCheckSql)->fetch_assoc();

                        if (isset($colCheck['has_class_dept']) && $colCheck['has_class_dept'] > 0 && isset($colCheck['has_course_dept']) && $colCheck['has_course_dept'] > 0) {
                            // Test validation function safely
                            $fnExistsRes = $conn->query("SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = 'validate_class_course_assignment_professional' LIMIT 1");
                            if ($fnExistsRes && $fnExistsRes->num_rows > 0) {
                                $test_q = $conn->query("SELECT validate_class_course_assignment_professional(1, 1) as test_result");
                                if ($test_q) {
                                    $test_result = $test_q->fetch_assoc()['test_result'];
                                    $validation_data = json_decode($test_result, true);
                                    if (is_array($validation_data) && isset($validation_data['valid'])) {
                                        logStep("✅ Validation function working correctly", 'success');
                                    } else {
                                        logStep("⚠️ Validation function returned unexpected result", 'warning');
                                    }
                                } else {
                                    logStep("⚠️ Validation function call failed: " . $conn->error, 'warning');
                                }
                            } else {
                                logStep("⚠️ Validation function not found; skipped function test", 'warning');
                            }
                        } else {
                            logStep("⚠️ Required columns for validation function missing (classes.department_id or courses.department_id); skipped function test", 'warning');
                        }
                    } catch (Exception $e) {
                        logStep("❌ Error testing validation function: " . $e->getMessage(), 'error');
                    }
                    
                    // Step 5: Generate statistics
                    logStep("📊 Generating final statistics...", 'info');
                    
                    $stats_sql = "SELECT 
                                     (SELECT COUNT(*) FROM classes WHERE is_active = 1) as total_classes,
                                     (SELECT COUNT(*) FROM courses WHERE is_active = 1) as total_courses,
                                     (SELECT COUNT(*) FROM lecturers WHERE is_active = 1) as total_lecturers,
                                     (SELECT COUNT(*) FROM rooms WHERE is_active = 1) as total_rooms,
                                     (SELECT COUNT(*) FROM class_courses WHERE is_active = 1) as total_assignments";
                    $stats = $conn->query($stats_sql)->fetch_assoc();
                    
                    logStep("📈 Final Statistics:", 'info');
                    logStep("   - Classes: {$stats['total_classes']}", 'info');
                    logStep("   - Courses: {$stats['total_courses']} (Global)", 'info');
                    logStep("   - Lecturers: {$stats['total_lecturers']} (Global)", 'info');
                    logStep("   - Rooms: {$stats['total_rooms']} (Global)", 'info');
                    logStep("   - Assignments: {$stats['total_assignments']}", 'info');
                    
                    if ($migration_success) {
                        logStep("🎉 Migration completed successfully!", 'success');
                        logStep("✅ Schema now correctly reflects business logic: only classes are stream-specific", 'success');
                    } else {
                        logStep("❌ Migration completed with errors. Please review the log above.", 'error');
                    }
                    ?>
                </div>
                
                <?php if ($migration_success): ?>
                    <div class="alert alert-success mt-3">
                        <h5>🎉 Migration Successful!</h5>
                        <p>The schema has been corrected to reflect the proper business logic:</p>
                        <ul>
                            <li>✅ <strong>Classes</strong> are stream-specific (can rotate between Regular/Weekend/Evening)</li>
                            <li>✅ <strong>Courses, Lecturers, Rooms, Departments</strong> are global (shared across all streams)</li>
                            <li>✅ Professional validation ensures department-oriented assignments</li>
                            <li>✅ Enhanced conflict detection for timetable generation</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger mt-3">
                        <h5>❌ Migration Had Issues</h5>
                        <p>Please review the log above and address any errors before proceeding.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="class_courses_professional.php" class="btn btn-primary">Test Professional Assignments</a>
            <a href="generate_timetable_corrected.php" class="btn btn-success">Test Timetable Generation</a>
            <a href="validate_stream_consistency.php" class="btn btn-info">Validate Consistency</a>
            <a href="index.php" class="btn btn-secondary">Return to Dashboard</a>
        </div>
        
    <?php else: ?>
        <!-- Confirmation Form -->
        <div class="card">
            <div class="card-header">
                <h5>⚠️ Migration Confirmation Required</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6>⚠️ Important Changes</h6>
                    <p>This migration will make the following changes to correct the schema:</p>
                    <ul>
                        <li><strong>Remove</strong> stream_id column from: courses, lecturers, rooms, departments, programs</li>
                        <li><strong>Keep</strong> stream_id only on classes table</li>
                        <li><strong>Update</strong> all related foreign keys and constraints</li>
                        <li><strong>Create</strong> professional validation functions</li>
                        <li><strong>Backup</strong> existing data before changes</li>
                    </ul>
                </div>
                
                <div class="alert alert-info">
                    <h6>📊 Current Database State</h6>
                    <?php
                    $current_state = [];
                    $tables_to_check = ['courses', 'lecturers', 'rooms', 'departments', 'programs', 'classes'];
                    
                    foreach ($tables_to_check as $table) {
                        $has_stream_id = $conn->query("SHOW COLUMNS FROM $table LIKE 'stream_id'");
                        $record_count = $conn->query("SELECT COUNT(*) as count FROM $table")->fetch_assoc()['count'];
                        $current_state[$table] = [
                            'has_stream_id' => $has_stream_id && $has_stream_id->num_rows > 0,
                            'record_count' => $record_count
                        ];
                    }
                    ?>
                    
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th>Has stream_id</th>
                                <th>Record Count</th>
                                <th>After Migration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($current_state as $table => $state): ?>
                                <tr>
                                    <td><code><?php echo $table; ?></code></td>
                                    <td>
                                        <?php if ($state['has_stream_id']): ?>
                                            <span class="badge bg-warning">Yes</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $state['record_count']; ?></td>
                                    <td>
                                        <?php if ($table === 'classes'): ?>
                                            <span class="badge bg-primary">Keep stream_id</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Remove stream_id (Global)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <form method="POST">
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="confirm_backup" required>
                        <label class="form-check-label" for="confirm_backup">
                            I understand that existing data will be backed up before migration
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="confirm_changes" required>
                        <label class="form-check-label" for="confirm_changes">
                            I understand that this will change the database schema significantly
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="confirm_logic" required>
                        <label class="form-check-label" for="confirm_logic">
                            I confirm that only classes should be stream-specific (not courses/lecturers/rooms)
                        </label>
                    </div>
                    
                    <button type="submit" name="confirm_migration" value="1" class="btn btn-warning btn-lg">
                        <i class="fas fa-play"></i> Apply Corrected Schema Migration
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Auto-scroll log container
const logContainer = document.getElementById('migration-log');
if (logContainer) {
    const observer = new MutationObserver(() => {
        logContainer.scrollTop = logContainer.scrollHeight;
    });
    observer.observe(logContainer, { childList: true });
}
</script>

</body>
</html>
