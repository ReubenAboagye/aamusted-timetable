<?php
include 'connect.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	switch ($action) {
		case 'add':
			$name = trim($_POST['name'] ?? '');
			$yearNumber = intval($_POST['year_number'] ?? 0);
			if ($name === '' || $yearNumber <= 0) {
				echo "<script>alert('Please provide a valid name and year number.');</script>";
				break;
			}
			// Prevent duplicates by year_number or name
			$checkSql = "SELECT id FROM levels WHERE year_number = ? OR name = ?";
			$checkStmt = $conn->prepare($checkSql);
			$checkStmt->bind_param('is', $yearNumber, $name);
			$checkStmt->execute();
			$exists = $checkStmt->get_result()->num_rows > 0;
			$checkStmt->close();
			if ($exists) {
				echo "<script>alert('A level with this name or year already exists.');</script>";
				break;
			}
			$sql = "INSERT INTO levels (name, year_number) VALUES (?, ?)";
			$stmt = $conn->prepare($sql);
			$stmt->bind_param('si', $name, $yearNumber);
			if ($stmt->execute()) {
				echo "<script>alert('Level added successfully!'); window.location.href='levels.php';</script>";
			} else {
				echo "Error: " . $stmt->error;
			}
			$stmt->close();
			break;

		case 'update':
			$id = intval($_POST['id'] ?? 0);
			$name = trim($_POST['name'] ?? '');
			$yearNumber = intval($_POST['year_number'] ?? 0);
			if ($id <= 0 || $name === '' || $yearNumber <= 0) {
				echo "<script>alert('Please provide valid values.');</script>";
				break;
			}
			$checkSql = "SELECT id FROM levels WHERE (year_number = ? OR name = ?) AND id != ?";
			$checkStmt = $conn->prepare($checkSql);
			$checkStmt->bind_param('isi', $yearNumber, $name, $id);
			$checkStmt->execute();
			$exists = $checkStmt->get_result()->num_rows > 0;
			$checkStmt->close();
			if ($exists) {
				echo "<script>alert('Another level with this name or year already exists.');</script>";
				break;
			}
			$sql = "UPDATE levels SET name = ?, year_number = ? WHERE id = ?";
			$stmt = $conn->prepare($sql);
			$stmt->bind_param('sii', $name, $yearNumber, $id);
			if ($stmt->execute()) {
				echo "<script>alert('Level updated successfully!'); window.location.href='levels.php';</script>";
			} else {
				echo "Error: " . $stmt->error;
			}
			$stmt->close();
			break;

		case 'delete':
			$id = intval($_POST['id'] ?? 0);
			if ($id <= 0) { echo "<script>alert('Invalid level.');</script>"; break; }
			// courses.level stores the numeric year (1..n), not the level id
			$yrStmt = $conn->prepare('SELECT year_number FROM levels WHERE id = ?');
			$yrStmt->bind_param('i', $id);
			$yrStmt->execute();
			$row = $yrStmt->get_result()->fetch_assoc();
			$yrStmt->close();
			if (!$row) { echo "<script>alert('Level not found.');</script>"; break; }
			$yearNumber = intval($row['year_number']);
			$checkSql = "SELECT COUNT(*) AS cnt FROM courses WHERE level = ?";
			$checkStmt = $conn->prepare($checkSql);
			$checkStmt->bind_param('i', $yearNumber);
			$checkStmt->execute();
			$cnt = $checkStmt->get_result()->fetch_assoc()['cnt'] ?? 0;
			$checkStmt->close();
			if ($cnt > 0) {
				echo "<script>alert('Cannot delete: Courses reference this level year.');</script>";
				break;
			}
			$stmt = $conn->prepare("DELETE FROM levels WHERE id = ?");
			$stmt->bind_param('i', $id);
			if ($stmt->execute()) {
				echo "<script>alert('Level deleted successfully!'); window.location.href='levels.php';</script>";
			} else {
				echo "Error: " . $stmt->error;
			}
			$stmt->close();
			break;
	}
}

// Fetch levels
$levels = [];
$res = $conn->query("SELECT * FROM levels ORDER BY year_number");
if ($res) {
	while ($row = $res->fetch_assoc()) { $levels[] = $row; }
}
?>
<?php $pageTitle = 'Manage Levels'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

<div class="main-content" id="mainContent">
	<h2>Manage Levels</h2>
	<div class="d-flex justify-content-between align-items-center mb-4">
		<div class="input-group" style="width: 300px;">
			<span class="input-group-text"><i class="fas fa-search"></i></span>
			<input type="text" class="form-control" id="searchInput" placeholder="Search levels...">
		</div>
		<div>
			<button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">Import</button>
			<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#levelModal"><i class="fas fa-plus me-2"></i>Add Level</button>
		</div>
	</div>

	<div class="table-container">
		<div class="table-header">
			<h4><i class="fas fa-layer-group me-2"></i>Existing Levels</h4>
		</div>
		<div class="table-responsive">
			<table class="table" id="levelsTable">
				<thead>
					<tr>
						<th>Name</th>
						<th>Year Number</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($levels)): ?>
						<tr><td colspan="3" class="text-center">No levels found</td></tr>
					<?php else: foreach ($levels as $level): ?>
						<tr>
							<td><strong><?php echo htmlspecialchars($level['name']); ?></strong></td>
							<td><span class="badge bg-primary"><?php echo (int)$level['year_number']; ?></span></td>
							<td>
								<button class="btn btn-sm btn-outline-primary" onclick="editLevel(<?php echo (int)$level['id']; ?>, '<?php echo htmlspecialchars($level['name'], ENT_QUOTES); ?>', <?php echo (int)$level['year_number']; ?>)">Edit</button>
								<button class="btn btn-sm btn-outline-danger" onclick="deleteLevel(<?php echo (int)$level['id']; ?>, '<?php echo htmlspecialchars($level['name'], ENT_QUOTES); ?>')">Delete</button>
							</td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="levelModal" tabindex="-1" aria-labelledby="levelModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="levelModalLabel">Add Level</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form method="POST" id="levelForm">
				<input type="hidden" name="action" id="levelAction" value="add">
				<input type="hidden" name="id" id="levelId">
				<div class="modal-body">
					<div class="mb-3">
						<label for="levelName" class="form-label">Name</label>
						<input type="text" class="form-control" id="levelName" name="name" placeholder="e.g., Level 100" required>
					</div>
					<div class="mb-3">
						<label for="yearNumber" class="form-label">Year Number</label>
						<input type="number" class="form-control" id="yearNumber" name="year_number" placeholder="e.g., 1" required min="1">
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					<button type="submit" class="btn btn-primary">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="importModalLabel">Import Levels</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form action="import_levels.php" method="POST" enctype="multipart/form-data">
					<div class="mb-3">
						<label for="levelsFile" class="form-label">Choose Excel File</label>
						<input type="file" class="form-control" id="levelsFile" name="file" required accept=".xls,.xlsx">
					</div>
					<button type="submit" class="btn btn-primary">Upload</button>
				</form>
			</div>
		</div>
	</div>
</div>

<div class="footer" id="footer">&copy; 2025 University Timetable Generator</div>

<button id="backToTop" style="position: fixed; bottom: 30px; right: 30px; display:none; background: rgba(128, 0, 32, 0.7); border: none; width: 50px; height: 50px; border-radius: 50%;">
	<svg width="50" height="50" viewBox="0 0 50 50">
		<circle id="progressCircle" cx="25" cy="25" r="20" fill="none" stroke="#FFD700" stroke-width="4" stroke-dasharray="126" stroke-dashoffset="126"></circle>
	</svg>
	<i class="fas fa-arrow-up arrow-icon" style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);color:#FFD700;font-size:1.5rem;"></i>
</button>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle behavior consistent with header include
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
	const sidebar = document.getElementById('sidebar');
	const mainContent = document.getElementById('mainContent');
	const footer = document.getElementById('footer');
	if (sidebar.classList.contains('collapsed')) {
		sidebar.classList.remove('collapsed');
		mainContent.classList.remove('collapsed');
		footer.classList.remove('collapsed');
	} else {
		sidebar.classList.add('collapsed');
		mainContent.classList.add('collapsed');
		footer.classList.add('collapsed');
	}
});

// Simple search filter
document.getElementById('searchInput')?.addEventListener('input', function() {
	const q = this.value.toLowerCase();
	document.querySelectorAll('#levelsTable tbody tr').forEach(tr => {
		const text = tr.textContent.toLowerCase();
		tr.style.display = text.includes(q) ? '' : 'none';
	});
});

// Edit and delete helpers
function editLevel(id, name, yearNumber) {
	document.getElementById('levelModalLabel').textContent = 'Edit Level';
	document.getElementById('levelAction').value = 'update';
	document.getElementById('levelId').value = id;
	document.getElementById('levelName').value = name;
	document.getElementById('yearNumber').value = yearNumber;
	const modal = new bootstrap.Modal(document.getElementById('levelModal'));
	modal.show();
}

function deleteLevel(id, name) {
	if (!confirm(`Delete level "${name}"? This cannot be undone.`)) return;
	const form = document.createElement('form');
	form.method = 'POST';
	form.action = 'levels.php';
	form.innerHTML = '<input type="hidden" name="action" value="delete">' +
		'<input type="hidden" name="id" value="' + id + '">';
	document.body.appendChild(form);
	form.submit();
}

// Back to top behavior
const backToTopButton = document.getElementById('backToTop');
const progressCircle = document.getElementById('progressCircle');
const circumference = 2 * Math.PI * 20;
progressCircle.style.strokeDasharray = circumference;
progressCircle.style.strokeDashoffset = circumference;
window.addEventListener('scroll', function() {
	const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
	backToTopButton.style.display = scrollTop > 100 ? 'block' : 'none';
	const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
	const scrollPercentage = scrollTop / scrollHeight;
	const offset = circumference - (scrollPercentage * circumference);
	progressCircle.style.strokeDashoffset = offset;
});
backToTopButton.addEventListener('click', function() { window.scrollTo({ top: 0, behavior: 'smooth' }); });
</script>

</body>
</html>

