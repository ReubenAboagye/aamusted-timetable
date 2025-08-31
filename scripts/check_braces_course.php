<?php
$file = 'course_roomtype.php';
$lines = file($file);
$bal = 0;
foreach ($lines as $i => $line) {
    $open = substr_count($line, '{');
    $close = substr_count($line, '}');
    $prev = $bal;
    $bal += $open - $close;
    if ($bal !== $prev) {
        echo ($i+1) . ": open+" . $open . " close+" . $close . " bal=" . $bal . "\n";
    }
}
echo "Final balance: $bal\n";


