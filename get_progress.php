<?php
header('Content-Type: application/json');

// Basic validation
$token = isset($_GET['token']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['token']) : '';
if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'missing token']);
    exit;
}

$file = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'progress_' . $token . '.json';
if (!file_exists($file)) {
    echo json_encode(['percent' => 0, 'generation' => 0, 'total' => 0, 'bestFitness' => 0, 'done' => false]);
    exit;
}

$raw = @file_get_contents($file);
if ($raw === false) {
    echo json_encode(['percent' => 0, 'generation' => 0, 'total' => 0, 'bestFitness' => 0, 'done' => false]);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    echo json_encode(['percent' => 0, 'generation' => 0, 'total' => 0, 'bestFitness' => 0, 'done' => false]);
    exit;
}

// Optionally, consider deleting stale files if done
echo json_encode($data);
?>

