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

        // Activate the selected stream in the database and deactivate others
        try {
            $conn->begin_transaction();
            $deact = $conn->prepare("UPDATE streams SET is_active = 0, updated_at = NOW() WHERE is_active = 1");
            $deact->execute();
            $deact->close();

            $act = $conn->prepare("UPDATE streams SET is_active = 1, updated_at = NOW() WHERE id = ?");
            $act->bind_param('i', $stream_id);
            $act->execute();
            $act->close();

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Database error while activating stream']);
            exit;
        }

        // Update session/current stream
        if ($streamManager->setCurrentStream($stream_id)) {
            echo json_encode([
                'success' => true,
                'message' => 'Stream activated and switched successfully',
                'stream_id' => $stream_id,
                'stream_name' => $streamManager->getCurrentStreamName()
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to set current stream in session'
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
