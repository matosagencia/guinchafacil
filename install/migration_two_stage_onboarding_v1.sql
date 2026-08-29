-- Migração para permitir cadastro em duas etapas:
-- Torna campos críticos de perfil obrigatórios apenas na etapa de conclusão.

ALTER TABLE guinchos MODIFY capacidade_ton DECIMAL(5,2) NULL;
ALTER TABLE guinchos MODIFY chave_pix VARCHAR(100) NULL;
ALTER TABLE guinchos MODIFY chave_pix_tipo ENUM('cpf','email','telefone','aleatoria') NULL;

-- Registra a versão da migration (assumindo o padrão do projeto)
INSERT INTO schema_migrations (version, applied_at) VALUES ('migration_two_stage_onboarding_v1', NOW());
