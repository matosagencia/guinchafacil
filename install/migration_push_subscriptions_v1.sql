-- migration_push_subscriptions_v1.sql
-- Base de inscricoes Web Push para guincho e especialista.

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id` BIGINT UNSIGNED NOT NULL,
    `tipo_usuario` VARCHAR(32) NOT NULL,
    `endpoint` TEXT NOT NULL,
    `endpoint_hash` CHAR(64) NOT NULL,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_seen_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_push_subscriptions_endpoint_hash` (`endpoint_hash`),
    KEY `idx_push_subscriptions_usuario_tipo` (`usuario_id`, `tipo_usuario`),
    KEY `idx_push_subscriptions_tipo` (`tipo_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
