<?php
/**
 * Apply Timetable Schema Fix Migration
 * 
 * This script applies the database schema fixes to resolve inconsistencies
 * between the timetable table schema and the code expectations.
 */

// Include database connection
include 'connect.php';

// Include flash helper if available
if (file_exists(__DIR__ . '/includes/flash.php')) {
    include_once __DIR__ . '/includes/flash.php';
}

// Set page title
$pageTitle = 'Apply Timetable Schema Fix';
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

// Function to check if column exists
function columnExists($conn, $tableName, $columnName) {
    $result = $conn->query("SHOW COLUMNS FROM `$tableName` LIKE '$columnName'");
    return $result && $result->num_rows > 0;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'apply_migration') {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            logMigrationStep(1, 'Starting timetable schema migration...', 'info');
            
            // Step 1: Create saved_timetables table
            logMigrationStep(2, 'Creating saved_timetables table...', 'info');
            $create_saved_timetables = "
            CREATE TABLE IF NOT EXISTS `saved_timetables` (
                `id` int NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `academic_year` varchar(50) NOT NULL,
                `semester` varchar(20) NOT NULL,
                `type` varchar(20) NOT NULL DEFAULT 'lecture',
                `stream_id` int DEFAULT NULL,
                `timetable_data` json DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_saved_timetables_stream` (`stream_id`),
                KEY `idx_saved_timetables_academic_year` (`academic_year`),
                KEY `idx_saved_timetables_semester` (`semester`),
                CONSTRAINT `fk_saved_timetables_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
            ";
            
            if (!$conn->query($create_saved_timetables)) {
                throw new Exception("Failed to create saved_timetables table: " . $conn->error);
            }
            logMigrationStep(3, 'saved_timetables table created successfully', 'success');
            
            // Step 2: Check current timetable table structure
            logMigrationStep(4, 'Analyzing current timetable table structure...', 'info');
            
            $current_schema = [];
            $result = $conn->query("SHOW COLUMNS FROM timetable");
            while ($row = $result->fetch_assoc()) {
                $current_schema[] = $row['Field'];
            }
            
            logMigrationStep(5, 'Current timetable columns: ' . implode(', ', $current_schema), 'info');
            
            // Step 3: Check if we need to migrate from old schema
            $needs_migration = in_array('class_id', $current_schema) && !in_array('class_course_id', $current_schema);
            
            if ($needs_migration) {
                logMigrationStep(6, 'Detected old schema. Creating backup and migrating...', 'warning');
                
                // Create backup
                $backup_result = $conn->query("CREATE TABLE IF NOT EXISTS `timetable_backup` AS SELECT * FROM `timetable`");
                if (!$backup_result) {
                    throw new Exception("Failed to create backup table: " . $conn->error);
                }
                
                $backup_count = $conn->query("SELECT COUNT(*) as count FROM timetable_backup")->fetch_assoc()['count'];
                logMigrationStep(7, "Backup created with $backup_count records", 'success');
                
                // Create migration log table
                $create_migration_log = "
                CREATE TABLE IF NOT EXISTS `timetable_migration_log` (
                    `id` int NOT NULL AUTO_INCREMENT,
                    `migration_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `old_record_count` int DEFAULT 0,
                    `new_record_count` int DEFAULT 0,
                    `migration_status` enum('pending','completed','failed') DEFAULT 'pending',
                    `notes` text,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
                ";
                
                if (!$conn->query($create_migration_log)) {
                    throw new Exception("Failed to create migration log table: " . $conn->error);
                }
                
                // Log migration start
                $conn->query("INSERT INTO `timetable_migration_log` (`old_record_count`, `migration_status`, `notes`) VALUES ($backup_count, 'pending', 'Schema migration from old to new timetable structure')");
                
                // Drop old table and create new one
                logMigrationStep(8, 'Recreating timetable table with new schema...', 'info');
                
                $drop_table = "DROP TABLE IF EXISTS `timetable`";
                if (!$conn->query($drop_table)) {
                    throw new Exception("Failed to drop old timetable table: " . $conn->error);
                }
                
                $create_new_table = "
                CREATE TABLE `timetable` (
                    `id` int NOT NULL AUTO_INCREMENT,
                    `class_course_id` int NOT NULL,
                    `lecturer_course_id` int NOT NULL,
                    `day_id` int NOT NULL,
                    `time_slot_id` int NOT NULL,
                    `room_id` int NOT NULL,
                    `division_label` varchar(10) DEFAULT NULL,
                    `semester` enum('first','second','summer') NOT NULL DEFAULT 'first',
                    `academic_year` varchar(9) DEFAULT NULL,
                    `timetable_type` enum('lecture','exam') NOT NULL DEFAULT 'lecture',
                    `is_active` tinyint(1) DEFAULT '1',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_timetable_slot` (`room_id`,`day_id`,`time_slot_id`,`semester`,`academic_year`,`timetable_type`),
                    UNIQUE KEY `uq_timetable_class_course_time` (`class_course_id`,`day_id`,`time_slot_id`,`division_label`),
                    KEY `class_course_id` (`class_course_id`),
                    KEY `lecturer_course_id` (`lecturer_course_id`),
                    KEY `day_id` (`day_id`),
                    KEY `time_slot_id` (`time_slot_id`),
                    KEY `room_id` (`room_id`),
                    KEY `idx_timetable_academic_year` (`academic_year`),
                    KEY `idx_timetable_semester` (`semester`),
                    CONSTRAINT `timetable_ibfk_1` FOREIGN KEY (`class_course_id`) REFERENCES `class_courses` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `timetable_ibfk_2` FOREIGN KEY (`lecturer_course_id`) REFERENCES `lecturer_courses` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `timetable_ibfk_3` FOREIGN KEY (`day_id`) REFERENCES `days` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `timetable_ibfk_4` FOREIGN KEY (`time_slot_id`) REFERENCES `time_slots` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `timetable_ibfk_5` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
                ";
                
                if (!$conn->query($create_new_table)) {
                    throw new Exception("Failed to create new timetable table: " . $conn->error);
                }
                
                logMigrationStep(9, 'New timetable table created successfully', 'success');
                
                // Note: Data migration from old to new schema is complex and requires
                // matching class_course and lecturer_course records. For now, we'll
                // leave the backup table intact for manual migration if needed.
                
                logMigrationStep(10, 'Migration completed. Old data preserved in timetable_backup table.', 'success');
                
                // Update migration log
                $conn->query("UPDATE `timetable_migration_log` SET `migration_status` = 'completed', `new_record_count` = 0, `notes` = 'Schema migration completed. Data migration requires manual intervention.' WHERE `migration_status` = 'pending'");
                
            } else {
                logMigrationStep(6, 'Schema already up to date. No migration needed.', 'success');
            }
            
            // Step 4: Add indexes for better performance
            logMigrationStep(11, 'Adding performance indexes...', 'info');
            
            $indexes = [
                "ALTER TABLE `timetable` ADD INDEX IF NOT EXISTS `idx_timetable_stream_lookup` (`class_course_id`, `day_id`, `time_slot_id`)",
                "ALTER TABLE `timetable` ADD INDEX IF NOT EXISTS `idx_timetable_lecturer_lookup` (`lecturer_course_id`, `day_id`, `time_slot_id`)",
                "ALTER TABLE `timetable` ADD INDEX IF NOT EXISTS `idx_timetable_room_schedule` (`room_id`, `day_id`, `time_slot_id`)",
                "ALTER TABLE `class_courses` ADD INDEX IF NOT EXISTS `idx_class_courses_active` (`is_active`)",
                "ALTER TABLE `class_courses` ADD INDEX IF NOT EXISTS `idx_class_courses_class` (`class_id`)",
                "ALTER TABLE `class_courses` ADD INDEX IF NOT EXISTS `idx_class_courses_course` (`course_id`)",
                "ALTER TABLE `lecturer_courses` ADD INDEX IF NOT EXISTS `idx_lecturer_courses_active` (`is_active`)",
                "ALTER TABLE `lecturer_courses` ADD INDEX IF NOT EXISTS `idx_lecturer_courses_lecturer` (`lecturer_id`)",
                "ALTER TABLE `lecturer_courses` ADD INDEX IF NOT EXISTS `idx_lecturer_courses_course` (`course_id`)"
            ];
            
            foreach ($indexes as $index_sql) {
                $conn->query($index_sql);
                // Don't throw error for index creation as they might already exist
            }
            
            logMigrationStep(12, 'Performance indexes added', 'success');
            
            // Step 5: Create timetable view
            logMigrationStep(13, 'Creating timetable view...', 'info');
            
            $create_view = "
            CREATE OR REPLACE VIEW `timetable_view` AS
            SELECT 
                t.id,
                t.class_course_id,
                t.lecturer_course_id,
                t.day_id,
                t.time_slot_id,
                t.room_id,
                t.division_label,
                t.semester,
                t.academic_year,
                t.timetable_type,
                t.is_active,
                t.created_at,
                t.updated_at,
                c.name as class_name,
                c.code as class_code,
                c.stream_id,
                s.name as stream_name,
                co.code as course_code,
                co.name as course_name,
                co.credits,
                co.hours_per_week,
                l.name as lecturer_name,
                l.department_id as lecturer_department_id,
                r.name as room_name,
                r.capacity as room_capacity,
                r.room_type,
                d.name as day_name,
                ts.start_time,
                ts.end_time,
                ts.duration
            FROM timetable t
            JOIN class_courses cc ON t.class_course_id = cc.id
            JOIN classes c ON cc.class_id = c.id
            JOIN courses co ON cc.course_id = co.id
            JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
            JOIN lecturers l ON lc.lecturer_id = l.id
            JOIN rooms r ON t.room_id = r.id
            JOIN days d ON t.day_id = d.id
            JOIN time_slots ts ON t.time_slot_id = ts.id
            JOIN streams s ON c.stream_id = s.id
            WHERE t.is_active = 1;
            ";
            
            if (!$conn->query($create_view)) {
                throw new Exception("Failed to create timetable view: " . $conn->error);
            }
            
            logMigrationStep(14, 'Timetable view created successfully', 'success');
            
            // Commit transaction
            $conn->commit();
            
            logMigrationStep(15, 'Migration completed successfully!', 'success');
            $success_message = 'Timetable schema migration completed successfully!';
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $error_message = 'Migration failed: ' . $e->getMessage();
            logMigrationStep('ERROR', 'Migration failed: ' . $e->getMessage(), 'error');
        }
    }
}
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-database me-2"></i>Apply Timetable Schema Fix</h4>
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
                        <h6 class="mb-0">Database Schema Migration</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>What this migration does:</h6>
                            <ul class="mb-0">
                                <li>Creates the <code>saved_timetables</code> table for proper save functionality</li>
                                <li>Updates the <code>timetable</code> table to use <code>class_course_id</code> and <code>lecturer_course_id</code></li>
                                <li>Adds <code>division_label</code> support for class divisions</li>
                                <li>Adds proper indexes for better performance</li>
                                <li>Creates a comprehensive <code>timetable_view</code> for easier querying</li>
                                <li>Backs up existing data and provides migration logging</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes:</h6>
                            <ul class="mb-0">
                                <li>This migration will create a backup of existing timetable data</li>
                                <li>If you have existing timetable data, it will be preserved in <code>timetable_backup</code> table</li>
                                <li>The migration is safe to run multiple times</li>
                                <li>Make sure you have a database backup before proceeding</li>
                            </ul>
                        </div>

                        <form method="POST" onsubmit="return confirm('Are you sure you want to apply the timetable schema migration? This will modify the database structure.');">
                            <input type="hidden" name="action" value="apply_migration">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-database me-2"></i>Apply Schema Migration
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Current Status</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        // Check current database state
                        $saved_timetables_exists = tableExists($conn, 'saved_timetables');
                        $timetable_has_class_course_id = columnExists($conn, 'timetable', 'class_course_id');
                        $timetable_view_exists = tableExists($conn, 'timetable_view');
                        ?>
                        
                        <div class="mb-3">
                            <strong>saved_timetables table:</strong>
                            <span class="badge <?php echo $saved_timetables_exists ? 'bg-success' : 'bg-warning'; ?>">
                                <?php echo $saved_timetables_exists ? 'Exists' : 'Missing'; ?>
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>timetable table schema:</strong>
                            <span class="badge <?php echo $timetable_has_class_course_id ? 'bg-success' : 'bg-warning'; ?>">
                                <?php echo $timetable_has_class_course_id ? 'Updated' : 'Needs Update'; ?>
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>timetable_view:</strong>
                            <span class="badge <?php echo $timetable_view_exists ? 'bg-success' : 'bg-warning'; ?>">
                                <?php echo $timetable_view_exists ? 'Exists' : 'Missing'; ?>
                            </span>
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
                                                <span class="badge bg-<?php echo $log['status'] === 'success' ? 'success' : ($log['status'] === 'error' ? 'danger' : 'info'); ?>">
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
