CREATE TABLE IF NOT EXISTS simulation_artifacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id CHAR(32) NOT NULL,
    step_id BIGINT UNSIGNED NULL,
    kind VARCHAR(32) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    private_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    size_bytes BIGINT UNSIGNED NULL,
    sha256 CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_run_id (run_id),
    KEY idx_step_id (step_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE simulation_runs
    ADD COLUMN engine VARCHAR(32) NOT NULL DEFAULT 'php_internal',
    ADD COLUMN suite VARCHAR(120) NULL,
    ADD COLUMN requested_by INT UNSIGNED NULL,
    ADD COLUMN requested_at DATETIME NULL,
    ADD COLUMN target_environment VARCHAR(64) NULL,
    ADD COLUMN target_url VARCHAR(255) NULL,
    ADD COLUMN browser VARCHAR(32) NULL,
    ADD COLUMN viewport VARCHAR(64) NULL,
    ADD COLUMN locale VARCHAR(16) NULL,
    ADD COLUMN timezone VARCHAR(64) NULL,
    ADD COLUMN worker_id VARCHAR(120) NULL,
    ADD COLUMN worker_pid INT NULL,
    ADD COLUMN heartbeat_at DATETIME NULL,
    ADD COLUMN started_at DATETIME NULL,
    ADD COLUMN finished_at DATETIME NULL,
    ADD COLUMN exit_code INT NULL,
    ADD COLUMN configuration_json LONGTEXT NULL,
    ADD COLUMN summary_json LONGTEXT NULL,
    ADD COLUMN app_version VARCHAR(64) NULL,
    ADD COLUMN git_commit VARCHAR(64) NULL;

ALTER TABLE simulation_steps
    ADD COLUMN system VARCHAR(64) NULL,
    ADD COLUMN class VARCHAR(120) NULL,
    ADD COLUMN function VARCHAR(120) NULL,
    ADD COLUMN file VARCHAR(255) NULL,
    ADD COLUMN phase VARCHAR(120) NULL,
    ADD COLUMN code VARCHAR(64) NULL,
    ADD COLUMN status VARCHAR(24) NULL,
    ADD COLUMN duration_ms INT NULL,
    ADD COLUMN expected_json LONGTEXT NULL,
    ADD COLUMN actual_json LONGTEXT NULL,
    ADD COLUMN error_message LONGTEXT NULL,
    ADD COLUMN stack_trace LONGTEXT NULL,
    ADD COLUMN started_at DATETIME NULL,
    ADD COLUMN finished_at DATETIME NULL;

-- idx_step_id já é declarado na criação da tabela simulation_artifacts acima
-- (índice duplicado removido no Pacote L1.2 — MySQL rejeita "Duplicate key name").
