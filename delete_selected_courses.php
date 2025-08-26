<?php
include 'connect.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['courses']) && is_array($data['courses'])) {
    $courseIds = $data['courses'];
    error_log("Attempting to delete courses with IDs: " . implode(', ', $courseIds));

    // Create placeholders for each course id
    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));

    // Begin transaction
    $conn->begin_transaction();

    try {
        // 1. Delete dependent rows from lecturer_course
        $stmt1 = $conn->prepare("DELETE FROM lecturer_course WHERE course_id IN ($placeholders)");
        if ($stmt1 === false) {
            throw new Exception("Prepare failed for lecturer_course: " . $conn->error);
        }
        $types = str_repeat('i', count($courseIds));
        if (version_compare(PHP_VERSION, '5.6.0') >= 0) {
            $stmt1->bind_param($types, ...$courseIds);
        } else {
            function refValues($arr) {
                $refs = array();
                foreach ($arr as $key => $value) {
                    $refs[$key] = &$arr[$key];
                }
                return $refs;
            }
            $params = array_merge([$types], $courseIds);
            call_user_func_array([$stmt1, 'bind_param'], refValues($params));
        }
        $stmt1->execute();
        $stmt1->close();

        // 2. Delete from course table
        $stmt2 = $conn->prepare("DELETE FROM course WHERE course_id IN ($placeholders)");
        if ($stmt2 === false) {
            throw new Exception("Prepare failed for course: " . $conn->error);
        }
        if (version_compare(PHP_VERSION, '5.6.0') >= 0) {
            $stmt2->bind_param($types, ...$courseIds);
        } else {
            $params = array_merge([$types], $courseIds);
            call_user_func_array([$stmt2, 'bind_param'], refValues($params));
        }
        $stmt2->execute();
        $affectedRows = $stmt2->affected_rows;
        $stmt2->close();

        // Commit transaction
        $conn->commit();

        if ($affectedRows > 0) {
            echo json_encode(['success' => true, 'affectedRows' => $affectedRows]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No rows deleted from course table. Verify that the selected course IDs exist.']);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Deletion failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
}

$conn->close();
?>
