# Contexto de retomada — Codex / GuinchaFácil

Data: 2026-08-12

## Objetivo atual

Continuar a auditoria técnica e implementar os itens 3 e 4 do plano final:

3. ampliar testes de concorrência, rollback, deadlock e falhas intermediárias;
4. refatorar incrementalmente `AdminController` e `GuinchoController`, sempre
   preservando comportamento e adicionando testes de regressão.

## Conclusões confirmadas com o Gemini

- O SSE não está morto. Está integrado em `index.php`,
  `src/Controllers/SseController.php` e nas telas de cliente, guincho e admin.
  Existem autenticação, autorização, heartbeat, encerramento, reconexão e
  fallback para polling. Riscos remanescentes: buffering/SAPI e testes reais
  de reconexão.
- `termos-servico.php` e `politica-privacidade.php` não possuem placeholders
  impeditivos; usam valores parametrizados por `config.php`.
- `public/assets/js/app.js` já possui `MaskUtils` funcional para CPF e telefone.
  Riscos secundários: elementos inseridos dinamicamente e sanitização defensiva
  no backend.
- Os crons possuem implementação funcional, catálogo, heartbeat, métricas e
  painel de saúde. A ativação no Agendador do Windows é etapa operacional de
  deploy. A documentação foi adicionada em `tools/README_CRONS.md`.
- Os fluxos centrais usam transações, rollback, `FOR UPDATE` e idempotência.
  Não há evidência de inviabilidade para produção; faltam testes adicionais de
  concorrência/deadlock em MySQL real.
- `src/Services/TarifaService.php` já existe e centraliza a precificação.
  Não criar outro serviço.
- Controladores grandes representam dívida de manutenção e risco de regressão,
  mas não exigem rewrite. A estratégia correta é extração incremental.
- Muitas migrações são risco preventivo de governança, sem falha reproduzível
  de instalação limpa comprovada.

## Trabalho já realizado

- Gemini CLI instalado globalmente: `@google/gemini-cli@0.55.1`.
- Modelo usado na confrontação final: `gemini-3.1-flash-lite`.
- `tools/README_CRONS.md` adicionado.
- Sintaxe PHP validada em rotas, documentos, SSE e crons.
- Sintaxe de `public/assets/js/app.js` validada.
- Baseline executado com PHP do XAMPP: `PedidoTransitionServiceTest.php`
  passou com 4 testes e 20 asserções.
- O conjunto de testes existente já cobre idempotência, corrida de
  cancelamento, rollback, POR, evidências e consistência financeira.

## Próxima implementação planejada

1. Extrair `AdminController::health()` para `AdminHealthController`.
2. Alterar a rota `/admin/health` em `index.php` para a nova classe.
3. Manter uma ponte de compatibilidade em `AdminController::health()`.
4. Adicionar teste de existência da ação extraída.
5. Adicionar teste explícito de falha de transição sem efeitos parciais.
6. Executar PHPUnit direcionado e validar sintaxe.
7. Depois escolher outra fatia pequena de `AdminController` ou
   `GuinchoController` com teste de regressão.

## Bloqueio do ambiente

O `apply_patch` falha intermitentemente ao ler arquivos existentes com:

`orchestrator_helper_launch_failed: codex-windows-sandbox-setup.exe ... program not found`

O executável existe em releases locais do Codex, mas não pode ser executado
manualmente: ele espera argumentos internos codificados pelo runtime. Arquivos
novos às vezes são criados, mas patches em `index.php` e controllers falham.

Não deixar extrações parciais. Qualquer classe criada sem alteração da rota deve
ser removida antes de encerrar a sessão.

## AtualizaÃ§Ã£o de retomada - auditoria Pix e mapas

- O fluxo de repasse Pix apÃ³s a conclusÃ£o estÃ¡ implementado em
  `PaymentJobService`, `PixPayoutWorker` e `tools/cron_reprocessar_pix.php`.
  HÃ¡ idempotÃªncia, retries, dead-letter, reabertura manual, guards financeiros
  e cÃ¡lculo do split. Os testes de fila, dead-letter, guards Pix e split passaram.
- O gateway Mercado Pago estÃ¡ configurado no ambiente (`MP_ACCESS_TOKEN` e
  `MP_WEBHOOK_SECRET` presentes). Nenhuma transferÃªncia real foi executada.
- O pagamento local `1163` pertence ao pedido `1550`: aprovado, `status_pix=pendente`,
  `pago_guincho=0` e sem `id_transacao_pix`. O pedido estÃ¡ `aguardando_guincho`
  e sem `guincho_id`, portanto ainda nÃ£o Ã© elegÃ­vel ao repasse.
- Foi criada a tarefa `GuinchoFacil\\Reprocessar Pix`, habilitada, repetindo a cada
  5 minutos, executando `C:\\xampp\\php\\php.exe C:\\xampp\\htdocs\\guinchafacil\\tools\\cron_reprocessar_pix.php`.
  Worker e script passaram na validaÃ§Ã£o de sintaxe.
- A tarefa estÃ¡ vinculada ao usuÃ¡rio `sores` em modo “somente quando conectado”.
  AlterÃ¡-la para `SYSTEM` retornou “Acesso negado”. PrÃ³ximo passo operacional:
  abrir PowerShell como administrador e executar:
  `schtasks /Change /TN "GuinchoFacil\\Reprocessar Pix" /RU SYSTEM /RL HIGHEST`
  e confirmar com `schtasks /Query /TN "GuinchoFacil\\Reprocessar Pix" /FO LIST /V`.
- CorreÃ§Ãµes anteriores de mapas: rotas OSRM/fallback em dashboard, central e
  Metas & TerritÃ³rio; marcadores customizados; destino incluÃ­do no JSON do
  dashboard; e caminho dos Ã­cones Leaflet corrigido. Sintaxe PHP/JS validada.

## Como retomar

- Reiniciar/atualizar o runtime do Codex pela aplicação.
- Abrir o projeto `C:\xampp\htdocs\guinchafacil`.
- Ler este arquivo antes de agir.
- Reaplicar a extração de `AdminHealthController` de forma atômica.
- Não executar crons reais durante testes; eles podem alterar pedidos,
  pagamentos ou dados de retenção.
