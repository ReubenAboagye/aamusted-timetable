<?php
header('Content-Type: application/json');
include 'connect.php';
// Stream helpers
if (file_exists(__DIR__ . '/includes/stream_validation.php')) include_once __DIR__ . '/includes/stream_validation.php';
if (file_exists(__DIR__ . '/includes/stream_manager.php')) include_once __DIR__ . '/includes/stream_manager.php';

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

    // Validate current stream
    $sv = function_exists('validateStreamSelection') ? validateStreamSelection($conn, false) : ['stream_id' => 0];
    $current_stream_id = (int)($sv['stream_id'] ?? 0);

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
            // Prepare validators and inserter
            $course_chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM courses WHERE id = ? AND stream_id = ?");
            if (!$course_chk) { throw new Exception('Failed to prepare course stream check: ' . $conn->error); }
            $insert_stmt = $conn->prepare("INSERT INTO lecturer_courses (lecturer_id, course_id) VALUES (?, ?)");
            if (!$insert_stmt) {
                throw new Exception('Failed to prepare insert statement: ' . $conn->error);
            }

            foreach ($assigned_course_ids as $course_id) {
                if ($course_id > 0) {
                    // Enforce stream: course must belong to current stream
                    $course_chk->bind_param('ii', $course_id, $current_stream_id);
                    $course_chk->execute();
                    $chk_res = $course_chk->get_result();
                    $row = $chk_res ? $chk_res->fetch_assoc() : ['cnt' => 0];
                    if ((int)($row['cnt'] ?? 0) === 0) {
                        throw new Exception('Course ' . $course_id . ' does not belong to the current stream');
                    }
                    $insert_stmt->bind_param('ii', $lecturer_id, $course_id);
                    $insert_stmt->execute();
                }
            }
            $insert_stmt->close();
            if (isset($course_chk)) $course_chk->close();
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
