<?php
$pageTitle = 'Custom Dialog Test';
include 'includes/header.php';
include 'includes/sidebar.php';

// Include custom dialog system
echo '<link rel="stylesheet" href="css/custom-dialogs.css">';
echo '<script src="js/custom-dialogs.js"></script>';
?>

<div class="main-content" id="mainContent">
    <div class="container mt-4">
        <h2>Custom Dialog System Test</h2>
        <p>Test the custom dialog system that replaces browser confirm/alert dialogs.</p>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Confirmation Dialogs</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-primary me-2 mb-2" onclick="testConfirm()">Test Confirm</button>
                        <button class="btn btn-warning me-2 mb-2" onclick="testWarning()">Test Warning</button>
                        <button class="btn btn-danger me-2 mb-2" onclick="testDanger()">Test Danger</button>
                        <button class="btn btn-success me-2 mb-2" onclick="testSuccess()">Test Success</button>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Alert Dialogs</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-info me-2 mb-2" onclick="testAlert()">Test Alert</button>
                        <button class="btn btn-secondary me-2 mb-2" onclick="testInfo()">Test Info</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Stream Management Examples</h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-danger me-2 mb-2" onclick="testStreamDeletion()">Test Stream Deletion</button>
                        <button class="btn btn-warning me-2 mb-2" onclick="testStreamDeactivation()">Test Stream Deactivation</button>
                        <button class="btn btn-success me-2 mb-2" onclick="testStreamActivation()">Test Stream Activation</button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Form Submission Test</h5>
                    </div>
                    <div class="card-body">
                        <p>Test custom dialogs with form submission (like in streams.php):</p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="test_action" value="delete">
                            <button type="submit" class="btn btn-danger me-2 mb-2" onclick="testFormSubmission(event, 'delete')">Test Delete Form</button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="test_action" value="activate">
                            <button type="submit" class="btn btn-success me-2 mb-2" onclick="testFormSubmission(event, 'activate')">Test Activate Form</button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="test_action" value="deactivate">
                            <button type="submit" class="btn btn-warning me-2 mb-2" onclick="testFormSubmission(event, 'deactivate')">Test Deactivate Form</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Test Results</h5>
                    </div>
                    <div class="card-body">
                        <div id="test-results" class="alert alert-info">
                            Click any button above to test the custom dialogs. Results will appear here.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateResults(message) {
    const results = document.getElementById('test-results');
    results.innerHTML = message;
    results.className = 'alert alert-success';
}

// Test functions
async function testConfirm() {
    const result = await customConfirm('This is a test confirmation dialog. Do you want to proceed?');
    updateResults(`Confirm dialog result: ${result ? 'Confirmed' : 'Cancelled'}`);
}

async function testWarning() {
    const result = await customWarning('This is a warning dialog. Please review before proceeding.');
    updateResults(`Warning dialog result: ${result ? 'Proceeded' : 'Cancelled'}`);
}

async function testDanger() {
    const result = await customDanger('This is a danger dialog. This action cannot be undone!');
    updateResults(`Danger dialog result: ${result ? 'Proceeded' : 'Cancelled'}`);
}

async function testSuccess() {
    const result = await customSuccess('Operation completed successfully!');
    updateResults(`Success dialog result: ${result ? 'Acknowledged' : 'Cancelled'}`);
}

async function testAlert() {
    const result = await customAlert('This is a test alert dialog.');
    updateResults(`Alert dialog result: ${result ? 'Acknowledged' : 'Cancelled'}`);
}

async function testInfo() {
    const result = await customAlert('This is an informational dialog.', {
        title: 'Information',
        type: 'info'
    });
    updateResults(`Info dialog result: ${result ? 'Acknowledged' : 'Cancelled'}`);
}

// Stream management test functions
async function testStreamDeletion() {
    const result = await customDanger(
        `Are you sure you want to permanently delete the stream "Test Stream"?<br><br><strong>This action cannot be undone!</strong><br><br>This will also delete all associated:<br>• Classes<br>• Courses<br>• Lecturer assignments<br>• Timetable entries`,
        {
            title: 'Delete Stream',
            confirmText: 'Delete Permanently',
            cancelText: 'Cancel',
            confirmButtonClass: 'danger'
        }
    );
    updateResults(`Stream deletion result: ${result ? 'Deleted' : 'Cancelled'}`);
}

async function testStreamDeactivation() {
    const result = await customWarning(
        `Are you sure you want to deactivate the stream "Test Stream"?<br><br>This will:<br>• Make the stream inactive<br>• Prevent new timetable generation for this stream<br>• Keep existing data intact`,
        {
            title: 'Deactivate Stream',
            confirmText: 'Deactivate',
            cancelText: 'Cancel',
            confirmButtonClass: 'warning'
        }
    );
    updateResults(`Stream deactivation result: ${result ? 'Deactivated' : 'Cancelled'}`);
}

async function testStreamActivation() {
    const result = await customWarning(
        `Are you sure you want to activate the stream "Test Stream"?<br><br>This will:<br>• Make this stream the active stream<br>• <strong>Deactivate all other streams</strong><br>• Set this as your current working stream`,
        {
            title: 'Activate Stream',
            confirmText: 'Activate',
            cancelText: 'Cancel',
            confirmButtonClass: 'success'
        }
    );
    updateResults(`Stream activation result: ${result ? 'Activated' : 'Cancelled'}`);
}

// Form submission test functions (like in streams.php)
async function testFormSubmission(event, action) {
    event.preventDefault(); // Prevent form submission
    
    let confirmed = false;
    let message = '';
    
    switch(action) {
        case 'delete':
            confirmed = await customDanger(
                `Are you sure you want to permanently delete this test item?<br><br><strong>This action cannot be undone!</strong><br><br>This will also delete all associated data.`,
                {
                    title: 'Delete Item',
                    confirmText: 'Delete Permanently',
                    cancelText: 'Cancel',
                    confirmButtonClass: 'danger'
                }
            );
            message = `Delete form result: ${confirmed ? 'Submitted' : 'Cancelled'}`;
            break;
            
        case 'activate':
            confirmed = await customWarning(
                `Are you sure you want to activate this test item?<br><br>This will:<br>• Make this item active<br>• <strong>Deactivate all other items</strong><br>• Set this as your current working item`,
                {
                    title: 'Activate Item',
                    confirmText: 'Activate',
                    cancelText: 'Cancel',
                    confirmButtonClass: 'success'
                }
            );
            message = `Activate form result: ${confirmed ? 'Submitted' : 'Cancelled'}`;
            break;
            
        case 'deactivate':
            confirmed = await customWarning(
                `Are you sure you want to deactivate this test item?<br><br>This will:<br>• Make the item inactive<br>• Prevent new operations for this item<br>• Keep existing data intact`,
                {
                    title: 'Deactivate Item',
                    confirmText: 'Deactivate',
                    cancelText: 'Cancel',
                    confirmButtonClass: 'warning'
                }
            );
            message = `Deactivate form result: ${confirmed ? 'Submitted' : 'Cancelled'}`;
            break;
    }
    
    updateResults(message);
    
    if (confirmed) {
        // Simulate form submission
        setTimeout(() => {
            updateResults(message + ' - Form would be submitted now!');
        }, 1000);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
