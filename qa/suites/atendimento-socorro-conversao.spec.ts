import { test, expect, type Page, type TestInfo } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import {
  rjEspecialistaApproachRoute,
  rjGuinchoApproachRoute,
  rjDeliveryRoute,
  rjExpectedStreetPattern,
  moveTowAlongRouteRealTime,
  resolveEvidenceImage
} from '../helpers/atendimento';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import {
  seedAtendimentoSocorroSetup,
  seedAtendimentoSocorroAtribuirEspecialista,
  seedAtendimentoSocorroAtribuirReboque,
  seedAtendimentoSocorroLigarRoadMatch,
  seedAtendimentoSocorroDesligarRoadMatch
} from '../helpers/seed';
import { CPF_TESTE_PADRAO, MP_SCENARIOS, MP_TEST_CARDS } from '../fixtures/mercadopago-test-cards.fixture';
import { RegistroPassos } from '../helpers/step-logger';

// E2E-SOCORRO-001 — pane elétrica atendida por um especialista PURO (sem
// capacidade de reboque aprovada), diagnóstico real REQUER_REBOQUE, cliente
// aprova a conversão informando o destino (que o pedido de socorro no local
// corretamente NÃO pede na criação — service_types.ELECTRICAL_DIAGNOSIS tem
// requires_destination=0), o que dispara uma cobrança COMPLEMENTAR real do
// reboque (ver §COBRANCA-REBOQUE-01 em ConversionService.php — correção de
// lacuna apontada pelo usuário em 26/07/2026: o valor do socorro no local
// não cobre o reboque, são serviços com preços diferentes). Depois do
// segundo pagamento real aprovado, um guincho comum (não o especialista)
// assume o reboque até o destino, com POR-VAL-010 (aderência à malha
// viária) ligado como verificação bônus nas duas pernas de GPS.
//
// Dois pagamentos REAIS via Payment Brick (mesmo padrão de E2E-PAY-004 em
// pagamento-sandbox.spec.ts): um pelo socorro inicial (R$ 89,90, deslocamento
// + diagnóstico), outro pelo reboque complementar (calculado por
// TarifaService a partir da distância real até o destino informado).
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
 * Roda o checkout transparente (Payment Brick) do zero até a navegação de
 * sucesso, assumindo que `page` já está em /pagamento/checkout/{pedidoId}.
 * Extraído de E2E-PAY-004 (qa/suites/pagamento-sandbox.spec.ts) — mesma
 * lógica, reaproveitada aqui porque este cenário precisa rodar o Brick
 * DUAS vezes (socorro inicial + reboque complementar).
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
    const { clickFirstAvailable } = await import('../helpers/auth');
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

test.describe('atendimento com conversão para reboque — pane elétrica (Rio de Janeiro)', () => {
  test('E2E-SOCORRO-001 | especialista atende pane elétrica, diagnostica REQUER_REBOQUE, cliente paga complementar e guincho comum conclui o reboque', async ({ browser }, testInfo) => {
    // Dois pagamentos reais (Brick) + diagnóstico + 2 pernas de GPS reais
    // (1km especialista + 1,2km entrega) — folga generosa, mesmo espírito
    // dos RJ-TOW-00x.
    testInfo.setTimeout(18 * 60_000);

    const seeded = seedAtendimentoSocorroSetup();
    expect(seeded.ok, 'seed E2E-SOCORRO-001 (setup) falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const ligado = seedAtendimentoSocorroLigarRoadMatch();
    expect(ligado.ok, 'não consegui ligar por_road_match_enabled para o bônus POR-VAL-010').toBeTruthy();

    const clienteContext = await browser.newContext();
    const clientePage = await clienteContext.newPage();
    const registroCliente = new RegistroPassos(testInfo, clientePage);

    try {
      // ── Fase 1: pagamento real do socorro inicial (R$ 89,90) ──────────
      await loginAs(clientePage, seeded.cliente_email, 'test123');
      await clientePage.goto(appPath(seeded.checkout_url), { waitUntil: 'domcontentloaded' });
      await pagarComBrickReal(clientePage, testInfo, registroCliente, 'socorro-inicial');

      // Webhook/aprovação (genérico, agnóstico de attendance_mode) já deve
      // ter avançado aguardando_pagamento -> aguardando_guincho.
      const especialistaAtribuido = await test.step('Seed: atribuir especialista (fila bypassed, mesmo padrão RJ-TOW)', async () => {
        const r = seedAtendimentoSocorroAtribuirEspecialista(pedidoId);
        expect(r.ok, `atribuir-especialista falhou: ${JSON.stringify(r)}`).toBeTruthy();
        expect(r.status).toBe('a_caminho');
        return r;
      });
      void especialistaAtribuido;

      const especialistaContext = await browser.newContext({
        geolocation: { latitude: rjEspecialistaApproachRoute[0].lat, longitude: rjEspecialistaApproachRoute[0].lng },
        permissions: ['geolocation']
      });
      const especialistaPage = await especialistaContext.newPage();

      try {
        await loginAs(especialistaPage, seeded.especialista_email, 'test123');
        await Promise.all([
          clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
          especialistaPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' })
        ]);
        await expect(clientePage.locator('#statusBannerCliente')).toContainText(/a caminho/i);
        await expect(especialistaPage.locator('#badgeStatusLabel')).toContainText(/a caminho/i);

        // ── Fase 2: aproximação real (1km) até o local da pane ──────────
        await moveTowAlongRouteRealTime(especialistaPage, pedidoId, rjEspecialistaApproachRoute.slice(1), 12_000);
        await expect(clientePage.locator('#rotaRuaAtual')).toContainText(rjExpectedStreetPattern, { timeout: 20000 });

        // a_caminho -> no_local (chegada, formulário genérico #statusForm).
        const { submitStatusWithOptionalImage } = await import('../helpers/atendimento');
        await submitStatusWithOptionalImage(especialistaPage);
        await expect(especialistaPage.locator('input[name="foto_chegada"]')).toBeVisible({ timeout: 20000 });
        await expect(clientePage.locator('#statusBannerCliente')).toContainText(/no local/i, { timeout: 20000 });

        // no_local -> diagnostico_iniciado: exige foto_chegada + evidence_token
        // (formulário próprio de diagnóstico, não o #statusForm genérico).
        await test.step('Diagnóstico: iniciar (foto de chegada real)', async () => {
          const nonceResponse = await especialistaPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const nonceData = await nonceResponse.json();
          if (!nonceResponse.ok() || !nonceData.ok) {
            throw new Error(`evidencia-nonce falhou: ${JSON.stringify(nonceData)}`);
          }
          const csrfToken = await especialistaPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const imagePath = resolveEvidenceImage();
          const imageFile = readFileSync(imagePath);
          const resp = await especialistaPage.request.post(appPath(`/guincho/diagnostico/iniciar/${pedidoId}`), {
            multipart: {
              csrf_token: csrfToken,
              evidence_token: nonceData.evidence_token as string,
              foto_chegada: { name: path.basename(imagePath), mimeType: 'image/jpeg', buffer: imageFile }
            }
          });
          if (!resp.ok()) throw new Error(`diagnostico/iniciar falhou ${resp.status()}: ${await resp.text()}`);
        });

        await especialistaPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });
        await expect(especialistaPage.locator('#badgeStatusLabel')).toContainText(/diagn.stico/i, { timeout: 20000 });

        // diagnostico_iniciado -> conversao_reboque_pendente (resultado real
        // REQUER_REBOQUE — o cenário inteiro depende deste parecer).
        await test.step('Diagnóstico: concluir com REQUER_REBOQUE', async () => {
          const csrfToken = await especialistaPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const resp = await especialistaPage.request.post(appPath(`/guincho/diagnostico/concluir/${pedidoId}`), {
            form: {
              csrf_token: csrfToken,
              resultado: 'REQUER_REBOQUE',
              descricao: 'Falha elétrica não reparável no local — bateria não segura carga, sistema de carregamento comprometido. Necessário reboque até oficina especializada.'
            }
          });
          if (!resp.ok()) throw new Error(`diagnostico/concluir falhou ${resp.status()}: ${await resp.text()}`);
        });

        await expect(clientePage.locator('#statusBannerCliente')).toContainText(/reboque/i, { timeout: 20000 });

        // ── Fase 3: cliente informa destino real e aprova a conversão ───
        // Isso dispara a cobrança complementar (ConversionService) e
        // redireciona de volta pro checkout para o SEGUNDO pagamento real.
        await clientePage.reload({ waitUntil: 'domcontentloaded' });
        const destinoInput = clientePage.locator('#conversaoDestinoInput');
        await expect(destinoInput, 'formulário de conversão (destino) não apareceu — verifique conversao_reboque_pendente').toBeVisible({ timeout: 20000 });
        await destinoInput.fill('Avenida Ayrton Senna, 2200, Barra da Tijuca, Rio de Janeiro - RJ');
        await clientePage.locator('#btnBuscarDestinoConversao').click();
        await expect(clientePage.locator('#conversaoDestinoFeedback')).toContainText(/encontrado/i, { timeout: 15000 });

        // §DETERMINISMO-TESTE: o geocode real (Nominatim) pode devolver uma
        // coordenada plausível mas não IDÊNTICA ao ponto usado por
        // rjDeliveryRoute (amostrado via OSRM, não geocode de endereço) — e
        // a chegada ao destino depende de geofence (200m, ver
        // PorThresholds::destinationRadiusM). Para a perna de entrega real
        // bater com precisão no destino esperado, sobrescreve as coordenadas
        // ocultas com o ponto exato de rjDeliveryRoute (o texto do endereço
        // buscado já provou que o geocode real funciona; isto só remove a
        // variância de "qual número exato do Nominatim" do caminho crítico
        // do teste).
        const destinoExato = rjDeliveryRoute[rjDeliveryRoute.length - 1];
        await clientePage.locator('#conversaoDestinoLat').evaluate((el: HTMLInputElement, v: number) => { el.value = String(v); }, destinoExato.lat);
        await clientePage.locator('#conversaoDestinoLng').evaluate((el: HTMLInputElement, v: number) => { el.value = String(v); }, destinoExato.lng);

        await expect(clientePage.locator('#btnAprovarConversao')).toBeEnabled({ timeout: 5000 });
        await clientePage.locator('#btnAprovarConversao').click();
        await clientePage.waitForURL(/\/pagamento\/checkout\//, { timeout: 20000 });

        // ── Fase 4: segundo pagamento real (complementar de reboque) ────
        await pagarComBrickReal(clientePage, testInfo, registroCliente, 'reboque-complementar');

        // ── Fase 5: guincho comum assume o reboque (não o especialista) ─
        const reboqueAtribuido = await test.step('Seed: atribuir guincho comum de reboque', async () => {
          const r = seedAtendimentoSocorroAtribuirReboque(pedidoId);
          expect(r.ok, `atribuir-reboque falhou: ${JSON.stringify(r)}`).toBeTruthy();
          expect(r.status).toBe('a_caminho');
          expect(r.guincho_id).toBe(seeded.guincho_reboque_id);
          return r;
        });
        void reboqueAtribuido;

        const guinchoContext = await browser.newContext({
          geolocation: { latitude: rjGuinchoApproachRoute[0].lat, longitude: rjGuinchoApproachRoute[0].lng },
          permissions: ['geolocation']
        });
        const guinchoPage = await guinchoContext.newPage();

        try {
          await loginAs(guinchoPage, seeded.guincho_reboque_email, 'test123');
          await Promise.all([
            clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
            guinchoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' })
          ]);
          await expect(clientePage.locator('#statusBannerCliente')).toContainText(/a caminho/i, { timeout: 20000 });
          await expect(guinchoPage.locator('#badgeStatusLabel')).toContainText(/a caminho/i, { timeout: 20000 });

          await moveTowAlongRouteRealTime(guinchoPage, pedidoId, rjGuinchoApproachRoute.slice(1), 12_000);
          await expect(clientePage.locator('#rotaRuaAtual')).toContainText(rjExpectedStreetPattern, { timeout: 20000 });

          await submitStatusWithOptionalImage(guinchoPage);
          await expect(guinchoPage.locator('#statusForm input[name="foto_plataforma"]')).toBeVisible({ timeout: 20000 });
          await expect(clientePage.locator('#statusBannerCliente')).toContainText(/no local/i, { timeout: 20000 });

          await submitStatusWithOptionalImage(guinchoPage, resolveEvidenceImage());
          await expect(guinchoPage.locator('#statusForm input[name="foto_destino"]')).toBeVisible({ timeout: 20000 });

          await moveTowAlongRouteRealTime(guinchoPage, pedidoId, rjDeliveryRoute.slice(1), 12_000);

          const nonceResponse = await guinchoPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const nonceData = await nonceResponse.json();
          if (!nonceResponse.ok() || !nonceData.ok) throw new Error(`evidencia-nonce (entrega) falhou: ${JSON.stringify(nonceData)}`);
          const csrfToken = await guinchoPage.locator('input[name="csrf_token"]').inputValue().catch(() => '');
          const deliveryImagePath = resolveEvidenceImage();
          const deliveryFile = readFileSync(deliveryImagePath);
          const deliveryResponse = await guinchoPage.request.post(appPath(`/guincho/pedido/status-atualizar/${pedidoId}`), {
            multipart: {
              csrf_token: csrfToken,
              pedido_id: String(pedidoId),
              evidence_token: nonceData.evidence_token as string,
              foto_destino: { name: path.basename(deliveryImagePath), mimeType: 'image/jpeg', buffer: deliveryFile }
            }
          });
          if (!deliveryResponse.ok()) throw new Error(`delivery falhou ${deliveryResponse.status()}: ${await deliveryResponse.text()}`);

          let finalStatusData: any;
          await expect.poll(async () => {
            const response = await guinchoPage.request.get(appPath(`/guincho/pedido/status-json/${pedidoId}`), {
              headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            finalStatusData = await response.json();
            return finalStatusData?.status || '';
          }, { timeout: 20000 }).toBe('concluido');

          const porSnapshotFinal = finalStatusData?.por_snapshot || {};
          expect(porSnapshotFinal.rota_integra, 'cadeia de hash da trilha POR quebrada').not.toBe(false);

          await testInfo.attach('socorro-001-resumo.json', {
            body: JSON.stringify({
              pedido_id: pedidoId,
              custo_socorro_inicial: 89.90,
              destino_geocodificado: 'Avenida Ayrton Senna, 2200, Barra da Tijuca, Rio de Janeiro - RJ',
              guincho_reboque_id: seeded.guincho_reboque_id,
              especialista_guincho_id: seeded.especialista_guincho_id,
              por_road_match_enabled: true
            }, null, 2),
            contentType: 'application/json'
          });
          await testInfo.attach('socorro-001-por-snapshot-final.json', {
            body: JSON.stringify(porSnapshotFinal, null, 2),
            contentType: 'application/json'
          });
        } finally {
          await guinchoContext.close();
        }
      } finally {
        await especialistaContext.close();
      }
    } finally {
      seedAtendimentoSocorroDesligarRoadMatch();
      await registroCliente.finalizar();
      await clienteContext.close();
    }
  });
});
