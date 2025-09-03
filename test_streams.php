<?php
// Test script to verify streams are loading correctly
include 'connect.php';

echo "<h2>Streams Test</h2>";

// Test 1: Check if streams table exists
echo "<h3>Test 1: Check if streams table exists</h3>";
$table_check = $conn->query("SHOW TABLES LIKE 'streams'");
if ($table_check && $table_check->num_rows > 0) {
    echo "✅ Streams table exists<br>";
} else {
    echo "❌ Streams table does not exist<br>";
}

// Test 2: Check streams table structure
echo "<h3>Test 2: Check streams table structure</h3>";
$columns = $conn->query("SHOW COLUMNS FROM streams");
if ($columns) {
    echo "✅ Streams table structure:<br>";
    while ($col = $columns->fetch_assoc()) {
        echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
} else {
    echo "❌ Could not get streams table structure<br>";
}

// Test 3: Check if streams have data
echo "<h3>Test 3: Check streams data</h3>";
$streams_sql = "SELECT id, name, code, is_active FROM streams ORDER BY id";
$streams_result = $conn->query($streams_sql);

if ($streams_result && $streams_result->num_rows > 0) {
    echo "✅ Found " . $streams_result->num_rows . " streams:<br>";
    while ($stream = $streams_result->fetch_assoc()) {
        echo "- ID: " . $stream['id'] . ", Name: " . htmlspecialchars($stream['name']) . ", Code: " . htmlspecialchars($stream['code']) . ", Active: " . $stream['is_active'] . "<br>";
    }
} else {
    echo "❌ No streams found in database<br>";
    
    // Try to create default streams
    echo "<h3>Attempting to create default streams...</h3>";
    
    $create_sql = "
    CREATE TABLE IF NOT EXISTS `streams` (
        `id` int NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL,
        `code` varchar(20) NOT NULL,
        `description` text,
        `active_days` json DEFAULT NULL,
        `period_start` time DEFAULT NULL,
        `period_end` time DEFAULT NULL,
        `break_start` time DEFAULT NULL,
        `break_end` time DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT '1',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
    ";
    
    if ($conn->query($create_sql)) {
        echo "✅ Created streams table<br>";
        
        $insert_sql = "
        INSERT IGNORE INTO `streams` (`id`, `name`, `code`, `description`, `is_active`) VALUES
        (1, 'Regular', 'REG', 'Regular weekday classes', 1),
        (2, 'Weekend', 'WKD', 'Weekend classes', 1),
        (3, 'Evening', 'EVE', 'Evening classes', 1);
        ";
        
        if ($conn->query($insert_sql)) {
            echo "✅ Inserted default streams<br>";
            
            // Check again
            $streams_result = $conn->query($streams_sql);
            if ($streams_result && $streams_result->num_rows > 0) {
                echo "✅ Now found " . $streams_result->num_rows . " streams:<br>";
                while ($stream = $streams_result->fetch_assoc()) {
                    echo "- ID: " . $stream['id'] . ", Name: " . htmlspecialchars($stream['name']) . ", Code: " . htmlspecialchars($stream['code']) . ", Active: " . $stream['is_active'] . "<br>";
                }
            }
        } else {
            echo "❌ Failed to insert default streams: " . $conn->error . "<br>";
        }
    } else {
        echo "❌ Failed to create streams table: " . $conn->error . "<br>";
    }
}

// Test 4: Check Stream Manager
echo "<h3>Test 4: Check Stream Manager</h3>";
if (file_exists('includes/stream_manager.php')) {
    include 'includes/stream_manager.php';
    $streamManager = getStreamManager();
    echo "✅ Stream Manager loaded<br>";
    echo "Current Stream ID: " . $streamManager->getCurrentStreamId() . "<br>";
    echo "Current Stream Name: " . $streamManager->getCurrentStreamName() . "<br>";
} else {
    echo "❌ Stream Manager file not found<br>";
}

$conn->close();
?>
