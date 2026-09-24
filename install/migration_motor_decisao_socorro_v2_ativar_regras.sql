-- GuinchaFacil - ativacao das regras comerciais do motor de decisao
-- Idempotente: apenas consolida configuracoes que ja fazem parte do fluxo.

UPDATE configuracoes
   SET valor = '1',
       descricao = 'Ativa o motor de monetizacao e indicacao de oficinas parceiras.'
 WHERE chave = 'monetizacao_oficinas_ativo';

UPDATE provider_workshop_settings
   SET raio_resgate_direto_km = 30.00
 WHERE raio_resgate_direto_km IS NULL
   AND status_parceria = 'ATIVO'
   AND faz_resgate_direto = 1;
