<?php
/**
 * Migration Runner - Applies database migrations safely
 */

include 'connect.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Track migration status
$migrations_applied = [];
$migrations_failed = [];

// Function to check if migration was already applied
function isMigrationApplied($conn, $migration_name) {
    // Create migrations table if it doesn't exist
    $create_table_sql = "
        CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `migration_name` VARCHAR(255) NOT NULL,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('success', 'failed') DEFAULT 'success',
            `error_message` TEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_migration` (`migration_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
    ";
    
    $conn->query($create_table_sql);
    
    // Check if migration was applied
    $stmt = $conn->prepare("SELECT id FROM migrations WHERE migration_name = ? AND status = 'success'");
    $stmt->bind_param('s', $migration_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    return $exists;
}

// Function to mark migration as applied
function markMigrationApplied($conn, $migration_name, $status = 'success', $error_message = null) {
    $stmt = $conn->prepare("INSERT INTO migrations (migration_name, status, error_message) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), error_message = VALUES(error_message), applied_at = CURRENT_TIMESTAMP");
    $stmt->bind_param('sss', $migration_name, $status, $error_message);
    $stmt->execute();
    $stmt->close();
}

// Function to run a migration file
function runMigration($conn, $migration_file) {
    global $migrations_applied, $migrations_failed;
    
    $migration_name = basename($migration_file);
    
    echo "<h3>Running migration: $migration_name</h3>\n";
    
    // Check if already applied
    if (isMigrationApplied($conn, $migration_name)) {
        echo "<p class='text-warning'>⚠️ Migration already applied, skipping.</p>\n";
        return true;
    }
    
    // Read migration file
    if (!file_exists($migration_file)) {
        echo "<p class='text-danger'>❌ Migration file not found: $migration_file</p>\n";
        $migrations_failed[] = $migration_name;
        return false;
    }
    
    $sql_content = file_get_contents($migration_file);
    if (empty($sql_content)) {
        echo "<p class='text-danger'>❌ Migration file is empty: $migration_file</p>\n";
        $migrations_failed[] = $migration_name;
        return false;
    }
    
    // Split SQL statements (simple approach - split by semicolon at end of line)
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql_content)));
    
    $conn->autocommit(false); // Start transaction
    
    try {
        foreach ($statements as $statement) {
            if (empty($statement) || strpos(trim($statement), '--') === 0) {
                continue; // Skip empty statements and comments
            }
            
            echo "<div class='text-muted'>Executing: " . substr($statement, 0, 100) . "...</div>\n";
            
            if (!$conn->query($statement)) {
                throw new Exception("SQL Error: " . $conn->error . "\nStatement: " . $statement);
            }
        }
        
        $conn->commit();
        markMigrationApplied($conn, $migration_name, 'success');
        echo "<p class='text-success'>✅ Migration completed successfully!</p>\n";
        $migrations_applied[] = $migration_name;
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
        echo "<p class='text-danger'>❌ Migration failed: $error_message</p>\n";
        markMigrationApplied($conn, $migration_name, 'failed', $error_message);
        $migrations_failed[] = $migration_name;
        return false;
    } finally {
        $conn->autocommit(true); // Restore autocommit
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration Runner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h1>Database Migration Runner</h1>
    <p class="text-muted">Running database migrations to fix stream-related issues...</p>
    
    <div class="card">
        <div class="card-body">
            <?php
            // Get all migration files
            $migration_files = glob(__DIR__ . '/migrations/*.sql');
            sort($migration_files);
            
            if (empty($migration_files)) {
                echo "<p class='text-warning'>No migration files found in migrations/ directory.</p>";
            } else {
                echo "<h4>Found " . count($migration_files) . " migration file(s)</h4>";
                
                foreach ($migration_files as $file) {
                    runMigration($conn, $file);
                    echo "<hr>";
                }
                
                // Summary
                echo "<div class='mt-4'>";
                echo "<h4>Migration Summary</h4>";
                echo "<p class='text-success'>✅ Applied: " . count($migrations_applied) . "</p>";
                echo "<p class='text-danger'>❌ Failed: " . count($migrations_failed) . "</p>";
                
                if (!empty($migrations_applied)) {
                    echo "<h5>Successfully Applied:</h5>";
                    echo "<ul>";
                    foreach ($migrations_applied as $migration) {
                        echo "<li class='text-success'>$migration</li>";
                    }
                    echo "</ul>";
                }
                
                if (!empty($migrations_failed)) {
                    echo "<h5>Failed:</h5>";
                    echo "<ul>";
                    foreach ($migrations_failed as $migration) {
                        echo "<li class='text-danger'>$migration</li>";
                    }
                    echo "</ul>";
                }
                echo "</div>";
            }
            ?>
        </div>
    </div>
    
    <div class="mt-4">
        <a href="index.php" class="btn btn-primary">Return to Dashboard</a>
        <a href="generate_timetable.php" class="btn btn-success">Test Timetable Generation</a>
    </div>
</div>
</body>
</html>