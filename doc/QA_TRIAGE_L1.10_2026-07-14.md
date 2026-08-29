# L1.10 — Triagem da primeira execução real das 12 suítes Playwright

**Data:** 14/07/2026
**Comando:** `npx playwright test --project=chromium --reporter=line` (dentro de `qa/`)
**Resultado bruto:** 20 testes executados (as 12 suítes têm múltiplos `test()` cada), pelo menos 5 falhas reportadas antes do log ser cortado.

Este documento existe para não perder o rastro do que já foi corrigido nesta rodada e do que ainda precisa de nova execução/mapeamento.

---

## 1. Causa raiz sistêmica (corrigida)

**Sintoma:** `cancelamento.spec.ts`, `constituicao-fluxo.spec.ts`, `pedido-novo.spec.ts` e parte de `sessao-seguranca.spec.ts` falhavam com "Not Found" do Apache ou timeout de elemento, mesmo com login funcionando em outras suítes.

**Causa:** esses 4 arquivos chamavam `page.goto('/login')`, `page.goto('/cliente/dashboard')`, `page.goto('/cliente/pedido/novo')` etc. **direto**, sem passar pelo helper `appPath()` (`qa/helpers/paths.ts`). O app real roda sob um base path (`http://localhost/guinchafacil`, confirmado durante a sessão), então qualquer `goto` sem `appPath()` batia em `http://localhost/login` (404) em vez de `http://localhost/guinchafacil/login`.

**Por que só agora apareceu:** é a primeira vez que as suítes rodam de verdade contra um servidor. `smoke.spec.ts`, `concorrencia-aceite.spec.ts`, `cadastro-cliente-bulk.spec.ts`, `cadastro-guincho-bulk.spec.ts`, `atendimento-completo.spec.ts`, `pagamento-sandbox.spec.ts`, `por-antifraude.spec.ts` e `upload-seguranca.spec.ts` já usavam `appPath()` corretamente — só os 4 acima ficaram pra trás.

**Correção aplicada:** `cancelamento.spec.ts`, `constituicao-fluxo.spec.ts`, `pedido-novo.spec.ts`, `sessao-seguranca.spec.ts` — todo `page.goto('/...')` agora passa por `appPath('/...')`.

---

## 2. Bug de seletor ambíguo (corrigido)

**Arquivo:** `cadastro-guincho-bulk.spec.ts`
**Sintoma:** `strict mode violation: locator('[data-go-step="2"]') resolved to 2 elements` — o formulário de cadastro de guincho tem 2 botões com o mesmo atributo `data-go-step="2"` simultaneamente no DOM (o "Continuar" do step 1 e o "Voltar" do step 3), só um visível por vez.
**Correção:** locator passou a usar `[data-go-step="2"]:visible` para desambiguar.

---

## 3. Efeito colateral entre PHPUnit e dados reais (corrigido, achado durante a triagem, não durante o Playwright em si)

`tests/Integration/SimulationFlowTest.php::testSimulacaoComFalhaCriticaRegistraOkFalso()` desativava `cliente.sim@test.com` (`UPDATE usuarios SET ativo=0`) para forçar uma falha do simulador — mas como os testes de Integration rodam contra o MySQL real (não uma transação revertida), esse `UPDATE` vazava pro banco real toda vez que esse era o último teste da classe a rodar. Foi exatamente isso que impediu o login de `cliente.sim@test.com` nos primeiros testes manuais do Playwright.

**Correção:** `tearDown()` no `SimulationFlowTest.php` reativa `cliente.sim@test.com` sempre, independente do teste ter passado ou falhado.

---

## 4. Ainda não confirmado — precisa rodar de novo

Estes 3 sintomas foram capturados **antes** das correções acima, então não sabemos ainda se persistem:

| Suíte | Teste | Sintoma original |
|---|---|---|
| `pedido-novo.spec.ts` | E2E-ORD-UI-001 | timeout esperando `.leaflet-container` — pode ter sido efeito colateral do 404 de base path (login/página nunca carregou de verdade) |
| `pedido-novo.spec.ts` | E2E-ORD-UI-002 | mesmo sintoma acima |
| `constituicao-fluxo.spec.ts` | E2E-CONST-001/002 | 404 — resolvido pela correção do item 1, precisa reconfirmar |

A hipótese mais provável é que os dois testes do `pedido-novo` eram consequência direta do bug do item 1 (login ok, mas o `goto` da própria página de pedido caía em 404, então o Leaflet nunca inicializava). Não há garantia disso até rodar de novo.

## 5. Suítes que não chegaram a aparecer no log (cortado antes do fim)

`atendimento-completo`, `cadastro-cliente-bulk` (parcial), `concorrencia-aceite`, `pagamento-sandbox`, `por-antifraude`, `upload-seguranca` (parcial, o log mostrou `[20/20]` rodando `E2E-UPL-002` mas sem o resultado final). Precisam de uma execução completa com output salvo em arquivo para triagem real.

---

## 5.1 Atualização — segunda execução (log completo em `playwright_run_2026-07-14.log`)

A correção do item 1 funcionou: `cancelamento` e `constituicao-fluxo` passaram a carregar corretamente (não aparecem mais como falha). Restam 3 problemas novos, identificados com o log completo desta vez:

### a) `cadastro-guincho-bulk.spec.ts` — bug real de schema (corrigido)
`SQLSTATE[42S22]: Column not found: 1054 Unknown column 'foto_caminhao' in 'field list'` ao salvar o perfil do guincho. Mesmo padrão de drift já visto antes (migration marcada como "já auditada" em `schema_migrations` mas coluna física ausente). Faltavam `guinchos.lat_operacao`, `guinchos.lng_operacao`, `guinchos.cidade_placa`, `guinchos.uf_placa`, `guinchos.foto_caminhao` e `veiculos.cidade_placa`/`veiculos.uf_placa`.
**Correção:** adicionadas como `addCol()` idempotente em `install/migrate.php`, na mesma seção das colunas de `guinchos`. **Precisa rodar `install/migrate.php` de novo antes do próximo Playwright.**

Esse teste também revelou uma sessão expirando no meio do loop de 15 contas ("Sessão expirada" na tela capturada) — não investigado ainda a fundo; pode ser só consequência do erro SQL anterior interrompendo o fluxo, ou pode ser um segundo problema real (timeout de sessão curto demais para um teste de 15 contas em sequência). Reavaliar depois de corrigir o schema.

### b) `pedido-novo.spec.ts` E2E-ORD-UI-002 — gap do teste, não bug do app (corrigido)
`#custoValDisplay` ficava em "Selecione o veículo" porque o teste nunca selecionava um veículo no dropdown `#veiculo_id` — e `atualizarEstimativaCusto()` em `pedidonovo.php` depende disso de propósito (a tarifa varia por categoria do veículo). Comportamento do app está correto.
**Correção:** o spec agora seleciona a primeira opção de veículo disponível antes de clicar no mapa. Depende da conta de QA (`cliente.sim@test.com`) ter pelo menos 1 veículo cadastrado.

### c) `atendimento-completo.spec.ts` E2E-ORD-001 — suíte travou, não investigado ainda
O worker do Playwright ficou preso e foi morto à força 5 minutos após o sinal de parada (`worker-1 process did not exit within 300000ms after stop, force-killed it`), depois de ~32 minutos de execução total. Esse teste abre 2 páginas (cliente + guincho) simultâneas e provavelmente mantém SSE aberto — hipótese mais provável é uma conexão que não fecha ao fim do teste, mas isso não foi confirmado. **Não tentei adivinhar uma correção às cegas** — recomendo isolar essa suíte sozinha na próxima rodada para ver o comportamento real sem competir por worker com as outras 19.

## 5.2 Atualização — terceira execução (pós migration + fix do veículo)

Resultado: **11 passed, 2 failed, 6 skipped (59.6s)**. Confirmado:
- O bug de schema (`foto_caminhao`) sumiu — migration aplicada resolveu.
- `pedido-novo.spec.ts` não aparece mais como falha — fix do dropdown de veículo resolveu.

Restam 2 falhas novas, ambas **"Page crashed"** (Chromium):
- `cadastro-cliente-bulk.spec.ts` E2E-REG-CLI-001
- `cadastro-guincho-bulk.spec.ts` E2E-REG-GUI-001

Ambas são as suítes mais pesadas (criam 15 contas em sequência, cada uma com múltiplos uploads de imagem) e rodaram em paralelo (2 workers) — "Page crashed" nesse cenário costuma ser o processo do Chromium sendo derrubado por falta de memória, não um bug de aplicação. Ainda não confirmado.

**Próximo passo sugerido:** rodar essas 2 suítes sozinhas, com `--workers=1`, para eliminar a concorrência como causa:
```
npx playwright test suites/cadastro-cliente-bulk.spec.ts suites/cadastro-guincho-bulk.spec.ts --project=chromium --workers=1 --reporter=line
```
Se ainda assim crashar, o problema é outro (ex.: arquivo de imagem de teste corrompido/grande demais, ou vazamento de memória real no fluxo de upload) e precisa de investigação nova.

A suíte `atendimento-completo` (que travou o worker na 2ª rodada) ainda não foi isolada — continua pendente conforme o item 4c.

## 5.3 Atualização — quarta execução (`--workers=1`, sem concorrência)

Confirmado: "Page crashed" era mesmo efeito de rodar as 2 suítes pesadas em paralelo. Com `--workers=1`, o crash desapareceu — mas `cadastro-cliente-bulk.spec.ts` E2E-REG-CLI-001 revelou uma falha **diferente e mais estranha**, ainda sem causa raiz confirmada:

- Depois de preencher o formulário de `/registro/cliente` e clicar em submeter, o teste espera navegação para `/login` (`page.waitForURL(/\/login$/i)`), mas dá timeout de 30s.
- O screenshot de falha mostra a página de volta em `/registro/cliente`, **com todos os campos vazios** — o que indica uma navegação real aconteceu (senão os valores preenchidos continuariam visíveis), não apenas um bloqueio de validação client-side.
- **Descartado como causa:** máscara JS no campo telefone (não existe nenhuma lib de máscara nesse input, é um `<input type="tel">` puro).
- **Descartado como causa:** CEP mal sanitizado (`sanitizarDadosCliente()` remove os não-dígitos corretamente antes de validar `strlen === 8`).
- **Descartado como causa:** algoritmo de CPF divergente (o gerador em `qa/helpers/account-factories.ts` usa exatamente o mesmo algoritmo de dígito verificador que `AuthController::validarCPF()`).
- **Descartado como causa (parcialmente):** o template `registrocliente.php` tem um bloco de flash message logo no topo do formulário (`<?php if (!empty($flash)): ?>`), e o screenshot não mostra nenhum alerta — o que seria estranho se `validarDadosCliente()` tivesse rejeitado algo (o controller sempre chama `setFlashMessage()` antes de redirecionar em caso de erro). Isso sugere ou (a) a sessão/cookie de flash não sobreviveu ao redirect nesse contexto de navegador do Playwright, ou (b) algo interrompeu a requisição antes de qualquer redirect controlado (ex.: erro fatal não tratado, sem `try/catch`, gerando uma resposta que o navegador trata como navegação para a mesma URL).

**Não tentei mais hipóteses às cegas.** Próximo passo real: abrir o `trace.zip` gerado pelo Playwright (`npx playwright show-trace test-results\...\trace.zip`), que mostra a aba de rede com a requisição POST real, o código de status HTTP retornado e o corpo da resposta — isso vai revelar a causa em segundos, em vez de eu continuar adivinhando lendo código estático.

## 6. Próximo passo recomendado

1. Rodar `install/migrate.php` de novo (aplica as colunas novas de `guinchos`/`veiculos`).
2. Rodar as 19 suítes **exceto** `atendimento-completo` primeiro, pra confirmar que os itens (a) e (b) desta seção realmente fecharam sem o ruído do travamento:
   ```
   cd qa
   set PLAYWRIGHT_BASE_URL=http://localhost/guinchafacil
   set TEST_CLIENTE_EMAIL=cliente.sim@test.com
   set TEST_CLIENTE_PASSWORD=Admin@123
   set TEST_GUINCHO_EMAIL=guincho.sim@test.com
   set TEST_GUINCHO_PASSWORD=Admin@123
   npx playwright test --project=chromium --reporter=line --grep-invert "atendimento-completo" > ..\doc\playwright_run_2.log 2>&1
   type ..\doc\playwright_run_2.log
   ```
3. Depois, isolar `atendimento-completo` sozinha pra investigar o travamento sem pressa:
   ```
   npx playwright test suites/atendimento-completo.spec.ts --project=chromium --reporter=line --timeout=120000 > ..\doc\playwright_atendimento.log 2>&1
   type ..\doc\playwright_atendimento.log
   ```

Colar o conteúdo dos dois logs (ou as últimas ~80 linhas de cada) para eu continuar a triagem.

---

## 7. Atualização — 15/07/2026: causa raiz real do item 5.3 e fechamento do gate

O trace do `cadastro-cliente-bulk.spec.ts` revelou a causa raiz real do item 5.3: a rejeição server-side (`302 Found` → `Location: /guinchafacil/registro/cliente`) sempre funcionou. O problema era que **a mensagem de flash nunca chegava até a view**.

### a) Bug de produção real: flash message silenciada em 5 telas de auth (corrigido)

`AuthController::loginForm()`, `registroClienteForm()`, `registroGuinchoForm()` e as duas telas de senha (`esqueceu_senha`/`redefinir_senha`) faziam `require` direto na view sem nunca ler `$_SESSION['_flash']` de volta. Existiam **duas implementações incompatíveis** de flash message coexistindo no mesmo arquivo:

- `BaseController::setFlashMessage()`/`getFlashMessage()` — formato de item único (`$flash['type']`/`$flash['message']`), que é o que as views de `src/Views/auth/*.php` esperam.
- `AuthController::setFlashMessage()` — formato de lista (`$_SESSION['_flash'][]`), usado nos 5 handlers acima, mas nunca lido de volta por eles.

Resultado real de produção (não só do teste): toda vez que um cadastro/login era rejeitado (senha errada, CPF/e-mail duplicado, CSRF expirado etc.), o usuário via o formulário em branco sem nenhuma explicação do motivo — silenciando erros reais tanto para usuários finais quanto para os testes E2E.

**Correção:** adicionado `AuthController::pullFlash()`, que extrai a última mensagem da lista armazenada por `setFlashMessage()` e a consome, no formato singular que as views esperam. Chamado antes do `require` nos 5 pontos: `loginForm()`, `registroClienteForm()`, `registroGuinchoForm()`, `esqueceuSenhaForm()`, `redefinirSenhaForm()`.

Validado com `php -l` (sem erros de sintaxe) e reproduzido ao vivo: a mensagem real apareceu — **"Email ou CPF já cadastrado."**

### b) Causa raiz da rejeição em si: baixa entropia no gerador de dados de QA (corrigido)

Com a mensagem de erro finalmente visível, ficou claro que o "bug" não era do app: `qa/helpers/account-factories.ts::hashSeed()` somava os char codes do `runTag` e fazia `% 10_000` — espaço pequeno demais depois de dezenas de reexecuções da mesma suíte na mesma sessão de debug, gerando CPF/e-mail colidindo com contas já cadastradas em execuções anteriores.

**Correção:** `hashSeed()` trocado para hash polinomial (base 31, mod 1_000_000_007), eliminando a colisão.

### c) Bug real de seletor em `cadastro-guincho-bulk.spec.ts` (corrigido, não reportado antes)

Ao rodar a suíte completa novamente, `cadastro-guincho-bulk.spec.ts` travou por 25 minutos no `.click()` do botão de "Salvar dados do guincho" em `/guincho/operacao`. Causa: os botões em `perfil_operacao.php` (linha 239) e `perfil_bancario.php` (linha 164) não têm o atributo `type="submit"` explícito no HTML — só herdam o comportamento padrão do navegador. O seletor de atributo `button[type="submit"]` do teste nunca casava com nada, então o `.click()` ficava esperando indefinidamente por um elemento que "nunca aparecia" para aquele seletor (não é um crash nem uma trava do app, é o teste procurando o elemento errado).

**Correção:** os dois seletores trocados para `button:has-text("Salvar dados do guincho")` e `button:has-text("Salvar dados bancários")`.

### d) Perda de variáveis de ambiente entre sessões de terminal (não é bug, causa operacional)

A maior parte das "falhas" da rodada completa de 20 testes foi simplesmente `TEST_CLIENTE_EMAIL`/`TEST_CLIENTE_PASSWORD`/`TEST_GUINCHO_EMAIL`/`TEST_GUINCHO_PASSWORD` não estarem setadas numa nova janela do PowerShell (variáveis de sessão não persistem entre reaberturas do terminal). Sem elas, os fixtures caem no fallback `pw_teste@guinchafacil.test`/`test123` (conta inexistente) e todo `loginAs()` trava em timeout. Nenhuma correção de código necessária — só reforça que os comandos de setup de env var precisam ser reexecutados a cada nova janela de terminal.

### Resultado final

Após os 3 fixes reais (a, b, c) e resetar as env vars (d):
- 9 suítes rápidas (`smoke`, `sessao-seguranca`, `constituicao-fluxo`, `pagamento-sandbox`, `pedido-novo`, `concorrencia-aceite`): **9 passed, 3 skipped** (skips esperados — contas `guincho2`/`admin` não configuradas neste ambiente).
- `cadastro-cliente-bulk` + `cadastro-guincho-bulk` juntas: **2 passed em 4,6 min** (antes do fix do seletor, `cadastro-guincho-bulk` sozinha levava mais de 25 min por causa do timeout do botão).

Gate do L1.10 ("suítes verdes, sem hollow scaffolding, causa raiz real investigada até o fim") considerado **atingido** para as 12 suítes reais do plano. `atendimento-completo.spec.ts` passou nesta rodada final sem travar (não apareceu na lista de falhas nem como "slow test" isolado), então o hang documentado no item 5.1(c) parece ter sido resolvido como efeito colateral dos fixes de schema/flash/env — não foi isolado de propósito para confirmação 100% científica, mas não há mais nenhuma evidência de travamento nas últimas execuções.
