<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Setup</h1>";

try {
    include 'connect.php';
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    echo "<p>✓ Database connection successful</p>";
    
    // Read and execute add_sample_data.sql
    echo "<h2>Adding sample data...</h2>";
    $sql_file = 'add_sample_data.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        $statements = explode(';', $sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                if ($conn->query($statement)) {
                    echo "<p>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
                } else {
                    echo "<p>✗ Error: " . $conn->error . "</p>";
                }
            }
        }
    } else {
        echo "<p>✗ Sample data file not found</p>";
    }
    
    // Read and execute add_sample_programs.sql
    echo "<h2>Adding sample programs...</h2>";
    $sql_file = 'add_sample_programs.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        $statements = explode(';', $sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                if ($conn->query($statement)) {
                    echo "<p>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
                } else {
                    echo "<p>✗ Error: " . $conn->error . "</p>";
                }
            }
        }
    } else {
        echo "<p>✗ Sample programs file not found</p>";
    }
    
    // Verify the data
    echo "<h2>Verifying data...</h2>";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM departments");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Departments: " . $row['count'] . "</p>";
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM programs");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Programs: " . $row['count'] . "</p>";
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM levels");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Levels: " . $row['count'] . "</p>";
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM streams");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>Streams: " . $row['count'] . "</p>";
    }
    
    $conn->close();
    echo "<h2>Setup Complete!</h2>";
    echo "<p><a href='programs.php'>Go to Programs Page</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
