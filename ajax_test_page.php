<!DOCTYPE html>
<html>
<head>
    <title>AJAX Test</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="js/ajax-utils.js"></script>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? 'test-token'; ?>">
</head>
<body>
    <h2>AJAX Test Page</h2>
    
    <div id="csrf-info"></div>
    <div id="test-results"></div>
    
    <button onclick="testAjax()">Test AJAX Call</button>
    <button onclick="testCSRF()">Test CSRF Token</button>
    
    <script>
    function testCSRF() {
        const token = AjaxUtils.csrfToken;
        document.getElementById('csrf-info').innerHTML = `
            <h3>CSRF Token Info:</h3>
            <p><strong>Token:</strong> ${token || 'NOT FOUND'}</p>
            <p><strong>Length:</strong> ${token ? token.length : 0}</p>
            <p><strong>From Meta:</strong> ${document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || 'NOT FOUND'}</p>
        `;
    }
    
    function testAjax() {
        console.log('Testing AJAX...');
        console.log('CSRF Token:', AjaxUtils.csrfToken);
        
        AjaxUtils.makeRequest('department', 'get_list', {}, 3, 'ajax_test_simple.php')
        .then(data => {
            console.log('Success:', data);
            document.getElementById('test-results').innerHTML = `
                <h3>AJAX Test Results:</h3>
                <div style="background: #d4edda; padding: 10px; border-radius: 5px;">
                    <strong>Success:</strong> ${data.success}<br>
                    <strong>Message:</strong> ${data.message}<br>
                    <strong>Data Count:</strong> ${data.data ? data.data.length : 0}
                </div>
                <pre>${JSON.stringify(data, null, 2)}</pre>
            `;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('test-results').innerHTML = `
                <h3>AJAX Test Results:</h3>
                <div style="background: #f8d7da; padding: 10px; border-radius: 5px;">
                    <strong>Error:</strong> ${error.message}
                </div>
            `;
        });
    }
    
    // Test on page load
    $(document).ready(function() {
        console.log('Page loaded');
        console.log('AjaxUtils available:', typeof AjaxUtils);
        console.log('CSRF Token:', AjaxUtils.csrfToken);
        testCSRF();
    });
    </script>
</body>
</html>