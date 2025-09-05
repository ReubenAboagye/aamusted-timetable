<?php
// Modal wrapper that includes the admin jobs partial
?>
<!-- Jobs Modal -->
<div class="modal fade" id="jobsModal" tabindex="-1" aria-labelledby="jobsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="jobsModalLabel">Timetable Generation Jobs</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php include_once __DIR__ . '/admin_jobs.php'; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


