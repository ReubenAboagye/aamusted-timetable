<?php
// Start session
session_start();

// Include database connection
include 'connect.php';

// Include stream manager
include 'includes/stream_manager.php';

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stream_id = isset($_POST['stream_id']) ? intval($_POST['stream_id']) : 0;
    
    if ($stream_id > 0) {
        $streamManager = getStreamManager();
        
        if ($streamManager->setCurrentStream($stream_id)) {
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Stream changed successfully',
                'stream_id' => $stream_id,
                'stream_name' => $streamManager->getCurrentStreamName()
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to change stream'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid stream ID'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
