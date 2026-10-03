CREATE TABLE IF NOT EXISTS test_runs (
    run_id INT AUTO_INCREMENT PRIMARY KEY,
    scenario_key VARCHAR(100) NOT NULL,
    status ENUM('pending', 'running', 'ok', 'fail') NOT NULL DEFAULT 'pending',
    started_at DATETIME NOT NULL,
    finished_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_run_steps (
    step_id INT AUTO_INCREMENT PRIMARY KEY,
    run_id INT NOT NULL,
    step_key VARCHAR(100) NOT NULL,
    node VARCHAR(100) NOT NULL,
    status ENUM('pending', 'running', 'ok', 'fail') NOT NULL DEFAULT 'pending',
    started_at DATETIME NOT NULL,
    duration_ms INT DEFAULT 0,
    detail TEXT DEFAULT NULL,
    FOREIGN KEY (run_id) REFERENCES test_runs(run_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;