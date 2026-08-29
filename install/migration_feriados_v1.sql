-- install/migration_feriados_v1.sql
-- §A6 (auditoria 21/07): tarifa não considerava feriados — tabela nunca
-- existiu. `recorrente_anual=1` casa por mês/dia todo ano (ex: Natal,
-- Ano Novo); `recorrente_anual=0` é uma data específica de um ano só
-- (ex: ponto facultativo local). `tarifa_feriado_km`/`tarifa_feriado_fixa`
-- seguem o mesmo padrão de tarifa_noturna_km/tarifa_noturna_fixa
-- (migration_add_tariff_configs.sql) — adicional que EMPILHA sobre a
-- tarifa base (e sobre o noturno, se coincidirem).

CREATE TABLE IF NOT EXISTS `feriados` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `data` DATE NOT NULL,
    `nome` VARCHAR(120) NOT NULL,
    `recorrente_anual` TINYINT(1) NOT NULL DEFAULT 1,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_feriado_data` (`data`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO configuracoes (chave, valor, descricao, atualizado_em)
VALUES
('tarifa_feriado_km', '5.50', 'Adicional por KM em feriados (R$) — empilha com o noturno se coincidirem', NOW()),
('tarifa_feriado_fixa', '15.00', 'Adicional fixo em feriados (R$) — empilha com o noturno se coincidirem', NOW())
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);

-- Feriados nacionais fixos, pré-cadastrados como recorrentes (o admin pode
-- desativar/remover os que não se aplicam e cadastrar os móveis/locais).
INSERT INTO feriados (data, nome, recorrente_anual, ativo)
VALUES
('2026-01-01', 'Confraternização Universal', 1, 1),
('2026-04-21', 'Tiradentes', 1, 1),
('2026-05-01', 'Dia do Trabalho', 1, 1),
('2026-09-07', 'Independência do Brasil', 1, 1),
('2026-10-12', 'Nossa Senhora Aparecida', 1, 1),
('2026-11-02', 'Finados', 1, 1),
('2026-11-15', 'Proclamação da República', 1, 1),
('2026-12-25', 'Natal', 1, 1)
ON DUPLICATE KEY UPDATE nome = VALUES(nome);

-- Chaves de tarifa por categoria que faltavam: TarifaService::categoriaDeVeiculo()
-- já resolvia 'suv'/'eletrico'/'caminhonete', mas sem estas chaves de config
-- resolverTarifaPorKm()/resolverTaxaFixa() sempre caíam no fallback base
-- (tarifa_por_km/taxa_fixa) — ou seja, toda categoria cobrava o mesmo valor
-- na prática, apesar do código já diferenciar por categoria.
INSERT INTO configuracoes (chave, valor, descricao, atualizado_em)
VALUES
('tarifa_suv_km', '4.20', 'Tarifa por KM para categoria SUV (R$)', NOW()),
('tarifa_suv_fixa', '12.00', 'Taxa fixa para categoria SUV (R$)', NOW()),
('tarifa_caminhonete_km', '4.80', 'Tarifa por KM para categoria caminhonete/utilitário (R$)', NOW()),
('tarifa_caminhonete_fixa', '14.00', 'Taxa fixa para categoria caminhonete/utilitário (R$)', NOW()),
('tarifa_eletrico_km', '3.50', 'Tarifa por KM para categoria elétrico (R$)', NOW()),
('tarifa_eletrico_fixa', '10.00', 'Taxa fixa para categoria elétrico (R$)', NOW())
ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);
