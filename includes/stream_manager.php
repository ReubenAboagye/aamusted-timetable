<?php
/**
 * Stream Manager - Handles stream selection and filtering throughout the application
 */

if (!class_exists('StreamManager')) {
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
        } elseif (isset($_SESSION['active_stream'])) {
            // Backwards compatibility with older session key
            $this->current_stream_id = $_SESSION['active_stream'];
            $_SESSION['current_stream_id'] = $this->current_stream_id;
        } elseif (isset($_SESSION['stream_id'])) {
            // Backwards compatibility with alternative session key
            $this->current_stream_id = $_SESSION['stream_id'];
            $_SESSION['current_stream_id'] = $this->current_stream_id;
        } else {
            // Get the currently active stream from database instead of hardcoding
            $active_stream_sql = "SELECT id FROM streams WHERE is_active = 1 LIMIT 1";
            $active_result = $this->conn->query($active_stream_sql);
            if ($active_result && $active_result->num_rows > 0) {
                $active_row = $active_result->fetch_assoc();
                $this->current_stream_id = $active_row['id'];
            } else {
                // Fallback to first stream if no active stream found
                $fallback_sql = "SELECT id FROM streams ORDER BY id LIMIT 1";
                $fallback_result = $this->conn->query($fallback_sql);
                if ($fallback_result && $fallback_result->num_rows > 0) {
                    $fallback_row = $fallback_result->fetch_assoc();
                    $this->current_stream_id = $fallback_row['id'];
                } else {
                    $this->current_stream_id = 1; // Ultimate fallback
                }
            }
            $_SESSION['current_stream_id'] = $this->current_stream_id;
        }
        
        // Get stream name
        $this->loadStreamName();
    }
    
    /**
     * Load the current stream name
     */
    private function loadStreamName() {
        $sql = "SELECT name FROM streams WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->current_stream_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->current_stream_name = $row['name'];
        } else {
            // Try to get the active stream name as fallback
            $fallback_sql = "SELECT name FROM streams WHERE is_active = 1 LIMIT 1";
            $fallback_result = $this->conn->query($fallback_sql);
            if ($fallback_result && $fallback_result->num_rows > 0) {
                $fallback_row = $fallback_result->fetch_assoc();
                $this->current_stream_name = $fallback_row['name'];
            } else {
                $this->current_stream_name = "Regular";
            }
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
            // Store in the canonical session key and keep legacy keys in sync
            $_SESSION['current_stream_id'] = $stream_id;
            $_SESSION['active_stream'] = $stream_id;
            $_SESSION['stream_id'] = $stream_id;
            $this->loadStreamName();
            return true;
        }
        return false;
    }
    
    /**
     * Sync session with currently active stream from database
     */
    public function syncWithActiveStream() {
        $active_stream_sql = "SELECT id FROM streams WHERE is_active = 1 LIMIT 1";
        $active_result = $this->conn->query($active_stream_sql);
        if ($active_result && $active_result->num_rows > 0) {
            $active_row = $active_result->fetch_assoc();
            $active_stream_id = $active_row['id'];
            
            // Update session if different from current
            if ($active_stream_id != $this->current_stream_id) {
                $this->setCurrentStream($active_stream_id);
                return true;
            }
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
        
        // Apply stream filter to ALL stream-aware tables
        $aliasTrim = rtrim($table_alias, '.');
        $stream_aware_tables = [
            'c', 'classes',           // Classes are stream-specific
            'co', 'courses',          // Courses are stream-specific
            'l', 'lecturers',         // Lecturers are stream-specific
            'r', 'rooms',             // Rooms are stream-specific
            'd', 'departments',       // Departments are stream-specific
            'p', 'programs',          // Programs are stream-specific
            'cc', 'class_courses'     // Class courses inherit stream from classes
        ];
        
        if (in_array(strtolower($aliasTrim), $stream_aware_tables)) {
            if (strpos($sql, 'WHERE') !== false) {
                $sql .= " AND {$alias}stream_id = " . $this->current_stream_id;
            } else {
                $sql .= " WHERE {$alias}stream_id = " . $this->current_stream_id;
            }
        }

        return $sql;
    }
    
    /**
     * Get stream filter condition for manual queries
     */
    public function getStreamFilterCondition($table_alias = '') {
        $aliasTrim = rtrim($table_alias, '.');
        $stream_aware_tables = [
            'c', 'classes',           // Classes are stream-specific
            'co', 'courses',          // Courses are stream-specific
            'l', 'lecturers',         // Lecturers are stream-specific
            'r', 'rooms',             // Rooms are stream-specific
            'd', 'departments',       // Departments are stream-specific
            'p', 'programs',          // Programs are stream-specific
            'cc', 'class_courses'     // Class courses inherit stream from classes
        ];
        
        if (in_array(strtolower($aliasTrim), $stream_aware_tables)) {
            $alias = $table_alias ? $table_alias . '.' : '';
            return "{$alias}stream_id = " . $this->current_stream_id;
        }
        return '';
    }
    
    /**
     * Check if a record belongs to current stream
     */
    public function isRecordInCurrentStream($table_name, $record_id) {
        // Check for tables that have stream_id column
        $stream_aware_tables = ['classes', 'courses', 'lecturers', 'rooms', 'departments', 'programs', 'class_courses'];
        
        if (!in_array(strtolower($table_name), $stream_aware_tables)) {
            // Non-stream-aware tables are considered global
            return true;
        }

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
     * Validate that a class and course belong to the same stream
     */
    public function validateClassCourseStreamConsistency($class_id, $course_id) {
        $sql = "SELECT c.stream_id as class_stream, co.stream_id as course_stream 
                FROM classes c, courses co 
                WHERE c.id = ? AND co.id = ? AND c.is_active = 1 AND co.is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $class_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['class_stream'] === $row['course_stream'];
        }
        
        $stmt->close();
        return false;
    }
    
    /**
     * Get stream-filtered SQL for common queries
     */
    public function getStreamFilteredSQL($base_table, $select_fields = '*', $additional_conditions = '') {
        $stream_condition = $this->getStreamFilterCondition($base_table);
        $where_clause = '';
        
        if (!empty($stream_condition)) {
            $where_clause = "WHERE {$stream_condition}";
            if (!empty($additional_conditions)) {
                $where_clause .= " AND ({$additional_conditions})";
            }
        } else if (!empty($additional_conditions)) {
            $where_clause = "WHERE {$additional_conditions}";
        }
        
        return "SELECT {$select_fields} FROM {$base_table} {$where_clause}";
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
}

/**
 * Global function to get stream manager instance
 */
if (!function_exists('getStreamManager')) {
function getStreamManager() {
    global $conn;
    static $streamManager = null;
    
    if ($streamManager === null) {
        $streamManager = new StreamManager($conn);
    }
    
    return $streamManager;
}
}

/**
 * Helper function to add stream filter to SQL
 */
if (!function_exists('addStreamFilter')) {
function addStreamFilter($sql, $table_alias = '') {
    $streamManager = getStreamManager();
    return $streamManager->addStreamFilter($sql, $table_alias);
}
}

/**
 * Helper function to get stream filter condition
 */
if (!function_exists('getStreamFilterCondition')) {
function getStreamFilterCondition($table_alias = '') {
    $streamManager = getStreamManager();
    return $streamManager->getStreamFilterCondition($table_alias);
}
}

/**
 * Helper function to sync with active stream
 */
if (!function_exists('syncWithActiveStream')) {
function syncWithActiveStream() {
    $streamManager = getStreamManager();
    return $streamManager->syncWithActiveStream();
}
}
?>
