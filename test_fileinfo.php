<?php
// Test if fileinfo extension is available
echo "<h2>PHP Extensions Test</h2>";

if (extension_loaded('fileinfo')) {
    echo "<p style='color: green;'>✓ fileinfo extension is loaded</p>";
} else {
    echo "<p style='color: red;'>✗ fileinfo extension is NOT loaded</p>";
}

if (function_exists('finfo_open')) {
    echo "<p style='color: green;'>✓ finfo_open function is available</p>";
} else {
    echo "<p style='color: red;'>✗ finfo_open function is NOT available</p>";
}

echo "<h3>All loaded extensions:</h3>";
echo "<pre>" . implode("\n", get_loaded_extensions()) . "</pre>";

echo "<h3>PHP Info:</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>PHP INI file: " . php_ini_loaded_file() . "</p>";
?>

