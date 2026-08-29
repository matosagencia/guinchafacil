import { test, expect, type Page } from '@playwright/test';
import { clienteCreds } from '../fixtures/test-data.fixture';
import { CPF_TESTE_PADRAO, MP_SCENARIOS, MP_TEST_CARDS } from '../fixtures/mercadopago-test-cards.fixture';
import { fillFirstAvailable, clickFirstAvailable, expectLoggedIn, loginAs } from '../helpers/auth';
import { confirmarWebhookMercadoPago, qaPedidoStatus } from '../helpers/seed';
import { criarPedidoViaFormulario } from '../helpers/pedido-form';
import { loginAsMpBuyer } from '../helpers/mercadopago';
import { cliqueRobustoAndes } from '../helpers/click-andes';
import { RegistroPassos } from '../helpers/step-logger';
import { mpBuyerTestUser } from '../fixtures/test-data.fixture';
import { appPath } from '../helpers/paths';

function paymentConfig() {
  return {
    pedidoId: process.env.TEST_CHECKOUT_PEDIDO_ID || process.env.TEST_PEDIDO_ID || ''
  };
}

// §PAY-SANDBOX-E2E-02: número do cartão, vencimento e código de segurança do
// checkout do MP ficam em <iframe> próprios (Secure Fields, isolados por
// PCI). A posição/quantidade total de iframes na página NÃO é estável (o MP
// injeta iframes extras de antifraude/tracking de forma assíncrona — visto
// na prática variando entre 2 e 5 durante o carregamento), então em vez de
// indexar por posição, procura em TODOS os frames da página o que contém um
// input com o placeholder exato daquele campo (valores confirmados via
// snapshot de acessibilidade do Playwright: "0000 0000 0000 0000", "MM/AA",
// "000").
async function fillInAnyFrame(page: Page, selector: string, value: string, timeoutMs = 20000): Promise<void> {
  const deadline = Date.now() + timeoutMs;
  let lastErr: unknown = null;
  while (Date.now() < deadline) {
    for (const frame of page.frames()) {
      try {
        const loc = frame.locator(selector);
        if (await loc.count()) {
          await loc.first().fill(value, { timeout: 3000 });
          return;
        }
      } catch (e) {
        lastErr = e;
      }
    }
    await page.waitForTimeout(300);
  }
  throw new Error(`Não encontrei campo "${selector}" em nenhum frame da página (timeout ${timeoutMs}ms). Último erro: ${lastErr}`);
}

// §PAY-TRANSP-E2E-04: o dropdown de parcelas do Payment Brick só existe
// (e só fica com opções de verdade, além do placeholder "Selecione uma
// opção") DEPOIS que o Brick identifica o BIN do cartão — ou seja, alguns
// instantes depois do número do cartão ser preenchido, de forma
// assíncrona (chamada própria do Brick pra API de parcelamento). Tentar
// selecionar antes disso silenciosamente falha (nenhuma opção real pra
// escolher), e SUBMETER sem selecionar parcela é provavelmente por isso
// que a API do MP recusa o pagamento — confirmado pelo usuário testando
// manualmente ("nao conseguiu selecionar as parcelas" antes do dropdown
// carregar). Mesma estratégia de fillInAnyFrame (procura em todos os
// frames, com polling), mas só considera o <select> "pronto" quando tem
// mais de 1 <option> (a primeira é sempre o placeholder desabilitado).
async function selectInAnyFrame(page: Page, selector: string, value: string, timeoutMs = 20000): Promise<boolean> {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    for (const frame of page.frames()) {
      try {
        const loc = frame.locator(selector).first();
        if (await loc.count()) {
          const opcoes = await loc.locator('option').count();
          if (opcoes > 1) {
            await loc.selectOption(value, { timeout: 3000 });
            return true;
          }
        }
      } catch {
        // frame pode não ter esse select, ou pode ter sido destruído entre
        // a checagem e o acesso — tenta o próximo frame/nova rodada.
      }
    }
    await page.waitForTimeout(300);
  }
  return false;
}

test.describe('pagamento sandbox', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = clienteCreds();
    test.skip(!email || !password, 'Credenciais de cliente não configuradas.');
    await loginAs(page, email, password);
    await expectLoggedIn(page);
  });

  test('E2E-PAY-001 | checkout sandbox renderiza provedores e formulário seguro', async ({ page }) => {
    const { pedidoId } = paymentConfig();
    test.skip(!pedidoId, 'Defina TEST_CHECKOUT_PEDIDO_ID para validar checkout sandbox.');

    await page.goto(appPath(`/pagamento/checkout/${pedidoId}`));
    await expect(page.locator('body')).toContainText(/checkout|pagamento/i);

    const mpForm = page.locator('form[action$="/pagamento/mercadopago"]').first();
    await expect(mpForm).toBeVisible();
    await expect(mpForm.locator('input[name="csrf_token"]')).toHaveValue(/.+/);
    await expect(mpForm.locator('input[name="pedido_id"]')).toHaveValue(String(pedidoId));
  });

  test('E2E-PAY-002 | páginas de resultado de pagamento permanecem acessíveis ao cliente autenticado', async ({ page }) => {
    await page.goto(appPath('/pagamento/pendente'));
    await expect(page.locator('body')).toContainText(/pagamento pendente|pendente/i);

    await page.goto(appPath('/pagamento/falha'));
    await expect(page.locator('body')).toContainText(/pagamento n.o conclu.do|falha|tentar novamente/i);
  });

  // §PAY-SANDBOX-E2E-01: cria o pedido pelo formulário real do dashboard
  // (origem/destino por endereço, geocoding de verdade — não seed direto no
  // banco), completa um checkout REAL no MercadoPago sandbox (cartão de teste
  // oficial, cenário "APRO" = aprovado) e depois reproduz o webhook que o MP
  // mandaria em produção — não dá pra receber o webhook de verdade em
  // localhost sem túnel público, já que notification_url aponta pra APP_URL
  // (produção), não pra este ambiente. Isso valida ponta a ponta, com
  // registro completo: pedido de socorro -> geocoding -> preferência criada
  // -> checkout do MP -> aprovação -> webhook -> pedido sai de
  // aguardando_pagamento -> aguardando_guincho -> pagamentos.status =
  // aprovado.
  //
  // AVISO: os seletores do formulário do MP abaixo foram escritos a partir da
  // documentação/estrutura pública do Checkout Pro, sem inspeção ao vivo da
  // página (o MP muda esse checkout com alguma frequência). Se este teste
  // falhar na etapa de preencher o cartão, rode com `--headed` ou
  // `--ui` pra ver onde o formulário real diverge e ajustar os seletores —
  // o teste tira um screenshot em cada etapa relevante pra facilitar isso.
  test('E2E-PAY-003 | pedido real (Riachuelo -> Gamboa, colisão) + checkout MercadoPago sandbox aprova pagamento (cartão APRO) e webhook dá baixa', async ({ page }, testInfo) => {
    testInfo.setTimeout(180_000);
    const registro = new RegistroPassos(testInfo, page);

    try {
      // §PAY-SANDBOX-E2E-03: loga como o usuário de teste "Comprador" no
      // domínio do MercadoPago ANTES de criar o pedido/entrar no checkout.
      // Resolve o erro "Uma das partes com as quais você está tentando
      // efetuar o pagamento é de teste." (checkout sandbox detectando que
      // comprador e vendedor são a mesma identidade quando o navegador não
      // tem sessão própria no MP). Login e sessão do nosso app (feito no
      // beforeEach) não são afetados — cookies de mercadopago.com.br e do
      // nosso domínio são independentes; navegar pra lá e voltar preserva
      // ambas as sessões no mesmo contexto do Playwright.
      const buyer = mpBuyerTestUser();
      test.skip(
        !buyer.username || !buyer.password,
        'Credenciais do comprador de teste do MercadoPago não configuradas em qa/.env.test-users.local.'
      );

      await registro.passo('Login MP como comprador de teste', () => loginAsMpBuyer(page, testInfo, registro));

      const pedidoIdOverride = paymentConfig().pedidoId;
      await registro.passo('Criar pedido / abrir checkout', async () => {
        if (pedidoIdOverride) {
          await page.goto(appPath(`/pagamento/checkout/${pedidoIdOverride}`), { waitUntil: 'domcontentloaded' });
        } else {
          await criarPedidoViaFormulario(page, {
            origem: 'Rua Riachuelo, 221, Rio de Janeiro, RJ',
            destino: 'Rua da Gamboa, 249, Rio de Janeiro, RJ',
            tipoProblema: 'colisao'
          });
          // criarPedidoViaFormulario já deixa a página em
          // /pagamento/checkout/{id} (ClienteController::pedidoCriar
          // redireciona pra lá quando pagamento antecipado está ativo).
        }
      });
      await testInfo.attach('checkout-apos-criacao.png', { body: await page.screenshot(), contentType: 'image/png' });

      const card = MP_TEST_CARDS.mastercard;
      const scenario = MP_SCENARIOS.aprovado; // titular = "APRO"
      const documento = scenario.documento || CPF_TESTE_PADRAO;

      await registro.passo('Ir pro checkout hospedado do MercadoPago', async () => {
        await clickFirstAvailable(page, [
          'form[action$="/pagamento/mercadopago"] button[type="submit"]',
          'button:has-text("Continuar com MercadoPago")'
        ]);
        // Sai do nosso domínio e cai no checkout hospedado pelo MP (produção
        // ou sandbox.mercadopago.com.br, dependendo de como o MP roteia a
        // preferência de um token de usuário de teste).
        await page.waitForURL(/mercadopago\.com/i, { timeout: 30000 });
        await page.waitForLoadState('domcontentloaded');
      });
      await testInfo.attach('mp-checkout-inicial.png', { body: await page.screenshot(), contentType: 'image/png' });

      // A página do checkout MP carrega em duas fases: primeiro um esqueleto
      // cinza de loading (skeleton, confirmado via screenshot real — blocos
      // sem texto, "main" praticamente vazio na árvore de acessibilidade), só
      // depois o conteúdo de verdade (agora mais lento por causa do login
      // como comprador logado, que busca dados salvos da conta). Verificar
      // count() uma única vez logo após domcontentloaded pega o esqueleto
      // vazio e falsea "não encontrei". Faz polling por até 20s esperando o
      // conteúdo real aparecer antes de desistir.
      const aguardarConteudoReal = async (candidatos: ReturnType<typeof page.getByText>[], timeoutMs: number) => {
        const deadline = Date.now() + timeoutMs;
        while (Date.now() < deadline) {
          for (const candidato of candidatos) {
            if (await candidato.first().count().catch(() => 0)) return true;
          }
          await page.waitForTimeout(400);
        }
        return false;
      };

      // Confirmado via error-context.md real: logado como comprador, o MP NÃO
      // mostra mais a tela genérica "Como você prefere pagar?" com um item de
      // lista "Cartão" solto — em vez disso mostra os meios de pagamento JÁ
      // SALVOS na conta de teste (radio list "payment-method": "Saldo em
      // conta" pré-selecionado/RECOMENDADO, mais cartões salvos) com botão
      // "Pagar" direto. Pra testar o cenário "APRO" com um cartão NOVO (não
      // um dos já salvos, que têm comportamento desconhecido), precisa
      // primeiro clicar em "Escolher outro meio de pagamento" pra abrir a
      // lista completa, que aí sim inclui a opção "Novo cartão". Extraída
      // como função (não só um bloco inline) porque o passo de seleção do
      // método de cartão, mais abaixo, pode precisar CHAMAR ISSO DE NOVO
      // como parte da sua própria estratégia de retry — reabrir a lista do
      // zero é uma forma segura/idempotente de sair de um estado "meio
      // clicado" que nem reload sozinho resolve.
      const abrirListaCompletaDeMetodos = async (): Promise<void> => {
        const escolherOutroMeio = page.getByText('Escolher outro meio de pagamento', { exact: false });
        if (await aguardarConteudoReal([escolherOutroMeio], 20000)) {
          await escolherOutroMeio.first().click();
          await page.waitForLoadState('domcontentloaded');
          await page.waitForTimeout(500);
        }
      };

      await registro.passo('Abrir lista completa de meios de pagamento', async () => {
        await abrirListaCompletaDeMetodos();
        await testInfo.attach('mp-checkout-metodos-salvos.png', { body: await page.screenshot(), contentType: 'image/png' });
      });

      // Confirmado via error-context.md real (comprador logado com cartões
      // salvos): a tela "Como você prefere pagar?" mostra 3 listas —
      // "balance" (Saldo em conta), "payment-methods" (cartões já salvos) e
      // "other-options", onde a opção certa pra testar um cartão NOVO é o
      // botão de nome acessível "Novo cartão Crédito ou pré-pago" (não
      // "Cartão" solto — isso só existia no fluxo de convidado, sem login).
      // Cada linha usa o mesmo padrão Andes/Fury já visto nas parcelas e no
      // login: <li cursor:pointer> com um <button> de acessibilidade
      // sobreposto por CSS que intercepta pointer events e trava o .click()
      // comum do Playwright em retry infinito. Usa o clique robusto (foco+
      // Enter -> mouse real com trajetória de hover -> force), repetido em
      // até 2 CICLOS completos — se o primeiro ciclo inteiro falhar, reabre
      // a lista de métodos do zero (idempotente: não gera pagamento nenhum,
      // só troca de tela) antes do segundo ciclo, em vez de insistir
      // indefinidamente no mesmo DOM possivelmente já corrompido por uma
      // transição incompleta.
      const numeroCartaoVisivel = async () => {
        for (const frame of page.frames()) {
          try {
            if (await frame.locator('input[placeholder="0000 0000 0000 0000"]').count()) return true;
          } catch {
            // frame pode ter sido destruído entre a checagem e o acesso — ignora.
          }
        }
        return false;
      };

      await registro.passo('Selecionar "Novo cartão"', async () => {
        const metodoCartaoCandidatos = [
          page.getByRole('button', { name: /novo cart.o/i }),
          page.locator('#new_card_row'),
          page.getByText('Novo cartão', { exact: false }),
          page.getByText('Cartão', { exact: true }),
          page.locator('text=/crédito ou pré-pago/i'),
          page.locator('[class*="payment"], li, div').filter({ hasText: /^Cartão$/ })
        ];

        // Mesma corrida de esqueleto/renderização já vista em outros pontos
        // deste fluxo: um .count() ÚNICO logo depois do clique em "Escolher
        // outro meio de pagamento" pode rodar ANTES da lista "Como você
        // prefere pagar?" (com "Novo cartão") terminar de renderizar —
        // confirmado com um error-context.md real onde a checagem falhou em
        // ~180ms mas o snapshot de diagnóstico, tirado alguns instantes
        // depois pelo próprio Playwright, já mostrava o botão "Novo cartão"
        // presente no DOM. Faz polling por até 15s procurando QUALQUER
        // candidato com count() > 0 antes de desistir, em vez de checar uma
        // vez só.
        const encontrarCandidatoDisponivel = async (timeoutMs: number) => {
          const deadline = Date.now() + timeoutMs;
          while (Date.now() < deadline) {
            for (const candidato of metodoCartaoCandidatos) {
              const alvo = candidato.first();
              if (await alvo.count().catch(() => 0)) return alvo;
            }
            await page.waitForTimeout(300);
          }
          return null;
        };

        let metodoCartaoClicado = false;
        for (let tentativa = 1; tentativa <= 2 && !metodoCartaoClicado; tentativa++) {
          const alvo = await encontrarCandidatoDisponivel(15000);
          if (!alvo) break;
          const itemPai = page.locator('li').filter({ has: alvo }).first();
          metodoCartaoClicado = await cliqueRobustoAndes(page, alvo, numeroCartaoVisivel, {
            elementoPai: itemPai,
            timeoutPorTentativaMs: 15000,
            ciclos: 2,
            antesDeRepetir: async () => {
              await page.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});
              await page.waitForTimeout(500);
              await abrirListaCompletaDeMetodos();
            }
          });
          if (!metodoCartaoClicado && tentativa < 2) {
            // Nenhum candidato reagiu ao clique robusto inteiro (2 ciclos) —
            // tenta mais uma rodada completa do zero: reabre a lista de
            // métodos e procura de novo, em vez de insistir no mesmo
            // elemento que já provou não funcionar.
            await page.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});
            await page.waitForTimeout(500);
            await abrirListaCompletaDeMetodos();
          }
        }

        if (!metodoCartaoClicado) {
          await testInfo.attach('mp-metodo-cartao-nao-encontrado.png', { body: await page.screenshot(), contentType: 'image/png' });
          throw new Error('Não encontrei/consegui clicar na opção "Novo cartão"/"Cartão" na tela "Como você prefere pagar?" do MP — veja o screenshot anexado.');
        }
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(500);
      });

      // "Preencha os dados do seu cartão": número, vencimento e código de
      // segurança ficam em <iframe> próprios (Secure Fields do MP). Busca cada
      // um pelo placeholder exato em qualquer frame da página (ver
      // fillInAnyFrame acima) em vez de depender da posição/contagem dos
      // iframes, que não é estável.
      await registro.passo('Preencher dados do cartão', async () => {
        await fillInAnyFrame(page, 'input[placeholder="0000 0000 0000 0000"]', card.numero.replace(/\s+/g, ''));
        await fillInAnyFrame(page, 'input[placeholder="MM/AA"]', card.vencimento); // campo único, não mês/ano separados
        await fillInAnyFrame(page, 'input[placeholder="000"]', card.cvv);

        // "Nome do titular" e "Documento do titular" são campos normais da
        // página (fora de iframe) — confirmado no mesmo snapshot.
        await page.getByRole('textbox', { name: 'Nome do titular' }).fill(scenario.codigo);
        await page.getByRole('textbox', { name: 'Documento do titular' }).fill(documento);
        // O combobox de tipo de documento já vem em "CPF" por padrão (visto
        // no snapshot) — não precisa trocar pra rodar os cenários desta
        // tabela.
      });
      await testInfo.attach('mp-checkout-preenchido.png', { body: await page.screenshot(), contentType: 'image/png' });

      // Este primeiro "Continuar" fecha o formulário do cartão; o MP ainda pode
      // mostrar uma etapa de parcelas/revisão antes do botão final de
      // pagamento — por isso o clique seguinte aceita tanto "Pagar" quanto
      // "Continuar" de novo.
      await registro.passo('Confirmar formulário do cartão', async () => {
        await clickFirstAvailable(page, [
          'button:has-text("Continuar")',
          'button[type="submit"]'
        ]);
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(500);
      });
      await testInfo.attach('mp-checkout-pos-continuar.png', { body: await page.screenshot(), contentType: 'image/png' });

      // Mesma corrida de renderização de sempre, um passo mais adiante: um
      // .url() checado NA HORA (mesmo depois de waitForLoadState +
      // waitForTimeout(500)) pode pegar a URL ainda antiga, antes do MP
      // navegar de fato pra "/installments/" ou pra revisão — confirmado
      // num run real onde o log mostrou "navigated to .../installments/"
      // só DEPOIS do clique em "Pagar" mais abaixo, ou seja, a navegação
      // pra parcelas aconteceu tarde e a etapa de selecionar "1x" foi
      // pulada por engano, deixando o teste preso na tela de parcelas até
      // o timeout de 60s esperando a volta pro app. Espera explicitamente
      // a URL virar "/installments/", "/review/" (ou o botão "Pagar"
      // aparecer, sinal de revisão) por até 15s antes de decidir se a
      // etapa de parcelas roda ou não.
      await registro.passo('Aguardar próxima tela (parcelas ou revisão)', async () => {
        const deadline = Date.now() + 15000;
        while (Date.now() < deadline) {
          if (page.url().includes('/installments/') || page.url().includes('/review/')) return;
          if (await page.getByRole('button', { name: /pagar/i }).count().catch(() => 0)) return;
          await page.waitForTimeout(300);
        }
      });

      // "Selecione o número de parcelas" — só aparece pra cartão de crédito.
      // Sempre escolhe a opção "1x" (sem juros). O DOM tem um <button> "fantasma"
      // (só pra acessibilidade, nome "Uma parcela de X reais...") separado da
      // área visual clicável de verdade, que é o <li> com cursor:pointer.
      // Checar sumiço do texto do heading é instável (pisca durante re-render
      // do client-side e pode falsear "avançou"); a URL é o sinal confiável —
      // essa etapa sempre fica em ".../installments/...", a próxima
      // (".../review/...") só quando a seleção realmente é aceita.
      if (page.url().includes('/installments/')) {
        await registro.passo('Selecionar parcelas (1x)', async () => {
          // Confirmado com o usuário: um clique HUMANO manual em "1x" avança
          // normalmente — ou seja, o problema é específico de como o
          // Playwright dispara a interação, não da UI/config do MP. A
          // estratégia abaixo replica o mais fielmente possível um clique
          // humano de verdade: scrollIntoView + bounding box real do
          // <li id="1x-row"> (a linha inteira, não só o botão de
          // acessibilidade) + sequência de mouse real (move → down → pausa
          // → up) via CDP, que gera eventos totalmente confiáveis
          // (isTrusted:true) nas coordenadas de tela reais.
          const linha1x = page.locator('#1x-row');
          let avancou = false;

          if (await linha1x.count().catch(() => 0)) {
            await linha1x.scrollIntoViewIfNeeded().catch(() => {});
            await page.waitForTimeout(200);
            const box = await linha1x.boundingBox();
            if (box && box.width > 0 && box.height > 0) {
              const x = box.x + box.width / 2;
              const y = box.y + box.height / 2;
              await page.mouse.move(x, y);
              await page.waitForTimeout(50);
              await page.mouse.down();
              await page.waitForTimeout(80);
              await page.mouse.up();
              await page.waitForURL((url) => !url.pathname.includes('/installments/'), { timeout: 8000 }).catch(() => {});
              avancou = !page.url().includes('/installments/');
            } else {
              await testInfo.attach('mp-parcelas-boundingbox-vazio.txt', {
                body: Buffer.from(`boundingBox() de #1x-row veio ${box ? JSON.stringify(box) : 'null'} — elemento pode estar fora da viewport ou com display:none no momento do clique.`),
                contentType: 'text/plain'
              });
            }
          }

          if (!avancou) {
            // Fallback 1: mesma estratégia de mouse real, mas mirando o botão
            // de acessibilidade (#1x-row button) em vez do <li> inteiro,
            // caso a área clicável real seja só o botão sobreposto.
            const botao1x = page.locator('#1x-row button, #1x-row [aria-labelledby="1x-row-content"]').first();
            if (await botao1x.count().catch(() => 0)) {
              await botao1x.scrollIntoViewIfNeeded().catch(() => {});
              const boxBtn = await botao1x.boundingBox();
              if (boxBtn && boxBtn.width > 0 && boxBtn.height > 0) {
                const x = boxBtn.x + boxBtn.width / 2;
                const y = boxBtn.y + boxBtn.height / 2;
                await page.mouse.move(x, y);
                await page.waitForTimeout(50);
                await page.mouse.down();
                await page.waitForTimeout(80);
                await page.mouse.up();
                await page.waitForURL((url) => !url.pathname.includes('/installments/'), { timeout: 8000 }).catch(() => {});
                avancou = !page.url().includes('/installments/');
              }
            }
          }

          if (!avancou) {
            // Fallback 2: Playwright .click() nativo (sem force) — dispara
            // eventos confiáveis via CDP como o mouse real acima, mas deixa
            // o Playwright resolver a actionability/posição sozinho.
            const candidatosParcela = [
              page.locator('#1x-row').first(),
              page.locator('#1x-row button').first(),
              page.locator('xpath=/html/body/main/div/div/div/div/section[1]/div[2]/div/ul/li[1]/button'),
              page.locator('li').filter({ hasText: /uma parcela de/i }).first(),
              page.getByRole('button', { name: /uma parcela de/i }).first(),
              page.getByText('Uma parcela de', { exact: false }).first()
            ];
            for (const candidato of candidatosParcela) {
              if (!(await candidato.count().catch(() => 0))) continue;
              await candidato.click().catch(() => {});
              await page.waitForURL((url) => !url.pathname.includes('/installments/'), { timeout: 8000 }).catch(() => {});
              if (!page.url().includes('/installments/')) {
                avancou = true;
                break;
              }
            }
          }

          if (!avancou) {
            await testInfo.attach('mp-parcelas-travado.png', { body: await page.screenshot(), contentType: 'image/png' });
            throw new Error(`Não consegui sair da tela "Selecione o número de parcelas" do MP (URL ainda: ${page.url()}) — veja o screenshot anexado.`);
          }
          await page.waitForLoadState('domcontentloaded');
          await page.waitForTimeout(500);
        });
        await testInfo.attach('mp-checkout-pos-parcelas.png', { body: await page.screenshot(), contentType: 'image/png' });
      } else {
        registro.registrar('Selecionar parcelas (1x)', true, 'Etapa pulada — não apareceu (comum quando o método salvo já define parcelamento).');
      }

      // "Revise o seu pagamento" — pede e-mail antes de liberar o botão "Pagar"
      // (sem isso o MP recusa o pagamento com "Não foi possível processar",
      // erro genérico que não tem nada a ver com o cartão/cenário de teste).
      // Usa o e-mail real do cliente de teste logado (clienteCreds()), não um
      // endereço fictício avulso.
      await registro.passo('Preencher e-mail de revisão (se solicitado)', async () => {
        const campoEmail = page.getByRole('textbox', { name: 'E-mail' }).or(page.getByPlaceholder(/nome@email\.com/i));
        if (await campoEmail.count().catch(() => 0)) {
          await campoEmail.first().fill(clienteCreds().email);
        }
      });
      await testInfo.attach('mp-checkout-revisao.png', { body: await page.screenshot(), contentType: 'image/png' });

      await registro.passo('Confirmar pagamento ("Pagar") e retornar ao app', async () => {
        await clickFirstAvailable(page, [
          'button[type="submit"]:has-text("Pagar")',
          'button:has-text("Pagar")',
          'button:has-text("Continuar")',
          'button[type="submit"]'
        ]);
        // Volta pro nosso domínio via back_urls (success/failure/pending) com
        // payment_id/collection_id na query string.
        await page.waitForURL(new RegExp(appPath('/pagamento/(sucesso|falha|pendente)').replace(/[/]/g, '\\/')), { timeout: 60000 });
      });
      await testInfo.attach('retorno-app.png', { body: await page.screenshot(), contentType: 'image/png' });

      const paymentId = await registro.passo('Extrair payment_id do retorno', async () => {
        const retorno = new URL(page.url());
        const id = retorno.searchParams.get('payment_id') || retorno.searchParams.get('collection_id') || '';
        expect(id, `payment_id não veio na URL de retorno: ${page.url()}`).not.toBe('');
        return id;
      });

      const webhook = await registro.passo('Confirmar webhook MercadoPago (pedido -> aguardando_guincho, pagamento -> aprovado)', async () => {
        const resultado = confirmarWebhookMercadoPago(paymentId);
        expect(resultado.ok, `Falha ao confirmar webhook: ${JSON.stringify(resultado)}`).toBe(true);
        expect(resultado.aprovado, `Pedido não avançou para aguardando_guincho: ${JSON.stringify(resultado)}`).toBe(true);
        expect(resultado.pedido_status).toBe('aguardando_guincho');
        expect(resultado.pagamento_status).toBe('aprovado');
        return resultado;
      });

      await testInfo.attach('webhook-resultado.json', {
        body: JSON.stringify(webhook, null, 2),
        contentType: 'application/json'
      });
    } finally {
      // Sempre anexa o relatório completo de passos (sucesso E falha de
      // CADA etapa, não só a que quebrou) — é o que dá pra colar aqui no
      // chat direto do terminal, sem precisar abrir screenshot nenhum, pra
      // saber exatamente até onde o fluxo chegou.
      await registro.finalizar();
    }
  });

  // §PAY-TRANSP-E2E-01: cobre o checkout transparente novo (Payment Brick
  // embutido em /pagamento/checkout/{id} — ver src/Views/pagamento/checkout.php
  // e PagamentoController::mercadoPagoTransparente()). Diferente do
  // E2E-PAY-003 acima (Checkout Pro, redirect pro domínio do MP, precisa de
  // login como comprador de teste), aqui o comprador NUNCA sai do nosso
  // domínio — a cobrança vai direto pra POST /v1/payments via
  // MercadoPagoProvider::criarPagamento(), autenticada só pelo access token
  // do lado servidor. Por isso não há necessidade de loginAsMpBuyer(): o
  // Brick client-side só usa a MP_PUBLIC_KEY (chave pública, sem sessão de
  // conta) para tokenizar/coletar os dados nos secure-fields.
  //
  // Os campos de cartão (número/validade/CVV) e também nome/documento do
  // titular renderizam dentro de <iframe>s isolados do domínio
  // secure-fields.mercadopago.com (confirmado via screenshot real — cada
  // campo mostra um ícone de cadeado). Reusa a mesma estratégia de
  // "procurar em todos os frames" (fillInAnyFrame) já validada no
  // E2E-PAY-003 acima, ao invés de indexar por posição.
  test('E2E-PAY-004 | checkout transparente MercadoPago (Payment Brick) aprova pagamento sem sair da página (cartão APRO)', async ({ page }, testInfo) => {
    // 120s ficou apertado depois de somar o passo de selecionar "Cartão de
    // crédito" (Brick às vezes abre a lista fechada, precisa de clique +
    // espera extra) — 180s dá folga sem mascarar uma falha real. Subiu pra
    // 240s depois de somar os 50s de espera visual na tela de sucesso no
    // fim do teste (--headed).
    testInfo.setTimeout(240_000);
    const registro = new RegistroPassos(testInfo, page);

    try {
      const pedidoIdOverride = paymentConfig().pedidoId;
      const pedidoId = await registro.passo('Criar pedido / abrir checkout transparente', async () => {
        if (pedidoIdOverride) {
          await page.goto(appPath(`/pagamento/checkout/${pedidoIdOverride}`), { waitUntil: 'domcontentloaded' });
          return pedidoIdOverride;
        }
        const id = await criarPedidoViaFormulario(page, {
          origem: 'Rua Riachuelo, 221, Rio de Janeiro, RJ',
          destino: 'Rua da Gamboa, 249, Rio de Janeiro, RJ',
          tipoProblema: 'colisao'
        });
        return String(id);
      });
      await testInfo.attach('brick-checkout-aberto.png', { body: await page.screenshot(), contentType: 'image/png' });

      const card = MP_TEST_CARDS.mastercard;
      const scenario = MP_SCENARIOS.aprovado; // titular = "APRO"
      const documento = scenario.documento || CPF_TESTE_PADRAO;

      // §PAY-TRANSP-E2E-02: o Brick carrega de forma assíncrona (SDK externo
      // + chamadas pro backend do MP pra montar os campos) — o container
      // #mp-payment-brick-container começa vazio e só ganha o formulário
      // depois de alguns segundos. fillInAnyFrame já faz polling; o wait
      // aqui é só uma garantia extra de que o campo de número do cartão
      // (sinal mais confiável de "Brick pronto") existe antes de medir
      // tempo de preenchimento nos passos seguintes.
      await registro.passo('Aguardar Payment Brick renderizar', async () => {
        await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 30000).catch(() => {
          // fill com valor vazio só serve pra confirmar que o campo existe/é
          // acionável; um erro aqui (campo nunca apareceu) é reportado como
          // falha explícita no passo seguinte, que tenta preencher de vez.
        });
        // §PAY-TRANSP-E2E-05: onError do Brick (checkout.php) escreve "Não
        // foi possível carregar o formulário..." no #checkout-mensagem — se
        // isso já apareceu aqui, os campos que o passo seguinte "encontrar"
        // são resquício de um Brick anterior/quebrado (visto num run real:
        // preenchimento completou em 1.3s, rápido demais pra ser real, e o
        // pagamento nunca foi enviado). Falha cedo com mensagem clara em
        // vez de deixar os passos seguintes preencherem campos mortos.
        const mensagemErro = page.locator('#checkout-mensagem:not(.d-none)');
        if (await mensagemErro.count().catch(() => 0)) {
          const texto = await mensagemErro.first().innerText().catch(() => '');
          if (/n.o p.de ser carregad|n.o foi poss.vel carregar/i.test(texto)) {
            throw new Error(`Payment Brick sinalizou erro de carregamento antes mesmo de preencher o cartão: "${texto}". Recarregue a página e tente de novo — não é um bug de automação.`);
          }
        }
      });

      // §PAY-TRANSP-E2E-06: o Brick nem sempre abre com "Cartão de
      // crédito" já expandido — em runs anteriores vinha pré-selecionado
      // (campos visíveis de cara), mas num run real ele voltou mostrando só
      // a lista de "Meios de pagamento" com os radios fechados, e o teste
      // travou esperando o campo de número do cartão que nunca aparecia
      // sem esse clique primeiro. Idempotente: se já estiver expandido
      // (campo já visível), o clique no radio não atrapalha.
      await registro.passo('Selecionar "Cartão de crédito" na lista de meios de pagamento', async () => {
        const jaExpandido = await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 1500).then(() => true).catch(() => false);
        if (jaExpandido) return;

        const candidatos = [
          page.getByRole('radio', { name: /cart.o de cr.dito/i }),
          page.locator('label').filter({ hasText: /^Cart.o de cr.dito/i }),
          page.getByText('Cartão de crédito', { exact: false })
        ];
        let clicado = false;
        for (const candidato of candidatos) {
          const alvo = candidato.first();
          if (await alvo.count().catch(() => 0)) {
            await alvo.click({ timeout: 5000 }).catch(() => {});
            clicado = true;
            break;
          }
        }
        if (!clicado) {
          throw new Error('Não encontrei a opção "Cartão de crédito" na lista de meios de pagamento do Brick.');
        }
        // Depois do clique, espera o campo de número do cartão aparecer de
        // verdade antes de seguir pro próximo passo.
        await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 15000);
      });
      await testInfo.attach('brick-metodo-selecionado.png', { body: await page.screenshot(), contentType: 'image/png' });

      await registro.passo('Preencher dados do cartão no Brick', async () => {
        await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', card.numero.replace(/\s+/g, ''), 30000);
        await fillInAnyFrame(page, 'input[placeholder="mm/aa"]', card.vencimento, 15000);
        await fillInAnyFrame(page, 'input[placeholder="Ex.: 123"]', card.cvv, 15000);
        await fillInAnyFrame(page, 'input[placeholder="Maria Santos Pereira"]', scenario.codigo, 15000);
        await fillInAnyFrame(page, 'input[placeholder="999.999.999-99"]', documento, 15000);
      });
      await testInfo.attach('brick-cartao-preenchido.png', { body: await page.screenshot(), contentType: 'image/png' });

      // §PAY-TRANSP-E2E-04 (ver comentário de selectInAnyFrame acima): só
      // depois do número do cartão preenchido o Brick identifica o BIN e
      // popula o <select> de parcelas — antes disso ele só tem o
      // placeholder "Selecione uma opção". Seleciona "1" (à vista) assim
      // que as opções reais aparecerem. Cartões de débito (ex: elo_debito)
      // não têm esse dropdown — por isso não falha o teste se ele nunca
      // aparecer, só registra que pulou.
      const parcelaSelecionada = await registro.passo('Aguardar e selecionar parcelamento (1x)', async () => {
        return selectInAnyFrame(page, 'select:has(option[aria-label*="À Vista"])', '1', 20000);
      });
      if (!parcelaSelecionada) {
        registro.registrar('Aguardar e selecionar parcelamento (1x)', true, 'Dropdown de parcelas não apareceu — ok para métodos sem parcelamento (ex: débito).');
      }
      await testInfo.attach('brick-parcelas-selecionadas.png', { body: await page.screenshot(), contentType: 'image/png' });

      await registro.passo('Preencher e-mail do comprador', async () => {
        const campoEmail = page.getByPlaceholder(/exemplo@email\.com/i)
          .or(page.getByRole('textbox', { name: /e-?mail/i }));
        if (await campoEmail.count().catch(() => 0)) {
          await campoEmail.first().fill(clienteCreds().email);
        }
      });

      // §PAY-TRANSP-E2E-03: quando o pagamento NÃO é aprovado,
      // tratarRespostaPagamento() (checkout.php) só mostra um alerta na
      // própria página — não navega pra lugar nenhum, por desenho (o Brick
      // fica pronto pra nova tentativa). Quando É aprovado, a mesma função
      // dispara window.location.href pro /pagamento/sucesso/{id} quase no
      // mesmo instante em que o fetch resolve — tentar ler o corpo da
      // resposta de rede via CDP depois disso (resposta.json()/.text())
      // é uma corrida perdida contra a navegação: o Chrome descarta o
      // recurso de rede assim que a página começa a navegar, e o teste
      // falha com "Protocol error (Network.getResponseBody): No resource
      // with given identifier found" mesmo com o pagamento aprovado no
      // banco (confirmado repetidas vezes nos logs do servidor). Em vez de
      // brigar com timing de rede, observa o resultado real como um usuário
      // veria: ou a navegação de sucesso acontece, ou o valor gravado em
      // window.__qaUltimaRespostaPagamento (setado por
      // tratarRespostaPagamento() antes de qualquer redirect — ver
      // checkout.php) mostra sucesso:false primeiro.
      const respostaPagamento = await registro.passo('Confirmar pagamento ("Pagar") e capturar resultado', async () => {
        await clickFirstAvailable(page, [
          'button:has-text("Pagar")',
          'button[type="submit"]:has-text("Pagar")'
        ]);

        const resultado = await Promise.race([
          page.waitForURL(/\/pagamento\/sucesso\//, { timeout: 60000 })
            .then(() => ({ sucesso: true, status: 'aprovado' } as { sucesso?: boolean; status?: string; erro?: string; detalhe?: unknown })),
          page.waitForFunction(
            () => {
              const r = (window as unknown as { __qaUltimaRespostaPagamento?: { sucesso?: boolean } }).__qaUltimaRespostaPagamento;
              return !!r && r.sucesso === false;
            },
            { timeout: 60000 }
          ).then(async () => {
            return page.evaluate(() => (window as unknown as {
              __qaUltimaRespostaPagamento?: { sucesso?: boolean; status?: string; erro?: string; detalhe?: unknown };
            }).__qaUltimaRespostaPagamento) as Promise<{ sucesso?: boolean; status?: string; erro?: string; detalhe?: unknown }>;
          }),
        ]);
        return resultado;
      });

      await testInfo.attach('brick-resposta-pagar.json', {
        body: JSON.stringify(respostaPagamento, null, 2),
        contentType: 'application/json'
      });

      if (!respostaPagamento.sucesso || respostaPagamento.status !== 'aprovado') {
        await testInfo.attach('brick-erro-tela.png', { body: await page.screenshot(), contentType: 'image/png' });
        throw new Error(
          `Pagamento não foi aprovado — resposta do backend: ${JSON.stringify(respostaPagamento)}. ` +
          'Isso reflete o que a API do MercadoPago recusou (ver campo "erro"/"detalhe"), não um bug de automação.'
        );
      }

      // Pagamento aprovado: agora sim espera a navegação (window.location.href
      // pra /pagamento/sucesso/{id}) que tratarRespostaPagamento() dispara.
      await registro.passo('Aguardar redirecionamento para /pagamento/sucesso/', async () => {
        await page.waitForURL(/\/pagamento\/sucesso\//, { timeout: 15000 });
      });
      await testInfo.attach('brick-retorno-app.png', { body: await page.screenshot(), contentType: 'image/png' });

      expect(page.url(), `Esperava terminar em /pagamento/sucesso/, mas ficou em: ${page.url()}`).toMatch(/\/pagamento\/sucesso\//);

      const statusFinal = await registro.passo('Confirmar estado do pedido/pagamento direto no banco', async () => {
        const resultado = qaPedidoStatus(pedidoId);
        expect(resultado.ok, `Falha ao consultar status do pedido: ${JSON.stringify(resultado)}`).toBe(true);
        expect(resultado.status).toBe('aguardando_guincho');
        expect(resultado.pagamento_status).toBe('aprovado');
        return resultado;
      });

      await testInfo.attach('brick-status-final.json', {
        body: JSON.stringify(statusFinal, null, 2),
        contentType: 'application/json'
      });

      // Segura a tela de sucesso (animação/logo/aguarde o guincho) aberta
      // por mais tempo em vez de fechar o navegador assim que a asserção
      // passa — só pra dar tempo de olhar visualmente em modo --headed.
      await registro.passo('Aguardar 50s na tela de sucesso (visual)', async () => {
        await page.waitForTimeout(50000);
      });
    } finally {
      await registro.finalizar();
    }
  });
});
