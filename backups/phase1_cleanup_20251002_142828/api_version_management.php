<?php
/**
 * API endpoint for timetable version management
 * Handles version deletion and other version-related operations
 */

include 'connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Invalid request data');
    }
    
    $action = $input['action'];
    
    if ($action === 'delete_version') {
        if (!isset($input['stream_id']) || !isset($input['semester']) || !isset($input['version'])) {
            throw new Exception('Missing required parameters: stream_id, semester, and version');
        }
        
        $stream_id = intval($input['stream_id']);
        $semester = intval($input['semester']);
        $version = trim($input['version']);
        
        // Validate version name
        if (empty($version)) {
            throw new Exception('Version name cannot be empty');
        }
        
        // Check if version exists
        $check_query = "
            SELECT COUNT(*) as count
            FROM timetable t
            JOIN class_courses cc ON t.class_course_id = cc.id
            JOIN classes c ON cc.class_id = c.id
            WHERE c.stream_id = ? AND t.semester = ? AND t.version = ?
        ";
        
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("iis", $stream_id, $semester, $version);
        $stmt->execute();
        $result = $stmt->get_result();
        $count = $result->fetch_assoc()['count'];
        $stmt->close();
        
        if ($count == 0) {
            throw new Exception('Version not found');
        }
        
        // Delete the version
        $delete_query = "
            DELETE t FROM timetable t
            JOIN class_courses cc ON t.class_course_id = cc.id
            JOIN classes c ON cc.class_id = c.id
            WHERE c.stream_id = ? AND t.semester = ? AND t.version = ?
        ";
        
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("iis", $stream_id, $semester, $version);
        
        if ($stmt->execute()) {
            $deleted_count = $stmt->affected_rows;
            $response['success'] = true;
            $response['message'] = "Version '$version' deleted successfully";
            $response['deleted_count'] = $deleted_count;
        } else {
            throw new Exception('Failed to delete version: ' . $stmt->error);
        }
        
        $stmt->close();
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Version management API error: " . $e->getMessage());
}

echo json_encode($response);
?>
