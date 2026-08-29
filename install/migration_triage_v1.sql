-- migration_triage_v1.sql
-- ETAPA 2 do ROADMAP — EXPANSÃO PARA SOCORRO AUTOMOTIVO (Fundamento 3: triagem).
-- Persiste cada sessão de triagem para auditoria/analytics — a decisão em si
-- é tomada por TriageRuleEngine (regras versionadas em código, não em banco).

CREATE TABLE IF NOT EXISTS `triage_sessions` (
    `id`                              INT AUTO_INCREMENT PRIMARY KEY,
    `session_token`                   VARCHAR(64)   NOT NULL,
    `cliente_id`                      INT           NULL,
    `symptom_code`                    VARCHAR(40)   NOT NULL,
    `respostas_json`                  TEXT          NULL,
    `resultado`                       ENUM('RECOMMENDED_SERVICE','ALTERNATIVE_SERVICES','SAFETY_RISK','TOWING_REQUIRED','MANUAL_REVIEW_REQUIRED') NULL,
    `recommended_service_code`        VARCHAR(60)   NULL,
    `alternative_service_codes_json`  TEXT          NULL,
    `safety_risk`                     TINYINT(1)    NOT NULL DEFAULT 0,
    `explicacao`                      VARCHAR(500)  NULL,
    `rule_version`                    VARCHAR(20)   NOT NULL DEFAULT 'v1',
    `created_at`                      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    `completed_at`                    DATETIME      NULL,
    UNIQUE KEY `uk_triage_sessions_token` (`session_token`),
    INDEX `idx_triage_sessions_cliente` (`cliente_id`),
    INDEX `idx_triage_sessions_symptom` (`symptom_code`),
    CONSTRAINT `fk_triage_sessions_cliente` FOREIGN KEY (`cliente_id`)
        REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
