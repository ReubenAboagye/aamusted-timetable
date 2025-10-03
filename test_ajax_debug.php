<?php
// Simple test for AJAX API
include 'connect.php';

// Start session for CSRF protection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo "CSRF Token: " . $_SESSION['csrf_token'];
echo "<br>";
echo "Session ID: " . session_id();
echo "<br>";

// Test AJAX API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>POST Data:</h3>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h3>CSRF Check:</h3>";
    if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
        echo "✅ CSRF token is valid";
    } else {
        echo "❌ CSRF token validation failed";
        echo "<br>Expected: " . $_SESSION['csrf_token'];
        echo "<br>Received: " . ($_POST['csrf_token'] ?? 'none');
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>AAMUSTED - AJAX Test</title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/timetable/images/aamustedLog.ico">
    <link rel="shortcut icon" type="image/x-icon" href="/timetable/images/aamustedLog.ico">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="js/ajax-utils.js"></script>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
</head>
<body>
    <h2>AJAX API Test</h2>
    
    <button onclick="testAjax()">Test AJAX Call</button>
    <div id="result"></div>
    
    <script>
    function testAjax() {
        console.log('CSRF Token:', AjaxUtils.csrfToken);
        
        AjaxUtils.makeRequest('department', 'get_list')
        .then(data => {
            console.log('Success:', data);
            document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('result').innerHTML = '<div style="color: red;">Error: ' + error.message + '</div>';
        });
    }
    
    // Test on page load
    $(document).ready(function() {
        console.log('Page loaded, CSRF token:', AjaxUtils.csrfToken);
    });
    </script>
</body>
</html>