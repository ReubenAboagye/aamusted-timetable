<?php
$file = 'rooms.php';
$content = file_get_contents($file);

$matches = [];
preg_match_all('/<\?php(.*?)\?>/s', $content, $matches);
$blocks = $matches[1] ?? [];

$totalBal = 0;
foreach ($blocks as $bi => $block) {
    $lines = explode("\n", $block);
    $bal = 0;
    echo "Block #".($bi+1)."\n";
    foreach ($lines as $i => $line) {
        $open = substr_count($line, '{');
        $close = substr_count($line, '}');
        $prev = $bal;
        $bal += $open - $close;
        if ($open || $close) {
            echo ($i+1).": open+$open close+$close bal=$bal\n";
        }
    }
    echo "Block final balance: $bal\n\n";
    $totalBal += $bal;
}
echo "Total balance across PHP blocks: $totalBal\n";


