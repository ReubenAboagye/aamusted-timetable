<?php
// Worker: consume job messages from RabbitMQ and run timetable generation
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../ga/GeneticAlgorithm.php';

// Basic config - adjust as needed or load from env
$rabbitHost = '127.0.0.1';
$rabbitPort = 5672;
$rabbitUser = 'guest';
$rabbitPass = 'guest';
$exchange = 'timetable_jobs';
$queueName = 'generate_timetable';

// Try using ext-amqp
if (!extension_loaded('amqp')) {
    fwrite(STDERR, "ext-amqp not available. Worker cannot connect to RabbitMQ.\n");
    exit(1);
}

$conn_args = [
    'host' => $rabbitHost,
    'port' => $rabbitPort,
    'login' => $rabbitUser,
    'password' => $rabbitPass,
    'vhost' => '/'
];

$amqpConn = new AMQPConnection($conn_args);
if (!$amqpConn->connect()) {
    fwrite(STDERR, "Failed to connect to RabbitMQ\n");
    exit(1);
}

$ch = new AMQPChannel($amqpConn);
$ex = new AMQPExchange($ch);
$ex->setName($exchange);
$ex->setType(AMQP_EX_TYPE_DIRECT);
$ex->declareExchange();

$q = new AMQPQueue($ch);
$q->setName($queueName);
$q->declareQueue();
$q->bind($exchange, $queueName);

echo "Worker started, waiting for jobs...\n";

$q->consume(function($envelope, $queue) use ($conn, $ch) {
    $body = $envelope->getBody();
    $data = json_decode($body, true);
    $jobId = $data['job_id'] ?? null;
    if (!$jobId) {
        $queue->nack($envelope->getDeliveryTag());
        return;
    }

    // Acquire semaphore per stream (default concurrency = 1)
    $streamId = intval($data['stream_id'] ?? 0);
    $concurrencyLimit = 1; // default per user request

    // Try to acquire slot: count running jobs for this stream
    $runningRes = $conn->query("SELECT COUNT(*) AS cnt FROM jobs WHERE stream_id = " . $streamId . " AND status = 'running'");
    $runningRow = $runningRes->fetch_assoc();
    $runningCount = intval($runningRow['cnt'] ?? 0);

    if ($runningCount >= $concurrencyLimit) {
        // Requeue with delay: nack and sleep to allow other jobs to proceed
        // Use a short sleep here to avoid busy loop; alternatively, rely on delayed requeue in RabbitMQ plugin
        $queue->nack($envelope->getDeliveryTag());
        sleep(2);
        return;
    }

    // Mark job running
    $update = $conn->prepare("UPDATE jobs SET status='running', progress=1, updated_at = NOW() WHERE id = ?");
    $update->bind_param('i', $jobId);
    $update->execute();
    $update->close();

    // Load job data
    $res = $conn->query("SELECT * FROM jobs WHERE id = " . intval($jobId));
    $job = $res->fetch_assoc();

    try {
        $options = json_decode($job['options'] ?? '{}', true) ?: [];

        // Instantiate GA and run (use options for population/generation limits)
        $gaOptions = array_merge($options, [
            'stream_id' => $job['stream_id'],
            'semester' => $job['semester'],
            'academic_year' => $job['academic_year']
        ]);

        $ga = new GeneticAlgorithm($conn, $gaOptions);

        // Register progress callback to update job row periodically
        $ga->setProgressCallback(function($progress) use ($conn, $jobId) {
            $percent = intval($progress['percent']);
            $bestFitness = $progress['best_fitness'];
            $hardCount = is_array($progress['hard_violations']) ? array_sum(array_map('count', $progress['hard_violations'])) : 0;
            $softCount = is_array($progress['soft_violations']) ? array_sum(array_map('count', $progress['soft_violations'])) : 0;
            $stmt = $conn->prepare("UPDATE jobs SET progress = ?, result = ? , updated_at = NOW() WHERE id = ?");
            $summary = json_encode(['percent' => $percent, 'best_fitness' => $bestFitness, 'hard_violations' => $hardCount, 'soft_violations' => $softCount]);
            $stmt->bind_param('isi', $percent, $summary, $jobId);
            $stmt->execute();
            $stmt->close();
        });

        $result = $ga->run();

        // Convert solution and save result summary
        $solution = $result['solution'] ?? null;
        $entries = [];
        if ($solution) {
            $entries = $ga->convertToDatabaseFormat($solution);
        }

        $update = $conn->prepare("UPDATE jobs SET status='completed', progress=100, result = ?, updated_at = NOW() WHERE id = ?");
        $result_json = json_encode(['statistics' => $result['statistics'] ?? [], 'runtime' => $result['runtime'] ?? 0, 'entries_count' => count($entries)]);
        $update->bind_param('si', $result_json, $jobId);
        $update->execute();
        $update->close();

        // Optionally: persist timetable entries to DB (not automatically done here)
        echo "Job $jobId completed.\n";
        $queue->ack($envelope->getDeliveryTag());
    } catch (Exception $e) {
        $err = $e->getMessage();
        $update = $conn->prepare("UPDATE jobs SET status='failed', error_message = ?, updated_at = NOW() WHERE id = ?");
        $update->bind_param('si', $err, $jobId);
        $update->execute();
        $update->close();

        fwrite(STDERR, "Job $jobId failed: $err\n");
        $queue->ack($envelope->getDeliveryTag());
    }
});

$amqpConn->disconnect();

?>


