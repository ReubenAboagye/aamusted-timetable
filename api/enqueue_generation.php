<?php
// Enqueue timetable generation job (publishes to RabbitMQ and inserts job row)
require_once __DIR__ . '/../connect.php';

header('Content-Type: application/json');

try {
    $payload = json_decode(file_get_contents('php://input'), true);
    $stream_id = isset($payload['stream_id']) ? intval($payload['stream_id']) : null;
    $academic_year = $payload['academic_year'] ?? null;
    $semester = isset($payload['semester']) ? intval($payload['semester']) : null;
    $options = $payload['options'] ?? null;

    // Insert job row
    $stmt = $conn->prepare("INSERT INTO jobs (job_type, stream_id, academic_year, semester, options, status, progress) VALUES (?, ?, ?, ?, ?, 'queued', 0)");
    $job_type = 'generate_timetable';
    $options_json = $options ? json_encode($options) : null;
    $stmt->bind_param('sisis', $job_type, $stream_id, $academic_year, $semester, $options_json);
    if (!$stmt->execute()) {
        throw new Exception('Failed to create job: ' . $stmt->error);
    }

    $jobId = $stmt->insert_id;
    $stmt->close();

    // Publish to RabbitMQ
    // Requires ext-amqp or php-amqplib; we'll try ext-amqp if available, otherwise return job id and let manual worker poll DB
    if (extension_loaded('amqp')) {
        $exchange = 'timetable_jobs';
        $queue = 'generate_timetable';

        $conn_args = [
            'host' => '127.0.0.1',
            'port' => 5672,
            'login' => 'guest',
            'password' => 'guest',
            'vhost' => '/'
        ];

        $amqpConn = new AMQPConnection($conn_args);
        if (!$amqpConn->connect()) {
            // fall back to DB-only enqueue
            echo json_encode(['success' => true, 'job_id' => $jobId, 'message' => 'Job created but RabbitMQ unavailable']);
            exit;
        }

        $ch = new AMQPChannel($amqpConn);
        $ex = new AMQPExchange($ch);
        $ex->setName($exchange);
        $ex->setType(AMQP_EX_TYPE_DIRECT);
        $ex->declareExchange();

        $message = json_encode(['job_id' => $jobId]);
        $ex->publish($message, $queue);

        $amqpConn->disconnect();
    }

    echo json_encode(['success' => true, 'job_id' => $jobId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>


