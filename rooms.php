<?php
// Handle AJAX requests FIRST, before any output or includes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_checkbox_states') {
    // Start session for AJAX request
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include database connection
    include 'connect.php';
    
    // Handle the AJAX request
    $room_states = json_decode($_POST['room_states'], true);
    
    if (!$room_states || !is_array($room_states)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid room states data.']);
        exit;
    }
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($room_states as $room_id => $is_active) {
        $id = (int)$room_id;
        $active = (int)$is_active;
        
        $update_sql = "UPDATE rooms SET is_active = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $active, $id);
        
        if ($update_stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
        }
        $update_stmt->close();
    }
    
    if ($success_count > 0) {
        $success_message = "Successfully updated active states for $success_count rooms!";
        if ($error_count > 0) {
            $success_message .= " $error_count rooms failed to update.";
        }
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => $success_message]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No room states were updated. Please check your selection.']);
    }
    exit;
}

session_start();
// Normalize action early to avoid undefined index notices
$action = $_POST['action'] ?? null;
// Detect AJAX requests globally for POST handlers
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Include database connection early
include 'connect.php';
include 'includes/flash.php';

// Handle AJAX requests for existing rooms data FIRST, before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_existing_rooms') {
    // Prevent any output before JSON response
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    try {
        $sql = "SELECT name, building FROM rooms WHERE is_active = 1";
        $result = $conn->query($sql);
        $rooms = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rooms[] = $row;
            }
        } else {
            // Log database error
            error_log("Database error in get_existing_rooms: " . $conn->error);
            $rooms = ['error' => 'Database query failed'];
        }
        
        // Set proper headers and return JSON
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        header('Access-Control-Allow-Origin: *');
        
        echo json_encode($rooms, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        // Log any exceptions
        error_log("Exception in get_existing_rooms: " . $e->getMessage());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Server error occurred'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
// Provide list of buildings for client-side refresh
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_existing_buildings') {
    if (ob_get_level()) ob_end_clean();
    $rows = [];
    $bsql = "SELECT id, name, code FROM buildings WHERE is_active = 1 ORDER BY name";
    $bres = $conn->query($bsql);
    if ($bres) {
        while ($r = $bres->fetch_assoc()) $rows[] = $r;
    }
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}

// Handle form processing BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action) {
    // Building add
    if ($action === 'add_building') {
        // Support both legacy field names and modal names: building_name / building_code
        $nameRaw = $_POST['building_name'] ?? $_POST['name'] ?? '';
        $codeRaw = $_POST['building_code'] ?? $_POST['code'] ?? '';
        $name = trim($conn->real_escape_string($nameRaw));
        $code = trim($conn->real_escape_string($codeRaw));

        // Detect AJAX requests
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        // Ensure logs directory exists for debugging
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);

        // Write incoming request for debugging
        @file_put_contents($logDir . '/building_add.log', "\n---\n" . date('c') . " REQUEST: " . json_encode($_POST) . "\n", FILE_APPEND | LOCK_EX);

        if (empty($name)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Building name is required.']);
                exit;
            }
            $_SESSION['error_message'] = 'Building name is required.';
            header('Location: rooms.php'); exit;
        }

        $sql = "INSERT INTO buildings (name, code) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
                exit;
            }
            error_log("ERROR: Building Add - Prepare failed: " . $conn->error);
            $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
            header('Location: rooms.php'); exit;
        }

        $stmt->bind_param("ss", $name, $code);

        if ($stmt->execute()) {
            $building_id = $stmt->insert_id;
            $stmt->close();
            @file_put_contents($logDir . '/building_add.log', "SUCCESS: inserted id={$building_id}\n", FILE_APPEND | LOCK_EX);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'id' => $building_id, 'name' => $name, 'code' => $code]);
                exit;
            }
            redirect_with_flash('rooms.php', 'success', 'Building added successfully!');
        } else {
            $err = $stmt->error;
            $stmt->close();
            @file_put_contents($logDir . '/building_add.log', "ERROR: " . $err . "\n", FILE_APPEND | LOCK_EX);
            error_log("ERROR: Building Add - Insert failed: " . $err);
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Error adding building: ' . $err]);
                exit;
            }
            $_SESSION['error_message'] = "Error adding building: " . $err;
            header('Location: rooms.php'); exit;
        }
    }
    
    // Import CSV
    if ($action === 'import') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            redirect_with_flash('rooms.php', 'error', 'Invalid import data.');
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) {
            redirect_with_flash('rooms.php', 'error', 'Could not open uploaded file.');
        }
        
        $imported = 0; $skipped = 0; $errors = [];
        $row = 1; // Start at 1 for human-readable row numbers
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row++;
            if (count($data) < 4) { $errors[] = "Row $row: Insufficient columns"; continue; }
            
            $name = trim($data[0]);
            $building_name = trim($data[1]);
            $room_type = trim($data[2]);
            $capacity = (int)$data[3];
            
            if (empty($name) || empty($building_name)) { $errors[] = "Row $row: Missing name or building"; continue; }
            
            // Get or create building
            $bst = $conn->prepare("SELECT id FROM buildings WHERE name = ? LIMIT 1");
            $bst->bind_param('s', $building_name);
            $bst->execute();
            $bres = $bst->get_result();
            
            if ($bres && $bres->num_rows > 0) {
                $building_id = $bres->fetch_assoc()['id'];
            } else {
                $bst->close();
                $bst = $conn->prepare("INSERT INTO buildings (name) VALUES (?)");
                $bst->bind_param('s', $building_name);
                if ($bst->execute()) {
                    $building_id = $conn->insert_id;
                } else {
                    $errors[] = "Row $row: Could not create building '$building_name'"; continue;
                }
            }
            $bst->close();
            
            // Map room type
            $room_type_mappings = [
                'Classroom' => 'classroom',
                'Lecture Hall' => 'lecture_hall',
                'Laboratory' => 'laboratory',
                'Computer Lab' => 'computer_lab',
                'Seminar Room' => 'seminar_room',
                'Auditorium' => 'auditorium'
            ];
            $db_room_type = $room_type_mappings[$room_type] ?? strtolower(str_replace(' ', '_', $room_type));
            
            // Check for duplicates
            $check_sql = "SELECT id FROM rooms WHERE name = ? AND building_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $name, $building_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result && $check_result->num_rows > 0) {
                $skipped++;
                $check_stmt->close();
                continue;
            }
            $check_stmt->close();
            
            // Insert room
            $insert_sql = "INSERT INTO rooms (name, room_type, capacity, building_id, is_active) VALUES (?, ?, ?, ?, 1)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssii", $name, $db_room_type, $capacity, $building_id);
            
            if ($insert_stmt->execute()) {
                $imported++;
            } else {
                $errors[] = "Row $row: Insert failed - " . $insert_stmt->error;
            }
            $insert_stmt->close();
        }
        fclose($handle);
        
        $message = "Imported $imported rooms.";
        if ($skipped > 0) $message .= " Skipped $skipped duplicates.";
        
        if (count($errors) > 10) {
            $short_errors = array_slice($errors, 0, 10);
            $short_errors[] = "... and " . (count($errors) - 10) . " more errors";
            redirect_with_flash('rooms.php', 'error', $message . ' Errors: ' . implode(' | ', $short_errors));
        } else {
            redirect_with_flash('rooms.php', 'success', $message);
        }
        exit;
    }
    
    // Handle multi-add (client sets action to 'add_multiple')
    if ($action === 'add_multiple') {
        // Expect building_id, multi_count, multi_start, room_type, capacity, is_active
        $building_id = isset($_POST['building_id']) ? (int)$_POST['building_id'] : 0;
        $multi_count = isset($_POST['multi_count']) ? (int)$_POST['multi_count'] : 0;
        $multi_start = isset($_POST['multi_start']) ? (int)$_POST['multi_start'] : 1;
        $room_type = trim($conn->real_escape_string($_POST['room_type'] ?? 'Classroom'));
        $capacity = isset($_POST['capacity']) ? (int)$_POST['capacity'] : 30;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($building_id <= 0 || $multi_count <= 0) {
            $_SESSION['error_message'] = 'Invalid building or count for multiple add.';
            header('Location: rooms.php'); exit;
        }

        // Map room type
        $room_type_mappings = [
            'Classroom' => 'classroom',
            'Lecture Hall' => 'lecture_hall',
            'Laboratory' => 'laboratory',
            'Computer Lab' => 'computer_lab',
            'Seminar Room' => 'seminar_room',
            'Auditorium' => 'auditorium'
        ];
        $db_room_type = $room_type_mappings[$room_type] ?? strtolower(str_replace(' ', '_', $room_type));

        // Lookup building code
        $bcode = '';
        $bst = $conn->prepare("SELECT code, name FROM buildings WHERE id = ? LIMIT 1");
        if ($bst) {
            $bst->bind_param('i', $building_id);
            $bst->execute();
            $bres = $bst->get_result();
            if ($bres && $bres->num_rows > 0) {
                $brow = $bres->fetch_assoc();
                $bcode = trim($brow['code'] ?? '') ?: preg_replace('/\s+/', '', strtoupper($brow['name']));
            }
            $bst->close();
        }

        if ($bcode === '') $bcode = 'B' . $building_id;

        // Prepare statements
        $insert_sql = "INSERT INTO rooms (name, room_type, capacity, building_id, is_active) VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $check_sql = "SELECT id FROM rooms WHERE name = ? AND building_id = ? LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);

        $imported = 0; $skipped = 0; $errors = [];
        for ($i = 0; $i < $multi_count; $i++) {
            $num = $multi_start + $i;
            // Insert a space between building code and room number (e.g., "MAIN 101")
            $room_name = $bcode . ' ' . $num;

            // Duplicate check
            if ($check_stmt) {
                $check_stmt->bind_param('si', $room_name, $building_id);
                $check_stmt->execute();
                $cres = $check_stmt->get_result();
                if ($cres && $cres->num_rows > 0) { $skipped++; continue; }
            }

            if ($insert_stmt) {
                $insert_stmt->bind_param('ssiii', $room_name, $db_room_type, $capacity, $building_id, $is_active);
                if ($insert_stmt->execute()) { $imported++; } else { $errors[] = 'Insert failed for ' . $room_name . ': ' . $insert_stmt->error; }
            } else {
                $errors[] = 'Insert statement prepare failed';
            }
        }
        if ($insert_stmt) $insert_stmt->close();
        if ($check_stmt) $check_stmt->close();

        $msg = "Added {$imported} rooms."; if ($skipped) $msg .= " {$skipped} skipped.";
        if (!empty($errors)) { $_SESSION['error_message'] = $msg . ' Errors: ' . implode(' | ', array_slice($errors,0,10)); }
        else { $_SESSION['success_message'] = $msg; }
        header('Location: rooms.php'); exit;
    }
    
    // Single add
    if ($action === 'add') {
        $name = trim($conn->real_escape_string($_POST['name']));
        $building_id = (int)$_POST['building_id'];
        $room_type = trim($conn->real_escape_string($_POST['room_type']));
        $valid_form_room_types = ['Classroom', 'Lecture Hall', 'Laboratory', 'Computer Lab', 'Seminar Room', 'Auditorium'];

        if (!in_array($room_type, $valid_form_room_types)) {
            $_SESSION['error_message'] = "Invalid room type selected. Please choose a valid room type from the dropdown.";
            header('Location: rooms.php'); exit;
        } else {
            $capacity = (int)$_POST['capacity'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Convert room type to database format (hardcoded mapping)
            $room_type_mappings = [
                'Classroom' => 'classroom',
                'Lecture Hall' => 'lecture_hall',
                'Laboratory' => 'laboratory',
                'Computer Lab' => 'computer_lab',
                'Seminar Room' => 'seminar_room',
                'Auditorium' => 'auditorium'
            ];
            $db_room_type = $room_type_mappings[$room_type] ?? 'classroom';
            
            // Application-level validation (hardcoded)
            $valid_room_types = ['classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium'];
            if (!in_array($db_room_type, $valid_room_types)) {
                $_SESSION['error_message'] = "Invalid room type selected. Please try again.";
                header('Location: rooms.php'); exit;
            } else {
                // Check for duplicates using building_id (current schema)
                $check_sql = "SELECT id FROM rooms WHERE name = ? AND building_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("si", $name, $building_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result && $check_result->num_rows > 0) {
                    $_SESSION['error_message'] = "Room with this name already exists in the selected building.";
                    $check_stmt->close();
                    header('Location: rooms.php'); exit;
                } else {
                    $check_stmt->close();
                    
                    $sql = "INSERT INTO rooms (name, room_type, capacity, building_id, is_active) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssiii", $name, $db_room_type, $capacity, $building_id, $is_active);

                        if ($stmt->execute()) {
                            $stmt->close();
                            redirect_with_flash('rooms.php', 'success', 'Room added successfully!');
                        } else {
                            error_log("ERROR: Single Add - Insert failed: " . $stmt->error);
                            $_SESSION['error_message'] = "Error adding room: " . $stmt->error;
                            header('Location: rooms.php'); exit;
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
                        header('Location: rooms.php'); exit;
                    }
                }
            }
        }
    }

    // Edit
    if ($action === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = trim($conn->real_escape_string($_POST['name']));
        $building_id = (int)$_POST['building_id'];
        $room_type = trim($conn->real_escape_string($_POST['room_type']));
        $valid_form_room_types = ['Classroom', 'Lecture Hall', 'Laboratory', 'Computer Lab', 'Seminar Room', 'Auditorium'];

        if (!in_array($room_type, $valid_form_room_types)) {
            $_SESSION['error_message'] = "Invalid room type selected. Please choose a valid room type from the dropdown.";
            header('Location: rooms.php'); exit;
        } else {
            $capacity = (int)$_POST['capacity'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Convert room type to database format (hardcoded mapping)
            $room_type_mappings = [
                'Classroom' => 'classroom',
                'Lecture Hall' => 'lecture_hall',
                'Laboratory' => 'laboratory',
                'Computer Lab' => 'computer_lab',
                'Seminar Room' => 'seminar_room',
                'Auditorium' => 'auditorium'
            ];
            $db_room_type = $room_type_mappings[$room_type] ?? 'classroom';
            
            // Application-level validation (hardcoded)
            $valid_room_types = ['classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium'];
            if (!in_array($db_room_type, $valid_room_types)) {
                $_SESSION['error_message'] = "Invalid room type selected. Please try again.";
                header('Location: rooms.php'); exit;
            } else {
                // Check for duplicates using building_id (current schema)
                $check_sql = "SELECT id FROM rooms WHERE name = ? AND building_id = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("sii", $name, $building_id, $id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result && $check_result->num_rows > 0) {
                    $_SESSION['error_message'] = "Room with this name already exists in the selected building.";
                    $check_stmt->close();
                    header('Location: rooms.php'); exit;
                } else {
                    $check_stmt->close();
                    
                    $sql = "UPDATE rooms SET name = ?, room_type = ?, capacity = ?, building_id = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssiiii", $name, $db_room_type, $capacity, $building_id, $is_active, $id);

                        if ($stmt->execute()) {
                            $stmt->close();
                            redirect_with_flash('rooms.php', 'success', 'Room updated successfully!');
                        } else {
                            error_log("ERROR: Edit - Update failed: " . $stmt->error);
                            $_SESSION['error_message'] = "Error updating room: " . $stmt->error;
                            header('Location: rooms.php'); exit;
                        }
                        $stmt->close();
                    } else {
                        $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
                        header('Location: rooms.php'); exit;
                    }
                }
            }
        }
    }
    
    // Delete (soft delete: set is_active = 0)
    if ($action === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE rooms SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_flash('rooms.php', 'success', 'Room deleted.');
        } else {
            error_log("ERROR: Delete - Update failed: " . $stmt->error);
            $_SESSION['error_message'] = "Error deleting room: " . $conn->error;
            header('Location: rooms.php'); exit;
        }
        $stmt->close();
    }
    
    // Toggle Status (toggle is_active for multiple rooms)
    if ($action === 'toggle_status' && isset($_POST['room_ids'])) {
        $room_ids = json_decode($_POST['room_ids'], true);
        if (!$room_ids || !is_array($room_ids)) {
            $_SESSION['error_message'] = "Invalid room selection.";
            header('Location: rooms.php'); exit;
        } else {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($room_ids as $room_id) {
                $id = (int)$room_id;
                
                // First get current status
                $check_sql = "SELECT is_active FROM rooms WHERE id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result && $check_result->num_rows > 0) {
                    $current_status = $check_result->fetch_assoc()['is_active'];
                    $new_status = $current_status ? 0 : 1; // Toggle
                    
                    $check_stmt->close();
                    
                    // Update with new status
                    $update_sql = "UPDATE rooms SET is_active = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("ii", $new_status, $id);
                    
                    if ($update_stmt->execute()) {
                        $success_count++;
                    } else {
                        error_log("ERROR: Toggle Status - Update failed for room $id: " . $update_stmt->error);
                        $error_count++;
                    }
                    $update_stmt->close();
                } else {
                    $error_count++;
                }
            }
            
            if ($success_count > 0) {
                $success_message = "Successfully toggled status for $success_count rooms!";
                if ($error_count > 0) {
                    $success_message .= " $error_count rooms failed to update.";
                }
                $_SESSION['success_message'] = $success_message;
            } else {
                $_SESSION['error_message'] = "No rooms were updated. Please check your selection.";
            }
            header('Location: rooms.php'); exit;
        }
    }
}

// Collect PHP warnings/notices so we can show them in the custom error card
$php_errors = [];
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$php_errors) {
    // Only capture warnings and notices (you can adjust levels as needed)
    if (in_array($errno, [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE])) {
        $php_errors[] = ['errno' => $errno, 'errstr' => $errstr, 'errfile' => $errfile, 'errline' => $errline];
        return true; // prevent PHP internal handler from also outputting
    }
    return false;
});

$pageTitle = 'Rooms Management';
include 'includes/header.php';
include 'includes/sidebar.php';

/**
 * HARDCODED ROOM TYPES (Application-level validation):
 * - classroom
 * - lecture_hall  
 * - laboratory
 * - computer_lab
 * - seminar_room
 * - auditorium
 * 
 * REMINDER: room_type is stored as VARCHAR in database, validation is enforced at application level
 */

// Database connection already included above

// Test room type validation (for debugging)
if (isset($_GET['test_room_type'])) {
    $test_type = $_GET['test_room_type'];
    $valid_types = ['classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium'];
    echo "Testing room type: '$test_type'<br>";
    echo "Valid types: " . implode(', ', $valid_types) . "<br>";
    echo "Is valid: " . (in_array($test_type, $valid_types) ? 'YES' : 'NO') . "<br>";
    echo "Length: " . strlen($test_type) . "<br>";
    exit;
}

// Fetch rooms with all fields from current schema, joining with buildings table
$sql = "SELECT r.id, r.name, b.name as building_name, r.room_type, r.capacity, r.is_active, r.created_at, r.updated_at, r.building_id 
        FROM rooms r 
        LEFT JOIN buildings b ON r.building_id = b.id 
        ORDER BY b.name, r.name";
$result = $conn->query($sql);

// Fetch buildings for dropdown
 $buildings_sql = "SELECT id, name, code FROM buildings WHERE is_active = 1 ORDER BY name";
 $buildings_result = $conn->query($buildings_sql);
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-door-open me-2"></i>Rooms Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                    <i class="fas fa-plus me-2"></i>Add New Room
                </button>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-file-import me-2"></i>Import CSV
                </button>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message) || isset($_SESSION['error_message']) || (!empty($php_errors))): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php 
                // Primary error messages
                if (isset($error_message)) {
                    echo htmlspecialchars($error_message);
                } elseif (isset($_SESSION['error_message'])) {
                    echo htmlspecialchars($_SESSION['error_message']);
                    unset($_SESSION['error_message']); // Clear after displaying
                }

                // Append any captured PHP warnings/notices
                if (!empty($php_errors)) {
                    echo '<hr style="margin:8px 0;">';
                    echo '<strong>System notices:</strong><br />';
                    foreach ($php_errors as $pe) {
                        $msg = htmlspecialchars($pe['errstr']);
                        $file = htmlspecialchars($pe['errfile']);
                        $line = (int)$pe['errline'];
                        echo "<div style=\"font-family:monospace; font-size:0.95em; margin-top:4px;\">" . $msg . " <small class=\"text-muted\">(" . $file . ":" . $line . ")</small></div>";
                    }
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="search-container m-3">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="search-input form-control" placeholder="Search rooms...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="statusFilter">
                        <option value="all">All Rooms</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary" id="toggleStatusBtn" style="display: none;">
                        <i class="fas fa-toggle-on me-2"></i>Toggle Status
                    </button>
                    <button class="btn btn-outline-primary" id="saveCheckboxBtn">
                        <i class="fas fa-save me-2"></i>Save Active States
                    </button>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table" id="roomsTable">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" id="selectAllRooms" class="form-check-input">
                            <br><small class="text-muted">Active</small>
                        </th>
                        <th>Room Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Building</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input room-checkbox" value="<?php echo $row['id']; ?>" <?php echo $row['is_active'] ? 'checked' : ''; ?>>
                                </td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td>
                                    <?php 
                                    $type_badge = 'bg-secondary';
                                    $display_type = ucwords(str_replace('_', ' ', $row['room_type']));
                                    switch($row['room_type']) {
                                        case 'lecture_hall': $type_badge = 'bg-primary'; break;
                                        case 'laboratory': $type_badge = 'bg-warning'; break;
                                        case 'computer_lab': $type_badge = 'bg-info'; break;
                                        case 'seminar_room': $type_badge = 'bg-success'; break;
                                        case 'auditorium': $type_badge = 'bg-danger'; break;
                                        case 'classroom': $type_badge = 'bg-secondary'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $type_badge; ?>"><?php echo htmlspecialchars($display_type); ?></span>
                                </td>
                                <td><span class="badge bg-dark"><?php echo htmlspecialchars($row['capacity']); ?> students</span></td>
                                <td><?php echo htmlspecialchars($row['building_name']); ?></td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editRoom(<?php echo (int)$row['id']; ?>, '<?php echo addslashes($row['name']); ?>', <?php echo (int)$row['building_id']; ?>, '<?php echo addslashes(ucwords(str_replace('_', ' ', $row['room_type']))); ?>', <?php echo (int)$row['capacity']; ?>, <?php echo (int)$row['is_active']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this room?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <i class="fas fa-door-open"></i>
                                <p>No rooms found. Add your first room to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Room Name/Number *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="building_id" class="form-label">Building *</label>
                                <select class="form-select" id="building_id" name="building_id" required>
                                    <option value="">Select Building</option>
                                    <?php 
                                    if ($buildings_result && $buildings_result->num_rows > 0) {
                                        mysqli_data_seek($buildings_result, 0); // Reset pointer
                                        while ($building = $buildings_result->fetch_assoc()) {
                                            // Keep building code available via data attribute for client-side generation
                                            $bname_esc = htmlspecialchars($building['name']);
                                            $bcode_esc = htmlspecialchars($building['code'] ?? '');
                                            echo '<option value="' . $building['id'] . '" data-code="' . $bcode_esc . '">' . $bname_esc . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">
                                    <a href="#" class="text-decoration-none open-add-building">
                                        <i class="fas fa-plus-circle me-1"></i>Add New Building
                                    </a>
                                    &nbsp;|&nbsp;
                                    <a href="#" class="text-decoration-none" id="addMultipleLink">
                                        <i class="fas fa-layer-group me-1"></i>Add Multiple
                                    </a>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div id="multipleOptions" style="display:none;" class="mb-3">
                        <label class="form-label">Add multiple rooms</label>
                        <div class="row">
                            <div class="col-md-4">
                                <input type="number" class="form-control" id="multi_start" name="multi_start" min="1" value="1" placeholder="Start number">
                            </div>
                            <div class="col-md-4">
                                <input type="number" class="form-control" id="multi_count" name="multi_count" min="1" value="5" placeholder="Count">
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="applyGenerate">Generate Names</button>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="room_type" class="form-label">Room Type *</label>
                                <select class="form-select" id="room_type" name="room_type" required>
                                    <option value="">Select Type *</option>
                                    <option value="Classroom">Classroom</option>
                                    <option value="Lecture Hall">Lecture Hall</option>
                                    <option value="Laboratory">Laboratory</option>
                                    <option value="Computer Lab">Computer Lab</option>
                                    <option value="Seminar Room">Seminar Room</option>
                                    <option value="Auditorium">Auditorium</option>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a valid room type.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="capacity" class="form-label">Capacity *</label>
                                <input type="number" class="form-control" id="capacity" name="capacity" min="1" max="500" required>
                            </div>
                        </div>
                    </div>
                    
                    

                    

                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Room Available
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Rooms (CSV)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="uploadArea" class="p-4 border rounded text-center" style="cursor:pointer;">
                    <p>Click or drag a CSV file here to upload</p>
                    <input type="file" id="csvFile" accept=".csv" style="display:none">
                </div>

                <div class="mt-3">
                    <table class="table" id="importPreviewTable">
                        <thead><tr><th>#</th><th>Name</th><th>Type</th><th>Capacity</th><th>Building</th><th>Status</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="processBtn">Import Selected</button>
            </div>
        </div>
    </div>
</div>
<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Room Name/Number *</label>
                                <input type="text" class="form-control" id="edit_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_building_id" class="form-label">Building *</label>
                                <select class="form-select" id="edit_building_id" name="building_id" required>
                                    <option value="">Select Building</option>
                                    <?php 
                                    if ($buildings_result && $buildings_result->num_rows > 0) {
                                        mysqli_data_seek($buildings_result, 0); // Reset pointer
                                        mysqli_data_seek($buildings_result, 0);
                                        while ($building = $buildings_result->fetch_assoc()) {
                                            // Keep building code available via data attribute for client-side generation
                                            $bname_esc = htmlspecialchars($building['name']);
                                            $bcode_esc = htmlspecialchars($building['code'] ?? '');
                                            echo '<option value="' . $building['id'] . '" data-code="' . $bcode_esc . '">' . $bname_esc . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">
                                    <a href="#" class="text-decoration-none open-add-building">
                                        <i class="fas fa-plus-circle me-1"></i>Add New Building
                                    </a>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_room_type" class="form-label">Room Type *</label>
                                <select class="form-select" id="edit_room_type" name="room_type" required>
                                    <option value="">Select Type *</option>
                                    <option value="Classroom">Classroom</option>
                                    <option value="Lecture Hall">Lecture Hall</option>
                                    <option value="Laboratory">Laboratory</option>
                                    <option value="Computer Lab">Computer Lab</option>
                                    <option value="Seminar Room">Seminar Room</option>
                                    <option value="Auditorium">Auditorium</option>
                                </select>
                                <div class="invalid-feedback">
                                    Please select a valid room type.
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_capacity" class="form-label">Capacity *</label>
                                <input type="number" class="form-control" id="edit_capacity" name="capacity" min="1" max="500" required>
                            </div>
                        </div>
                    </div>
                    

                    

                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">
                                Room Available
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Room</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- Bulk Edit Modal -->
<div class="modal fade" id="bulkEditModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Edit Rooms</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="bulkEditForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_edit">
                    <input type="hidden" name="room_ids" id="bulkEditRoomIds">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong id="bulkEditCount">0</strong> rooms selected for editing.
                        <br><small>Only checked fields will be updated. Leave unchecked to keep existing values.</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Room Type</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bulk_edit_room_type_check">
                                    <label class="form-check-label" for="bulk_edit_room_type_check">Update room type</label>
                                </div>
                                <select class="form-select mt-2" id="bulk_edit_room_type" name="room_type" disabled>
                                    <option value="">Select Type</option>
                                    <option value="Classroom">Classroom</option>
                                    <option value="Lecture Hall">Lecture Hall</option>
                                    <option value="Laboratory">Laboratory</option>
                                    <option value="Computer Lab">Computer Lab</option>
                                    <option value="Seminar Room">Seminar Room</option>
                                    <option value="Auditorium">Auditorium</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Capacity</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="bulk_edit_capacity_check">
                                    <label class="form-check-label" for="bulk_edit_capacity_check">Update capacity</label>
                                </div>
                                <input type="number" class="form-control mt-2" id="bulk_edit_capacity" name="capacity" min="1" max="500" disabled>
                            </div>
                        </div>
                    </div>
                    
                    

                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="bulk_edit_status_check">
                            <label class="form-check-label" for="bulk_edit_status_check">Update status</label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="bulk_edit_is_active" name="is_active" disabled>
                            <label class="form-check-label" for="bulk_edit_is_active">
                                Room Available
                            </label>
                                </div>
                            </div>
                                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Selected Rooms</button>
                            </div>
            </form>
                                </div>
                            </div>
                        </div>

<!-- Add Building Modal -->
<div class="modal fade" id="addBuildingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Building</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_building">
                    
                    <div class="mb-3">
                        <label for="building_name" class="form-label">Building Name *</label>
                        <input type="text" class="form-control" id="building_name" name="building_name" required>
                        </div>
                    
                    <div class="mb-3">
                        <label for="building_code" class="form-label">Building Code *</label>
                        <input type="text" class="form-control" id="building_code" name="building_code" required>
                        <small class="text-muted">Short code for the building (e.g., "MAIN", "SCI", "ENG")</small>
                                </div>
                    
                    <div class="mb-3">
                        <label for="building_description" class="form-label">Description</label>
                        <textarea class="form-control" id="building_description" name="building_description" rows="2"></textarea>
                            </div>
                                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Building</button>
                            </div>
            </form>
                                </div>
                            </div>
                                </div>





<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
// Debug: Check if Bootstrap is loading
console.log('Rooms page loading...');
console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
if (typeof bootstrap !== 'undefined') {
    console.log('Bootstrap Modal available:', typeof bootstrap.Modal !== 'undefined');
}

// Protect Bootstrap Modal from external interference
(function() {
    // Store original Bootstrap Modal constructor
    let OriginalModal = null;
    
    // Wait for Bootstrap to load, then protect it
    function protectBootstrapModal() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            OriginalModal = bootstrap.Modal;
            console.log('Bootstrap Modal protected from external interference');
        } else {
            setTimeout(protectBootstrapModal, 100);
        }
    }
    
    protectBootstrapModal();
})();

// Ensure Bootstrap is available before using modal functions
function ensureBootstrapLoaded() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        console.error('Bootstrap Modal not available');
        return false;
    }
    return true;
}

// Safe modal initialization that prevents external widget conflicts
function safeModalInit(element, options = {}) {
    try {
        if (!element) {
            console.error('Modal element not found');
            return null;
        }
        
        if (!ensureBootstrapLoaded()) {
            console.error('Bootstrap not loaded, cannot initialize modal');
            return null;
        }
        
        // Destroy any existing modal instance first to prevent conflicts
        try {
            const existingInstance = bootstrap.Modal.getInstance(element);
            if (existingInstance) {
                existingInstance.dispose();
            }
        } catch (e) {
            console.warn('Could not dispose existing modal instance:', e);
        }
        
        // Create modal with explicit options to prevent backdrop conflicts
        const modalOptions = {
            backdrop: true,
            keyboard: true,
            focus: true,
            ...options
        };
        
        // Try to create modal instance with error handling for backdrop issues
        let modal = null;
        try {
            modal = new bootstrap.Modal(element, modalOptions);
        } catch (backdropError) {
            console.warn('Backdrop error detected, trying alternative initialization:', backdropError);
            // Try without backdrop option as fallback
            try {
                modal = new bootstrap.Modal(element, { keyboard: true, focus: true });
            } catch (fallbackError) {
                console.error('Fallback modal initialization also failed:', fallbackError);
                return null;
            }
        }
        
        return modal;
    } catch (error) {
        console.error('Error initializing modal:', error);
        return null;
    }
}

// Override Bootstrap's automatic modal initialization to prevent conflicts
function initializeModalsManually() {
    // Disable automatic modal initialization by removing data-bs-toggle attributes temporarily
    const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
    modalTriggers.forEach(trigger => {
        const target = trigger.getAttribute('data-bs-target');
        trigger.removeAttribute('data-bs-toggle');
        trigger.removeAttribute('data-bs-target');
        
        // Add click handler instead
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalElement = document.querySelector(target);
            if (modalElement) {
                const modal = safeModalInit(modalElement);
                if (modal) {
                    modal.show();
                }
            }
        });
    });
}

// Wait for Bootstrap to be available (with timeout)
function waitForBootstrap(callback, maxWait = 5000) {
    const startTime = Date.now();

    function checkBootstrap() {
        if (ensureBootstrapLoaded()) {
            callback();
        } else if (Date.now() - startTime < maxWait) {
            setTimeout(checkBootstrap, 50);
        } else {
            console.error('Bootstrap failed to load within timeout');
            // Try to proceed anyway, but with error handling
            try {
                callback();
            } catch (error) {
                console.error('Error executing callback after Bootstrap timeout:', error);
            }
        }
    }

    checkBootstrap();
}

function editRoom(id, name, buildingId, roomType, capacity, isActive) {
    if (!ensureBootstrapLoaded()) return;

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_building_id').value = buildingId;
    document.getElementById('edit_capacity').value = capacity;

    // Set room type (convert database room_type to form value)
    const editRoomType = document.getElementById('edit_room_type');
    const roomTypeMappings = {
        'classroom': 'Classroom',
        'lecture_hall': 'Lecture Hall',
        'laboratory': 'Laboratory',
        'computer_lab': 'Computer Lab',
        'seminar_room': 'Seminar Room',
        'auditorium': 'Auditorium'
    };
    editRoomType.value = roomTypeMappings[roomType] || roomType || 'Classroom';



    // Set is_active checkbox
    document.getElementById('edit_is_active').checked = !!isActive;

    var el = document.getElementById('editRoomModal');
    if (!el) return console.error('editRoomModal element missing');

    try {
        var editModal = safeModalInit(el);
        if (editModal) {
            editModal.show();
        }
    } catch (error) {
        console.error('Error showing edit modal:', error);
    }
}








// Unified initialization block removed duplicate/conflict content; initialization handled below

// Add search and filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.search-input');
    const statusFilter = document.getElementById('statusFilter');
    const toggleStatusBtn = document.getElementById('toggleStatusBtn');
    
    // Search functionality
    searchInput?.addEventListener('input', function() {
        filterRooms();
    });
    
    // Status filter functionality
    statusFilter?.addEventListener('change', function() {
        filterRooms();
    });
    
    // Toggle status functionality
    toggleStatusBtn?.addEventListener('click', function() {
        const selectedRows = document.querySelectorAll('tbody tr:not([style*="display: none"]) input[type="checkbox"]:checked');
        if (selectedRows.length === 0) {
            alert('Please select at least one room to toggle status.');
            return;
        }
        
        if (confirm(`Are you sure you want to toggle the status of ${selectedRows.length} selected room(s)?`)) {
            const roomIds = Array.from(selectedRows).map(cb => cb.value);
            toggleRoomStatus(roomIds);
        }
    });
    
    // Save checkbox states functionality
    const saveCheckboxBtn = document.getElementById('saveCheckboxBtn');
    saveCheckboxBtn?.addEventListener('click', function() {
        const roomCheckboxes = document.querySelectorAll('.room-checkbox');
        const roomStates = {};
        
        roomCheckboxes.forEach(checkbox => {
            roomStates[checkbox.value] = checkbox.checked ? 1 : 0;
        });
        
        if (confirm('Are you sure you want to save the current room active states?')) {
            saveCheckboxStates(roomStates);
        }
    });
    
    function filterRooms() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusFilterValue = statusFilter.value;
        const table = document.getElementById('roomsTable');
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const statusCell = row.querySelector('td:nth-child(6)'); // Updated column index
            const isActive = statusCell && statusCell.textContent.includes('Active');
            
            let showBySearch = text.includes(searchTerm);
            let showByStatus = true;
            
            if (statusFilterValue === 'active') {
                showByStatus = isActive;
            } else if (statusFilterValue === 'inactive') {
                showByStatus = !isActive;
            }
            
            row.style.display = (showBySearch && showByStatus) ? '' : 'none';
        });
        
        // Show/hide toggle button based on filter
        updateToggleButton();
    }
    
    function updateToggleButton() {
        const visibleRows = document.querySelectorAll('tbody tr:not([style*="display: none"])');
        const hasVisibleRows = visibleRows.length > 0;
        toggleStatusBtn.style.display = hasVisibleRows ? 'inline-block' : 'none';
    }
    
    // Select all functionality
    const selectAllCheckbox = document.getElementById('selectAllRooms');
    const roomCheckboxes = document.querySelectorAll('.room-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            roomCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            // Auto-save after select all change with debouncing
            clearTimeout(window.checkboxSaveTimeout);
            window.checkboxSaveTimeout = setTimeout(() => {
                const roomStates = {};
                roomCheckboxes.forEach(checkbox => {
                    roomStates[checkbox.value] = checkbox.checked ? 1 : 0;
                });
                saveCheckboxStates(roomStates);
            }, 500); // Increased delay to 500ms for better debouncing
        });
    }
    
    // Individual checkbox functionality
    roomCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllCheckbox();
            // Auto-save after individual checkbox change with debouncing
            clearTimeout(window.checkboxSaveTimeout);
            window.checkboxSaveTimeout = setTimeout(() => {
                const roomStates = {};
                roomCheckboxes.forEach(cb => {
                    roomStates[cb.value] = cb.checked ? 1 : 0;
                });
                saveCheckboxStates(roomStates);
            }, 500); // Increased delay to 500ms for better debouncing
        });
    });
    
    function updateSelectAllCheckbox() {
        const checkedCount = document.querySelectorAll('.room-checkbox:checked').length;
        const totalCount = roomCheckboxes.length;
        
        if (checkedCount === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (checkedCount === totalCount) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
        }
    }
    
    // Initialize select all checkbox state on page load
    if (selectAllCheckbox && roomCheckboxes.length > 0) {
        updateSelectAllCheckbox();
    }
    
    function toggleRoomStatus(roomIds) {
        // Create a form to submit the toggle request
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'toggle_status';
        
        const idsInput = document.createElement('input');
        idsInput.type = 'hidden';
        idsInput.name = 'room_ids';
        idsInput.value = JSON.stringify(roomIds);
        
        form.appendChild(actionInput);
        form.appendChild(idsInput);
        document.body.appendChild(form);
        form.submit();
    }
    
    function saveCheckboxStates(roomStates) {
        // Show saving indicator
        const saveBtn = document.getElementById('saveCheckboxBtn');
        if (saveBtn) {
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            saveBtn.disabled = true;
            
            // Restore button after save
            setTimeout(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }, 1000);
        }
        
        // Use AJAX to save checkbox states without page refresh
        const formData = new FormData();
        formData.append('action', 'save_checkbox_states');
        formData.append('room_states', JSON.stringify(roomStates));
        
        fetch('rooms.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Room active states saved successfully:', data.message);
                showSaveIndicator('success');
            } else {
                console.error('Error saving room active states:', data.message);
                showSaveIndicator('error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showSaveIndicator('error');
        });
    }
    
    function showSaveIndicator(type) {
        // Create or update save indicator
        let indicator = document.getElementById('saveIndicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'saveIndicator';
            indicator.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; padding: 10px 15px; border-radius: 5px; font-weight: bold;';
            document.body.appendChild(indicator);
        }
        
        if (type === 'success') {
            indicator.style.backgroundColor = '#28a745';
            indicator.style.color = 'white';
            indicator.textContent = '✓ Saved';
        } else {
            indicator.style.backgroundColor = '#dc3545';
            indicator.style.color = 'white';
            indicator.textContent = '✗ Error';
        }
        
        // Hide indicator after 2 seconds
        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.remove();
            }
        }, 2000);
    }

    // Attach delegated handler for add-building links (works even if functions load later)
    document.body.addEventListener('click', function(e) {
        const target = e.target.closest && e.target.closest('.open-add-building');
        if (target) {
            e.preventDefault();
            // If function exists, call it; otherwise wait briefly and retry
            if (typeof showAddBuildingModal === 'function') {
                showAddBuildingModal();
            } else {
                // Wait a short time for scripts to initialize
                setTimeout(function() {
                    if (typeof showAddBuildingModal === 'function') {
                        showAddBuildingModal();
                    } else {
                        console.error('showAddBuildingModal not available');
                        // Fallback: open the modal element directly if present
                        const el = document.getElementById('addBuildingModal');
                        if (el) {
                            el.classList.add('show');
                            el.style.display = 'block';
                            document.body.classList.add('modal-open');
                            if (!document.querySelector('.modal-backdrop')) {
                                const backdrop = document.createElement('div');
                                backdrop.className = 'modal-backdrop fade show';
                                document.body.appendChild(backdrop);
                            }
                        }
                    }
                }, 50);
            }
        }
    });
});

// Enhanced form validation for room type
document.addEventListener('DOMContentLoaded', function() {
    waitForBootstrap(function() {
    const addRoomForm = document.querySelector('#addRoomModal form');
    if (addRoomForm) {
        addRoomForm.addEventListener('submit', function(e) {
            const roomTypeSelect = document.getElementById('room_type');
            if (!roomTypeSelect.value) {
                e.preventDefault();
                alert('Please select a valid room type.');
                roomTypeSelect.focus();
                return false;
            }
            
            const validRoomTypes = ['Classroom', 'Lecture Hall', 'Laboratory', 'Computer Lab', 'Seminar Room', 'Auditorium'];
            if (!validRoomTypes.includes(roomTypeSelect.value)) {
                e.preventDefault();
                alert('Invalid room type selected. Please choose a valid option from the dropdown.');
                roomTypeSelect.focus();
                return false;
            }
        });
    }
    
    const editRoomForm = document.querySelector('#editRoomModal form');
    if (editRoomForm) {
        editRoomForm.addEventListener('submit', function(e) {
            const roomTypeSelect = document.getElementById('edit_room_type');
            if (!roomTypeSelect.value) {
                e.preventDefault();
                alert('Please select a valid room type.');
                roomTypeSelect.focus();
                return false;
            }
            
            const validRoomTypes = ['Classroom', 'Lecture Hall', 'Laboratory', 'Computer Lab', 'Seminar Room', 'Auditorium'];
            if (!validRoomTypes.includes(roomTypeSelect.value)) {
                e.preventDefault();
                alert('Invalid room type selected. Please choose a valid option from the dropdown.');
                roomTypeSelect.focus();
                return false;
            }
        });
    }
    
    // Bulk Edit functionality
    setupBulkEdit();
});
});

// Bulk Edit Functions
function setupBulkEdit() {
    const selectAllCheckbox = document.getElementById('selectAllRooms');
    const roomCheckboxes = document.querySelectorAll('.room-checkbox');
    const bulkEditBtn = document.getElementById('bulkEditBtn');
    const selectedCountSpan = document.getElementById('selectedCount');
    
    // Select all functionality
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            roomCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateBulkEditButton();
        });
    }
    
    // Individual checkbox functionality
    roomCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkEditButton();
            updateSelectAllCheckbox();
        });
    });
    
    // Update bulk edit button state
    function updateBulkEditButton() {
        const selectedCount = document.querySelectorAll('.room-checkbox:checked').length;
        selectedCountSpan.textContent = selectedCount;
        
        if (selectedCount > 0) {
            bulkEditBtn.disabled = false;
        } else {
            bulkEditBtn.disabled = true;
        }
    }
    

    
    // Setup bulk edit modal functionality
    setupBulkEditModal();
}

function setupBulkEditModal() {
    const bulkEditForm = document.getElementById('bulkEditForm');
    const bulkEditModal = document.getElementById('bulkEditModal');
    
    // Handle form submission
    if (bulkEditForm) {
        bulkEditForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selectedRooms = document.querySelectorAll('.room-checkbox:checked');
            if (selectedRooms.length === 0) {
                alert('Please select at least one room to edit.');
                return;
            }
            
            const roomIds = Array.from(selectedRooms).map(cb => cb.value);
            document.getElementById('bulkEditRoomIds').value = JSON.stringify(roomIds);
            
            // Submit the form
            this.submit();
        });
    }
    
    // Setup checkbox controls for enabling/disabling fields
    const checkboxes = [
        { id: 'bulk_edit_room_type_check', target: 'bulk_edit_room_type' },
        { id: 'bulk_edit_capacity_check', target: 'bulk_edit_capacity' },


        { id: 'bulk_edit_status_check', target: 'bulk_edit_is_active' }
    ];
    
    checkboxes.forEach(item => {
        const checkbox = document.getElementById(item.id);
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                const isChecked = this.checked;
                
                if (item.target) {
                    // Single target
                    const target = document.getElementById(item.target);
                    if (target) {
                        target.disabled = !isChecked;
                        if (target.type === 'checkbox') {
                            target.checked = false;
                        } else if (target.tagName === 'SELECT') {
                            target.value = '';
                        } else {
                            target.value = '';
                        }
                    }
                } else if (item.targets) {
                    // Multiple targets
                    item.targets.forEach(targetId => {
                        const target = document.getElementById(targetId);
                        if (target) {
                            target.disabled = !isChecked;
                            target.checked = false;
                        }
                    });
                }
            });
        }
    });
    
    // Update count when modal opens
    if (bulkEditModal) {
        bulkEditModal.addEventListener('show.bs.modal', function() {
            const selectedCount = document.querySelectorAll('.room-checkbox:checked').length;
            document.getElementById('bulkEditCount').textContent = selectedCount;
        });
    }
}

// Add Building Modal Functions
function showAddBuildingModal() {
    // Wait for Bootstrap to be available, then attempt to show the modal.
    waitForBootstrap(function() {
        var el = document.getElementById('addBuildingModal');
        if (!el) {
            console.error('showAddBuildingModal: addBuildingModal element not found');
            return;
        }

        try {
            // Try safe initialization first
            var modal = null;
            try {
                modal = safeModalInit(el);
            } catch (e) {
                console.warn('safeModalInit failed for addBuildingModal:', e);
            }

            if (modal) {
                modal.show();
                return;
            }

            // Try native Bootstrap constructor if available
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                try {
                    var nativeModal = new bootstrap.Modal(el);
                    nativeModal.show();
                    return;
                } catch (e) {
                    console.warn('Native Bootstrap Modal failed for addBuildingModal:', e);
                }
            }

            // Last-resort fallback: toggle modal classes manually so user can still interact
            el.classList.add('show');
            el.style.display = 'block';
            el.setAttribute('aria-modal', 'true');
            el.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');

            // Add backdrop element if none exists
            if (!document.querySelector('.modal-backdrop')) {
                var backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
            }
        } catch (error) {
            console.error('Error showing add building modal:', error);
        }
    });
}

// Multi-add handlers
document.addEventListener('DOMContentLoaded', function() {
    const addMultipleLink = document.getElementById('addMultipleLink');
    const multipleOptions = document.getElementById('multipleOptions');
    const applyGenerate = document.getElementById('applyGenerate');
    const buildingSelect = document.getElementById('building_id');
    const nameInput = document.getElementById('name');

    if (addMultipleLink && multipleOptions) {
        addMultipleLink.addEventListener('click', function(e) {
            e.preventDefault();
            multipleOptions.style.display = multipleOptions.style.display === 'none' ? '' : 'none';
        });
    }

    if (applyGenerate && buildingSelect && nameInput) {
        applyGenerate.addEventListener('click', function() {
            const start = parseInt(document.getElementById('multi_start').value) || 1;
            const count = parseInt(document.getElementById('multi_count').value) || 1;
            const selectedOption = buildingSelect.options[buildingSelect.selectedIndex];
            const bcode = selectedOption ? (selectedOption.getAttribute('data-code') || '') : '';
            if (!bcode) {
                alert('Please select a building with a code before generating multiple rooms.');
                return;
            }
            // Fill name input with first generated value and store generated list on the button for later submit
            const firstName = bcode + ' ' + start;
            nameInput.value = firstName;

            // store generated names in dataset for use when submitting (client will submit form normally as single add)
            const generated = [];
            for (let i = 0; i < count; i++) { generated.push(bcode + ' ' + (start + i)); }
            applyGenerate.dataset.generated = JSON.stringify(generated);
            alert('Generated ' + generated.length + ' room names. Click Add Room to create them (will create multiple if detected).');
        });
    }

    // Intercept add room form submission to switch to multi-add when the multiple options are visible
    const addRoomForm = document.querySelector('#addRoomModal form');
    if (addRoomForm) {
        addRoomForm.addEventListener('submit', function(e) {
            const multipleOptionsEl = document.getElementById('multipleOptions');
            const multiCountEl = document.getElementById('multi_count');
            const actionInput = this.querySelector('input[name="action"]');
            if (multipleOptionsEl && multiCountEl && multipleOptionsEl.style.display !== 'none' && parseInt(multiCountEl.value) > 0) {
                // Switch to multi-add action
                if (actionInput) actionInput.value = 'add_multiple';
            } else {
                if (actionInput) actionInput.value = 'add';
            }
            // allow normal submit to proceed
        });
    }
});

// After multi-add/add, fetch updated buildings and clear add form fields when modal closes
document.addEventListener('DOMContentLoaded', function() {
    const addBuildingModalEl = document.getElementById('addBuildingModal');
    const addBuildingForm = addBuildingModalEl ? addBuildingModalEl.querySelector('form') : null;

    function clearAddBuildingForm() {
        if (!addBuildingForm) return;
        addBuildingForm.reset();
    }

    // When addBuilding modal hides, clear the form
    if (addBuildingModalEl) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            addBuildingModalEl.addEventListener('hidden.bs.modal', function() { clearAddBuildingForm(); });
        } else {
            // fallback: listen for clicks on close buttons
            addBuildingModalEl.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => btn.addEventListener('click', clearAddBuildingForm));
        }
    }

    // Implement a function to fetch latest buildings and repopulate selects
    function refreshBuildingSelects(selectors) {
        fetch('rooms.php?action=get_existing_buildings', { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(resp => resp.json())
        .then(data => {
            if (!Array.isArray(data)) return;
            const targetSelectors = selectors || document.querySelectorAll('select[name="building_id"], #building_id, #edit_building_id');
            targetSelectors.forEach(sel => {
                // remember selected value
                const prev = sel.value;
                // clear options
                sel.innerHTML = '<option value="">Select Building</option>';
                data.forEach(b => {
                    const opt = document.createElement('option'); opt.value = b.id; opt.textContent = b.name; if (b.code) opt.setAttribute('data-code', b.code); sel.appendChild(opt);
                });
                // try restore previous or leave empty
                if (prev) sel.value = prev;
            });
        })
        .catch(err => console.warn('Failed to refresh buildings', err));
    }

    // Expose refresh function globally for other scripts to call after multi-add
    window.refreshBuildingSelects = refreshBuildingSelects;
});

// AJAX: submit Add Building form in background and update building selects without full reload
document.addEventListener('DOMContentLoaded', function() {
    const addBuildingForm = document.querySelector('#addBuildingModal form');
    if (!addBuildingForm) return;

    addBuildingForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const formData = new FormData(form);
        formData.set('action', 'add_building');

        const submitBtn = form.querySelector('button[type="submit"]');
        const origText = submitBtn ? submitBtn.innerHTML : null;
        if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...'; }

        fetch('rooms.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(async response => {
            const text = await response.text();
            let data = null;
            try { data = text ? JSON.parse(text) : null; } catch (e) { console.warn('Non-JSON response for add_building:', text); }
            return { ok: response.ok, status: response.status, data: data, text: text };
        })
        .then(obj => {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origText; }
            if (!obj.ok) {
                console.error('Add building failed', obj.status, obj.text);
                const msg = (obj.data && obj.data.message) ? obj.data.message : obj.text || 'Server error';
                alert('Error adding building: ' + msg);
                return;
            }
            const data = obj.data;
            if (!data) { alert('Unexpected server response when adding building'); return; }

            if (data.success) {
                const selectors = document.querySelectorAll('select[name="building_id"], #building_id, #edit_building_id');
                selectors.forEach(sel => {
                    try {
                        const opt = document.createElement('option');
                        opt.value = data.id;
                        opt.textContent = data.name;
                        if (data.code) opt.setAttribute('data-code', data.code);
                        sel.appendChild(opt);
                    } catch (e) { console.warn('Could not append building option', e); }
                });

                const bsel = document.getElementById('building_id'); if (bsel) bsel.value = data.id;
                const ebsel = document.getElementById('edit_building_id'); if (ebsel) ebsel.value = data.id;

                const modalEl = document.getElementById('addBuildingModal');
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    try { bootstrap.Modal.getOrCreateInstance(modalEl).hide(); } catch(e){/*silent*/}
                } else if (modalEl) {
                    modalEl.classList.remove('show'); modalEl.style.display = 'none'; document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop'); if (backdrop) backdrop.remove();
                }

                showSaveIndicator('success');
            } else {
                const msg = data.message || 'Failed to add building';
                alert(msg);
            }
        })
        .catch(err => {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origText; }
            console.error('Fetch error while adding building:', err);
            alert('Error adding building: network or server error (see console)');
        });
    });
});

</script>