-- install/migration_payment_ledger_v2.sql
-- Pacote L1.7 — ledger de repasse append-only (plano seção 4.8).
-- Diferente de `pagamentos` (mutável, uma linha por pagamento), esta tabela
-- nunca é atualizada nem apagada: cada evento financeiro relevante grava uma
-- linha nova, permitindo provar contabilmente que
--   soma(credito_guincho) + soma(credito_plataforma) = soma(valor_total aprovado)
-- e que soma(debito_repasse_guincho) <= soma(credito_guincho) ao longo do tempo,
-- mesmo que `pagamentos` seja corrigida manualmente depois.
-- Idempotente: pode ser reexecutada sem erro.

CREATE TABLE IF NOT EXISTS payout_ledger_entries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pagamento_id INT NOT NULL,
    pedido_id INT NOT NULL,
    entry_type ENUM(
        'credito_guincho',
        'credito_plataforma',
        'debito_repasse_guincho',
        'estorno_credito_guincho',
        'estorno_credito_plataforma'
    ) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    referencia_externa VARCHAR(150) NULL,
    metadata_json TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pagamento (pagamento_id),
    KEY idx_pedido (pedido_id),
    KEY idx_tipo_criado (entry_type, criado_em),
    CONSTRAINT fk_payout_ledger_pagamento FOREIGN KEY (pagamento_id) REFERENCES pagamentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_payout_ledger_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
