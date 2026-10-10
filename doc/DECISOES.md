# DECISÕES — GuinchaFácil

> Registro de decisões do projeto. Agente nenhum contradiz uma decisão daqui; se discordar, abre `BLOCKERS.md`.
> Status: **Proposta** (aguarda o dono confirmar) · **Aceita** · **Pendente** (falta decidir).
> Confirme ou ajuste o status de cada item antes de liberar os agentes.

## D0 — Regras de trabalho R1–R7

**Status:** Proposta

| # | Regra |
| --- | --- |
| R1 | **Uma faixa, um dono.** Só o dono escreve nos arquivos da sua faixa (`docs/lanes.json`). |
| R2 | **Contrato antes de código.** Mudou entrada/saída entre faixas? Edita `docs/CONTRATOS.md` primeiro; o dono aprova. |
| R3 | **Checkpoint verde.** Tag `ok-AAAAMMDD-N` só com smoke test 100% verde. Rollback: `git reset --hard <tag>`. |
| R4 | **Orçamento de investigação.** Máx. 5 arquivos e 5 comandos antes de propor correção. Falhou 2x no mesmo ponto? Escreve `docs/BLOCKERS.md` e para. |
| R5 | **Ninguém testa a própria faixa.** Matriz: A é testada pelo Gemini, B pelo ChatGPT, C pelo DeepSeek. |
| R6 | **Diff mínimo.** Sem "limpezas" fora da tarefa. Exclusão de arquivo só com autorização escrita do dono. |
| R7 | **Toda checagem de rota usa GET** (`curl -s -o /dev/null -w "%{http_code}" URL`), nunca `curl -sI`. |

## D1 — Motor único de decisão: `DecisaoAtendimentoService`

**Status:** Proposta (recomendação do protocolo; o dono confirma)

- **Contexto:** a análise apontou dois motores (Especialista e Oficina/Decisão). Verificado: `PricingService`, `TarifaService` e `EspecialistaPricingService` coexistem.
- **Decisão:** `DecisaoAtendimentoService` é o motor único.
- **Consequência:** serviços legados ficam **congelados, sem apagar**, até o admin migrar. A faixa A faz `PricingService` delegar aos cálculos de `TarifaService`/`EspecialistaPricingService` (ou o inverso), decide o sentido e documenta aqui. Resultado esperado: um único ponto de cálculo de preço, com `strict_types`.
- **Sentido da delegação:** a definir pela faixa A (registrar abaixo quando decidido).

## D2 — Falso alarme do 404 e o que mudou no processo

**Status:** Aceita (verificado no código)

- O "404 em todas as rotas" vinha de `curl -sI` (HEAD); o `index.php` só trata GET/POST.
- Consequência: regra R7. Não investigar LiteSpeed/opcache por causa desse sintoma.
- Opcional, para o dono aprovar: mapear HEAD→GET em `$metodo` no `index.php` (arquivo compartilhado).
- O 404 real de `/especialista/disponibilidade` no navegador ainda precisa de prova: comparar md5 do `index.php` de produção com o do repositório e ler o log `RTR-001`.

## D3 — Nome da pasta de documentação: `docs/` ou `doc/`

**Status:** Pendente

- O protocolo v2 verificou que a pasta real do repositório é `doc/` (já bloqueada no `.htaccess`).
- Este pacote foi gerado com `docs/`, conforme pedido.
- Opções: (a) manter `docs/` e bloquear no `.htaccess`; (b) renomear para `doc/` e atualizar `lanes.json`, `lane_guard` e os prompts. Escolher uma e registrar aqui.

## D4 — Infraestrutura de teste: estender, não recriar

**Status:** Proposta

- Já existem `qa/`, `tests/e2e`, `tests/Unit`, `tests/Integration` e as rotas `/admin/qa/run/*` e `/admin/simulador`.
- A faixa C **estende** o que existe (runner passo a passo, eventos, ingest, SVG animado) sem criar um segundo sistema.
- Migration de testes: `install/migration_testes_v1.sql`, aplicada pelo `install/migrate.php` existente.

## D5 — Qual `playwright.config` é o oficial

**Status:** Pendente

- Existem dois (raiz e `qa/`). Escolher um; o outro vira legado. Até lá, a faixa C não cria um terceiro.

## D6 — Arquivos `.bak*` e soltos na raiz

**Status:** Pendente (exige autorização escrita, R6)

- 148 `.bak*` em `src/` e `public/` (8 do `DecisaoAtendimentoService`) e arquivos soltos na raiz (`_auditoria_export.txt`, `deepKS.txt`, `result.json`, `gemini.js`, `index.php.bak-stage3-*`).
- Proposta: mover para `_archive/`, fora do deploy. Nenhum agente move ou apaga sem autorização.

## D7 — Risco do `.cpanel.yml` (tarefa C0)

**Status:** Pendente

- Antes do próximo deploy: a faixa C valida onde o `.env` de produção realmente vive e propõe um `.cpanel.yml` que não apague `.env`, não copie `tests`/`qa`/`.bak` e trate arquivos removidos.
- Até lá, não rodar deploy por cPanel Git.

## D8 — Checkpoint inicial

**Status:** Proposta

- Criar `git tag ok-baseline` no estado atual antes de liberar os agentes (hoje: 3 branches, nenhuma tag).
- Formato das próximas tags: `ok-AAAAMMDD-N`.

## D9 — Fronteira `CheckoutController` (A) × `/checkout/cliente` (B)

**Status:** **Aceita em 2026-10-08** (opção a)


- B **não edita** `CheckoutController.php` diretamente.
- Se B precisar de mudança no método `cliente()`, emite `CONTRATO_PEDIDO` para A, que executa e responde.
- **Justificativa:** manter uma única fonte de verdade; o ciclo `CONTRATO_PEDIDO` já funciona bem no projeto.


## D10 — Arquivos sem faixa

**Status:** **Aceita em 2026-10-08** — os 4 arquivos passam a ser da **Faixa B**

- `src/Models/Configuracao.php` → **B**
- `src/Services/Address/*` → **B**
- `src/Services/PreCotacao/PreCotacaoOpcoesService.php` → **B**
- `src/Services/CoberturaService.php` → **B**
- **Ações decorrentes:** `doc/lanes.json` atualizado (`_sem_faixa_definida` vazia; 4 caminhos na chave `B`). A Faixa A emite `CONTRATO_PEDIDO` para B se precisar mexer.

**Ações decorrentes:**

- `doc/lanes.json` atualizado (chave `B` inclui os 4 caminhos; `_sem_faixa_definida` ficou vazia).
- A Faixa B passa a ser responsável por mudanças nesses arquivos.
- Se a Faixa A precisar mexer em algum deles, emite `CONTRATO_PEDIDO` para B.

- **2026-10-03**  atualização com autorização do dono:
  - `src/Views/components/admin_nav_operacional.php`  **Faixa B**. Aplicado patch de menu (`/admin/pedido/novo`  `/admin/pedido/novo/v2`) nesta data.
  - `src/Views/layouts/header.php`  **compartilhado**. Ainda pendente: dropdown mobile do admin com o mesmo link antigo. Só o dono edita.
  - `src/Controllers/AdminController.php`, `src/Views/admin/pedidonovo_etapa1.php`, `src/Views/admin/pedidonovo_funil.php`, `src/Views/admin/partials/_precotacao_funil_admin.php`, `public/assets/css/pages/admin-pedido-flow.css`  **novos, atribuídos informalmente à Faixa B** (Caminho 3 do funil admin, autorizado pelo dono em 2026-10-02).

## D11 — Segurança

**Status:** Aceita

- Rotacionar segredos do Mercado Pago (client secret, webhook secret, access tokens de produção).
- Desativar `cliente1..3@teste.local` em produção ao fim dos testes.
- Nunca enviar `.env`, `node_modules` ou `.git` a IAs.

## Registro de alterações

| Data | Mudança |
| --- | --- |
| 2026-09-30 | Criação do arquivo com D0–D11 a partir do protocolo multi-agente v2 |
| 2026-10-08 | D9 Aceita (opção a); D10 Aceita (4 arquivos → B); D14 Aceita (rm --cached) |

## D14 — Artefatos de teste no git

**Status:** **Aceita em 2026-10-08**

- `playwright-report/` e `*.zip` **não** devem ser versionados.
- Ação: `git rm --cached` + `.gitignore` (commit `e6bdcae`).
- **Sem reescrever história** (decisão explícita).
- Artefatos já no histórico permanecem; novos não entram.
- Proposta: .gitignore + git rm --cached. Sem reescrever história.
- **Ação:** commit `e6bdcae` removeu `playwright-report/` do rastreamento; `.gitignore` atualizado.
- **Sem reescrever história** (decisão explícita).
| 2026-10-08 | D9 Aceita (opção a); D10 Aceita (4 arquivos → B); D14 Aceita (rm --cached) |