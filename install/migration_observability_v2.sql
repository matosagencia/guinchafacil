ALTER TABLE app_logs
    ADD COLUMN application VARCHAR(32) NULL AFTER criado_em,
    ADD COLUMN file VARCHAR(255) NULL AFTER `system`,
    ADD COLUMN phase VARCHAR(120) NULL AFTER file,
    ADD COLUMN code VARCHAR(64) NULL AFTER phase,
    ADD COLUMN request_id VARCHAR(64) NULL AFTER code,
    ADD COLUMN run_id VARCHAR(64) NULL AFTER request_id,
    ADD COLUMN pedido_id INT NULL AFTER run_id,
    ADD COLUMN usuario_id INT NULL AFTER pedido_id,
    ADD COLUMN guincho_id INT NULL AFTER usuario_id,
    ADD COLUMN duration_ms INT NULL AFTER guincho_id;

CREATE INDEX idx_app_logs_system_created ON app_logs(`system`, criado_em);
CREATE INDEX idx_app_logs_code_created ON app_logs(code, criado_em);
CREATE INDEX idx_app_logs_request_id ON app_logs(request_id);
CREATE INDEX idx_app_logs_run_id ON app_logs(run_id);
CREATE INDEX idx_app_logs_pedido_created ON app_logs(pedido_id, criado_em);
