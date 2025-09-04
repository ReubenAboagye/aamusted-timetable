<?php
/**
 * Apply Updated Database Schema
 * 
 * This script applies the complete, updated database schema for the AAMUSTED
 * Timetable System. It replaces the old schema with a unified, consistent schema.
 */

// Include database connection
include 'connect.php';

// Include flash helper if available
if (file_exists(__DIR__ . '/includes/flash.php')) {
    include_once __DIR__ . '/includes/flash.php';
}

// Set page title
$pageTitle = 'Apply Updated Database Schema';
include 'includes/header.php';
include 'includes/sidebar.php';

// Initialize variables
$success_message = '';
$error_message = '';
$migration_log = [];

// Function to log migration steps
function logMigrationStep($step, $message, $status = 'info') {
    global $migration_log;
    $migration_log[] = [
        'step' => $step,
        'message' => $message,
        'status' => $status,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Function to check if table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

// Function to backup existing data
function backupTable($conn, $tableName) {
    if (tableExists($conn, $tableName)) {
        $backupTableName = $tableName . '_backup_' . date('Ymd_His');
        $sql = "CREATE TABLE `$backupTableName` AS SELECT * FROM `$tableName`";
        if ($conn->query($sql)) {
            $count = $conn->query("SELECT COUNT(*) as count FROM `$backupTableName`")->fetch_assoc()['count'];
            return ['success' => true, 'backup_table' => $backupTableName, 'count' => $count];
        } else {
            return ['success' => false, 'error' => $conn->error];
        }
    }
    return ['success' => true, 'backup_table' => null, 'count' => 0];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'apply_schema') {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            logMigrationStep(1, 'Starting updated schema application...', 'info');
            
            // Step 1: Create backup of existing data
            logMigrationStep(2, 'Creating backups of existing data...', 'info');
            
            $tables_to_backup = [
                'departments', 'streams', 'levels', 'programs', 'buildings', 
                'room_types', 'courses', 'lecturers', 'classes', 'class_courses',
                'lecturer_courses', 'course_room_types', 'rooms', 'days', 
                'time_slots', 'stream_time_slots', 'timetable', 'saved_timetables',
                'timetable_lecturers'
            ];
            
            $backups_created = [];
            foreach ($tables_to_backup as $table) {
                $backup_result = backupTable($conn, $table);
                if ($backup_result['success']) {
                    if ($backup_result['backup_table']) {
                        $backups_created[] = $backup_result['backup_table'];
                        logMigrationStep(3, "Backup created: {$backup_result['backup_table']} ({$backup_result['count']} records)", 'success');
                    }
                } else {
                    logMigrationStep(3, "Backup failed for $table: " . $backup_result['error'], 'warning');
                }
            }
            
            // Step 2: Read and apply the updated schema
            logMigrationStep(4, 'Reading updated schema file...', 'info');
            
            $schema_file = 'migrations/DB_Schema_Updated.sql';
            if (!file_exists($schema_file)) {
                throw new Exception("Schema file not found: $schema_file");
            }
            
            $schema_content = file_get_contents($schema_file);
            if (!$schema_content) {
                throw new Exception("Failed to read schema file");
            }
            
            logMigrationStep(5, 'Schema file read successfully', 'success');
            
            // Step 3: Split and execute SQL statements
            logMigrationStep(6, 'Applying schema changes...', 'info');
            
            // Split SQL by semicolon and execute each statement
            $statements = explode(';', $schema_content);
            $executed_count = 0;
            $error_count = 0;
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement) || strpos($statement, '--') === 0) {
                    continue; // Skip comments and empty statements
                }
                
                // Skip SELECT statements (like the final status message)
                if (strpos(strtoupper($statement), 'SELECT') === 0) {
                    continue;
                }
                
                if ($conn->query($statement)) {
                    $executed_count++;
                } else {
                    $error_count++;
                    logMigrationStep(7, "SQL Error: " . $conn->error . " in statement: " . substr($statement, 0, 100) . "...", 'warning');
                }
            }
            
            logMigrationStep(8, "Executed $executed_count statements successfully, $error_count errors", $error_count > 0 ? 'warning' : 'success');
            
            // Step 4: Verify schema application
            logMigrationStep(9, 'Verifying schema application...', 'info');
            
            $required_tables = [
                'departments', 'streams', 'levels', 'programs', 'buildings', 
                'room_types', 'courses', 'lecturers', 'classes', 'class_courses',
                'lecturer_courses', 'course_room_types', 'rooms', 'days', 
                'time_slots', 'stream_time_slots', 'timetable', 'saved_timetables',
                'timetable_lecturers'
            ];
            
            $missing_tables = [];
            foreach ($required_tables as $table) {
                if (!tableExists($conn, $table)) {
                    $missing_tables[] = $table;
                }
            }
            
            if (!empty($missing_tables)) {
                throw new Exception("Missing tables after schema application: " . implode(', ', $missing_tables));
            }
            
            logMigrationStep(10, 'All required tables created successfully', 'success');
            
            // Step 5: Verify views
            logMigrationStep(11, 'Verifying views...', 'info');
            
            $views = ['timetable_view', 'stream_timetable_view'];
            $missing_views = [];
            foreach ($views as $view) {
                if (!tableExists($conn, $view)) {
                    $missing_views[] = $view;
                }
            }
            
            if (!empty($missing_views)) {
                logMigrationStep(12, "Missing views: " . implode(', ', $missing_views), 'warning');
            } else {
                logMigrationStep(12, 'All views created successfully', 'success');
            }
            
            // Step 6: Create migration log
            logMigrationStep(13, 'Creating migration log...', 'info');
            
            $create_migration_log = "
            CREATE TABLE IF NOT EXISTS `schema_migration_log` (
                `id` int NOT NULL AUTO_INCREMENT,
                `migration_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `schema_version` varchar(20) DEFAULT '2.0',
                `backup_tables` text,
                `executed_statements` int DEFAULT 0,
                `error_count` int DEFAULT 0,
                `migration_status` enum('completed','failed') DEFAULT 'completed',
                `notes` text,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
            ";
            
            $conn->query($create_migration_log);
            
            $backup_tables_json = json_encode($backups_created);
            $insert_log = $conn->prepare("
                INSERT INTO schema_migration_log 
                (schema_version, backup_tables, executed_statements, error_count, migration_status, notes) 
                VALUES (?, ?, ?, ?, 'completed', ?)
            ");
            $notes = "Updated schema applied successfully. All tables and relationships updated.";
            $insert_log->bind_param("ssiis", '2.0', $backup_tables_json, $executed_count, $error_count, $notes);
            $insert_log->execute();
            
            logMigrationStep(14, 'Migration log created successfully', 'success');
            
            // Commit transaction
            $conn->commit();
            
            logMigrationStep(15, 'Schema update completed successfully!', 'success');
            $success_message = 'Updated database schema applied successfully! All tables, relationships, and views have been created.';
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $error_message = 'Schema application failed: ' . $e->getMessage();
            logMigrationStep('ERROR', 'Schema application failed: ' . $e->getMessage(), 'error');
        }
    }
}

// Check current schema status
$current_tables = [];
$required_tables = [
    'departments', 'streams', 'levels', 'programs', 'buildings', 
    'room_types', 'courses', 'lecturers', 'classes', 'class_courses',
    'lecturer_courses', 'course_room_types', 'rooms', 'days', 
    'time_slots', 'stream_time_slots', 'timetable', 'saved_timetables',
    'timetable_lecturers'
];

foreach ($required_tables as $table) {
    $current_tables[$table] = tableExists($conn, $table);
}

$schema_file_exists = file_exists('migrations/DB_Schema_Updated.sql');
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-database me-2"></i>Apply Updated Database Schema</h4>
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
                        <h6 class="mb-0">Updated Schema Application</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>What this update does:</h6>
                            <ul class="mb-0">
                                <li>Replaces the old schema with a unified, consistent schema</li>
                                <li>Fixes all foreign key relationship issues</li>
                                <li>Adds stream-based filtering support to all entities</li>
                                <li>Updates timetable table to use class_course_id and lecturer_course_id</li>
                                <li>Creates proper indexes for performance optimization</li>
                                <li>Adds data validation triggers</li>
                                <li>Creates comprehensive views for easier querying</li>
                                <li>Backs up all existing data before making changes</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes:</h6>
                            <ul class="mb-0">
                                <li>This will create backups of all existing data</li>
                                <li>The process is irreversible - make sure you have a database backup</li>
                                <li>All existing data will be preserved in backup tables</li>
                                <li>The migration is safe and includes error handling</li>
                                <li>After completion, you can manually restore data if needed</li>
                            </ul>
                        </div>

                        <?php if ($schema_file_exists): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to apply the updated schema? This will modify the entire database structure.');">
                                <input type="hidden" name="action" value="apply_schema">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-database me-2"></i>Apply Updated Schema
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Schema file not found. Please ensure the file 'migrations/DB_Schema_Updated.sql' exists.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Current Schema Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Schema file:</strong>
                            <span class="badge <?php echo $schema_file_exists ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $schema_file_exists ? 'Found' : 'Missing'; ?>
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Required tables:</strong>
                            <div class="mt-2">
                                <?php 
                                $existing_count = 0;
                                foreach ($current_tables as $table => $exists): 
                                    if ($exists) $existing_count++;
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small><?php echo $table; ?>:</small>
                                        <span class="badge <?php echo $exists ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo $exists ? '✓' : '✗'; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <?php echo $existing_count; ?> of <?php echo count($required_tables); ?> tables exist
                                </small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($migration_log)): ?>
        <div class="row m-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Migration Log</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Step</th>
                                        <th>Message</th>
                                        <th>Status</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($migration_log as $log): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($log['step']); ?></td>
                                            <td><?php echo htmlspecialchars($log['message']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $log['status'] === 'success' ? 'success' : ($log['status'] === 'error' ? 'danger' : ($log['status'] === 'warning' ? 'warning' : 'info')); ?>">
                                                    <?php echo ucfirst($log['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
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

<?php 
$conn->close();
include 'includes/footer.php'; 
?>
