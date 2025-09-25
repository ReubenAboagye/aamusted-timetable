<?php
// Ensure no output before JSON
ob_start();

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Include database connection
include 'connect.php';

// Helper function to resolve room type id from either numeric id or name
function resolveRoomTypeId($conn, $roomType) {
    if (is_numeric($roomType)) return (int)$roomType;
    $stmt = $conn->prepare("SELECT id FROM room_types WHERE name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $roomType);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ? (int)$row['id'] : null;
    }
    return null;
}

// Helper function to send JSON response
function sendResponse($success, $message, $data = null) {
    // Clear any output buffer
    ob_clean();
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Only allow POST requests for AJAX operations
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Only POST requests are allowed');
}

$action = $_POST['action'] ?? null;

if (empty($action)) {
    sendResponse(false, 'No action specified');
}

try {
    switch ($action) {
        case 'add_single':
            $course_id = (int)($_POST['course_id'] ?? 0);
            $room_type = $_POST['room_type'] ?? '';
            $room_type_id = resolveRoomTypeId($conn, $room_type);

            if ($course_id <= 0 || !$room_type_id) {
                sendResponse(false, 'Please select a valid course and room type.');
            }

            // Verify that the room_type_id exists in the database
            $verify_sql = "SELECT COUNT(*) as count FROM room_types WHERE id = ? AND is_active = 1";
            $verify_stmt = $conn->prepare($verify_sql);
            $verify_stmt->bind_param("i", $room_type_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_row = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            if ($verify_row['count'] == 0) {
                sendResponse(false, 'Selected room type does not exist or is inactive.');
            }

            // Check if course already has room type preference
            $check_sql = "SELECT COUNT(*) as count FROM course_room_types WHERE course_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $course_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_row = $check_result->fetch_assoc();
            $check_stmt->close();

            if ($check_row['count'] > 0) {
                sendResponse(false, "This course already has room type preferences set.");
            }

            $sql = "INSERT INTO course_room_types (course_id, room_type_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $course_id, $room_type_id);

            if ($stmt->execute()) {
                $stmt->close();
                
                // Get the newly added record for response
                $new_record_sql = "SELECT crt.course_id, rt.id AS room_type_id, rt.name AS preferred_room_type,
                                  co.`code` AS course_code, co.`name` AS course_name
                                  FROM course_room_types crt
                                  LEFT JOIN room_types rt ON crt.room_type_id = rt.id
                                  LEFT JOIN courses co ON crt.course_id = co.id
                                  WHERE crt.course_id = ?";
                $new_stmt = $conn->prepare($new_record_sql);
                $new_stmt->bind_param("i", $course_id);
                $new_stmt->execute();
                $new_result = $new_stmt->get_result();
                $new_row = $new_result->fetch_assoc();
                $new_stmt->close();
                
                sendResponse(true, 'Course room type preference added successfully!', $new_row);
            } else {
                sendResponse(false, "Error adding course room type preference: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'add_bulk':
            $course_ids = $_POST['course_ids'] ?? [];
            $room_type = $_POST['room_type'] ?? '';
            $room_type_id = resolveRoomTypeId($conn, $room_type);

            if (empty($course_ids) || !$room_type_id) {
                sendResponse(false, "Please select courses and specify a valid room type.");
            }

            // Verify that the room_type_id exists in the database
            $verify_sql = "SELECT COUNT(*) as count FROM room_types WHERE id = ? AND is_active = 1";
            $verify_stmt = $conn->prepare($verify_sql);
            $verify_stmt->bind_param("i", $room_type_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_row = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            if ($verify_row['count'] == 0) {
                sendResponse(false, 'Selected room type does not exist or is inactive.');
            }

            $stmt = $conn->prepare("INSERT IGNORE INTO course_room_types (course_id, room_type_id) VALUES (?, ?)");
            $success_count = 0;
            $skipped_count = 0;

            foreach ($course_ids as $course_id) {
                $cid = (int)$course_id;
                $stmt->bind_param("ii", $cid, $room_type_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success_count++;
                    } else {
                        $skipped_count++;
                    }
                }
            }
            $stmt->close();
            
            $message = "Bulk operation completed! Added to {$success_count} courses";
            if ($skipped_count > 0) {
                $message .= ", {$skipped_count} courses already had preferences";
            }
            $message .= ".";
            
            sendResponse(true, $message, [
                'success_count' => $success_count,
                'skipped_count' => $skipped_count
            ]);
            break;

        case 'delete':
            $course_id = (int)($_POST['course_id'] ?? 0);
            
            if ($course_id <= 0) {
                sendResponse(false, 'Invalid course ID.');
            }

            $sql = "DELETE FROM course_room_types WHERE course_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $course_id);

            if ($stmt->execute()) {
                $stmt->close();
                sendResponse(true, 'Course room type preference deleted successfully!');
            } else {
                sendResponse(false, "Error deleting course room type preference: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'edit':
            $course_id = (int)($_POST['course_id'] ?? 0);
            $room_type = $_POST['room_type'] ?? '';
            $room_type_id = resolveRoomTypeId($conn, $room_type);

            if ($course_id <= 0 || !$room_type_id) {
                sendResponse(false, 'Invalid input for update.');
            }

            // Verify that the room_type_id exists in the database
            $verify_sql = "SELECT COUNT(*) as count FROM room_types WHERE id = ? AND is_active = 1";
            $verify_stmt = $conn->prepare($verify_sql);
            $verify_stmt->bind_param("i", $room_type_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_row = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            if ($verify_row['count'] == 0) {
                sendResponse(false, 'Selected room type does not exist or is inactive.');
            }

            $sql = "UPDATE course_room_types SET room_type_id = ? WHERE course_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $room_type_id, $course_id);

            if ($stmt->execute()) {
                $stmt->close();
                
                // Get the updated record for response
                $updated_record_sql = "SELECT crt.course_id, rt.id AS room_type_id, rt.name AS preferred_room_type,
                                      co.`code` AS course_code, co.`name` AS course_name
                                      FROM course_room_types crt
                                      LEFT JOIN room_types rt ON crt.room_type_id = rt.id
                                      LEFT JOIN courses co ON crt.course_id = co.id
                                      WHERE crt.course_id = ?";
                $updated_stmt = $conn->prepare($updated_record_sql);
                $updated_stmt->bind_param("i", $course_id);
                $updated_stmt->execute();
                $updated_result = $updated_stmt->get_result();
                $updated_row = $updated_result->fetch_assoc();
                $updated_stmt->close();
                
                sendResponse(true, 'Course room type preference updated successfully!', $updated_row);
            } else {
                sendResponse(false, "Error updating course room type preference: " . $stmt->error);
            }
            $stmt->close();
            break;

        case 'get_courses':
            // Get all courses for dropdowns
            $courses_sql = "SELECT id, `code` AS course_code, `name` AS course_name FROM courses WHERE is_active = 1 ORDER BY `code`";
            $courses_result = $conn->query($courses_sql);
            
            $courses = [];
            if ($courses_result) {
                while ($course = $courses_result->fetch_assoc()) {
                    $courses[] = $course;
                }
            }
            
            sendResponse(true, 'Courses retrieved successfully', $courses);
            break;

        case 'get_room_types':
            // Get room types from database
            $room_types = [];
            $rt_res = $conn->query("SELECT id, name FROM room_types WHERE is_active = 1 ORDER BY name");
            if ($rt_res && $rt_res->num_rows > 0) {
                while ($r = $rt_res->fetch_assoc()) {
                    $room_types[] = $r;
                }
            } else {
                // Fallback list if no room types exist in database
                $room_types = [
                    ['id'=>1,'name'=>'Classroom'], 
                    ['id'=>2,'name'=>'Lecture Hall'], 
                    ['id'=>3,'name'=>'Laboratory'], 
                    ['id'=>4,'name'=>'Computer Lab'],
                    ['id'=>5,'name'=>'Seminar Room'],
                    ['id'=>6,'name'=>'Auditorium']
                ];
            }
            
            sendResponse(true, 'Room types retrieved successfully', $room_types);
            break;

        case 'get_table_data':
            // Get existing course room type preferences for table
            $sql = "SELECT crt.course_id, rt.id AS room_type_id, rt.name AS preferred_room_type,
                    co.`code` AS course_code, co.`name` AS course_name
                    FROM course_room_types crt
                    LEFT JOIN room_types rt ON crt.room_type_id = rt.id
                    LEFT JOIN courses co ON crt.course_id = co.id
                    ORDER BY co.`code`";
            $result = $conn->query($sql);
            
            $table_data = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $table_data[] = $row;
                }
            }
            
            sendResponse(true, 'Table data retrieved successfully', $table_data);
            break;

        default:
            sendResponse(false, 'Invalid action specified');
    }

} catch (Exception $e) {
    sendResponse(false, 'An error occurred: ' . $e->getMessage());
} catch (Error $e) {
    sendResponse(false, 'A fatal error occurred: ' . $e->getMessage());
}

// If we reach here, something went wrong
sendResponse(false, 'Unknown error occurred');
?>