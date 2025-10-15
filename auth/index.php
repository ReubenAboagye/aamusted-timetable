//the project is known to redirect to auth/index.php after login but we want it to redirect to the main root directory index.php

//redirect to /timetable/index.php
<?php
include_once __DIR__ . '/../includes/auth.php';

$base = auth_base_path();
$target = rtrim($base, '/') . '/';
header('Location: ' . auth_normalize_path($target), true, 302);
exit;
?>
