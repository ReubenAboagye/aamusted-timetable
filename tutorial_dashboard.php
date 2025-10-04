<?php
$pageTitle = 'Dashboard Tutorial';
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
            <h1><i class="fas fa-home"></i> Dashboard Tutorial</h1>
            <p>Learn how to navigate and use the main dashboard effectively</p>
        </div>

        <!-- Content -->
        <div class="tutorial-content">
            <!-- Overview Section -->
            <div class="section">
                <h2><i class="fas fa-info-circle"></i> Dashboard Overview</h2>
                <p>The Dashboard is your central command center for the AAMUSTED Timetable Generator. It provides:</p>
                
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-chart-bar"></i> Real-time Metrics</h4>
                        <p>View live counts of departments, lecturers, courses, rooms, and classes</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-bolt"></i> Quick Access</h4>
                        <p>Direct links to all major modules with visual indicators</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-stream"></i> Stream Management</h4>
                        <p>Switch between different academic streams easily</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-search"></i> Search Functionality</h4>
                        <p>Quick search across all modules from the dashboard</p>
                    </div>
                </div>
            </div>

            <!-- Stream Selection -->
            <div class="section">
                <h2><i class="fas fa-exchange-alt"></i> Stream Selection</h2>
                <p>Before using any features, you need to select an active stream:</p>
                
                <div class="step-list">
                    <ol>
                        <li><strong>Locate the Stream Selector:</strong> Found in the top-right corner of the dashboard</li>
                        <li><strong>Choose Your Stream:</strong> Select from the dropdown menu (e.g., "Regular", "Evening", "Weekend")</li>
                        <li><strong>Click Switch:</strong> Press the "Switch" button to activate the selected stream</li>
                        <li><strong>Verify Selection:</strong> The current stream name will appear in the header</li>
                    </ol>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Pro Tip</h4>
                    <p>If you see "No Stream Selected" in the header, click on it to go directly to Stream Management and set up your streams.</p>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="section">
                <h2><i class="fas fa-th"></i> Dashboard Grid Navigation</h2>
                <p>The main dashboard grid contains six key modules:</p>

                <h3>Generate Timetable</h3>
                <div class="feature-card">
                    <h4><i class="fas fa-calendar-plus"></i> Generate Timetable</h4>
                    <p>Creates optimized timetables using genetic algorithms. Shows the number of active classes that need scheduling.</p>
                </div>

                <h3>Saved Timetables</h3>
                <div class="feature-card">
                    <h4><i class="fas fa-save"></i> Saved Timetables</h4>
                    <p>View and manage previously generated timetables. Shows the count of distinct timetable versions saved.</p>
                </div>

                <h3>Data Management Modules</h3>
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-building"></i> Departments</h4>
                        <p>Manage academic departments and their details</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-chalkboard-teacher"></i> Lecturers</h4>
                        <p>Add and manage teaching staff information</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-door-open"></i> Rooms</h4>
                        <p>Configure available physical spaces and facilities</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-book"></i> Courses</h4>
                        <p>Manage course catalog and academic offerings</p>
                    </div>
                </div>
            </div>

            <!-- Search Functionality -->
            <div class="section">
                <h2><i class="fas fa-search"></i> Search Functionality</h2>
                <p>The dashboard includes a powerful search feature:</p>
                
                <div class="step-list">
                    <ol>
                        <li><strong>Locate Search Bar:</strong> Found below the page header</li>
                        <li><strong>Enter Search Term:</strong> Type any module name or keyword</li>
                        <li><strong>View Results:</strong> Matching modules will be highlighted, others hidden</li>
                        <li><strong>Clear Search:</strong> Delete text to show all modules again</li>
                    </ol>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Search Tips</h4>
                    <p>Search is case-insensitive and works with partial matches. Try searching for "time", "course", or "room" to see related modules.</p>
                </div>
            </div>

            <!-- Dashboard Overview Section -->
            <div class="section">
                <h2><i class="fas fa-info"></i> Dashboard Overview Panel</h2>
                <p>The bottom section provides a comprehensive overview:</p>
                
                <div class="feature-card">
                    <h4><i class="fas fa-list"></i> Current Status</h4>
                    <p>Shows the current stream name and provides a summary of what each module does. This is especially helpful for new users.</p>
                </div>

                <div class="warning-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Important Note</h4>
                    <p>All data counts and operations are stream-specific. Make sure you're working with the correct stream before making changes.</p>
                </div>
            </div>

            <!-- Best Practices -->
            <div class="section">
                <h2><i class="fas fa-star"></i> Best Practices</h2>
                
                <div class="step-list">
                    <ol>
                        <li><strong>Always Check Stream:</strong> Verify you're in the correct stream before making changes</li>
                        <li><strong>Monitor Metrics:</strong> Keep an eye on the counts to ensure data integrity</li>
                        <li><strong>Use Search:</strong> Quickly find modules instead of scrolling through the sidebar</li>
                        <li><strong>Regular Backups:</strong> Use the saved timetables feature to preserve your work</li>
                        <li><strong>Start with Data:</strong> Set up departments, lecturers, courses, and rooms before generating timetables</li>
                    </ol>
                </div>
            </div>

            <!-- Navigation -->
            <div class="navigation-buttons">
                <a href="tutorial.php" class="nav-btn btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Tutorials
                </a>
                <a href="tutorial_data_management.php" class="nav-btn btn btn-primary">
                    Next: Data Management <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>