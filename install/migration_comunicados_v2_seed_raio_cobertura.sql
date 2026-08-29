-- §COBERTURA-RAIO-01 (05/08/2026): comunicado interno pro painel do
-- guincheiro/especialista (mesma tabela/mesmo dashboard, ver
-- ComunicadoService::PLACEMENT_TOW_DASHBOARD_AFTER_STATS) avisando que
-- raio_cobertura_km, que antes era só metadado exibido, agora AFETA de
-- verdade quantos pedidos aparecem na fila (GuinchoController::
-- montarOfertasDisponiveis / SseController::pedidosDisponiveis, via
-- CoberturaService::raioEfetivoGuincho). Sem esse aviso, quem nunca
-- ajustou o campo (fica em 20km por padrão desde o cadastro) pode ver a
-- fila encolher sem entender o motivo.
--
-- Roda depois de migration_comunicados_v1.sql (cria a tabela) — nome com
-- sufixo v2 garante ordem alfabética correta em install/migrate.php.
--
-- Idempotente por (titulo, placement): não há unique key de negócio na
-- tabela `comunicados`, então usamos INSERT ... SELECT ... WHERE NOT
-- EXISTS para nunca duplicar em reexecuções do migrate.php.

INSERT INTO comunicados (
    titulo, subtitulo, etiqueta, publico, placement, formato, tema,
    imagem_desktop, imagem_alt, cta_label, cta_url, cta_target,
    status, prioridade, inicio_em, fim_em, duracao_slide_seg,
    frequencia, dismissivel, dismiss_ttl_horas
)
SELECT
    'Seu raio de cobertura agora define quantos chamados você recebe',
    'Ajustamos o sistema para respeitar de verdade o raio_cobertura_km do seu cadastro — antes ele era só informativo. Revise em "Meu Perfil" e aumente se quiser aparecer em mais chamados da sua região.',
    'Dica',
    'guincho',
    'guincho_dashboard_after_stats',
    'wide',
    'info',
    '/public/assets/img/logo-wordmark.png',
    'GuinchaFácil',
    'Revisar meu raio',
    '/guincho/perfil',
    'self',
    'publicado',
    100,
    NULL,
    NULL,
    8,
    'sessao',
    1,
    24
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM comunicados
     WHERE titulo = 'Seu raio de cobertura agora define quantos chamados você recebe'
       AND placement = 'guincho_dashboard_after_stats'
);
