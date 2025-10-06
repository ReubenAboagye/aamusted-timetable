<?php
/**
 * AJAX endpoint for loading lecturer conflicts
 * This handles the AJAX requests to load conflicts data asynchronously
 */

include 'connect.php';

// Set JSON header
header('Content-Type: application/json');

// Get parameters
$semester = $_GET['semester'] ?? 'second';
$academic_year = $_GET['academic_year'] ?? '2025/2026';
$version = $_GET['version'] ?? null;
$page = intval($_GET['page'] ?? 1);
$limit = intval($_GET['limit'] ?? 20); // Load 20 conflicts per page
$offset = ($page - 1) * $limit;

try {
    // Build conflict query based on whether we're viewing a specific version
    if ($version) {
        // Show conflicts for specific version only
        $conflictsQuery = "SELECT lc.lecturer_id, t.day_id, t.time_slot_id, t.semester, t.academic_year, t.version,
                           COUNT(*) as conflict_count
                           FROM timetable t
                           JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                           WHERE t.semester = ? AND t.academic_year = ? AND t.version = ? AND t.timetable_type = 'lecture'
                           GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id, t.semester, t.academic_year, t.version
                           HAVING conflict_count > 1
                           ORDER BY lc.lecturer_id, t.day_id, t.time_slot_id
                           LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($conflictsQuery);
        $stmt->bind_param("sssii", $semester, $academic_year, $version, $limit, $offset);
    } else {
        // Show conflicts for all versions
        $conflictsQuery = "SELECT lc.lecturer_id, t.day_id, t.time_slot_id, t.semester, t.academic_year, t.version,
                           COUNT(*) as conflict_count
                           FROM timetable t
                           JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                           WHERE t.semester = ? AND t.academic_year = ? AND t.timetable_type = 'lecture'
                           GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id, t.semester, t.academic_year, t.version
                           HAVING conflict_count > 1
                           ORDER BY lc.lecturer_id, t.day_id, t.time_slot_id, t.version
                           LIMIT ? OFFSET ?";
        
        $stmt = $conn->prepare($conflictsQuery);
        $stmt->bind_param("ssii", $semester, $academic_year, $limit, $offset);
    }

    $stmt->execute();
    $conflictGroups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get total count for pagination
    if ($version) {
        $countQuery = "SELECT COUNT(*) as total FROM (
                           SELECT lc.lecturer_id, t.day_id, t.time_slot_id, t.semester, t.academic_year, t.version,
                           COUNT(*) as conflict_count
                           FROM timetable t
                           JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                           WHERE t.semester = ? AND t.academic_year = ? AND t.version = ? AND t.timetable_type = 'lecture'
                           GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id, t.semester, t.academic_year, t.version
                           HAVING conflict_count > 1
                       ) as conflict_summary";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param("sss", $semester, $academic_year, $version);
    } else {
        $countQuery = "SELECT COUNT(*) as total FROM (
                           SELECT lc.lecturer_id, t.day_id, t.time_slot_id, t.semester, t.academic_year, t.version,
                           COUNT(*) as conflict_count
                           FROM timetable t
                           JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                           WHERE t.semester = ? AND t.academic_year = ? AND t.timetable_type = 'lecture'
                           GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id, t.semester, t.academic_year, t.version
                           HAVING conflict_count > 1
                       ) as conflict_summary";
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bind_param("ss", $semester, $academic_year);
    }
    
    $countStmt->execute();
    $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    // Get detailed conflict entries for each conflict group
    $conflicts = [];
    $detailedConflicts = [];

    foreach ($conflictGroups as $conflictGroup) {
        $detailQuery = "SELECT t.*, lc.lecturer_id, l.name as lecturer_name, c.name as course_name, 
                        cl.name as class_name, d.name as day_name, ts.start_time, ts.end_time, r.name as room_name
                        FROM timetable t
                        JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                        JOIN lecturers l ON lc.lecturer_id = l.id
                        JOIN class_courses cc ON t.class_course_id = cc.id
                        JOIN classes cl ON cc.class_id = cl.id
                        JOIN courses c ON cc.course_id = c.id
                        JOIN days d ON t.day_id = d.id
                        JOIN time_slots ts ON t.time_slot_id = ts.id
                        JOIN rooms r ON t.room_id = r.id
                        WHERE lc.lecturer_id = ? AND t.day_id = ? AND t.time_slot_id = ? 
                        AND t.semester = ? AND t.academic_year = ? AND t.timetable_type = 'lecture'";
        
        $params = [
            $conflictGroup['lecturer_id'], 
            $conflictGroup['day_id'], 
            $conflictGroup['time_slot_id'],
            $conflictGroup['semester'],
            $conflictGroup['academic_year']
        ];
        
        // Add version filter if viewing specific version
        if ($version) {
            $detailQuery .= " AND t.version = ?";
            $params[] = $version;
        }
        
        $detailQuery .= " ORDER BY t.id";
        
        $detailStmt = $conn->prepare($detailQuery);
        $detailStmt->bind_param(str_repeat('i', 3) . str_repeat('s', count($params) - 3), ...$params);
        $detailStmt->execute();
        $conflictEntries = $detailStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $detailStmt->close();
        
        if (!empty($conflictEntries)) {
            // Create enriched conflict data with details from first entry
            $firstEntry = $conflictEntries[0];
            $conflict = [
                'lecturer_id' => $conflictGroup['lecturer_id'],
                'day_id' => $conflictGroup['day_id'],
                'time_slot_id' => $conflictGroup['time_slot_id'],
                'semester' => $conflictGroup['semester'],
                'academic_year' => $conflictGroup['academic_year'],
                'version' => $conflictGroup['version'],
                'conflict_count' => $conflictGroup['conflict_count'],
                'lecturer_name' => $firstEntry['lecturer_name'],
                'day_name' => $firstEntry['day_name'],
                'start_time' => $firstEntry['start_time'],
                'end_time' => $firstEntry['end_time']
            ];
            
            $conflicts[] = $conflict;
            $detailedConflicts[$conflictGroup['lecturer_id'] . '-' . $conflictGroup['day_id'] . '-' . $conflictGroup['time_slot_id']] = $conflictEntries;
        }
    }

    // Return JSON response
    echo json_encode([
        'success' => true,
        'conflicts' => $conflicts,
        'detailedConflicts' => $detailedConflicts,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalCount / $limit),
            'total_count' => $totalCount,
            'limit' => $limit
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

mysqli_close($conn);
?>









