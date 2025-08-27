<?php
include 'connect.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	switch ($action) {
		case 'add':
			$name = trim($_POST['name'] ?? '');
			if ($name === '') { echo "<script>alert('Please provide a name.');</script>"; break; }
			$check = $conn->prepare('SELECT id FROM session_types WHERE name = ?');
			$check->bind_param('s', $name);
			$check->execute();
			if ($check->get_result()->num_rows > 0) { $check->close(); echo "<script>alert('Session type already exists.');</script>"; break; }
			$check->close();
			$stmt = $conn->prepare('INSERT INTO session_types (name) VALUES (?)');
			$stmt->bind_param('s', $name);
			if ($stmt->execute()) { echo "<script>alert('Session type added!'); window.location.href='session_types.php';</script>"; }
			else { echo 'Error: ' . $stmt->error; }
			$stmt->close();
			break;
		case 'update':
			$id = intval($_POST['id'] ?? 0);
			$name = trim($_POST['name'] ?? '');
			if ($id <= 0 || $name === '') { echo "<script>alert('Invalid values.');</script>"; break; }
			$check = $conn->prepare('SELECT id FROM session_types WHERE name = ? AND id != ?');
			$check->bind_param('si', $name, $id);
			$check->execute();
			if ($check->get_result()->num_rows > 0) { $check->close(); echo "<script>alert('Another session type with this name exists.');</script>"; break; }
			$check->close();
			$stmt = $conn->prepare('UPDATE session_types SET name = ? WHERE id = ?');
			$stmt->bind_param('si', $name, $id);
			if ($stmt->execute()) { echo "<script>alert('Session type updated!'); window.location.href='session_types.php';</script>"; }
			else { echo 'Error: ' . $stmt->error; }
			$stmt->close();
			break;
		case 'delete':
			$id = intval($_POST['id'] ?? 0);
			if ($id <= 0) { echo "<script>alert('Invalid session type.');</script>"; break; }
			$dep = $conn->prepare('SELECT COUNT(*) AS cnt FROM timetable WHERE session_type_id = ?');
			$dep->bind_param('i', $id);
			$dep->execute();
			$cnt = $dep->get_result()->fetch_assoc()['cnt'] ?? 0;
			$dep->close();
			if ($cnt > 0) { echo "<script>alert('Cannot delete: referenced by timetable.');</script>"; break; }
			$stmt = $conn->prepare('DELETE FROM session_types WHERE id = ?');
			$stmt->bind_param('i', $id);
			if ($stmt->execute()) { echo "<script>alert('Session type deleted!'); window.location.href='session_types.php';</script>"; }
			else { echo 'Error: ' . $stmt->error; }
			$stmt->close();
			break;
	}
}

// Fetch
$types = [];
$res = $conn->query('SELECT * FROM session_types ORDER BY name');
if ($res) { while ($r = $res->fetch_assoc()) { $types[] = $r; } }
?>
<?php $pageTitle = 'Session Types'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

<div class="main-content" id="mainContent">
	<h2>Session Types</h2>
	<div class="d-flex justify-content-between align-items-center mb-4">
		<div class="input-group" style="width: 300px;">
			<span class="input-group-text"><i class="fas fa-search"></i></span>
			<input type="text" class="form-control" id="searchInput" placeholder="Search session types...">
		</div>
		<div>
			<button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#importModal">Import</button>
			<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#typeModal"><i class="fas fa-plus me-2"></i>Add</button>
		</div>
	</div>

	<div class="table-container">
		<div class="table-header"><h4><i class="fas fa-tags me-2"></i>Existing Session Types</h4></div>
		<div class="table-responsive">
			<table class="table" id="typesTable">
				<thead><tr><th>Name</th><th>Actions</th></tr></thead>
				<tbody>
					<?php if (empty($types)): ?>
						<tr><td colspan="2" class="text-center">No session types found</td></tr>
					<?php else: foreach ($types as $t): ?>
						<tr>
							<td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
							<td>
								<button class="btn btn-sm btn-outline-primary" onclick="editType(<?php echo (int)$t['id']; ?>, '<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>')">Edit</button>
								<button class="btn btn-sm btn-outline-danger" onclick="deleteType(<?php echo (int)$t['id']; ?>, '<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>')">Delete</button>
							</td>
						</tr>
					<?php endforeach; endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="typeModal" tabindex="-1" aria-labelledby="typeModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="typeModalLabel">Add Session Type</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form method="POST" id="typeForm">
				<input type="hidden" name="action" id="typeAction" value="add">
				<input type="hidden" name="id" id="typeId">
				<div class="modal-body">
					<div class="mb-3">
						<label for="typeName" class="form-label">Name</label>
						<input type="text" class="form-control" id="typeName" name="name" placeholder="e.g., Lecture" required>
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
				<h5 class="modal-title" id="importModalLabel">Import Session Types</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form action="import_session_types.php" method="POST" enctype="multipart/form-data">
					<div class="mb-3">
						<label for="typesFile" class="form-label">Choose Excel File</label>
						<input type="file" class="form-control" id="typesFile" name="file" required accept=".xls,.xlsx">
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
function editType(id, name) {
	document.getElementById('typeModalLabel').textContent = 'Edit Session Type';
	document.getElementById('typeAction').value = 'update';
	document.getElementById('typeId').value = id;
	document.getElementById('typeName').value = name;
	new bootstrap.Modal(document.getElementById('typeModal')).show();
}
function deleteType(id, name) {
	if (!confirm(`Delete session type "${name}"?`)) return;
	const form = document.createElement('form');
	form.method = 'POST';
	form.action = 'session_types.php';
	form.innerHTML = '<input type="hidden" name="action" value="delete">' +
		'<input type="hidden" name="id" value="' + id + '">';
	document.body.appendChild(form);
	form.submit();
}
document.getElementById('searchInput')?.addEventListener('input', function() {
	const q = this.value.toLowerCase();
	document.querySelectorAll('#typesTable tbody tr').forEach(tr => {
		tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
	});
});

// Sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
	const sidebar = document.getElementById('sidebar');
	const mainContent = document.getElementById('mainContent');
	const footer = document.getElementById('footer');
	if (sidebar.classList.contains('collapsed')) { sidebar.classList.remove('collapsed'); mainContent.classList.remove('collapsed'); footer.classList.remove('collapsed'); }
	else { sidebar.classList.add('collapsed'); mainContent.classList.add('collapsed'); footer.classList.add('collapsed'); }
});

// Back to top
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

