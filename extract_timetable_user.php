<?php
// Standalone extraction page for students and lecturers (AAMUSTED branding)
// Improved UI inspired by export_timetable.php: stats cards, export options, preview

include 'connect.php';

// Start output buffering so we can safely send export headers (PDF/CSV)
if (!ob_get_level()) {
	ob_start();
}

$pageTitle = 'AAMUSTED - Extract Timetable';

// --- Read filters
$selected_stream = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$role = isset($_GET['role']) ? $_GET['role'] : '';

// IDs for specific role
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
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

// Load lecturers (global)
$lecturers_result = $conn->query("SELECT id, name FROM lecturers WHERE is_active = 1 ORDER BY name");

// --- Build query for timetable display/export when needed filters are present
$timetable_rows = [];
$can_query = ($selected_stream > 0 && $selected_semester > 0 && in_array($role, ['class','lecturer']));

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
		// Add division filter if specified
		if (!empty($division_label)) {
			$where[] = "t.division_label = ?";
			$params[] = $division_label;
			$types .= "s";
		}
	}

	if ($role === 'lecturer' && $lecturer_id > 0) {
		$where[] = "l.id = ?";
		$params[] = $lecturer_id;
		$types .= "i";
	}

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

// Compute small stats similar to export_timetable.php for the UI cards
$total_entries = $conn->query("SELECT COUNT(*) as count FROM timetable")->fetch_assoc()['count'];
$col = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'");
$has_t_class_course = ($col && $col->num_rows > 0);
if ($has_t_class_course) {
	$total_classes = $conn->query("SELECT COUNT(DISTINCT c.id) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN classes c ON cc.class_id = c.id")->fetch_assoc()['count'];
	$total_courses = $conn->query("SELECT COUNT(DISTINCT co.id) as count FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN courses co ON cc.course_id = co.id")->fetch_assoc()['count'];
} else {
	$total_classes = $conn->query("SELECT COUNT(DISTINCT c.id) as count FROM timetable t JOIN classes c ON t.class_id = c.id")->fetch_assoc()['count'];
	$total_courses = $conn->query("SELECT COUNT(DISTINCT co.id) as count FROM timetable t JOIN courses co ON t.course_id = co.id")->fetch_assoc()['count'];
}
$days_result = $conn->query("SELECT id FROM days WHERE is_active = 1");
$working_days = $days_result ? $days_result->num_rows : 0;
if ($col) $col->close();

// Render improved standalone page
if (!$do_export) {
	header('Content-Type: text/html; charset=utf-8');
	?>
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title><?php echo htmlspecialchars($pageTitle); ?></title>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
		<style>
		:root { --primary-color: #0d6efd; --hover-color: #084298; }
		body { background: #f4f6f9; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; }
		.container-xl { max-width: 1100px; }
		.card { border-radius: 12px; box-shadow: 0 6px 20px rgba(12,15,20,0.06); }
		.brand { display:flex; align-items:center; gap:14px; }
		.brand img { height:56px; }
		.brand h2 { margin:0; color:var(--primary-color); font-size:20px; }
		.small-note { color:#6b7280; }
		.stat-card { background: linear-gradient(135deg, var(--primary-color) 0%, var(--hover-color) 100%); color: white; border-radius: 10px; padding: 18px; }
		.stat-number { font-size:1.8rem; font-weight:700; }
		.stat-label { font-size:0.9rem; opacity:0.9; }
		.table-responsive { border-radius: 8px; overflow: hidden; }
		.table thead th { background: linear-gradient(135deg, var(--primary-color) 0%, var(--hover-color) 100%); color: white; border: none; }
		.division-header { background: linear-gradient(135deg, #0d6efd, #084298); color: white; font-weight:700; text-align:center; padding:8px; }
		.division-separator { background-color: #eef2f8; height: 2px; }
		.division-badge { font-size:0.85em; padding:4px 8px; }
		.empty-state { text-align:center; padding:40px 20px; color:#6c757d; }
		.actions .btn { min-width:140px; }
		@media (max-width:767px) {
			.brand h2 { font-size:16px; }
		}
		</style>
	</head>
	<body>
	<div class="container-xl py-4">
		<div class="card p-3 mb-4">
			<div class="d-flex justify-content-between align-items-center">
				<div class="brand">
					<img src="images/aamusted-logo.png" alt="AAMUSTED Logo" onerror="this.style.display='none'" />
					<div>
						<h2>AAMUSTED Timetable Extract</h2>
						<div class="small-note">Students and lecturers can view or export timetables for their stream and semester.</div>
					</div>
				</div>
				<div class="text-end">
					<!-- Intentionally no link back to main project -->
					<div class="small-note">AAMUSTED</div>
				</div>
			</div>
		</div>

		<!-- Stats cards removed as requested -->

		<div class="card p-3 mt-4">
			<div class="row g-3">
				<div class="col-lg-9">
					<form method="GET" action="extract_timetable_user.php" class="row g-3">
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
							<label class="form-label d-block">I am</label>
							<div class="btn-group" role="group">
								<input type="radio" class="btn-check" name="role" id="roleClass" value="class" <?php echo ($role==='class')?'checked':''; ?> autocomplete="off">
								<label class="btn btn-outline-primary" for="roleClass"><i class="fas fa-user-graduate me-1"></i>Student / Class</label>
								<input type="radio" class="btn-check" name="role" id="roleLect" value="lecturer" <?php echo ($role==='lecturer')?'checked':''; ?> autocomplete="off">
								<label class="btn btn-outline-primary" for="roleLect"><i class="fas fa-chalkboard-teacher me-1"></i>Lecturer</label>
							</div>
						</div>

						<div class="col-12 role-fields mt-2">
							<div class="row g-3">
								<div class="col-md-4 role-block role-class" style="display: <?php echo ($role==='class')?'block':'none'; ?>;">
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
								<div class="col-md-4 role-block role-class" style="display: <?php echo ($role==='class')?'block':'none'; ?>;">
									<label for="division_label" class="form-label">Division (Optional)</label>
									<select name="division_label" id="division_label" class="form-select">
										<option value="">All divisions</option>
										<?php if ($class_id > 0): ?>
											<?php
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
								</div>
								<div class="col-md-4 role-block role-lecturer" style="display: <?php echo ($role==='lecturer')?'block':'none'; ?>;">
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

						<div class="col-12 d-flex align-items-center gap-2 actions">
							<button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>View Timetable</button>
							<?php if (!empty($timetable_rows)): ?>
								<a class="btn btn-outline-success" href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] . (strpos($_SERVER['REQUEST_URI'], '?') !== false ? '&' : '?') . 'export=1&format=csv'); ?>" target="_blank"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
								<?php $qs = $_GET; $qs['export'] = '1'; $qs['format'] = 'pdf'; $pdfUrl = 'export_timetable_pdf.php?' . http_build_query($qs); ?>
								<a class="btn btn-outline-danger" href="<?php echo htmlspecialchars($pdfUrl); ?>" target="_blank"><i class="fas fa-file-pdf me-1"></i>Export PDF</a>
							<?php endif; ?>
						</div>
					</form>
				</div>

				<div class="col-lg-3">
					<div class="card p-3">
						<h6 class="mb-2">Quick Info</h6>
						<p class="small-note mb-1">Use Stream + Semester to narrow results. Students choose their class; lecturers pick their name.</p>
						<hr />
						<p class="mb-1"><strong>Export</strong></p>
						<ul class="small-note mb-1">
							<li>CSV for spreadsheets</li>
							<li>PDF for printing</li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<?php if ($can_query): ?>
			<div class="card p-3 mt-4">
				<div class="table-responsive">
					<table class="table table-striped">
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
								$levels = [];
								foreach ($timetable_rows as $row) {
									$levelText = $row['class_name'] ?? '';
									$levelNum = 0;
									if (preg_match('/(\d{2,3})/', $levelText, $m)) {
										$raw = (int)$m[1];
										$levelNum = ($raw > 0 && $raw < 10) ? $raw * 100 : $raw;
									} else { $levelNum = 9999; }
									$division = $row['division_label'] ?: 'No Division';
									if (!isset($levels[$levelNum])) $levels[$levelNum] = [];
									if (!isset($levels[$levelNum][$division])) $levels[$levelNum][$division] = [];
									$levels[$levelNum][$division][] = $row;
								}
								ksort($levels);
								$is_first_level = true;
								foreach ($levels as $levelNum => $divisions):
									if (!$is_first_level) { echo '<tr><td colspan="8" class="division-separator"></td></tr>'; }
									echo '<tr class="division-header"><td colspan="8">Level ' . htmlspecialchars($levelNum) . '</td></tr>';
									ksort($divisions);
									foreach ($divisions as $division => $rows):
										echo '<tr class="division-header"><td colspan="8">' . htmlspecialchars($rows[0]['class_name']) . ' - Division ' . htmlspecialchars($division) . '</td></tr>';
										foreach ($rows as $row):
											$isNewDay = ($row['day_name'] !== ($lastDay ?? null));
											$dayCell = $isNewDay ? $row['day_name'] : '';
											if ($isNewDay) { $startCell = $row['start_time']; $endCell = $row['end_time']; $lastStart = $row['start_time']; $lastEnd = $row['end_time']; }
											else { if ($row['start_time'] === ($lastStart ?? null) && $row['end_time'] === ($lastEnd ?? null)) { $startCell = ''; $endCell = ''; } else { $startCell = $row['start_time']; $endCell = $row['end_time']; $lastStart = $row['start_time']; $lastEnd = $row['end_time']; } }
											echo '<tr>';
											echo '<td>' . htmlspecialchars($dayCell) . '</td>';
											echo '<td>' . htmlspecialchars($startCell) . '</td>';
											echo '<td>' . htmlspecialchars($endCell) . '</td>';
											echo '<td><strong>' . htmlspecialchars($row['class_name']) . '</strong></td>';
											echo '<td><span class="badge bg-primary division-badge">' . htmlspecialchars($division) . '</span></td>';
											echo '<td>' . htmlspecialchars(($row['course_code'] ? ($row['course_code'] . ' - ') : '') . $row['course_name']) . '</td>';
											echo '<td>' . htmlspecialchars($row['lecturer_name']) . '</td>';
											echo '<td>' . htmlspecialchars($row['room_name']) . '</td>';
											echo '</tr>';
										$lastDay = $row['day_name'];
										endforeach;
										$is_first_level = false;
									endforeach;
								endforeach;
							?>
							<?php else: ?>
								<tr><td colspan="8" class="empty-state"><i class="fas fa-info-circle"></i><div>No timetable entries found for the selected filters.</div></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>

	</div>

	<script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
	<script>
	// Toggle role blocks dynamically when radio changes
	document.addEventListener('DOMContentLoaded', function() {
		// track Choices instances by element id
		window.choicesInstances = window.choicesInstances || {};

		function initChoicesForSelect(id) {
			var el = document.getElementById(id);
			if (!el) return;
			// destroy previous instance if present
			if (window.choicesInstances[id]) {
				try { window.choicesInstances[id].destroy(); } catch (e) {}
			}
			// Create new Choices instance
			window.choicesInstances[id] = new Choices(el, { searchEnabled: true, shouldSort: false, itemSelectText: '' });
		}

		function enableChoices(id, enable) {
			if (!window.choicesInstances[id]) return;
			try {
				if (enable) {
					window.choicesInstances[id].enable();
				} else {
					window.choicesInstances[id].disable();
				}
			} catch (e) {
				console.log('Error enabling/disabling choices for ' + id + ':', e);
			}
		}

		function updateRoleBlocks() {
			var role = document.querySelector('input[name="role"]:checked');
			var roleVal = role ? role.value : '';
			
			console.log('Updating role blocks for:', roleVal);
			
			// Hide all role blocks and disable their fields
			document.querySelectorAll('.role-block').forEach(function(el){
				el.style.display = 'none';
				el.querySelectorAll('select, input').forEach(function(ctrl){ 
					ctrl.disabled = true; 
					ctrl.removeAttribute('required'); 
				});
			});
			
			// Show and enable fields for the active role
			if (roleVal === 'class') {
				console.log('Showing class blocks');
				var blocks = document.querySelectorAll('.role-class');
				blocks.forEach(function(b){ 
					b.style.display='block'; 
					b.querySelectorAll('select, input').forEach(function(ctrl){ ctrl.disabled = false; }); 
				});
				var cls = document.getElementById('class_id'); 
				if (cls) cls.setAttribute('required','required');
				
				// Enable choices with a small delay to ensure DOM is updated
				setTimeout(function() {
					enableChoices('class_id', true);
					enableChoices('division_label', true);
				}, 100);
			}
			if (roleVal === 'lecturer') {
				console.log('Showing lecturer blocks');
				var b2 = document.querySelector('.role-lecturer'); 
				if (b2) { 
					b2.style.display='block'; 
					b2.querySelectorAll('select, input').forEach(function(ctrl){ ctrl.disabled = false; }); 
				}
				var lec = document.getElementById('lecturer_id'); 
				if (lec) lec.setAttribute('required','required');
				
				// Enable choices with a small delay to ensure DOM is updated
				setTimeout(function() {
					enableChoices('lecturer_id', true);
				}, 100);
			}
			
			// Disable choices for inactive roles
			if (roleVal !== 'class') { 
				enableChoices('class_id', false); 
				enableChoices('division_label', false); 
			}
			if (roleVal !== 'lecturer') { 
				enableChoices('lecturer_id', false); 
			}
		}
		Array.from(document.querySelectorAll('input[name="role"]')).forEach(function(radio){ radio.addEventListener('change', updateRoleBlocks); });

		// Initialize Choices on selects that exist
		initChoicesForSelect('class_id');
		initChoicesForSelect('lecturer_id');
		initChoicesForSelect('division_label');

		updateRoleBlocks();

		// When stream changes, dynamically load classes for that stream
		var streamSelect = document.getElementById('stream_id');
		if (streamSelect) {
			streamSelect.addEventListener('change', function(){
				var streamId = this.value;
				var classSelect = document.getElementById('class_id');
				if (classSelect) {
					// Clear existing choices instance
					if (window.choicesInstances['class_id']) { 
						try { window.choicesInstances['class_id'].destroy(); } catch (e) {} 
						window.choicesInstances['class_id'] = null; 
					}
					
					classSelect.innerHTML = '<option value="">Select class</option>';
					if (streamId) {
						classSelect.innerHTML = '<option value="">Loading classes...</option>';
						classSelect.disabled = true;
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
								// Re-initialize Choices with new options
								initChoicesForSelect('class_id');
								// Set up change listener for the new Choices instance
								setupClassChangeListener();
							})
							.catch(error => { 
								console.error('Error loading classes:', error); 
								classSelect.innerHTML = '<option value="">Error loading classes</option>'; 
								classSelect.disabled = false; 
								initChoicesForSelect('class_id');
								setupClassChangeListener();
							});
					} else {
						// Re-initialize Choices even when no stream selected
						initChoicesForSelect('class_id');
						setupClassChangeListener();
					}
				}
			});
		}

		// Function to set up class change listener using Choices.js events
		function setupClassChangeListener() {
			if (window.choicesInstances['class_id']) {
				window.choicesInstances['class_id'].passedElement.element.addEventListener('change', function(){
					var classId = this.value;
					var divisionSelect = document.getElementById('division_label');
					if (divisionSelect) {
						// Clear existing choices instance
						if (window.choicesInstances['division_label']) { 
							try { window.choicesInstances['division_label'].destroy(); } catch (e) {} 
							window.choicesInstances['division_label'] = null; 
						}
						
						divisionSelect.innerHTML = '<option value="">All divisions</option>';
						if (classId) {
							divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';
							divisionSelect.disabled = true;
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
									// Re-initialize Choices with new options
									initChoicesForSelect('division_label');
								})
								.catch(error => { 
									console.error('Error loading divisions:', error); 
									divisionSelect.innerHTML = '<option value="">Error loading divisions</option>'; 
									divisionSelect.disabled = false; 
									initChoicesForSelect('division_label');
								});
						} else {
							// Re-initialize Choices even when no class selected
							initChoicesForSelect('division_label');
						}
					}
				});
			}
		}

		// Set up initial class change listener
		setupClassChangeListener();
	});
	</script>
	</body>
	</html>
	<?php
}
?>
