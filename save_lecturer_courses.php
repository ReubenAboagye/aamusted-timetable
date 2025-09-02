<?php
header('Content-Type: application/json');
include 'connect.php';

$response = ['success' => false, 'message' => null, 'error' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $lecturer_id = isset($input['lecturer_id']) ? (int)$input['lecturer_id'] : 0;
    $assigned_course_ids = isset($input['assigned_course_ids']) && is_array($input['assigned_course_ids']) 
                          ? array_map('intval', $input['assigned_course_ids']) : [];
    
    if ($lecturer_id <= 0) {
        throw new Exception('Invalid lecturer ID');
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // First, remove all existing assignments for this lecturer
        $delete_stmt = $conn->prepare("DELETE FROM lecturer_courses WHERE lecturer_id = ?");
        if (!$delete_stmt) {
            throw new Exception('Failed to prepare delete statement: ' . $conn->error);
        }
        
        $delete_stmt->bind_param('i', $lecturer_id);
        $delete_stmt->execute();
        $delete_stmt->close();

        // Then, insert new assignments
        if (!empty($assigned_course_ids)) {
            $insert_stmt = $conn->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
            if (!$insert_stmt) {
                throw new Exception('Failed to prepare insert statement: ' . $conn->error);
            }

            foreach ($assigned_course_ids as $course_id) {
                if ($course_id > 0) {
                    $insert_stmt->bind_param('ii', $lecturer_id, $course_id);
                    $insert_stmt->execute();
                }
            }
            $insert_stmt->close();
        }

        // Commit transaction
        $conn->commit();

        $response['success'] = true;
        $response['message'] = 'Course assignments updated successfully';

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
} catch (Error $e) {
    $response['error'] = 'System error: ' . $e->getMessage();
}

echo json_encode($response);
?>
