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
                        $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql_content)));
                        
                        $conn->autocommit(false);
                        
                        try {
                            foreach ($statements as $statement) {
                                if (empty($statement) || strpos(trim($statement), '--') === 0) {
                                    continue;
                                }
                                
                                if (!$conn->query($statement)) {
                                    throw new Exception("SQL Error: " . $conn->error);
                                }
                            }
                            
                            $conn->commit();
                            logStep("✅ Successfully applied: " . basename($file_path), 'success');
                            return true;
                            
                        } catch (Exception $e) {
                            $conn->rollback();
                            logStep("❌ Failed to apply " . basename($file_path) . ": " . $e->getMessage(), 'error');
                            $migration_success = false;
                            return false;
                        } finally {
                            $conn->autocommit(true);
                        }
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
                    $orphaned_tt = $conn->query("SELECT COUNT(*) as count FROM timetable t LEFT JOIN class_courses cc ON t.class_course_id = cc.id WHERE cc.id IS NULL")->fetch_assoc()['count'];
                    if ($orphaned_tt > 0) {
                        logStep("⚠️ Found $orphaned_tt orphaned timetable records", 'warning');
                    } else {
                        logStep("✅ No orphaned timetable records", 'success');
                    }
                    
                    // Step 4: Test new functions and procedures
                    logStep("🧪 Testing new functions and procedures...", 'info');
                    
                    try {
                        // Test validation function
                        $test_result = $conn->query("SELECT validate_class_course_assignment_professional(1, 1) as test_result")->fetch_assoc()['test_result'];
                        $validation_data = json_decode($test_result, true);
                        if (is_array($validation_data) && isset($validation_data['valid'])) {
                            logStep("✅ Validation function working correctly", 'success');
                        } else {
                            logStep("⚠️ Validation function may have issues", 'warning');
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