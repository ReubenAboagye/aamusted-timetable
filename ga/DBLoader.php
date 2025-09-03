<?php
/**
 * Lightweight DB loader for GA inputs.
 * Usage: $loader = new GA\DBLoader($conn); $data = $loader->loadAll();
 */
class DBLoader {
    private $conn;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    /**
     * Load all required domain tables into arrays used by the GA.
     * Returns associative array with keys: class_courses, lecturer_courses, classes, rooms, time_slots, days, streams
     */
    public function loadAll(): array {
        return [
            'class_courses' => $this->loadClassCourses(),
            'lecturer_courses' => $this->loadLecturerCourses(),
            'classes' => $this->loadClasses(),
            'rooms' => $this->loadRooms(),
            'time_slots' => $this->loadTimeSlots(),
            'days' => $this->loadDays(),
            'streams' => $this->loadStreams(),
        ];
    }

    private function fetchAll($sql) {
        $res = $this->conn->query($sql);
        $out = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) { $out[] = $r; }
            $res->close();
        }
        return $out;
    }

    private function loadClassCourses() {
        return $this->fetchAll("SELECT id, class_id, course_id, lecturer_id, semester, academic_year FROM class_courses WHERE is_active = 1");
    }

    private function loadLecturerCourses() {
        return $this->fetchAll("SELECT id, lecturer_id, course_id FROM lecturer_courses WHERE is_active = 1");
    }

    private function loadClasses() {
        return $this->fetchAll("SELECT id, name, total_capacity, divisions_count, stream_id FROM classes WHERE is_active = 1");
    }

    private function loadRooms() {
        return $this->fetchAll("SELECT id, name, capacity FROM rooms WHERE is_active = 1");
    }

    private function loadTimeSlots() {
        return $this->fetchAll("SELECT id, start_time, end_time FROM time_slots WHERE is_mandatory = 1 ORDER BY start_time");
    }

    private function loadDays() {
        return $this->fetchAll("SELECT id, name FROM days WHERE is_active = 1 ORDER BY id");
    }

    private function loadStreams() {
        return $this->fetchAll("SELECT id, name, active_days FROM streams WHERE is_active = 1");
    }
}

?>

