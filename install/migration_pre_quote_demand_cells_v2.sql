-- Evolucao da telemetria de demanda: registra casos sem cobertura.

ALTER TABLE `pre_quote_demand_cells`
    ADD COLUMN `no_coverage_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `converted_count`;
