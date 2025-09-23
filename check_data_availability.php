<?php
/**
 * Check available data and fix data loading issues
 */

include 'connect.php';
require_once __DIR__ . '/ga/DBLoader.php';

echo "<h2>Data Availability Check</h2>\n";

// Include stream manager
if (file_exists(__DIR__ . '/includes/stream_manager.php')) include_once __DIR__ . '/includes/stream_manager.php';
$streamManager = getStreamManager();
$current_stream_id = $streamManager->getCurrentStreamId();

echo "<h3>1. Available Streams</h3>\n";
$query = "SELECT id, name FROM streams WHERE is_active = 1";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $selected = ($row['id'] == $current_stream_id) ? " (CURRENT)" : "";
    echo "<p>Stream {$row['id']}: {$row['name']}$selected</p>\n";
}

echo "<h3>2. Available Academic Years</h3>\n";
$query = "SELECT DISTINCT academic_year FROM timetable ORDER BY academic_year DESC";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    echo "<p>Academic Year: {$row['academic_year']}</p>\n";
}

echo "<h3>3. Available Semesters</h3>\n";
$query = "SELECT DISTINCT semester FROM timetable ORDER BY semester";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    echo "<p>Semester: {$row['semester']}</p>\n";
}

echo "<h3>4. Class Courses by Stream</h3>\n";
$query = "
    SELECT 
        c.stream_id,
        s.name as stream_name,
        COUNT(cc.id) as class_course_count
    FROM class_courses cc
    JOIN classes c ON cc.class_id = c.id
    JOIN streams s ON c.stream_id = s.id
    WHERE cc.is_active = 1 AND c.is_active = 1
    GROUP BY c.stream_id, s.name
    ORDER BY c.stream_id
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $selected = ($row['stream_id'] == $current_stream_id) ? " (CURRENT)" : "";
    echo "<p>Stream {$row['stream_id']} ({$row['stream_name']}): {$row['class_course_count']} class courses$selected</p>\n";
}

echo "<h3>5. Testing Data Loading</h3>\n";

$loader = new DBLoader($conn);

// Test different combinations
$test_combinations = [
    ['stream_id' => $current_stream_id, 'semester' => 2, 'academic_year' => '2024/2025'],
    ['stream_id' => $current_stream_id, 'semester' => 1, 'academic_year' => '2024/2025'],
    ['stream_id' => $current_stream_id, 'semester' => 2],
    ['stream_id' => $current_stream_id, 'semester' => 1],
    ['stream_id' => $current_stream_id],
    []
];

foreach ($test_combinations as $i => $options) {
    echo "<h4>Test " . ($i + 1) . ": " . json_encode($options) . "</h4>\n";
    
    try {
        $data = $loader->loadAll($options);
        echo "<p>Class courses: " . count($data['class_courses']) . "</p>\n";
        echo "<p>Lecturer courses: " . count($data['lecturer_courses']) . "</p>\n";
        echo "<p>Rooms: " . count($data['rooms']) . "</p>\n";
        echo "<p>Time slots: " . count($data['time_slots']) . "</p>\n";
        echo "<p>Days: " . count($data['days']) . "</p>\n";
        
        if (count($data['class_courses']) > 0) {
            echo "<p style='color: green;'>✓ Found class courses!</p>\n";
            break;
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
    }
}

echo "<h3>6. Sample Class Courses</h3>\n";
$query = "
    SELECT 
        cc.id,
        cc.class_id,
        cc.course_id,
        cc.semester,
        cc.academic_year,
        c.name as class_name,
        co.name as course_name,
        c.stream_id
    FROM class_courses cc
    JOIN classes c ON cc.class_id = c.id
    JOIN courses co ON cc.course_id = co.id
    WHERE cc.is_active = 1 AND c.is_active = 1
    LIMIT 10
";
$result = $conn->query($query);
echo "<table border='1' style='border-collapse: collapse;'>\n";
echo "<tr><th>ID</th><th>Class</th><th>Course</th><th>Stream</th><th>Semester</th><th>Academic Year</th></tr>\n";
while ($row = $result->fetch_assoc()) {
    echo "<tr>\n";
    echo "<td>{$row['id']}</td>\n";
    echo "<td>{$row['class_name']}</td>\n";
    echo "<td>{$row['course_name']}</td>\n";
    echo "<td>{$row['stream_id']}</td>\n";
    echo "<td>{$row['semester']}</td>\n";
    echo "<td>{$row['academic_year']}</td>\n";
    echo "</tr>\n";
}
echo "</table>\n";

$conn->close();
?>


