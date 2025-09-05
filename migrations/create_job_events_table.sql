-- Create job_events table to track restart events during generation
CREATE TABLE IF NOT EXISTS job_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_id (job_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);
