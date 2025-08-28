<?php
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

// Database connection
include 'connect.php';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Debug: Log all POST data
    error_log("POST action: " . $_POST['action']);
    if (isset($_POST['room_type'])) {
        error_log("Raw room_type from POST: '" . $_POST['room_type'] . "'");
        error_log("Raw room_type length: " . strlen($_POST['room_type']));
        error_log("Raw room_type ASCII: " . implode(',', array_map('ord', str_split($_POST['room_type']))));
    }

    // Bulk import
    if ($_POST['action'] === 'bulk_import' && isset($_POST['import_data'])) {
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
                $building = isset($row['building']) ? trim($conn->real_escape_string($row['building'])) : '';
                $room_type = isset($row['room_type']) ? trim($row['room_type']) : 'classroom';
                $capacity = isset($row['capacity']) ? (int)$row['capacity'] : 30;

                // Debug: Log the raw CSV data
                error_log("Bulk Import - Processing row: name='$name', building='$building', room_type='$room_type', capacity=$capacity");
                error_log("Bulk Import - Raw facilities: '" . (isset($row['facilities']) ? $row['facilities'] : 'NOT_SET') . "'");
                error_log("Bulk Import - Raw accessibility_features: '" . (isset($row['accessibility_features']) ? $row['accessibility_features'] : 'NOT_SET') . "'");

                // Smart parsing for CSV format fields
                $stream_availability = '["regular"]';
                if (isset($row['stream_availability']) && !empty($row['stream_availability'])) {
                    $sa_raw = trim($row['stream_availability']);
                    if (strpos($sa_raw, ',') !== false) {
                        $sa_parts = array_map('trim', str_getcsv($sa_raw));
                        $sa_clean = [];
                        foreach ($sa_parts as $part) {
                            $part_lower = strtolower($part);
                            if (in_array($part_lower, ['regular', 'evening', 'weekend'])) {
                                $sa_clean[] = $part_lower;
                            }
                        }
                        if (!empty($sa_clean)) {
                            $stream_availability = json_encode($sa_clean);
                        }
                    } else {
                        $sa_lower = strtolower($sa_raw);
                        if (in_array($sa_lower, ['regular', 'evening', 'weekend'])) {
                            $stream_availability = json_encode([$sa_lower]);
                        }
                    }
                }

                $facilities = '[]';
                if (isset($row['facilities'])) {
                    $fac_raw = trim($row['facilities']);
                    error_log("Bulk Import - Trimmed facilities: '$fac_raw'");
                    if (!empty($fac_raw)) {
                        if (strpos($fac_raw, ',') !== false) {
                            $fac_parts = array_map('trim', str_getcsv($fac_raw));
                            $fac_clean = [];
                            foreach ($fac_parts as $part) {
                                $part_lower = strtolower($part);
                                if (in_array($part_lower, ['projector', 'whiteboard', 'computer', 'audio_system', 'air_conditioning'])) {
                                    $fac_clean[] = $part_lower;
                                }
                            }
                            if (!empty($fac_clean)) {
                                $facilities = json_encode($fac_clean);
                            }
                        } else {
                            $fac_lower = strtolower($fac_raw);
                            if (in_array($fac_lower, ['projector', 'whiteboard', 'computer', 'audio_system', 'air_conditioning'])) {
                                $facilities = json_encode([$fac_lower]);
                            }
                        }
                    }
                }
                error_log("Bulk Import - Final facilities: $facilities");

                $accessibility_features = '[]';
                if (isset($row['accessibility_features'])) {
                    $acc_raw = trim($row['accessibility_features']);
                    error_log("Bulk Import - Trimmed accessibility_features: '$acc_raw'");
                    if (!empty($acc_raw)) {
                        if (strtolower($acc_raw) === 'none') {
                            $accessibility_features = '[]';
                        } else {
                            if (strpos($acc_raw, ',') !== false) {
                                $acc_parts = array_map('trim', str_getcsv($acc_raw));
                                $acc_clean = [];
                                foreach ($acc_parts as $part) {
                                    $part_lower = strtolower($part);
                                    if (in_array($part_lower, ['wheelchair_access', 'elevator', 'ramp'])) {
                                        $acc_clean[] = $part_lower;
                                    }
                                }
                                if (!empty($acc_clean)) {
                                    $accessibility_features = json_encode($acc_clean);
                                }
                            } else {
                                $acc_lower = strtolower($acc_raw);
                                if (in_array($acc_lower, ['wheelchair_access', 'elevator', 'ramp'])) {
                                    $accessibility_features = json_encode([$acc_lower]);
                                }
                            }
                        }
                    }
                }
                error_log("Bulk Import - Final accessibility_features: $accessibility_features");
                $is_active = isset($row['is_active']) ? (int)(strtolower(trim($row['is_active'])) === '1' || strtolower(trim($row['is_active'])) === 'true') : 1;

                if ($name === '' || $building === '') {
                    error_log("ERROR: Bulk Import - Missing name or building");
                    $error_count++;
                    continue;
                }

                // Validate JSON fields
                foreach (['stream_availability', 'facilities', 'accessibility_features'] as $json_field) {
                    if (empty($$json_field) || !json_decode($$json_field, true)) {
                        error_log("ERROR: Bulk Import - Invalid JSON in $json_field: " . $$json_field);
                        $$json_field = ($json_field === 'stream_availability') ? '["regular"]' : '[]';
                    }
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

                $sql = "INSERT INTO rooms (name, building, room_type, capacity, stream_availability, facilities, accessibility_features, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    error_log("ERROR: Bulk Import - Prepare failed: " . $conn->error);
                    $error_count++;
                    continue;
                }

                error_log("Bulk Import - Binding: name='$name', building='$building', room_type='$db_room_type', capacity=$capacity, stream_availability='$stream_availability', facilities='$facilities', accessibility_features='$accessibility_features', is_active=$is_active");

                $stmt->bind_param("ssissssi", $name, $building, $db_room_type, $capacity, $stream_availability, $facilities, $accessibility_features, $is_active);
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    error_log("ERROR: Bulk Import - Insert failed: " . $stmt->error);
                    error_log("ERROR: Bulk Import - Failed values: name='$name', building='$building', room_type='$db_room_type', capacity=$capacity, stream_availability='$stream_availability', facilities='$facilities', accessibility_features='$accessibility_features', is_active=$is_active");
                    $error_count++;
                }
                $stmt->close();
            }

            if ($check_stmt) $check_stmt->close();

            if ($success_count > 0) {
                $success_message = "Successfully imported $success_count rooms!";
                if ($ignored_count > 0) $success_message .= " $ignored_count duplicates ignored.";
                if ($error_count > 0) $success_message .= " $error_count records failed to import.";
            } else {
                if ($ignored_count > 0 && $error_count === 0) {
                    $success_message = "No new rooms imported. $ignored_count duplicates ignored.";
                } else {
                    $error_message = "No rooms were imported. Please check your data.";
                }
            }
        }

    // Single add
    } elseif ($_POST['action'] === 'add') {
        // Debug: Log all POST data
        error_log("Single Add - POST data received: " . json_encode($_POST));
        error_log("Single Add - Raw room_type value: '" . $_POST['room_type'] . "'");
        error_log("Single Add - Raw room_type length: " . strlen($_POST['room_type']));
        
        $name = trim($conn->real_escape_string($_POST['name']));
        $building = trim($conn->real_escape_string($_POST['building']));
        $room_type = trim($conn->real_escape_string($_POST['room_type']));
        $valid_form_room_types = ['Classroom', 'Lecture Hall', 'Laboratory', 'Computer Lab', 'Seminar Room', 'Auditorium'];

        error_log("Single Add - After trim room_type: '$room_type'");
        error_log("Single Add - Valid form types: " . json_encode($valid_form_room_types));

        if (!in_array($room_type, $valid_form_room_types)) {
            error_log("ERROR: Single Add - Invalid room type: '$room_type'");
            $error_message = "Invalid room type selected. Please choose a valid room type from the dropdown.";
        } else {
            $capacity = (int)$_POST['capacity'];
            $stream_availability = isset($_POST['stream_availability']) ? json_encode($_POST['stream_availability']) : '["regular"]';
            $facilities = isset($_POST['facilities']) ? json_encode($_POST['facilities']) : '[]';
            $accessibility_features = isset($_POST['accessibility_features']) ? json_encode($_POST['accessibility_features']) : '[]';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Validate JSON fields
            foreach (['stream_availability', 'facilities', 'accessibility_features'] as $json_field) {
                if (empty($$json_field) || !json_decode($$json_field, true)) {
                    error_log("ERROR: Single Add - Invalid JSON in $json_field: " . $$json_field);
                    $$json_field = ($json_field === 'stream_availability') ? '["regular"]' : '[]';
                }
            }

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
                    error_log("  capacity: $capacity");
                    error_log("  stream_availability: '$stream_availability'");
                    error_log("  facilities: '$facilities'");
                    error_log("  accessibility_features: '$accessibility_features'");
                    error_log("  is_active: $is_active");
                    
                    $sql = "INSERT INTO rooms (name, building, room_type, capacity, stream_availability, facilities, accessibility_features, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssissssi", $name, $building, $db_room_type, $capacity, $stream_availability, $facilities, $accessibility_features, $is_active);
                        error_log("Single Add - Binding: name='$name', building='$building', room_type='$db_room_type', capacity=$capacity, stream_availability='$stream_availability', facilities='$facilities', accessibility_features='$accessibility_features', is_active=$is_active");

                        if ($stmt->execute()) {
                            $success_message = "Room added successfully!";
                        } else {
                            error_log("ERROR: Single Add - Insert failed: " . $stmt->error);
                            error_log("ERROR: Single Add - Failed values: name='$name', building='$building', room_type='$db_room_type', capacity=$capacity, stream_availability='$stream_availability', facilities='$facilities', accessibility_features='$accessibility_features', is_active=$is_active");
                            $error_message = "Error adding room: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $error_message = "Error preparing statement: " . $conn->error;
                    }
                }
            }
        }

    // Edit
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = trim($conn->real_escape_string($_POST['name']));
        $building = trim($conn->real_escape_string($_POST['building']));
        $room_type = trim($conn->real_escape_string($_POST['room_type']));
        $valid_form_room_types = ['Classroom', 'Lecture Hall', 'Laboratory', 'Computer Lab', 'Seminar Room', 'Auditorium'];

        if (!in_array($room_type, $valid_form_room_types)) {
            error_log("ERROR: Edit - Invalid room type: '$room_type'");
            $error_message = "Invalid room type selected. Please choose a valid room type from the dropdown.";
        } else {
            $capacity = (int)$_POST['capacity'];
            $stream_availability = isset($_POST['stream_availability']) ? json_encode($_POST['stream_availability']) : '["regular"]';
            $facilities = isset($_POST['facilities']) ? json_encode($_POST['facilities']) : '[]';
            $accessibility_features = isset($_POST['accessibility_features']) ? json_encode($_POST['accessibility_features']) : '[]';
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Validate JSON fields
            foreach (['stream_availability', 'facilities', 'accessibility_features'] as $json_field) {
                if (empty($$json_field) || !json_decode($$json_field, true)) {
                    error_log("ERROR: Edit - Invalid JSON in $json_field: " . $$json_field);
                    $$json_field = ($json_field === 'stream_availability') ? '["regular"]' : '[]';
                }
            }

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
                    $sql = "UPDATE rooms SET name = ?, building = ?, room_type = ?, capacity = ?, stream_availability = ?, facilities = ?, accessibility_features = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssisssii", $name, $building, $db_room_type, $capacity, $stream_availability, $facilities, $accessibility_features, $is_active, $id);
                        error_log("Edit - Binding: name='$name', building='$building', room_type='$db_room_type', capacity=$capacity, stream_availability='$stream_availability', facilities='$facilities', accessibility_features='$accessibility_features', is_active=$is_active, id=$id");

                        if ($stmt->execute()) {
                            $success_message = "Room updated successfully!";
                        } else {
                            error_log("ERROR: Edit - Update failed: " . $stmt->error);
                            error_log("ERROR: Edit - Failed values: name='$name', building='$building', room_type='$db_room_type', capacity=$capacity, stream_availability='$stream_availability', facilities='$facilities', accessibility_features='$accessibility_features', is_active=$is_active, id=$id");
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
    } elseif ($_POST['action'] === 'bulk_edit' && isset($_POST['room_ids'])) {
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
                
                // Stream Availability
                if (isset($_POST['stream_availability']) && is_array($_POST['stream_availability'])) {
                    $stream_availability = json_encode($_POST['stream_availability']);
                    $update_fields[] = "stream_availability = ?";
                    $update_values[] = $stream_availability;
                    $update_types .= "s";
                }
                
                // Facilities
                if (isset($_POST['facilities']) && is_array($_POST['facilities'])) {
                    $facilities = json_encode($_POST['facilities']);
                    $update_fields[] = "facilities = ?";
                    $update_values[] = $facilities;
                    $update_types .= "s";
                }
                
                // Accessibility Features
                if (isset($_POST['accessibility_features']) && is_array($_POST['accessibility_features'])) {
                    $accessibility_features = json_encode($_POST['accessibility_features']);
                    $update_fields[] = "accessibility_features = ?";
                    $update_values[] = $accessibility_features;
                    $update_types .= "s";
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
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE rooms SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Room deleted.";
        } else {
            error_log("ERROR: Delete - Update failed: " . $stmt->error);
            $error_message = "Error deleting room: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch rooms with all fields from schema
$sql = "SELECT id, name, building, room_type, capacity, stream_availability, facilities, accessibility_features, is_active, created_at, updated_at FROM rooms WHERE is_active = 1 ORDER BY building, name";
$result = $conn->query($sql);
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-door-open me-2"></i>Rooms Management</h4>
            <div class="d-flex gap-2">
                <button class="btn btn-warning" id="bulkEditBtn" data-bs-toggle="modal" data-bs-target="#bulkEditModal" disabled>
                    <i class="fas fa-edit me-2"></i>Bulk Edit (<span id="selectedCount">0</span>)
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fas fa-upload me-2"></i>Import
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
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
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
                        <th>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllRooms">
                                <label class="form-check-label" for="selectAllRooms">All</label>
                            </div>
                        </th>
                        <th>Room Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Building</th>
                        <th>Stream Availability</th>
                        <th>Facilities</th>
                        <th>Accessibility</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input room-checkbox" type="checkbox" value="<?php echo $row['id']; ?>">
                                    </div>
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
                                <td><?php echo htmlspecialchars($row['building']); ?></td>
                                <td>
                                    <?php 
                                    $stream_avail = json_decode($row['stream_availability'], true);
                                    if ($stream_avail && is_array($stream_avail)) {
                                        foreach ($stream_avail as $avail) {
                                            echo '<span class="badge bg-info me-1">' . htmlspecialchars(ucfirst($avail)) . '</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $facilities = json_decode($row['facilities'], true);
                                    if ($facilities && is_array($facilities)) {
                                        foreach ($facilities as $facility) {
                                            echo '<span class="badge bg-success me-1">' . htmlspecialchars(ucfirst($facility)) . '</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $accessibility = json_decode($row['accessibility_features'], true);
                                    if ($accessibility && is_array($accessibility)) {
                                        foreach ($accessibility as $feature) {
                                            echo '<span class="badge bg-warning me-1">' . htmlspecialchars(ucwords(str_replace('_', ' ', $feature))) . '</span>';
                                        }
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge bg-success">Available</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unavailable</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editRoom(<?php echo $row['id']; ?>, <?php echo json_encode($row['name']); ?>, <?php echo json_encode($row['building']); ?>, <?php echo json_encode(ucwords(str_replace('_', ' ', $row['room_type']))); ?>, <?php echo (int)$row['capacity']; ?>, <?php echo json_encode($row['stream_availability']); ?>, <?php echo json_encode($row['facilities']); ?>, <?php echo json_encode($row['accessibility_features']); ?>, <?php echo $row['is_active']; ?>)">
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
                            <td colspan="9" class="empty-state">
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
                                <label for="building" class="form-label">Building *</label>
                                <input type="text" class="form-control" id="building" name="building" required>
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
                        <label class="form-label">Stream Availability *</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stream_availability[]" value="regular" id="sa_regular" checked>
                                    <label class="form-check-label" for="sa_regular">Regular</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stream_availability[]" value="evening" id="sa_evening">
                                    <label class="form-check-label" for="sa_evening">Evening</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stream_availability[]" value="weekend" id="sa_weekend">
                                    <label class="form-check-label" for="sa_weekend">Weekend</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Facilities</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="projector" id="fac_projector">
                                    <label class="form-check-label" for="fac_projector">Projector</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="whiteboard" id="fac_whiteboard">
                                    <label class="form-check-label" for="fac_whiteboard">Whiteboard</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="computer" id="fac_computer">
                                    <label class="form-check-label" for="fac_computer">Computer</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="audio_system" id="fac_audio">
                                    <label class="form-check-label" for="fac_audio">Audio System</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="air_conditioning" id="fac_ac">
                                    <label class="form-check-label" for="fac_ac">Air Conditioning</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Accessibility Features</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accessibility_features[]" value="wheelchair_access" id="acc_wheelchair">
                                    <label class="form-check-label" for="acc_wheelchair">Wheelchair Access</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accessibility_features[]" value="elevator" id="acc_elevator">
                                    <label class="form-check-label" for="acc_elevator">Elevator</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accessibility_features[]" value="ramp" id="acc_ramp">
                                    <label class="form-check-label" for="acc_ramp">Ramp</label>
                                </div>
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
                                <label for="edit_building" class="form-label">Building *</label>
                                <input type="text" class="form-control" id="edit_building" name="building" required>
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
                        <label class="form-label">Stream Availability *</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stream_availability[]" value="regular" id="edit_sa_regular">
                                    <label class="form-check-label" for="edit_sa_regular">Regular</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stream_availability[]" value="evening" id="edit_sa_evening">
                                    <label class="form-check-label" for="edit_sa_evening">Evening</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stream_availability[]" value="weekend" id="edit_sa_weekend">
                                    <label class="form-check-label" for="edit_sa_weekend">Weekend</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Facilities</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="projector" id="edit_fac_projector">
                                    <label class="form-check-label" for="edit_fac_projector">Projector</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="whiteboard" id="edit_fac_whiteboard">
                                    <label class="form-check-label" for="edit_fac_whiteboard">Whiteboard</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="computer" id="edit_fac_computer">
                                    <label class="form-check-label" for="edit_fac_computer">Computer</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="audio_system" id="edit_fac_audio">
                                    <label class="form-check-label" for="edit_fac_audio">Audio System</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="air_conditioning" id="edit_fac_ac">
                                    <label class="form-check-label" for="edit_fac_ac">Air Conditioning</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Accessibility Features</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accessibility_features[]" value="wheelchair_access" id="edit_acc_wheelchair">
                                    <label class="form-check-label" for="edit_acc_wheelchair">Wheelchair Access</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accessibility_features[]" value="elevator" id="edit_acc_elevator">
                                    <label class="form-check-label" for="edit_acc_elevator">Elevator</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accessibility_features[]" value="ramp" id="edit_acc_ramp">
                                    <label class="form-check-label" for="edit_acc_ramp">Ramp</label>
                                </div>
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
                        <small class="text-muted">Supported format: CSV with headers: name,building,room_type,capacity,stream_availability,facilities,accessibility_features,is_active</small>
                        <br><small class="text-muted">Room types: classroom, lecture_hall, laboratory, computer_lab, seminar_room, auditorium</small>
                        <br><small class="text-muted">Stream availability: "regular, evening, weekend" (comma-separated)</small>
                        <br><small class="text-muted">Facilities: "projector, whiteboard" (comma-separated)</small>
                        <br><small class="text-muted">Accessibility: "wheelchair_access, elevator, ramp" or "none" (comma-separated)</small>
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
                                    <th>Stream Availability</th>
                                    <th>Facilities</th>
                                    <th>Accessibility</th>
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
                        <label class="form-label">Stream Availability</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="bulk_edit_stream_availability_check">
                            <label class="form-check-label" for="bulk_edit_stream_availability_check">Update stream availability</label>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stream_availability[]" value="regular" id="bulk_sa_regular" disabled>
                                    <label class="form-check-label" for="bulk_sa_regular">Regular</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stream_availability[]" value="evening" id="bulk_sa_evening" disabled>
                                    <label class="form-check-label" for="bulk_sa_evening">Evening</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="stream_availability[]" value="weekend" id="bulk_sa_weekend" disabled>
                                    <label class="form-check-label" for="bulk_sa_weekend">Weekend</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Facilities</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="bulk_edit_facilities_check">
                            <label class="form-check-label" for="bulk_edit_facilities_check">Update facilities</label>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="projector" id="bulk_fac_projector" disabled>
                                    <label class="form-check-label" for="bulk_fac_projector">Projector</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="whiteboard" id="bulk_fac_whiteboard" disabled>
                                    <label class="form-check-label" for="bulk_fac_whiteboard">Whiteboard</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="computer" id="bulk_fac_computer" disabled>
                                    <label class="form-check-label" for="bulk_fac_computer">Computer</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="audio_system" id="bulk_fac_audio" disabled>
                                    <label class="form-check-label" for="bulk_fac_audio">Audio System</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="facilities[]" value="air_conditioning" id="bulk_fac_ac" disabled>
                                    <label class="form-check-label" for="bulk_fac_ac">Air Conditioning</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Accessibility Features</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="bulk_edit_accessibility_check">
                            <label class="form-check-label" for="bulk_edit_accessibility_check">Update accessibility features</label>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accessibility_features[]" value="wheelchair_access" id="bulk_acc_wheelchair" disabled>
                                    <label class="form-check-label" for="bulk_acc_wheelchair">Wheelchair Access</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accessibility_features[]" value="elevator" id="bulk_acc_elevator" disabled>
                                    <label class="form-check-label" for="bulk_acc_elevator">Elevator</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="accessibility_features[]" value="ramp" id="bulk_acc_ramp" disabled>
                                    <label class="form-check-label" for="bulk_acc_ramp">Ramp</label>
                                </div>
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
function editRoom(id, name, building, roomType, capacity, sessionAvailability, facilities, accessibilityFeatures, isActive) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_building').value = building;
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
    
    // Set stream availability checkboxes
    const saCheckboxes = document.querySelectorAll('#editRoomModal input[name="stream_availability[]"]');
    saCheckboxes.forEach(checkbox => checkbox.checked = false);
    try {
        const saData = JSON.parse(sessionAvailability);
        if (Array.isArray(saData)) {
            saData.forEach(avail => {
                const checkbox = document.querySelector(`#editRoomModal input[value="${avail}"]`);
                if (checkbox) checkbox.checked = true;
            });
        }
    } catch (e) {
        console.error('Error parsing stream_availability:', e);
    }
    
    // Set facilities checkboxes
    const facCheckboxes = document.querySelectorAll('#editRoomModal input[name="facilities[]"]');
    facCheckboxes.forEach(checkbox => checkbox.checked = false);
    try {
        const facData = JSON.parse(facilities);
        if (Array.isArray(facData)) {
            facData.forEach(facility => {
                const checkbox = document.querySelector(`#editRoomModal input[value="${facility}"]`);
                if (checkbox) checkbox.checked = true;
            });
        }
    } catch (e) {
        console.error('Error parsing facilities:', e);
    }
    
    // Set accessibility features checkboxes
    const accCheckboxes = document.querySelectorAll('#editRoomModal input[name="accessibility_features[]"]');
    accCheckboxes.forEach(checkbox => checkbox.checked = false);
    try {
        const accData = JSON.parse(accessibilityFeatures);
        if (Array.isArray(accData)) {
            accData.forEach(feature => {
                const checkbox = document.querySelector(`#editRoomModal input[value="${feature}"]`);
                if (checkbox) checkbox.checked = true;
            });
        }
    } catch (e) {
        console.error('Error parsing accessibility_features:', e);
    }
    
    // Set is_active checkbox
    document.getElementById('edit_is_active').checked = !!isActive;

    var editModal = new bootstrap.Modal(document.getElementById('editRoomModal'));
    editModal.show();
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
    console.log('Expected headers: name, building, room_type, capacity, stream_availability, facilities, accessibility_features, is_active');
    
    // Check if headers match expected format
    const expectedHeaders = ['name', 'building', 'room_type', 'capacity', 'stream_availability', 'facilities', 'accessibility_features', 'is_active'];
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
            building: row.building || row.Building || '',
            room_type: row.room_type || row.roomType || row.room_type || 'classroom',
            capacity: row.capacity || row.Capacity || '30',
            stream_availability: row.stream_availability || row.streamAvailability || 'regular',
            facilities: row.facilities || row.Facilities || '',
            accessibility_features: row.accessibility_features || row.accessibilityFeatures || row.accessibility_features || '',
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

        // Validate stream_availability
        let sa_clean = ['regular'];
        if (validated.stream_availability) {
            const sa_parts = validated.stream_availability.split(',').map(p => p.trim().toLowerCase());
            sa_clean = sa_parts.filter(p => ['regular', 'evening', 'weekend'].includes(p));
            if (sa_clean.length === 0) {
                sa_clean = ['regular'];
            }
        }
        validated.stream_availability = JSON.stringify(sa_clean);

        // Validate facilities
        let fac_clean = [];
        if (validated.facilities) {
            const fac_parts = validated.facilities.split(',').map(p => p.trim().toLowerCase());
            fac_clean = fac_parts.filter(p => ['projector', 'whiteboard', 'computer', 'audio_system', 'air_conditioning'].includes(p));
        }
        validated.facilities = JSON.stringify(fac_clean);

        // Validate accessibility_features
        let acc_clean = [];
        if (validated.accessibility_features && validated.accessibility_features.toLowerCase() !== 'none') {
            const acc_parts = validated.accessibility_features.split(',').map(p => p.trim().toLowerCase());
            acc_clean = acc_parts.filter(p => ['wheelchair_access', 'elevator', 'ramp'].includes(p));
        }
        validated.accessibility_features = JSON.stringify(acc_clean);

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
            <td>${formatArrayField(row.stream_availability)}</td>
            <td>${formatArrayField(row.facilities)}</td>
            <td>${formatArrayField(row.accessibility_features)}</td>
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

// Enhanced form validation for room type
document.addEventListener('DOMContentLoaded', function() {
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
        { id: 'bulk_edit_stream_availability_check', targets: ['bulk_sa_regular', 'bulk_sa_evening', 'bulk_sa_weekend'] },
        { id: 'bulk_edit_facilities_check', targets: ['bulk_fac_projector', 'bulk_fac_whiteboard', 'bulk_fac_computer', 'bulk_fac_audio', 'bulk_fac_ac'] },
        { id: 'bulk_edit_accessibility_check', targets: ['bulk_acc_wheelchair', 'bulk_acc_elevator', 'bulk_acc_ramp'] },
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
</script>