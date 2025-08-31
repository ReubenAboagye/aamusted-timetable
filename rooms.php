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

    // Bulk Add Rooms
    if ($action === 'bulk_add_rooms') {
        $building_id = (int)$_POST['building_id'];
        $room_type = trim($conn->real_escape_string($_POST['room_type']));
        $capacity = (int)$_POST['capacity'];
        $room_prefix = trim($conn->real_escape_string($_POST['room_prefix']));
        $room_suffix = trim($conn->real_escape_string($_POST['room_suffix']));
        $start_number = (int)$_POST['start_number'];
        $end_number = (int)$_POST['end_number'];
        
        if ($building_id <= 0 || empty($room_type) || $capacity <= 0 || empty($room_prefix) || $start_number <= 0 || $end_number <= 0) {
            $_SESSION['error_message'] = "All required fields must be filled.";
        } elseif ($start_number > $end_number) {
            $_SESSION['error_message'] = "Start number must be less than or equal to end number.";
        } elseif (($end_number - $start_number + 1) > 100) {
            $_SESSION['error_message'] = "Cannot create more than 100 rooms at once.";
        } else {
            // Convert room type to database format
            $room_type_mappings = [
                'Classroom' => 'classroom',
                'Lecture Hall' => 'lecture_hall',
                'Laboratory' => 'laboratory',
                'Computer Lab' => 'computer_lab',
                'Seminar Room' => 'seminar_room',
                'Auditorium' => 'auditorium'
            ];
            $db_room_type = $room_type_mappings[$room_type] ?? 'classroom';
            
            $success_count = 0;
            $error_count = 0;
            $duplicate_count = 0;
            
            // Prepare the insert statement
            $sql = "INSERT INTO rooms (name, room_type, capacity, building_id, is_active) VALUES (?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($sql);
            
            for ($i = $start_number; $i <= $end_number; $i++) {
                $room_name = $room_prefix . $i . $room_suffix;
                
                // Check if room already exists
                $check_sql = "SELECT id FROM rooms WHERE name = ? AND building_id = ? AND is_active = 1";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("si", $room_name, $building_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result && $check_result->num_rows > 0) {
                    $duplicate_count++;
                    $check_stmt->close();
                    continue;
                }
                $check_stmt->close();
                
                // Insert the room
                $stmt->bind_param("ssii", $room_name, $db_room_type, $capacity, $building_id);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                    error_log("ERROR: Bulk Add Rooms - Failed to insert room: $room_name");
                }
            }
            
            $stmt->close();
            
            // Create success message
            $message_parts = [];
            if ($success_count > 0) {
                $message_parts[] = "$success_count rooms created successfully";
            }
            if ($duplicate_count > 0) {
                $message_parts[] = "$duplicate_count rooms skipped (already exist)";
            }
            if ($error_count > 0) {
                $message_parts[] = "$error_count rooms failed to create";
            }
            
            if ($success_count > 0) {
                redirect_with_flash('rooms.php', 'success', implode(', ', $message_parts) . '.');
            } else {
                $_SESSION['error_message'] = implode(', ', $message_parts) . '.';
            }
        }
        // Redirect to prevent form resubmission
        header('Location: rooms.php');
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

// Handle bulk import and form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Normalize action to avoid undefined index notices
    $action = $_POST['action'] ?? null;
    // Debug: Log all POST data
    error_log("POST action: " . ($action ?? 'NULL'));
    if (isset($_POST['room_type'])) {
        error_log("Raw room_type from POST: '" . $_POST['room_type'] . "'");
        error_log("Raw room_type length: " . strlen($_POST['room_type']));
        error_log("Raw room_type ASCII: " . implode(',', array_map('ord', str_split($_POST['room_type']))));
    }
    // Bulk import
    if ($action === 'bulk_import' && isset($_POST['import_data'])) {
        $import_data = json_decode($_POST['import_data'], true);
        if ($import_data) {
            $success_count = 0;
            $ignored_count = 0;
            $error_count = 0;

            // Prepare a check statement to detect existing (name + building)
            $check_sql = "SELECT id FROM rooms WHERE name = ? AND building = ?";
            $check_stmt = $conn->prepare($check_sql);

            // Hardcoded room type mappings for CSV import
            $room_type_mappings = [
                'classroom' => 'classroom',
                'class room' => 'classroom',
                'class' => 'classroom',
                'lecture_hall' => 'lecture_hall',
                'lecture hall' => 'lecture_hall',
                'lecture' => 'lecture_hall',
                'hall' => 'lecture_hall',
                'laboratory' => 'laboratory',
                'lab' => 'laboratory',
                'computer_lab' => 'computer_lab',
                'computer lab' => 'computer_lab',
                'computer' => 'computer_lab',
                'comp lab' => 'computer_lab',
                'seminar_room' => 'seminar_room',
                'seminar room' => 'seminar_room',
                'seminar' => 'seminar_room',
                'auditorium' => 'auditorium'
            ];

            foreach ($import_data as $row) {
                // Debug: Log the raw row data
                error_log("Bulk Import - Raw row data: " . json_encode($row));
                
                // Sanitize and trim inputs
                $name = isset($row['name']) ? trim($conn->real_escape_string($row['name'])) : '';
                $room_type = isset($row['room_type']) ? trim($conn->real_escape_string($row['room_type'])) : 'classroom';
                $capacity = isset($row['capacity']) ? (int)$row['capacity'] : 30;
                $building_id = 1; // Default to main building since we only have one

                // Debug: Log the processed data
                error_log("Bulk Import - Processing row: name='$name', room_type='$room_type', capacity=$capacity, building_id=$building_id");

                $is_active = isset($row['is_active']) ? (int)(strtolower(trim($row['is_active'])) === '1' || strtolower(trim($row['is_active'])) === 'true') : 1;

                if ($name === '') {
                    error_log("ERROR: Bulk Import - Missing name");
                    $error_count++;
                    continue;
                }

                // Convert and validate room_type
                if (empty($room_type) || $room_type === null) {
                    error_log("ERROR: Bulk Import - Room type is empty or null, using default 'classroom'");
                    $room_type = 'classroom';
                }
                
                $room_type_lower = strtolower(trim($room_type));
                $db_room_type = isset($room_type_mappings[$room_type_lower]) ? $room_type_mappings[$room_type_lower] : 'classroom';
                error_log("Bulk Import - Original room_type: '$room_type', Lowercase: '$room_type_lower', Converted to: '$db_room_type'");
                error_log("Bulk Import - Available mappings: " . implode(', ', array_keys($room_type_mappings)));

                // Application-level validation (hardcoded)
                $valid_room_types = ['classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium'];
                if (!in_array($db_room_type, $valid_room_types)) {
                    error_log("ERROR: Bulk Import - Invalid room type '$db_room_type' from original '$room_type'");
                    error_log("ERROR: Bulk Import - Falling back to 'classroom'");
                    $db_room_type = 'classroom';
                }
                error_log("SUCCESS: Bulk Import - Final room type: '$db_room_type'");

                // Skip if room with same name and building exists
                if ($check_stmt) {
                    $check_stmt->bind_param("ss", $name, $building);
                    $check_stmt->execute();
                    $existing = $check_stmt->get_result();
                    if ($existing && $existing->num_rows > 0) {
                        error_log("Bulk Import - Skipping duplicate: name='$name', building='$building'");
                        $ignored_count++;
                        continue;
                    }
                }

                // Final validation before insert
                error_log("Bulk Import - Final validation - room_type: '$db_room_type', valid types: " . implode(', ', $valid_room_types));
                if (!in_array($db_room_type, $valid_room_types)) {
                    error_log("ERROR: Bulk Import - Final validation failed for room_type: '$db_room_type'");
                    $error_count++;
                    continue;
                }
                
                // Check room type length and format
                if (strlen($db_room_type) > 20) {
                    error_log("ERROR: Bulk Import - Room type too long: '$db_room_type' (length: " . strlen($db_room_type) . ")");
                    $error_count++;
                    continue;
                }
                
                // Check for invalid characters in room type
                if (!preg_match('/^[a-z_]+$/', $db_room_type)) {
                    error_log("ERROR: Bulk Import - Room type contains invalid characters: '$db_room_type'");
                    $error_count++;
                    continue;
                }

                $sql = "INSERT INTO rooms (name, room_type, capacity, building_id, is_active) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    error_log("ERROR: Bulk Import - Prepare failed: " . $conn->error);
                    $error_count++;
                    continue;
                }

                error_log("Bulk Import - Binding: name='$name', room_type='$db_room_type', capacity=$capacity, building_id=$building_id, is_active=$is_active");

                $stmt->bind_param("ssiii", $name, $db_room_type, $capacity, $building_id, $is_active);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    error_log("ERROR: Bulk Import - Insert failed: " . $stmt->error);
                    error_log("ERROR: Bulk Import - Failed values: name='$name', room_type='$db_room_type', capacity=$capacity, building_id=$building_id, is_active=$is_active");
                    $error_count++;
                }
                $stmt->close();
            }

            if ($check_stmt) $check_stmt->close();

            if ($success_count > 0) {
                $msg = "Successfully imported $success_count rooms!";
                if ($ignored_count > 0) $msg .= " $ignored_count duplicates ignored.";
                if ($error_count > 0) $msg .= " $error_count records failed to import.";
                redirect_with_flash('rooms.php', 'success', $msg);
            } else {
                if ($ignored_count > 0 && $error_count === 0) {
                    $success_message = "No new rooms imported. $ignored_count duplicates ignored.";
                } else {
                    $error_message = "No rooms were imported. Please check your data.";
                }
            }
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

                // Check for duplicates
                $check_sql = "SELECT id FROM rooms WHERE name = ? AND building = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ss", $name, $building);
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
                    error_log("  building: '$building'");
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
                            $building_id = 1; // Default to main building
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

                // Check for duplicates
                $check_sql = "SELECT id FROM rooms WHERE name = ? AND building = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ssi", $name, $building, $id);
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
                        $building_id = 1; // Default to main building
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
    

}

// Fetch rooms with all fields from current schema, joining with buildings table
$sql = "SELECT r.id, r.name, b.name as building_name, r.room_type, r.capacity, r.is_active, r.created_at, r.updated_at, r.building_id 
        FROM rooms r 
        LEFT JOIN buildings b ON r.building_id = b.id 
        WHERE r.is_active = 1 
        ORDER BY b.name, r.name";
$result = $conn->query($sql);

// Fetch buildings for dropdown
$buildings_sql = "SELECT id, name FROM buildings WHERE is_active = 1 ORDER BY name";
$buildings_result = $conn->query($buildings_sql);

}
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-door-open me-2"></i>Rooms Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-upload me-2"></i>Import
                </button>
                <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#bulkAddRoomsModal">
                    <i class="fas fa-layer-group me-2"></i>Bulk Add Rooms
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
                    <i class="fas fa-plus me-2"></i>Add New Room
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
                                            echo '<option value="' . $building['id'] . '">' . htmlspecialchars($building['name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">
                                    <a href="#" onclick="showAddBuildingModal()" class="text-decoration-none">
                                        <i class="fas fa-plus-circle me-1"></i>Add New Building
                                    </a>
                                </small>
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
                                        while ($building = $buildings_result->fetch_assoc()) {
                                            echo '<option value="' . $building['id'] . '">' . htmlspecialchars($building['name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">
                                    <a href="#" onclick="showAddBuildingModal()" class="text-decoration-none">
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

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Rooms</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-upload fa-3x text-primary mb-3"></i>
                    <h6>Upload CSV File</h6>
                    <p class="text-muted">Drop your CSV file here or click to browse</p>
                </div>

                <div class="mb-3">
                    <div class="upload-area" id="uploadArea" style="border: 2px dashed #ccc; border-radius: 8px; padding: 40px; text-align: center; background: #f8f9fa; cursor: pointer;">
                        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-3"></i>
                        <p class="mb-2">Drop CSV file here or <strong>click to browse</strong></p>
                        <small class="text-muted">Supported format: CSV with headers: name,room_type,capacity,is_active</small>
                        <br><small class="text-muted">Room types: classroom, lecture_hall, laboratory, computer_lab, seminar_room, auditorium</small>
                        <br><small class="text-muted">All rooms will be assigned to the main building automatically</small>
                    </div>
                    <input type="file" class="form-control d-none" id="csvFile" accept=".csv">
                </div>

                <div class="mb-3">
                    <h6>Preview (first 10 rows)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm" id="previewTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Building</th>
                                    <th>Type</th>
                                    <th>Capacity</th>


                                    <th>Status</th>
                                    <th>Validation</th>
                                </tr>
                            </thead>
                            <tbody id="previewBody"></tbody>
                        </table>
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="processBtn" disabled>Process File</button>
                </div>
            </div>
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

<!-- Bulk Add Rooms Modal -->
<div class="modal fade" id="bulkAddRoomsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Add Rooms</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_add_rooms">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_building_id" class="form-label">Building *</label>
                                <select class="form-select" id="bulk_building_id" name="building_id" required>
                                    <option value="">Select Building</option>
                                    <?php
                                    if ($buildings_result && $buildings_result->num_rows > 0) {
                                        mysqli_data_seek($buildings_result, 0); // Reset pointer
                                        while ($building = $buildings_result->fetch_assoc()) {
                                            echo '<option value="' . $building['id'] . '" data-code="' . htmlspecialchars($building['code']) . '">' . htmlspecialchars($building['name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                                </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bulk_room_type" class="form-label">Room Type *</label>
                                <select class="form-select" id="bulk_room_type" name="room_type" required>
                                    <option value="">Select Type *</option>
                                    <option value="Classroom">Classroom</option>
                                    <option value="Lecture Hall">Lecture Hall</option>
                                    <option value="Laboratory">Laboratory</option>
                                    <option value="Computer Lab">Computer Lab</option>
                                    <option value="Seminar Room">Seminar Room</option>
                                    <option value="Auditorium">Auditorium</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                    <div class="mb-3">
                                <label for="bulk_capacity" class="form-label">Capacity *</label>
                                <input type="number" class="form-control" id="bulk_capacity" name="capacity" min="1" max="500" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                            <div class="mb-3">
                                <label for="room_prefix" class="form-label">Room Prefix *</label>
                                <input type="text" class="form-control" id="room_prefix" name="room_prefix" required readonly>
                                <small class="text-muted">Automatically set to selected building's code</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                            <div class="mb-3">
                                <label for="room_suffix" class="form-label">Room Suffix</label>
                                <input type="text" class="form-control" id="room_suffix" name="room_suffix" placeholder="e.g., A, B">
                                <small class="text-muted">Optional text after the number</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                    <div class="mb-3">
                                <label for="start_number" class="form-label">Start Number *</label>
                                <input type="number" class="form-control" id="start_number" name="start_number" min="1" required>
                                <small class="text-muted">Starting room number</small>
                        </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_number" class="form-label">End Number *</label>
                                <input type="number" class="form-control" id="end_number" name="end_number" min="1" required>
                                <small class="text-muted">Ending room number</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Room Names Preview:</strong><br>
                        <span id="bulk_preview">Select a building and enter room range to see preview</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Rooms</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// Embed existing room name+building for client-side duplicate checks
$existing_name_building = [];
$rooms_res = $conn->query("SELECT name, building FROM rooms WHERE is_active = 1");
if ($rooms_res) {
    while ($r = $rooms_res->fetch_assoc()) {
        $existing_name_building[] = ['name' => $r['name'], 'building' => $r['building']];
    }
}
?>
<script>
var existingRoomNameBuilding = <?php echo json_encode($existing_name_building); ?> || [];
var existingRoomNameBuildingSet = {};
existingRoomNameBuilding.forEach(function(item){ if (item.name && item.building) existingRoomNameBuildingSet[(item.name.trim().toUpperCase() + '|' + item.building.trim().toUpperCase())] = true; });
</script>

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

let importDataRooms = [];
let existingRooms = [];

// Function to load existing rooms for duplicate checking
function loadExistingRooms() {
    console.log('Loading existing rooms for duplicate checking...');
    
    fetch('rooms.php?action=get_existing_rooms')
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.text().then(text => {
                console.log('Raw response:', text.substring(0, 200) + '...');
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', e);
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response');
                }
            });
        })
        .then(data => {
            console.log('Successfully loaded existing rooms:', data);
            existingRooms = data;
        })
        .catch(error => {
            console.error('Error loading existing rooms:', error);
            // Set empty array as fallback
            existingRooms = [];
        });
}

function parseCSVRooms(csvText) {
    console.log('Parsing CSV text, length:', csvText.length);
    console.log('CSV preview:', csvText.substring(0, 500));
    
    const lines = csvText.split('\n').filter(l => l.trim());
    console.log('Number of non-empty lines:', lines.length);
    
    if (lines.length === 0) {
        console.log('No lines found in CSV');
        return data;
    }
    
    // Parse headers - handle quoted CSV properly
    const headers = parseCSVLine(lines[0]).map(h => h.trim().toLowerCase().replace(/"/g, ''));
    console.log('Parsed headers:', headers);
    console.log('Expected headers: name, room_type, capacity, is_active');
    
    // Check if headers match expected format
    const expectedHeaders = ['name', 'room_type', 'capacity', 'is_active'];
    const missingHeaders = expectedHeaders.filter(h => !headers.includes(h));
    if (missingHeaders.length > 0) {
        console.warn('Missing headers:', missingHeaders);
    }
    
    const data = [];
    for (let i = 1; i < lines.length; i++) {
        console.log(`Processing line ${i}:`, lines[i].substring(0, 100));
        const values = parseCSVLine(lines[i]);
        console.log(`Line ${i} values:`, values);
        
        const row = {};
        headers.forEach((header, index) => {
            row[header] = values[index] ? values[index].trim() : '';
        });
        data.push(row);
        console.log(`Line ${i} parsed row:`, row);
    }
    
    console.log('Final parsed data:', data);
    return data;
}

// Helper function to parse CSV line with proper quote handling
function parseCSVLine(line) {
    console.log('parseCSVLine called with:', line);
    
    const result = [];
    let current = '';
    let inQuotes = false;
    
    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        
        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            result.push(current);
            current = '';
        } else {
            current += char;
        }
    }
    
    result.push(current);
    console.log('parseCSVLine result:', result);
    return result;
}

function validateRoomsData(data) {
    console.log('Validating rooms data:', data);
    console.log('Data type:', typeof data);
    console.log('Data length:', data ? data.length : 'undefined');
    
    if (!data || !Array.isArray(data)) {
        console.error('Invalid data format - expected array');
        return [];
    }
    
    const roomTypeMappings = {
        'classroom': 'classroom',
        'class room': 'classroom',
        'class': 'classroom',
        'lecture_hall': 'lecture_hall',
        'lecture hall': 'lecture_hall',
        'lecture': 'lecture_hall',
        'hall': 'lecture_hall',
        'laboratory': 'laboratory',
        'lab': 'laboratory',
        'computer_lab': 'computer_lab',
        'computer lab': 'computer_lab',
        'computer': 'computer_lab',
        'comp lab': 'computer_lab',
        'seminar_room': 'seminar_room',
        'seminar room': 'seminar_room',
        'seminar': 'seminar_room',
        'auditorium': 'auditorium'
    };

    return data.map(row => {
        console.log('Processing row:', row);
        
        const validated = {
            name: row.name || row.Name || '',
            room_type: row.room_type || row.roomType || row.room_type || 'classroom',
            capacity: row.capacity || row.Capacity || '30',
            is_active: (row.is_active || row.isActive || '1') === '1' ? '1' : '0'
        };
        
        console.log('Validated row:', validated);
        validated.valid = true;
        validated.errors = [];

        if (!validated.name.trim()) {
            validated.valid = false;
            validated.errors.push('Name required');
        }
        if (!validated.building.trim()) {
            validated.valid = false;
            validated.errors.push('Building required');
        }

        // Validate room_type
        const room_type_lower = validated.room_type.toLowerCase().trim();
        validated.room_type = roomTypeMappings[room_type_lower] || 'classroom';
        const validRoomTypes = ['classroom', 'lecture_hall', 'laboratory', 'computer_lab', 'seminar_room', 'auditorium'];
        if (!validRoomTypes.includes(validated.room_type)) {
            validated.valid = false;
            validated.errors.push(`Invalid room type: ${validated.room_type}`);
        }

        // Validate capacity
        const capacityNum = parseInt(validated.capacity);
        if (isNaN(capacityNum) || capacityNum < 1 || capacityNum > 500) {
            validated.valid = false;
            validated.errors.push('Capacity must be between 1 and 500');
        }
        validated.capacity = capacityNum;



        // Check for duplicate name+building (only if we have existing rooms data)
        if (existingRooms && existingRooms.length > 0) {
            const existingRoom = existingRooms.find(r => 
                r.name.toLowerCase() === validated.name.toLowerCase() && 
                r.building.toLowerCase() === validated.building.toLowerCase()
            );
            if (existingRoom) {
                validated.valid = false;
                validated.errors.push('Room name and building combination already exists.');
            }
        } else {
            console.log('Skipping duplicate check - no existing rooms data available');
        }

        return validated;
    });
}

// Helper function to format array fields for display
function formatArrayField(field) {
    if (!field || field === '[]') {
        return '<span class="text-muted">None</span>';
    }
    
    try {
        if (typeof field === 'string') {
            // If it's already a JSON string, parse it
            const parsed = JSON.parse(field);
            if (Array.isArray(parsed)) {
                if (parsed.length === 0) {
                    return '<span class="text-muted">None</span>';
                }
                return parsed.map(item => `<span class="badge bg-info me-1">${item}</span>`).join('');
            }
        } else if (Array.isArray(field)) {
            if (field.length === 0) {
                return '<span class="text-muted">None</span>';
            }
            return field.map(item => `<span class="badge bg-info me-1">${item}</span>`).join('');
        }
    } catch (e) {
        // If parsing fails, treat as comma-separated string
        if (typeof field === 'string' && field.includes(',')) {
            const items = field.split(',').map(item => item.trim()).filter(item => item);
            if (items.length === 0) {
                return '<span class="text-muted">None</span>';
            }
            return items.map(item => `<span class="badge bg-info me-1">${item}</span>`).join('');
        }
    }
    
    // Fallback: display as is
    return field || '<span class="text-muted">None</span>';
}

function showPreviewRooms() {
    console.log('showPreviewRooms called');
    console.log('importDataRooms:', importDataRooms);
    console.log('importDataRooms length:', importDataRooms ? importDataRooms.length : 'undefined');
    
    const tbody = document.getElementById('previewBody');
    if (!tbody) {
        console.error('previewBody element not found');
        return;
    }
    
    tbody.innerHTML = '';
    const previewRows = importDataRooms.slice(0, 10);
    let validCount = 0;
    
    console.log('Showing preview for rooms:', previewRows);

    previewRows.forEach((row, idx) => {
        const tr = document.createElement('tr');
        tr.className = row.valid ? '' : 'table-danger';

        let validationHtml = '';
        if (row.valid) {
            validationHtml = '<span class="text-success">✓ Valid</span>';
            validCount++;
        } else {
            const isExisting = row.errors && row.errors.some(e => e.toLowerCase().includes('already exists'));
            if (isExisting) {
                validationHtml = '<span class="badge bg-secondary">Skipped (exists)</span>';
            } else {
                validationHtml = '<span class="text-danger">✗ ' + (row.errors ? row.errors.join(', ') : 'Invalid') + '</span>';
            }
        }

        tr.innerHTML = `
            <td>${idx+1}</td>
            <td>${row.name}</td>
            <td>${row.building}</td>
            <td>${row.room_type}</td>
            <td>${row.capacity}</td>


            <td>${row.is_active === '1' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</td>
            <td>${validationHtml}</td>
        `;
        tbody.appendChild(tr);
    });

    const processBtn = document.getElementById('processBtn');
    if (processBtn) {
        if (validCount > 0) {
            processBtn.disabled = false;
            processBtn.textContent = `Process (${validCount})`;
        } else {
            processBtn.disabled = true;
            processBtn.textContent = 'Process File';
        }
    }
}

function processRoomsFile(file) {
    console.log('Processing file:', file.name, 'Size:', file.size, 'Type:', file.type);
    
    const reader = new FileReader();
    reader.onload = function(e) {
        console.log('File loaded, content length:', e.target.result.length);
        console.log('File content preview:', e.target.result.substring(0, 500));
        
        try {
            const data = parseCSVRooms(e.target.result);
            console.log('Parsed CSV data:', data);
            
            if (data && data.length > 0) {
                importDataRooms = validateRoomsData(data);
                console.log('Validated data:', importDataRooms);
                showPreviewRooms();
            } else {
                console.error('No data parsed from CSV');
                alert('No data could be parsed from the CSV file. Please check the file format.');
            }
        } catch (error) {
            console.error('Error processing CSV file:', error);
            alert('Error processing CSV file: ' + error.message);
        }
    };
    
    reader.onerror = function() {
        console.error('Error reading file');
        alert('Error reading the file. Please try again.');
    };
    
    reader.readAsText(file);
}

function processRoomsImport() {
    if (importDataRooms.length === 0) {
        alert('No data to import');
        return;
    }
    
    const validData = importDataRooms.filter(row => row.valid);
    if (validData.length === 0) {
        alert('No valid data to import');
        return;
    }
    
    // Send data to server
    const formData = new FormData();
    formData.append('action', 'bulk_import');
    formData.append('import_data', JSON.stringify(validData));
    
    fetch('rooms.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Reload page to show results
        location.reload();
    })
    .catch(error => {
        console.error('Error importing rooms:', error);
        alert('Error importing rooms. Please try again.');
    });
}

// Set up drag/drop and file input
document.addEventListener('DOMContentLoaded', function() {
    waitForBootstrap(function() {
    // Initialize modals manually to prevent conflicts
    initializeModalsManually();
    
    // Load existing rooms for duplicate checking
    loadExistingRooms();
    
    // Retry loading existing rooms if it fails
    setTimeout(() => {
        if (existingRooms.length === 0) {
            console.log('Retrying to load existing rooms...');
            loadExistingRooms();
        }
    }, 2000);
    
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('csvFile');
    const processBtn = document.getElementById('processBtn');

    uploadArea.addEventListener('click', () => fileInput.click());
    uploadArea.addEventListener('dragover', function(e){ e.preventDefault(); this.style.borderColor='#007bff'; this.style.background='#e3f2fd'; });
    uploadArea.addEventListener('dragleave', function(e){ e.preventDefault(); this.style.borderColor='#ccc'; this.style.background='#f8f9fa'; });
    uploadArea.addEventListener('drop', function(e){ e.preventDefault(); this.style.borderColor='#ccc'; this.style.background='#f8f9fa'; const files = e.dataTransfer.files; if (files.length) { fileInput.files = files; processRoomsFile(files[0]); } });
    fileInput.addEventListener('change', function(){ 
        console.log('File input change event triggered');
        console.log('Files selected:', this.files);
        if (this.files.length) {
            console.log('Processing file:', this.files[0]);
            processRoomsFile(this.files[0]);
        } else {
            console.log('No files selected');
        }
    });

    processBtn.addEventListener('click', function(){
        const validData = importDataRooms.filter(r => r.valid);
        if (validData.length === 0) { alert('No valid records to import'); return; }
        
        console.log('Processing import with data:', validData);
        
        const form = document.createElement('form'); 
        form.method='POST'; 
        form.style.display='none';
        
        const actionInput = document.createElement('input'); 
        actionInput.type='hidden'; 
        actionInput.name='action'; 
        actionInput.value='bulk_import';
        
        const dataInput = document.createElement('input'); 
        dataInput.type='hidden'; 
        dataInput.name='import_data'; 
        dataInput.value = JSON.stringify(validData);
        
        form.appendChild(actionInput); 
        form.appendChild(dataInput); 
        document.body.appendChild(form); 
        form.submit();
    });
});
});

// Add search functionality
document.querySelector('.search-input').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const table = document.getElementById('roomsTable');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
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
    });
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
    if (!ensureBootstrapLoaded()) return;

    var el = document.getElementById('addBuildingModal');
    if (!el) {
        console.error('showAddBuildingModal: addBuildingModal element not found');
        return;
    }

    try {
        var modal = safeModalInit(el);
        if (modal) {
            modal.show();
        }
    } catch (error) {
        console.error('Error showing add building modal:', error);
    }
}

// Bulk Add Rooms Functions
document.addEventListener('DOMContentLoaded', function() {
    waitForBootstrap(function() {
    // Setup bulk add rooms preview
    const bulkInputs = ['room_prefix', 'room_suffix', 'start_number', 'end_number'];
    bulkInputs.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('input', updateBulkPreview);
        }
    });

    // Setup building selection to auto-populate room prefix
    const bulkBuildingSelect = document.getElementById('bulk_building_id');
    if (bulkBuildingSelect) {
        bulkBuildingSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const buildingCode = selectedOption.getAttribute('data-code');

            if (buildingCode) {
                const roomPrefixInput = document.getElementById('room_prefix');
                if (roomPrefixInput) {
                    roomPrefixInput.value = buildingCode;
                    // Trigger preview update
                    updateBulkPreview();
                }
            } else {
                // Clear prefix if no building selected
                const roomPrefixInput = document.getElementById('room_prefix');
                if (roomPrefixInput) {
                    roomPrefixInput.value = '';
                    updateBulkPreview();
                }
            }
        });
    }

    // Setup bulk add rooms modal to reset when opened
    const bulkAddRoomsModal = document.getElementById('bulkAddRoomsModal');
    if (bulkAddRoomsModal) {
        bulkAddRoomsModal.addEventListener('show.bs.modal', function() {
            // Reset form fields
            document.getElementById('bulk_building_id').value = '';
            document.getElementById('bulk_room_type').value = '';
            document.getElementById('bulk_capacity').value = '';
            document.getElementById('room_prefix').value = '';
            document.getElementById('room_suffix').value = '';
            document.getElementById('start_number').value = '';
            document.getElementById('end_number').value = '';
            document.getElementById('bulk_preview').textContent = 'Select a building and enter room range to see preview';
            document.getElementById('bulk_preview').className = '';
        });
    }
    });
});

function updateBulkPreview() {
    const prefix = document.getElementById('room_prefix')?.value || '';
    const suffix = document.getElementById('room_suffix')?.value || '';
    const start = parseInt(document.getElementById('start_number')?.value) || 0;
    const end = parseInt(document.getElementById('end_number')?.value) || 0;
    const previewElement = document.getElementById('bulk_preview');

    if (!prefix || start <= 0 || end <= 0) {
        previewElement.textContent = 'Select a building and enter room range to see preview';
        return;
    }
    
    if (start > end) {
        previewElement.textContent = 'Start number must be less than or equal to end number';
        previewElement.className = 'text-danger';
        return;
    }
    
    const count = end - start + 1;
    if (count > 100) {
        previewElement.textContent = 'Cannot create more than 100 rooms at once';
        previewElement.className = 'text-danger';
        return;
    }
    
    // Show preview of first few and last few rooms
    let preview = '';
    if (count <= 10) {
        // Show all rooms
        const rooms = [];
        for (let i = start; i <= end; i++) {
            rooms.push(`${prefix}${i}${suffix}`);
        }
        preview = rooms.join(', ');
    } else {
        // Show first 3, ..., last 3
        const firstRooms = [];
        const lastRooms = [];
        
        for (let i = start; i < start + 3; i++) {
            firstRooms.push(`${prefix}${i}${suffix}`);
        }
        
        for (let i = end - 2; i <= end; i++) {
            lastRooms.push(`${prefix}${i}${suffix}`);
        }
        
        preview = `${firstRooms.join(', ')}, ..., ${lastRooms.join(', ')} (${count} rooms total)`;
    }
    
    previewElement.textContent = preview;
    previewElement.className = '';
}
</script>