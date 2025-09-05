<?php
// Public extraction page (no sidebar) for students, departments, lecturers
// Step 1: choose stream, semester; Step 2: choose role + specific filter; Step 3: view/export

include 'connect.php';

// Start output buffering so we can safely send export headers (PDF/CSV)
if (!ob_get_level()) {
	ob_start();
}

$pageTitle = 'Extract Timetable';
$show_admin_jobs_modal = false; // Disable admin jobs JSON polling on this public page

// --- Read filters
$selected_stream = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$role = isset($_GET['role']) ? $_GET['role'] : '';

// IDs for specific role
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$lecturer_id = isset($_GET['lecturer_id']) ? intval($_GET['lecturer_id']) : 0;

// Export handling
$do_export = isset($_GET['export']) && $_GET['export'] === '1';
$export_format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// If PDF export is requested on this page, redirect to the dedicated endpoint
if ($do_export && strtolower($export_format) === 'pdf') {
	$qs = $_GET;
	unset($qs['export']);
	unset($qs['format']);
	$redirectUrl = 'export_timetable_pdf.php?' . http_build_query($qs);
	header('Location: ' . $redirectUrl);
	exit;
}

// Only include header/layout for non-export (view) requests
if (!$do_export) {
	include 'includes/header.php';
}

// --- Load options for selectors
$streams_result = $conn->query("SELECT id, name FROM streams WHERE is_active = 1 ORDER BY name");
$semesters = [1 => 'Semester 1', 2 => 'Semester 2'];

// Load classes filtered by stream if provided
$classes_result = null;
if ($selected_stream > 0) {
	$stmt = $conn->prepare("SELECT id, name FROM classes WHERE is_active = 1 AND stream_id = ? ORDER BY name");
	if ($stmt) {
		$stmt->bind_param('i', $selected_stream);
		$stmt->execute();
		$classes_result = $stmt->get_result();
		$stmt->close();
	}
}

// Load departments
$departments_result = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");

// Load lecturers (global)
$lecturers_result = $conn->query("SELECT id, name FROM lecturers WHERE is_active = 1 ORDER BY name");

// --- Build query for timetable display/export when all needed filters are present
$timetable_rows = [];
$can_query = ($selected_stream > 0 && $selected_semester > 0 && in_array($role, ['class','department','lecturer']));

if ($can_query) {
	// Detect schema variants
	$has_class_course = false;
	$has_lecturer_course = false;
	$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
	if ($col && $col->num_rows > 0) { $has_class_course = true; }
	$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'lecturer_course_id'");
	if ($col && $col->num_rows > 0) { $has_lecturer_course = true; }

	$select_parts = [
		"d.name AS day_name",
		"ts.start_time",
		"ts.end_time",
		"c.name AS class_name",
		"co.code AS course_code",
		"co.name AS course_name",
		"IFNULL(l.name, '') AS lecturer_name",
		"r.name AS room_name",
		"r.capacity AS room_capacity"
	];

	$joins = [];
	if ($has_class_course) {
		$joins[] = "JOIN class_courses cc ON t.class_course_id = cc.id";
		$joins[] = "JOIN classes c ON cc.class_id = c.id";
		$joins[] = "JOIN courses co ON cc.course_id = co.id";
	} else {
		$joins[] = "JOIN classes c ON t.class_id = c.id";
		$joins[] = "JOIN courses co ON t.course_id = co.id";
	}

	if ($has_lecturer_course) {
		$joins[] = "LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id";
		$joins[] = "LEFT JOIN lecturers l ON lc.lecturer_id = l.id";
	} else {
		$joins[] = "LEFT JOIN lecturers l ON t.lecturer_id = l.id";
	}

	$joins[] = "JOIN days d ON t.day_id = d.id";
	$joins[] = "JOIN time_slots ts ON t.time_slot_id = ts.id";
	$joins[] = "JOIN rooms r ON t.room_id = r.id";

	$where = ["c.stream_id = ?", "t.semester = ?"];
	$params = [$selected_stream, $selected_semester];
	$types = "ii";

	if ($role === 'class' && $class_id > 0) {
		$where[] = "c.id = ?";
		$params[] = $class_id;
		$types .= "i";
	}

	if ($role === 'department' && $department_id > 0) {
		$joins[] = "JOIN programs p ON c.program_id = p.id";
		$where[] = "p.department_id = ?";
		$params[] = $department_id;
		$types .= "i";
	}

	if ($role === 'lecturer' && $lecturer_id > 0) {
		// If old schema, l.id may be null only when no lecturer assigned; filter still works
		$where[] = "l.id = ?";
		$params[] = $lecturer_id;
		$types .= "i";
	}

	$sql = "SELECT " . implode(",\n\t\t", $select_parts) . "\nFROM timetable t\n\t" . implode("\n\t", $joins) . "\nWHERE " . implode(" AND ", $where) . "\nORDER BY d.id, ts.start_time, c.name, co.code";

	$stmt = $conn->prepare($sql);
	if ($stmt) {
		if (!empty($params)) {
			$stmt->bind_param($types, ...$params);
		}
		$stmt->execute();
		$res = $stmt->get_result();
		while ($row = $res->fetch_assoc()) {
			$timetable_rows[] = $row;
		}
		$stmt->close();
	}

	// Export if requested
	if ($do_export && !empty($timetable_rows)) {
		if ($export_format === 'csv') {
			header('Content-Type: text/csv; charset=utf-8');
			header('Content-Disposition: attachment; filename="timetable_export.csv"');
			$out = fopen('php://output', 'w');
			fputcsv($out, ['Day', 'Start', 'End', 'Class', 'Course Code', 'Course Name', 'Lecturer', 'Room', 'Capacity']);
			$lastDay = null;
			foreach ($timetable_rows as $r) {
				$dayCell = ($r['day_name'] !== $lastDay) ? $r['day_name'] : '';
				fputcsv($out, [
					$dayCell,
					$r['start_time'],
					$r['end_time'],
					$r['class_name'],
					$r['course_code'],
					$r['course_name'],
					$r['lecturer_name'],
					$r['room_name'],
					$r['room_capacity'],
				]);
				$lastDay = $r['day_name'];
			}
			fclose($out);
			exit;
		}
	}
}
?>

<style>
/* Make content full-width since we have no sidebar here */
#mainContent { margin-left: 0 !important; }
#footer { left: 0 !important; }
</style>

<div class="main-content" id="mainContent">
	<div class="table-container">
		<div class="table-header d-flex justify-content-between align-items-center">
			<h4><i class="fas fa-download me-2"></i>Extract Timetable</h4>
		</div>
		<div class="p-3">
			<form method="GET" action="extract_timetable.php" class="row g-3">
				<div class="col-md-4">
					<label for="stream_id" class="form-label">Stream</label>
					<select name="stream_id" id="stream_id" class="form-select" required>
						<option value="">Select stream</option>
						<?php if ($streams_result && $streams_result->num_rows > 0): ?>
							<?php while ($s = $streams_result->fetch_assoc()): ?>
								<option value="<?php echo $s['id']; ?>" <?php echo ($selected_stream == $s['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
							<?php endwhile; ?>
						<?php endif; ?>
					</select>
				</div>
				<div class="col-md-3">
					<label for="semester" class="form-label">Semester</label>
					<select name="semester" id="semester" class="form-select" required>
						<option value="">Select semester</option>
						<?php foreach ($semesters as $k => $label): ?>
							<option value="<?php echo $k; ?>" <?php echo ($selected_semester == $k) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-5">
					<label class="form-label d-block">Who is extracting?</label>
					<div class="btn-group" role="group">
						<input type="radio" class="btn-check" name="role" id="roleClass" value="class" <?php echo ($role==='class')?'checked':''; ?> autocomplete="off">
						<label class="btn btn-outline-primary" for="roleClass"><i class="fas fa-user-graduate me-1"></i>Student/Class</label>
						<input type="radio" class="btn-check" name="role" id="roleDept" value="department" <?php echo ($role==='department')?'checked':''; ?> autocomplete="off">
						<label class="btn btn-outline-primary" for="roleDept"><i class="fas fa-building me-1"></i>Department</label>
						<input type="radio" class="btn-check" name="role" id="roleLect" value="lecturer" <?php echo ($role==='lecturer')?'checked':''; ?> autocomplete="off">
						<label class="btn btn-outline-primary" for="roleLect"><i class="fas fa-chalkboard-teacher me-1"></i>Lecturer</label>
					</div>
				</div>

				<!-- Role-specific selectors -->
				<div class="col-12">
					<div class="row g-3">
						<div class="col-md-6 role-block role-class" style="display: <?php echo ($role==='class')?'block':'none'; ?>;">
							<label for="class_id" class="form-label">Class</label>
							<select name="class_id" id="class_id" class="form-select" <?php echo ($role==='class')?'required':''; ?>>
								<option value="">Select class</option>
								<?php if ($classes_result && $classes_result->num_rows > 0): ?>
									<?php while ($c = $classes_result->fetch_assoc()): ?>
										<option value="<?php echo $c['id']; ?>" <?php echo ($class_id == $c['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
									<?php endwhile; ?>
								<?php elseif ($selected_stream > 0): ?>
									<option value="">No classes found for this stream</option>
								<?php endif; ?>
							</select>
						</div>
						<div class="col-md-6 role-block role-department" style="display: <?php echo ($role==='department')?'block':'none'; ?>;">
							<label for="department_id" class="form-label">Department</label>
							<select name="department_id" id="department_id" class="form-select" <?php echo ($role==='department')?'required':''; ?>>
								<option value="">Select department</option>
								<?php if ($departments_result && $departments_result->num_rows > 0): ?>
									<?php while ($d = $departments_result->fetch_assoc()): ?>
										<option value="<?php echo $d['id']; ?>" <?php echo ($department_id == $d['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option>
									<?php endwhile; ?>
								<?php endif; ?>
							</select>
						</div>
						<div class="col-md-6 role-block role-lecturer" style="display: <?php echo ($role==='lecturer')?'block':'none'; ?>;">
							<label for="lecturer_id" class="form-label">Lecturer</label>
							<select name="lecturer_id" id="lecturer_id" class="form-select" <?php echo ($role==='lecturer')?'required':''; ?>>
								<option value="">Select lecturer</option>
								<?php if ($lecturers_result && $lecturers_result->num_rows > 0): ?>
									<?php while ($l = $lecturers_result->fetch_assoc()): ?>
										<option value="<?php echo $l['id']; ?>" <?php echo ($lecturer_id == $l['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($l['name']); ?></option>
									<?php endwhile; ?>
								<?php endif; ?>
							</select>
						</div>
					</div>
				</div>

				<div class="col-md-3 d-flex align-items-end">
					<button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>View Timetable</button>
				</div>
			</form>
		</div>
	</div>

	<?php if ($can_query): ?>
		<div class="table-container mt-3">
			<div class="table-header d-flex justify-content-between align-items-center">
				<h5 class="m-0">Results</h5>
				<div>
					<?php if (!empty($timetable_rows)): ?>
						<a class="btn btn-sm btn-outline-success me-2" href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'export=1&format=csv'); ?>" target="_blank"><i class="fas fa-file-csv me-2"></i>Export CSV</a>
						<?php
							// Build URL for the dedicated PDF endpoint to avoid inline JS/CSS parsing issues
							$qs = $_GET; $qs['export'] = '1'; $qs['format'] = 'pdf';
							$pdfUrl = 'export_timetable_pdf.php?' . http_build_query($qs);
						?>
						<a class="btn btn-sm btn-outline-danger" href="<?php echo htmlspecialchars($pdfUrl); ?>" target="_blank"><i class="fas fa-file-pdf me-2"></i>Export PDF</a>
					<?php endif; ?>
				</div>
			</div>
			<div class="table-responsive">
				<table class="table">
					<thead>
						<tr>
							<th>Day</th>
							<th>Start</th>
							<th>End</th>
							<th>Class</th>
							<th>Course</th>
							<th>Lecturer</th>
							<th>Room</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($timetable_rows)): ?>
							<?php foreach ($timetable_rows as $row): ?>
								<tr>
									<td><?php echo htmlspecialchars($row['day_name']); ?></td>
									<td><?php echo htmlspecialchars($row['start_time']); ?></td>
									<td><?php echo htmlspecialchars($row['end_time']); ?></td>
									<td><strong><?php echo htmlspecialchars($row['class_name']); ?></strong></td>
									<td><?php echo htmlspecialchars(($row['course_code'] ? ($row['course_code'] . ' - ') : '') . $row['course_name']); ?></td>
									<td><?php echo htmlspecialchars($row['lecturer_name']); ?></td>
									<td><?php echo htmlspecialchars($row['room_name']); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="7" class="empty-state">
									<i class="fas fa-info-circle"></i>
									<p>No timetable entries found for the selected filters.</p>
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>
</div>

<script>
// Toggle role blocks dynamically when radio changes
document.addEventListener('DOMContentLoaded', function() {
	function updateRoleBlocks() {
		var role = document.querySelector('input[name="role"]:checked');
		var roleVal = role ? role.value : '';
		document.querySelectorAll('.role-block').forEach(function(el){ el.style.display = 'none'; });
		if (roleVal === 'class') document.querySelector('.role-class')?.setAttribute('style','display:block;');
		if (roleVal === 'department') document.querySelector('.role-department')?.setAttribute('style','display:block;');
		if (roleVal === 'lecturer') document.querySelector('.role-lecturer')?.setAttribute('style','display:block;');
	}
	Array.from(document.querySelectorAll('input[name="role"]')).forEach(function(radio){
		radio.addEventListener('change', updateRoleBlocks);
	});
	updateRoleBlocks();

	// When stream changes, clear class selection (since classes are stream-specific)
	var streamSelect = document.getElementById('stream_id');
	if (streamSelect) {
		streamSelect.addEventListener('change', function(){
			var cls = document.getElementById('class_id'); if (cls) { cls.selectedIndex = 0; }
		});
	}
});
</script>

<?php include 'includes/footer.php'; ?>


