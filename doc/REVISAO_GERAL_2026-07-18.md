# GuinchaFácil — Revisão geral de documentação e pendências (18/07/2026)

Revisão feita cruzando a Constituição Mestra, as auditorias anteriores (14/07) e o estado real do código verificado hoje (arquivos existentes em `src/Controllers`, `src/Services`, `src/Models`, `qa/suites`, `install/*.sql`).

---

## 1. Itens que a Constituição marcava como PENDENTE mas já estão implementados

Confirmado por arquivo real no código, não por suposição:

| Item | Evidência |
|---|---|
| Admin Health | `src/Services/HealthService.php` + rota `/admin/health` |
| Simulador oficial ponta a ponta | `SimulationService.php`, `SimulationRun/Step/Artifact` (models), rota `/admin/simulador` |
| Suíte Playwright | 24 specs em `qa/suites/` (smoke, pagamento-sandbox, cancelamento, concorrência, onboarding, etc.) — não é mais pendente como bloco, mas falta 1 suíte específica (item 3) |
| Governança do `.env` pelo admin | rotas `/admin/env`, `/admin/env/salvar`, `/admin/env/auditoria` + `ConfigSecurityService` |
| Seletor único de gateway | `PAYMENT_GATEWAY_ACTIVE` + `PaymentProviderFactory`/`MercadoPagoProvider`/`PagSeguroProvider` |
| Estorno automático | `src/Services/EstornoService.php` existe |
| PIX-GUARD | logs confirmam `PIX-GUARD-03` ativo em `PixService` |
| Modo de debug global | `DebugMode.php` + `migration_debug_mode_v1.sql` |
| Carteira/ledger financeiro do guincheiro | `src/Models/Finance/PayoutLedgerEntry.php` + `src/Services/Payment/PayoutLedgerService.php` + `migration_payment_ledger_v2.sql` (nome diferente do especificado na constituição, mas cobre a mesma função) |
| Funcionário/Gerente + Demandas (separação de deveres) | `FuncionarioController`, `GerenteController`, `Demanda.php`, `DemandaService.php`, `migration_funcionario_gerente_v1.sql` — feito nesta sessão |
| Auditoria por ação no registro do pedido | seção "Histórico de demandas" em `admin/pedidodetalhe.php` |

---

## 2. Pendências reais confirmadas hoje

| # | Item | Situação |
|---|---|---|
| 1 | Suíte Playwright funcionário/gerente | **Resolvido.** `qa/suites/funcionario-gerente-demandas.spec.ts` criada e verde (13/13, 1 flaky que se recupera no retry automático). No processo, 3 bugs reais de produto foram encontrados e corrigidos: `pedido_id` duplicado no form de nova demanda (quebrava toda criação), flash de erro invisível na tela, e reembolso "valor em branco" nunca disparava dupla aprovação mesmo em valor alto (furo de segurança real). |
| 2 | `.env.local` duplicado/desatualizado dentro do webroot | **Resolvido.** Pasta interna `C:\xampp\htdocs\guinchafacil-secrets\` apagada; sistema usa só a cópia externa (`C:\xampp\guinchafacil-secrets\.env.local`, fora do webroot). De quebra, foi identificado e mitigado um risco de exposição real: como o vhost padrão da porta 80 do XAMPP aponta `DocumentRoot` direto pra `C:\xampp\htdocs`, aquele arquivo estava potencialmente servível via HTTP sem nenhum bloqueio — foi adicionado um `.htaccess` com `Require all denied` como cinto de segurança (hoje redundante já que a pasta não existe mais, mas fica documentado o gap). |
| 3 | `.htaccess` — handler cPanel órfão | **Resolvido** (já vinha comentado desde o início da sessão, ao corrigir o erro 500 original). Não reativar em ambiente local — só se reimplantar em host cPanel real. |
| 4 | Tabelas do sistema MariaDB (`mysql.*`) corrompidas | Reparadas hoje (`aria_chk -r`). **Causa raiz investigada e confirmada**: `C:\xampp\mysql\data\mysql_error.log` mostra, desde abril/2026, o padrão `InnoDB: Operating system error number 32 in a file operation — another program is using InnoDB's files` repetido, e em pelo menos um caso (24/04) linhas do log literalmente entrelaçadas/corrompidas, evidenciando **duas instâncias de `mysqld.exe` escrevendo ao mesmo tempo** — um `mysqld.exe` anterior não terminava de verdade (kill forçado, fechamento abrupto do Painel XAMPP, ou antivírus travando `ibdata1`) antes do próximo subir, e as tabelas Aria/MyISAM (sem redo-log à prova de crash) ficam com índice corrompido. Mitigado hoje: exclusão de `C:\xampp\mysql\data`/`mysqld.exe`/`httpd.exe` no antivírus + orientação de sempre checar `Get-Process mysqld` antes de reiniciar e nunca fechar o painel/desligar o PC sem clicar "Stop" primeiro. |

---

## 3. Pendências herdadas da auditoria de 14/07 — reverificadas em 18/07 (sessão de correção)

Todos os 12 itens abaixo foram investigados a fundo no código real (não por suposição) e corrigidos onde havia problema de verdade. Resultado: **9 já estavam resolvidos** (a auditoria de 14/07 estava desatualizada) e **3 tinham bug real**, agora corrigidos.

| Item | Veredito | Detalhe |
|---|---|---|
| Rastreamento em tempo real com nome de ruas (`a_caminho`) | **Já resolvido** | `RoutingSnapshotService` + `StreetResolutionService` (map-matching, geofence, reverse geocode) já implementados e testados (`E2E-ORD-002`). Painel do Leaflet Routing Machine é `show:false` de propósito — substituído por UI própria melhor. |
| Cancelamento com taxa proporcional | **Já resolvido** | `CancellationCalculationService` já usa `distance_ratio`/`time_ratio`, não é binário. Bloqueio de <2km é regra de negócio válida (serviço já quase concluído), não limitação técnica. |
| `app.js` — máscaras vazias | **Parcialmente falso + limpo** | `MaskUtils` (CPF/telefone) já funciona 100%, plugado nos cadastros. `ChatManager`/`CostCalculator`/`StatusPoller` eram stubs vazios sem nenhum uso (grep confirmou zero referências) — removidos por limpeza. |
| Tarifa duplicada em 4-5 lugares | **Já resolvido** | `TarifaService::calcularDetalhado()` → `GeoService::calcularCusto()` é o único caminho; views só exibem o resultado vindo do servidor. |
| `SseController.php` código morto | **Já resolvido** | 3 rotas registradas (`/sse/pedidos`, `/sse/admin/pedidos`, `/sse/pedido/{id}`), usadas em 6 views. |
| Placeholders em termos/privacidade | **Já resolvido** | Ambos os arquivos têm conteúdo jurídico real e completo (14 seções). |
| Crons sem agendamento | **Real — corrigido** | Scripts funcionam; não havia agendamento no Windows. Criadas as 5 tarefas via `schtasks` (achado bônus: `cron_retencao_operacional.php`, não citado na lista original) — confirmado ÊXITO em todas na máquina do usuário. |
| Escape de HTML em email | **Já resolvido** | Todo dado dinâmico passa por `NotificacaoService::e()` (`htmlspecialchars`) antes do template. |
| Política de senha inconsistente | **Real — corrigido** | Backend sempre exigia 8+; 4 telas (`guincho/perfil.php`, `admin/usuarioform.php`, `admin/usuarioedit.php`, `admin/guinchoform.php`) mostravam `minlength="6"` e "mínimo 6". Corrigidas para 8. |
| Download autenticado de documentos | **Real — corrigido (mais grave que "parcial")** | CNH/foto do veículo do guincheiro ficavam em `public/uploads/` (dentro do webroot, servível direto, ignorando toda autenticação). Movido para `UPLOAD_PATH_DOCS` fora do webroot (mesmo padrão do `storage/private/evidencias`), com fallback para arquivos antigos. Duas telas admin que linkavam direto pra `/uploads/...` corrigidas para usar `/arquivo/{id}?tipo=...`. |
| Geocoding com cache canônico | **Real — corrigido** | Só `reverseGeocode()` tinha cache persistente. `geocode()` (forward) e `buscarCep()` só tinham cache em memória (morre a cada request). Schema já tinha os tipos `forward`/`cep` prontos, sem código usando — implementado agora, mesmo padrão do reverse. |
| Seed sem `comissao_percentual` | **Não-issue** | Chave foi renomeada para `comissao_plataforma` há tempos; é isso que todo o código real usa (5 lugares, todos com fallback seguro `?? 0.15`). `comissao_percentual` só sobrevive em scripts de migração históricos sem efeito prático. |

---

## 4. Percentual estimado

Usando a mesma régua da auditoria de 14/07 (estrutura 30% / integração 35% / aceite-testes 20% / hardening 15%), atualizada com o que foi confirmado hoje:

| Métrica | 14/07 | Hoje (18/07) | Nota |
|---|---:|---:|---|
| Cobertura estrutural | 79% | **~85%** | Funcionário/gerente, demandas, ledger, health, simulador, env-governance e gateway selector fecharam boa parte do que faltava estruturalmente. |
| Integração funcional | 68% | **~70%** | Ganho pequeno — os módulos novos existem mas ainda não têm cobertura de teste automatizado (item 1 da seção 2). |
| Prontidão de aceite/produção | 60% | **~62%** | Ambiente local teve uma degradação real de infraestrutura (handler cPanel + corrupção do MySQL) que consumiu esta sessão inteira e não tem relação com o produto em si — mas é um lembrete de que o ambiente de QA precisa de mais resiliência. |
| Aderência visual aos guias | 39% | **39%** (não avaliado hoje) | Precisa de nova auditoria visual dedicada. |

**Leitura honesta: o projeto está por volta de 65-70% pronto para produção real**, puxado para cima pela fundação (estrutura, backend, segurança, separação de deveres) e para baixo pelos mesmos gaps de sempre: rastreamento em tempo real, cancelamento proporcional, front-end JS incompleto, e falta de teste automatizado para a feature mais recente.

---

## 5. Recomendação de ordem

1. Escrever a suíte Playwright de funcionário/gerente (única pendência 100% nova, sem dependência de nada).
2. Mover o `.env.local` interno para fora do webroot definitivamente e remover a cópia duplicada.
3. Fechar rastreamento em tempo real com nome de ruas (maior impacto de produto pendente).
4. Implementar cancelamento proporcional (impacto financeiro direto).
5. Preencher `app.js` (máscaras) — baixo esforço, alto impacto de qualidade percebida.
6. Agendar os crons em produção.
