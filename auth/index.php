//the project is known to redirect to auth/index.php after login but we want it to redirect to the main root directory index.php

//redirect to /timetable/index.php
<?php
define('REDIRECT_TARGET', '/timetable/index.php');
// Set HTTP status code for redirect (302 Found)
header('Location: /timetable/index.php', true, 302);

// Optional: Log the redirect for debugging
// error_log('Redirecting to /timetable/index.php after authentication');

exit;
?>
?>
