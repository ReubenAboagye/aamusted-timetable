<?php
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/ga/GeneticAlgorithm.php';
require_once __DIR__ . '/ga/DBLoader.php';

// Resolve stream/semester
$stream_id = 3;
if (file_exists(__DIR__ . '/includes/stream_manager.php')) {
    require_once __DIR__ . '/includes/stream_manager.php';
    $sm = getStreamManager();
    $sid = $sm->getCurrentStreamId();
    if ($sid) { $stream_id = $sid; }
}
$semester = isset($argv[1]) ? (int)$argv[1] : 2;
if ($semester !== 1 && $semester !== 2) { $semester = 2; }

// Academic year (simple compute)
$m = (int)date('n');
$y = (int)date('Y');
$academic_year = ($m >= 8) ? ($y . '/' . ($y + 1)) : (($y - 1) . '/' . $y);

// Load data and run GA with very small generations for probing
$ga = new GeneticAlgorithm($conn, [
    'population_size' => 10,
    'generations' => 10,
    'mutation_rate' => 0.1,
    'crossover_rate' => 0.8,
    'stream_id' => $stream_id,
    'semester' => $semester,
    'academic_year' => $academic_year,
    'max_runtime' => 10
]);

$result = $ga->run();
$solution = $ga->getBestSolution();

// Map class id to name
$loader = new DBLoader($conn);
$data = $loader->loadAll(['stream_id' => $stream_id, 'semester' => $semester, 'academic_year' => $academic_year]);
$classNameById = [];
foreach ($data['classes'] as $c) { $classNameById[(int)$c['id']] = $c['name']; }

$labelsPerClass = [];
foreach ($solution['individual'] as $gene) {
    $cid = (int)$gene['class_id'];
    $label = $gene['division_label'] ?? '';
    if (!isset($labelsPerClass[$cid])) { $labelsPerClass[$cid] = []; }
    $labelsPerClass[$cid][$label] = true;
}

ksort($labelsPerClass);
foreach ($labelsPerClass as $cid => $labels) {
    $name = $classNameById[$cid] ?? ('class_'.$cid);
    echo $cid . "\t" . $name . "\tlabels=" . implode(',', array_keys($labels)) . "\n";
}
?>


