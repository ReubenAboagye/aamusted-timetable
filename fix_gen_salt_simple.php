<?php
/**
 * Fix for gen_salt function error - Simple MySQL solution
 */

include 'connect.php';

echo "<h2>Fixing gen_salt Function Error</h2>\n";

try {
    // Create the gen_salt function using a simpler approach
    $create_function_sql = "
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
    END
    ";
    
    if ($conn->query($create_function_sql)) {
        echo "<p style='color: green;'>✓ gen_salt function created successfully</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Failed to create gen_salt function: " . $conn->error . "</p>\n";
        
        // Try alternative approach - create a stored procedure instead
        echo "<p style='color: orange;'>⚠️ Trying alternative approach with stored procedure...</p>\n";
        
        $create_proc_sql = "
        CREATE PROCEDURE generate_salt(IN salt_type VARCHAR(10), OUT salt_value VARCHAR(16))
        BEGIN
            DECLARE chars VARCHAR(62) DEFAULT 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            DECLARE i INT DEFAULT 1;
            DECLARE len INT DEFAULT 16;
            
            SET salt_value = '';
            
            WHILE i <= len DO
                SET salt_value = CONCAT(salt_value, SUBSTRING(chars, FLOOR(1 + RAND() * 62), 1));
                SET i = i + 1;
            END WHILE;
        END
        ";
        
        if ($conn->query($create_proc_sql)) {
            echo "<p style='color: green;'>✓ generate_salt stored procedure created successfully</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Failed to create stored procedure: " . $conn->error . "</p>\n";
        }
    }
    
    // Test if function exists now
    $test_query = "SELECT gen_salt('md5') as salt";
    $result = $conn->query($test_query);
    
    if ($result) {
        $salt = $result->fetch_assoc()['salt'];
        echo "<p style='color: green;'>✓ Function test successful. Generated salt: $salt</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Function test failed: " . $conn->error . "</p>\n";
        
        // Provide PHP-based solution
        echo "<h3>PHP-Based Solution</h3>\n";
        echo "<p style='color: blue;'>Since the database function approach isn't working, here's a PHP solution:</p>\n";
        
        // Create a PHP function to generate salt
        function generateSalt($length = 16) {
            $characters = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
            $salt = "";
            for ($i = 0; $i < $length; $i++) {
                $salt .= $characters[rand(0, strlen($characters) - 1)];
            }
            return $salt;
        }
        
        // Test the PHP function
        $php_salt = generateSalt(16);
        echo "<p style='color: green;'>✓ PHP salt generation test successful. Generated salt: $php_salt</p>\n";
        
        // Show how to use it in admin user creation
        echo "<h4>How to fix the admin user creation:</h4>\n";
        echo "<p>Replace the database gen_salt() call with PHP code:</p>\n";
        
        $php_solution = '
        // Instead of using gen_salt() in SQL:
        // INSERT INTO admin_users (password_hash, salt) VALUES (SHA2(CONCAT(?, gen_salt("md5")), 256), gen_salt("md5"))
        
        // Use PHP to generate salt and hash:
        $salt = generateSalt(16);
        $hashed_password = hash("sha256", $password . $salt);
        
        $sql = "INSERT INTO admin_users (first_name, last_name, email, phone, password_hash, salt) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $first_name, $last_name, $email, $phone, $hashed_password, $salt);
        ';
        
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($php_solution) . "</pre>\n";
    }
    
    // Check for admin tables
    echo "<h3>Checking for Admin Tables</h3>\n";
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
        echo "<p style='color: orange;'>⚠️ No admin/user tables found.</p>\n";
        echo "<p>You may need to create the admin user table first. Here's a suggested SQL:</p>\n";
        
        $create_table_sql = "
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
        
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>" . htmlspecialchars($create_table_sql) . "</pre>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}

$conn->close();
?>

