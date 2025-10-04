<?php
$pageTitle = 'Assignment Management Tutorial';
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
            <h1><i class="fas fa-tasks"></i> Assignment Management Tutorial</h1>
            <p>Learn how to create the essential assignments needed for timetable generation</p>
        </div>

        <!-- Content -->
        <div class="tutorial-content">
            <!-- Overview Section -->
            <div class="section">
                <h2><i class="fas fa-info-circle"></i> Assignment Management Overview</h2>
                <p>Assignment Management is the bridge between your data and timetable generation. These modules create the relationships that tell the system which lecturers teach which courses and which classes take which courses.</p>
                
                <div class="warning-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Prerequisites</h4>
                    <p>Before using Assignment Management, ensure you have completed the Data Management setup: Departments, Programs, Lecturers, Courses, Levels, Classes, Rooms, and Room Types.</p>
                </div>
            </div>

            <!-- Assignment Modules -->
            <div class="section">
                <h2><i class="fas fa-link"></i> Assignment Modules</h2>
                
                <div class="module-grid">
                    <div class="module-card">
                        <h4><i class="fas fa-user-plus"></i> Lecturer Course Assignment</h4>
                        <p>Assign lecturers to specific courses they will teach.</p>
                        <ul class="module-features">
                            <li>Link lecturers to courses</li>
                            <li>Set teaching preferences</li>
                            <li>Define lecturer availability</li>
                            <li>Manage teaching load</li>
                            <li>Track assignment status</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-sitemap"></i> Class Course Assignment</h4>
                        <p>Assign courses to specific classes that will take them.</p>
                        <ul class="module-features">
                            <li>Link classes to courses</li>
                            <li>Set class capacity</li>
                            <li>Define course requirements</li>
                            <li>Manage class schedules</li>
                            <li>Track enrollment</li>
                        </ul>
                    </div>

                    <div class="module-card">
                        <h4><i class="fas fa-book-clock"></i> Course Room Type</h4>
                        <p>Define what type of room each course requires.</p>
                        <ul class="module-features">
                            <li>Set room type requirements</li>
                            <li>Define equipment needs</li>
                            <li>Specify capacity requirements</li>
                            <li>Manage room preferences</li>
                            <li>Track room availability</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Lecturer Course Assignment -->
            <div class="section">
                <h2><i class="fas fa-user-plus"></i> Lecturer Course Assignment</h2>
                
                <h3>Purpose</h3>
                <p>This module creates the relationship between lecturers and courses, telling the system which lecturer will teach which course. This is essential for conflict detection and timetable generation.</p>

                <h3>How to Assign Lecturers to Courses</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Navigate to Lecturer Course:</strong> Click on "Lecturer Course" in the Assignment Management section</li>
                        <li><strong>Click "Add New Assignment":</strong> Use the "Add New" button to create a new assignment</li>
                        <li><strong>Select Lecturer:</strong> Choose from the dropdown list of available lecturers</li>
                        <li><strong>Select Course:</strong> Choose the course this lecturer will teach</li>
                        <li><strong>Set Preferences:</strong> Configure any teaching preferences or constraints</li>
                        <li><strong>Save Assignment:</strong> Click "Save" to create the assignment</li>
                        <li><strong>Verify Assignment:</strong> Check that the assignment appears in the list</li>
                    </ol>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Pro Tips</h4>
                    <p>You can assign multiple lecturers to the same course if it's team-taught. You can also assign one lecturer to multiple courses. The system will automatically detect conflicts during timetable generation.</p>
                </div>
            </div>

            <!-- Class Course Assignment -->
            <div class="section">
                <h2><i class="fas fa-sitemap"></i> Class Course Assignment</h2>
                
                <h3>Purpose</h3>
                <p>This module assigns courses to classes, defining which classes will take which courses. This determines the student groups that need to be scheduled.</p>

                <h3>How to Assign Courses to Classes</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Navigate to Class Course:</strong> Click on "Class Course" in the Assignment Management section</li>
                        <li><strong>Click "Add New Assignment":</strong> Use the "Add New" button to create a new assignment</li>
                        <li><strong>Select Class:</strong> Choose the class that will take this course</li>
                        <li><strong>Select Course:</strong> Choose the course this class will take</li>
                        <li><strong>Set Requirements:</strong> Define any specific requirements or constraints</li>
                        <li><strong>Save Assignment:</strong> Click "Save" to create the assignment</li>
                        <li><strong>Verify Assignment:</strong> Check that the assignment appears in the list</li>
                    </ol>
                </div>

                <div class="warning-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> Important Note</h4>
                    <p>Make sure the class and course are compatible (same level, same program, etc.). The system will validate these relationships during assignment creation.</p>
                </div>
            </div>

            <!-- Course Room Type Assignment -->
            <div class="section">
                <h2><i class="fas fa-book-clock"></i> Course Room Type Assignment</h2>
                
                <h3>Purpose</h3>
                <p>This module defines what type of room each course requires. This ensures courses are scheduled in appropriate spaces (lecture halls, labs, computer labs, etc.).</p>

                <h3>How to Assign Room Types to Courses</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Navigate to Course Room Type:</strong> Click on "Course Room Type" in the Assignment Management section</li>
                        <li><strong>Click "Add New Assignment":</strong> Use the "Add New" button to create a new assignment</li>
                        <li><strong>Select Course:</strong> Choose the course that needs a specific room type</li>
                        <li><strong>Select Room Type:</strong> Choose the required room type (Lecture Hall, Lab, Computer Lab, etc.)</li>
                        <li><strong>Set Capacity:</strong> Define minimum capacity requirements if needed</li>
                        <li><strong>Save Assignment:</strong> Click "Save" to create the assignment</li>
                        <li><strong>Verify Assignment:</strong> Check that the assignment appears in the list</li>
                    </ol>
                </div>

                <div class="success-box">
                    <h4><i class="fas fa-check-circle"></i> Success Indicator</h4>
                    <p>When all assignments are complete, you'll see comprehensive lists showing all lecturer-course, class-course, and course-room type relationships. This data is now ready for timetable generation.</p>
                </div>
            </div>

            <!-- Best Practices -->
            <div class="section">
                <h2><i class="fas fa-star"></i> Best Practices</h2>
                
                <div class="step-list">
                    <ol>
                        <li><strong>Complete All Assignments:</strong> Ensure every course has both lecturer and class assignments</li>
                        <li><strong>Verify Room Types:</strong> Make sure all courses have appropriate room type assignments</li>
                        <li><strong>Check Capacity:</strong> Ensure room capacities match class sizes</li>
                        <li><strong>Review Conflicts:</strong> Check for potential scheduling conflicts before generating timetables</li>
                        <li><strong>Test Assignments:</strong> Use the conflict detection tools to validate your assignments</li>
                        <li><strong>Document Changes:</strong> Keep track of assignment modifications for future reference</li>
                    </ol>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Assignment Strategy</h4>
                    <p>Start with core courses first, then add electives. Consider lecturer preferences and availability when making assignments. Use the bulk assignment features for efficiency when dealing with many courses.</p>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="section">
                <h2><i class="fas fa-wrench"></i> Troubleshooting</h2>
                
                <h3>Common Issues</h3>
                <div class="module-grid">
                    <div class="module-card">
                        <h4><i class="fas fa-exclamation-circle"></i> Missing Assignments</h4>
                        <p>If courses appear without lecturers or classes, check that all necessary assignments have been created. Use the search function to find unassigned courses.</p>
                    </div>
                    <div class="module-card">
                        <h4><i class="fas fa-link"></i> Broken Relationships</h4>
                        <p>If assignments fail to save, verify that the referenced lecturer, course, or class exists and is active in the system.</p>
                    </div>
                    <div class="module-card">
                        <h4><i class="fas fa-calendar-times"></i> Scheduling Conflicts</h4>
                        <p>If you see conflict warnings, review lecturer availability and room type requirements. Adjust assignments as needed.</p>
                    </div>
                    <div class="module-card">
                        <h4><i class="fas fa-users"></i> Capacity Issues</h4>
                        <p>If classes are too large for assigned rooms, either increase room capacity or split large classes into smaller groups.</p>
                    </div>
                </div>
            </div>

            <!-- Next Steps -->
            <div class="section">
                <h2><i class="fas fa-arrow-right"></i> Next Steps</h2>
                <p>Once you've completed all assignments, you're ready to move on to timetable generation. The system will use all your assignment data to create optimized schedules.</p>
                
                <div class="success-box">
                    <h4><i class="fas fa-check-circle"></i> Ready for Timetable Generation</h4>
                    <p>With all assignments complete, you can now proceed to the Timetable Management tutorial to learn how to generate and manage your timetables.</p>
                </div>
            </div>

            <!-- Navigation -->
            <div class="navigation-buttons">
                <a href="tutorial_data_management.php" class="nav-btn btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Previous: Data Management
                </a>
                <a href="tutorial_timetable_management.php" class="nav-btn btn btn-primary">
                    Next: Timetable Management <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>