<?php
include 'connect.php';

if (!isset($_GET['semester'])) {
    die("Semester not provided.");
}
$semester = intval($_GET['semester']);

// Retrieve courses and their associated lecturer info.
$sql = "SELECT co.course_id, co.course_name, cc.class_id, l.lecturer_id, l.lecturer_name
        FROM course co
        JOIN class_course cc ON co.course_id = cc.course_id
        JOIN lecturer_course lc ON co.course_id = lc.course_id
        JOIN lecturer l ON lc.lecturer_id = l.lecturer_id
        WHERE co.semester = $semester";
$coursesResult = $conn->query($sql);
if (!$coursesResult) { die("Error: " . $conn->error); }
$courses = [];
while ($row = $coursesResult->fetch_assoc()) {
    $courses[] = $row;
}
if (empty($courses)) {
    die("No courses available for semester.");
}

// Retrieve distinct class information.
$classSql = "SELECT DISTINCT c.class_id, c.class_name
             FROM class c
             JOIN class_course cc ON c.class_id = cc.class_id
             JOIN course co ON cc.course_id = co.course_id
             WHERE co.semester = $semester";
$classResult = $conn->query($classSql);
$classes = [];
while ($row = $classResult->fetch_assoc()) {
    $classes[] = $row;
}
if (empty($classes)) {
    die("No classes found for semester.");
}

// Retrieve available rooms.
$roomSql = "SELECT * FROM room";
$roomResult = $conn->query($roomSql);
$rooms = [];
while ($row = $roomResult->fetch_assoc()) {
    $rooms[] = $row;
}
if (empty($rooms)) {
    die("No rooms found.");
}

// Include the GA timetable generator.
include 'ga_timetable_generator.php';
$ga = new GeneticAlgorithm($classes, $courses, $rooms);
$ga->initializePopulation(50);
$bestTimetable = $ga->evolve(100);

// For re-optimization, delete existing timetable entries for this semester.
$deleteSQL = "DELETE FROM timetable WHERE semester = $semester";
$conn->query($deleteSQL);

// Insert new timetable entries.
foreach ($bestTimetable as $entry) {
    $class_id = $entry['class_id'];
    $course_id = $entry['course_id'];
    // Retrieve lecturer_id from courses array.
    $lecturer_id = null;
    foreach ($courses as $course) {
        if ($course['course_id'] == $course_id) {
            $lecturer_id = $course['lecturer_id'];
            break;
        }
    }
    // Retrieve room_id by matching room_name.
    $room_name = $entry['room_name'];
    $room_id = null;
    foreach ($rooms as $room) {
        if ($room['room_name'] == $room_name) {
            $room_id = $room['room_id'];
            break;
        }
    }
    $day = $entry['day'];
    $time_slot = $entry['time_slot'];
    
    // Note: This file needs to be updated to match the current schema
    // The current schema uses session_id, day_id, room_id instead of semester, day, time_slot
    echo "Re-optimization completed. Note: This file needs schema updates to work with the current system.";
    break; // Exit loop after first iteration to avoid multiple messages

echo "Re-optimization completed. Note: This file needs schema updates to work with the current system.";
$conn->close();
?>
