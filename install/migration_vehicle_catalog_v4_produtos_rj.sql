-- Catálogo RJ v2: itens de assistência e cobertura veicular ampliada.
-- Executada após v1-v3 do catálogo veicular e após o catálogo de produtos.
-- Preços são referências médias de mercado em BRL (RJ, 2026), editáveis.

INSERT IGNORE INTO produtos (sku, nome, categoria, descricao, especificacao, preco_referencia, unidade) VALUES
('BAT-55AH','Bateria 55Ah','bateria','Bateria 12V para compactos e sedãs.','55Ah 12V',470.00,'un'),
('BAT-75AH','Bateria 75Ah','bateria','Bateria 12V para SUVs, utilitários e diesel leve.','75Ah 12V',720.00,'un'),
('BAT-80AH','Bateria 80Ah','bateria','Bateria 12V para SUVs e utilitários.','80Ah 12V',790.00,'un'),
('BAT-90AH','Bateria 90Ah','bateria','Bateria 12V para utilitários e vans.','90Ah 12V',860.00,'un'),
('BAT-MOTO-5AH','Bateria moto 5Ah','bateria','Bateria para motos urbanas.','5Ah 12V',150.00,'un'),
('BAT-MOTO-7AH','Bateria moto 7Ah','bateria','Bateria para motos urbanas.','7Ah 12V',185.00,'un'),
('BAT-MOTO-9AH','Bateria moto 9Ah','bateria','Bateria para motos de maior cilindrada.','9Ah 12V',240.00,'un'),
('BAT-MOTO-12AH','Bateria moto 12Ah','bateria','Bateria para motos de maior cilindrada.','12Ah 12V',310.00,'un'),
('PNEU-175-70-R14','Pneu 175/70 R14','pneu','Pneu para compactos e hatches.','175/70 R14',360.00,'un'),
('PNEU-185-65-R15','Pneu 185/65 R15','pneu','Pneu para compactos e sedãs.','185/65 R15',440.00,'un'),
('PNEU-205-55-R16','Pneu 205/55 R16','pneu','Pneu para sedãs médios e hatches.','205/55 R16',540.00,'un'),
('PNEU-215-60-R17','Pneu 215/60 R17','pneu','Pneu para SUVs leves.','215/60 R17',680.00,'un'),
('PNEU-225-65-R17','Pneu 225/65 R17','pneu','Pneu para SUVs e utilitários leves.','225/65 R17',730.00,'un'),
('PNEU-265-65-R17','Pneu 265/65 R17','pneu','Pneu para picapes médias.','265/65 R17',920.00,'un'),
('PNEU-195-75-R16C','Pneu 195/75 R16C','pneu','Pneu reforçado para vans e comerciais leves.','195/75 R16C',790.00,'un'),
('PNEU-MOTO-90-90-18','Pneu moto 90/90-18','pneu','Pneu comum para motos urbanas.','90/90-18',290.00,'un'),
('PNEU-MOTO-110-70-17','Pneu moto 110/70-17','pneu','Pneu para motos médias.','110/70-17',370.00,'un'),
('PNEU-MOTO-120-80-18','Pneu moto 120/80-18','pneu','Pneu para motos de maior cilindrada.','120/80-18',430.00,'un'),
('SERV-MACARRAO-MOTO','Reparo de pneu com macarrão — moto','servico_pneu','Reparo emergencial externo, sujeito à avaliação de segurança.','moto',35.00,'serviço'),
('SERV-MACARRAO-PASSEIO','Reparo de pneu com macarrão — passeio','servico_pneu','Reparo emergencial externo, sujeito à avaliação de segurança.','carro/sedã/hatch',45.00,'serviço'),
('SERV-MACARRAO-SUV','Reparo de pneu com macarrão — SUV','servico_pneu','Reparo emergencial externo, sujeito à avaliação de segurança.','SUV',55.00,'serviço'),
('SERV-MACARRAO-PICKUP','Reparo de pneu com macarrão — picape','servico_pneu','Reparo emergencial externo, sujeito à avaliação de segurança.','picape',60.00,'serviço'),
('SERV-MACARRAO-VAN','Reparo de pneu com macarrão — van','servico_pneu','Reparo emergencial externo, sujeito à avaliação de segurança.','van/comercial',70.00,'serviço');

CREATE TABLE IF NOT EXISTS service_vehicle_price_references (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    operational_category_id INT NULL,
    reference_amount DECIMAL(10,2) NOT NULL,
    region_code VARCHAR(10) NOT NULL DEFAULT 'RJ',
    source_note VARCHAR(255) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_service_vehicle_price (product_id, operational_category_id, region_code),
    KEY idx_service_vehicle_price_region (region_code, active),
    CONSTRAINT fk_svp_product FOREIGN KEY (product_id) REFERENCES produtos(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_svp_category FOREIGN KEY (operational_category_id) REFERENCES vehicle_operational_categories(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO service_vehicle_price_references (product_id, operational_category_id, reference_amount, region_code, source_note)
SELECT p.id, c.id, x.valor, 'RJ', 'Referência média RJ 2026; confirmar medida, marca, disponibilidade e segurança no local.'
FROM produtos p
JOIN (SELECT 'SERV-MACARRAO-MOTO' sku, 'MOTORCYCLE_LIGHT' cat, 35.00 valor UNION ALL SELECT 'SERV-MACARRAO-PASSEIO','PASSENGER_COMPACT',45.00 UNION ALL SELECT 'SERV-MACARRAO-PASSEIO','PASSENGER_HATCH',45.00 UNION ALL SELECT 'SERV-MACARRAO-PASSEIO','PASSENGER_SEDAN',45.00 UNION ALL SELECT 'SERV-MACARRAO-SUV','SUV_LIGHT',55.00 UNION ALL SELECT 'SERV-MACARRAO-SUV','SUV_HEAVY',60.00 UNION ALL SELECT 'SERV-MACARRAO-PICKUP','PICKUP_LIGHT',60.00 UNION ALL SELECT 'SERV-MACARRAO-PICKUP','PICKUP_HEAVY',70.00 UNION ALL SELECT 'SERV-MACARRAO-VAN','VAN_LIGHT',70.00 UNION ALL SELECT 'SERV-MACARRAO-VAN','COMMERCIAL_LIGHT',70.00) x ON x.sku = p.sku
JOIN vehicle_operational_categories c ON c.code = x.cat;

INSERT INTO vehicle_brands (name) VALUES ('Nissan'),('Jeep'),('Kia'),('Mitsubishi'),('Suzuki'),('BYD') ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO vehicle_models (brand_id, name)
SELECT b.id, x.modelo FROM (SELECT 'Nissan' marca,'Kicks' modelo UNION ALL SELECT 'Nissan','Frontier' UNION ALL SELECT 'Jeep','Renegade' UNION ALL SELECT 'Jeep','Compass' UNION ALL SELECT 'Kia','Sportage' UNION ALL SELECT 'Mitsubishi','L200' UNION ALL SELECT 'Suzuki','Jimny' UNION ALL SELECT 'BYD','Dolphin') x JOIN vehicle_brands b ON b.name=x.marca
ON DUPLICATE KEY UPDATE name=VALUES(name);
INSERT INTO vehicle_versions (model_id, name, start_year, end_year, fuel_type, body_type, operational_category_id, active)
SELECT vm.id, x.versao, x.ano_inicio, x.ano_fim, x.combustivel, x.carroceria, c.id, 1
FROM (SELECT 'Nissan' marca,'Kicks' modelo,'1.6 Flex' versao,2017 ano_inicio,NULL ano_fim,'flex' combustivel,'suv' carroceria,'SUV_LIGHT' cat UNION ALL SELECT 'Nissan','Frontier','2.3 Diesel 4x4',2017,NULL,'diesel','pickup','PICKUP_HEAVY' UNION ALL SELECT 'Jeep','Renegade','1.3 Turbo Flex',2022,NULL,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Jeep','Compass','1.3 Turbo Flex',2022,NULL,'flex','suv','SUV_HEAVY' UNION ALL SELECT 'Kia','Sportage','2.0 Flex',2011,2022,'flex','suv','SUV_LIGHT' UNION ALL SELECT 'Mitsubishi','L200','2.4 Diesel 4x4',2017,NULL,'diesel','pickup','PICKUP_HEAVY' UNION ALL SELECT 'Suzuki','Jimny','1.3 4x4',2013,2022,'gasolina','suv','SUV_LIGHT' UNION ALL SELECT 'BYD','Dolphin','Elétrico',2023,NULL,'eletrico','hatch','ELECTRIC_LIGHT') x
JOIN vehicle_brands b ON b.name=x.marca JOIN vehicle_models vm ON vm.brand_id=b.id AND vm.name=x.modelo JOIN vehicle_operational_categories c ON c.code=x.cat
WHERE NOT EXISTS (SELECT 1 FROM vehicle_versions v WHERE v.model_id=vm.id AND v.name=x.versao);
