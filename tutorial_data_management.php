<?php
$pageTitle = 'Data Management Tutorial';
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
            <h1><i class="fas fa-database"></i> Data Management Tutorial</h1>
            <p>Master the core data management modules for effective timetable generation</p>
        </div>

        <!-- Content -->
        <div class="tutorial-content">
            <!-- Overview Section -->
            <div class="section">
                <h2><i class="fas fa-info-circle"></i> Data Management Overview</h2>
                <p>Data Management is the foundation of your timetable system. These modules contain all the essential information needed to generate accurate and conflict-free timetables.</p>
                
                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Setup Order</h4>
                    <p>Follow this recommended order: Departments → Programs → Lecturers → Courses → Levels → Classes → Rooms → Room Types. This ensures proper relationships and dependencies.</p>
                </div>
            </div>

            <!-- Core Modules -->
            <div class="section">
                <h2><i class="fas fa-cogs"></i> Core Data Modules</h2>
                
                <div class="module-grid">
                    <div class="module-card">
                        <h4><i class="fas fa-building"></i> Departments</h4>
                        <p>Academic departments are the organizational units of your institution.</p>
                        <ul class="module-features">
                            <li>Add/edit department names and codes</li>
                            <li>Set department status (active/inactive)</li>
                            <li>Search and filter departments</li>
                            <li>Bulk operations for efficiency</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-graduation-cap"></i> Programs</h4>
                        <p>Academic programs offered by departments.</p>
                        <ul class="module-features">
                            <li>Create degree programs and certificates</li>
                            <li>Link programs to departments</li>
                            <li>Set program duration and requirements</li>
                            <li>Manage program status</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-chalkboard-teacher"></i> Lecturers</h4>
                        <p>Teaching staff who will be assigned to courses.</p>
                        <ul class="module-features">
                            <li>Add lecturer personal information</li>
                            <li>Set availability and preferences</li>
                            <li>Assign to departments</li>
                            <li>Track teaching load</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-book"></i> Courses</h4>
                        <p>Individual subjects and classes offered.</p>
                        <ul class="module-features">
                            <li>Create course catalog</li>
                            <li>Set credit hours and prerequisites</li>
                            <li>Link courses to departments</li>
                            <li>Define course requirements</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-layer-group"></i> Levels</h4>
                        <p>Academic levels (100, 200, 300, 400, etc.).</p>
                        <ul class="module-features">
                            <li>Define academic year levels</li>
                            <li>Set level-specific requirements</li>
                            <li>Configure level progression rules</li>
                            <li>Manage level status</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-users"></i> Classes</h4>
                        <p>Student groups that will attend courses.</p>
                        <ul class="module-features">
                            <li>Create class groups</li>
                            <li>Set class capacity</li>
                            <li>Link to programs and levels</li>
                            <li>Manage class divisions</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-door-open"></i> Rooms</h4>
                        <p>Physical spaces available for classes.</p>
                        <ul class="module-features">
                            <li>Add room details and capacity</li>
                            <li>Set room availability</li>
                            <li>Assign room types</li>
                            <li>Manage room status</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-tags"></i> Room Types</h4>
                        <p>Categories of rooms (Lecture Hall, Lab, Computer Lab, etc.).</p>
                        <ul class="module-features">
                            <li>Define room categories</li>
                            <li>Set type-specific requirements</li>
                            <li>Configure equipment needs</li>
                            <li>Manage type availability</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Common Operations -->
            <div class="section">
                <h2><i class="fas fa-tools"></i> Common Operations</h2>
                
                <h3>Adding New Records</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Click "Add New" Button:</strong> Located at the top of each module page</li>
                        <li><strong>Fill Required Fields:</strong> Complete all mandatory information</li>
                        <li><strong>Set Status:</strong> Choose active/inactive as appropriate</li>
                        <li><strong>Save Record:</strong> Click "Save" to store the information</li>
                        <li><strong>Verify Entry:</strong> Check the record appears in the list</li>
                    </ol>
                </div>

                <h3>Editing Existing Records</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Locate Record:</strong> Use search or scroll to find the item</li>
                        <li><strong>Click Edit Button:</strong> Usually a pencil icon in the actions column</li>
                        <li><strong>Modify Information:</strong> Update the necessary fields</li>
                        <li><strong>Save Changes:</strong> Click "Update" to save modifications</li>
                        <li><strong>Confirm Update:</strong> Verify changes are reflected in the list</li>
                    </ol>
                </div>

                <h3>Search and Filter</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Use Search Bar:</strong> Type keywords to find specific records</li>
                        <li><strong>Apply Filters:</strong> Use dropdown filters for status, department, etc.</li>
                        <li><strong>Sort Results:</strong> Click column headers to sort data</li>
                        <li><strong>Clear Filters:</strong> Reset to view all records</li>
                    </ol>
                </div>
            </div>

            <!-- Best Practices -->
            <div class="section">
                <h2><i class="fas fa-star"></i> Best Practices</h2>
                
                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Data Quality Tips</h4>
                    <p>Always use consistent naming conventions, avoid duplicate entries, and regularly review inactive records. This ensures clean data for timetable generation.</p>
                </div>

                <div class="step-list">
                    <ol>
                        <li><strong>Consistent Naming:</strong> Use standard formats for codes and names</li>
                        <li><strong>Complete Information:</strong> Fill all required fields thoroughly</li>
                        <li><strong>Regular Updates:</strong> Keep information current and accurate</li>
                        <li><strong>Status Management:</strong> Properly manage active/inactive status</li>
                        <li><strong>Relationship Integrity:</strong> Ensure proper links between related records</li>
                        <li><strong>Backup Data:</strong> Export data regularly for backup purposes</li>
                    </ol>
                </div>

                <div class="warning-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Important Warnings</h4>
                    <p>Deleting records that are referenced by other modules will cause errors. Always check dependencies before removing data. Use the inactive status instead of deletion when possible.</p>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="section">
                <h2><i class="fas fa-wrench"></i> Troubleshooting</h2>
                
                <h3>Common Issues</h3>
                <div class="module-grid">
                    <div class="module-card">
                        <h4><i class="fas fa-exclamation-circle"></i> Duplicate Entries</h4>
                        <p>If you see duplicate records, use the search function to find them and either edit or deactivate the duplicates.</p>
                    </div>
                    <div class="module-card">
                        <h4><i class="fas fa-link"></i> Broken Relationships</h4>
                        <p>Ensure all foreign key relationships are properly maintained. Check that departments exist before adding programs.</p>
                    </div>
                    <div class="module-card">
                        <h4><i class="fas fa-search"></i> Search Not Working</h4>
                        <p>Try refreshing the page or clearing browser cache. Check that you're using the correct search terms.</p>
                    </div>
                    <div class="module-card">
                        <h4><i class="fas fa-save"></i> Save Failures</h4>
                        <p>Verify all required fields are filled and check for special characters that might cause issues.</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="navigation-buttons">
                <a href="tutorial_dashboard.php" class="nav-btn btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Previous: Dashboard
                </a>
                <a href="tutorial_assignment_management.php" class="nav-btn btn btn-primary">
                    Next: Assignment Management <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>