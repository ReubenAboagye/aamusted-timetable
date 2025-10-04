<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
include_once 'connect.php';

// Get inactive records grouped by table
$inactive_data = [];
$total_inactive = 0;

$tables = [
    'programs' => ['name' => 'Programs', 'icon' => 'fas fa-graduation-cap', 'color' => 'maroon'],
    'courses' => ['name' => 'Courses', 'icon' => 'fas fa-book', 'color' => 'blue'],
    'lecturers' => ['name' => 'Lecturers', 'icon' => 'fas fa-chalkboard-teacher', 'color' => 'green'],
    'departments' => ['name' => 'Departments', 'icon' => 'fas fa-building', 'color' => 'gold'],
    'rooms' => ['name' => 'Rooms', 'icon' => 'fas fa-door-open', 'color' => 'maroon'],
    'room_types' => ['name' => 'Room Types', 'icon' => 'fas fa-tags', 'color' => 'blue'],
    'classes' => ['name' => 'Classes', 'icon' => 'fas fa-users', 'color' => 'green'],
    'streams' => ['name' => 'Streams', 'icon' => 'fas fa-stream', 'color' => 'gold']
];

foreach ($tables as $table_name => $table_info) {
    // Define which tables have a 'code' column
    $tables_with_code = ['programs', 'courses', 'departments', 'classes', 'streams'];
    
    if (in_array($table_name, $tables_with_code)) {
        $query = "SELECT id, name, code FROM $table_name WHERE is_active = 0 ORDER BY name";
    } else {
        $query = "SELECT id, name FROM $table_name WHERE is_active = 0 ORDER BY name";
    }
    
    $result = $conn->query($query);
    
    $records = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Add null code for tables that don't have it
            if (!in_array($table_name, $tables_with_code)) {
                $row['code'] = null;
            }
            $records[] = $row;
        }
    }
    
    $record_count = count($records);
    $total_inactive += $record_count;
    
    $inactive_data[$table_name] = [
        'info' => $table_info,
        'records' => $records,
        'count' => $record_count
    ];
}

$conn->close();

// Return JSON response
echo json_encode([
    'success' => true,
    'data' => $inactive_data,
    'total_inactive' => $total_inactive
]);
?>
