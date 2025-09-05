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


