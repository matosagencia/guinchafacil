-- migration_hibrido_complementar_v1.sql
-- §HIBRIDO-COMPLEMENTAR-01 (27/07/2026) — cobrança complementar de reboque
-- no caminho HÍBRIDO da conversão (prestador que já tinha o pedido de
-- socorro no local e também tem capacidade de reboque aprovada). Antes desta
-- migration, o híbrido pulava direto para 'preparacao_veiculo' sem cobrar
-- nada (mesmo bug de fundo do caminho não-híbrido, já corrigido antes via
-- migration_order_flow_v1.sql + ConversionService).
--
-- Amplia pedidos.status de forma ADITIVA (mesmo padrão de
-- migration_order_flow_v1.sql): todos os valores existentes continuam
-- exatamente iguais, na mesma ordem. Nenhum pedido existente é afetado.
--
-- 'aguardando_pagamento_reboque_hibrido': pedido convertido pra reboque,
-- prestador já vinculado é híbrido e continua com o pedido, mas o
-- complementar de reboque ainda não foi pago. Pagamento aprovado leva a
-- 'preparacao_veiculo'; se o prestador perder capacidade/aprovação antes
-- disso, o pedido é rebaixado para 'aguardando_guincho' (fila comum) — ver
-- PedidoTransitionService::approvePayment().
--
-- pagamentos_arquivados: nova tabela — `pagamentos` tem UNIQUE(pedido_id),
-- então uma segunda cobrança real (socorro local -> reboque complementar)
-- precisa arquivar o pagamento aprovado original antes de resetar a linha
-- para a nova cobrança. Sem isso, a segunda cobrança não conseguia nem ser
-- criada (Pagamento::criar batia no UNIQUE e reiniciarTentativa() recusa
-- reaproveitar linha já 'aprovado' — correto para o caso geral, mas bloqueava
-- este fluxo legítimo). Serve também de fonte inequívoca do valor pago pelo
-- socorro no local para o cálculo do crédito de conversão (antes uma query
-- ambígua em `pagamentos` filtrando só por status='aprovado').

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
        'preparacao_veiculo',
        'aguardando_pagamento_reboque_hibrido'
    ) NOT NULL DEFAULT 'aguardando_pagamento';

CREATE TABLE IF NOT EXISTS pagamentos_arquivados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id INT NOT NULL,
    -- §ARQUIVAMENTO-COMPLETO-01 (27/07/2026, achado em revisão): o
    -- pagamento reaproveitado mantém o MESMO `pagamentos.id` entre a
    -- cobrança do socorro no local e a do complementar — sem registrar
    -- explicitamente qual era o id da linha viva no momento do
    -- arquivamento, fica ambíguo depois relacionar payout_ledger_entries
    -- (que referencia pagamentos.id) antigos com o pagamento correto.
    pagamento_id_original INT NULL,
    fase VARCHAR(30) NOT NULL DEFAULT 'socorro_local',
    metodo VARCHAR(30) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_guincho DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_plataforma DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL,
    id_externo VARCHAR(150) NULL,
    id_transacao_pix VARCHAR(100) NULL,
    status_pix_original VARCHAR(20) NULL,
    pago_guincho_original TINYINT(1) NULL,
    webhook_payload TEXT NULL,
    criado_em_original DATETIME NULL,
    data_pagamento_original DATETIME NULL,
    arquivado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pedido_fase (pedido_id, fase),
    KEY idx_id_externo (id_externo),
    CONSTRAINT fk_pagamentos_arquivados_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'migration_hibrido_complementar_v1.sql aplicado — status híbrido complementar + pagamentos_arquivados.' AS resultado;
