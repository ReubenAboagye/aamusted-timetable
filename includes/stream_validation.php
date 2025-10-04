<?php
/**
 * Stream Validation Helper
 * Ensures that a valid stream is selected before allowing database operations
 */

if (!function_exists('validateStreamSelection')) {
    /**
     * Validate that a stream is properly selected
     * @param mysqli $conn Database connection
     * @param bool $redirect_on_failure Whether to redirect to stream selection page on failure
     * @return array Array with 'valid' => bool, 'stream_id' => int, 'stream_name' => string, 'message' => string
     */
    function validateStreamSelection($conn, $redirect_on_failure = true) {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // ALWAYS prioritize the active stream from database over session data
        $stream_id = null;
        $active_stream_sql = "SELECT id FROM streams WHERE is_active = 1 LIMIT 1";
        $active_result = $conn->query($active_stream_sql);
        if ($active_result && $active_result->num_rows > 0) {
            $active_row = $active_result->fetch_assoc();
            $stream_id = intval($active_row['id']);
            // Update session to match database
            $_SESSION['current_stream_id'] = $stream_id;
            $_SESSION['active_stream'] = $stream_id;
        } else {
            // If no active stream in database, check session as fallback
            if (isset($_SESSION['current_stream_id'])) {
                $stream_id = intval($_SESSION['current_stream_id']);
            } elseif (isset($_SESSION['active_stream'])) {
                $stream_id = intval($_SESSION['active_stream']);
            } elseif (isset($_SESSION['stream_id'])) {
                $stream_id = intval($_SESSION['stream_id']);
            }
        }
        
        // If still no valid stream, get the first available stream
        if (!$stream_id || $stream_id <= 0) {
            $fallback_sql = "SELECT id FROM streams ORDER BY id LIMIT 1";
            $fallback_result = $conn->query($fallback_sql);
            if ($fallback_result && $fallback_result->num_rows > 0) {
                $fallback_row = $fallback_result->fetch_assoc();
                $stream_id = intval($fallback_row['id']);
                // Update session
                $_SESSION['current_stream_id'] = $stream_id;
                $_SESSION['active_stream'] = $stream_id;
            }
        }
        
        // Validate that the stream exists (regardless of active status)
        if ($stream_id && $stream_id > 0) {
            $stream_check_sql = "SELECT id, name, is_active FROM streams WHERE id = ?";
            $stmt = $conn->prepare($stream_check_sql);
            if ($stmt) {
                $stmt->bind_param("i", $stream_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $stream_data = $result->fetch_assoc();
                    $stmt->close();
                    return [
                        'valid' => true,
                        'stream_id' => $stream_id,
                        'stream_name' => $stream_data['name'],
                        'is_active' => $stream_data['is_active'],
                        'message' => 'Stream validation successful'
                    ];
                }
                $stmt->close();
            }
        }
        
        // If we reach here, no valid stream was found
        $message = 'No active stream selected. Please select a stream before performing this action.';
        
        if ($redirect_on_failure) {
            // Set error message and redirect to streams page
            if (function_exists('redirect_with_flash')) {
                redirect_with_flash('streams.php', 'error', $message);
            } else {
                $_SESSION['error_message'] = $message;
                header('Location: streams.php');
                exit;
            }
        }
        
        return [
            'valid' => false,
            'stream_id' => 0,
            'stream_name' => '',
            'message' => $message
        ];
    }
}

if (!function_exists('requireStreamSelection')) {
    /**
     * Require stream selection - throws exception if no valid stream
     * @param mysqli $conn Database connection
     * @throws Exception If no valid stream is selected
     * @return array Array with stream data
     */
    function requireStreamSelection($conn) {
        $validation = validateStreamSelection($conn, false);
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }
        return $validation;
    }
}

if (!function_exists('validateStreamForAjax')) {
    /**
     * Validate stream for AJAX requests
     * @param mysqli $conn Database connection
     * @return void Exits with JSON error if validation fails
     */
    function validateStreamForAjax($conn) {
        $validation = validateStreamSelection($conn, false);
        if (!$validation['valid']) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $validation['message'],
                'redirect' => 'streams.php'
            ]);
            exit;
        }
        return $validation;
    }
}

if (!function_exists('getCurrentStreamInfo')) {
    /**
     * Get current stream information safely
     * @param mysqli $conn Database connection
     * @return array Stream information
     */
    function getCurrentStreamInfo($conn) {
        return validateStreamSelection($conn, false);
    }
}
?>
