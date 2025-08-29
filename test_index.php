<?php
// Simple test version of index.php
include 'connect.php';

// Simple query test
$dept_query = "SELECT COUNT(*) AS dept_count FROM departments WHERE is_active = 1";
$dept_result = $conn->query($dept_query);
$dept_count = 0;
if ($dept_result) {
    $dept_row = $dept_result->fetch_assoc();
    $dept_count = $dept_row['dept_count'];
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-box { border: 1px solid #ccc; padding: 20px; margin: 10px; background: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Test Dashboard</h1>
    <div class="test-box">
        <h2>Database Test</h2>
        <p>Department count: <?php echo $dept_count; ?></p>
        <p>If you see a number above, the database connection is working.</p>
    </div>
    
    <div class="test-box">
        <h2>Include Test</h2>
        <p>Testing includes:</p>
        <?php 
        if (file_exists('includes/header.php')) {
            echo "✓ Header file exists<br>";
        } else {
            echo "✗ Header file missing<br>";
        }
        
        if (file_exists('includes/sidebar.php')) {
            echo "✓ Sidebar file exists<br>";
        } else {
            echo "✗ Sidebar file missing<br>";
        }
        
        if (file_exists('includes/footer.php')) {
            echo "✓ Footer file exists<br>";
        } else {
            echo "✗ Footer file missing<br>";
        }
        ?>
    </div>
    
    <div class="test-box">
        <h2>PHP Info</h2>
        <p>PHP Version: <?php echo phpversion(); ?></p>
        <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
</body>
</html>
