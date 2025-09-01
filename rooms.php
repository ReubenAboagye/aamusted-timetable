<?php
session_start();
// Normalize action early to avoid undefined index notices
$action = $_POST['action'] ?? null;
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
// Handle AJAX requests for existing rooms data FIRST, before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_existing_rooms') {
    // Prevent any output before JSON response
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    try {
        // Include database connection
        include 'connect.php';
        
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
        
        // Return error response as JSON
        header('Content-Type: application/json; charset=utf-8');
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Database error occurred: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    
    exit;
}

// Include necessary files for form processing
include 'connect.php';
include 'includes/flash.php';

// Handle form submissions BEFORE any HTML output to avoid header issues
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    // Add Building
    if ($action === 'add_building') {
        $building_name = trim($conn->real_escape_string($_POST['building_name']));
        $building_code = trim($conn->real_escape_string($_POST['building_code']));
        $building_description = trim($conn->real_escape_string($_POST['building_description']));
        
        if (empty($building_name) || empty($building_code)) {
            $_SESSION['error_message'] = "Building name and code are required.";
        } else {
            // Check if building name or code already exists
            $check_sql = "SELECT id FROM buildings WHERE name = ? OR code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("ss", $building_name, $building_code);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result && $check_result->num_rows > 0) {
                $_SESSION['error_message'] = "A building with this name or code already exists.";
                $check_stmt->close();
            } else {
                $check_stmt->close();
                $sql = "INSERT INTO buildings (name, code, description, is_active) VALUES (?, ?, ?, 1)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $building_name, $building_code, $building_description);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    redirect_with_flash('rooms.php', 'success', 'Building added successfully!');
                } else {
                    error_log("ERROR: Add Building - Insert failed: " . $stmt->error);
                    $_SESSION['error_message'] = "Error adding building: " . $stmt->error;
                }
                $stmt->close();
            }
        }
        // Redirect to prevent form resubmission
        header('Location: rooms.php');
        exit;
    }

    // Bulk import handler (expects JSON array in 'import_data')
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_import') {
        $import_json = $_POST['import_data'] ?? '';
        $import_data = json_decode($import_json, true);

        if (!$import_data || !is_array($import_data)) {
            redirect_with_flash('rooms.php', 'error', 'Invalid import data.');
            exit;
        }

        // Room type mapping from form labels to database values
        $room_type_mappings = [
            'Classroom' => 'classroom',
            'Lecture Hall' => 'lecture_hall',
            'Laboratory' => 'laboratory',
            'Computer Lab' => 'computer_lab',
            'Seminar Room' => 'seminar_room',
            'Auditorium' => 'auditorium'
        ];

        $insert_sql = "INSERT INTO rooms (name, room_type, capacity, building_id, building, is_active) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);

        $check_sql = "SELECT id FROM rooms WHERE name = ? AND building_id = ? LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($import_data as $idx => $row) {
            $name = trim($row['name'] ?? '');
            $room_type_input = trim($row['room_type'] ?? '');
            $capacity = isset($row['capacity']) ? (int)$row['capacity'] : 0;
            $is_active = isset($row['is_active']) ? (int)$row['is_active'] : 1;

            if ($name === '') {
                $skipped++;
                $errors[] = "Row {$idx}: empty name";
                continue;
            }

            if ($capacity < 1 || $capacity > 500) {
                $capacity = 30;
            }

            // Resolve building_id
            $building_id = 1;
            if (!empty($row['building_id']) && (int)$row['building_id'] > 0) {
                $building_id = (int)$row['building_id'];
            } elseif (!empty($row['building'])) {
                $bname = trim($row['building']);
                $bst = $conn->prepare("SELECT id FROM buildings WHERE name = ? LIMIT 1");
                if ($bst) {
                    $bst->bind_param('s', $bname);
                    $bst->execute();
                    $bres = $bst->get_result();
                    if ($bres && $bres->num_rows > 0) {
                        $brow = $bres->fetch_assoc();
                        $building_id = (int)$brow['id'];
                    }
                    $bst->close();
                }
            }

            // Resolve building name for insert (some schemas include 'building' column)
            $building_name = '';
            if (!empty($row['building'])) {
                $building_name = trim($row['building']);
            } else {
                $bst2 = $conn->prepare("SELECT name FROM buildings WHERE id = ? LIMIT 1");
                if ($bst2) {
                    $bst2->bind_param('i', $building_id);
                    $bst2->execute();
                    $bres2 = $bst2->get_result();
                    if ($bres2 && $bres2->num_rows > 0) {
                        $brow2 = $bres2->fetch_assoc();
                        $building_name = $brow2['name'];
                    }
                    $bst2->close();
                }
            }

            // Map room type
            $db_room_type = $room_type_mappings[$room_type_input] ?? strtolower(str_replace(' ', '_', $room_type_input));

            // Duplicate check
            if ($check_stmt) {
                $check_stmt->bind_param('si', $name, $building_id);
                $check_stmt->execute();
                $cres = $check_stmt->get_result();
                if ($cres && $cres->num_rows > 0) {
                    $skipped++;
                    continue;
                }
            }

            if ($insert_stmt) {
                $insert_stmt->bind_param('ssiisi', $name, $db_room_type, $capacity, $building_id, $building_name, $is_active);
                if ($insert_stmt->execute()) {
                    $imported++;
                } else {
                    $errors[] = "Row {$idx}: insert failed: " . $insert_stmt->error;
                }
            } else {
                $errors[] = "Row {$idx}: insert statement not prepared";
            }
        }

        if ($insert_stmt) $insert_stmt->close();
        if ($check_stmt) $check_stmt->close();

        $message = "Imported {$imported} rooms.";
        if ($skipped > 0) $message .= " {$skipped} skipped.";
        if (!empty($errors)) {
            // store up to first 10 errors in session
            $short_errors = array_slice($errors, 0, 10);
            redirect_with_flash('rooms.php', 'error', $message . ' Errors: ' . implode(' | ', $short_errors));
        } else {
            redirect_with_flash('rooms.php', 'success', $message);
        }
        exit;
    }


}
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Normalize action to avoid undefined index notices
    $action = $_POST['action'] ?? null;
    // Debug: Log all POST data
    error_log("POST action: " . ($action ?? 'NULL'));
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
    if (isset($_POST['room_type'])) {
        error_log("Raw room_type from POST: '" . $_POST['room_type'] . "'");
        error_log("Raw room_type length: " . strlen($_POST['room_type']));
        error_log("Raw room_type ASCII: " . implode(',', array_map('ord', str_split($_POST['room_type']))));
    }
    
    // Single add
    } elseif ($action === 'add') {
        // Debug: Log all POST data
        error_log("Single Add - POST data received: " . json_encode($_POST));
        error_log("Single Add - Raw room_type value: '" . $_POST['room_type'] . "'");
        error_log("Single Add - Raw room_type length: " . strlen($_POST['room_type']));
        error_log("Single Add - Raw room_type bytes: " . implode(',', array_map('ord', str_split($_POST['room_type']))));
        
        $name = trim($conn->real_escape_string($_POST['name']));
        $building_id = (int)$_POST['building_id'];
        $room_type = trim($conn->real_escape_string($_POST['room_type']));
        $valid_form_room_types = ['Classroom', 'Lecture Hall', 'Laboratory', 'Computer Lab', 'Seminar Room', 'Auditorium'];

        error_log("Single Add - After trim room_type: '$room_type'");
        error_log("Single Add - After trim room_type length: " . strlen($room_type));
        error_log("Single Add - After trim room_type bytes: " . implode(',', array_map('ord', str_split($room_type))));
        error_log("Single Add - Valid form types: " . json_encode($valid_form_room_types));

        if (!in_array($room_type, $valid_form_room_types)) {
            error_log("ERROR: Single Add - Invalid room type: '$room_type'");
            $error_message = "Invalid room type selected. Please choose a valid room type from the dropdown.";
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
            error_log("Single Add - Original room_type: '$room_type', Converted to: '$db_room_type'");
            error_log("Single Add - Room type mapping used: '$room_type' => '$room_type' => '$db_room_type'");
            error_log("Single Add - Available mappings: " . json_encode($room_type_mappings));
            
            // Debug: Check exact string comparison
            error_log("Single Add - Room type exact match check:");
            foreach ($room_type_mappings as $form_value => $db_value) {
                $exact_match = ($form_value === $room_type);
                $length_match = (strlen($form_value) === strlen($room_type));
                error_log("  '$form_value' vs '$room_type': exact=$exact_match, length=$length_match");
            }
            
            // Application-level validation (hardcoded)
            $valid_room_types = ['classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium'];
            if (!in_array($db_room_type, $valid_room_types)) {
                error_log("ERROR: Single Add - Invalid room type '$db_room_type' from original '$room_type'");
                $error_message = "Invalid room type selected. Please try again.";
            } else {
                error_log("SUCCESS: Single Add - Valid room type '$db_room_type'");

                // Check for duplicates using building_id (current schema)
                $check_sql = "SELECT id FROM rooms WHERE name = ? AND building_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("si", $name, $building_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result && $check_result->num_rows > 0) {
                    $error_message = "Room with this name already exists in the selected building.";
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    // Final debug before INSERT
                    error_log("Single Add - Final values before INSERT:");
                    error_log("  name: '$name'");
                    error_log("  building_id: '$building_id'");
                    error_log("  room_type: '$db_room_type'");
                    error_log("  room_type length: " . strlen($db_room_type));
                    error_log("  room_type bytes: " . implode(',', array_map('ord', str_split($db_room_type))));
                    error_log("  capacity: $capacity");


                    error_log("  is_active: $is_active");
                    
                    // Final validation - ensure room_type matches database ENUM exactly
                    $valid_db_room_types = ['classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium'];
                    if (!in_array($db_room_type, $valid_db_room_types)) {
                        error_log("ERROR: Single Add - Final validation failed: room_type '$db_room_type' not in valid list: " . json_encode($valid_db_room_types));
                        $error_message = "Invalid room type value. Please try again.";
                    } else {
                        error_log("SUCCESS: Single Add - Final validation passed for room_type: '$db_room_type'");
                        
                                                $sql = "INSERT INTO rooms (name, room_type, capacity, building_id, is_active) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        if ($stmt) {
                            // Use provided building_id from form; do not override with hardcoded default
                            $stmt->bind_param("ssiii", $name, $db_room_type, $capacity, $building_id, $is_active);
                            error_log("Single Add - Binding: name='$name', room_type='$db_room_type', capacity=$capacity, building_id=$building_id, is_active=$is_active");

                            if ($stmt->execute()) {
                                $stmt->close();
                                redirect_with_flash('rooms.php', 'success', 'Room added successfully!');
                            } else {
                                error_log("ERROR: Single Add - Insert failed: " . $stmt->error);
                                error_log("ERROR: Single Add - Failed values: name='$name', room_type='$db_room_type', capacity=$capacity, building_id=$building_id, is_active=$is_active");
                                $error_message = "Error adding room: " . $stmt->error;
                            }
                            $stmt->close();
                        } else {
                            $error_message = "Error preparing statement: " . $conn->error;
                        }
                    }
                }
            }
        }
        
        // Redirect to prevent form resubmission
        if (isset($error_message)) {
            $_SESSION['error_message'] = $error_message;
        }
        header('Location: rooms.php');
        exit;

    // Edit
    } elseif ($action === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = trim($conn->real_escape_string($_POST['name']));
        $building_id = (int)$_POST['building_id'];
        $room_type = trim($conn->real_escape_string($_POST['room_type']));
        $valid_form_room_types = ['Classroom', 'Lecture Hall', 'Laboratory', 'Computer Lab', 'Seminar Room', 'Auditorium'];

        if (!in_array($room_type, $valid_form_room_types)) {
            error_log("ERROR: Edit - Invalid room type: '$room_type'");
            $error_message = "Invalid room type selected. Please choose a valid room type from the dropdown.";
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
            error_log("Edit - Original room_type: '$room_type', Converted to: '$db_room_type'");
            
            // Application-level validation (hardcoded)
            $valid_room_types = ['classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium'];
            if (!in_array($db_room_type, $valid_room_types)) {
                error_log("ERROR: Edit - Invalid room type '$db_room_type' from original '$room_type'");
                $error_message = "Invalid room type selected. Please try again.";
            } else {
                error_log("SUCCESS: Edit - Valid room type '$db_room_type'");

                // Check for duplicates - use building_id column (current schema) and correct parameter
                $check_sql = "SELECT id FROM rooms WHERE name = ? AND building_id = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("sii", $name, $building_id, $id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result && $check_result->num_rows > 0) {
                    $error_message = "Another room with this name exists in the selected building.";
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    $sql = "UPDATE rooms SET name = ?, room_type = ?, capacity = ?, building_id = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        // Use provided building_id from form; do not override with hardcoded default
                        $stmt->bind_param("ssiiii", $name, $db_room_type, $capacity, $building_id, $is_active, $id);
                        error_log("Edit - Binding: name='$name', room_type='$db_room_type', capacity=$capacity, building_id=$building_id, is_active=$is_active, id=$id");

                        if ($stmt->execute()) {
                            $stmt->close();
                            redirect_with_flash('rooms.php', 'success', 'Room updated successfully!');
                        } else {
                            error_log("ERROR: Edit - Update failed: " . $stmt->error);
                            error_log("ERROR: Edit - Failed values: name='$name', room_type='$db_room_type', capacity=$capacity, building_id=$building_id, is_active=$is_active, id=$id");
                            $error_message = "Error updating room: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error_message = "Error preparing statement: " . $conn->error;
                    }
                }
            }
        }
        
        // Redirect to prevent form resubmission
        if (isset($error_message)) {
            $_SESSION['error_message'] = $error_message;
        }
        header('Location: rooms.php');
        exit;

    // Bulk Edit
    } elseif ($action === 'bulk_edit' && isset($_POST['room_ids'])) {
        $room_ids = json_decode($_POST['room_ids'], true);
        if (!$room_ids || !is_array($room_ids)) {
            $error_message = "Invalid room selection.";
        } else {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($room_ids as $room_id) {
                $id = (int)$room_id;
                
                // Build dynamic UPDATE query based on what fields are being updated
                $update_fields = [];
                $update_values = [];
                $update_types = "";
                
                // Room Type
                if (isset($_POST['room_type']) && !empty($_POST['room_type'])) {
                    $room_type = trim($conn->real_escape_string($_POST['room_type']));
                    $valid_form_room_types = ['Classroom', 'Lecture Hall', 'Laboratory', 'Computer Lab', 'Seminar Room', 'Auditorium'];
                    
                    if (in_array($room_type, $valid_form_room_types)) {
                        $room_type_mappings = [
                            'Classroom' => 'classroom',
                            'Lecture Hall' => 'lecture_hall',
                            'Laboratory' => 'laboratory',
                            'Computer Lab' => 'computer_lab',
                            'Seminar Room' => 'seminar_room',
                            'Auditorium' => 'auditorium'
                        ];
                        $db_room_type = $room_type_mappings[$room_type] ?? 'classroom';
                        
                        $update_fields[] = "room_type = ?";
                        $update_values[] = $db_room_type;
                        $update_types .= "s";
                    }
                }
                
                // Capacity
                if (isset($_POST['capacity']) && !empty($_POST['capacity'])) {
                    $capacity = (int)$_POST['capacity'];
                    if ($capacity >= 1 && $capacity <= 500) {
                        $update_fields[] = "capacity = ?";
                        $update_values[] = $capacity;
                        $update_types .= "i";
                    }
                }
                

                
                // Status
                if (isset($_POST['is_active'])) {
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    $update_fields[] = "is_active = ?";
                    $update_values[] = $is_active;
                    $update_types .= "i";
                }
                
                // Only update if there are fields to update
                if (!empty($update_fields)) {
                    $update_fields[] = "updated_at = NOW()";
                    $sql = "UPDATE rooms SET " . implode(", ", $update_fields) . " WHERE id = ?";
                    $update_values[] = $id;
                    $update_types .= "i";
                    
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param($update_types, ...$update_values);
                        if ($stmt->execute()) {
                            $success_count++;
                        } else {
                            error_log("ERROR: Bulk Edit - Update failed for room $id: " . $stmt->error);
                            $error_count++;
                        }
                        $stmt->close();
                    } else {
                        error_log("ERROR: Bulk Edit - Prepare failed for room $id: " . $conn->error);
                        $error_count++;
                    }
                }
            }
            
            if ($success_count > 0) {
                $success_message = "Successfully updated $success_count rooms!";
                if ($error_count > 0) {
                    $success_message .= " $error_count rooms failed to update.";
                }
            } else {
                $error_message = "No rooms were updated. Please check your selection.";
            }
        }
        
        // Redirect to prevent form resubmission
        if (isset($success_message)) {
            $_SESSION['success_message'] = $success_message;
        } elseif (isset($error_message)) {
            $_SESSION['error_message'] = $error_message;
        }
        header('Location: rooms.php');
        exit;
    
    // Delete (soft delete: set is_active = 0)
    } elseif ($action === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE rooms SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_flash('rooms.php', 'success', 'Room deleted.');
        } else {
            error_log("ERROR: Delete - Update failed: " . $stmt->error);
            $error_message = "Error deleting room: " . $conn->error;
        }
        $stmt->close();
        
        // Redirect to prevent form resubmission
        if (isset($error_message)) {
            $_SESSION['error_message'] = $error_message;
        }
        header('Location: rooms.php');
        exit;
}

// Fetch rooms with all fields from current schema, joining with buildings table
$sql = "SELECT r.id, r.name, b.name as building_name, r.room_type, r.capacity, r.is_active, r.created_at, r.updated_at, r.building_id 
        FROM rooms r 
        LEFT JOIN buildings b ON r.building_id = b.id 
        WHERE r.is_active = 1 
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
            <input type="text" class="search-input" placeholder="Search rooms...">
        </div>

        <div class="table-responsive">
            <table class="table" id="roomsTable">
                <thead>
                    <tr>

                        <th>Room Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Building</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
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
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editRoom(<?php echo $row['id']; ?>, <?php echo json_encode($row['name']); ?>, <?php echo $row['building_id']; ?>, <?php echo json_encode(ucwords(str_replace('_', ' ', $row['room_type']))); ?>, <?php echo (int)$row['capacity']; ?>, <?php echo $row['is_active']; ?>)">
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
                            <td colspan="4" class="empty-state">
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
    editRoomType.value = roomTypeMappings[roomType] || 'Classroom';



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

// Add search functionality
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('.search-input')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('roomsTable');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
    });

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
    
    // Update select all checkbox state
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


</script>