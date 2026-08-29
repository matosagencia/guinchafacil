CREATE TABLE IF NOT EXISTS `payment_jobs` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pedido_id` INT NOT NULL,
    `pagamento_id` INT NOT NULL,
    `job_type` VARCHAR(32) NOT NULL,
    `idempotency_key` VARCHAR(120) NOT NULL,
    `status` VARCHAR(24) NOT NULL DEFAULT 'queued',
    `attempt_count` INT NOT NULL DEFAULT 0,
    `max_attempts` INT NOT NULL DEFAULT 5,
    `worker_id` VARCHAR(120) NULL,
    `locked_at` DATETIME NULL,
    `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_error` TEXT NULL,
    `payload_json` LONGTEXT NULL,
    `finished_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_payment_jobs_idempotency` (`idempotency_key`),
    KEY `idx_payment_jobs_status_available` (`status`, `available_at`),
    KEY `idx_payment_jobs_pedido` (`pedido_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_job_attempts` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `payment_job_id` BIGINT UNSIGNED NOT NULL,
    `attempt_number` INT NOT NULL,
    `worker_id` VARCHAR(120) NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `response_json` LONGTEXT NULL,
    `error_message` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_payment_job_attempts_job` (`payment_job_id`),
    CONSTRAINT `fk_payment_job_attempts_job` FOREIGN KEY (`payment_job_id`) REFERENCES `payment_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
