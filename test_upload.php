<?php
// Test PHP upload configuration
echo "<h2>PHP Upload Configuration Test</h2>";

echo "<h3>Upload Settings:</h3>";
echo "<p>file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "</p>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";
echo "<p>max_execution_time: " . ini_get('max_execution_time') . "</p>";
echo "<p>memory_limit: " . ini_get('memory_limit') . "</p>";

echo "<h3>Test File Upload:</h3>";
echo '<form method="POST" enctype="multipart/form-data">';
echo '<input type="file" name="testfile" accept=".csv">';
echo '<input type="submit" value="Test Upload">';
echo '</form>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Upload Results:</h3>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    if (isset($_FILES['testfile'])) {
        $file = $_FILES['testfile'];
        echo "<h4>File Details:</h4>";
        echo "<p>Name: " . ($file['name'] ?? 'not set') . "</p>";
        echo "<p>Type: " . ($file['type'] ?? 'not set') . "</p>";
        echo "<p>Size: " . ($file['size'] ?? 'not set') . "</p>";
        echo "<p>Error: " . ($file['error'] ?? 'not set') . "</p>";
        echo "<p>Temp Name: " . ($file['tmp_name'] ?? 'not set') . "</p>";
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            echo "<p style='color: green;'>✓ Upload successful!</p>";
        } else {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            echo "<p style='color: red;'>✗ Upload failed: " . ($errors[$file['error']] ?? 'Unknown error') . "</p>";
        }
    }
}
?>

