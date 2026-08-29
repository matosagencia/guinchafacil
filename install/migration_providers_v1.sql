-- migration_providers_v1.sql
-- ROADMAP socorro automotivo — Etapa 12 (generalização de prestador).
--
-- DECISÃO DE ESTRATÉGIA (confirmada pelo usuário em 22/07/2026, via
-- AskUserQuestion): CAMADA ADITIVA, não rename/refactor. `guinchos`,
-- `pedidos.guincho_id` e o role literal 'guincho' (AuthService::requireAuth)
-- continuam funcionando exatamente como hoje — 50+ arquivos e 25 pontos de
-- checagem de role dependem disso e o fluxo de reboque é o único em
-- produção real. Nada disso é tocado por esta migration.
--
-- O que esta migration cria é uma ponte: cada `guincho` existente ganha um
-- `provider` (1:1, via legacy_guincho_id) e uma `provider_unit` do tipo
-- TOW_TRUCK (1:1, via legacy_guincho_id). Novos tipos de prestador
-- (socorrista autônomo, oficina, empresa híbrida) que NÃO têm registro em
-- `guinchos` poderão existir em `providers` sem legacy_guincho_id — mas
-- ainda não têm código de cadastro/aprovação (isso é a próxima etapa, só
-- depois que esta base estiver validada).
--
-- GAP CONHECIDO E ACEITO NESTA ETAPA: `provider_capabilities`,
-- `provider_equipment` e `provider_vehicle_compatibility` (Etapa 1) seguem
-- com FK em `guinchos.id`, não em `providers.id`. Ou seja: um provider novo
-- sem guincho ainda não consegue declarar capacidades pela tela atual.
-- Documentado aqui de propósito — resolver isso é trabalho futuro
-- (generalizar provider_capabilities.provider_id para referenciar
-- providers.id via a ponte legacy_guincho_id), não desta migration.

-- Nota: `id`/FKs abaixo usam INT (não BIGINT UNSIGNED) de propósito — é o
-- mesmo tipo de `guinchos.id`, `usuarios.id` e `pedidos.id` na base
-- (install/migrate.php), evitando qualquer mismatch signed/unsigned nas
-- constraints de FK e mantendo consistência com o resto do schema.

CREATE TABLE IF NOT EXISTS `providers` (
    `id`                     INT AUTO_INCREMENT PRIMARY KEY,
    `provider_type`           VARCHAR(30) NOT NULL,
    `legal_name`               VARCHAR(180) NOT NULL,
    `trade_name`               VARCHAR(180) NULL,
    `document_type`           VARCHAR(20) NULL,
    `document_number`         VARCHAR(30) NULL,
    `approval_status`         VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    `payment_recipient_type`  VARCHAR(20) NOT NULL DEFAULT 'INDIVIDUAL',
    `pix_key`                  VARCHAR(500) NULL,
    `rating`                    DECIMAL(3,2) NULL,
    `active`                    TINYINT(1) NOT NULL DEFAULT 1,
    `legacy_guincho_id`       INT NULL,
    `created_at`               DATETIME NOT NULL,
    `updated_at`               DATETIME NOT NULL,
    UNIQUE KEY `uk_providers_legacy_guincho` (`legacy_guincho_id`),
    KEY `idx_providers_type` (`provider_type`),
    KEY `idx_providers_approval` (`approval_status`),
    CONSTRAINT `fk_providers_legacy_guincho` FOREIGN KEY (`legacy_guincho_id`)
        REFERENCES `guinchos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `provider_members` (
    `id`                     INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id`             INT NOT NULL,
    `user_id`                  INT NOT NULL,
    `role_code`                VARCHAR(30) NOT NULL DEFAULT 'OWNER_OPERATOR',
    `approval_status`         VARCHAR(20) NOT NULL DEFAULT 'APPROVED',
    `can_accept_orders`       TINYINT(1) NOT NULL DEFAULT 1,
    `can_execute_services`   TINYINT(1) NOT NULL DEFAULT 1,
    `can_manage_inventory`   TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`               DATETIME NOT NULL,
    `updated_at`               DATETIME NOT NULL,
    UNIQUE KEY `uk_provider_member` (`provider_id`, `user_id`),
    KEY `idx_provider_members_user` (`user_id`),
    CONSTRAINT `fk_provider_members_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_provider_members_user` FOREIGN KEY (`user_id`)
        REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `provider_units` (
    `id`                     INT AUTO_INCREMENT PRIMARY KEY,
    `provider_id`             INT NOT NULL,
    `unit_type`                VARCHAR(30) NOT NULL,
    `plate`                     VARCHAR(15) NULL,
    `active`                    TINYINT(1) NOT NULL DEFAULT 1,
    `legacy_guincho_id`       INT NULL,
    `created_at`               DATETIME NOT NULL,
    `updated_at`               DATETIME NOT NULL,
    UNIQUE KEY `uk_provider_unit_legacy_guincho` (`legacy_guincho_id`),
    KEY `idx_provider_units_provider` (`provider_id`),
    KEY `idx_provider_units_type` (`unit_type`),
    CONSTRAINT `fk_provider_units_provider` FOREIGN KEY (`provider_id`)
        REFERENCES `providers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_provider_units_legacy_guincho` FOREIGN KEY (`legacy_guincho_id`)
        REFERENCES `guinchos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: um provider + uma provider_unit por guincho já existente.
-- Idempotente por natureza — se rodar de novo, o INSERT...SELECT abaixo só
-- insere guinchos que ainda não têm provider ponte (LEFT JOIN ... IS NULL).
INSERT INTO `providers`
    (provider_type, legal_name, trade_name, document_type, document_number,
     approval_status, payment_recipient_type, pix_key, rating, active,
     legacy_guincho_id, created_at, updated_at)
SELECT
    'INDIVIDUAL',
    COALESCE(u.nome, CONCAT('Guincho #', g.id)),
    NULL,
    NULL,
    NULL,
    CASE WHEN g.aprovado = 1 THEN 'APPROVED' ELSE 'PENDING' END,
    'INDIVIDUAL',
    g.chave_pix, -- copiado como está (mesmo formato armazenado hoje); nada lê providers.pix_key ainda
    g.reputacao,
    g.disponivel,
    g.id,
    NOW(),
    NOW()
FROM `guinchos` g
JOIN `usuarios` u ON u.id = g.usuario_id
LEFT JOIN `providers` p ON p.legacy_guincho_id = g.id
WHERE p.id IS NULL;

INSERT INTO `provider_units`
    (provider_id, unit_type, plate, active, legacy_guincho_id, created_at, updated_at)
SELECT
    p.id,
    'TOW_TRUCK',
    g.placa_guincho,
    g.disponivel,
    g.id,
    NOW(),
    NOW()
FROM `guinchos` g
JOIN `providers` p ON p.legacy_guincho_id = g.id
LEFT JOIN `provider_units` pu ON pu.legacy_guincho_id = g.id
WHERE pu.id IS NULL;

INSERT INTO `provider_members`
    (provider_id, user_id, role_code, approval_status, can_accept_orders, can_execute_services, can_manage_inventory, created_at, updated_at)
SELECT
    p.id,
    g.usuario_id,
    'OWNER_OPERATOR',
    'APPROVED',
    1, 1, 1,
    NOW(),
    NOW()
FROM `guinchos` g
JOIN `providers` p ON p.legacy_guincho_id = g.id
LEFT JOIN `provider_members` pm ON pm.provider_id = p.id AND pm.user_id = g.usuario_id
WHERE pm.id IS NULL;
