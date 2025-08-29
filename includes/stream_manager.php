<?php
/**
 * Stream Manager - Handles stream selection and filtering throughout the application
 */

class StreamManager {
    private $conn;
    private $current_stream_id;
    private $current_stream_name;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->initializeStream();
    }
    
    /**
     * Initialize the current stream based on session or default
     */
    private function initializeStream() {
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if stream is set in session
        if (isset($_SESSION['current_stream_id'])) {
            $this->current_stream_id = $_SESSION['current_stream_id'];
        } else {
            // Set default stream (Regular)
            $this->current_stream_id = 1;
            $_SESSION['current_stream_id'] = $this->current_stream_id;
        }
        
        // Get stream name
        $this->loadStreamName();
    }
    
    /**
     * Load the current stream name
     */
    private function loadStreamName() {
        $sql = "SELECT name FROM streams WHERE id = ? AND is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->current_stream_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->current_stream_name = $row['name'];
        } else {
            $this->current_stream_name = "Regular";
        }
        $stmt->close();
    }
    
    /**
     * Get current stream ID
     */
    public function getCurrentStreamId() {
        return $this->current_stream_id;
    }
    
    /**
     * Get current stream name
     */
    public function getCurrentStreamName() {
        return $this->current_stream_name;
    }
    
    /**
     * Set current stream
     */
    public function setCurrentStream($stream_id) {
        if (is_numeric($stream_id)) {
            $this->current_stream_id = $stream_id;
            $_SESSION['current_stream_id'] = $stream_id;
            $this->loadStreamName();
            return true;
        }
        return false;
    }
    
    /**
     * Get all available streams
     */
    public function getAllStreams() {
        $sql = "SELECT id, name, code FROM streams WHERE is_active = 1 ORDER BY name";
        $result = $this->conn->query($sql);
        $streams = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $streams[] = $row;
            }
        }
        
        return $streams;
    }
    
    /**
     * Add stream filter to SQL query
     */
    public function addStreamFilter($sql, $table_alias = '') {
        $alias = $table_alias ? $table_alias . '.' : '';
        
        if (strpos($sql, 'WHERE') !== false) {
            $sql .= " AND {$alias}stream_id = " . $this->current_stream_id;
        } else {
            $sql .= " WHERE {$alias}stream_id = " . $this->current_stream_id;
        }
        
        return $sql;
    }
    
    /**
     * Get stream filter condition for manual queries
     */
    public function getStreamFilterCondition($table_alias = '') {
        $alias = $table_alias ? $table_alias . '.' : '';
        return "{$alias}stream_id = " . $this->current_stream_id;
    }
    
    /**
     * Check if a record belongs to current stream
     */
    public function isRecordInCurrentStream($table_name, $record_id) {
        $sql = "SELECT stream_id FROM {$table_name} WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $record_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['stream_id'] == $this->current_stream_id;
        }
        
        $stmt->close();
        return false;
    }
    
    /**
     * Get stream selector HTML
     */
    public function getStreamSelector() {
        $streams = $this->getAllStreams();
        $html = '<div class="stream-selector">';
        $html .= '<label for="streamSelect" class="form-label">Current Stream:</label>';
        $html .= '<select id="streamSelect" class="form-select form-select-sm" onchange="changeStream(this.value)">';
        
        foreach ($streams as $stream) {
            $selected = ($stream['id'] == $this->current_stream_id) ? 'selected' : '';
            $html .= '<option value="' . $stream['id'] . '" ' . $selected . '>' . $stream['name'] . '</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get stream badge HTML
     */
    public function getStreamBadge() {
        return '<span class="badge bg-primary">' . htmlspecialchars($this->current_stream_name) . '</span>';
    }
}

/**
 * Global function to get stream manager instance
 */
function getStreamManager() {
    global $conn;
    static $streamManager = null;
    
    if ($streamManager === null) {
        $streamManager = new StreamManager($conn);
    }
    
    return $streamManager;
}

/**
 * Helper function to add stream filter to SQL
 */
function addStreamFilter($sql, $table_alias = '') {
    $streamManager = getStreamManager();
    return $streamManager->addStreamFilter($sql, $table_alias);
}

/**
 * Helper function to get stream filter condition
 */
function getStreamFilterCondition($table_alias = '') {
    $streamManager = getStreamManager();
    return $streamManager->getStreamFilterCondition($table_alias);
}
?>
