-- GuinchaFácil - dados locais de teste
-- Execute depois do schema/migrations em guinchafacil_dev.
-- Idempotente por e-mail/CPF/placa/nome. Não contém credenciais de produção.

INSERT INTO usuarios (nome,email,senha_hash,telefone,cpf,tipo,ativo,criado_em)
VALUES ('Cliente Teste Playwright','cliente.teste@guinchafacil.dev',
        '$2y$10$F04LwXpVu40Mgeaus8G8C.evSQi717hUVPvyB.CmOXhCW5nCMZWNi',
        '21999999999','12345678909','cliente',1,NOW())
ON DUPLICATE KEY UPDATE nome=VALUES(nome),telefone=VALUES(telefone),ativo=1,senha_hash=VALUES(senha_hash);

SET @cliente_id := (SELECT id FROM usuarios WHERE email='cliente.teste@guinchafacil.dev' LIMIT 1);

INSERT INTO veiculos (usuario_id,placa,cidade_placa,uf_placa,marca,modelo,ano,cor,tipo,criado_em)
SELECT @cliente_id,'TEST123','Rio de Janeiro','RJ','Volkswagen','Gol',2020,'Prata','carro',NOW()
WHERE NOT EXISTS (SELECT 1 FROM veiculos WHERE usuario_id=@cliente_id AND placa='TEST123');

INSERT INTO oficinas_favoritas (usuario_id,nome,telefone,endereco,lat,lng,criado_em,atualizado_em)
SELECT @cliente_id,'Mecânica Especializada Rio','2122222222','Rua do Riachuelo, 450, Rio de Janeiro - RJ',-22.9145,-43.1862,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM oficinas_favoritas WHERE usuario_id=@cliente_id AND nome='Mecânica Especializada Rio');
INSERT INTO oficinas_favoritas (usuario_id,nome,telefone,endereco,lat,lng,criado_em,atualizado_em)
SELECT @cliente_id,'Auto Center Gamboa','2123333333','Av. Rodrigues Alves, 200, Rio de Janeiro - RJ',-22.8955,-43.1950,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM oficinas_favoritas WHERE usuario_id=@cliente_id AND nome='Auto Center Gamboa');

INSERT INTO usuarios (nome,email,senha_hash,telefone,cpf,tipo,ativo,criado_em)
SELECT 'Guincho Teste Plataforma','guincho.plataforma@guinchafacil.dev',
       '$2y$10$F04LwXpVu40Mgeaus8G8C.evSQi717hUVPvyB.CmOXhCW5nCMZWNi','21999990001','12345678901','guincho',1,NOW()
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email='guincho.plataforma@guinchafacil.dev');
INSERT INTO usuarios (nome,email,senha_hash,telefone,cpf,tipo,ativo,criado_em)
SELECT 'Guincho Teste Lança','guincho.lanca@guinchafacil.dev',
       '$2y$10$F04LwXpVu40Mgeaus8G8C.evSQi717hUVPvyB.CmOXhCW5nCMZWNi','21999990002','12345678902','guincho',1,NOW()
WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email='guincho.lanca@guinchafacil.dev');

INSERT INTO guinchos (usuario_id,cnh_numero,cnh_validade,placa_guincho,modelo_veiculo,ano_veiculo,capacidade_ton,raio_cobertura_km,chave_pix,chave_pix_tipo,lat_atual,lng_atual,lat_operacao,lng_operacao,disponivel,aprovado,criado_em,atualizado_em)
SELECT u.id,'TESTE123','2030-12-31','TSTPLAT1','Plataforma teste',2020,3.50,30,'teste-plataforma','aleatoria',-22.9140,-43.1870,-22.9140,-43.1870,1,1,NOW(),NOW()
FROM usuarios u WHERE u.email='guincho.plataforma@guinchafacil.dev'
AND NOT EXISTS (SELECT 1 FROM guinchos g WHERE g.placa_guincho='TSTPLAT1');
INSERT INTO guinchos (usuario_id,cnh_numero,cnh_validade,placa_guincho,modelo_veiculo,ano_veiculo,capacidade_ton,raio_cobertura_km,chave_pix,chave_pix_tipo,lat_atual,lng_atual,lat_operacao,lng_operacao,disponivel,aprovado,criado_em,atualizado_em)
SELECT u.id,'TESTE456','2030-12-31','TSTLANC1','Lança teste',2020,2.00,30,'teste-lanca','aleatoria',-22.9130,-43.1880,-22.9130,-43.1880,1,1,NOW(),NOW()
FROM usuarios u WHERE u.email='guincho.lanca@guinchafacil.dev'
AND NOT EXISTS (SELECT 1 FROM guinchos g WHERE g.placa_guincho='TSTLANC1');
