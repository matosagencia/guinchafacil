-- Reprecificacao do atendimento de troca de pneu no catalogo dos especialistas.
-- Ajuste para faixa mais compatível com atendimento urbano curto (Niteroi / Rio).
-- Migração idempotente: pode ser reaplicada sem efeito colateral.

START TRANSACTION;

UPDATE servicos_especialista
   SET preco_atendimento = 49.90,
       preco_adicional = 20.00,
       adicional_noturno = 15.00
 WHERE codigo = 'TIRE_CHANGE';

COMMIT;
