<?php
$pageTitle = 'Classes Management';
include 'includes/header.php';
include 'includes/sidebar.php';

// Database connection
include 'connect.php';

// Include stream manager early
include 'includes/stream_manager.php';
$streamManager = getStreamManager();

// Check whether the `departments` table has a `short_name` column (some DBs may lack it)
$dept_short_exists = false;
$col_check = $conn->query("SHOW COLUMNS FROM departments LIKE 'short_name'");
if ($col_check && $col_check->num_rows > 0) {
    $dept_short_exists = true;
}

// Fetch streams from database
$streams_sql = "SELECT id, name, code FROM streams WHERE is_active = 1 ORDER BY name";
$streams_result = $conn->query($streams_sql);

// Check for query errors
if (!$streams_result) {
    error_log("Streams query failed: " . $conn->error);
    // Try without WHERE clause to see if table exists
    $fallback_sql = "SELECT id, name, code FROM streams ORDER BY name";
    $streams_result = $conn->query($fallback_sql);
    if (!$streams_result) {
        error_log("Fallback streams query also failed: " . $conn->error);
        $streams_result = null;
    }
}

// Debug: Show what streams are being fetched
if (isset($_GET['debug_streams'])) {
    echo "<div class='alert alert-info'>";
    echo "<strong>Streams Debug:</strong><br>";
    echo "<strong>Query:</strong> " . htmlspecialchars($streams_sql) . "<br>";
    echo "<strong>Result:</strong> " . ($streams_result ? $streams_result->num_rows : 'Query failed') . "<br>";
    if ($streams_result && $streams_result->num_rows > 0) {
        echo "<strong>Available Streams:</strong><br>";
        $streams_result->data_seek(0);
        while ($stream = $streams_result->fetch_assoc()) {
            echo "- ID: " . $stream['id'] . ", Name: " . htmlspecialchars($stream['name']) . ", Code: " . htmlspecialchars($stream['code']) . "<br>";
        }
    } else {
        echo "<strong>No streams found or query failed</strong><br>";
        // Try to show what's actually in the table
        $all_streams_debug = $conn->query("SELECT id, name, code, is_active FROM streams ORDER BY id");
        if ($all_streams_debug && $all_streams_debug->num_rows > 0) {
            echo "<strong>All streams in table (including inactive):</strong><br>";
            while ($stream = $all_streams_debug->fetch_assoc()) {
                $status = $stream['is_active'] ? 'Active' : 'Inactive';
                echo "- ID: " . $stream['id'] . ", Name: " . htmlspecialchars($stream['name']) . ", Code: " . htmlspecialchars($stream['code']) . ", Status: " . $status . "<br>";
            }
        } else {
            echo "<strong>No streams found in table at all</strong><br>";
        }
    }
    echo "</div>";
}

// Additional debug info for dropdown streams
if (isset($_GET['debug_dropdown'])) {
    $dropdown_test_sql = "SELECT id, name, code FROM streams WHERE is_active = 1 ORDER BY name";
    $dropdown_test_result = $conn->query($dropdown_test_sql);
    echo "<div class='alert alert-warning'>";
    echo "<strong>Dropdown Streams Debug:</strong><br>";
    echo "<strong>Query:</strong> " . htmlspecialchars($dropdown_test_sql) . "<br>";
    echo "<strong>Result:</strong> " . ($dropdown_test_result ? $dropdown_test_result->num_rows : 'Query failed') . "<br>";
    if ($dropdown_test_result && $dropdown_test_result->num_rows > 0) {
        echo "<strong>Dropdown Streams:</strong><br>";
        while ($stream = $dropdown_test_result->fetch_assoc()) {
            echo "- ID: " . $stream['id'] . ", Name: " . htmlspecialchars($stream['name']) . ", Code: " . htmlspecialchars($stream['code']) . "<br>";
        }
    }
    echo "</div>";
}

// Debug: Check if streams table exists and has data
if (!$streams_result) {
    // If streams table doesn't exist, create it with default data
    $create_streams_sql = "
    CREATE TABLE IF NOT EXISTS `streams` (
        `id` int NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL,
        `code` varchar(20) NOT NULL,
        `description` text,
        `active_days` json DEFAULT NULL,
        `period_start` time DEFAULT NULL,
        `period_end` time DEFAULT NULL,
        `break_start` time DEFAULT NULL,
        `break_end` time DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT '1',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`),
        UNIQUE KEY `code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
    ";
    $conn->query($create_streams_sql);
    
    // Insert default streams
    $insert_streams_sql = "
    INSERT IGNORE INTO `streams` (`id`, `name`, `code`, `description`, `is_active`) VALUES
    (1, 'Regular', 'REG', 'Regular weekday classes', 1),
    (2, 'Weekend', 'WKD', 'Weekend classes', 1),
    (3, 'Evening', 'EVE', 'Evening classes', 1);
    ";
    $conn->query($insert_streams_sql);
    
    // Try fetching streams again
    $streams_result = $conn->query($streams_sql);
}

// Handle form submission for adding new class
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'add') {
        // Current schema: classes has program_id and level_id
        $program_id = $conn->real_escape_string($_POST['program_code'] ?? '');
        $level_id = $conn->real_escape_string($_POST['level_id'] ?? '');
        $total_capacity = intval($_POST['total_capacity'] ?? 0);
        $stream_id = isset($_POST['stream_id']) ? $conn->real_escape_string($_POST['stream_id']) : $streamManager->getCurrentStreamId();
        $is_active = 1;

        // Fetch program and its department short code if available
        if ($dept_short_exists) {
            $prog_sql = "SELECT p.id, p.name, p.code, p.department_id, d.short_name AS dept_short FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.id = ?";
        } else {
            // departments.short_name not present; fall back to first 3 chars of department name
            $prog_sql = "SELECT p.id, p.name, p.code, p.department_id, SUBSTRING(d.name,1,3) AS dept_short FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.id = ?";
        }
        $prog_stmt = $conn->prepare($prog_sql);
        $prog_stmt->bind_param("i", $program_id);
        $prog_stmt->execute();
        $prog_res = $prog_stmt->get_result();
        $program = $prog_res->fetch_assoc();
        $prog_stmt->close();

        $lvl_stmt = $conn->prepare("SELECT id, name FROM levels WHERE id = ?");
        $lvl_stmt->bind_param("i", $level_id);
        $lvl_stmt->execute();
        $lvl_res = $lvl_stmt->get_result();
        $level = $lvl_res->fetch_assoc();
        $lvl_stmt->close();

        // Validate stream
        $stream_stmt = $conn->prepare("SELECT id, name FROM streams WHERE id = ? AND is_active = 1");
        $stream_stmt->bind_param("i", $stream_id);
        $stream_stmt->execute();
        $stream_res = $stream_stmt->get_result();
        $stream = $stream_res->fetch_assoc();
        $stream_stmt->close();

        if ($program && $level && $stream) {
            // Create a single parent class record and store total_capacity and divisions_count
            $divisions_count = max(1, (int)ceil($total_capacity / 100));

            // Extract level numeric part for code (e.g., 'Level 100' -> '100' or 'Year 1' -> '100')
            preg_match('/(\d+)/', $level['name'], $m);
            $raw_level = isset($m[1]) ? (int)$m[1] : 1;
            // If level is given as small number like 1,2,3 treat as Year (100,200,300)
            if ($raw_level > 0 && $raw_level < 10) {
                $level_num = $raw_level * 100;
            } else {
                $level_num = $raw_level;
            }
            $year_suffix = date('Y');

            // Build short class base using department short or program code and level number, e.g. "ITE 100"
            $dept_short = !empty($program['dept_short']) ? strtoupper(trim($program['dept_short'])) : strtoupper(trim($program['code'] ?? 'PRG'));
            $class_name = $dept_short . ' ' . $level_num; // e.g. ITE 100
            $class_code = $dept_short . $level_num; // compact code used for identifiers

            // Ensure stream_id is an integer
            $stream_id = (int)$stream_id;

            // Check if class code already exists (including inactive ones)
            $check_sql = "SELECT id, name, is_active FROM classes WHERE code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $class_code);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing_class = $check_result->fetch_assoc();
                $check_stmt->close();
                if ($existing_class['is_active'] == 1) {
                    $error_message = "Class code '{$class_code}' already exists as an active class.";
                } else {
                    $error_message = "Class code '{$class_code}' already exists but is inactive. Please use a different code or reactivate the existing class.";
                }
            } else {
                $check_stmt->close();
                
                $sql = "INSERT INTO classes (program_id, level_id, name, code, stream_id, is_active, total_capacity, divisions_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("iissiiii", $program_id, $level_id, $class_name, $class_code, $stream_id, $is_active, $total_capacity, $divisions_count);
                if ($stmt->execute()) {
                    $insertedId = $stmt->insert_id;
                    $stmt->close();
                    // Optionally create persistent division rows if a class_divisions table exists
                    $createdDivisions = 0;
                    $checkDivTable = $conn->query("SHOW TABLES LIKE 'class_divisions'");
                    if ($checkDivTable && $checkDivTable->num_rows > 0) {
                        $insDiv = $conn->prepare("INSERT INTO class_divisions (class_id, division_label, capacity) VALUES (?, ?, ?)");
                        if ($insDiv) {
                            // Distribute capacity evenly (last one gets remainder)
                            $base = intdiv($total_capacity, $divisions_count);
                            $remainder = $total_capacity % $divisions_count;
                            for ($d = 0; $d < $divisions_count; $d++) {
                                $label = '';
                                $n = $d;
                                while (true) {
                                    $label = chr(65 + ($n % 26)) . $label;
                                    $n = intdiv($n, 26) - 1;
                                    if ($n < 0) break;
                                }
                                $cap = $base + ($d < $remainder ? 1 : 0);
                                $insDiv->bind_param("isi", $insertedId, $label, $cap);
                                if ($insDiv->execute()) $createdDivisions++;
                            }
                            $insDiv->close();
                        }
                    }

                    $msg = "Successfully created class '{$class_name}' in {$stream['name']} stream with {$divisions_count} divisions.";
                    if ($createdDivisions > 0) $msg .= " Created {$createdDivisions} class records.";
                    redirect_with_flash('classes.php', 'success', $msg);
                } else {
                    $error_message = "Failed to create class: " . $stmt->error;
                    if (strpos($stmt->error, 'uq_class_code_year_semester') !== false) {
                        $error_message = "Class code '{$class_code}' already exists. Please use a different code.";
                    }
                    $stmt->close();
                }
            } else {
                $error_message = "Failed to prepare class insert: " . $conn->error;
            }
            }
        } else {
            $error_message = "Invalid program, level, or stream selected.";
        }
    } elseif ($action === 'delete' && isset($_POST['id'])) {
        $id = $conn->real_escape_string($_POST['id']);
        $sql = "UPDATE classes SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $stmt->close();
            redirect_with_flash('classes.php', 'success', 'Class deleted successfully!');
        } else {
            $error_message = "Error deleting class: " . $conn->error;
        }
        $stmt->close();
    
    // Edit class (update representative row: name, total_capacity, divisions_count, stream_id)
    } elseif ($action === 'edit' && isset($_POST['id'])) {
        $id = (int)($_POST['id'] ?? 0);
        $name = isset($_POST['name']) ? $conn->real_escape_string(trim($_POST['name'])) : '';
        $total_capacity = isset($_POST['total_capacity']) ? (int)$_POST['total_capacity'] : 0;
        $divisions_count = isset($_POST['divisions_count']) ? max(1, (int)$_POST['divisions_count']) : 1;
        $stream_id = isset($_POST['stream_id']) ? (int)$_POST['stream_id'] : $streamManager->getCurrentStreamId();

        if ($id <= 0) {
            $error_message = 'Invalid class id.';
        } else {
            // Validate stream exists
            $stream_check = $conn->prepare("SELECT id FROM streams WHERE id = ? AND is_active = 1");
            $stream_check->bind_param("i", $stream_id);
            $stream_check->execute();
            $stream_result = $stream_check->get_result();
            
            if ($stream_result->num_rows === 0) {
                $error_message = 'Invalid stream selected.';
                $stream_check->close();
            } else {
                $stream_check->close();
                $update_sql = "UPDATE classes SET name = ?, total_capacity = ?, divisions_count = ?, stream_id = ? WHERE id = ?";
                $u_stmt = $conn->prepare($update_sql);
                if ($u_stmt) {
                    $u_stmt->bind_param('siiii', $name, $total_capacity, $divisions_count, $stream_id, $id);
                    if ($u_stmt->execute()) {
                        $u_stmt->close();
                        redirect_with_flash('classes.php', 'success', 'Class updated successfully!');
                    } else {
                        $error_message = 'Error updating class: ' . $u_stmt->error;
                        $u_stmt->close();
                    }
                } else {
                    $error_message = 'Error preparing update: ' . $conn->error;
                }
            }
        }
    } elseif ($action === 'reactivate' && isset($_POST['id'])) {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            $error_message = 'Invalid class id.';
        } else {
            $reactivate_sql = "UPDATE classes SET is_active = 1 WHERE id = ?";
            $r_stmt = $conn->prepare($reactivate_sql);
            if ($r_stmt) {
                $r_stmt->bind_param('i', $id);
                if ($r_stmt->execute()) {
                    $r_stmt->close();
                    redirect_with_flash('classes.php', 'success', 'Class reactivated successfully!');
                } else {
                    $error_message = 'Error reactivating class: ' . $r_stmt->error;
                    $r_stmt->close();
                }
            } else {
                $error_message = 'Error preparing reactivate: ' . $conn->error;
            }
        }
    }
}

// Fetch classes with related program, level and stream information
// Detect commonly used foreign keys on `classes` and join accordingly
$class_has_program = false;
$class_has_level = false;
$class_has_stream = false;
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'program_id'");
if ($col && $col->num_rows > 0) {
    $class_has_program = true;
}
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'level_id'");
if ($col && $col->num_rows > 0) {
    $class_has_level = true;
}
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'stream_id'");
if ($col && $col->num_rows > 0) {
    $class_has_stream = true;
}

$select_extra = [];
$from_clause = "FROM classes c ";

// Join programs
if ($class_has_program) {
    $select_extra[] = "p.name as program_name";
    $select_extra[] = "p.code as program_code";
    if ($dept_short_exists) {
        $select_extra[] = "d.short_name as dept_short";
    } else {
        $select_extra[] = "SUBSTRING(d.name,1,3) as dept_short";
    }
    $from_clause .= "LEFT JOIN programs p ON c.program_id = p.id ";
    $from_clause .= "LEFT JOIN departments d ON p.department_id = d.id ";
}

// Join levels
if ($class_has_level) {
    $select_extra[] = "l.name as level_name";
    $from_clause .= "LEFT JOIN levels l ON c.level_id = l.id ";
}

// Join streams
if ($class_has_stream) {
    $select_extra[] = "s.name as stream_name";
    $select_extra[] = "s.code as stream_code";
    $from_clause .= "LEFT JOIN streams s ON c.stream_id = s.id ";
}

// Detect if classes table stores divisions_count (new schema)
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'divisions_count'");
$has_divisions_col = ($col && $col->num_rows > 0);
if ($col) $col->close();

// Detect if classes table stores total_capacity
$col = $conn->query("SHOW COLUMNS FROM classes LIKE 'total_capacity'");
$has_total_capacity = ($col && $col->num_rows > 0);
if ($col) $col->close();

// Preserve department short selection (only used when programs/departments are linked)
if ($dept_short_exists && !$class_has_program) {
    // If we don't have program join, but departments are expected elsewhere, include department placeholder
    $select_extra[] = $dept_short_exists ? "d.short_name as department_short" : "SUBSTRING(d.name,1,3) as department_short";
}

// Build select to show individual class records
$select_clause = "c.id, c.name, c.divisions_count, c.total_capacity, c.program_id, c.level_id";
if ($class_has_stream) {
    $select_clause .= ", c.stream_id";
}
if (!empty($select_extra)) {
    $select_clause .= ", " . implode(', ', $select_extra);
}

$where_clause = "WHERE c.is_active = 1";
// Optionally show inactive classes
if (isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1') {
    $where_clause = "WHERE 1=1"; // Show all classes (active and inactive)
}
// Temporarily disable stream filtering to debug
// if ($class_has_stream) {
//     $where_clause .= " AND c.stream_id = " . $streamManager->getCurrentStreamId();
// }

$sql = "SELECT " . $select_clause . "\n        " . $from_clause . "\n        " . $where_clause . "\n        ORDER BY c.name";
$result = $conn->query($sql);

// Debug: Show the SQL query and result count
if (isset($_GET['debug'])) {
    echo "<div class='alert alert-info'>";
    echo "<strong>Debug SQL:</strong><br>";
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";
    echo "<strong>Result count:</strong> " . ($result ? $result->num_rows : 'Query failed');
    echo "</div>";
    
    // Debug streams data
    echo "<div class='alert alert-warning'>";
    echo "<strong>Streams Debug:</strong><br>";
    echo "<strong>Streams Query:</strong> " . htmlspecialchars($streams_sql) . "<br>";
    echo "<strong>Streams Result:</strong> " . ($streams_result ? $streams_result->num_rows : 'Query failed') . "<br>";
    echo "<strong>Current Stream ID:</strong> " . $streamManager->getCurrentStreamId() . "<br>";
    if ($streams_result && $streams_result->num_rows > 0) {
        echo "<strong>Available Streams:</strong><br>";
        $streams_result->data_seek(0);
        while ($stream = $streams_result->fetch_assoc()) {
            echo "- ID: " . $stream['id'] . ", Name: " . htmlspecialchars($stream['name']) . ", Code: " . htmlspecialchars($stream['code']) . "<br>";
        }
    }
    echo "</div>";
}

$dept_select_cols = $dept_short_exists ? "id, name, short_name" : "id, name";
// Departments are global; do not filter by stream here
$dept_sql = "SELECT " . $dept_select_cols . " FROM departments WHERE is_active = 1 ORDER BY name";
$dept_result = $conn->query($dept_sql);

// Fetch levels for dropdown
// Use year_number for ordering when available, otherwise fall back to id
$level_order_col = 'year_number';
$col_check = $conn->query("SHOW COLUMNS FROM levels LIKE 'year_number'");
if (!($col_check && $col_check->num_rows > 0)) {
    $level_order_col = 'id';
}
$level_sql = "SELECT id, name FROM levels ORDER BY " . $level_order_col;
$level_result = $conn->query($level_sql);


?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-users me-2"></i>Classes Management</h4>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="fas fa-plus me-2"></i>Add New Classes
            </button>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="search-container m-3">
            <div class="row">
                <div class="col-md-5">
                    <input type="text" class="search-input form-control" placeholder="Search classes...">
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="streamFilter">
                        <option value="">All Streams</option>
                        <?php 
                        // Fetch streams for the filter dropdown
                        $filter_streams_sql = "SELECT id, name, code FROM streams ORDER BY name";
                        $filter_streams_result = $conn->query($filter_streams_sql);
                        if ($filter_streams_result && $filter_streams_result->num_rows > 0):
                            while ($stream = $filter_streams_result->fetch_assoc()):
                        ?>
                            <option value="<?php echo $stream['id']; ?>">
                                <?php echo htmlspecialchars($stream['name']); ?>
                            </option>
                        <?php 
                            endwhile;
                        endif;
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="showInactive" onchange="toggleInactiveClasses()">
                        <label class="form-check-label" for="showInactive">
                            Show Inactive
                        </label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <small class="text-muted" id="filterSummary">
                        Showing all classes
                    </small>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table" id="classesTable">
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Level</th>
                        <th>Program</th>
                        <th>Total Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                                                         <tr class="<?php echo (isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1' && ($row['is_active'] ?? 1) == 0) ? 'table-secondary' : ''; ?>">
                                 <td>
                                     <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                     <?php if ($class_has_stream && !empty($row['stream_name'])): ?>
                                         <br><small><span class="badge bg-success"><?php echo htmlspecialchars($row['stream_name']); ?></span></small>
                                     <?php endif; ?>
                                     <?php if (isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1' && ($row['is_active'] ?? 1) == 0): ?>
                                         <br><small><span class="badge bg-warning">Inactive</span></small>
                                     <?php endif; ?>
                                 </td>
                                 <td><span class="badge bg-warning"><?php echo htmlspecialchars($row['level'] ?? ($row['level_name'] ?? 'N/A')); ?></span></td>
                                 <td>
                                     <?php $deptCode = trim(strtoupper($row['dept_short'] ?? $row['program_code'] ?? ''));
                                     $levelTextRow = $row['level_name'] ?? $row['level'] ?? '';
                                     preg_match('/(\d+)/', $levelTextRow, $lm);
                                     $rawLevelRow = isset($lm[1]) ? (int)$lm[1] : 0;
                                     $levelNumRow = ($rawLevelRow > 0 && $rawLevelRow < 10) ? $rawLevelRow * 100 : $rawLevelRow;
                                     ?>
                                     <span class="badge bg-info"><?php echo htmlspecialchars(($deptCode ? $deptCode . ' ' : '') . ($row['program_name'] ?? 'N/A')); ?></span>
                                 </td>
                                 <td>
                                     <div class="d-flex align-items-center gap-2">
                                         <span class="badge bg-dark"><?php echo (int)($row['total_capacity'] ?? 0); ?> students</span>
                                         <span class="badge bg-secondary"><?php echo (int)($row['divisions_count'] ?? 1); ?> classes</span>
                                     </div>
                                 </td>
                                                                 <td>
                                     <button class="btn btn-sm btn-outline-secondary me-1" onclick="showDivisions('<?php echo htmlspecialchars(addslashes($deptCode)); ?>', '<?php echo htmlspecialchars(addslashes($levelNumRow)); ?>', <?php echo (int)($row['divisions_count'] ?? 1); ?>)">
                                         <i class="fas fa-list"></i>
                                     </button>
                                     <button class="btn btn-sm btn-outline-primary me-1" onclick="editClass(this)" 
                                             data-id="<?php echo (int)$row['id']; ?>" 
                                             data-name="<?php echo htmlspecialchars($row['name'] ?? '', ENT_QUOTES); ?>" 
                                             data-divisions="<?php echo (int)($row['divisions_count'] ?? 1); ?>" 
                                             data-total="<?php echo (int)($row['total_capacity'] ?? 0); ?>" 
                                             data-stream="<?php echo (int)($row['stream_id'] ?? 0); ?>">
                                         <i class="fas fa-edit"></i>
                                     </button>
                                     <?php if (isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1' && ($row['is_active'] ?? 1) == 0): ?>
                                         <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to reactivate this class?')">
                                             <input type="hidden" name="action" value="reactivate">
                                             <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                             <button type="submit" class="btn btn-sm btn-outline-success" title="Reactivate Class">
                                                 <i class="fas fa-undo"></i>
                                             </button>
                                         </form>
                                     <?php endif; ?>
                                     <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this class?')">
                                         <input type="hidden" name="action" value="delete">
                                         <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                         <button type="submit" class="btn btn-sm btn-outline-danger">
                                             <i class="fas fa-trash"></i>
                                         </button>
                                     </form>
                                 </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <i class="fas fa-users"></i>
                                <p>No classes found. Add your first classes to get started!</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Classes Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Classes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="divisions_count" id="divisions_count" value="1">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Auto-Class Generation:</strong> Select the program, level, stream, and enter the number of students. The system will create a single class record and split it into divisions (max 100 students per division). You can preview the divisions below.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="program_code" class="form-label">Program Code *</label>
                                <select class="form-select" id="program_code" name="program_code" required>
                                    <option value="">Select Program</option>
                                    <?php
                                    // Fetch programs for dropdown (include dept short if available)
                                    if ($dept_short_exists) {
                                        $programs_sql = "SELECT p.id, p.name, p.code, d.short_name AS dept_short FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.is_active = 1 ORDER BY p.name";
                                    } else {
                                        $programs_sql = "SELECT p.id, p.name, p.code, SUBSTRING(d.name,1,3) AS dept_short FROM programs p LEFT JOIN departments d ON p.department_id = d.id WHERE p.is_active = 1 ORDER BY p.name";
                                    }
                                    $programs_result = $conn->query($programs_sql);
                                    if ($programs_result && $programs_result->num_rows > 0):
                                        while ($program = $programs_result->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $program['id']; ?>" data-code="<?php echo htmlspecialchars($program['code']); ?>" data-dept="<?php echo htmlspecialchars($program['dept_short'] ?? $program['code']); ?>">
                                            <?php echo htmlspecialchars($program['name']); ?> (<?php echo htmlspecialchars($program['code']); ?>)
                                        </option>
                                    <?php 
                                        endwhile;
                                    endif;
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="level_id" class="form-label">Level *</label>
                                <select class="form-select" id="level_id" name="level_id" required>
                                    <option value="">Select Level</option>
                                    <?php if ($level_result && $level_result->num_rows > 0): ?>
                                        <?php while ($level = $level_result->fetch_assoc()): ?>
                                            <option value="<?php echo $level['id']; ?>"><?php echo htmlspecialchars($level['name']); ?></option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="stream_id" class="form-label">Stream *</label>
                                <select class="form-select" id="stream_id" name="stream_id" required>
                                    <option value="">Select Stream</option>
                                    <?php 
                                    // Fetch streams fresh for the dropdown to ensure we have the data
                                    // Temporarily show ALL streams (both active and inactive) for debugging
                                    $dropdown_streams_sql = "SELECT id, name, code, is_active FROM streams ORDER BY name";
                                    $dropdown_streams_result = $conn->query($dropdown_streams_sql);
                                    if ($dropdown_streams_result && $dropdown_streams_result->num_rows > 0): ?>
                                        <?php while ($stream = $dropdown_streams_result->fetch_assoc()): ?>
                                            <option value="<?php echo $stream['id']; ?>" 
                                                    <?php echo $stream['id'] == $streamManager->getCurrentStreamId() ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($stream['name']); ?>
                                                <?php echo $stream['is_active'] ? ' (Active)' : ' (Inactive)'; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="">No streams available</option>
                                    <?php endif; ?>
                                </select>
                                <?php if (isset($_GET['debug_streams'])): ?>
                                    <small class="text-muted">Debug: Streams result has <?php echo $dropdown_streams_result ? $dropdown_streams_result->num_rows : 0; ?> rows</small>
                                <?php endif; ?>
                                <?php if (!$dropdown_streams_result || $dropdown_streams_result->num_rows == 0): ?>
                                    <small class="text-danger">No streams available. Please create streams first in the Streams Management page.</small>
                                <?php else: ?>
                                    <small class="text-info">Showing all streams (active and inactive) for debugging. Inactive streams are marked with "(Inactive)".</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="total_capacity" class="form-label">Number of Students *</label>
                                <input type="number" class="form-control" id="total_capacity" name="total_capacity" min="1" max="10000" value="100" required>
                                <small class="text-muted">The system will create classes automatically (max 100 students per class)</small>
                                <small class="text-muted">The system will create classes automatically (max 100 students per class)</small>
                            </div>
                        </div>
                    </div>
                    
                    <div id="classPreview" class="alert alert-secondary" style="display: none;">
                        <strong>Class Preview:</strong> <span id="previewText"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate Classes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Classes Modal (formerly Divisions) -->
<div class="modal fade" id="divisionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Class Sections</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="divisionsList"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Class</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editClassForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_class_id" value="">
                    <div class="mb-3">
                        <label class="form-label">Class Name</label>
                        <input type="text" name="name" id="edit_class_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Capacity</label>
                        <input type="number" name="total_capacity" id="edit_total_capacity" class="form-control" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Classes</label>
                        <input type="number" name="divisions_count" id="edit_divisions_count" class="form-control" min="1">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stream</label>
                        <select name="stream_id" id="edit_stream_id" class="form-select" required>
                            <option value="">Select Stream</option>
                            <?php 
                            // Fetch streams fresh for the edit dropdown - show all streams
                            $edit_streams_sql = "SELECT id, name, code, is_active FROM streams ORDER BY name";
                            $edit_streams_result = $conn->query($edit_streams_sql);
                            if ($edit_streams_result && $edit_streams_result->num_rows > 0): ?>
                                <?php while ($stream = $edit_streams_result->fetch_assoc()): ?>
                                    <option value="<?php echo $stream['id']; ?>">
                                        <?php echo htmlspecialchars($stream['name']); ?>
                                        <?php echo $stream['is_active'] ? ' (Active)' : ' (Inactive)'; ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>

<script>
// Stream filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const streamFilter = document.getElementById('streamFilter');
    const searchInput = document.querySelector('.search-input');
    
    // Add event listeners for filtering
    if (streamFilter) {
        streamFilter.addEventListener('change', filterClasses);
    }
    if (searchInput) {
        searchInput.addEventListener('input', filterClasses);
    }
});

function filterClasses() {
    const searchTerm = document.querySelector('.search-input').value.toLowerCase();
    const selectedStream = document.getElementById('streamFilter').value;
    const tableRows = document.querySelectorAll('#classesTable tbody tr');
    
    tableRows.forEach(row => {
        const className = row.querySelector('td:first-child').textContent.toLowerCase();
        const streamBadge = row.querySelector('td:first-child .badge.bg-success');
        const streamName = streamBadge ? streamBadge.textContent.toLowerCase() : '';
        
        let showRow = true;
        
        // Filter by search term (search in class name and stream name)
        if (searchTerm && !className.includes(searchTerm) && !streamName.includes(searchTerm)) {
            showRow = false;
        }
        
        // Filter by stream
        if (selectedStream) {
            const streamOption = document.querySelector(`#streamFilter option[value="${selectedStream}"]`);
            const selectedStreamName = streamOption ? streamOption.textContent.toLowerCase() : '';
            if (streamName !== selectedStreamName) {
                showRow = false;
            }
        }
        
        row.style.display = showRow ? '' : 'none';
    });
    
    // Show/hide empty state message
    const visibleRows = document.querySelectorAll('#classesTable tbody tr:not([style*="display: none"])');
    const emptyStateRow = document.querySelector('#classesTable tbody tr.empty-state');
    
    if (visibleRows.length === 0) {
        if (!emptyStateRow) {
            const tbody = document.querySelector('#classesTable tbody');
            const newEmptyState = document.createElement('tr');
            newEmptyState.className = 'empty-state';
            newEmptyState.innerHTML = `
                <td colspan="5" class="text-center">
                    <i class="fas fa-search"></i>
                    <p>No classes match your current filters.</p>
                </td>
            `;
            tbody.appendChild(newEmptyState);
        }
    } else if (emptyStateRow) {
        emptyStateRow.remove();
    }
    
    // Update filter summary
    updateFilterSummary(visibleRows.length, searchTerm, selectedStream);
}

function updateFilterSummary(visibleCount, searchTerm, selectedStream) {
    const summaryElement = document.getElementById('filterSummary');
    const totalRows = document.querySelectorAll('#classesTable tbody tr:not(.empty-state)').length;
    
    let summaryText = `Showing ${visibleCount} of ${totalRows} classes`;
    
    if (searchTerm || selectedStream) {
        const filters = [];
        if (searchTerm) filters.push(`search: "${searchTerm}"`);
        if (selectedStream) {
            const streamOption = document.querySelector(`#streamFilter option[value="${selectedStream}"]`);
            const streamName = streamOption ? streamOption.textContent : '';
            filters.push(`stream: "${streamName}"`);
        }
        summaryText += ` (filtered by ${filters.join(', ')})`;
    }
    
    summaryElement.textContent = summaryText;
}

function clearFilters() {
    document.querySelector('.search-input').value = '';
    document.getElementById('streamFilter').value = '';
    document.getElementById('showInactive').checked = false;
    filterClasses();
}

function toggleInactiveClasses() {
    const showInactive = document.getElementById('showInactive').checked;
    
    // Reload the page with a parameter to show inactive classes
    const url = new URL(window.location);
    if (showInactive) {
        url.searchParams.set('show_inactive', '1');
    } else {
        url.searchParams.delete('show_inactive');
    }
    window.location.href = url.toString();
}

function editClass(btn) {
    // Get data from button dataset
    if (btn && btn.dataset) {
        var classId = btn.dataset.id;
        var name = btn.dataset.name || '';
        var divisions = btn.dataset.divisions || '1';
        var total = btn.dataset.total || '0';
        var stream = btn.dataset.stream || '';

        // Populate the existing modal with data
        document.getElementById('edit_class_id').value = classId;
        document.getElementById('edit_class_name').value = name;
        document.getElementById('edit_total_capacity').value = total;
        document.getElementById('edit_divisions_count').value = divisions;
        
        // Set the selected stream in the dropdown
        var streamSelect = document.getElementById('edit_stream_id');
        if (streamSelect && stream) {
            streamSelect.value = stream;
        }

        // Show the modal
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modal = new bootstrap.Modal(document.getElementById('editClassModal'));
            modal.show();
        } else {
            alert('Open edit modal for: ' + name);
        }
    } else {
        alert('Edit functionality will be implemented here for class ID: ' + btn);
    }
}

// Preview class names as user types
const totalCapacityEl = document.getElementById('total_capacity');
const programCodeEl = document.getElementById('program_code');
const levelIdEl = document.getElementById('level_id');
if (totalCapacityEl) totalCapacityEl.addEventListener('input', updateClassPreview);
if (programCodeEl) programCodeEl.addEventListener('change', updateClassPreview);
if (levelIdEl) levelIdEl.addEventListener('change', updateClassPreview);


function updateClassPreview() {
    const capacity = parseInt(document.getElementById('total_capacity').value) || 0;
    const programSelect = document.getElementById('program_code');
    const levelSelect = document.getElementById('level_id');
    const previewDiv = document.getElementById('classPreview');
    const previewText = document.getElementById('previewText');
    
    if (capacity > 0 && programSelect.value && levelSelect.value) {
        const selectedOption = programSelect.options[programSelect.selectedIndex];
        // Prefer program code (data-code) for naming, fall back to department short (data-dept)
        const deptShort = selectedOption.dataset.code || selectedOption.dataset.dept || selectedOption.text.match(/\(([^)]+)\)/)?.[1] || 'PROG';
        const levelText = levelSelect.options[levelSelect.selectedIndex].text;
        
        // Extract just the number from level text (e.g., "Level 100" -> "100")
        const levelNumber = levelText.match(/\d+/)?.[0] || levelText.replace(/\D/g, '');
        
        const numClasses = Math.ceil(capacity / 100);
        const labels = typeof generateDivisionLabels === 'function' ? generateDivisionLabels(numClasses) : (function(){
            const out = [];
            for (let i=0;i<numClasses;i++){ out.push(String.fromCharCode(65 + (i%26))); }
            return out;
        })();

        // Format sample as "DEPT LEVELLETTER" e.g. "ITE 100A"
        const sample = labels.map(l => deptShort + ' ' + levelNumber + l);

        previewText.textContent = numClasses + ' classes — Sample: ' + sample.slice(0, 10).join(', ') + (sample.length > 10 ? ', ...' : '');
        // Update hidden divisions count input
        var divCountEl = document.getElementById('divisions_count');
        if (divCountEl) divCountEl.value = numClasses;
        previewDiv.style.display = 'block';
    } else {
        previewDiv.style.display = 'none';
    }
}
</script>

<script>
// Show class sections modal and generate letter labels A..Z, AA.. for counts > 26
function generateDivisionLabels(count) {
    const labels = [];
    for (let i = 0; i < count; i++) {
        let n = i;
        let label = '';
        while (true) {
            label = String.fromCharCode(65 + (n % 26)) + label;
            n = Math.floor(n / 26) - 1;
            if (n < 0) break;
        }
        labels.push(label);
    }
    return labels;
}

function showDivisions(deptCode, levelNum, count) {
    const labels = generateDivisionLabels(count || 1);
    const container = document.getElementById('divisionsList');
    container.innerHTML = '';
    const ul = document.createElement('ul');
    ul.className = 'list-group';
    labels.forEach((lab, idx) => {
        const li = document.createElement('li');
        li.className = 'list-group-item';
        const fullName = (deptCode ? deptCode + ' ' : '') + levelNum + lab; // e.g., ITE 100A
        li.innerHTML = '<div><strong>' + fullName + '</strong><br/><small>Section ' + (idx+1) + '</small></div>';
        ul.appendChild(li);
    });
    container.appendChild(ul);
    var el = document.getElementById('divisionsModal');
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(el).show();
    } else {
        alert('Classes:\n' + labels.join(', '));
    }
}
</script>
