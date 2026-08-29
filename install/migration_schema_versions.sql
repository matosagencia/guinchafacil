CREATE TABLE IF NOT EXISTS schema_migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    version VARCHAR(120) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_by VARCHAR(120) NULL,
    execution_ms INT UNSIGNED NULL,
    success TINYINT(1) NOT NULL DEFAULT 1,
    error_message TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_schema_migrations_version (version),
    UNIQUE KEY uk_schema_migrations_filename (filename),
    KEY idx_schema_migrations_applied_at (applied_at),
    KEY idx_schema_migrations_success (success)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
