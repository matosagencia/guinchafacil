-- migration_order_flow_v1.sql
-- ETAPA 3 do ROADMAP — EXPANSÃO PARA SOCORRO AUTOMOTIVO (Fundamento 4: novas
-- máquinas de estado). Amplia o ENUM de pedidos.status de forma ADITIVA —
-- os 7 valores atuais continuam exatamente iguais, na mesma ordem, no mesmo
-- lugar. Nenhum pedido de reboque existente ou em andamento é afetado.
-- (Decisão de arquitetura tomada com o usuário em 22/07/2026: ampliar o ENUM
-- em vez de criar tabela de estado paralela.)
--
-- Novos valores usados só por pedidos com attendance_mode <> 'TOWING'
-- (ver OnSiteFlowDefinition/HybridFlowDefinition em src/Services/OrderFlow/):
--   diagnostico_iniciado, diagnostico_concluido, autorizacao_servico_pendente,
--   em_execucao_servico, teste_final, conversao_reboque_pendente,
--   conversao_aprovada_cliente, preparacao_veiculo

ALTER TABLE `pedidos`
    MODIFY COLUMN `status` ENUM(
        'aguardando_pagamento',
        'aguardando_guincho',
        'a_caminho',
        'no_local',
        'em_reboque',
        'concluido',
        'cancelado',
        'diagnostico_iniciado',
        'diagnostico_concluido',
        'autorizacao_servico_pendente',
        'em_execucao_servico',
        'teste_final',
        'conversao_reboque_pendente',
        'conversao_aprovada_cliente',
        'preparacao_veiculo'
    ) NOT NULL DEFAULT 'aguardando_pagamento';

SELECT 'migration_order_flow_v1.sql aplicado — pedidos.status ampliado de forma aditiva.' AS resultado;
