<?php
/**
 * Enhanced Timetable Generation using Genetic Algorithm
 * 
 * This script uses the complete genetic algorithm implementation to generate
 * optimal timetables while respecting all constraints and preferences.
 */

include 'connect.php';

// Ensure flash helper is available for PRG redirects
if (file_exists(__DIR__ . '/includes/flash.php')) include_once __DIR__ . '/includes/flash.php';

// Include stream manager
if (file_exists(__DIR__ . '/includes/stream_manager.php')) include_once __DIR__ . '/includes/stream_manager.php';

// Include genetic algorithm components
require_once __DIR__ . '/ga/GeneticAlgorithm.php';
require_once __DIR__ . '/ga/DBLoader.php';
require_once __DIR__ . '/ga/TimetableRepresentation.php';
require_once __DIR__ . '/ga/ConstraintChecker.php';
require_once __DIR__ . '/ga/FitnessEvaluator.php';

$streamManager = getStreamManager();
$current_stream_id = $streamManager->getCurrentStreamId();

$success_message = '';
$error_message = '';
$generation_results = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    
    if ($action === 'generate_lecture_timetable') {
        // Read parameters
        $academic_year = trim($_POST['academic_year'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $stream_id = $_POST['stream_id'] ?? $current_stream_id;
        
        // GA parameters
        $population_size = (int)($_POST['population_size'] ?? 100);
        $generations = (int)($_POST['generations'] ?? 500);
        $mutation_rate = (float)($_POST['mutation_rate'] ?? 0.1);
        $crossover_rate = (float)($_POST['crossover_rate'] ?? 0.8);
        $max_runtime = (int)($_POST['max_runtime'] ?? 300);
        
        // Validate inputs
        if ($academic_year === '' || $semester === '') {
            $error_message = 'Please specify academic year and semester before generating the timetable.';
        } else {
            try {
                // Normalize academic year
                $academic_year = normalizeAcademicYear($academic_year);
                
                // Clear existing timetable for this period
                clearExistingTimetable($conn, $academic_year, $semester, $stream_id);
                
                // Initialize genetic algorithm
                $gaOptions = [
                    'population_size' => $population_size,
                    'generations' => $generations,
                    'mutation_rate' => $mutation_rate,
                    'crossover_rate' => $crossover_rate,
                    'max_runtime' => $max_runtime,
                    'stream_id' => $stream_id,
                    'academic_year' => $academic_year,
                    'semester' => $semester
                ];
                
                $ga = new GeneticAlgorithm($conn, $gaOptions);
                
                // Validate data before generation
                $loader = new DBLoader($conn);
                $data = $loader->loadAll($gaOptions);
                $validation = $loader->validateDataForGeneration($data);
                
                if (!$validation['valid']) {
                    $error_message = 'Cannot generate timetable: ' . implode(', ', $validation['errors']);
                } else {
                    // Run genetic algorithm
                    $start_time = microtime(true);
                    $results = $ga->run();
                    $end_time = microtime(true);
                    
                    $generation_results = [
                        'runtime' => $end_time - $start_time,
                        'generations_completed' => $results['generations_completed'],
                        'best_solution' => $results['solution'],
                        'statistics' => $results['statistics']
                    ];
                    
                    // Convert solution to database format
                    $timetableEntries = $ga->convertToDatabaseFormat($results['solution']);
                    
                    // Insert into database
                    $inserted_count = insertTimetableEntries($conn, $timetableEntries);
                    
                    if ($inserted_count > 0) {
                        $success_message = "Timetable generated successfully using Genetic Algorithm! $inserted_count entries created.";
                        $success_message .= " Runtime: " . round($generation_results['runtime'], 2) . " seconds";
                        $success_message .= ", Generations: " . $generation_results['generations_completed'];
                        
                        if (function_exists('redirect_with_flash')) {
                            redirect_with_flash('generate_timetable_ga.php', 'success', $success_message);
                        }
                    } else {
                        $error_message = "Genetic algorithm completed but no timetable entries were inserted.";
                    }
                }
                
            } catch (Exception $e) {
                $error_message = 'Timetable generation failed: ' . $e->getMessage();
            }
        }
    }
}

// Helper functions
function normalizeAcademicYear($input) {
    $s = trim((string)$input);
    if ($s === '') return $s;
    
    // Prefer explicit 4-digit/4-digit formats like 2024/2025
    if (preg_match('/(\d{4}\/\d{4})/', $s, $m)) {
        return $m[1];
    }
    
    // Accept 4-digit-4-digit variants and normalize to slash
    if (preg_match('/(\d{4}[-]\d{4})/', $s, $m)) {
        return str_replace('-', '/', $m[1]);
    }
    
    // Accept shortened second year like 2024/25 -> expand if possible
    if (preg_match('/(\d{4}\/\d{2})/', $s, $m)) {
        $parts = explode('/', $m[1]);
        $start = $parts[0];
        $end = $parts[1];
        if (strlen($end) === 2) {
            $century = substr($start, 0, 2);
            $end = $century . $end;
        }
        return $start . '/' . $end;
    }
    
    // Fallback: take the first whitespace-separated token and truncate to 9 chars
    $parts = preg_split('/\s+/', $s);
    $tok = $parts[0] ?? $s;
    if (strlen($tok) > 9) $tok = substr($tok, 0, 9);
    return $tok;
}

function clearExistingTimetable($conn, $academic_year, $semester, $stream_id) {
    // Check if timetable table has the new schema
    $has_class_course = $conn->query("SHOW COLUMNS FROM timetable LIKE 'class_course_id'")->num_rows > 0;
    
    if ($has_class_course) {
        // New schema: clear via class_courses -> classes -> stream
        $sql = "DELETE t FROM timetable t 
                JOIN class_courses cc ON t.class_course_id = cc.id 
                JOIN classes c ON cc.class_id = c.id 
                WHERE t.academic_year = ? AND t.semester = ? AND c.stream_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $academic_year, $semester, $stream_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Old schema: clear via class_id -> classes -> stream
        $sql = "DELETE t FROM timetable t 
                JOIN classes c ON t.class_id = c.id 
                WHERE t.academic_year = ? AND t.semester = ? AND c.stream_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $academic_year, $semester, $stream_id);
        $stmt->execute();
        $stmt->close();
    }
}

function insertTimetableEntries($conn, $entries) {
    if (empty($entries)) {
        return 0;
    }
    
    $inserted_count = 0;
    
    foreach ($entries as $entry) {
        $sql = "INSERT INTO timetable (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, division_label, semester, academic_year) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iiiiisss", 
                $entry['class_course_id'],
                $entry['lecturer_course_id'],
                $entry['day_id'],
                $entry['time_slot_id'],
                $entry['room_id'],
                $entry['division_label'],
                $entry['semester'],
                $entry['academic_year']
            );
            
            if ($stmt->execute()) {
                $inserted_count++;
            }
            $stmt->close();
        }
    }
    
    return $inserted_count;
}

// Get statistics
$total_assignments = 0;
$total_timetable_entries = 0;
$total_classes = 0;
$total_courses = 0;

if ($current_stream_id) {
    // Count assignments for current stream
    $sql = "SELECT COUNT(*) as count FROM class_courses cc 
            JOIN classes c ON cc.class_id = c.id 
            WHERE cc.is_active = 1 AND c.stream_id = " . intval($current_stream_id);
    $total_assignments = $conn->query($sql)->fetch_assoc()['count'];
    
    // Count timetable entries for current stream
    $sql = "SELECT COUNT(*) as count FROM timetable t 
            JOIN class_courses cc ON t.class_course_id = cc.id 
            JOIN classes c ON cc.class_id = c.id 
            WHERE c.stream_id = " . intval($current_stream_id);
    $total_timetable_entries = $conn->query($sql)->fetch_assoc()['count'];
    
    // Count classes for current stream
    $total_classes = $conn->query("SELECT COUNT(*) as count FROM classes WHERE is_active = 1 AND stream_id = " . intval($current_stream_id))->fetch_assoc()['count'];
} else {
    $total_assignments = $conn->query("SELECT COUNT(*) as count FROM class_courses WHERE is_active = 1")->fetch_assoc()['count'];
    $total_timetable_entries = $conn->query("SELECT COUNT(*) as count FROM timetable")->fetch_assoc()['count'];
    $total_classes = $conn->query("SELECT COUNT(*) as count FROM classes WHERE is_active = 1")->fetch_assoc()['count'];
}

$total_courses = $conn->query("SELECT COUNT(*) as count FROM courses WHERE is_active = 1")->fetch_assoc()['count'];

// Get available streams
$streams = $conn->query("SELECT id, name, code FROM streams WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Set page title
$pageTitle = 'Generate Timetable (Genetic Algorithm)';
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content" id="mainContent">
    <div class="table-container">
        <div class="table-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-dna me-2"></i>Generate Timetable using Genetic Algorithm</h4>
        </div>

        <div class="m-3">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="row m-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Genetic Algorithm Timetable Generation</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>About Genetic Algorithm:</h6>
                            <ul class="mb-0">
                                <li>Uses evolutionary computation to find optimal timetable solutions</li>
                                <li>Respects all hard constraints (no conflicts)</li>
                                <li>Optimizes soft constraints (preferences)</li>
                                <li>Automatically handles complex scheduling problems</li>
                                <li>Provides detailed fitness analysis and statistics</li>
                            </ul>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="action" value="generate_lecture_timetable">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="academic_year" class="form-label">Academic Year</label>
                                        <input type="text" class="form-control" id="academic_year" name="academic_year" 
                                               placeholder="e.g., 2024/2025" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="semester" class="form-label">Semester</label>
                                        <select class="form-select" id="semester" name="semester" required>
                                            <option value="">Select Semester</option>
                                            <option value="first">First Semester</option>
                                            <option value="second">Second Semester</option>
                                            <option value="summer">Summer Semester</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="stream_id" class="form-label">Stream</label>
                                <select class="form-select" id="stream_id" name="stream_id">
                                    <option value="">All Streams</option>
                                    <?php foreach ($streams as $stream): ?>
                                        <option value="<?php echo $stream['id']; ?>" 
                                                <?php echo $stream['id'] == $current_stream_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($stream['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="population_size" class="form-label">Population Size</label>
                                        <input type="number" class="form-control" id="population_size" name="population_size" 
                                               value="100" min="50" max="500">
                                        <div class="form-text">Number of solutions in each generation</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="generations" class="form-label">Maximum Generations</label>
                                        <input type="number" class="form-control" id="generations" name="generations" 
                                               value="500" min="100" max="2000">
                                        <div class="form-text">Maximum number of generations to run</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="mutation_rate" class="form-label">Mutation Rate</label>
                                        <input type="number" class="form-control" id="mutation_rate" name="mutation_rate" 
                                               value="0.1" min="0.01" max="0.5" step="0.01">
                                        <div class="form-text">Probability of gene mutation (0.01-0.5)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="crossover_rate" class="form-label">Crossover Rate</label>
                                        <input type="number" class="form-control" id="crossover_rate" name="crossover_rate" 
                                               value="0.8" min="0.1" max="1.0" step="0.1">
                                        <div class="form-text">Probability of gene crossover (0.1-1.0)</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="max_runtime" class="form-label">Maximum Runtime (seconds)</label>
                                <input type="number" class="form-control" id="max_runtime" name="max_runtime" 
                                       value="300" min="60" max="1800">
                                <div class="form-text">Maximum time to run the algorithm (60-1800 seconds)</div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-dna me-2"></i>Generate Timetable with GA
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">System Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Total Assignments:</strong>
                            <span class="badge bg-primary"><?php echo $total_assignments; ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Timetable Entries:</strong>
                            <span class="badge bg-success"><?php echo $total_timetable_entries; ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Classes:</strong>
                            <span class="badge bg-info"><?php echo $total_classes; ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Courses:</strong>
                            <span class="badge bg-warning"><?php echo $total_courses; ?></span>
                        </div>
                        
                        <div class="mt-3">
                            <a href="generate_timetable.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-random me-2"></i>Use Simple Generator
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($generation_results): ?>
        <div class="row m-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Generation Results</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Performance Metrics</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Runtime:</strong> <?php echo round($generation_results['runtime'], 2); ?> seconds</li>
                                    <li><strong>Generations:</strong> <?php echo $generation_results['generations_completed']; ?></li>
                                    <li><strong>Best Fitness:</strong> <?php echo round($generation_results['best_solution']['fitness']['total_score'], 2); ?></li>
                                    <li><strong>Hard Violations:</strong> <?php echo array_sum(array_map('count', $generation_results['best_solution']['fitness']['hard_violations'])); ?></li>
                                    <li><strong>Soft Violations:</strong> <?php echo array_sum(array_map('count', $generation_results['best_solution']['fitness']['soft_violations'])); ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Solution Quality</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Feasible:</strong> 
                                        <span class="badge <?php echo $generation_results['best_solution']['fitness']['is_feasible'] ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo $generation_results['best_solution']['fitness']['is_feasible'] ? 'Yes' : 'No'; ?>
                                        </span>
                                    </li>
                                    <li><strong>Quality Rating:</strong> 
                                        <?php 
                                        $fitnessEvaluator = new FitnessEvaluator([]);
                                        $qualityRating = $fitnessEvaluator->getQualityRating($generation_results['best_solution']['individual']);
                                        $qualityClass = $qualityRating === 'Excellent' ? 'success' : 
                                                      ($qualityRating === 'Good' ? 'info' : 
                                                      ($qualityRating === 'Fair' ? 'warning' : 'danger'));
                                        ?>
                                        <span class="badge bg-<?php echo $qualityClass; ?>"><?php echo $qualityRating; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php 
$conn->close();
include 'includes/footer.php'; 
?>
