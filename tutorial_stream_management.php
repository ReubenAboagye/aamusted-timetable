<?php
$pageTitle = 'Stream Management Tutorial';
include 'connect.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
.tutorial-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.tutorial-header {
    background: linear-gradient(135deg, var(--primary-color), var(--hover-color));
    color: white;
    padding: 40px;
    border-radius: 16px;
    text-align: center;
    margin-bottom: 30px;
    box-shadow: 0 15px 35px rgba(128, 0, 32, 0.3);
}

.tutorial-header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 15px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.tutorial-header p {
    font-size: 1.1rem;
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

.tutorial-content {
    background: white;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.section {
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 2px solid #f0f0f0;
}

.section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.section h2 {
    color: var(--primary-color);
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.section h2 i {
    margin-right: 15px;
    font-size: 2rem;
}

.section h3 {
    color: var(--primary-color);
    font-size: 1.3rem;
    font-weight: 600;
    margin: 25px 0 15px;
    padding-left: 20px;
    border-left: 4px solid var(--primary-color);
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.feature-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 12px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.feature-card:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.feature-card h4 {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.feature-card h4 i {
    margin-right: 10px;
    font-size: 1.2rem;
}

.feature-card p {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.5;
    margin: 0;
}

.step-list {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 12px;
    margin: 20px 0;
}

.step-list ol {
    margin: 0;
    padding-left: 20px;
}

.step-list li {
    margin-bottom: 15px;
    line-height: 1.6;
    color: #555;
}

.step-list li strong {
    color: var(--primary-color);
}

.tip-box {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border: 2px solid #2196f3;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
}

.tip-box h4 {
    color: #1976d2;
    font-weight: 600;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.tip-box h4 i {
    margin-right: 10px;
}

.tip-box p {
    color: #1976d2;
    margin: 0;
    line-height: 1.6;
}

.warning-box {
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
    border: 2px solid #ff9800;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
}

.warning-box h4 {
    color: #f57c00;
    font-weight: 600;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.warning-box h4 i {
    margin-right: 10px;
}

.warning-box p {
    color: #f57c00;
    margin: 0;
    line-height: 1.6;
}

.success-box {
    background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
    border: 2px solid #4caf50;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
}

.success-box h4 {
    color: #2e7d32;
    font-weight: 600;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.success-box h4 i {
    margin-right: 10px;
}

.success-box p {
    color: #2e7d32;
    margin: 0;
    line-height: 1.6;
}

.info-box {
    background: linear-gradient(135deg, #f3e5f5, #e1bee7);
    border: 2px solid #9c27b0;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
}

.info-box h4 {
    color: #7b1fa2;
    font-weight: 600;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
}

.info-box h4 i {
    margin-right: 10px;
}

.info-box p {
    color: #7b1fa2;
    margin: 0;
    line-height: 1.6;
}

.navigation-buttons {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 40px;
    padding-top: 30px;
    border-top: 2px solid #f0f0f0;
}

.nav-btn {
    padding: 12px 24px;
    font-weight: 600;
    border-radius: 8px;
    text-decoration: none;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
}

.nav-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}

.nav-btn i {
    margin: 0 8px;
}

@media (max-width: 768px) {
    .tutorial-header {
        padding: 30px 20px;
    }
    
    .tutorial-header h1 {
        font-size: 2rem;
    }
    
    .tutorial-content {
        padding: 30px 20px;
    }
    
    .feature-grid {
        grid-template-columns: 1fr;
    }
    
    .navigation-buttons {
        flex-direction: column;
        gap: 15px;
    }
    
    .nav-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="main-content">
    <div class="tutorial-page">
        <!-- Header -->
        <div class="tutorial-header">
            <h1><i class="fas fa-cogs"></i> Stream Management Tutorial</h1>
            <p>Learn how to create and manage different academic streams for your institution</p>
        </div>

        <!-- Content -->
        <div class="tutorial-content">
            <!-- Overview Section -->
            <div class="section">
                <h2><i class="fas fa-info-circle"></i> Stream Management Overview</h2>
                <p>Stream Management allows you to create and manage different academic streams (e.g., Regular, Evening, Weekend, Distance Learning) with separate configurations, time slots, and data.</p>
                
                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> What are Streams?</h4>
                    <p>Streams are different academic programs or delivery modes that your institution offers. Each stream can have its own schedule, time slots, and academic data while sharing the same core system.</p>
                </div>
            </div>

            <!-- Stream Features -->
            <div class="section">
                <h2><i class="fas fa-star"></i> Stream Features</h2>
                
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-calendar"></i> Separate Schedules</h4>
                        <p>Each stream can have its own timetable and scheduling requirements</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-clock"></i> Custom Time Slots</h4>
                        <p>Different streams can operate on different time schedules</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-users"></i> Independent Data</h4>
                        <p>Lecturers, courses, and classes can be stream-specific</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-toggle-on"></i> Easy Switching</h4>
                        <p>Switch between streams seamlessly from the dashboard</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-shield-alt"></i> Data Isolation</h4>
                        <p>Data from one stream doesn't interfere with another</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-cog"></i> Flexible Configuration</h4>
                        <p>Configure each stream according to its specific needs</p>
                    </div>
                </div>
            </div>

            <!-- Creating Streams -->
            <div class="section">
                <h2><i class="fas fa-plus-circle"></i> Creating New Streams</h2>
                
                <h3>How to Create a Stream</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Navigate to Stream Management:</strong> Click on "Stream Management" in the sidebar or dashboard</li>
                        <li><strong>Click "Add New Stream":</strong> Use the "Add New" button to create a new stream</li>
                        <li><strong>Enter Stream Details:</strong>
                            <ul style="margin-top: 10px; padding-left: 20px;">
                                <li><strong>Name:</strong> Enter a descriptive name (e.g., "Evening Program", "Weekend Classes")</li>
                                <li><strong>Code:</strong> Enter a short code (e.g., "EVE", "WKD")</li>
                                <li><strong>Description:</strong> Provide a detailed description of the stream</li>
                            </ul>
                        </li>
                        <li><strong>Configure Active Days:</strong> Select which days of the week this stream operates</li>
                        <li><strong>Set Status:</strong> Choose whether the stream is active or inactive</li>
                        <li><strong>Configure Time Slots:</strong> Set up time slots specific to this stream</li>
                        <li><strong>Save Stream:</strong> Click "Save" to create the stream</li>
                    </ol>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Stream Naming Tips</h4>
                    <p>Use clear, descriptive names that immediately identify the stream's purpose. Avoid abbreviations that might be confusing to users.</p>
                </div>
            </div>

            <!-- Stream Configuration -->
            <div class="section">
                <h2><i class="fas fa-cog"></i> Stream Configuration</h2>
                
                <h3>Time Slots Configuration</h3>
                <p>Each stream can have its own time slot configuration:</p>
                
                <div class="step-list">
                    <ol>
                        <li><strong>Select Time Slots:</strong> Choose which time slots are available for this stream</li>
                        <li><strong>Set Operating Hours:</strong> Define when this stream operates (e.g., 6:00 PM - 10:00 PM for evening)</li>
                        <li><strong>Configure Breaks:</strong> Set break times specific to the stream</li>
                        <li><strong>Define Constraints:</strong> Set any special scheduling constraints</li>
                    </ol>
                </div>

                <h3>Active Days Configuration</h3>
                <p>Define which days of the week the stream operates:</p>
                
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-calendar-week"></i> Regular Stream</h4>
                        <p>Monday to Friday, 8:00 AM - 5:00 PM</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-moon"></i> Evening Stream</h4>
                        <p>Monday to Friday, 6:00 PM - 10:00 PM</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-calendar-day"></i> Weekend Stream</h4>
                        <p>Saturday and Sunday, 9:00 AM - 4:00 PM</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-laptop"></i> Distance Learning</h4>
                        <p>Flexible scheduling with online components</p>
                    </div>
                </div>
            </div>

            <!-- Switching Streams -->
            <div class="section">
                <h2><i class="fas fa-exchange-alt"></i> Switching Between Streams</h2>
                
                <h3>How to Switch Streams</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Use Dashboard Selector:</strong> Use the stream dropdown in the dashboard header</li>
                        <li><strong>Select Target Stream:</strong> Choose the stream you want to switch to</li>
                        <li><strong>Click Switch:</strong> Press the "Switch" button to activate the stream</li>
                        <li><strong>Verify Switch:</strong> Check that the header shows the correct stream name</li>
                        <li><strong>Access Stream Data:</strong> All modules will now show data for the selected stream</li>
                    </ol>
                </div>

                <div class="warning-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Stream Switching Warning</h4>
                    <p>When you switch streams, all data views and operations will be filtered to show only data for the selected stream. Make sure you're working with the correct stream before making changes.</p>
                </div>
            </div>

            <!-- Managing Streams -->
            <div class="section">
                <h2><i class="fas fa-edit"></i> Managing Existing Streams</h2>
                
                <h3>Editing Stream Information</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Navigate to Stream Management:</strong> Go to the Stream Management page</li>
                        <li><strong>Find Target Stream:</strong> Locate the stream you want to edit</li>
                        <li><strong>Click Edit:</strong> Use the edit button (pencil icon) for the stream</li>
                        <li><strong>Modify Information:</strong> Update the stream details as needed</li>
                        <li><strong>Update Configuration:</strong> Modify time slots or active days if needed</li>
                        <li><strong>Save Changes:</strong> Click "Update" to save your changes</li>
                    </ol>
                </div>

                <h3>Deactivating Streams</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Edit Stream:</strong> Open the stream for editing</li>
                        <li><strong>Uncheck Active Status:</strong> Remove the checkmark from "Is Active"</li>
                        <li><strong>Save Changes:</strong> Save the updated status</li>
                        <li><strong>Verify Deactivation:</strong> Confirm the stream is no longer available for selection</li>
                    </ol>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Stream Management Tips</h4>
                    <p>Instead of deleting streams, consider deactivating them. This preserves historical data while removing them from active use. You can reactivate streams later if needed.</p>
                </div>
            </div>

            <!-- Stream Data Management -->
            <div class="section">
                <h2><i class="fas fa-database"></i> Stream Data Management</h2>
                
                <h3>Data Isolation</h3>
                <p>Each stream maintains separate data for:</p>
                
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-building"></i> Departments</h4>
                        <p>Stream-specific department configurations</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-chalkboard-teacher"></i> Lecturers</h4>
                        <p>Lecturers assigned to specific streams</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-book"></i> Courses</h4>
                        <p>Courses offered in each stream</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-users"></i> Classes</h4>
                        <p>Student groups for each stream</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-door-open"></i> Rooms</h4>
                        <p>Room assignments and availability</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-calendar-alt"></i> Timetables</h4>
                        <p>Separate timetables for each stream</p>
                    </div>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Data Sharing</h4>
                    <p>While streams maintain separate data, some resources like rooms and lecturers can be shared between streams if configured appropriately.</p>
                </div>
            </div>

            <!-- Best Practices -->
            <div class="section">
                <h2><i class="fas fa-star"></i> Best Practices</h2>
                
                <div class="step-list">
                    <ol>
                        <li><strong>Plan Stream Structure:</strong> Design your stream structure before creating streams</li>
                        <li><strong>Use Clear Naming:</strong> Choose descriptive names that clearly identify each stream</li>
                        <li><strong>Configure Time Slots:</strong> Set up appropriate time slots for each stream's schedule</li>
                        <li><strong>Test Switching:</strong> Regularly test stream switching to ensure it works properly</li>
                        <li><strong>Monitor Data:</strong> Keep track of data in each stream to avoid confusion</li>
                        <li><strong>Document Configuration:</strong> Maintain documentation of each stream's configuration</li>
                        <li><strong>Regular Maintenance:</strong> Periodically review and update stream configurations</li>
                    </ol>
                </div>

                <div class="success-box">
                    <h4><i class="fas fa-check-circle"></i> Stream Success</h4>
                    <p>Well-configured streams provide flexibility and organization for institutions with multiple academic programs or delivery modes.</p>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="section">
                <h2><i class="fas fa-wrench"></i> Troubleshooting</h2>
                
                <h3>Common Issues</h3>
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-question-circle"></i> Stream Not Appearing</h4>
                        <p>Check that the stream is marked as active and refresh the page. Verify stream configuration is complete.</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-exclamation-triangle"></i> Switch Failures</h4>
                        <p>Ensure you have proper permissions and that the target stream exists and is active.</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-database"></i> Data Not Showing</h4>
                        <p>Verify you're in the correct stream and that data exists for that stream. Check stream-specific filters.</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-clock"></i> Time Slot Issues</h4>
                        <p>Ensure time slots are properly configured for the stream and that they don't conflict with other streams.</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="navigation-buttons">
                <a href="tutorial_timetable_management.php" class="nav-btn btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Previous: Timetable Management
                </a>
                <a href="tutorial_extract_export.php" class="nav-btn btn btn-primary">
                    Next: Extract & Export <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>