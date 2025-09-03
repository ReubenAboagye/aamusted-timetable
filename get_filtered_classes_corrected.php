<?php
/**
 * CORRECTED: Get Filtered Classes
 * Only classes are stream-filtered, everything else is global
 */

header('Content-Type: application/json');
include 'connect.php';

// Include corrected stream manager
if (file_exists(__DIR__ . '/includes/stream_manager_corrected.php')) {
    include_once __DIR__ . '/includes/stream_manager_corrected.php';
    $streamManager = getStreamManager();
    $current_stream_id = $streamManager->getCurrentStreamId();
} else {
    // Fallback if stream manager doesn't exist
    session_start();
    $current_stream_id = $_SESSION['current_stream_id'] ?? 1;
}

$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;
$level_id = isset($_GET['level_id']) ? (int)$_GET['level_id'] : 0;
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';

// CORRECTED: Only filter classes by stream_id (classes are the only stream-specific entity)
$query = "SELECT 
              c.id, 
              c.name, 
              c.class_code,
              c.capacity,
              c.current_enrollment,
              d.name as department_name,
              p.name as program_name,
              l.name as level_name,
              s.name as stream_name
          FROM classes c
          LEFT JOIN departments d ON c.department_id = d.id
          LEFT JOIN programs p ON c.program_id = p.id
          LEFT JOIN levels l ON c.level_id = l.id
          LEFT JOIN streams s ON c.stream_id = s.id
          WHERE c.is_active = 1 AND c.stream_id = ?";

$params = [$current_stream_id];
$types = 'i';

// Additional filters (these are for global entities, so no stream filtering needed)
if ($department_id > 0) {
    $query .= " AND c.department_id = ?";
    $params[] = $department_id;
    $types .= 'i';
}

if ($program_id > 0) {
    $query .= " AND c.program_id = ?";
    $params[] = $program_id;
    $types .= 'i';
}

if ($level_id > 0) {
    $query .= " AND c.level_id = ?";
    $params[] = $level_id;
    $types .= 'i';
}

if (!empty($search_name)) {
    $query .= " AND c.name LIKE ?";
    $params[] = "%$search_name%";
    $types .= 's';
}

$query .= " ORDER BY d.name, p.name, l.numeric_value, c.name";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = [
            'id' => $row['id'],
            'name' => htmlspecialchars($row['name']),
            'class_code' => htmlspecialchars($row['class_code'] ?? ''),
            'capacity' => $row['capacity'],
            'current_enrollment' => $row['current_enrollment'],
            'department_name' => htmlspecialchars($row['department_name'] ?? ''),
            'program_name' => htmlspecialchars($row['program_name'] ?? ''),
            'level_name' => htmlspecialchars($row['level_name'] ?? ''),
            'stream_name' => htmlspecialchars($row['stream_name'] ?? ''),
            'enrollment_percentage' => $row['capacity'] > 0 ? round(($row['current_enrollment'] / $row['capacity']) * 100, 1) : 0
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $classes,
        'total' => count($classes),
        'stream' => [
            'id' => $current_stream_id,
            'name' => $streamManager ? $streamManager->getCurrentStreamName() : 'Unknown'
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed',
        'data' => []
    ]);
}
?>
