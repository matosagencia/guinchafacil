# GuinchaFácil — Marco técnico e plano de conclusão

**Data:** 20/10/2026  
**Ambiente-fonte:** `C:\xampp\htdocs\guinchafacil` (XAMPP Windows)  
**Banco de desenvolvimento:** `guinchafacil_dev`  
**Bancos de homologação:** `guinchafacil_fin3c4_test` e `guinchafacil_fin3_immutable_qa`  
**Repositório remoto:** `matosagencia/guinchafacil` (`main`, público)  
**Natureza:** checkpoint técnico, NÃO homologação de produção.

## 1. Escopo e regras de governança

O GuinchaFácil integra website/backoffice PHP, API, serviços financeiros e aplicativo Android para assistência automotiva, oficinas e guinchos. Implementações e homologações até este marco foram realizadas no XAMPP local. O commit de checkpoint deve ser feito a partir da cópia local após revisão do diff; publicação remota, migração do banco principal e implantação em produção são marcos distintos e exigem validação específica.

Não incluir em commits: `.env*`, tokens, chaves API, logs, backups, bancos SQL contendo dados reais, imagens/documentos pessoais, arquivos de clientes, credenciais ou artefatos de depuração. Scripts de migração estruturais somente quando revisados e sem segredos.

## 2. Implementado e evidências

| Trilha | Entregas principais | Evidência disponível | Classificação |
|---|---|---|---|
| Interface/Backoffice | layout unificado, navegação administrativa, perfis oficina/guincho/cliente, revisões e controles | homologações anteriores em XAMPP | implementado, revalidar regressão integral |
| Operação/preço | motor de preços com relevo, contratação de serviços, regras de matching, assistência local, fallbacks e inadimplência | vetores e testes isolados relatados | partes implementadas; integração operacional pendente |
| FIN-2 | carteira, pagamentos, compensação, financeiro BO | homologações locais anteriores | implementado, integração final pendente |
| FIN.3-C.2–C.9 | cálculo, ledger bilateral de eventos, fechamento semanal, ajustes tardios e multiciclo | PASS em staging, testes concorrentes | homologação técnica isolada |
| FIN.3-C.10–11.7 | concorrência, auditoria de integrações e adaptador financeiro transacional | PASS em staging | sem integração em fluxo real |
| FIN.3-C.11.8–11.9 | contrato monetário em centavos e conciliador das fontes | PASS sem SQL | contrato definido; divergência comercial precisa decisão explícita |
| FIN.3-C.11.10 | snapshot lógico em memória, SHA-256, ajuste separado | 15 testes PASS, ExitCode 0 | homologado em memória |
| FIN.3-C.11.11 A–D | DDL, persistência idempotente transacional, concorrência, crash, retry, rollback, ajuste | R3 PASS ExitCode 0; bancos isolados | homologado em MariaDB isolado |
| FIN.3-C.11.12 A–B | auditoria e desenho de imutabilidade | CHECK/PLAN PASS | aprovado |
| FIN.3-C.11.12 C1–C2 | banco descartável, 4 triggers, `UPDATE`/`DELETE` bloqueados, integridade preservada | TESTAR.ps1 PASS ExitCode 0 | homologado no banco descartável |

**Estado dos bancos de teste:** ao final de FIN.3-C.11.11-D, `fin3_snapshots_financeiros` e `fin3_snapshot_ajustes_propostos` estavam vazias em `guinchafacil_fin3c4_test`. Em `guinchafacil_fin3_immutable_qa`, um snapshot e um ajuste permanecem intencionalmente para provar a imutabilidade. Não tentar limpeza manual desses registros.

## 3. Pendências e riscos

### Bloqueantes para integração financeira

1. **FIN.3-C.11.12-C3 — Conta de aplicação com privilégios mínimos:** definir identidade dedicada, provisionamento seguro fora do repositório, conceder `SELECT`/`INSERT` estritamente necessários, negar `UPDATE`/`DELETE`/DDL/GRANT, testar operações permitidas e rejeitadas. Não usar `root` no fluxo operacional.
2. **Conciliar três fontes monetárias:** split do pagamento, comissões calculadas no encerramento e faturas do parceiro. Definir política comercial formal para comissão de cidade, reserva do gateway, pagamento direto e eventuais divergências. Não eleger fonte de verdade silenciosamente.
3. **Integração controlada com `PedidoTransitionService`, `ComissaoService`, `FaturaService`, `PayoutLedgerService`:** garantir mesma transação PDO, idempotência ponta a ponta, ausência de lançamentos duplicados e compatibilidade com obrigações financeiras.
4. **Prevenir efeitos externos de teste:** `FaturaService::fecharCiclo()` pode acionar geração PIX por dependência; não executar crons, webhooks ou pagamentos reais durante os testes.
5. **Imutabilidade versus manutenção:** planejar retenção, testes em banco descartável e acesso administrativo auditável. Triggers não protegem contra administrador que tenha permissão para removê-los.

### Bloqueantes para conclusão do produto

6. Homologar fluxo real de contratação, localização, matching por anéis e elegibilidade, oficina com capacidade de remoção, guincho direto, aceite, execução, conclusão e exceções.
7. Homologar estados financeiros de pagamento via plataforma e direto ao parceiro, estorno, conciliação de ciclos, dívida/inadimplência, descontos e bloqueios de atendimento.
8. Revisar segurança API: autenticação/autorização por perfil, CSRF web, idempotência de endpoints, rate limit, segredos, webhook signature, auditoria, validação de entrada.
9. Finalizar app Android: autenticação, localização/permissões, busca/aceite de serviço, sincronismo e notificações; validar contratos API e tratamento offline/conexão instável.
10. Testes de carga, restauração de backup, monitoramento, métricas, observabilidade, rollback e homologação da infraestrutura cPanel antes de go-live.

## 4. Metodologia de implementação (por ondas e gates)

1. **Inventário:** identificar arquivos proprietários do domínio, versão de schema, dependências e contratos; anotar hashes e mudanças pendentes.
2. **Contrato:** especificar invariantes e casos de teste antes de alterar código ou SQL; valores financeiros em centavos, timestamps explícitos, idempotência determinística e fail-closed.
3. **Preflight somente leitura:** verificar banco-alvo, schema, índices, engines, FKs, permissões, arquivos, SHA-256 e possíveis drifts.
4. **Staging isolado:** aplicar mudanças somente no ambiente permitido, com autorização textual explícita para DDL; nunca reaplicar automaticamente após falha parcial.
5. **QA sequencial e concorrente:** lint PHP, unitários, transações, rollback, race, crash, retries, ajustes, segurança negativa e limpeza por fixtures permitidos.
6. **Revisão e aprovação:** guardar logs PASS, ExitCode=0, hashes de arquivos, relatórios e decisão de risco residual. Marcar o escopo real comprovado.
7. **Commit com escopo explícito:** revisar `git status`/`git diff`, fazer stage seletivo, examinar segredos e binários, criar commit local descritivo. Push apenas após revisão do checkpoint remoto.
8. **Integração incremental:** integrar o fluxo operacional em pequenos lotes, com feature flags, reversibilidade, observabilidade e reexecução dos testes.
9. **Entrega:** homologação end-to-end, segurança, backup/restore, rollout gradual e critérios de rollback.

## 5. Plano imediato

| Ordem | Ação | Gate de aceite |
|---|---|---|
| 0 | Criar checkpoint local revisado | diff revisado, arquivos sensíveis excluídos, commit SHA conhecido |
| 1 | FIN.3-C.11.12-C3: conta SQL restrita em ambiente descartável | INSERT/SELECT permitidos, UPDATE/DELETE/DDL negados, PHP idempotente PASS |
| 2 | Formalizar política de comissão, gateway e fluxo pago-na-chegada | tabela de decisão assinada, testes de divergência |
| 3 | Auditoria de integração real (read-only) | mapa de chamadas/transações/efeitos externos aprovado |
| 4 | Integrar adapter/snapshot por transação sob feature flag desligada | testes integrais isolados e sem PIX PASS |
| 5 | Regressão backoffice/API/app Android | fluxos críticos e segurança PASS |
| 6 | Homologação final e release | checklist de operação, rollback e monitoramento aprovado |

## 6. Procedimento seguro de commit LOCAL

**Não execute `git add .` nem `git add -A` sem inspeção.** Execute no PowerShell 5.1:

```powershell
Set-Location 'C:\xampp\htdocs\guinchafacil'
git status --short --branch
git remote -v
git diff --stat
git diff --name-only
git ls-files --others --exclude-standard
```

Revisar a lista antes de preparar staging. Para um commit documental, copie este relatório para `doc/MARCO_TECNICO_FIN3_20261020.md` e use:

```powershell
New-Item -ItemType Directory -Force -Path 'doc' | Out-Null
# Copie manualmente o MD entregue para doc/MARCO_TECNICO_FIN3_20261020.md

git add -- 'doc/MARCO_TECNICO_FIN3_20261020.md'
git diff --cached --stat
git diff --cached --check
git diff --cached -- 'doc/MARCO_TECNICO_FIN3_20261020.md'
# Revise o conteúdo e então:
git commit -m 'docs(fin3): registrar marco de homologacao e plano de conclusao'
git log -1 --format='%h %s'
```

Para incorporar código local neste checkpoint, selecionar arquivos individualmente após auditoria de secrets/diff e testes; não colocar ZIPs de teste completos, logs nem bancos no commit. **Não fazer push automaticamente.**

## 7. Critérios de projeto concluído

O projeto somente poderá ser considerado concluído quando: (a) fluxos cliente/guincho/oficina/admin e Android forem homologados end-to-end; (b) dados financeiros estiverem conciliados, protegidos e auditáveis; (c) PIX real estiver em sandbox/produção conforme autorização e com proteção de idempotência; (d) testes de segurança, carga, recuperação e integração tiverem evidência; (e) deployment, monitoramento, backup/rollback e manuais estiverem validados.
