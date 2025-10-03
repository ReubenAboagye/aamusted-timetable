<?php
// Admin partial: show job list and progress (to be included in admin pages)
if (!isset($conn)) {
    include_once __DIR__ . '/../connect.php';
}

$streamId = $active_stream ?? 1;

?>
<div class="card mb-3">
  <div class="card-header">Timetable Generation Jobs</div>
  <div class="card-body">
    <div id="jobsTableContainer">
      <table class="table">
        <thead>
          <tr><th>ID</th><th>Status</th><th>Progress</th><th>Stream</th><th>Created</th><th>Action</th></tr>
        </thead>
        <tbody id="jobsTableBody">
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function fetchJobs() {
  fetch('api/list_jobs.php?stream_id=' + <?= json_encode($streamId) ?>)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      // Check if response is JSON
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        throw new Error('Response is not JSON');
      }
      
      return response.text().then(text => {
        try {
          return JSON.parse(text);
        } catch (e) {
          console.error('Invalid JSON response:', text);
          throw new Error('Invalid JSON response: ' + text.substring(0, 100));
        }
      });
    })
    .then(data => {
      if (!data.success) {
        console.error('API returned error:', data.error);
        return;
      }
      const tbody = document.getElementById('jobsTableBody');
      tbody.innerHTML = '';
      data.jobs.forEach(j => {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td>' + j.id + '</td>' +
          '<td>' + j.status + '</td>' +
          '<td><div class="progress"><div class="progress-bar" role="progressbar" style="width:' + j.progress + '%">' + j.progress + '%</div></div></td>' +
          '<td>' + (j.stream_id || '') + '</td>' +
          '<td>' + j.created_at + '</td>' +
          '<td><a class="btn btn-sm btn-outline-primary" href="api/job_status.php?job_id=' + j.id + '">Details</a></td>';
        tbody.appendChild(tr);
      });
    })
    .catch(error => {
      console.error('Error fetching jobs:', error);
      const tbody = document.getElementById('jobsTableBody');
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading jobs: ' + error.message + '</td></tr>';
    });
}

document.addEventListener('DOMContentLoaded', function() {
  fetchJobs();
  setInterval(fetchJobs, 5000);
});
</script>


