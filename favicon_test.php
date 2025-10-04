<?php
// Favicon test page
$pageTitle = 'Favicon Test';
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-check-circle text-success"></i> Favicon Test Page</h4>
                </div>
                <div class="card-body">
                    <h5>Favicon Troubleshooting Steps:</h5>
                    <ol>
                        <li><strong>Check Browser Tab:</strong> Look for the AAMUSTED icon in your browser tab</li>
                        <li><strong>Clear Browser Cache:</strong> 
                            <ul>
                                <li>Chrome: Ctrl+Shift+Delete → Clear cached images and files</li>
                                <li>Firefox: Ctrl+Shift+Delete → Cached Web Content</li>
                                <li>Edge: Ctrl+Shift+Delete → Cached images and files</li>
                            </ul>
                        </li>
                        <li><strong>Hard Refresh:</strong> Press Ctrl+F5 or Ctrl+Shift+R</li>
                        <li><strong>Check Direct Access:</strong> Try accessing the favicon directly: 
                            <a href="images/aamustedLog.ico" target="_blank">images/aamustedLog.ico</a>
                        </li>
                        <li><strong>Try Different Browser:</strong> Test in Chrome, Firefox, Edge</li>
                        <li><strong>XAMPP Issue:</strong> If you see XAMPP icon, it's normal - browsers cache favicons aggressively</li>
                        <li><strong>Browser Restart:</strong> Close and reopen your browser completely</li>
                        <li><strong>Incognito/Private Mode:</strong> Test in incognito/private browsing mode</li>
                    </ol>
                    
                    <div class="alert alert-info mt-3">
                        <strong>Note:</strong> Some browsers cache favicons aggressively. It may take a few minutes or a browser restart to see the new favicon.
                    </div>
                    
                    <h6>Current Favicon Implementation:</h6>
                    <pre><code>&lt;link rel="icon" type="image/x-icon" href="images/aamustedLog.ico"&gt;
&lt;link rel="shortcut icon" type="image/x-icon" href="images/aamustedLog.ico"&gt;
&lt;link rel="apple-touch-icon" href="images/aamustedLog.ico"&gt;</code></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
