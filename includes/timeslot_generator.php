<?php
/**
 * Time Slot Generator Utility
 * Generates hourly time slots based on stream periods
 */

/**
 * Generate time slots for a stream based on its period start/end times
 * 
 * @param mysqli $conn Database connection
 * @param int $stream_id Stream ID
 * @param string $period_start Start time (HH:MM:SS format)
 * @param string $period_end End time (HH:MM:SS format)
 * @param string|null $break_start Break start time (optional)
 * @param string|null $break_end Break end time (optional)
 * @return array Result array with success status and message
 */
function generateTimeSlots($conn, $stream_id, $period_start, $period_end, $break_start = null, $break_end = null) {
    try {
        // Validate inputs
        if (empty($period_start) || empty($period_end)) {
            return ['success' => false, 'message' => 'Period start and end times are required'];
        }

        // Convert time strings to DateTime objects for easier manipulation
        $start = new DateTime($period_start);
        $end = new DateTime($period_end);
        
        if ($start >= $end) {
            return ['success' => false, 'message' => 'Period start time must be before end time'];
        }

        // Handle break times if provided
        $break_start_obj = null;
        $break_end_obj = null;
        if (!empty($break_start) && !empty($break_end)) {
            $break_start_obj = new DateTime($break_start);
            $break_end_obj = new DateTime($break_end);
            
            if ($break_start_obj >= $break_end_obj) {
                return ['success' => false, 'message' => 'Break start time must be before break end time'];
            }
        }

        // Clear existing time slots for this stream
        $clear_sql = "DELETE FROM time_slots WHERE stream_id = ?";
        $clear_stmt = $conn->prepare($clear_sql);
        $clear_stmt->bind_param("i", $stream_id);
        $clear_stmt->execute();
        $clear_stmt->close();

        // Generate hourly slots
        $current = clone $start;
        $slots_created = 0;
        $insert_sql = "INSERT INTO time_slots (start_time, end_time, duration, is_break, is_mandatory, stream_id) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);

        while ($current < $end) {
            $slot_start = $current->format('H:i:s');
            $current->add(new DateInterval('PT1H')); // Add 1 hour
            $slot_end = $current->format('H:i:s');
            
            // Don't create a slot that goes beyond the period end
            if ($current > $end) {
                $slot_end = $end->format('H:i:s');
            }

            // Calculate duration in minutes
            $slot_start_time = new DateTime($slot_start);
            $slot_end_time = new DateTime($slot_end);
            $duration = ($slot_end_time->getTimestamp() - $slot_start_time->getTimestamp()) / 60;

            // Check if this slot overlaps with break time
            $is_break = 0;
            $is_mandatory = 1; // Default to mandatory
            
            if ($break_start_obj && $break_end_obj) {
                $slot_start_obj = new DateTime($slot_start);
                $slot_end_obj = new DateTime($slot_end);
                
                // Check if slot overlaps with break period
                if (($slot_start_obj < $break_end_obj) && ($slot_end_obj > $break_start_obj)) {
                    $is_break = 1;
                    $is_mandatory = 0; // Break slots are not mandatory for scheduling
                }
            }

            // Insert the time slot
            $insert_stmt->bind_param("ssiiii", $slot_start, $slot_end, $duration, $is_break, $is_mandatory, $stream_id);
            
            if ($insert_stmt->execute()) {
                $slots_created++;
            } else {
                error_log("Failed to create time slot: " . $conn->error);
            }

            // Safety check to prevent infinite loops
            if ($slots_created > 24) {
                break;
            }
        }

        $insert_stmt->close();

        if ($slots_created > 0) {
            return [
                'success' => true, 
                'message' => "Successfully generated {$slots_created} time slots for the stream",
                'slots_created' => $slots_created
            ];
        } else {
            return ['success' => false, 'message' => 'No time slots were created'];
        }

    } catch (Exception $e) {
        error_log("Time slot generation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error generating time slots: ' . $e->getMessage()];
    }
}

/**
 * Regenerate time slots for an existing stream
 * 
 * @param mysqli $conn Database connection
 * @param int $stream_id Stream ID
 * @return array Result array with success status and message
 */
function regenerateTimeSlots($conn, $stream_id) {
    // Get stream details
    $stream_sql = "SELECT period_start, period_end, break_start, break_end FROM streams WHERE id = ? AND is_active = 1";
    $stream_stmt = $conn->prepare($stream_sql);
    $stream_stmt->bind_param("i", $stream_id);
    $stream_stmt->execute();
    $result = $stream_stmt->get_result();
    
    if ($stream = $result->fetch_assoc()) {
        $stream_stmt->close();
        return generateTimeSlots(
            $conn, 
            $stream_id, 
            $stream['period_start'], 
            $stream['period_end'], 
            $stream['break_start'], 
            $stream['break_end']
        );
    } else {
        $stream_stmt->close();
        return ['success' => false, 'message' => 'Stream not found or inactive'];
    }
}

/**
 * Get time slots for a specific stream
 * 
 * @param mysqli $conn Database connection
 * @param int $stream_id Stream ID
 * @return array Array of time slots
 */
function getStreamTimeSlots($conn, $stream_id) {
    $sql = "SELECT id, start_time, end_time, duration, is_break, is_mandatory 
            FROM time_slots 
            WHERE stream_id = ? 
            ORDER BY start_time";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $stream_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $slots = [];
    while ($row = $result->fetch_assoc()) {
        $slots[] = $row;
    }
    
    $stmt->close();
    return $slots;
}
?>
