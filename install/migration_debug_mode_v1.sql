-- Migration: modo de debug global (arquitetura de observabilidade)
-- Adiciona a chave de configuração `debug_mode_ativo`, que quando ligada pelo
-- admin ativa logging verboso cross-cutting (backend PHP + frontend JS) em
-- todo o sistema: sistema/classe/função em execução, contexto JS e
-- localização exata do erro — pensado para acelerar diagnóstico tanto por
-- humanos quanto por IA (Constituição, item de arquitetura de observabilidade).
-- Idempotente: usa INSERT ... ON DUPLICATE KEY para não sobrescrever se já
-- existir um valor configurado manualmente.

INSERT INTO configuracoes (chave, valor, descricao)
VALUES (
    'debug_mode_ativo',
    '0',
    'Modo de debug global: quando "1", ativa logs verbosos (sistema/classe/função/JS/localização do erro) em todo o app, para diagnóstico por humanos e IA.'
)
ON DUPLICATE KEY UPDATE chave = chave;
