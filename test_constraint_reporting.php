<?php
// Test the enhanced constraint reporting
include 'connect.php';
include_once 'schedule_functions.php';

echo "Testing enhanced constraint reporting...\n";
echo "=====================================\n\n";

// Test with stream_id = 3 (Regular stream), semester = 2
$stream_id = 3;
$semester = 2;

$result = scheduleUnscheduledClasses($conn, $stream_id, $semester);

// Handle the new return format
if (is_array($result)) {
    $scheduled_count = $result['scheduled_count'];
    $constraint_failures = $result['constraint_failures'];
    
    echo "Scheduled: $scheduled_count courses\n";
    echo "Constraint failures: " . count($constraint_failures) . " courses\n\n";
    
    if (!empty($constraint_failures)) {
        echo "Detailed Constraint Analysis:\n";
        echo "============================\n";
        
        foreach ($constraint_failures as $failure) {
            echo "Course: {$failure['course_code']} - {$failure['course_name']}\n";
            echo "Class: {$failure['class_name']}\n";
            echo "Lecturer: {$failure['lecturer_name']}\n";
            echo "Reason: {$failure['reason']}\n";
            echo "Details: {$failure['details']}\n";
            echo "Attempts: {$failure['attempts']} slot/room combinations\n";
            echo "Suitable rooms: {$failure['suitable_rooms']}\n";
            echo "Available slots: {$failure['available_slots']}\n";
            echo "---\n";
        }
    }
} else {
    // Fallback for old format
    echo "Scheduled: $result courses\n";
    echo "Note: Using old format - constraint details not available\n";
}

$conn->close();
?>
