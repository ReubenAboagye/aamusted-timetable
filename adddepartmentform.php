<?php
include 'connect.php'; // Include database connection

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new department
                $departmentName = $_POST['departmentName'];
                $departmentCode = $_POST['departmentCode'];
                $shortName = $_POST['shortName'];
                $headOfDepartment = $_POST['headOfDepartment'];
                $isActive = isset($_POST['isActive']) ? 1 : 0;

                // Check if department code already exists
                $checkSql = "SELECT id FROM departments WHERE code = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("s", $departmentCode);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('Department code already exists!');</script>";
                } else {
                    $sql = "INSERT INTO departments (name, code, short_name, head_of_department, is_active) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssssi", $departmentName, $departmentCode, $shortName, $headOfDepartment, $isActive);
                        if ($stmt->execute()) {
                            echo "<script>alert('Department added successfully!'); window.location.href='adddepartmentform.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'update':
                // Update existing department
                $id = $_POST['id'];
                $departmentName = $_POST['departmentName'];
                $departmentCode = $_POST['departmentCode'];
                $shortName = $_POST['shortName'];
                $headOfDepartment = $_POST['headOfDepartment'];
                $isActive = isset($_POST['isActive']) ? 1 : 0;

                // Check if department code already exists for other departments
                $checkSql = "SELECT id FROM departments WHERE code = ? AND id != ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("si", $departmentCode, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo "<script>alert('Department code already exists!');</script>";
                } else {
                    $sql = "UPDATE departments SET name = ?, code = ?, short_name = ?, head_of_department = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssssii", $departmentName, $departmentCode, $shortName, $headOfDepartment, $isActive, $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Department updated successfully!'); window.location.href='adddepartmentform.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                $checkStmt->close();
                break;

            case 'delete':
                // Delete department
                $id = $_POST['id'];
                
                // Check if department has dependent records
                $checkSql = "SELECT 
                    (SELECT COUNT(*) FROM classes WHERE department_id = ?) as class_count,
                    (SELECT COUNT(*) FROM courses WHERE department_id = ?) as course_count,
                    (SELECT COUNT(*) FROM lecturers WHERE department_id = ?) as lecturer_count";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("iii", $id, $id, $id);
                $checkStmt->execute();
                $result = $checkStmt->get_result();
                $dependencies = $result->fetch_assoc();
                $checkStmt->close();
                
                if ($dependencies['class_count'] > 0 || $dependencies['course_count'] > 0 || $dependencies['lecturer_count'] > 0) {
                    echo "<script>alert('Cannot delete department: It has dependent classes, courses, or lecturers. Consider deactivating instead.');</script>";
                } else {
                    $sql = "DELETE FROM departments WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("i", $id);
                        if ($stmt->execute()) {
                            echo "<script>alert('Department deleted successfully!'); window.location.href='adddepartmentform.php';</script>";
                        } else {
                            echo "Error: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
                break;
        }
    }
}

// Fetch existing departments for display
$departments = [];
$sql = "SELECT * FROM departments ORDER BY name";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}
?>

<?php $pageTitle = 'Manage Departments'; include 'includes/header.php'; include 'includes/sidebar.php'; ?>

<div class="main-content" id="mainContent">
    <div class="container mt-0">
      <div class="row mb-3 align-items-center">
        <div class="col-md-6">
          <h4 class="m-0">Departments</h4>
                            </div>
        <div class="col-md-3 text-end">
          <input type="text" id="departmentSearch" class="form-control" placeholder="Search departments...">
                    </div>
        <div class="col-md-3 text-end">
          <button class="btn btn-primary" id="showAddBtn">Add Department</button>
          <button class="btn btn-secondary ms-2" id="showImportBtn">Import</button>
                </div>
            </div>
            
                            <div class="table-container">
                <div class="table-header">
                    <h4><i class="fas fa-building me-2"></i>Departments</h4>
                </div>
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search departments...">
                </div>
                <div class="table-responsive">
                    <table class="table" id="departmentsTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Short</th>
                                <th>Head</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                                    <tbody>
                <?php if (empty($departments)): ?>
                  <tr><td colspan="6" class="empty-state"><i class="fas fa-inbox"></i><br>No departments found</td></tr>
                <?php else: ?>
                                        <?php foreach ($departments as $dept): ?>
                    <tr data-name="<?php echo htmlspecialchars(strtolower($dept['name'])); ?>" data-code="<?php echo htmlspecialchars(strtolower($dept['code'])); ?>" data-short="<?php echo htmlspecialchars(strtolower($dept['short_name'])); ?>" data-head="<?php echo htmlspecialchars(strtolower($dept['head_of_department'])); ?>">
                                                <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                                <td><code><?php echo htmlspecialchars($dept['code']); ?></code></td>
                                                <td><?php echo htmlspecialchars($dept['short_name'] ?: '-'); ?></td>
                                                <td><?php echo htmlspecialchars($dept['head_of_department'] ?: '-'); ?></td>
                                                <td>
                                                    <?php if ($dept['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                        <button class="btn btn-sm btn-outline-primary edit-dept-btn" data-id="<?php echo $dept['id']; ?>" data-name="<?php echo htmlspecialchars($dept['name']); ?>" data-code="<?php echo htmlspecialchars($dept['code']); ?>" data-short="<?php echo htmlspecialchars($dept['short_name']); ?>" data-head="<?php echo htmlspecialchars($dept['head_of_department']); ?>" data-active="<?php echo $dept['is_active']; ?>">Edit</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDepartment(<?php echo $dept['id']; ?>, '<?php echo htmlspecialchars(addslashes($dept['name'])); ?>')">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                <?php endif; ?>
                                    </tbody>
                                </table>
                    </div>
                </div>
            </div>
        
        <div class="mt-3">
            
        </div>
    </div>
  </div>

  <!-- Department Modal -->
  <div class="modal fade" id="departmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="departmentModalLabel">Add Department</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="" id="departmentForm">
          <input type="hidden" id="action" name="action" value="add">
          <input type="hidden" id="departmentId" name="id" value="">
          <div class="modal-body">
            <div class="mb-3">
              <label for="departmentName" class="form-label">Department Name *</label>
              <input type="text" class="form-control" id="departmentName" name="departmentName" required>
            </div>
            <div class="mb-3">
              <label for="departmentCode" class="form-label">Department Code *</label>
              <input type="text" class="form-control" id="departmentCode" name="departmentCode" required maxlength="20">
              <div class="form-text">Unique identifier for the department</div>
            </div>
            <div class="mb-3">
              <label for="shortName" class="form-label">Short Name</label>
              <input type="text" class="form-control" id="shortName" name="shortName" maxlength="10">
              <div class="form-text">Short name (optional)</div>
            </div>
            <div class="mb-3">
              <label for="headOfDepartment" class="form-label">Head of Department</label>
              <input type="text" class="form-control" id="headOfDepartment" name="headOfDepartment">
            </div>
            <div class="mb-3 form-check">
              <input class="form-check-input" type="checkbox" id="isActive" name="isActive" checked>
              <label class="form-check-label" for="isActive">Department is Active</label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" id="submitBtn">Save Department</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<?php include 'includes/footer.php'; ?>

  <!-- Bootstrap JS (make sure this is included) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Header time, sidebar toggle and back-to-top
    function updateTime(){
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US',{hour12:true,hour:'2-digit',minute:'2-digit',second:'2-digit'});
        const el = document.getElementById('currentTime');
        if(el) el.textContent = timeString;
    }
    setInterval(updateTime,1000); updateTime();

    const sidebarToggle=document.getElementById('sidebarToggle');
    if(sidebarToggle){
        sidebarToggle.addEventListener('click',function(){
            const sidebar=document.getElementById('sidebar');
            const mainContent=document.getElementById('mainContent');
            const footer=document.getElementById('footer');
            sidebar.classList.toggle('show');
            if(sidebar.classList.contains('show')){
                mainContent.classList.add('shift');
                footer.classList.add('shift');
            } else {
                mainContent.classList.remove('shift');
                footer.classList.remove('shift');
            }
        });
    }

    const backToTopButton=document.getElementById('backToTop');
    const progressCircle=document.getElementById('progressCircle');
    if(progressCircle){
        const circumference=2*Math.PI*20;
        progressCircle.style.strokeDasharray=circumference;
        progressCircle.style.strokeDashoffset=circumference;
        window.addEventListener('scroll',function(){
            const scrollTop=document.documentElement.scrollTop||document.body.scrollTop;
            if(backToTopButton) backToTopButton.style.display=scrollTop>100?'block':'none';
            const scrollHeight=document.documentElement.scrollHeight-document.documentElement.clientHeight;
            const scrollPercentage=scrollTop/(scrollHeight||1);
            const offset=circumference-(scrollPercentage*circumference);
            progressCircle.style.strokeDashoffset=offset;
        });
        if(backToTopButton) backToTopButton.addEventListener('click',function(){
            window.scrollTo({ top:0, behavior:'smooth' });
        });
    }

    // Modal and department actions
    const departmentModalEl = document.getElementById('departmentModal');
    const departmentModal = (typeof bootstrap !== 'undefined' && departmentModalEl) ? new bootstrap.Modal(departmentModalEl) : null;

    const showAddBtn = document.getElementById('showAddBtn');
    if (showAddBtn) {
      showAddBtn.addEventListener('click', function(){
        document.getElementById('action').value = 'add';
        document.getElementById('departmentId').value = '';
        document.getElementById('departmentForm').reset();
        const label = document.getElementById('departmentModalLabel'); if(label) label.textContent = 'Add Department';
        const submit = document.getElementById('submitBtn'); if(submit) submit.textContent = 'Save Department';
        if (departmentModal) departmentModal.show();
      });
    }

    // Import modal
    const showImportBtn = document.getElementById('showImportBtn');
    if (showImportBtn) {
      showImportBtn.addEventListener('click', function(){
        if (!document.getElementById('importModal')) {
          const div = document.createElement('div');
          div.innerHTML = `
          <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Import Departments</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <p>Upload a CSV file. First row should be headers. Convert Excel files to CSV format first.</p>
                  <input type="file" id="importFileInput" accept=".csv" class="form-control" />
                  <div class="mt-3" id="importPreviewContainer" style="max-height:400px; overflow:auto;"></div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="button" class="btn btn-primary" id="importPreviewBtn">Preview</button>
                  <button type="button" class="btn btn-success" id="importConfirmBtn" disabled>Import</button>
                </div>
              </div>
            </div>
          </div>`;
          document.body.appendChild(div);
        }
        const importModalEl = document.getElementById('importModal');
        const importModal = (typeof bootstrap !== 'undefined' && importModalEl) ? new bootstrap.Modal(importModalEl) : null;
        if (importModal) importModal.show();
      });
    }

    // Preview / import flow
    document.addEventListener('click', function(e){
      if (e.target && e.target.id === 'importPreviewBtn') {
        const input = document.getElementById('importFileInput');
        const previewContainer = document.getElementById('importPreviewContainer');
        const confirmBtn = document.getElementById('importConfirmBtn');
        previewContainer.innerHTML = '';
        confirmBtn.disabled = true;
        if (!input.files || input.files.length === 0) { alert('Please choose a file to preview.'); return; }
        const file = input.files[0];
        const formData = new FormData();
        formData.append('file', file);
        console.log('Uploading file:', file.name, 'Size:', file.size);
        console.log('FormData entries:');
        for (let [key, value] of formData.entries()) {
            console.log(key, value);
        }
        fetch('import_department_preview_simple.php', { 
            method: 'POST', 
            body: formData,
            // Don't set Content-Type header - let the browser set it for FormData
        })
          .then(r => {
            console.log('Response status:', r.status);
            return r.json();
          })
          .then(data => {
            console.log('Response data:', data);
            if (data.success) {
              const table = document.createElement('table');
              table.className = 'table table-sm table-bordered';
              const thead = document.createElement('thead');
              const headerRow = document.createElement('tr');
              data.headers.forEach(h => { const th = document.createElement('th'); th.textContent = h; headerRow.appendChild(th); });
              thead.appendChild(headerRow); table.appendChild(thead);
              const tbody = document.createElement('tbody');
              data.rows.slice(0,100).forEach(row => {
                const tr = document.createElement('tr');
                row.forEach(cell => { const td = document.createElement('td'); td.textContent = cell; tr.appendChild(td); });
                tbody.appendChild(tr);
              });
              table.appendChild(tbody);
              previewContainer.appendChild(table);
              confirmBtn.disabled = false;
              confirmBtn.dataset.uploadToken = data.upload_token || '';
            } else {
              previewContainer.textContent = data.error || 'Failed to parse file.';
            }
                      }).catch(err => { 
              console.error('Fetch error:', err);
              previewContainer.textContent = 'Error reading file: ' + err.message; 
            });
      }

      if (e.target && e.target.id === 'importConfirmBtn') {
        const token = e.target.dataset.uploadToken;
        if (!token) { alert('Missing upload token. Please preview again.'); return; }
        
        const confirmBtn = e.target;
        const previewContainer = document.getElementById('importPreviewContainer');
        
        // Show loading state
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Importing...';
        previewContainer.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Importing departments...</div>';
        
        // Submit import request
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'import_department.php';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'upload_token';
        input.value = token;
        form.appendChild(input);
        
        // Use fetch to get the response
        const formData = new FormData(form);
        fetch('import_department.php', {
          method: 'POST',
          body: formData
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            previewContainer.innerHTML = `
              <div class="alert alert-success">
                <h5><i class="fas fa-check-circle"></i> ${data.message}</h5>
                <p><strong>Summary:</strong></p>
                <ul>
                  <li>Total processed: ${data.summary.total_processed}</li>
                  <li>Successfully imported: ${data.summary.successful}</li>
                  <li>Errors: ${data.summary.errors}</li>
                </ul>
                ${data.errors ? '<p><strong>Errors:</strong></p><ul>' + data.errors.map(e => '<li>' + e + '</li>').join('') + '</ul>' : ''}
              </div>
              <button class="btn btn-primary" onclick="location.reload()">Refresh Page</button>
            `;
          } else {
            previewContainer.innerHTML = `
              <div class="alert alert-danger">
                <h5><i class="fas fa-exclamation-triangle"></i> Import Failed</h5>
                <p>${data.error}</p>
                <button class="btn btn-secondary" onclick="location.reload()">Try Again</button>
              </div>
            `;
          }
        })
        .catch(err => {
          previewContainer.innerHTML = `
            <div class="alert alert-danger">
              <h5><i class="fas fa-exclamation-triangle"></i> Import Error</h5>
              <p>${err.message}</p>
              <button class="btn btn-secondary" onclick="location.reload()">Try Again</button>
            </div>
          `;
        })
        .finally(() => {
          confirmBtn.disabled = false;
          confirmBtn.textContent = 'Import';
        });
      }
    });

    // Attach edit buttons behaviour (delegated)
    document.addEventListener('click', function(e){
      const btn = e.target.closest && e.target.closest('.edit-dept-btn');
      if (!btn) return;
      const id = btn.dataset.id;
      const name = btn.dataset.name || '';
      const code = btn.dataset.code || '';
      const shortName = btn.dataset.short || '';
      const head = btn.dataset.head || '';
      const isActive = parseInt(btn.dataset.active || '0', 10);
      document.getElementById('action').value = 'update';
      document.getElementById('departmentId').value = id;
      document.getElementById('departmentName').value = name;
      document.getElementById('departmentCode').value = code;
      document.getElementById('shortName').value = shortName;
      document.getElementById('headOfDepartment').value = head;
      document.getElementById('isActive').checked = isActive === 1;
      const label = document.getElementById('departmentModalLabel'); if(label) label.textContent = 'Edit Department';
      const submit = document.getElementById('submitBtn'); if(submit) submit.textContent = 'Update Department';
      if (departmentModal) departmentModal.show();
    });

    window.deleteDepartment = function(id, name) {
      if (confirm(`Are you sure you want to delete the department "${name}"?`)) {
          const form = document.createElement('form');
          form.method = 'POST'; form.action = '';
          const actionInput = document.createElement('input'); actionInput.type = 'hidden'; actionInput.name = 'action'; actionInput.value = 'delete';
          const idInput = document.createElement('input'); idInput.type = 'hidden'; idInput.name = 'id'; idInput.value = id;
          form.appendChild(actionInput); form.appendChild(idInput);
          document.body.appendChild(form); form.submit();
      }
    }

    // Table search/filter
    const departmentSearch = document.getElementById('departmentSearch');
    if (departmentSearch) {
      departmentSearch.addEventListener('input', function(){
        const q = this.value.trim().toLowerCase();
        const rows = document.querySelectorAll('#departmentsTable tbody tr');
        rows.forEach(r => {
          const name = r.dataset.name || '';
          const code = r.dataset.code || '';
          const short = r.dataset.short || '';
          const head = r.dataset.head || '';
          const matches = name.includes(q) || code.includes(q) || short.includes(q) || head.includes(q);
          r.style.display = matches ? '' : 'none';
        });
      });
    }
});
</script>
</body>
</html>
