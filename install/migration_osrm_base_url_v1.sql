-- migration_osrm_base_url_v1.sql
-- §7.2 — configura a chave OSRM_BASE_URL em producao.
-- Idempotente. Nao sobrescreve se ja existir.

INSERT INTO configuracoes (chave, valor, descricao, criado_em, atualizado_em)
SELECT
  'por_road_match_base_url',
  'https://router.project-osrm.org',
  'URL base do servico OSRM-compatible. DEV/QA: demo publico. PRODUCAO: instancia propria (ex: http://10.0.0.5:5000). Prioridade: env OSRM_BASE_URL > esta chave > demo.',
  NOW(), NOW()
 WHERE NOT EXISTS (
   SELECT 1 FROM configuracoes WHERE chave = 'por_road_match_base_url'
 );

SELECT chave, valor FROM configuracoes WHERE chave = 'por_road_match_base_url';