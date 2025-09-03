<?php
/**
 * CORRECTED: Get Courses
 * Courses are GLOBAL - no stream filtering needed
 * Provides department-oriented filtering for professional class-course assignments
 */

header('Content-Type: application/json');
include 'connect.php';

$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$level_id = isset($_GET['level_id']) ? (int)$_GET['level_id'] : 0;
$course_type = isset($_GET['course_type']) ? $_GET['course_type'] : '';
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0; // For compatibility checking

// CORRECTED: Courses are global - no stream filtering
$query = "SELECT 
              co.id, 
              co.course_code, 
              co.course_name,
              co.credits,
              co.hours_per_week,
              co.course_type,
              co.preferred_room_type,
              d.name as department_name,
              l.name as level_name,
              l.numeric_value as level_number
          FROM courses co
          LEFT JOIN departments d ON co.department_id = d.id
          LEFT JOIN levels l ON co.level_id = l.id
          WHERE co.is_active = 1";

$params = [];
$types = '';

if ($department_id > 0) {
    $query .= " AND co.department_id = ?";
    $params[] = $department_id;
    $types .= 'i';
}

if ($level_id > 0) {
    $query .= " AND co.level_id = ?";
    $params[] = $level_id;
    $types .= 'i';
}

if (!empty($course_type)) {
    $query .= " AND co.course_type = ?";
    $params[] = $course_type;
    $types .= 's';
}

$query .= " ORDER BY d.name, l.numeric_value, co.course_code";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $course_data = [
            'id' => $row['id'],
            'code' => htmlspecialchars($row['course_code']),
            'name' => htmlspecialchars($row['course_name']),
            'credits' => $row['credits'],
            'hours_per_week' => $row['hours_per_week'],
            'course_type' => $row['course_type'],
            'preferred_room_type' => $row['preferred_room_type'],
            'department_name' => htmlspecialchars($row['department_name'] ?? ''),
            'level_name' => htmlspecialchars($row['level_name'] ?? ''),
            'level_number' => $row['level_number']
        ];
        
        // If class_id is provided, add compatibility information
        if ($class_id > 0) {
            $compatibility_sql = "SELECT 
                                     c.department_id as class_dept,
                                     c.level_id as class_level,
                                     d.name as class_dept_name,
                                     l.name as class_level_name
                                  FROM classes c
                                  LEFT JOIN departments d ON c.department_id = d.id
                                  LEFT JOIN levels l ON c.level_id = l.id
                                  WHERE c.id = ?";
            $comp_stmt = $conn->prepare($compatibility_sql);
            $comp_stmt->bind_param('i', $class_id);
            $comp_stmt->execute();
            $comp_result = $comp_stmt->get_result();
            
            if ($comp_data = $comp_result->fetch_assoc()) {
                // Calculate compatibility score
                $score = 0;
                $compatibility_notes = [];
                
                // Department compatibility
                if ($comp_data['class_dept'] == $row['department_id']) {
                    $score += 10;
                    $compatibility_notes[] = 'Same department';
                } else {
                    if ($row['course_type'] === 'core') {
                        $score -= 5;
                        $compatibility_notes[] = 'Different department (core course)';
                    } else {
                        $score += 2;
                        $compatibility_notes[] = 'Cross-departmental (acceptable for ' . $row['course_type'] . ')';
                    }
                }
                
                // Level compatibility
                if ($comp_data['class_level'] == $row['level_id']) {
                    $score += 15;
                    $compatibility_notes[] = 'Same level';
                } else {
                    $score -= 15;
                    $compatibility_notes[] = 'Level mismatch';
                }
                
                // Course type bonus
                switch ($row['course_type']) {
                    case 'core':
                        $score += 8;
                        break;
                    case 'elective':
                        $score += 3;
                        break;
                    case 'practical':
                        $score += 5;
                        break;
                }
                
                $course_data['compatibility'] = [
                    'score' => $score,
                    'status' => $score >= 20 ? 'highly_recommended' : 
                               ($score >= 10 ? 'recommended' : 
                               ($score >= 0 ? 'acceptable' : 'not_recommended')),
                    'notes' => $compatibility_notes,
                    'class_department' => $comp_data['class_dept_name'],
                    'class_level' => $comp_data['class_level_name']
                ];
            }
            $comp_stmt->close();
        }
        
        $courses[] = $course_data;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $courses,
        'total' => count($courses),
        'filters_applied' => [
            'department_id' => $department_id,
            'level_id' => $level_id,
            'course_type' => $course_type,
            'class_id' => $class_id
        ],
        'note' => 'Courses are global - no stream filtering applied'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed: ' . $conn->error,
        'data' => []
    ]);
}
?>