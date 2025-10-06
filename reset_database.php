<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'connect.php';

// Check if reset is confirmed
$confirmed = isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'DELETE_ALL_DATA';
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Create backups directory if it doesn't exist
$backupDir = __DIR__ . '/backups/database_resets';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Function to create database backup
function createDatabaseBackup($conn, $backupDir) {
    // Get database name from connection
    $result = $conn->query("SELECT DATABASE() as dbname");
    $row = $result->fetch_assoc();
    $dbName = $row['dbname'];
    
    $timestamp = date('Y-m-d_His');
    $backupFile = $backupDir . "/backup_before_reset_{$timestamp}.sql";
    
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    $sqlDump = "-- Database Backup Before Reset\n";
    $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sqlDump .= "-- Database: {$dbName}\n\n";
    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    
    foreach ($tables as $table) {
        // Get CREATE TABLE statement
        $result = $conn->query("SHOW CREATE TABLE `{$table}`");
        $row = $result->fetch_row();
        $sqlDump .= "\n-- Table: {$table}\n";
        $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sqlDump .= $row[1] . ";\n\n";
        
        // Get table data
        $result = $conn->query("SELECT * FROM `{$table}`");
        if ($result->num_rows > 0) {
            $sqlDump .= "-- Data for table: {$table}\n";
            
            while ($row = $result->fetch_assoc()) {
                $sqlDump .= "INSERT INTO `{$table}` VALUES (";
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                $sqlDump .= implode(', ', $values) . ");\n";
            }
            $sqlDump .= "\n";
        }
    }
    
    $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    
    // Write to file
    file_put_contents($backupFile, $sqlDump);
    
    return [
        'success' => true,
        'file' => $backupFile,
        'filename' => basename($backupFile),
        'size' => filesize($backupFile)
    ];
}

// Get current record counts
function getTableCounts($conn) {
    $tables = [
        'timetable_lecturers' => ['Timetable Lecturers', 'relationship'],
        'timetable' => ['Timetable Entries', 'data'],
        'saved_timetables' => ['Saved Timetables', 'data'],
        'stream_time_slots' => ['Stream Time Slots', 'relationship'],
        'class_courses' => ['Class Courses', 'relationship'],
        'lecturer_courses' => ['Lecturer Courses', 'relationship'],
        'course_room_types' => ['Course Room Types', 'relationship'],
        'classes' => ['Classes', 'data'],
        'lecturers' => ['Lecturers', 'data'],
        'courses' => ['Courses', 'data'],
        'rooms' => ['Rooms', 'infrastructure'],
        'programs' => ['Programs', 'config'],
        'buildings' => ['Buildings', 'infrastructure'],
        'room_types' => ['Room Types', 'config'],
        'levels' => ['Levels', 'config'],
        'streams' => ['Streams', 'config'],
        'departments' => ['Departments', 'config'],
        'days' => ['Days', 'config'],
        'time_slots' => ['Time Slots', 'config']
    ];
    
    $counts = [];
    foreach ($tables as $table => $info) {
        $result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
        if ($result) {
            $row = $result->fetch_assoc();
            $counts[$table] = [
                'label' => $info[0],
                'category' => $info[1],
                'count' => $row['count']
            ];
        } else {
            $counts[$table] = [
                'label' => $info[0],
                'category' => $info[1],
                'count' => 'Error'
            ];
        }
    }
    return $counts;
}

// Get table dependencies
function getTableDependencies() {
    return [
        'timetable_lecturers' => ['timetable'],
        'timetable' => ['class_courses', 'lecturer_courses'],
        'saved_timetables' => [],
        'stream_time_slots' => [],
        'class_courses' => ['classes'],
        'lecturer_courses' => ['lecturers'],
        'course_room_types' => ['courses'],
        'classes' => ['programs', 'streams'],
        'lecturers' => ['departments'],
        'courses' => ['departments'],
        'rooms' => ['buildings'],
        'programs' => ['departments'],
        'buildings' => [],
        'room_types' => [],
        'levels' => [],
        'streams' => [],
        'departments' => [],
        'days' => [],
        'time_slots' => []
    ];
}

$beforeCounts = getTableCounts($conn);
$resetSuccess = false;
$resetErrors = [];
$backupInfo = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Reset - Timetable System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-maroon: #800020;
            --hover-maroon: #600010;
            --dark-maroon: #4a0013;
            --brand-blue: #0d6efd;
            --brand-gold: #FFD700;
            --brand-green: #198754;
            --danger-red: #dc3545;
            --warning-yellow: #ffc107;
            --text-muted: #6c757d;
            --border-light: #e9ecef;
            --bg-light: #f8f9fa;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--hover-maroon) 100%);
            color: white;
            padding: 40px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.05)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,112C672,96,768,96,864,112C960,128,1056,160,1152,160C1248,160,1344,128,1392,112L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.1;
        }
        
        .header-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        
        .header-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .header-icon i {
            font-size: 2.5em;
            color: white;
        }
        
        .header h1 {
            font-size: 2.25em;
            margin-bottom: 10px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fff5f5 0%, #fee2e2 100%);
            border: 2px solid var(--danger-red);
            border-left: 5px solid var(--danger-red);
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.1);
        }
        
        .warning-box h2 {
            color: var(--danger-red);
            font-size: 1.4em;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            font-weight: 600;
        }
        
        .warning-box h2 i {
            margin-right: 12px;
            font-size: 1.3em;
            background: var(--danger-red);
            color: white;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .warning-box ul {
            margin-left: 20px;
            color: #991b1b;
            line-height: 2;
        }
        
        .warning-box li {
            margin-bottom: 8px;
        }
        
        .counts-section {
            margin-bottom: 30px;
        }
        
        .counts-section h3 {
            color: var(--primary-maroon);
            margin-bottom: 15px;
            font-size: 1.3em;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .counts-section h3 i {
            margin-right: 10px;
            color: var(--primary-maroon);
        }
        
        .counts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .count-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .count-card.has-data {
            background: #fef3c7;
            border-color: #fbbf24;
        }
        
        .count-card .label {
            font-weight: 500;
            color: #475569;
        }
        
        .count-card .count {
            font-size: 1.5em;
            font-weight: bold;
            color: #1e293b;
        }
        
        .count-card.has-data .count {
            color: #b45309;
        }
        
        .count-card-selectable {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            gap: 12px;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .count-card-selectable:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
        }
        
        .count-card-selectable.has-data {
            background: #fef3c7;
            border-color: #fbbf24;
        }
        
        .count-card-selectable.has-data:hover {
            border-color: #f59e0b;
            background: #fef9c3;
        }
        
        .count-card-selectable input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            flex-shrink: 0;
        }
        
        .count-card-selectable .card-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex: 1;
        }
        
        .count-card-selectable .label {
            font-weight: 500;
            color: #475569;
        }
        
        .count-card-selectable .count {
            font-size: 1.5em;
            font-weight: bold;
            color: #1e293b;
        }
        
        .count-card-selectable.has-data .count {
            color: #b45309;
        }
        
        .btn-small {
            padding: 8px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
            color: #475569;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-small:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
        }
        
        .form-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .form-group input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1em;
            font-family: monospace;
        }
        
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #dc2626;
        }
        
        .form-group .hint {
            color: #64748b;
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-red) 0%, #b91c1c 100%);
            color: white;
            border: 1px solid var(--danger-red);
        }
        
        .btn-danger:hover:not(:disabled) {
            background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }
        
        .btn-danger:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
            border-color: #cbd5e1;
        }
        
        .btn-secondary {
            background: #64748b;
            color: white;
            border: 1px solid #64748b;
        }
        
        .btn-secondary:hover {
            background: #475569;
            border-color: #475569;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--hover-maroon) 100%);
            color: white;
            border: 1px solid var(--primary-maroon);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--hover-maroon) 0%, var(--dark-maroon) 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
        }
        
        .success-message {
            background: linear-gradient(135deg, #dcfce7 0%, #d1fae5 100%);
            border: 2px solid var(--brand-green);
            border-left: 5px solid var(--brand-green);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            color: #166534;
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.1);
        }
        
        .success-message h3 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            font-weight: 600;
        }
        
        .success-message h3 i {
            margin-right: 10px;
            background: var(--brand-green);
            color: white;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .error-message {
            background: linear-gradient(135deg, #fff5f5 0%, #fee2e2 100%);
            border: 2px solid var(--danger-red);
            border-left: 5px solid var(--danger-red);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            color: #991b1b;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.1);
        }
        
        .error-message h3 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            font-weight: 600;
        }
        
        .error-message h3 i {
            margin-right: 10px;
            background: var(--danger-red);
            color: white;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .error-list {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .deletion-order {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-left: 4px solid var(--primary-maroon);
            padding: 20px;
            margin-top: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .deletion-order h4 {
            color: var(--primary-maroon);
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .deletion-order h4 i {
            margin-right: 10px;
        }
        
        .deletion-order ol {
            margin-left: 20px;
            color: #475569;
            line-height: 1.8;
        }
        
        .back-link a {
            color: var(--primary-maroon);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .back-link a:hover {
            color: var(--hover-maroon);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="header-icon">
                    <i class="fas fa-database"></i>
                </div>
                <h1>Database Reset & Restore</h1>
                <p>Manage database records - Reset demo data or restore from backup</p>
            </div>
        </div>
        
        <div class="content">
            <?php
            // Process reset if confirmed
            if ($confirmed && $action === 'reset') {
                // Get selected tables
                $selectedTables = isset($_POST['tables']) ? $_POST['tables'] : [];
                $createBackup = isset($_POST['create_backup']) && $_POST['create_backup'] === '1';
                
                // Step 1: Create backup if requested
                if ($createBackup) {
                    echo '<div class="success-message">';
                    echo '<h3><i class="fas fa-spinner fa-spin"></i> Creating Database Backup...</h3>';
                    echo '<p>Backing up all data before deletion...</p>';
                    echo '</div>';
                    
                    try {
                        $backupInfo = createDatabaseBackup($conn, $backupDir);
                        echo '<div class="success-message">';
                        echo '<h3><i class="fas fa-check-circle"></i> Backup Created Successfully!</h3>';
                        echo '<p><strong>File:</strong> ' . htmlspecialchars($backupInfo['filename']) . '</p>';
                        echo '<p><strong>Size:</strong> ' . number_format($backupInfo['size'] / 1024, 2) . ' KB</p>';
                        echo '<p><strong>Location:</strong> backups/database_resets/</p>';
                        echo '<p><a href="backups/database_resets/' . htmlspecialchars($backupInfo['filename']) . '" download style="color: #16a34a; font-weight: 600;"><i class="fas fa-download"></i> Download Backup</a></p>';
                        echo '</div>';
                    } catch (Exception $e) {
                        echo '<div class="error-message">';
                        echo '<h3><i class="fas fa-exclamation-triangle"></i> Backup Failed</h3>';
                        echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                        echo '<p>Proceeding with deletion anyway...</p>';
                        echo '</div>';
                    }
                }
                
                echo '<div class="success-message">';
                echo '<h3><i class="fas fa-sync fa-spin"></i> Resetting Database...</h3>';
                echo '<p>Deleting records in proper order to respect foreign key constraints...</p>';
                echo '</div>';
                
                // Disable foreign key checks temporarily for safer deletion
                $conn->query("SET FOREIGN_KEY_CHECKS = 0");
                
                // Full deletion order
                $fullDeletionOrder = [
                    'timetable_lecturers',
                    'timetable',
                    'saved_timetables',
                    'stream_time_slots',
                    'class_courses',
                    'lecturer_courses',
                    'course_room_types',
                    'classes',
                    'lecturers',
                    'courses',
                    'rooms',
                    'programs',
                    'buildings',
                    'room_types',
                    'levels',
                    'streams',
                    'departments',
                    'days',
                    'time_slots'
                ];
                
                // If selective deletion, filter and add dependencies
                if (!empty($selectedTables)) {
                    $dependencies = getTableDependencies();
                    $tablesToDelete = [];
                    
                    // Add selected tables and their dependencies
                    foreach ($selectedTables as $table) {
                        if (!in_array($table, $tablesToDelete)) {
                            $tablesToDelete[] = $table;
                        }
                        
                        // Add all tables that depend on this table
                        foreach ($fullDeletionOrder as $depTable) {
                            if (isset($dependencies[$depTable]) && in_array($table, $dependencies[$depTable])) {
                                if (!in_array($depTable, $tablesToDelete)) {
                                    $tablesToDelete[] = $depTable;
                                }
                            }
                        }
                    }
                    
                    // Sort by deletion order
                    $deletionOrder = array_values(array_intersect($fullDeletionOrder, $tablesToDelete));
                    
                    echo '<p><strong>Deleting ' . count($deletionOrder) . ' table(s) (including dependencies)</strong></p>';
                } else {
                    $deletionOrder = $fullDeletionOrder;
                    echo '<p><strong>Deleting all tables</strong></p>';
                }
                
                $totalDeleted = 0;
                foreach ($deletionOrder as $table) {
                    $result = $conn->query("DELETE FROM `$table`");
                    if ($result) {
                        $affected = $conn->affected_rows;
                        $totalDeleted += $affected;
                        echo "<p>✓ Deleted $affected records from <strong>$table</strong></p>";
                    } else {
                        $resetErrors[] = "Error deleting from $table: " . $conn->error;
                        echo "<p style='color: #dc2626;'>✗ Error deleting from <strong>$table</strong>: " . htmlspecialchars($conn->error) . "</p>";
                    }
                }
                
                // Reset auto-increment counters
                foreach ($deletionOrder as $table) {
                    $conn->query("ALTER TABLE `$table` AUTO_INCREMENT = 1");
                }
                
                // Re-enable foreign key checks
                $conn->query("SET FOREIGN_KEY_CHECKS = 1");
                
                if (empty($resetErrors)) {
                    $resetSuccess = true;
                    echo '<div class="success-message" style="margin-top: 20px;">';
                    echo '<h3><i class="fas fa-check-circle"></i> Database Reset Complete!</h3>';
                    echo '<p>Successfully deleted <strong>' . number_format($totalDeleted) . '</strong> records from <strong>' . count($deletionOrder) . '</strong> table(s).</p>';
                    if ($createBackup && $backupInfo) {
                        echo '<p>Your backup is saved at: <code>backups/database_resets/' . htmlspecialchars($backupInfo['filename']) . '</code></p>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="error-message" style="margin-top: 20px;">';
                    echo '<h3><i class="fas fa-exclamation-triangle"></i> Reset Completed with Errors</h3>';
                    echo '<p>Some tables encountered errors during deletion:</p>';
                    echo '<ul class="error-list">';
                    foreach ($resetErrors as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
                
                // Show updated counts
                $afterCounts = getTableCounts($conn);
                echo '<div class="counts-section">';
                echo '<h3><i class="fas fa-chart-bar"></i> Updated Record Counts</h3>';
                echo '<div class="counts-grid">';
                foreach ($afterCounts as $table => $data) {
                    $hasData = $data['count'] > 0;
                    echo '<div class="count-card' . ($hasData ? ' has-data' : '') . '">';
                    echo '<span class="label">' . htmlspecialchars($data['label']) . '</span>';
                    echo '<span class="count">' . $data['count'] . '</span>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
                
                echo '<div class="back-link">';
                echo '<a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a> | ';
                echo '<a href="reset_database.php"><i class="fas fa-redo"></i> Reset Another Database</a>';
                echo '</div>';
                
            } else {
                // Show warning and confirmation form
            ?>
            
            <div class="warning-box">
                <h2><i class="fas fa-exclamation-triangle"></i> WARNING: Destructive Operation</h2>
                <ul>
                    <li><strong>This action is IRREVERSIBLE!</strong></li>
                    <li>All demo data will be permanently deleted from the database</li>
                    <li>This includes: departments, programs, courses, lecturers, classes, timetables, and all related data</li>
                    <li>Make sure you have a backup if you need to restore this data later</li>
                    <li>Only proceed if you're ready to start fresh with real data</li>
                </ul>
            </div>
            
            <div class="counts-section">
                <h3><i class="fas fa-chart-bar"></i> Current Record Counts</h3>
                <p style="margin-bottom: 15px; color: #64748b;">Review what will be deleted:</p>
                
                <div class="selection-options" style="margin-bottom: 20px; padding: 15px; background: #f1f5f9; border-radius: 8px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin-bottom: 10px;">
                        <input type="checkbox" id="selectAll" style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: 600; color: #1e293b;">Select All Tables</span>
                    </label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="button" onclick="selectByCategory('data')" class="btn-small">Select Data Tables</button>
                        <button type="button" onclick="selectByCategory('config')" class="btn-small">Select Config Tables</button>
                        <button type="button" onclick="selectByCategory('relationship')" class="btn-small">Select Relationships</button>
                        <button type="button" onclick="deselectAll()" class="btn-small">Clear All</button>
                    </div>
                </div>
                
                <?php
                // Group tables by category
                $categories = [
                    'data' => [],
                    'relationship' => [],
                    'infrastructure' => [],
                    'config' => []
                ];
                
                $totalRecords = 0;
                foreach ($beforeCounts as $table => $data) {
                    if ($data['count'] > 0) {
                        $totalRecords += $data['count'];
                    }
                    $categories[$data['category']][$table] = $data;
                }
                
                $categoryLabels = [
                    'data' => '<i class="fas fa-table"></i> Data Tables',
                    'relationship' => '<i class="fas fa-link"></i> Relationship Tables',
                    'infrastructure' => '<i class="fas fa-building"></i> Infrastructure Tables',
                    'config' => '<i class="fas fa-cog"></i> Configuration Tables'
                ];
                
                foreach ($categories as $category => $tables) {
                    if (empty($tables)) continue;
                    
                    echo '<div class="category-section">';
                    echo '<h4 style="margin: 20px 0 10px 0; color: #475569;">' . $categoryLabels[$category] . '</h4>';
                    echo '<div class="counts-grid">';
                    
                    foreach ($tables as $table => $data) {
                        $hasData = $data['count'] > 0;
                        echo '<label class="count-card-selectable' . ($hasData ? ' has-data' : '') . '" data-category="' . $category . '">';
                        echo '<input type="checkbox" name="tables[]" value="' . htmlspecialchars($table) . '" class="table-checkbox" data-category="' . $category . '">';
                        echo '<div class="card-content">';
                        echo '<span class="label">' . htmlspecialchars($data['label']) . '</span>';
                        echo '<span class="count">' . $data['count'] . '</span>';
                        echo '</div>';
                        echo '</label>';
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }
                ?>
                
                <p style="text-align: center; font-size: 1.2em; margin-top: 15px; font-weight: 600; color: #b45309;">
                    Total Records in Database: <?php echo number_format($totalRecords); ?>
                </p>
                <p style="text-align: center; font-size: 0.9em; color: #64748b; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> If no tables are selected, ALL tables will be reset
                </p>
            </div>
            
            <div class="deletion-order">
                <h4><i class="fas fa-list-ol"></i> Deletion Order (respecting foreign key constraints):</h4>
                <ol>
                    <li>Timetable Lecturers (relationship table)</li>
                    <li>Timetable Entries</li>
                    <li>Saved Timetables</li>
                    <li>Stream Time Slots</li>
                    <li>Class Courses</li>
                    <li>Lecturer Courses</li>
                    <li>Course Room Types</li>
                    <li>Classes</li>
                    <li>Lecturers</li>
                    <li>Courses</li>
                    <li>Rooms</li>
                    <li>Programs</li>
                    <li>Buildings, Room Types, Levels, Streams</li>
                    <li>Departments</li>
                    <li>Days, Time Slots</li>
                </ol>
            </div>
            
            <form method="POST" action="reset_database.php" id="resetForm">
                <div class="form-section">
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 15px; cursor: pointer; background: linear-gradient(135deg, #dcfce7 0%, #d1fae5 100%); padding: 20px; border-radius: 8px; border: 2px solid #16a34a;">
                            <input 
                                type="checkbox" 
                                name="create_backup" 
                                value="1" 
                                id="create_backup"
                                checked
                                style="width: 20px; height: 20px; cursor: pointer;"
                            >
                            <div style="flex: 1;">
                                <strong style="color: #166534; display: flex; align-items: center; gap: 8px; margin-bottom: 5px; font-size: 1.1em;">
                                    <i class="fas fa-save"></i> Create Backup Before Deletion
                                </strong>
                                <span style="color: #15803d; font-size: 0.95em;">Recommended: Creates a full SQL backup that can be restored later</span>
                            </div>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_reset">
                            Type <code style="background: #fef2f2; padding: 2px 8px; border-radius: 4px; color: #dc2626;">DELETE_ALL_DATA</code> to confirm:
                        </label>
                        <input 
                            type="text" 
                            id="confirm_reset" 
                            name="confirm_reset" 
                            placeholder="Type DELETE_ALL_DATA here"
                            autocomplete="off"
                            required
                        >
                        <div class="hint">This confirmation is required to prevent accidental deletion.</div>
                    </div>
                    
                    <input type="hidden" name="action" value="reset">
                    
                    <div class="button-group">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-danger" id="resetButton" disabled>
                            <i class="fas fa-trash-alt"></i> Reset Database
                        </button>
                    </div>
                </div>
            </form>
            
            <script>
                // Enable submit button only when correct confirmation text is entered
                document.getElementById('confirm_reset').addEventListener('input', function(e) {
                    const resetButton = document.getElementById('resetButton');
                    if (e.target.value === 'DELETE_ALL_DATA') {
                        resetButton.disabled = false;
                    } else {
                        resetButton.disabled = true;
                    }
                });
                
                // Select all checkboxes
                document.getElementById('selectAll').addEventListener('change', function(e) {
                    const checkboxes = document.querySelectorAll('.table-checkbox');
                    checkboxes.forEach(cb => cb.checked = e.target.checked);
                });
                
                // Update "Select All" state when individual checkboxes change
                document.querySelectorAll('.table-checkbox').forEach(cb => {
                    cb.addEventListener('change', function() {
                        const allCheckboxes = document.querySelectorAll('.table-checkbox');
                        const checkedCheckboxes = document.querySelectorAll('.table-checkbox:checked');
                        const selectAllCheckbox = document.getElementById('selectAll');
                        
                        if (checkedCheckboxes.length === allCheckboxes.length) {
                            selectAllCheckbox.checked = true;
                            selectAllCheckbox.indeterminate = false;
                        } else if (checkedCheckboxes.length === 0) {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = false;
                        } else {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = true;
                        }
                    });
                });
                
                // Select by category functions
                function selectByCategory(category) {
                    const checkboxes = document.querySelectorAll(`.table-checkbox[data-category="${category}"]`);
                    checkboxes.forEach(cb => cb.checked = true);
                    updateSelectAllState();
                }
                
                function deselectAll() {
                    const checkboxes = document.querySelectorAll('.table-checkbox');
                    checkboxes.forEach(cb => cb.checked = false);
                    document.getElementById('selectAll').checked = false;
                    document.getElementById('selectAll').indeterminate = false;
                }
                
                function updateSelectAllState() {
                    const allCheckboxes = document.querySelectorAll('.table-checkbox');
                    const checkedCheckboxes = document.querySelectorAll('.table-checkbox:checked');
                    const selectAllCheckbox = document.getElementById('selectAll');
                    
                    if (checkedCheckboxes.length === allCheckboxes.length) {
                        selectAllCheckbox.checked = true;
                        selectAllCheckbox.indeterminate = false;
                    } else if (checkedCheckboxes.length === 0) {
                        selectAllCheckbox.checked = false;
                        selectAllCheckbox.indeterminate = false;
                    } else {
                        selectAllCheckbox.checked = false;
                        selectAllCheckbox.indeterminate = true;
                    }
                }
                
                // Add confirmation dialog
                document.getElementById('resetForm').addEventListener('submit', function(e) {
                    const checkedCheckboxes = document.querySelectorAll('.table-checkbox:checked');
                    const createBackup = document.getElementById('create_backup').checked;
                    
                    let message = '';
                    if (checkedCheckboxes.length === 0) {
                        message = 'Are you ABSOLUTELY SURE you want to delete ALL data from ALL tables? This cannot be undone!';
                    } else {
                        message = `Are you ABSOLUTELY SURE you want to delete data from ${checkedCheckboxes.length} selected table(s) (including dependent tables)? This cannot be undone!`;
                    }
                    
                    if (createBackup) {
                        message += '\n\nA backup will be created before deletion.';
                    } else {
                        message += '\n\nWARNING: No backup will be created!';
                    }
                    
                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });
            </script>
            
            <?php } ?>
        </div>
    </div>
</body>
</html>

