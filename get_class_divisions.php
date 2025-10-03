<?php
header('Content-Type: application/json');

include 'connect.php';

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($class_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid class ID',
        'divisions' => []
    ]);
    exit;
}

try {
    // Get divisions that have scheduled timetable entries
    $stmt = $conn->prepare("
        SELECT DISTINCT t.division_label 
        FROM timetable t 
        JOIN class_courses cc ON t.class_course_id = cc.id 
        WHERE cc.class_id = ? AND t.division_label IS NOT NULL 
        ORDER BY t.division_label
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $divisions = [];
    while ($row = $result->fetch_assoc()) {
        $divisions[] = $row['division_label'];
    }
    
    $stmt->close();
    
    // If no divisions found in timetable, get theoretical divisions from class
    if (empty($divisions)) {
        $stmt2 = $conn->prepare("SELECT divisions_count FROM classes WHERE id = ?");
        if ($stmt2) {
            $stmt2->bind_param('i', $class_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            if ($row2 = $result2->fetch_assoc()) {
                $divisions_count = max(1, (int)$row2['divisions_count']);
                // Generate division labels (A, B, C, etc.)
                for ($i = 0; $i < $divisions_count; $i++) {
                    $label = '';
                    $n = $i;
                    while (true) {
                        $label = chr(65 + ($n % 26)) . $label;
                        $n = intdiv($n, 26) - 1;
                        if ($n < 0) break;
                    }
                    $divisions[] = $label;
                }
            }
            $stmt2->close();
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Divisions loaded successfully',
        'divisions' => $divisions
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'divisions' => []
    ]);
}

$conn->close();
?>
