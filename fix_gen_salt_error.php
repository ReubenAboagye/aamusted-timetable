<?php
/**
 * Fix for gen_salt function error in admin user creation
 * This script creates the missing gen_salt function in the database
 */

include 'connect.php';

echo "<h2>Fixing gen_salt Function Error</h2>\n";

try {
    // Check if gen_salt function already exists
    $check_query = "SELECT COUNT(*) as count FROM information_schema.routines WHERE routine_name = 'gen_salt' AND routine_schema = DATABASE()";
    $result = $conn->query($check_query);
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        echo "<p style='color: green;'>✓ gen_salt function already exists</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ gen_salt function not found, creating it...</p>\n";
        
        // Create the gen_salt function
        $create_function_sql = "
        DELIMITER $$
        CREATE FUNCTION gen_salt(type VARCHAR(10))
        RETURNS VARCHAR(16)
        DETERMINISTIC
        READS SQL DATA
        BEGIN
            DECLARE salt VARCHAR(16);
            DECLARE chars VARCHAR(62) DEFAULT 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            DECLARE i INT DEFAULT 1;
            DECLARE len INT DEFAULT 16;
            
            SET salt = '';
            
            WHILE i <= len DO
                SET salt = CONCAT(salt, SUBSTRING(chars, FLOOR(1 + RAND() * 62), 1));
                SET i = i + 1;
            END WHILE;
            
            RETURN salt;
        END$$
        DELIMITER ;
        ";
        
        if ($conn->multi_query($create_function_sql)) {
            echo "<p style='color: green;'>✓ gen_salt function created successfully</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Failed to create gen_salt function: " . $conn->error . "</p>\n";
        }
    }
    
    // Test the function
    echo "<h3>Testing gen_salt Function</h3>\n";
    $test_query = "SELECT gen_salt('md5') as salt";
    $result = $conn->query($test_query);
    
    if ($result) {
        $salt = $result->fetch_assoc()['salt'];
        echo "<p style='color: green;'>✓ Function test successful. Generated salt: $salt</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Function test failed: " . $conn->error . "</p>\n";
    }
    
    // Alternative: Create a simpler PHP-based solution
    echo "<h3>Alternative PHP Solution</h3>\n";
    echo "<p>If the database function approach doesn't work, here's a PHP-based solution:</p>\n";
    
    $php_salt_function = '
    function generateSalt($length = 16) {
        $characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $salt = "";
        for ($i = 0; $i < $length; $i++) {
            $salt .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $salt;
    }
    
    // Usage in admin user creation:
    $salt = generateSalt(16);
    $hashed_password = hash("sha256", $password . $salt);
    ';
    
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($php_salt_function) . "</pre>\n";
    
    // Check if there are any admin-related tables
    echo "<h3>Database Tables Check</h3>\n";
    $tables_query = "SHOW TABLES LIKE '%admin%' OR SHOW TABLES LIKE '%user%'";
    $result = $conn->query("SHOW TABLES");
    
    $admin_tables = [];
    while ($row = $result->fetch_array()) {
        $table_name = $row[0];
        if (stripos($table_name, 'admin') !== false || stripos($table_name, 'user') !== false) {
            $admin_tables[] = $table_name;
        }
    }
    
    if (!empty($admin_tables)) {
        echo "<p>Found admin/user related tables:</p>\n";
        echo "<ul>\n";
        foreach ($admin_tables as $table) {
            echo "<li>$table</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ No admin/user tables found. You may need to create the admin user table first.</p>\n";
        
        // Provide SQL to create admin table
        $create_admin_table_sql = "
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20),
            password_hash VARCHAR(255) NOT NULL,
            salt VARCHAR(16) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        );
        ";
        
        echo "<h4>Suggested Admin Table Creation SQL:</h4>\n";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($create_admin_table_sql) . "</pre>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}

$conn->close();
?>

