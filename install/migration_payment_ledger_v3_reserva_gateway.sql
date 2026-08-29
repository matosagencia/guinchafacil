-- install/migration_payment_ledger_v3_reserva_gateway.sql
-- §SPLIT-LIQUIDO-01 / §RECONCILIACAO-01 (03/08/2026): PayoutLedgerService::
-- registrarSplitAprovado() já sabe gravar um lançamento com
-- entry_type = 'reserva_gateway' (a parcela retida para taxa de gateway,
-- ver src/Services/Payment/PayoutLedgerService.php), mas o ENUM de
-- payout_ledger_entries.entry_type (migration_payment_ledger_v2.sql) nunca
-- foi atualizado pra incluir esse valor. Hoje nenhum chamador passa
-- $valorReservaGateway > 0, então o bug está adormecido — mas o primeiro
-- pagamento que passar essa reserva vai falhar o INSERT (valor fora do
-- ENUM). Corrige isso ampliando o ENUM sem tocar nos valores existentes.
-- Idempotente: só altera se 'reserva_gateway' ainda não estiver no tipo.

SET @db_name := DATABASE();

SET @tem_valor := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'payout_ledger_entries'
      AND COLUMN_NAME = 'entry_type' AND COLUMN_TYPE LIKE '%reserva_gateway%'
);

SET @sql := IF(@tem_valor = 0,
    "ALTER TABLE payout_ledger_entries MODIFY COLUMN entry_type ENUM(
        'credito_guincho',
        'credito_plataforma',
        'debito_repasse_guincho',
        'estorno_credito_guincho',
        'estorno_credito_plataforma',
        'reserva_gateway'
    ) NOT NULL",
    'SELECT "payout_ledger_entries.entry_type já contém reserva_gateway" AS info'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
