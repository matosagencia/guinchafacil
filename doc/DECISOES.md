# DECISÕES — GuinchaFácil

> Registro de decisões do projeto. Agente nenhum contradiz uma decisão daqui; se discordar, abre `BLOCKERS.md`.
> Status: **Proposta** (aguarda o dono confirmar) · **Aceita** · **Pendente** (falta decidir).
> Confirme ou ajuste o status de cada item antes de liberar os agentes.

## D0 — Regras de trabalho R1–R7

**Status:** Proposta

| # | Regra |
| --- | --- |
| R1 | **Uma faixa, um dono.** Só o dono escreve nos arquivos da sua faixa (`doc/lanes.json`). |
| R2 | **Contrato antes de código.** Mudou entrada/saída entre faixas? Edita `doc/CONTRATOS.md` primeiro; o dono aprova. |
| R3 | **Checkpoint verde.** Tag `ok-AAAAMMDD-N` só com smoke test 100% verde. Rollback: `git reset --hard <tag>`. |
| R4 | **Orçamento de investigação.** Máx. 5 arquivos e 5 comandos antes de propor correção. Falhou 2x no mesmo ponto? Escreve `doc/BLOCKERS.md` e para. |
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

## D3 — Nome da pasta de documentação: `doc/`

**Status:** Aceita (`doc/`), conforme `doc/lanes.json` (`_notas`) e o `.htaccess`. **Confirme.**

- A pasta real do repositório é `doc/` (já bloqueada no `.htaccess`).
- O pacote original foi gerado com `docs/`; todas as referências passam a `doc/`.

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

**Ações decorrentes:**

- `doc/lanes.json` atualizado (`_sem_faixa_definida` vazia; 4 caminhos na chave `B`).
- A Faixa B passa a ser responsável por mudanças nesses arquivos.
- Se a Faixa A precisar mexer em algum deles, emite `CONTRATO_PEDIDO` para B.

**Atualização de 2026-10-03, com autorização do dono:**

- `src/Views/components/admin_nav_operacional.php` → **Faixa B**. Aplicado patch de menu (`/admin/pedido/novo` → `/admin/pedido/novo/v2`) nesta data.
- `src/Views/layouts/header.php` → **compartilhado**. Ainda pendente: dropdown mobile do admin com o mesmo link antigo. Só o dono edita.
- `src/Controllers/AdminController.php`, `src/Views/admin/pedidonovo_etapa1.php`, `src/Views/admin/pedidonovo_funil.php`, `src/Views/admin/partials/_precotacao_funil_admin.php`, `public/assets/css/pages/admin-pedido-flow.css` → **novos, atribuídos informalmente à Faixa B** (Caminho 3 do funil admin, autorizado pelo dono em 2026-10-02).

## D11 — Segurança

**Status:** Aceita

- Rotacionar segredos do Mercado Pago (client secret, webhook secret, access tokens de produção).
- Desativar `cliente1..3@teste.local` em produção ao fim dos testes.
- Nunca enviar `.env`, `node_modules` ou `.git` a IAs.

## D14 — Artefatos de teste no git

**Status:** **Aceita em 2026-10-08**

- `playwright-report/` e `*.zip` **não** devem ser versionados.
- **Ação:** `git rm --cached` + `.gitignore` (commit `e6bdcae`).
- **Sem reescrever história** (decisão explícita). Artefatos já no histórico permanecem; novos não entram.

## D15 — Fator de Relevo na Fórmula de Reboque

**Status:** Aceita; **parcialmente substituída por D19** (2026-10-10).

- A fórmula travada do reboque passa a aceitar o multiplicador de inclinação.
- Fórmula: `REBOQUE_FINAL = (taxa_fixa + (tarifa_por_km × distancia)) × fator_relevo`.
- O `fator_relevo` baseia-se exclusivamente no valor absoluto da inclinação em graus (`abs(inclinacao_graus)`).
- ~~A altitude é processada e armazenada apenas para fins de diagnóstico.~~ **Revogado por D19:** a altitude entra no cálculo.

## D16 — Módulo de Vulnerabilidade, Auditoria e Máquina de Estados

**Status:** Aceita

- Fica criada a regra de prioridade operacional e vulnerabilidade humana.
- **Regra de inicialização:** o estado de prioridade na criação do pedido obedece estritamente: `prioridade_solicitada = false` resulta em `nao_solicitada`; `prioridade_solicitada = true` resulta em `pendente_confirmacao`.
- **Regra financeira e operacional:** a contestação pelo parceiro no check-in (alegando inconsistência na vulnerabilidade) **não gera débito automático** no cartão do cliente. Ela altera o status para `divergente` / `pendente_revisao` para análise humana. O tipo `local_isolado` restringe-se estritamente ao dispatch operacional, não abrindo fluxo de revisão de vulnerabilidade humana.
- **Máquina de estados:** o ciclo de vida do status (`pedidos.prioridade_status`) segue a transição por etapas (`nao_solicitada`, `pendente_confirmacao`, `confirmada`, `divergente`, `pendente_revisao`, `cancelada`), sendo obrigatório registrar cada transição em `pedido_eventos` com ator, data, status anterior, status novo e motivo.

## D17 — Propriedade do Funil Público (/pre-cotacao) e Rota de Check-in

**Status:** Aceita

- **Faixa B:** dona absoluta das Views (`src/Views/public/pre-cotacao.php`, `_precotacao_funil.php`) e da rota de check-in operacional (`src/Controllers/GuinchoController.php` via `GuinchoController::confirmarPrioridade`).
- **Faixa A:** dona do motor, APIs, persistência, regras de negócio e manipulação de DOM atrelada a negócio via JS (`public/assets/js/public-pre-cotacao-flow.js`, `PrioridadeService`, `RelevoService`).

## D18 — Alocação das Views de Parceiros Públicos

**Status:** Aceita

- `src/Views/public/parceiros/*` pertence à **Faixa A**, operando em conjunto com a controladora e serviços correspondentes.

## D19 — Relevo = elevação + inclinação (substitui parte de D15)

**Status:** Aceita pelo dono em 2026-10-10 (decisões 1 a 3); réguas e composição **pendentes**. Contrato: `relevo_v2` (proposto).

- **A altitude entra no cálculo.** Subir 750 m até o Alto da Boa Vista é custo, mesmo que a rua no alto seja plana.
- **Dois fatores independentes:** **elevação** (desnível do trajeto) vale para guincho e assistência; **inclinação** da via vale só para o guincho.
- **Guincho conta os dois trechos:** guincho até o cliente e cliente até o destino, com o carro rebocado.
- **Descida pesa menos que subida:** coeficiente de descida menor que 1, configurável no admin.
- **Comissão:** segue a divisão atual, percentual da plataforma sobre o **valor total**, incluindo adicionais. O canhoto do parceiro mostra o detalhamento.
- **Em aberto:** descida da assistência; composição dos fatores; faixas da régua de elevação; teto dos dois fatores.

## D20 — Prioridade gratuita para vulnerabilidade e penalidade em degraus

**Status:** Aceita pelo dono em 2026-10-10 (decisão 4 confirmada: "concordo totalmente").

- Cliente com criança a bordo (e demais tipos de vulnerabilidade humana de D16) recebe prioridade **sem cobrança adicional**.
- Se o parceiro constatar que a condição declarada não existia, aplica-se penalidade em degraus **após revisão humana**: 1ª ocorrência = aviso e perda da prioridade gratuita naquele atendimento; reincidência = suspensão temporária com contestação.
- Isso adapta a ideia original de bloquear permanentemente para um bloqueio proporcional.
- Salvaguardas: regra e penalidade visíveis **antes** da pergunta; uso do botão de divergência por parceiro é auditado; validar a redação com advogado (CDC, art. 51) antes de ir ao ar.
- **Em aberto:** "sozinho em local deserto" também ganha prioridade gratuita, com a mesma penalidade? Hoje `local_isolado` é só despacho (D16).

## D21 — Serviço de altitude: OpenTopoData autohospedado em VPS

**Status:** Proposta (recomendação; o dono está inclinado a adotar).

- **Recomendação:** OpenTopoData em VPS própria (dados SRTM; roda com ou sem Docker). A API pública (1 chamada/s, 1.000 chamadas/dia) não sustenta cotações reais.
- O cPanel de produção não roda Docker nem processos longos; a VPS hospeda só o serviço de altitude.
- **Arquitetura:** cache no banco -> VPS (HTTPS, chave de API, timeout curto) -> tabela de zonas como fallback, com log. A cotação nunca trava.
- **Mapeamento de ruas:** tabela `via_segmentos` pré-calculada para Rio e Niterói (OSM + altitude), com inclinação **com sinal**. Reduz a dependência da API em tempo de execução, mas não elimina o modelo de elevação na carga e na expansão para novas cidades.
- **Antes de contratar:** verificar o plano de R$ 49 (KVM, root, ≥1 GB RAM, snapshot/backup, reputação e reembolso). **Não verificado.**

## Registro de alterações

| Data | Mudança |
| --- | --- |
| 2026-09-30 | Criação do arquivo com D0–D11 a partir do protocolo multi-agente v2 |
| 2026-10-08 | D9 Aceita (opção a); D10 Aceita (4 arquivos → B); D14 Aceita (rm --cached) |
| 2026-10-10 | D19 (elevação + inclinação, substitui parte de D15), D20 (penalidade em degraus), D21 (VPS/OpenTopoData); D3 passa a `doc/` |
