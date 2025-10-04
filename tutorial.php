<?php
$pageTitle = 'Tutorial & User Guide';
include 'connect.php';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
.tutorial-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.tutorial-hero {
    background: linear-gradient(135deg, var(--primary-color), var(--hover-color));
    color: white;
    padding: 60px 40px;
    border-radius: 16px;
    text-align: center;
    margin-bottom: 40px;
    box-shadow: 0 20px 40px rgba(128, 0, 32, 0.3);
}

.tutorial-hero h1 {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 20px;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
}

.tutorial-hero p {
    font-size: 1.2rem;
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

.tutorial-nav {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.tutorial-card {
    background: white;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;
}

.tutorial-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--brand-blue));
}

.tutorial-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
    border-color: var(--primary-color);
}

.tutorial-card h3 {
    color: var(--primary-color);
    font-size: 1.4rem;
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
}

.tutorial-card h3 i {
    margin-right: 12px;
    font-size: 1.6rem;
}

.tutorial-card p {
    color: #666;
    line-height: 1.6;
    margin-bottom: 20px;
}

.tutorial-card .btn {
    width: 100%;
    padding: 12px;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.tutorial-card .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.2);
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 40px 0;
}

.feature-item {
    background: white;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.feature-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.feature-item i {
    font-size: 2.5rem;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.feature-item h4 {
    color: var(--primary-color);
    font-weight: 600;
    margin-bottom: 10px;
}

.feature-item p {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.5;
}

.quick-start {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 40px;
    border-radius: 16px;
    margin: 40px 0;
}

.quick-start h2 {
    color: var(--primary-color);
    text-align: center;
    margin-bottom: 30px;
    font-size: 2rem;
}

.steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.step {
    background: white;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    position: relative;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.step-number {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--primary-color);
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
}

.step h4 {
    color: var(--primary-color);
    margin: 15px 0 10px;
    font-weight: 600;
}

.step p {
    color: #666;
    font-size: 0.9rem;
    line-height: 1.5;
}

.support-section {
    background: white;
    padding: 40px;
    border-radius: 16px;
    text-align: center;
    margin-top: 40px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.support-section h2 {
    color: var(--primary-color);
    margin-bottom: 20px;
}

.support-section p {
    color: #666;
    margin-bottom: 30px;
    font-size: 1.1rem;
}

.support-buttons {
    display: flex;
    gap: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.support-buttons .btn {
    padding: 12px 24px;
    font-weight: 600;
    border-radius: 8px;
    min-width: 150px;
}

@media (max-width: 768px) {
    .tutorial-hero {
        padding: 40px 20px;
    }
    
    .tutorial-hero h1 {
        font-size: 2rem;
    }
    
    .tutorial-nav {
        grid-template-columns: 1fr;
    }
    
    .features-grid {
        grid-template-columns: 1fr;
    }
    
    .steps {
        grid-template-columns: 1fr;
    }
    
    .support-buttons {
        flex-direction: column;
        align-items: center;
    }
}
</style>

<div class="main-content">
    <div class="tutorial-container">
        <!-- Hero Section -->
        <div class="tutorial-hero">
            <h1><i class="fas fa-graduation-cap"></i> Tutorial & User Guide</h1>
            <p>Master the AAMUSTED Timetable Generator with our comprehensive step-by-step tutorials. Learn how to navigate every feature and maximize your productivity.</p>
        </div>

        <!-- Quick Start Guide -->
        <div class="quick-start">
            <h2><i class="fas fa-rocket"></i> Quick Start Guide</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h4>Select Stream</h4>
                    <p>Choose your academic stream from the dropdown in the header or visit Stream Management</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h4>Setup Data</h4>
                    <p>Add departments, lecturers, courses, and rooms through the Data Management section</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h4>Create Assignments</h4>
                    <p>Assign lecturers to courses and courses to classes in Assignment Management</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h4>Generate Timetable</h4>
                    <p>Use the Generate Timetable feature to create optimized schedules automatically</p>
                </div>
            </div>
        </div>

        <!-- Tutorial Navigation -->
        <div class="tutorial-nav">
            <div class="tutorial-card">
                <h3><i class="fas fa-home"></i> Dashboard Overview</h3>
                <p>Learn how to navigate the main dashboard, understand the metrics, and use the quick access buttons to different modules.</p>
                <a href="tutorial_dashboard.php" class="btn btn-primary">Start Tutorial</a>
            </div>

            <div class="tutorial-card">
                <h3><i class="fas fa-database"></i> Data Management</h3>
                <p>Master the core data management modules: Departments, Programs, Lecturers, Courses, Levels, Classes, Rooms, and Room Types.</p>
                <a href="tutorial_data_management.php" class="btn btn-primary">Start Tutorial</a>
            </div>

            <div class="tutorial-card">
                <h3><i class="fas fa-calendar-alt"></i> Timetable Management</h3>
                <p>Learn how to generate timetables, manage time slots, view saved timetables, and handle lecturer conflicts.</p>
                <a href="tutorial_timetable_management.php" class="btn btn-primary">Start Tutorial</a>
            </div>

            <div class="tutorial-card">
                <h3><i class="fas fa-tasks"></i> Assignment Management</h3>
                <p>Understand how to assign lecturers to courses, courses to classes, and configure course room type requirements.</p>
                <a href="tutorial_assignment_management.php" class="btn btn-primary">Start Tutorial</a>
            </div>

            <div class="tutorial-card">
                <h3><i class="fas fa-cogs"></i> Stream Management</h3>
                <p>Learn how to create and manage different academic streams, configure time slots, and switch between streams.</p>
                <a href="tutorial_stream_management.php" class="btn btn-primary">Start Tutorial</a>
            </div>

            <div class="tutorial-card">
                <h3><i class="fas fa-download"></i> Extract & Export</h3>
                <p>Discover how to extract timetables for individual users, export data, and generate reports.</p>
                <a href="tutorial_extract_export.php" class="btn btn-primary">Start Tutorial</a>
            </div>
        </div>

        <!-- Key Features -->
        <div class="features-grid">
            <div class="feature-item">
                <i class="fas fa-magic"></i>
                <h4>Smart Generation</h4>
                <p>AI-powered timetable generation using genetic algorithms for optimal scheduling</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-shield-alt"></i>
                <h4>Conflict Detection</h4>
                <p>Automatic detection and resolution of lecturer and room conflicts</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-users"></i>
                <h4>Multi-Stream Support</h4>
                <p>Manage multiple academic streams with separate configurations</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-mobile-alt"></i>
                <h4>Responsive Design</h4>
                <p>Access the system from any device with our mobile-friendly interface</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-chart-line"></i>
                <h4>Real-time Analytics</h4>
                <p>Live dashboard with metrics and statistics for better decision making</p>
            </div>
            <div class="feature-item">
                <i class="fas fa-save"></i>
                <h4>Version Control</h4>
                <p>Save multiple timetable versions and manage different academic periods</p>
            </div>
        </div>

        <!-- Support Section -->
        <div class="support-section">
            <h2><i class="fas fa-life-ring"></i> Need Help?</h2>
            <p>If you need additional assistance or have questions not covered in these tutorials, we're here to help!</p>
            <div class="support-buttons">
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
                <a href="streams.php" class="btn btn-outline-primary">
                    <i class="fas fa-cogs"></i> Stream Management
                </a>
                <button class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Guide
                </button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>