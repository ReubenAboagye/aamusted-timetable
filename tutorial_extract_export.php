<?php
$pageTitle = 'Extract & Export Tutorial';
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
            <h1><i class="fas fa-download"></i> Extract & Export Tutorial</h1>
            <p>Learn how to extract timetables and export data for various purposes</p>
        </div>

        <!-- Content -->
        <div class="tutorial-content">
            <!-- Overview Section -->
            <div class="section">
                <h2><i class="fas fa-info-circle"></i> Extract & Export Overview</h2>
                <p>The Extract & Export functionality allows you to generate personalized timetables for individual users and export data in various formats for reporting, printing, and sharing purposes.</p>
                
                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Key Features</h4>
                    <p>Extract timetables for specific lecturers, classes, or rooms. Export data to PDF, Excel, CSV, and other formats. Generate reports and summaries for administrative purposes.</p>
                </div>
            </div>

            <!-- Extract Timetable -->
            <div class="section">
                <h2><i class="fas fa-user-clock"></i> Extract Timetable</h2>
                
                <h3>Purpose</h3>
                <p>The Extract Timetable feature generates personalized timetables for individual users (lecturers, students, or administrators) based on their specific assignments and roles.</p>

                <h3>How to Extract a Timetable</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Navigate to Extract Timetable:</strong> Click on "Extract Timetable" in the sidebar</li>
                        <li><strong>Select User Type:</strong> Choose whether you're extracting for a lecturer, class, or room</li>
                        <li><strong>Choose Specific User:</strong> Select the specific lecturer, class, or room from the dropdown</li>
                        <li><strong>Select Stream:</strong> Choose the stream for which to extract the timetable</li>
                        <li><strong>Choose Semester:</strong> Select the academic semester</li>
                        <li><strong>Set Date Range:</strong> Define the period for the timetable</li>
                        <li><strong>Generate Extract:</strong> Click "Extract Timetable" to generate the personalized schedule</li>
                        <li><strong>View Results:</strong> Review the extracted timetable</li>
                        <li><strong>Export if Needed:</strong> Export the timetable to PDF or other formats</li>
                    </ol>
                </div>

                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-chalkboard-teacher"></i> Lecturer Extract</h4>
                        <p>Shows all classes and courses assigned to a specific lecturer</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-users"></i> Class Extract</h4>
                        <p>Displays the complete schedule for a specific class</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-door-open"></i> Room Extract</h4>
                        <p>Shows all classes scheduled in a specific room</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-calendar-week"></i> Weekly View</h4>
                        <p>Provides a weekly overview of schedules</p>
                    </div>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Extract Tips</h4>
                    <p>Use extracts to provide lecturers with their teaching schedules, students with their class schedules, and administrators with room utilization reports.</p>
                </div>
            </div>

            <!-- Export Formats -->
            <div class="section">
                <h2><i class="fas fa-file-export"></i> Export Formats</h2>
                
                <h3>Available Export Formats</h3>
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-file-pdf"></i> PDF Export</h4>
                        <p>Generate professional PDF documents for printing and sharing</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-file-excel"></i> Excel Export</h4>
                        <p>Export data to Excel format for analysis and manipulation</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-file-csv"></i> CSV Export</h4>
                        <p>Export data in CSV format for database imports</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-file-alt"></i> Text Export</h4>
                        <p>Export data in plain text format for simple viewing</p>
                    </div>
                </div>

                <h3>How to Export Data</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Select Data to Export:</strong> Choose the data you want to export (timetable, lecturer list, course list, etc.)</li>
                        <li><strong>Choose Export Format:</strong> Select PDF, Excel, CSV, or text format</li>
                        <li><strong>Configure Export Options:</strong> Set date ranges, filters, and formatting options</li>
                        <li><strong>Preview Export:</strong> Review the data that will be exported</li>
                        <li><strong>Generate Export:</strong> Click "Export" to create the file</li>
                        <li><strong>Download File:</strong> Save the exported file to your computer</li>
                    </ol>
                </div>
            </div>

            <!-- PDF Generation -->
            <div class="section">
                <h2><i class="fas fa-file-pdf"></i> PDF Generation</h2>
                
                <h3>Purpose</h3>
                <p>PDF generation creates professional, printable documents suitable for official use, distribution to staff and students, and archival purposes.</p>

                <h3>PDF Features</h3>
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-university"></i> Institutional Branding</h4>
                        <p>Includes institutional logos and headers</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-calendar-alt"></i> Calendar Layout</h4>
                        <p>Professional calendar-style timetable layout</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-print"></i> Print Ready</h4>
                        <p>Optimized for printing on standard paper sizes</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-search"></i> Searchable Text</h4>
                        <p>Text is searchable and selectable in the PDF</p>
                    </div>
                </div>

                <h3>How to Generate PDF Timetables</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Access PDF Export:</strong> Use the PDF export option in Extract Timetable or Saved Timetables</li>
                        <li><strong>Configure Layout:</strong> Choose between weekly, daily, or monthly views</li>
                        <li><strong>Set Formatting:</strong> Configure fonts, colors, and layout options</li>
                        <li><strong>Include Metadata:</strong> Add institutional information, dates, and other details</li>
                        <li><strong>Generate PDF:</strong> Click "Generate PDF" to create the document</li>
                        <li><strong>Download File:</strong> Save the PDF file to your computer</li>
                        <li><strong>Print or Share:</strong> Use the PDF for printing or digital distribution</li>
                    </ol>
                </div>
            </div>

            <!-- Data Export -->
            <div class="section">
                <h2><i class="fas fa-database"></i> Data Export</h2>
                
                <h3>Purpose</h3>
                <p>Data export allows you to extract raw data from the system for analysis, reporting, integration with other systems, or backup purposes.</p>

                <h3>Exportable Data Types</h3>
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-building"></i> Department Data</h4>
                        <p>Export department information and configurations</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-chalkboard-teacher"></i> Lecturer Data</h4>
                        <p>Export lecturer information and assignments</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-book"></i> Course Data</h4>
                        <p>Export course catalog and requirements</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-users"></i> Class Data</h4>
                        <p>Export class information and enrollments</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-door-open"></i> Room Data</h4>
                        <p>Export room information and availability</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-calendar-alt"></i> Timetable Data</h4>
                        <p>Export complete timetable information</p>
                    </div>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Export Strategy</h4>
                    <p>Regular data exports serve as backups and enable integration with other systems. Consider scheduling automatic exports for critical data.</p>
                </div>
            </div>

            <!-- Reporting Features -->
            <div class="section">
                <h2><i class="fas fa-chart-bar"></i> Reporting Features</h2>
                
                <h3>Available Reports</h3>
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-chart-line"></i> Utilization Reports</h4>
                        <p>Room and lecturer utilization statistics</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-exclamation-triangle"></i> Conflict Reports</h4>
                        <p>Detailed conflict analysis and resolution</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-calendar-check"></i> Schedule Reports</h4>
                        <p>Comprehensive schedule summaries</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-users-cog"></i> Assignment Reports</h4>
                        <p>Lecturer and course assignment summaries</p>
                    </div>
                </div>

                <h3>How to Generate Reports</h3>
                <div class="step-list">
                    <ol>
                        <li><strong>Select Report Type:</strong> Choose the type of report you need</li>
                        <li><strong>Set Parameters:</strong> Configure date ranges, filters, and criteria</li>
                        <li><strong>Choose Format:</strong> Select PDF, Excel, or other export format</li>
                        <li><strong>Generate Report:</strong> Click "Generate Report" to create the document</li>
                        <li><strong>Review Results:</strong> Check the generated report for accuracy</li>
                        <li><strong>Export or Print:</strong> Save or print the report as needed</li>
                    </ol>
                </div>
            </div>

            <!-- Best Practices -->
            <div class="section">
                <h2><i class="fas fa-star"></i> Best Practices</h2>
                
                <div class="step-list">
                    <ol>
                        <li><strong>Regular Exports:</strong> Schedule regular data exports for backup purposes</li>
                        <li><strong>Format Selection:</strong> Choose appropriate formats for different use cases</li>
                        <li><strong>Data Validation:</strong> Always review exported data for accuracy</li>
                        <li><strong>File Organization:</strong> Organize exported files with clear naming conventions</li>
                        <li><strong>Access Control:</strong> Ensure exported data is handled securely</li>
                        <li><strong>Version Control:</strong> Keep track of different export versions</li>
                        <li><strong>Documentation:</strong> Document export procedures for other users</li>
                    </ol>
                </div>

                <div class="success-box">
                    <h4><i class="fas fa-check-circle"></i> Export Success</h4>
                    <p>Effective use of extract and export features enhances the system's utility and provides valuable data for decision-making and reporting.</p>
                </div>
            </div>

            <!-- Troubleshooting -->
            <div class="section">
                <h2><i class="fas fa-wrench"></i> Troubleshooting</h2>
                
                <h3>Common Issues</h3>
                <div class="feature-grid">
                    <div class="feature-card">
                        <h4><i class="fas fa-download"></i> Export Failures</h4>
                        <p>Check that you have proper permissions and that the data exists. Verify export parameters are correct.</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-file"></i> Format Issues</h4>
                        <p>Ensure you're using compatible software to open exported files. Try different export formats if needed.</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-search"></i> Missing Data</h4>
                        <p>Verify that the data you're trying to export exists and is accessible for the selected stream.</p>
                    </div>
                    <div class="feature-card">
                        <h4><i class="fas fa-clock"></i> Performance Issues</h4>
                        <p>Large exports may take time. Consider breaking large exports into smaller chunks.</p>
                    </div>
                </div>
            </div>

            <!-- Conclusion -->
            <div class="section">
                <h2><i class="fas fa-graduation-cap"></i> Tutorial Completion</h2>
                <p>Congratulations! You have completed the comprehensive tutorial series for the AAMUSTED Timetable Generator. You now have the knowledge and skills to effectively use all aspects of the system.</p>
                
                <div class="success-box">
                    <h4><i class="fas fa-trophy"></i> What You've Learned</h4>
                    <p>You've mastered dashboard navigation, data management, assignment creation, timetable generation, stream management, and data extraction. You're now ready to efficiently manage your institution's scheduling needs.</p>
                </div>

                <div class="tip-box">
                    <h4><i class="fas fa-lightbulb"></i> Next Steps</h4>
                    <p>Start with a small dataset to practice the workflow, then gradually expand to your full institution. Remember to save your work regularly and maintain backups of important data.</p>
                </div>
            </div>

            <!-- Navigation -->
            <div class="navigation-buttons">
                <a href="tutorial_stream_management.php" class="nav-btn btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Previous: Stream Management
                </a>
                <a href="tutorial.php" class="nav-btn btn btn-primary">
                    Back to Tutorials <i class="fas fa-home"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>