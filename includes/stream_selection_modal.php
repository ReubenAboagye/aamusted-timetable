<?php
/**
 * Stream Selection Modal Component
 * Provides a user-friendly modal for stream selection warnings and prompts
 */
?>

<!-- Stream Selection Modal -->
<div class="modal fade" id="streamSelectionModal" tabindex="-1" aria-labelledby="streamSelectionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="streamSelectionModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i>Stream Selection Required
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-3">
          <i class="fas fa-stream fa-3x text-warning mb-3"></i>
          <p class="mb-2">You need to select a stream before performing this action.</p>
          <p class="text-muted small">Streams help organize your timetables by academic periods or program types.</p>
        </div>
        
        <div class="alert alert-info">
          <strong>What would you like to do?</strong>
        </div>
        
        <div class="d-grid gap-2">
          <a href="index.php" class="btn btn-primary">
            <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard to Switch Stream
          </a>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-2"></i>Continue Without Action
          </button>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <small class="text-muted">
          <i class="fas fa-info-circle me-1"></i>
          Tip: You can always change streams from the Dashboard page
        </small>
      </div>
    </div>
  </div>
</div>

<!-- Stream Selection Warning Alert (for inline display) -->
<div class="alert alert-warning d-none" id="streamSelectionAlert" role="alert">
  <div class="d-flex align-items-center">
    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
    <div class="flex-grow-1">
      <h6 class="alert-heading mb-1">No Stream Selected</h6>
      <p class="mb-2">Please select a stream to continue with your current action.</p>
      <a href="index.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-tachometer-alt me-1"></i>Go to Dashboard
      </a>
      <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="this.parentElement.parentElement.parentElement.classList.add('d-none')">
        <i class="fas fa-times me-1"></i>Dismiss
      </button>
    </div>
  </div>
</div>

<script>
// Stream selection modal functions
function showStreamSelectionModal(message = 'You need to select a stream before performing this action.') {
  // Update modal message if provided
  const modalBody = document.querySelector('#streamSelectionModal .modal-body p');
  if (modalBody) {
    modalBody.textContent = message;
  }
  
  // Show the modal
  const modal = new bootstrap.Modal(document.getElementById('streamSelectionModal'));
  modal.show();
}

function showStreamSelectionAlert(message = 'No stream selected. Please select a stream to continue.') {
  const alert = document.getElementById('streamSelectionAlert');
  if (alert) {
    // Update message if needed
    const alertText = alert.querySelector('.alert-heading').nextElementSibling;
    if (alertText) {
      alertText.textContent = message;
    }
    
    // Show the alert
    alert.classList.remove('d-none');
    
    // Scroll to top to ensure visibility
    alert.scrollIntoView({behavior: 'smooth', block: 'start'});
  }
}

// Override the global AJAX handler for better stream validation handling
document.addEventListener('DOMContentLoaded', function() {
  // Enhanced AJAX error handling
  const originalHandleAjaxStreamError = window.handleAjaxStreamError;
  
  window.handleAjaxStreamError = function(response) {
    if (originalHandleAjaxStreamError && originalHandleAjaxStreamError(response)) {
      return true;
    }
    
    // Handle our enhanced stream validation errors
    if (response && response.action_required === 'stream_selection') {
      showStreamSelectionModal(response.message);
      return true;
    }
    return false;
  };
  
  // Auto-hide alerts after some time
  setTimeout(function() {
    const alert = document.getElementById('streamSelectionAlert');
    if (alert && !alert.classList.contains('d-none')) {
      alert.classList.add('d-none');
    }
  }, 10000); // Hide after 10 seconds
});
</script>






