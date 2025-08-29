<?php
// Export Timetable Page - Export timetable data to CSV

include 'connect.php';

// Get session ID from URL
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

if ($session_id <= 0) {
    die('Invalid session ID');
}

// Fetch session details
$session_sql = "SELECT semester_name, academic_year, semester_number FROM sessions WHERE id = ?";
$session_stmt = $conn->prepare($session_sql);
$session_stmt->bind_param('i', $session_id);
$session_stmt->execute();
$session_result = $session_stmt->get_result();
$session_data = $session_result->fetch_assoc();
$session_stmt->close();

if (!$session_data) {
    die('Session not found');
}

// Fetch timetable data for the session
$timetable_sql = "
    SELECT 
        d.name as day_name,
        '08:00' as start_time,
        '09:00' as end_time,
        r.name as room_name,
        r.building,
        r.capacity,
        c.name as class_name,
        co.name as course_name,
        co.code as course_code,
        l.name as lecturer_name,
        st.name as session_type,
        t.created_at
    FROM timetable t
    JOIN sessions s ON t.session_id = s.id
    JOIN class_courses cc ON t.class_course_id = cc.id
    JOIN classes c ON cc.class_id = c.id
    JOIN courses co ON cc.course_id = co.id
    JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    JOIN lecturers l ON lc.lecturer_id = l.id
    JOIN rooms r ON t.room_id = r.id
    JOIN days d ON t.day_id = d.id
    JOIN session_types st ON t.session_type_id = st.id
    WHERE t.session_id = ?
    ORDER BY 
        CASE d.name 
            WHEN 'Monday' THEN 1 
            WHEN 'Tuesday' THEN 2 
            WHEN 'Wednesday' THEN 3 
            WHEN 'Thursday' THEN 4 
            WHEN 'Friday' THEN 5 
            WHEN 'Saturday' THEN 6 
            WHEN 'Sunday' THEN 7 
        END,
        r.name
";

$timetable_stmt = $conn->prepare($timetable_sql);
$timetable_stmt->bind_param('i', $session_id);
$timetable_stmt->execute();
$timetable_result = $timetable_stmt->get_result();
$timetable_stmt->close();

// Set headers for CSV download
$filename = "timetable_" . str_replace('/', '_', $session_data['academic_year']) . "_semester_" . $session_data['semester_number'] . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV header
$headers = [
    'Day',
    'Start Time',
    'End Time',
    'Room',
    'Building',
    'Capacity',
    'Class',
    'Course Code',
    'Course Name',
    'Lecturer',
    'Session Type',
    'Created Date'
];
fputcsv($output, $headers);

// Write timetable data
while ($row = $timetable_result->fetch_assoc()) {
    $csv_row = [
        $row['day_name'],
        $row['start_time'],
        $row['end_time'],
        $row['room_name'],
        $row['building'],
        $row['capacity'],
        $row['class_name'],
        $row['course_code'],
        $row['course_name'],
        $row['lecturer_name'],
        $row['session_type'],
        date('Y-m-d H:i:s', strtotime($row['created_at']))
    ];
    fputcsv($output, $csv_row);
}

// Close the output stream
fclose($output);

// Close database connection
$conn->close();
exit();
?>
