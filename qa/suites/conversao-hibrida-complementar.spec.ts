import { test, expect, type Page, type TestInfo } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import {
  rjEspecialistaApproachRoute,
  rjDeliveryRoute,
  rjExpectedStreetPattern,
  moveTowAlongRouteRealTime,
  resolveEvidenceImage,
  submitStatusWithOptionalImage
} from '../helpers/atendimento';
import { loginAs, clickFirstAvailable } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import {
  seedConversaoHibridaSetup,
  seedConversaoHibridaAtribuirHibrido,
  seedConversaoHibridaSuspenderCapacidadeReboque,
  seedConversaoHibridaReaprovarCapacidadeReboque,
  pagamentoIdExterno,
  confirmarWebhookMercadoPago,
  qaPedidoStatus,
  qaGuinchoStatus
} from '../helpers/seed';
import { CPF_TESTE_PADRAO, MP_SCENARIOS, MP_TEST_CARDS } from '../fixtures/mercadopago-test-cards.fixture';
import { RegistroPassos } from '../helpers/step-logger';

// E2E-HIBRIDO-001/002 — prestador HÍBRIDO (já nasce com ProviderCapability
// aprovada em ELECTRICAL_DIAGNOSIS + TOW_CAR): atende a pane elétrica no
// local, diagnostica REQUER_REBOQUE, e — diferente de E2E-SOCORRO-001, cujo
// especialista NÃO tem reboque aprovado e por isso perde o pedido de volta
// pra fila — aqui ConversionService::finalizarCaminhoHibrido mantém o MESMO
// guincho_id vinculado e cobra um complementar de reboque sem reabrir
// matching algum (ver §HIBRIDO-COMPLEMENTAR-01 em
// install/migration_hibrido_complementar_v1.sql). Cobre exatamente os
// controles pedidos na revisão de 27/07/2026: pagamento duplicado/crédito
// reaplicado impossível (arquivamento + lock), aprovação idempotente
// (webhook antigo ignorado, webhook novo repetido sem duplicar valores),
// revalidação de capacidade/disponibilidade/vínculo no momento do
// pagamento (downgrade quando o prestador perde aptidão de reboque antes de
// pagar), e liberação correta da reserva do prestador quando o cliente
// cancela enquanto aguarda o complementar.
test.use({ video: 'on' });

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
  throw new Error(`Não encontrei campo "${selector}" em nenhum frame (timeout ${timeoutMs}ms). Último erro: ${lastErr}`);
}

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
        // frame pode não ter esse select — tenta o próximo.
      }
    }
    await page.waitForTimeout(300);
  }
  return false;
}

/**
 * Checkout transparente (Payment Brick) real, do zero até a navegação de
 * sucesso — mesma lógica de E2E-PAY-004/E2E-SOCORRO-001, reaproveitada aqui
 * porque este cenário também precisa rodar o Brick DUAS vezes (socorro
 * inicial + reboque complementar híbrido).
 */
async function pagarComBrickReal(page: Page, testInfo: TestInfo, registro: RegistroPassos, rotulo: string): Promise<void> {
  const card = MP_TEST_CARDS.mastercard;
  const scenario = MP_SCENARIOS.aprovado;
  const documento = scenario.documento || CPF_TESTE_PADRAO;

  await registro.passo(`[${rotulo}] Aguardar Payment Brick renderizar`, async () => {
    await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 30000).catch(() => {});
    const mensagemErro = page.locator('#checkout-mensagem:not(.d-none)');
    if (await mensagemErro.count().catch(() => 0)) {
      const texto = await mensagemErro.first().innerText().catch(() => '');
      if (/n.o p.de ser carregad|n.o foi poss.vel carregar/i.test(texto)) {
        throw new Error(`Payment Brick sinalizou erro de carregamento: "${texto}".`);
      }
    }
  });

  await registro.passo(`[${rotulo}] Selecionar "Cartão de crédito"`, async () => {
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
    if (!clicado) throw new Error('Não encontrei "Cartão de crédito" na lista de meios de pagamento do Brick.');
    await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 15000);
  });

  await registro.passo(`[${rotulo}] Preencher dados do cartão`, async () => {
    await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', card.numero.replace(/\s+/g, ''), 30000);
    await fillInAnyFrame(page, 'input[placeholder="mm/aa"]', card.vencimento, 15000);
    await fillInAnyFrame(page, 'input[placeholder="Ex.: 123"]', card.cvv, 15000);
    await fillInAnyFrame(page, 'input[placeholder="Maria Santos Pereira"]', scenario.codigo, 15000);
    await fillInAnyFrame(page, 'input[placeholder="999.999.999-99"]', documento, 15000);
  });

  const parcelaSelecionada = await registro.passo(`[${rotulo}] Selecionar parcelamento (1x)`, async () => {
    return selectInAnyFrame(page, 'select:has(option[aria-label*="À Vista"])', '1', 20000);
  });
  if (!parcelaSelecionada) {
    registro.registrar(`[${rotulo}] Selecionar parcelamento (1x)`, true, 'Dropdown não apareceu — ok para métodos sem parcelamento.');
  }

  await registro.passo(`[${rotulo}] Preencher e-mail do comprador`, async () => {
    const campoEmail = page.getByPlaceholder(/exemplo@email\.com/i).or(page.getByRole('textbox', { name: /e-?mail/i }));
    if (await campoEmail.count().catch(() => 0)) {
      const { clienteCreds } = await import('../fixtures/test-data.fixture');
      await campoEmail.first().fill(clienteCreds().email);
    }
  });

  const respostaPagamento = await registro.passo(`[${rotulo}] Confirmar pagamento ("Pagar")`, async () => {
    await clickFirstAvailable(page, ['button:has-text("Pagar")', 'button[type="submit"]:has-text("Pagar")']);
    return Promise.race([
      page.waitForURL(/\/pagamento\/sucesso\//, { timeout: 60000 }).then(() => ({ sucesso: true, status: 'aprovado' } as any)),
      page.waitForFunction(() => {
        const r = (window as any).__qaUltimaRespostaPagamento;
        return !!r && r.sucesso === false;
      }, { timeout: 60000 }).then(() => page.evaluate(() => (window as any).__qaUltimaRespostaPagamento))
    ]);
  });

  await testInfo.attach(`brick-resposta-${rotulo}.json`, {
    body: JSON.stringify(respostaPagamento, null, 2),
    contentType: 'application/json'
  });

  if (!respostaPagamento.sucesso || respostaPagamento.status !== 'aprovado') {
    throw new Error(`[${rotulo}] Pagamento não foi aprovado: ${JSON.stringify(respostaPagamento)}`);
  }

  await registro.passo(`[${rotulo}] Aguardar redirecionamento para /pagamento/sucesso/`, async () => {
    await page.waitForURL(/\/pagamento\/sucesso\//, { timeout: 15000 });
  });
}

/**
 * Cliente aprova a conversão (destino real + override das coordenadas com o
 * ponto exato de rjDeliveryRoute, mesmo motivo/comentário de
 * §DETERMINISMO-TESTE em atendimento-socorro-conversao.spec.ts — o geocode
 * real do Nominatim pode ser plausível mas não idêntico ao ponto amostrado
 * via OSRM, e a chegada ao destino depende de geofence).
 */
async function aprovarConversaoComDestino(clientePage: Page): Promise<void> {
  await clientePage.reload({ waitUntil: 'domcontentloaded' });
  const destinoInput = clientePage.locator('#conversaoDestinoInput');
  await expect(destinoInput, 'formulário de conversão (destino) não apareceu — verifique conversao_reboque_pendente').toBeVisible({ timeout: 20000 });
  await destinoInput.fill('Avenida Ayrton Senna, 2200, Barra da Tijuca, Rio de Janeiro - RJ');
  await clientePage.locator('#btnBuscarDestinoConversao').click();
  await expect(clientePage.locator('#conversaoDestinoFeedback')).toContainText(/encontrado/i, { timeout: 15000 });

  const destinoExato = rjDeliveryRoute[rjDeliveryRoute.length - 1];
  await clientePage.locator('#conversaoDestinoLat').evaluate((el: HTMLInputElement, v: number) => { el.value = String(v); }, destinoExato.lat);
  await clientePage.locator('#conversaoDestinoLng').evaluate((el: HTMLInputElement, v: number) => { el.value = String(v); }, destinoExato.lng);

  await expect(clientePage.locator('#btnAprovarConversao')).toBeEnabled({ timeout: 5000 });
  await clientePage.locator('#btnAprovarConversao').click();
}

test.describe('conversão híbrida com cobrança complementar (prestador mantém o pedido)', () => {
  test('E2E-HIBRIDO-001 | prestador híbrido diagnostica REQUER_REBOQUE, mantém o pedido, cobra complementar e conclui o reboque com webhooks idempotentes', async ({ browser }, testInfo) => {
    // Dois pagamentos reais (Brick) + diagnóstico + 2 chamadas de webhook
    // deliberadamente repetidas (antigo arquivado + novo já processado) +
    // 1 perna de GPS real (entrega) — folga generosa, mesmo espírito dos
    // demais E2E de conversão.
    testInfo.setTimeout(18 * 60_000);

    const seeded = seedConversaoHibridaSetup();
    expect(seeded.ok, 'seed E2E-HIBRIDO-001 (setup) falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const clienteContext = await browser.newContext();
    const clientePage = await clienteContext.newPage();
    const registroCliente = new RegistroPassos(testInfo, clientePage);

    try {
      // ── Fase 1: pagamento real do socorro inicial (R$ 89,90) ──────────
      await loginAs(clientePage, seeded.cliente_email, 'test123');
      await clientePage.goto(appPath(seeded.checkout_url), { waitUntil: 'domcontentloaded' });
      await pagarComBrickReal(clientePage, testInfo, registroCliente, 'socorro-inicial');

      // Captura o payment_id do pagamento ORIGINAL antes que a conversão o
      // arquive — o checkout transparente aprova de forma síncrona no
      // servidor e nunca devolve o idExterno pro cliente, então este é o
      // único jeito de recuperá-lo depois para o teste de webhook antigo.
      const idsAntesDaConversao = await test.step('Capturar payment_id do socorro inicial (antes do arquivamento)', async () => {
        const r = pagamentoIdExterno(pedidoId);
        expect(r.ok, `pagamentoIdExterno falhou: ${JSON.stringify(r)}`).toBeTruthy();
        expect(r.vivo_status).toBe('aprovado');
        expect(r.vivo_payment_id_numerico, 'payment_id do socorro inicial não veio').toBeTruthy();
        return r;
      });
      const idExternoOriginal = String(idsAntesDaConversao.vivo_payment_id_numerico);

      // ── Fase 2: prestador híbrido assume o pedido (fila bypassed) ─────
      const hibridoAtribuido = await test.step('Seed: atribuir prestador híbrido (fila bypassed)', async () => {
        const r = seedConversaoHibridaAtribuirHibrido(pedidoId);
        expect(r.ok, `atribuir-hibrido falhou: ${JSON.stringify(r)}`).toBeTruthy();
        expect(r.status).toBe('a_caminho');
        expect(r.guincho_id).toBe(seeded.hibrido_guincho_id);
        return r;
      });
      void hibridoAtribuido;

      const hibridoContext = await browser.newContext({
        geolocation: { latitude: rjEspecialistaApproachRoute[0].lat, longitude: rjEspecialistaApproachRoute[0].lng },
        permissions: ['geolocation']
      });
      const hibridoPage = await hibridoContext.newPage();

      try {
        await loginAs(hibridoPage, seeded.hibrido_email, 'test123');
        await Promise.all([
          clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
          hibridoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' })
        ]);
        await expect(clientePage.locator('#statusBannerCliente')).toContainText(/a caminho/i);
        await expect(hibridoPage.locator('#badgeStatusLabel')).toContainText(/a caminho/i);

        // ── Fase 3: aproximação real (1km) até o local da pane ──────────
        await moveTowAlongRouteRealTime(hibridoPage, pedidoId, rjEspecialistaApproachRoute.slice(1), 12_000);
        await expect(clientePage.locator('#rotaRuaAtual')).toContainText(rjExpectedStreetPattern, { timeout: 20000 });

        await submitStatusWithOptionalImage(hibridoPage);
        await expect(hibridoPage.locator('input[name="foto_chegada"]')).toBeVisible({ timeout: 20000 });
        await expect(clientePage.locator('#statusBannerCliente')).toContainText(/no local/i, { timeout: 20000 });

        await test.step('Diagnóstico: iniciar (foto de chegada real)', async () => {
          const nonceResponse = await hibridoPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const nonceData = await nonceResponse.json();
          if (!nonceResponse.ok() || !nonceData.ok) {
            throw new Error(`evidencia-nonce falhou: ${JSON.stringify(nonceData)}`);
          }
          const csrfToken = await hibridoPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const imagePath = resolveEvidenceImage();
          const imageFile = readFileSync(imagePath);
          const resp = await hibridoPage.request.post(appPath(`/guincho/diagnostico/iniciar/${pedidoId}`), {
            multipart: {
              csrf_token: csrfToken,
              evidence_token: nonceData.evidence_token as string,
              foto_chegada: { name: path.basename(imagePath), mimeType: 'image/jpeg', buffer: imageFile }
            }
          });
          if (!resp.ok()) throw new Error(`diagnostico/iniciar falhou ${resp.status()}: ${await resp.text()}`);
        });

        await hibridoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });
        await expect(hibridoPage.locator('#badgeStatusLabel')).toContainText(/diagn.stico/i, { timeout: 20000 });

        await test.step('Diagnóstico: concluir com REQUER_REBOQUE', async () => {
          const csrfToken = await hibridoPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const resp = await hibridoPage.request.post(appPath(`/guincho/diagnostico/concluir/${pedidoId}`), {
            form: {
              csrf_token: csrfToken,
              resultado: 'REQUER_REBOQUE',
              descricao: 'Falha elétrica não reparável no local — necessário reboque até oficina especializada (cenário híbrido: mesmo prestador segue com o pedido).'
            }
          });
          if (!resp.ok()) throw new Error(`diagnostico/concluir falhou ${resp.status()}: ${await resp.text()}`);
        });

        await expect(clientePage.locator('#statusBannerCliente')).toContainText(/reboque/i, { timeout: 20000 });

        // ── Fase 4: cliente aprova a conversão (destino real) ───────────
        // Dispara o arquivamento do pagamento original + crédito de
        // conversão + cobrança complementar — MANTENDO o mesmo guincho_id
        // (caminho híbrido, ver ConversionService::finalizarCaminhoHibrido).
        await aprovarConversaoComDestino(clientePage);

        const statusPosConversao = await test.step('Confirmar status pós-conversão (aguardando_pagamento_reboque_hibrido, MESMO guincho_id)', async () => {
          const r = qaPedidoStatus(pedidoId);
          expect(r.ok, `qaPedidoStatus falhou: ${JSON.stringify(r)}`).toBeTruthy();
          expect(r.status).toBe('aguardando_pagamento_reboque_hibrido');
          expect(r.guincho_id, 'guincho_id deveria continuar o MESMO do caminho híbrido (sem reabrir matching)').toBe(seeded.hibrido_guincho_id);
          return r;
        });
        void statusPosConversao;

        await clientePage.waitForURL(/\/pagamento\/checkout\//, { timeout: 20000 });

        // ── Fase 5: segundo pagamento real (complementar de reboque) ────
        await pagarComBrickReal(clientePage, testInfo, registroCliente, 'reboque-complementar');

        const statusPosComplementar = await test.step('Confirmar status pós-complementar (preparacao_veiculo, MESMO guincho_id)', async () => {
          const r = qaPedidoStatus(pedidoId);
          expect(r.ok).toBeTruthy();
          expect(r.status).toBe('preparacao_veiculo');
          expect(r.guincho_id).toBe(seeded.hibrido_guincho_id);
          expect(r.pagamento_status).toBe('aprovado');
          return r;
        });
        void statusPosComplementar;

        const idsDepoisDoComplementar = await test.step('Capturar payment_id do complementar (para o teste de webhook repetido)', async () => {
          const r = pagamentoIdExterno(pedidoId);
          expect(r.ok).toBeTruthy();
          expect(r.vivo_status).toBe('aprovado');
          expect(r.vivo_payment_id_numerico, 'payment_id do complementar não veio').toBeTruthy();
          // O arquivamento precisa ter preservado o pagamento ORIGINAL —
          // não pode ter sumido nem sido sobrescrito pelo complementar.
          expect(r.arquivado_payment_id_numerico).toBe(idExternoOriginal);
          expect(r.arquivado_status).toBe('aprovado');
          // E o vivo (complementar) precisa ser um payment_id DIFERENTE do
          // original — prova de que é uma cobrança nova de verdade, não a
          // mesma linha reaproveitada com o dado antigo.
          expect(r.vivo_payment_id_numerico).not.toBe(idExternoOriginal);
          return r;
        });
        const idExternoComplementar = String(idsDepoisDoComplementar.vivo_payment_id_numerico);

        // ── Fase 6: webhook ANTIGO (pagamento arquivado) repetido ───────
        // §WEBHOOK-ARQUIVADO-01: um webhook atrasado do pagamento ORIGINAL
        // chegando SÓ AGORA (depois que ele já foi arquivado e o
        // complementar já foi aprovado) precisa ser ignorado sem tocar em
        // NADA do pagamento complementar vivo.
        const webhookAntigoRepetido = await test.step('Repetir webhook do pagamento ORIGINAL (já arquivado) — deve ser ignorado sem alterar o complementar', async () => {
          const resultado = confirmarWebhookMercadoPago(idExternoOriginal);
          // O script de simulação de webhook procura o payment_id na tabela
          // VIVA de pagamentos — como o id_externo original foi limpo da
          // linha viva no momento do arquivamento (ver
          // Pagamento::arquivarParaCobrancaComplementar), ele legitimamente
          // não encontra mais nada ali. Isso É a prova de que o
          // arquivamento funcionou (o dado antigo não está mais na linha
          // viva) — não indica falha do webhook em si.
          expect(resultado.ok, `esperava ok=false (nada de vivo com esse id_externo antigo): ${JSON.stringify(resultado)}`).toBe(false);
          expect(String(resultado.erro || '')).toMatch(/nenhum pagamento/i);
          return resultado;
        });
        void webhookAntigoRepetido;

        const statusPosWebhookAntigo = qaPedidoStatus(pedidoId);
        expect(statusPosWebhookAntigo.ok).toBeTruthy();
        expect(statusPosWebhookAntigo.status, 'webhook antigo não pode ter alterado o status do pedido').toBe('preparacao_veiculo');
        expect(statusPosWebhookAntigo.guincho_id).toBe(seeded.hibrido_guincho_id);

        const idsPosWebhookAntigo = pagamentoIdExterno(pedidoId);
        expect(idsPosWebhookAntigo.vivo_payment_id_numerico, 'webhook antigo não pode ter sobrescrito o id_externo do complementar vivo').toBe(idExternoComplementar);
        expect(idsPosWebhookAntigo.arquivado_payment_id_numerico, 'pagamento original precisa continuar arquivado, intacto').toBe(idExternoOriginal);

        // ── Fase 7: webhook NOVO (complementar) repetido — idempotência ─
        const webhookNovoTentativa1 = await test.step('Repetir webhook do complementar — 1ª chamada pós-aprovação síncrona', async () => {
          const resultado = confirmarWebhookMercadoPago(idExternoComplementar);
          // O gate de status de PagamentoAprovacaoService::aprovar() só
          // aceita 'aguardando_pagamento'/'aguardando_pagamento_reboque_hibrido'
          // — como o pedido já avançou pra 'preparacao_veiculo' (aprovação
          // síncrona do Brick já processou tudo), o webhook é rejeitado
          // pelo gate ANTES de qualquer duplicação de crédito/repasse. O
          // que importa provar aqui é que os VALORES não mudam entre
          // chamadas repetidas — ver asserção abaixo.
          return resultado;
        });
        const webhookNovoTentativa2 = await test.step('Repetir webhook do complementar — 2ª chamada (idempotência)', async () => {
          return confirmarWebhookMercadoPago(idExternoComplementar);
        });

        expect(webhookNovoTentativa1.valor_guincho).toBe(webhookNovoTentativa2.valor_guincho);
        expect(webhookNovoTentativa1.valor_plataforma).toBe(webhookNovoTentativa2.valor_plataforma);
        expect(webhookNovoTentativa1.pedido_status).toBe(webhookNovoTentativa2.pedido_status);
        expect(webhookNovoTentativa1.pagamento_status).toBe('aprovado');
        expect(webhookNovoTentativa2.pagamento_status).toBe('aprovado');

        await testInfo.attach('hibrido-001-webhooks.json', {
          body: JSON.stringify({
            webhook_antigo_repetido: webhookAntigoRepetido,
            webhook_novo_tentativa_1: webhookNovoTentativa1,
            webhook_novo_tentativa_2: webhookNovoTentativa2
          }, null, 2),
          contentType: 'application/json'
        });

        // ── Fase 8: mesmo prestador conclui o reboque (sem reatribuição) ─
        await hibridoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });
        await expect(hibridoPage.locator('#badgeStatusLabel')).toContainText(/prepara.*ve.culo|prepara..o/i, { timeout: 20000 });

        await test.step('Iniciar reboque (foto de coleta, mesmo prestador)', async () => {
          const formPreparacao = hibridoPage.locator('form[action*="preparacao/concluir"]');
          await expect(formPreparacao, 'formulário de preparação (iniciar reboque) não apareceu').toBeVisible({ timeout: 20000 });
          const csrfToken = await formPreparacao.locator('input[name="csrf_token"]').inputValue().catch(() => '');
          const evidenceToken = await formPreparacao.locator('input[name="evidence_token"]').inputValue().catch(() => '');
          const imagePath = resolveEvidenceImage();
          const imageFile = readFileSync(imagePath);
          const resp = await hibridoPage.request.post(appPath(`/guincho/preparacao/concluir/${pedidoId}`), {
            multipart: {
              csrf_token: csrfToken,
              evidence_token: evidenceToken,
              foto_plataforma: { name: path.basename(imagePath), mimeType: 'image/jpeg', buffer: imageFile }
            }
          });
          if (!resp.ok()) throw new Error(`preparacao/concluir falhou ${resp.status()}: ${await resp.text()}`);
        });

        await expect.poll(async () => {
          const r = qaPedidoStatus(pedidoId);
          return r.status || '';
        }, { timeout: 20000 }).toBe('em_reboque');

        // ── Fase 9: entrega real (1,2km) até o destino ───────────────────
        await hibridoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });
        await moveTowAlongRouteRealTime(hibridoPage, pedidoId, rjDeliveryRoute.slice(1), 12_000);

        const nonceResponse = await hibridoPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const nonceData = await nonceResponse.json();
        if (!nonceResponse.ok() || !nonceData.ok) throw new Error(`evidencia-nonce (entrega) falhou: ${JSON.stringify(nonceData)}`);
        const csrfTokenEntrega = await hibridoPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
        const deliveryImagePath = resolveEvidenceImage();
        const deliveryFile = readFileSync(deliveryImagePath);
        const deliveryResponse = await hibridoPage.request.post(appPath(`/guincho/pedido/status-atualizar/${pedidoId}`), {
          multipart: {
            csrf_token: csrfTokenEntrega,
            pedido_id: String(pedidoId),
            evidence_token: nonceData.evidence_token as string,
            foto_destino: { name: path.basename(deliveryImagePath), mimeType: 'image/jpeg', buffer: deliveryFile }
          }
        });
        if (!deliveryResponse.ok()) throw new Error(`delivery falhou ${deliveryResponse.status()}: ${await deliveryResponse.text()}`);

        let finalStatusData: any;
        await expect.poll(async () => {
          const response = await hibridoPage.request.get(appPath(`/guincho/pedido/status-json/${pedidoId}`), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          finalStatusData = await response.json();
          return finalStatusData?.status || '';
        }, { timeout: 20000 }).toBe('concluido');

        const porSnapshotFinal = finalStatusData?.por_snapshot || {};
        expect(porSnapshotFinal.rota_integra, 'cadeia de hash da trilha POR quebrada').not.toBe(false);

        await testInfo.attach('hibrido-001-resumo.json', {
          body: JSON.stringify({
            pedido_id: pedidoId,
            hibrido_guincho_id: seeded.hibrido_guincho_id,
            payment_id_original: idExternoOriginal,
            payment_id_complementar: idExternoComplementar
          }, null, 2),
          contentType: 'application/json'
        });
      } finally {
        await hibridoContext.close();
      }
    } finally {
      await registroCliente.finalizar();
      await clienteContext.close();
    }
  });

  test('E2E-HIBRIDO-002 | cliente cancela durante a espera do complementar — prestador é liberado e o pagamento já rendido NÃO é estornado automaticamente', async ({ browser }, testInfo) => {
    testInfo.setTimeout(10 * 60_000);

    const seeded = seedConversaoHibridaSetup();
    expect(seeded.ok, 'seed E2E-HIBRIDO-002 (setup) falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const clienteContext = await browser.newContext();
    const clientePage = await clienteContext.newPage();
    const registroCliente = new RegistroPassos(testInfo, clientePage);

    try {
      await loginAs(clientePage, seeded.cliente_email, 'test123');
      await clientePage.goto(appPath(seeded.checkout_url), { waitUntil: 'domcontentloaded' });
      await pagarComBrickReal(clientePage, testInfo, registroCliente, 'socorro-inicial');

      const hibridoAtribuido = seedConversaoHibridaAtribuirHibrido(pedidoId);
      expect(hibridoAtribuido.ok, `atribuir-hibrido falhou: ${JSON.stringify(hibridoAtribuido)}`).toBeTruthy();
      expect(hibridoAtribuido.status).toBe('a_caminho');

      const hibridoContext = await browser.newContext({
        geolocation: { latitude: rjEspecialistaApproachRoute[0].lat, longitude: rjEspecialistaApproachRoute[0].lng },
        permissions: ['geolocation']
      });
      const hibridoPage = await hibridoContext.newPage();

      try {
        await loginAs(hibridoPage, seeded.hibrido_email, 'test123');
        await Promise.all([
          clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
          hibridoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' })
        ]);

        await moveTowAlongRouteRealTime(hibridoPage, pedidoId, rjEspecialistaApproachRoute.slice(1), 12_000);
        await submitStatusWithOptionalImage(hibridoPage);
        await expect(hibridoPage.locator('input[name="foto_chegada"]')).toBeVisible({ timeout: 20000 });

        await test.step('Diagnóstico: iniciar + concluir com REQUER_REBOQUE', async () => {
          const nonceResponse = await hibridoPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const nonceData = await nonceResponse.json();
          const csrfToken1 = await hibridoPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const imagePath = resolveEvidenceImage();
          const respIniciar = await hibridoPage.request.post(appPath(`/guincho/diagnostico/iniciar/${pedidoId}`), {
            multipart: {
              csrf_token: csrfToken1,
              evidence_token: nonceData.evidence_token as string,
              foto_chegada: { name: path.basename(imagePath), mimeType: 'image/jpeg', buffer: readFileSync(imagePath) }
            }
          });
          if (!respIniciar.ok()) throw new Error(`diagnostico/iniciar falhou ${respIniciar.status()}: ${await respIniciar.text()}`);

          await hibridoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });
          const csrfToken2 = await hibridoPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const respConcluir = await hibridoPage.request.post(appPath(`/guincho/diagnostico/concluir/${pedidoId}`), {
            form: {
              csrf_token: csrfToken2,
              resultado: 'REQUER_REBOQUE',
              descricao: 'Falha elétrica não reparável no local — necessário reboque (cenário de cancelamento pós-conversão híbrida).'
            }
          });
          if (!respConcluir.ok()) throw new Error(`diagnostico/concluir falhou ${respConcluir.status()}: ${await respConcluir.text()}`);
        });

        await expect(clientePage.locator('#statusBannerCliente')).toContainText(/reboque/i, { timeout: 20000 });

        // Cliente aprova a conversão (dispara arquivamento + crédito +
        // complementar) mas propositalmente NÃO paga o complementar — só
        // cancela em seguida, ainda em 'aguardando_pagamento_reboque_hibrido'.
        await aprovarConversaoComDestino(clientePage);
        await clientePage.waitForURL(/\/pagamento\/checkout\//, { timeout: 20000 });

        const statusAntesDoCancelamento = qaPedidoStatus(pedidoId);
        expect(statusAntesDoCancelamento.ok).toBeTruthy();
        expect(statusAntesDoCancelamento.status).toBe('aguardando_pagamento_reboque_hibrido');
        expect(statusAntesDoCancelamento.guincho_id).toBe(seeded.hibrido_guincho_id);

        const idsAntesDoCancelamento = pagamentoIdExterno(pedidoId);
        expect(idsAntesDoCancelamento.arquivado_status, 'o socorro no local já prestado precisa continuar arquivado como aprovado').toBe('aprovado');
        expect(idsAntesDoCancelamento.vivo_status, 'o complementar (ainda não pago) precisa estar pendente antes do cancelamento').toBe('pendente');

        // ── Cancelamento pelo cliente (mesmo fluxo real de UI de
        // cancelamento.spec.ts: modal + preview com snapshot_id + confirmar) ──
        await test.step('Cliente cancela o pedido (ainda aguardando o complementar)', async () => {
          await clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' });
          const btnCancelar = clientePage.locator('#btnCancelarPedido');
          await expect(btnCancelar, 'botão de cancelar não apareceu para aguardando_pagamento_reboque_hibrido').toBeVisible({ timeout: 15000 });
          await expect(btnCancelar).toBeEnabled();
          await btnCancelar.click();

          const confirmBtn = clientePage.locator('#btnConfirmarCancelamento');
          await expect(confirmBtn).toBeEnabled({ timeout: 15000 });
          await confirmBtn.click();
          await clientePage.waitForURL(/\/cliente\/historico/i, { timeout: 15000 });
        });

        // ── Asserções: pedido cancelado, prestador liberado, pagamento
        // original (já rendido) NÃO estornado automaticamente ────────────
        await expect.poll(async () => {
          const r = qaPedidoStatus(pedidoId);
          return r.status || '';
        }, { timeout: 15000 }).toBe('cancelado');

        const guinchoAposCancelamento = await test.step('Confirmar que o prestador híbrido foi liberado (disponivel=1)', async () => {
          const r = qaGuinchoStatus(seeded.hibrido_guincho_id);
          expect(r.ok, `qaGuinchoStatus falhou: ${JSON.stringify(r)}`).toBeTruthy();
          expect(r.disponivel, 'prestador híbrido deveria ter sido liberado (disponivel=1) pela transição genérica de cancelamento').toBe(1);
          return r;
        });
        void guinchoAposCancelamento;

        const idsAposCancelamento = await test.step('Confirmar que o pagamento original (serviço já prestado) NÃO foi estornado automaticamente', async () => {
          const r = pagamentoIdExterno(pedidoId);
          expect(r.ok).toBeTruthy();
          // §ESTORNO-ARQUIVADO-01: o cancelamento AUTOMÁTICO do cliente
          // (CancelamentoService -> EstornoService::estornar sem
          // incluirArquivado) nunca reabre sozinho um pagamento já
          // arquivado — o socorro no local já foi de fato prestado, então
          // estornar isso automaticamente seria uma decisão de negócio que
          // ninguém pediu (só a ação MANUAL do admin via DemandaService
          // habilita essa busca). O pagamento arquivado deve continuar
          // 'aprovado', intacto.
          expect(r.arquivado_status, 'pagamento original (já rendido) não deveria ser estornado por um cancelamento automático').toBe('aprovado');
          return r;
        });
        void idsAposCancelamento;

        await testInfo.attach('hibrido-002-resumo.json', {
          body: JSON.stringify({
            pedido_id: pedidoId,
            hibrido_guincho_id: seeded.hibrido_guincho_id,
            status_final: 'cancelado',
            guincho_liberado: true,
            pagamento_original_estornado_automaticamente: false
          }, null, 2),
          contentType: 'application/json'
        });
      } finally {
        await hibridoContext.close();
      }
    } finally {
      await registroCliente.finalizar();
      await clienteContext.close();
    }
  });

  test('E2E-HIBRIDO-003 | prestador perde capacidade de reboque ANTES de pagar o complementar — pedido é rebaixado para a fila comum', async ({ browser }, testInfo) => {
    testInfo.setTimeout(12 * 60_000);

    const seeded = seedConversaoHibridaSetup();
    expect(seeded.ok, 'seed E2E-HIBRIDO-003 (setup) falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const clienteContext = await browser.newContext();
    const clientePage = await clienteContext.newPage();
    const registroCliente = new RegistroPassos(testInfo, clientePage);

    try {
      await loginAs(clientePage, seeded.cliente_email, 'test123');
      await clientePage.goto(appPath(seeded.checkout_url), { waitUntil: 'domcontentloaded' });
      await pagarComBrickReal(clientePage, testInfo, registroCliente, 'socorro-inicial');

      const hibridoAtribuido = seedConversaoHibridaAtribuirHibrido(pedidoId);
      expect(hibridoAtribuido.ok).toBeTruthy();

      const hibridoContext = await browser.newContext({
        geolocation: { latitude: rjEspecialistaApproachRoute[0].lat, longitude: rjEspecialistaApproachRoute[0].lng },
        permissions: ['geolocation']
      });
      const hibridoPage = await hibridoContext.newPage();

      try {
        await loginAs(hibridoPage, seeded.hibrido_email, 'test123');
        await Promise.all([
          clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
          hibridoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' })
        ]);

        await moveTowAlongRouteRealTime(hibridoPage, pedidoId, rjEspecialistaApproachRoute.slice(1), 12_000);
        await submitStatusWithOptionalImage(hibridoPage);
        await expect(hibridoPage.locator('input[name="foto_chegada"]')).toBeVisible({ timeout: 20000 });

        await test.step('Diagnóstico: iniciar + concluir com REQUER_REBOQUE', async () => {
          const nonceResponse = await hibridoPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const nonceData = await nonceResponse.json();
          const csrfToken1 = await hibridoPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const imagePath = resolveEvidenceImage();
          const respIniciar = await hibridoPage.request.post(appPath(`/guincho/diagnostico/iniciar/${pedidoId}`), {
            multipart: {
              csrf_token: csrfToken1,
              evidence_token: nonceData.evidence_token as string,
              foto_chegada: { name: path.basename(imagePath), mimeType: 'image/jpeg', buffer: readFileSync(imagePath) }
            }
          });
          if (!respIniciar.ok()) throw new Error(`diagnostico/iniciar falhou ${respIniciar.status()}: ${await respIniciar.text()}`);

          await hibridoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });
          const csrfToken2 = await hibridoPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const respConcluir = await hibridoPage.request.post(appPath(`/guincho/diagnostico/concluir/${pedidoId}`), {
            form: {
              csrf_token: csrfToken2,
              resultado: 'REQUER_REBOQUE',
              descricao: 'Falha elétrica não reparável no local — necessário reboque (cenário de downgrade por perda de capacidade).'
            }
          });
          if (!respConcluir.ok()) throw new Error(`diagnostico/concluir falhou ${respConcluir.status()}: ${await respConcluir.text()}`);
        });

        await expect(clientePage.locator('#statusBannerCliente')).toContainText(/reboque/i, { timeout: 20000 });

        await aprovarConversaoComDestino(clientePage);
        await clientePage.waitForURL(/\/pagamento\/checkout\//, { timeout: 20000 });

        const statusPosConversao = qaPedidoStatus(pedidoId);
        expect(statusPosConversao.status).toBe('aguardando_pagamento_reboque_hibrido');
        expect(statusPosConversao.guincho_id).toBe(seeded.hibrido_guincho_id);

        // ── O prestador perde a capacidade de reboque ENTRE a decisão de
        // conversão e o pagamento do complementar ────────────────────────
        const capacidadeSuspensa = await test.step('Seed: suspender a capacidade TOW_CAR do prestador híbrido', async () => {
          const r = seedConversaoHibridaSuspenderCapacidadeReboque(seeded.hibrido_guincho_id);
          expect(r.ok, `suspender-capacidade-reboque falhou: ${JSON.stringify(r)}`).toBeTruthy();
          return r;
        });
        void capacidadeSuspensa;

        try {
          // Paga o complementar — PedidoTransitionService::approvePayment
          // deve revalidar a capacidade NESTE momento (guinchoAindaValidoParaHibrido)
          // e, como ela foi suspensa, rebaixar o pedido pra fila comum em
          // vez de seguir com um prestador sem aptidão de reboque.
          await pagarComBrickReal(clientePage, testInfo, registroCliente, 'reboque-complementar');

          const statusPosDowngrade = await test.step('Confirmar downgrade: aguardando_guincho / guincho_id liberado', async () => {
            const r = qaPedidoStatus(pedidoId);
            expect(r.ok).toBeTruthy();
            expect(r.status, 'pedido deveria ter sido rebaixado pra fila comum (aguardando_guincho)').toBe('aguardando_guincho');
            expect(r.guincho_id, 'guincho_id deveria ter sido liberado (NULL) no downgrade').toBeNull();
            expect(r.pagamento_status, 'o complementar deveria continuar aprovado mesmo com o downgrade — o cliente já pagou').toBe('aprovado');
            return r;
          });
          void statusPosDowngrade;

          const guinchoAposDowngrade = await test.step('Confirmar que o prestador híbrido foi liberado (disponivel=1) pelo downgrade', async () => {
            const r = qaGuinchoStatus(seeded.hibrido_guincho_id);
            expect(r.ok).toBeTruthy();
            expect(r.disponivel, 'prestador deveria ter sido liberado pelo downgrade de approvePayment').toBe(1);
            return r;
          });
          void guinchoAposDowngrade;

          await testInfo.attach('hibrido-003-resumo.json', {
            body: JSON.stringify({
              pedido_id: pedidoId,
              hibrido_guincho_id: seeded.hibrido_guincho_id,
              downgrade: true,
              status_final: 'aguardando_guincho'
            }, null, 2),
            contentType: 'application/json'
          });
        } finally {
          // Restaura a capacidade pra não vazar estado suspenso entre
          // execuções (seed 'setup' também reaprova, mas isto documenta a
          // intenção explicitamente e cobre reruns manuais/parciais).
          seedConversaoHibridaReaprovarCapacidadeReboque(seeded.hibrido_guincho_id);
        }
      } finally {
        await hibridoContext.close();
      }
    } finally {
      await registroCliente.finalizar();
      await clienteContext.close();
    }
  });
});
