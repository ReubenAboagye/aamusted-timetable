<?php
/**
 * Migrate Timetable Data from Old Schema to New Schema
 * 
 * This script migrates existing timetable data from the old schema
 * (class_id, course_id, lecturer_id) to the new schema
 * (class_course_id, lecturer_course_id).
 */

// Include database connection
include 'connect.php';

// Include flash helper if available
if (file_exists(__DIR__ . '/includes/flash.php')) {
    include_once __DIR__ . '/includes/flash.php';
}

// Set page title
$pageTitle = 'Migrate Timetable Data';
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'migrate_data') {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            logMigrationStep(1, 'Starting data migration from old to new schema...', 'info');
            
            // Check if backup table exists
            $backup_exists = $conn->query("SHOW TABLES LIKE 'timetable_backup'")->num_rows > 0;
            if (!$backup_exists) {
                throw new Exception("No backup table found. Please run the schema migration first.");
            }
            
            logMigrationStep(2, 'Backup table found. Checking for existing data...', 'info');
            
            // Count records in backup table
            $backup_count = $conn->query("SELECT COUNT(*) as count FROM timetable_backup")->fetch_assoc()['count'];
            logMigrationStep(3, "Found $backup_count records to migrate", 'info');
            
            if ($backup_count == 0) {
                logMigrationStep(4, 'No data to migrate. Migration completed.', 'success');
                $success_message = 'No data to migrate. Migration completed successfully!';
            } else {
                // Step 1: Create class_course mappings for existing data
                logMigrationStep(5, 'Creating class_course mappings for existing data...', 'info');
                
                // Get all unique class-course combinations from backup
                $class_course_combinations = $conn->query("
                    SELECT DISTINCT class_id, course_id, semester, academic_year 
                    FROM timetable_backup 
                    WHERE class_id IS NOT NULL AND course_id IS NOT NULL
                ");
                
                $mappings_created = 0;
                while ($row = $class_course_combinations->fetch_assoc()) {
                    // Check if class_course mapping already exists
                    $existing = $conn->prepare("
                        SELECT id FROM class_courses 
                        WHERE class_id = ? AND course_id = ? AND semester = ? AND academic_year = ?
                    ");
                    $existing->bind_param("iiss", $row['class_id'], $row['course_id'], $row['semester'], $row['academic_year']);
                    $existing->execute();
                    $existing_result = $existing->get_result();
                    
                    if ($existing_result->num_rows == 0) {
                        // Create new class_course mapping
                        $insert = $conn->prepare("
                            INSERT INTO class_courses (class_id, course_id, semester, academic_year, is_active) 
                            VALUES (?, ?, ?, ?, 1)
                        ");
                        $insert->bind_param("iiss", $row['class_id'], $row['course_id'], $row['semester'], $row['academic_year']);
                        if ($insert->execute()) {
                            $mappings_created++;
                        }
                        $insert->close();
                    }
                    $existing->close();
                }
                
                logMigrationStep(6, "Created $mappings_created new class_course mappings", 'success');
                
                // Step 2: Create lecturer_course mappings for existing data
                logMigrationStep(7, 'Creating lecturer_course mappings for existing data...', 'info');
                
                // Get all unique lecturer-course combinations from backup
                $lecturer_course_combinations = $conn->query("
                    SELECT DISTINCT lecturer_id, course_id 
                    FROM timetable_backup 
                    WHERE lecturer_id IS NOT NULL AND course_id IS NOT NULL
                ");
                
                $lecturer_mappings_created = 0;
                while ($row = $lecturer_course_combinations->fetch_assoc()) {
                    // Check if lecturer_course mapping already exists
                    $existing = $conn->prepare("
                        SELECT id FROM lecturer_courses 
                        WHERE lecturer_id = ? AND course_id = ?
                    ");
                    $existing->bind_param("ii", $row['lecturer_id'], $row['course_id']);
                    $existing->execute();
                    $existing_result = $existing->get_result();
                    
                    if ($existing_result->num_rows == 0) {
                        // Create new lecturer_course mapping
                        $insert = $conn->prepare("
                            INSERT INTO lecturer_courses (lecturer_id, course_id, is_active) 
                            VALUES (?, ?, 1)
                        ");
                        $insert->bind_param("ii", $row['lecturer_id'], $row['course_id']);
                        if ($insert->execute()) {
                            $lecturer_mappings_created++;
                        }
                        $insert->close();
                    }
                    $existing->close();
                }
                
                logMigrationStep(8, "Created $lecturer_mappings_created new lecturer_course mappings", 'success');
                
                // Step 3: Migrate timetable records
                logMigrationStep(9, 'Migrating timetable records to new schema...', 'info');
                
                // Get all records from backup
                $backup_records = $conn->query("
                    SELECT * FROM timetable_backup 
                    ORDER BY id
                ");
                
                $migrated_count = 0;
                $skipped_count = 0;
                
                while ($record = $backup_records->fetch_assoc()) {
                    // Find corresponding class_course_id
                    $class_course_query = $conn->prepare("
                        SELECT id FROM class_courses 
                        WHERE class_id = ? AND course_id = ? AND semester = ? AND academic_year = ?
                    ");
                    $class_course_query->bind_param("iiss", $record['class_id'], $record['course_id'], $record['semester'], $record['academic_year']);
                    $class_course_query->execute();
                    $class_course_result = $class_course_query->get_result();
                    $class_course_row = $class_course_result->fetch_assoc();
                    $class_course_query->close();
                    
                    if (!$class_course_row) {
                        $skipped_count++;
                        continue;
                    }
                    
                    // Find corresponding lecturer_course_id
                    $lecturer_course_query = $conn->prepare("
                        SELECT id FROM lecturer_courses 
                        WHERE lecturer_id = ? AND course_id = ?
                    ");
                    $lecturer_course_query->bind_param("ii", $record['lecturer_id'], $record['course_id']);
                    $lecturer_course_query->execute();
                    $lecturer_course_result = $lecturer_course_query->get_result();
                    $lecturer_course_row = $lecturer_course_result->fetch_assoc();
                    $lecturer_course_query->close();
                    
                    if (!$lecturer_course_row) {
                        $skipped_count++;
                        continue;
                    }
                    
                    // Insert into new timetable table
                    $insert_query = $conn->prepare("
                        INSERT INTO timetable (
                            class_course_id, lecturer_course_id, day_id, time_slot_id, room_id,
                            division_label, semester, academic_year, timetable_type, is_active,
                            created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $division_label = $record['division_label'] ?? null;
                    $timetable_type = $record['timetable_type'] ?? 'lecture';
                    $is_active = $record['is_active'] ?? 1;
                    $created_at = $record['created_at'] ?? date('Y-m-d H:i:s');
                    $updated_at = $record['updated_at'] ?? date('Y-m-d H:i:s');
                    
                    $insert_query->bind_param("iiiiisssiss", 
                        $class_course_row['id'],
                        $lecturer_course_row['id'],
                        $record['day_id'],
                        $record['time_slot_id'],
                        $record['room_id'],
                        $division_label,
                        $record['semester'],
                        $record['academic_year'],
                        $timetable_type,
                        $is_active,
                        $created_at,
                        $updated_at
                    );
                    
                    if ($insert_query->execute()) {
                        $migrated_count++;
                    } else {
                        $skipped_count++;
                    }
                    $insert_query->close();
                }
                
                logMigrationStep(10, "Migrated $migrated_count records, skipped $skipped_count records", 'success');
                
                // Step 4: Update migration log
                logMigrationStep(11, 'Updating migration log...', 'info');
                
                $update_log = $conn->prepare("
                    UPDATE timetable_migration_log 
                    SET new_record_count = ?, 
                        notes = CONCAT('Data migration completed. Migrated: ', ?, ', Skipped: ', ?)
                    WHERE migration_status = 'completed'
                ");
                $update_log->bind_param("iii", $migrated_count, $migrated_count, $skipped_count);
                $update_log->execute();
                $update_log->close();
                
                logMigrationStep(12, 'Migration log updated successfully', 'success');
            }
            
            // Commit transaction
            $conn->commit();
            
            logMigrationStep(13, 'Data migration completed successfully!', 'success');
            $success_message = 'Timetable data migration completed successfully!';
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            $error_message = 'Data migration failed: ' . $e->getMessage();
            logMigrationStep('ERROR', 'Data migration failed: ' . $e->getMessage(), 'error');
        }
    }
}

// Check current status
$backup_exists = false;
$backup_count = 0;
$new_table_count = 0;

try {
    $backup_exists = $conn->query("SHOW TABLES LIKE 'timetable_backup'")->num_rows > 0;
    if ($backup_exists) {
        $backup_count = $conn->query("SELECT COUNT(*) as count FROM timetable_backup")->fetch_assoc()['count'];
    }
    $new_table_count = $conn->query("SELECT COUNT(*) as count FROM timetable")->fetch_assoc()['count'];
} catch (Exception $e) {
    // Table might not exist yet
}
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-exchange-alt me-2"></i>Migrate Timetable Data</h4>
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
                        <h6 class="mb-0">Data Migration</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>What this migration does:</h6>
                            <ul class="mb-0">
                                <li>Converts existing timetable data from old schema to new schema</li>
                                <li>Creates missing class_course and lecturer_course mappings</li>
                                <li>Migrates all timetable records to use the new foreign key structure</li>
                                <li>Preserves all original data and timestamps</li>
                                <li>Updates migration logs with results</li>
                            </ul>
                        </div>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes:</h6>
                            <ul class="mb-0">
                                <li>This migration requires the schema migration to be completed first</li>
                                <li>It will create new class_course and lecturer_course records as needed</li>
                                <li>The migration is safe and can be run multiple times</li>
                                <li>Original data is preserved in the backup table</li>
                            </ul>
                        </div>

                        <?php if ($backup_exists && $backup_count > 0): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to migrate the timetable data? This will convert existing records to the new schema.');">
                                <input type="hidden" name="action" value="migrate_data">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-exchange-alt me-2"></i>Migrate Data
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-secondary">
                                <i class="fas fa-info-circle me-2"></i>
                                No data to migrate. Either the schema migration hasn't been run yet, or there's no existing data to migrate.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Migration Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Backup table:</strong>
                            <span class="badge <?php echo $backup_exists ? 'bg-success' : 'bg-warning'; ?>">
                                <?php echo $backup_exists ? 'Exists' : 'Missing'; ?>
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Records in backup:</strong>
                            <span class="badge bg-info"><?php echo $backup_count; ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Records in new table:</strong>
                            <span class="badge bg-info"><?php echo $new_table_count; ?></span>
                        </div>
                        
                        <div class="mt-3">
                            <a href="apply_timetable_schema_fix.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-2"></i>Back to Schema Migration
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
