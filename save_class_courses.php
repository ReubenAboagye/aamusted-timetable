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
        $stmt->execute();
        $stmt->close();
    } else {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    // Insert or reactivate provided assignments
    foreach ($assigned_ids as $course_id) {
        // Try to reactivate existing mapping
        $stmt = $conn->prepare("UPDATE class_courses SET is_active = 1 WHERE class_id = ? AND course_id = ?");
        if ($stmt) {
            $stmt->bind_param('ii', $class_id, $course_id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
        } else {
            throw new Exception('Prepare failed (reactivate): ' . $conn->error);
        }

        if (empty($affected) || $affected === 0) {
            // Insert new mapping
            $stmt = $conn->prepare("INSERT INTO class_courses (class_id, course_id, is_active) VALUES (?, ?, 1)");
            if ($stmt) {
                $stmt->bind_param('ii', $class_id, $course_id);
                $stmt->execute();
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


