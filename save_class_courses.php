<?php
require_once 'connect.php';

// Expect JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
header('Content-Type: application/json');

if (!$data || !isset($data['class_id']) || !isset($data['assigned_course_ids'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid payload']);
    exit;
}

$class_id = (int)$data['class_id'];
$assigned_ids = array_map('intval', $data['assigned_course_ids']);

// Allow optional semester and academic_year from client; otherwise compute defaults
$semester = isset($data['semester']) ? $conn->real_escape_string($data['semester']) : 'first';
if (!in_array($semester, ['first','second','summer'])) $semester = 'first';
$academic_year = '';
if (isset($data['academic_year']) && is_string($data['academic_year']) && trim($data['academic_year']) !== '') {
    $academic_year = $conn->real_escape_string(trim($data['academic_year']));
} else {
    // compute current academic year as YYYY/YYYY+1 assuming academic year starts in Aug
    $m = (int)date('n');
    $y = (int)date('Y');
    if ($m >= 8) {
        $academic_year = $y . '/' . ($y + 1);
    } else {
        $academic_year = ($y - 1) . '/' . $y;
    }
}

if ($class_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid class id']);
    exit;
}

// Begin transaction
$conn->begin_transaction();
try {
    // Deactivate all existing assignments for this class first
    $stmt = $conn->prepare("UPDATE class_courses SET is_active = 0 WHERE class_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $class_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to deactivate existing mappings: ' . $stmt->error);
        }
        $stmt->close();
    } else {
        throw new Exception('Prepare failed (deactivate): ' . $conn->error);
    }

    // Check schema requirements for class_courses table to give a helpful error
    $colRes = $conn->query("SHOW COLUMNS FROM class_courses LIKE 'lecturer_id'");
    $lecturerAllowsNull = true;
    if ($colRes && $colRes->num_rows > 0) {
        $col = $colRes->fetch_assoc();
        $lecturerAllowsNull = (strtoupper($col['Null']) === 'YES');
    }

    // Insert or reactivate provided assignments
    foreach ($assigned_ids as $course_id) {
        // Try to reactivate existing mapping
        $stmt = $conn->prepare("UPDATE class_courses SET is_active = 1 WHERE class_id = ? AND course_id = ?");
        if ($stmt) {
            $stmt->bind_param('ii', $class_id, $course_id);
            if (!$stmt->execute()) {
                throw new Exception('Failed to reactivate mapping: ' . $stmt->error);
            }
            $affected = $stmt->affected_rows;
            $stmt->close();
        } else {
            throw new Exception('Prepare failed (reactivate): ' . $conn->error);
        }

        if (empty($affected) || $affected === 0) {
            // If lecturer_id does not allow NULL, stop with helpful message
            if (!$lecturerAllowsNull) {
                throw new Exception('Database schema requires a non-null lecturer_id for class_courses. Run the migration to allow NULL lecturer_id or supply a lecturer when assigning courses.');
            }

            // Insert new mapping. lecturer_id will be NULL; include semester and academic_year.
            $stmt = $conn->prepare("INSERT INTO class_courses (class_id, course_id, lecturer_id, semester, academic_year, is_active) VALUES (?, ?, NULL, ?, ?, 1)");
            if ($stmt) {
                $stmt->bind_param('iiss', $class_id, $course_id, $semester, $academic_year);
                if (!$stmt->execute()) {
                    throw new Exception('Failed to insert mapping: ' . $stmt->error);
                }
                $stmt->close();
            } else {
                throw new Exception('Prepare failed (insert): ' . $conn->error);
            }
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Saved']);
} catch (Exception $e) {
    $conn->rollback();
    error_log('save_class_courses error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}


