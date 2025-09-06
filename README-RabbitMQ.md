RabbitMQ setup and worker instructions

1. Install RabbitMQ and ensure it's running on the host.

2. PHP AMQP extension:
   - Install ext-amqp (PECL): `pecl install amqp` and enable in php.ini
   - Alternatively, use `php-amqplib/php-amqplib` (composer) and adapt worker/publisher code.

3. Start worker:
   - php workers/generate_timetable_worker.php

4. Enqueue a job (example):
   POST /api/enqueue_generation.php with JSON body:
   {
     "stream_id": 1,
     "academic_year": "2024/2025",
     "semester": 1,
     "options": { "population_size": 200, "generations": 100 }
   }

Notes:
- Worker currently requires ext-amqp. If unavailable, enqueue API will still create a job row and return the job id; run the worker in polling mode or install the extension.



SELECT c.id, c.name, t.division_label, COUNT() AS cnt
FROM timetable t
JOIN class_courses cc ON t.class_course_id = cc.id
JOIN classes c ON cc.class_id = c.id
WHERE c.stream_id = 3
GROUP BY c.id, t.division_label
ORDER BY c.name, t.division_label;