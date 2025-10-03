<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/ga/DBLoader.php';

$loader = new DBLoader($conn);
$data = $loader->loadAll();

$byClass = [];
foreach ($data['class_courses'] as $cc) {
    $cid = (int)$cc['class_id'];
    if (!isset($byClass[$cid])) { $byClass[$cid] = ['name' => $cc['class_name'] ?? ('class_'.$cid), 'labels' => [], 'count' => 0]; }
    $label = $cc['division_label'] ?? '';
    $byClass[$cid]['labels'][$label] = true;
    $byClass[$cid]['count']++;
}

ksort($byClass);
foreach ($byClass as $cid => $info) {
    $labels = implode(',', array_keys($info['labels']));
    echo $cid . "\t" . $info['name'] . "\tlabels=" . $labels . "\trows=" . $info['count'] . "\n";
}
?>


