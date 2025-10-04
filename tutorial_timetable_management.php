<?php
$pageTitle = 'Timetable Management Tutorial';
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

.module-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.module-card {
    background: #f8f9fa;
    padding: 25px;
    border-radius: 12px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.module-card:hover {
    border-color: var(--primary-color);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

.module-card h4 {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    font-size: 1.2rem;
}

.module-card h4 i {
    margin-right: 12px;
    font-size: 1.4rem;
}

.module-card p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 15px;
}

.module-features {
    list-style: none;
    padding: 0;
    margin: 0;
}

.module-features li {
    color: #555;
    margin-bottom: 8px;
    padding-left: 20px;
    position: relative;
}

.module-features li::before {
    content: '✓';
    position: absolute;
    left: 0;
    color: var(--brand-green);
    font-weight: bold;
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
    
    .module-grid {
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
            <h1><i class="fas fa-calendar-alt"></i> Timetable Management Tutorial</h1>
            <p>Master the art of generating, managing, and optimizing academic timetables</p>
        </div>

        <!-- Content -->
        <div class="tutorial-content">
            <!-- Overview Section -->
            <div class="section">
                <h2><i class="fas fa-info-circle"></i> Timetable Management Overview</h2>
                <p>Timetable Management is the core functionality of the system. It uses advanced genetic algorithms to create optimized schedules that minimize conflicts and maximize efficiency.</p>
                
                <div class="warning-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Prerequisites</h4>
                    <p>Before generating timetables, ensure you have completed: Data Management setup, Assignment Management (Lecturer-Course, Class-Course, Course-Room Type assignments), and Time Slots configuration.</p>
                </div>
            </div>

            <!-- Timetable Modules -->
            <div class="section">
                <h2><i class="fas fa-cogs"></i> Timetable Management Modules</h2>
                
                <div class="module-grid">
                    <div class="module-card">
                        <h4><i class="fas fa-calendar-plus"></i> Generate Timetable</h4>
                        <p>The main timetable generation engine using genetic algorithms.</p>
                        <ul class="module-features">
                            <li>AI-powered optimization</li>
                            <li>Conflict detection and resolution</li>
                            <li>Multiple generation strategies</li>
                            <li>Real-time progress tracking</li>
                            <li>Automatic constraint handling</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-clock"></i> Time Slots</h4>
                        <p>Configure available time periods for scheduling.</p>
                        <ul class="module-features">
                            <li>Define daily time periods</li>
                            <li>Set break times</li>
                            <li>Configure working hours</li>
                            <li>Manage slot availability</li>
                            <li>Stream-specific configurations</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-save"></i> Saved Timetables</h4>
                        <p>View and manage previously generated timetables.</p>
                        <ul class="module-features">
                            <li>Browse saved versions</li>
                            <li>Compare different timetables</li>
                            <li>Export to various formats</li>
                            <li>Version management</li>
                            <li>Restore previous versions</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-exclamation-triangle"></i> Lecturer Conflicts</h4>
                        <p>Identify and resolve scheduling conflicts.</p>
                        <ul class="module-features">
                            <li>Detect double bookings</li>
                            <li>Identify room conflicts</li>
                            <li>Resolve time overlaps</li>
                            <li>Generate conflict reports</li>
                            <li>Suggest resolutions</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Time Slots Configuration -->
            <div class="section">
                <h2><i class="fas fa-clock"></i> Time Slots Configuration</h2>
                
                <h3>Purpose</h3>
                <p>Time slots define when classes can be scheduled. Proper configuration is essential for realistic timetable generation.</p>

                <h3>How to Configure Time Slots</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Navigate to Time Slots:</strong> Click on "Time Slots" in the Timetable Management section</li>
                        <li><strong>Review Current Slots:</strong> Check existing time periods and their status</li>
                        <li><strong>Add New Slots:</strong> Use "Add New" to create additional time periods</li>
                        <li><strong>Set Time Ranges:</strong> Define start and end times for each slot</li>
                        <li><strong>Configure Breaks:</strong> Set break times between classes</li>
                        <li><strong>Set Availability:</strong> Mark slots as available/unavailable</li>
                        <li><strong>Save Configuration:</strong> Save your time slot settings</li>
                    </ol>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Time Slot Tips</h4>
                    <p>Consider your institution's working hours, break times, and any special scheduling requirements. Common slots include: 8:00-9:00, 9:00-10:00, 10:00-11:00, etc.</p>
                </div>
            </div>

            <!-- Generate Timetable -->
            <div class="section">
                <h2><i class="fas fa-calendar-plus"></i> Generate Timetable</h2>
                
                <h3>Purpose</h3>
                <p>The Generate Timetable feature uses advanced genetic algorithms to create optimized schedules that minimize conflicts and maximize efficiency.</p>

                <h3>How to Generate a Timetable</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Navigate to Generate Timetable:</strong> Click on "Generate Lecture Timetable" in the Timetable Management section</li>
                        <li><strong>Review Prerequisites:</strong> Ensure all data and assignments are complete</li>
                        <li><strong>Configure Parameters:</strong> Set generation parameters (population size, generations, etc.)</li>
                        <li><strong>Select Constraints:</strong> Choose which constraints to apply during generation</li>
                        <li><strong>Start Generation:</strong> Click "Generate Timetable" to begin the process</li>
                        <li><strong>Monitor Progress:</strong> Watch the real-time progress indicators</li>
                        <li><strong>Review Results:</strong> Examine the generated timetable for conflicts</li>
                        <li><strong>Save Timetable:</strong> Save the successful timetable for future use</li>
                    </ol>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Generation Process</h4>
                    <p>The system uses genetic algorithms that evolve solutions over multiple generations. Each generation improves upon the previous one, leading to increasingly optimal timetables.</p>
                </div>

                <div class="warning-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Generation Time</h4>
                    <p>Timetable generation can take several minutes for large datasets. The system will show progress indicators and estimated completion times. Do not close the browser during generation.</p>
                </div>
            </div>

            <!-- Saved Timetables -->
            <div class="section">
                <h2><i class="fas fa-save"></i> Saved Timetables</h2>
                
                <h3>Purpose</h3>
                <p>Saved Timetables allows you to view, compare, and manage previously generated timetables. This is essential for version control and historical reference.</p>

                <h3>How to Manage Saved Timetables</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Navigate to Saved Timetables:</strong> Click on "Saved Timetable" in the Timetable Management section</li>
                        <li><strong>Browse Timetables:</strong> View all saved timetables organized by stream and version</li>
                        <li><strong>Filter Results:</strong> Use filters to find specific timetables by stream, semester, or version</li>
                        <li><strong>View Details:</strong> Click on a timetable to see detailed scheduling information</li>
                        <li><strong>Export Timetable:</strong> Export timetables to PDF, Excel, or other formats</li>
                        <li><strong>Compare Versions:</strong> Compare different timetable versions side by side</li>
                        <li><strong>Manage Versions:</strong> Delete old versions or restore previous ones</li>
                    </ol>
                </div>

                <div class="success-box">
                    <h4><i class="fas fa-check-circle"></i> Version Control</h4>
                    <p>Each generated timetable is automatically saved with a unique version identifier. This allows you to maintain multiple timetable versions for different academic periods or scenarios.</p>
                </div>
            </div>

            <!-- Lecturer Conflicts -->
            <div class="section">
                <h2><i class="fas fa-exclamation-triangle"></i> Lecturer Conflicts</h2>
                
                <h3>Purpose</h3>
                <p>Lecturer Conflicts helps identify and resolve scheduling conflicts that may occur in generated timetables.</p>

                <h3>How to Handle Conflicts</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Navigate to Lecturer Conflicts:</strong> Click on "Lecturer Conflicts" in the Timetable Management section</li>
                        <li><strong>Review Conflict List:</strong> Examine all detected conflicts</li>
                        <li><strong>Analyze Conflict Types:</strong> Understand the nature of each conflict</li>
                        <li><strong>Resolve Conflicts:</strong> Use suggested resolutions or manual adjustments</li>
                        <li><strong>Regenerate if Needed:</strong> If conflicts are severe, consider regenerating the timetable</li>
                        <li><strong>Document Resolutions:</strong> Keep track of how conflicts were resolved</li>
                    </ol>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Conflict Prevention</h4>
                    <p>Most conflicts can be prevented by ensuring accurate lecturer availability data and proper room type assignments. Regular conflict checking helps maintain timetable quality.</p>
                </div>
            </div>

            <!-- Best Practices -->
            <div class="section">
                <h2><i class="fas fa-star"></i> Best Practices</h2>
                
                <div class="step-list">
                    <ol>
                        <li><strong>Complete Setup First:</strong> Ensure all data and assignments are complete before generation</li>
                        <li><strong>Configure Time Slots:</strong> Set realistic time slots that match your institution's schedule</li>
                        <li><strong>Test with Small Datasets:</strong> Start with smaller datasets to test your configuration</li>
                        <li><strong>Monitor Generation Progress:</strong> Watch for any errors or warnings during generation</li>
                        <li><strong>Review Results Thoroughly:</strong> Always check generated timetables for conflicts</li>
                        <li><strong>Save Successful Timetables:</strong> Keep multiple versions for comparison</li>
                        <li><strong>Regular Maintenance:</strong> Periodically review and update time slots and constraints</li>
                    </ol>
                </div>

                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Optimization Tips</h4>
                    <p>For best results, ensure lecturer availability is accurate, room capacities match class sizes, and time slots are realistic. The genetic algorithm works best with well-configured constraints.</p>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="section">
                <h2><i class="fas fa-wrench"></i> Troubleshooting</h2>
                
                <h3>Common Issues</h3>
                <div class="module-grid">
                    <div class="module-card">
                        <h4><i class="fas fa-times-circle"></i> Generation Failures</h4>
                        <p>If generation fails, check that all assignments are complete and time slots are properly configured. Review error messages for specific issues.</p>
                    </div>
                    <div class="module-card">
                        <h4><i class="fas fa-exclamation-triangle"></i> High Conflict Rates</h4>
                        <p>If many conflicts occur, review lecturer availability, room assignments, and time slot configurations. Consider adjusting constraints.</p>
                    </div>
                    <div class="module-card">
                        <h4><i class="fas fa-clock"></i> Long Generation Times</h4>
                        <p>Large datasets may take longer to process. Consider reducing the dataset size or adjusting generation parameters for faster results.</p>
                    </div>
                    <div class="module-card">
                        <h4><i class="fas fa-save"></i> Save Issues</h4>
                        <p>If timetables won't save, check that you have proper permissions and that the database connection is stable.</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="navigation-buttons">
                <a href="tutorial_assignment_management.php" class="nav-btn btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Previous: Assignment Management
                </a>
                <a href="tutorial_stream_management.php" class="nav-btn btn btn-primary">
                    Next: Stream Management <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>