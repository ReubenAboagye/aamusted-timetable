<?php
// Public extraction page for students, departments, lecturers
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
$selected_version = isset($_GET['version']) ? $_GET['version'] : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';

// IDs for specific role
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$lecturer_id = isset($_GET['lecturer_id']) ? intval($_GET['lecturer_id']) : 0;
$division_label = isset($_GET['division_label']) ? $_GET['division_label'] : '';

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
	include 'includes/sidebar.php';
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

// Load versions for the selected stream and semester
$versions_result = null;
if ($selected_stream > 0 && $selected_semester > 0) {
	$semester_text = ($selected_semester == 1) ? 'first' : 'second';
	$stmt = $conn->prepare("SELECT DISTINCT version FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN classes c ON cc.class_id = c.id WHERE c.stream_id = ? AND t.semester = ? AND t.version IS NOT NULL ORDER BY t.version DESC");
	if ($stmt) {
		$stmt->bind_param('is', $selected_stream, $semester_text);
		$stmt->execute();
		$versions_result = $stmt->get_result();
		$stmt->close();
	}
}

// --- Build query for timetable display/export when all needed filters are present
$timetable_rows = [];
$can_query = ($selected_stream > 0 && $selected_semester > 0 && in_array($role, ['class','department','lecturer','full']));

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
		"t.division_label",
		"co.code AS course_code",
		"co.name AS course_name",
		"IFNULL(l.name, '') AS lecturer_name",
		"r.name AS room_name",
		"r.capacity AS room_capacity",
		"(SELECT COUNT(*) FROM lecturer_courses lc2 WHERE lc2.course_id = co.id AND lc2.is_active = 1) as lecturer_count"
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

	// Convert semester number to text
	$semester_text = ($selected_semester == 1) ? 'first' : 'second';
	
	$where = ["c.stream_id = ?", "t.semester = ?"];
	$params = [$selected_stream, $semester_text];
	$types = "is";
	
	// Add version filter if specified
	if (!empty($selected_version)) {
		$where[] = "t.version = ?";
		$params[] = $selected_version;
		$types .= "s";
	}

	if ($role === 'class' && $class_id > 0) {
		$where[] = "c.id = ?";
		$params[] = $class_id;
		$types .= "i";
		
		// Add division filter if specified
		if (!empty($division_label)) {
			$where[] = "t.division_label = ?";
			$params[] = $division_label;
			$types .= "s";
		}
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

	// Order by day, start time, class name, division label, then course code so divisions appear grouped
	$sql = "SELECT " . implode(",\n\t\t", $select_parts) . "\nFROM timetable t\n\t" . implode("\n\t", $joins) . "\nWHERE " . implode(" AND ", $where) . "\nORDER BY d.id, ts.start_time, c.name, t.division_label, co.code";

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
			$filename = 'timetable_export';
			if ($role === 'class' && !empty($division_label)) {
				$filename .= '_division_' . $division_label;
			}
			header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
			$out = fopen('php://output', 'w');
			fputcsv($out, ['Day', 'Start', 'End', 'Class', 'Division', 'Course Code', 'Course Name', 'Lecturer', 'Room', 'Capacity']);
			$lastDay = null;
			$lastStart = null;
			$lastEnd = null;
			foreach ($timetable_rows as $r) {
				$isNewDay = ($r['day_name'] !== $lastDay);
				$dayCell = $isNewDay ? $r['day_name'] : '';

				// Merge period labels within the same day: only show Start/End when they change
				if ($isNewDay) {
					$startCell = $r['start_time'];
					$endCell = $r['end_time'];
					$lastStart = $r['start_time'];
					$lastEnd = $r['end_time'];
				} else {
					if ($r['start_time'] === $lastStart && $r['end_time'] === $lastEnd) {
						$startCell = '';
						$endCell = '';
					} else {
						$startCell = $r['start_time'];
						$endCell = $r['end_time'];
						$lastStart = $r['start_time'];
						$lastEnd = $r['end_time'];
					}
				}

				fputcsv($out, [
					$dayCell,
					$startCell,
					$endCell,
					$r['class_name'],
					$r['division_label'] ?: '',
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
/* Division header styling */
.division-header {
	background: linear-gradient(135deg, #007bff, #0056b3) !important;
	color: white !important;
	font-weight: bold;
	text-align: center;
	padding: 12px !important;
}

.division-separator {
	background-color: #f8f9fa !important;
	height: 2px !important;
	padding: 0 !important;
}

.division-badge {
	font-size: 0.9em;
	padding: 4px 8px;
}
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
				<div class="col-md-3">
					<label for="version" class="form-label">Version (Optional)</label>
					<div class="position-relative">
						<select name="version" id="version" class="form-select">
							<option value="">Latest version</option>
							<?php if ($versions_result && $versions_result->num_rows > 0): ?>
								<?php while ($v = $versions_result->fetch_assoc()): ?>
									<option value="<?php echo htmlspecialchars($v['version']); ?>" <?php echo ($selected_version == $v['version']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['version']); ?></option>
								<?php endwhile; ?>
							<?php endif; ?>
						</select>
						<div id="version-loading" class="position-absolute top-50 end-0 translate-middle-y me-3" style="display: none;">
							<div class="spinner-border spinner-border-sm text-primary" role="status">
								<span class="visually-hidden">Loading...</span>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-3">
					<label class="form-label d-block">Who is extracting?</label>
					<div class="btn-group" role="group">
						<input type="radio" class="btn-check" name="role" id="roleClass" value="class" <?php echo ($role==='class')?'checked':''; ?> autocomplete="off">
						<label class="btn btn-outline-primary" for="roleClass"><i class="fas fa-user-graduate me-1"></i>Student/Class</label>
						<input type="radio" class="btn-check" name="role" id="roleDept" value="department" <?php echo ($role==='department')?'checked':''; ?> autocomplete="off">
						<label class="btn btn-outline-primary" for="roleDept"><i class="fas fa-building me-1"></i>Department</label>
						<input type="radio" class="btn-check" name="role" id="roleLect" value="lecturer" <?php echo ($role==='lecturer')?'checked':''; ?> autocomplete="off">
						<label class="btn btn-outline-primary" for="roleLect"><i class="fas fa-chalkboard-teacher me-1"></i>Lecturer</label>
						<input type="radio" class="btn-check" name="role" id="roleFull" value="full" <?php echo ($role==='full')?'checked':''; ?> autocomplete="off">
						<label class="btn btn-outline-primary" for="roleFull"><i class="fas fa-globe me-1"></i>Full Timetable</label>
					</div>
				</div>

				<!-- Role-specific selectors -->
				<div class="col-12">
					<div class="row g-3">
						<div class="col-md-6 role-block role-class" style="display: <?php echo ($role==='class')?'block':'none'; ?>;">
							<label for="class_id" class="form-label">Class</label>
							<div class="position-relative">
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
								<div id="class-loading" class="position-absolute top-50 end-0 translate-middle-y me-3" style="display: none;">
									<div class="spinner-border spinner-border-sm text-primary" role="status">
										<span class="visually-hidden">Loading...</span>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-6 role-block role-class" style="display: <?php echo ($role==='class')?'block':'none'; ?>;">
							<label for="division_label" class="form-label">Division (Optional)</label>
							<div class="position-relative">
								<select name="division_label" id="division_label" class="form-select">
									<option value="">All divisions</option>
									<?php if ($class_id > 0): ?>
										<?php
										// Get available divisions for the selected class
										$div_stmt = $conn->prepare("SELECT DISTINCT t.division_label FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id WHERE cc.class_id = ? AND t.division_label IS NOT NULL ORDER BY t.division_label");
										if ($div_stmt) {
											$div_stmt->bind_param('i', $class_id);
											$div_stmt->execute();
											$div_result = $div_stmt->get_result();
											while ($div_row = $div_result->fetch_assoc()) {
												$selected = ($division_label == $div_row['division_label']) ? 'selected' : '';
												echo '<option value="' . htmlspecialchars($div_row['division_label']) . '" ' . $selected . '>' . htmlspecialchars($div_row['division_label']) . '</option>';
											}
											$div_stmt->close();
										}
										?>
									<?php endif; ?>
								</select>
								<div id="division-loading" class="position-absolute top-50 end-0 translate-middle-y me-3" style="display: none;">
									<div class="spinner-border spinner-border-sm text-primary" role="status">
										<span class="visually-hidden">Loading...</span>
									</div>
								</div>
							</div>
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
				<h5 class="m-0">
					Results
					<?php if (!empty($selected_version)): ?>
						<span class="badge bg-info ms-2">Version: <?php echo htmlspecialchars($selected_version); ?></span>
					<?php else: ?>
						<span class="badge bg-secondary ms-2">Latest Version</span>
					<?php endif; ?>
					<?php if ($role === 'class' && !empty($division_label)): ?>
						<span class="badge bg-primary ms-2">Division <?php echo htmlspecialchars($division_label); ?></span>
					<?php endif; ?>
				</h5>
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
							<th>Division</th>
							<th>Course</th>
							<th>Lecturer</th>
							<th>Room</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($timetable_rows)): ?>
							<?php
							// Group entries by numeric level (e.g., 100,200,300...) then by division
							$levels = [];
							foreach ($timetable_rows as $row) {
								// Extract numeric part from class name (e.g., 'ADT 100' -> 100)
								$levelText = $row['class_name'] ?? '';
								$levelNum = 0;
								if (preg_match('/(\d{2,3})/', $levelText, $m)) {
									$raw = (int)$m[1];
									$levelNum = ($raw > 0 && $raw < 10) ? $raw * 100 : $raw;
								} else {
									// Fallback to a high number so unknown levels appear last
									$levelNum = 9999;
								}
								$division = $row['division_label'] ?: 'No Division';
								if (!isset($levels[$levelNum])) $levels[$levelNum] = [];
								if (!isset($levels[$levelNum][$division])) $levels[$levelNum][$division] = [];
								$levels[$levelNum][$division][] = $row;
							}
							// Sort levels ascending (100,200,...)
							ksort($levels);
							$is_first_level = true;
							foreach ($levels as $levelNum => $divisions):
								// Render level header
								if (!$is_first_level) {
									// separator between levels
									?>
									<tr><td colspan="8" class="division-separator"></td></tr>
									<?php
								}
								?>
								<tr class="division-header">
									<td colspan="8"><i class="fas fa-layer-group me-2"></i>Level <?php echo htmlspecialchars($levelNum); ?></td>
								</tr>
								<?php
								// Sort divisions alphabetically
								ksort($divisions);
								foreach ($divisions as $division => $rows):
									// division header
									?>
									<tr class="division-header">
										<td colspan="8"><i class="fas fa-users me-2"></i><?php echo htmlspecialchars($rows[0]['class_name']); ?> - Division <?php echo htmlspecialchars($division); ?></td>
									</tr>
									<?php foreach ($rows as $row): ?>
<?php
$isNewDay = ($row['day_name'] !== ($lastDay ?? null));
$dayCell = $isNewDay ? $row['day_name'] : '';
if ($isNewDay) {
	$startCell = $row['start_time'];
	$endCell = $row['end_time'];
	$lastStart = $row['start_time'];
	$lastEnd = $row['end_time'];
} else {
	if ($row['start_time'] === ($lastStart ?? null) && $row['end_time'] === ($lastEnd ?? null)) {
		$startCell = '';
		$endCell = '';
	} else {
		$startCell = $row['start_time'];
		$endCell = $row['end_time'];
		$lastStart = $row['start_time'];
		$lastEnd = $row['end_time'];
	}
}
?>
									<tr>
										<td><?php echo htmlspecialchars($dayCell); ?></td>
										<td><?php echo htmlspecialchars($startCell); ?></td>
										<td><?php echo htmlspecialchars($endCell); ?></td>
										<td><strong><?php echo htmlspecialchars($row['class_name']); ?></strong></td>
										<td><span class="badge bg-primary division-badge"><?php echo htmlspecialchars($division); ?></span></td>
										<td><?php echo htmlspecialchars(($row['course_code'] ? ($row['course_code'] . ' - ') : '') . $row['course_name']); ?></td>
										<td><?php 
											if ($row['lecturer_count'] > 1) {
												echo 'Lecturer: multiple lecturers';
											} else {
												echo htmlspecialchars($row['lecturer_name']);
											}
										?></td>
										<td><?php echo htmlspecialchars($row['room_name']); ?></td>
									</tr>
<?php $lastDay = $row['day_name']; endforeach; ?>
								<?php
								$is_first_level = false;
								endforeach;
							endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="8" class="empty-state">
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
		// Hide all role blocks and disable their fields to avoid HTML5 required blocking submission
		document.querySelectorAll('.role-block').forEach(function(el){
			el.style.display = 'none';
			el.querySelectorAll('select, input').forEach(function(ctrl){
				ctrl.disabled = true;
				ctrl.removeAttribute('required');
			});
		});
		// Show and enable fields for the active role; set required only where needed
		if (roleVal === 'class') {
			var blocks = document.querySelectorAll('.role-class');
			blocks.forEach(function(b){
				b.setAttribute('style','display:block;');
				b.querySelectorAll('select, input').forEach(function(ctrl){ ctrl.disabled = false; });
			});
			var cls = document.getElementById('class_id');
			if (cls) cls.setAttribute('required','required');
		}
		if (roleVal === 'department') {
			var b = document.querySelector('.role-department');
			if (b) {
				b.setAttribute('style','display:block;');
				b.querySelectorAll('select, input').forEach(function(ctrl){ ctrl.disabled = false; });
			}
			var dep = document.getElementById('department_id');
			if (dep) dep.setAttribute('required','required');
		}
		if (roleVal === 'lecturer') {
			var b2 = document.querySelector('.role-lecturer');
			if (b2) {
				b2.setAttribute('style','display:block;');
				b2.querySelectorAll('select, input').forEach(function(ctrl){ ctrl.disabled = false; });
			}
			var lec = document.getElementById('lecturer_id');
			if (lec) lec.setAttribute('required','required');
		}
		// For 'full' role, we intentionally keep all role-specific selectors hidden
	}
	Array.from(document.querySelectorAll('input[name="role"]')).forEach(function(radio){
		radio.addEventListener('change', updateRoleBlocks);
	});
	updateRoleBlocks();

	// Function to load versions when stream and semester change
	function loadVersions() {
		var streamId = document.getElementById('stream_id').value;
		var semesterId = document.getElementById('semester').value;
		var versionSelect = document.getElementById('version');
		
		if (versionSelect) {
			// Clear existing options except the first one
			versionSelect.innerHTML = '<option value="">Latest version</option>';
			
			if (streamId && semesterId) {
				// Show loading state
				versionSelect.innerHTML = '<option value="">Loading versions...</option>';
				versionSelect.disabled = true;
				document.getElementById('version-loading').style.display = 'block';
				
				// Fetch versions for the selected stream and semester
				fetch('get_timetable_versions.php?stream_id=' + streamId + '&semester=' + semesterId)
					.then(response => response.json())
					.then(data => {
						versionSelect.innerHTML = '<option value="">Latest version</option>';
						if (data.success && data.versions.length > 0) {
							data.versions.forEach(function(version) {
								var option = document.createElement('option');
								option.value = version;
								option.textContent = version;
								versionSelect.appendChild(option);
							});
						} else {
							var option = document.createElement('option');
							option.value = '';
							option.textContent = 'No versions found';
							versionSelect.appendChild(option);
						}
						versionSelect.disabled = false;
						document.getElementById('version-loading').style.display = 'none';
					})
					.catch(error => {
						console.error('Error loading versions:', error);
						versionSelect.innerHTML = '<option value="">Error loading versions</option>';
						versionSelect.disabled = false;
						document.getElementById('version-loading').style.display = 'none';
					});
			} else {
				document.getElementById('version-loading').style.display = 'none';
			}
		}
	}

	// When stream changes, dynamically load classes for that stream
	var streamSelect = document.getElementById('stream_id');
	if (streamSelect) {
		streamSelect.addEventListener('change', function(){
			var streamId = this.value;
			var classSelect = document.getElementById('class_id');
			
			if (classSelect) {
				// Clear existing options except the first one
				classSelect.innerHTML = '<option value="">Select class</option>';
				
				if (streamId) {
					// Show loading state
					classSelect.innerHTML = '<option value="">Loading classes...</option>';
					classSelect.disabled = true;
					document.getElementById('class-loading').style.display = 'block';
					
					// Fetch classes for the selected stream
					fetch('get_filtered_classes.php?stream_id=' + streamId)
						.then(response => response.json())
						.then(data => {
							classSelect.innerHTML = '<option value="">Select class</option>';
							if (data.success && data.classes.length > 0) {
								data.classes.forEach(function(cls) {
									var option = document.createElement('option');
									option.value = cls.id;
									option.textContent = cls.name;
									classSelect.appendChild(option);
								});
							} else {
								var option = document.createElement('option');
								option.value = '';
								option.textContent = 'No classes found for this stream';
								classSelect.appendChild(option);
							}
							classSelect.disabled = false;
							document.getElementById('class-loading').style.display = 'none';
						})
						.catch(error => {
							console.error('Error loading classes:', error);
							classSelect.innerHTML = '<option value="">Error loading classes</option>';
							classSelect.disabled = false;
							document.getElementById('class-loading').style.display = 'none';
						});
				} else {
					document.getElementById('class-loading').style.display = 'none';
				}
			}
			
			// Also load versions when stream changes
			loadVersions();
		});
	}
	
	// When semester changes, load versions
	var semesterSelect = document.getElementById('semester');
	if (semesterSelect) {
		semesterSelect.addEventListener('change', loadVersions);
	}
	
	// When class changes, dynamically load divisions for that class
	var classSelect = document.getElementById('class_id');
	if (classSelect) {
		classSelect.addEventListener('change', function(){
			var classId = this.value;
			var divisionSelect = document.getElementById('division_label');
			
			if (divisionSelect) {
				// Clear existing options except the first one
				divisionSelect.innerHTML = '<option value="">All divisions</option>';
				
				if (classId) {
					// Show loading state
					divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';
					divisionSelect.disabled = true;
					document.getElementById('division-loading').style.display = 'block';
					
					// Fetch divisions for the selected class
					fetch('get_class_divisions.php?class_id=' + classId)
						.then(response => response.json())
						.then(data => {
							divisionSelect.innerHTML = '<option value="">All divisions</option>';
							if (data.success && data.divisions.length > 0) {
								data.divisions.forEach(function(division) {
									var option = document.createElement('option');
									option.value = division;
									option.textContent = division;
									divisionSelect.appendChild(option);
								});
							} else {
								var option = document.createElement('option');
								option.value = '';
								option.textContent = 'No divisions found for this class';
								divisionSelect.appendChild(option);
							}
							divisionSelect.disabled = false;
							document.getElementById('division-loading').style.display = 'none';
						})
						.catch(error => {
							console.error('Error loading divisions:', error);
							divisionSelect.innerHTML = '<option value="">Error loading divisions</option>';
							divisionSelect.disabled = false;
							document.getElementById('division-loading').style.display = 'none';
						});
				} else {
					document.getElementById('division-loading').style.display = 'none';
				}
			}
		});
	}
});
</script>

<?php include 'includes/footer.php'; ?>


