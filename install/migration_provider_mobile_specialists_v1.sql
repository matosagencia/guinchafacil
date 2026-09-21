-- Migra especialistas legados para a camada generalizada de providers.
-- A tabela especialistas continua preservada nesta etapa para compatibilidade operacional.
SET @db = DATABASE();

SET @sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE providers ADD COLUMN legacy_especialista_id BIGINT NULL',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = @db AND table_name = 'providers' AND column_name = 'legacy_especialista_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE providers ADD UNIQUE KEY uk_providers_legacy_especialista (legacy_especialista_id)',
        'SELECT 1')
    FROM information_schema.statistics
    WHERE table_schema = @db AND table_name = 'providers' AND index_name = 'uk_providers_legacy_especialista'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO providers
    (provider_type, legal_name, trade_name, document_type, document_number,
     approval_status, payment_recipient_type, pix_key, active, legacy_especialista_id,
     created_at, updated_at)
SELECT 'INDIVIDUAL',
       COALESCE(NULLIF(e.nome_profissional, ''), u.nome),
       NULLIF(e.nome_profissional, ''),
       e.documento_tipo,
       e.cpf_cnpj,
       CASE WHEN e.aprovado = 1 THEN 'APPROVED' ELSE 'PENDING' END,
       'INDIVIDUAL',
       e.chave_pix,
       1,
       e.id,
       COALESCE(e.criado_em, NOW()),
       COALESCE(e.atualizado_em, NOW())
  FROM especialistas e
  JOIN usuarios u ON u.id = e.usuario_id
  LEFT JOIN providers p ON p.legacy_especialista_id = e.id
 WHERE p.id IS NULL;

INSERT INTO provider_workshop_settings
    (provider_id, taxa_indicacao_fixa, taxa_resgate_direto, permite_resgate_direto,
     recebe_veiculo_patio, faz_resgate_direto, regra_versao, status_parceria,
     raio_checkin_m, created_at, updated_at)
SELECT p.id, 30.00, 20.00, 1, 0, 1, 'mobile-v1',
       CASE WHEN p.approval_status = 'APPROVED' THEN 'ATIVO' ELSE 'INATIVO' END,
       150, NOW(), NOW()
  FROM providers p
  LEFT JOIN provider_workshop_settings ws ON ws.provider_id = p.id
 WHERE p.provider_type = 'INDIVIDUAL'
   AND p.legacy_especialista_id IS NOT NULL
   AND ws.provider_id IS NULL;

INSERT INTO provider_members
    (provider_id, user_id, role_code, approval_status, can_accept_orders,
     can_execute_services, can_manage_inventory, created_at, updated_at)
SELECT p.id, e.usuario_id, 'OWNER_OPERATOR',
       CASE WHEN p.approval_status = 'APPROVED' THEN 'APPROVED' ELSE 'PENDING' END,
       1, 1, 0, NOW(), NOW()
  FROM providers p
  JOIN especialistas e ON e.id = p.legacy_especialista_id
  LEFT JOIN provider_members pm ON pm.provider_id = p.id AND pm.user_id = e.usuario_id
 WHERE pm.provider_id IS NULL;
