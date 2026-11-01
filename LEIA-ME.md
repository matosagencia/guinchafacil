# Instalação conservadora — A-MAPA-23

Fonte consultada: repositório GitHub `matosagencia/guinchafacil` (branch padrão), incluindo `PricingZone`, `GuinchoDisponibilidade` e `BloqueioFinanceiroService` como contrato existente. **Não implica que essa versão seja idêntica ao seu XAMPP local.**

1. Compare `src/Services/PreCotacao/MapaCoberturaService.php` com o código do XAMPP.
2. Copie somente esse arquivo à árvore `src/Services/PreCotacao/` caso o caminho ainda não exista. Não substitua serviços/rotas compartilhados.
3. Rode `& 'C:\xampp\php\php.exe' -l 'src\Services\PreCotacao\MapaCoberturaService.php'`.
4. Confirme no seu MySQL: `SHOW COLUMNS FROM pricing_zones; SHOW COLUMNS FROM guinchos;` e se `BloqueioFinanceiroService::idsBloqueados()` retorna IDs de guinchos.
5. Entregue o snippet de `doc/CONTRATO_B_A_MAPA_23.md` ao **dono** para registrar a rota `index.php` e conectar o middleware rate-limit por IP. Nenhum arquivo compartilhado foi alterado aqui.
6. Teste com GET local usando coordenadas em Niterói, depois GET em área sem cobertura. Confirme que não há localização precisa do guincho e que zonas `nao_ativada` não vazam.

## Pendências
- Integração no roteador/middleware existente (somente dono).
- Homologação da query no banco `guinchafacil_dev`.
- Homologação com testes T1/T2 (não executados nesse ambiente).
- Os marcadores não demonstram guinchos cobrindo individualmente a ocorrência; são indicação pública aproximada da rede.
# Regra operacional atual — provider unificado

Especialista e oficina são o mesmo prestador operacional no motor atual. Para testes locais, configure o provider central, suas coordenadas/raio e capacidades; não dependa de uma oficina legada isolada para validar cobertura.

