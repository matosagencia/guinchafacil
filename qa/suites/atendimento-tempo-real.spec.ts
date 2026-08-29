import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { clienteCreds, guinchoCreds } from '../fixtures/test-data.fixture';
import {
  realtimeApproachRoute,
  realtimeDeliveryRoute,
  realtimeExpectedStreetPattern,
  moveTowAlongRouteRealTime,
  resolveEvidenceImage,
  submitStatusWithOptionalImage
} from '../helpers/atendimento';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAtendimentoTempoReal } from '../helpers/seed';

// Histórico: atendimento-completo.spec.ts (E2E-ORD-001) valida a máquina de
// estados do atendimento (aceite → deslocamento → coleta → entrega →
// conclusão) o mais rápido possível — pra isso, "acelera o relógio"
// simulando device_timestamp 120s à frente por ponto enquanto o teste real
// leva ~250ms por ponto. Isso nunca exercita de verdade
// LocationValidationService::validatePoint com dados reais: o antifraude
// sempre recebe "tempo de sobra" fabricado, então uma regressão que
// permitisse concluir uma corrida de 50km em 5 minutos reais NÃO seria
// detectada por aquele teste.
//
// Este spec (E2E-ORD-002) cobre exatamente essa lacuna: rota curta real
// (~1,8km cada trecho, ruas de verdade de São Paulo via OSRM), percorrida em
// TEMPO REAL — device_timestamp = Date.now() no instante do envio, com
// esperas reais (page.waitForTimeout) entre pontos. Silenciosamente valida
// duas coisas ao mesmo tempo: (1) o antifraude aceita uma viagem real e
// plausível sem falso-positivo, e (2) a trilha gravada tem pontos densos o
// bastante pra realmente acompanhar a rua no mapa do admin (ao contrário da
// trilha "em linha reta" observada no pedido 1330, que usava só 3-5
// waypoints esparsos).
test.describe('atendimento em tempo real (antifraude de verdade)', () => {
  test('E2E-ORD-002 | rota curta (~1.8km) percorrida em tempo real, sem timestamp acelerado', async ({ browser }, testInfo) => {
    // ~12 pontos por trecho, 9s reais entre cada um = ~1.6min por trecho +
    // login/chat/upload. Timeout generoso pra não mascarar lentidão real do
    // ambiente, mas o grosso do tempo aqui é ESPERA REAL INTENCIONAL, não
    // lentidão de servidor.
    testInfo.setTimeout(10 * 60_000);

    const cliente = clienteCreds();
    const guincho = guinchoCreds();

    test.skip(!cliente.email || !cliente.password, 'Defina TEST_CLIENTE_EMAIL e TEST_CLIENTE_PASSWORD.');
    test.skip(!guincho.email || !guincho.password, 'Defina TEST_GUINCHO_EMAIL e TEST_GUINCHO_PASSWORD.');

    const seeded = seedAtendimentoTempoReal();
    const pedidoId = String(seeded.pedido_id);

    const clienteContext = await browser.newContext();
    const guinchoContext = await browser.newContext({
      geolocation: {
        latitude: realtimeApproachRoute[0].lat,
        longitude: realtimeApproachRoute[0].lng
      },
      permissions: ['geolocation']
    });

    const clientePage = await clienteContext.newPage();
    const guinchoPage = await guinchoContext.newPage();

    try {
      await loginAs(clientePage, cliente.email, cliente.password);
      await loginAs(guinchoPage, guincho.email, guincho.password);
      await expectLoggedIn(clientePage);
      await expectLoggedIn(guinchoPage);

      await Promise.all([
        clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
        guinchoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' })
      ]);

      await expect(clientePage.locator('#statusBannerCliente')).toContainText(/a caminho/i);
      await expect(guinchoPage.locator('#badgeStatusLabel')).toContainText(/a caminho/i);

      // Percorre a aproximação em tempo real (sem o primeiro ponto — o
      // guincho já nasce nele via geolocation do seed/contexto).
      await moveTowAlongRouteRealTime(guinchoPage, pedidoId, realtimeApproachRoute.slice(1), 9_000);
      await expect(clientePage.locator('#rotaRuaAtual')).toContainText(realtimeExpectedStreetPattern, { timeout: 20000 });

      // Chegada: transição a_caminho -> no_local (sem evidência exigida).
      await submitStatusWithOptionalImage(guinchoPage);
      await expect(guinchoPage.locator('#statusForm input[name="foto_plataforma"]')).toBeVisible({ timeout: 20000 });
      await expect(clientePage.locator('#statusBannerCliente')).toContainText(/no local/i, { timeout: 20000 });

      // Coleta: no_local -> em_reboque (exige foto_plataforma + geofence da origem,
      // que acabamos de satisfazer com o último ponto real da aproximação).
      await submitStatusWithOptionalImage(guinchoPage, resolveEvidenceImage());
      await expect(guinchoPage.locator('#statusForm input[name="foto_destino"]')).toBeVisible({ timeout: 20000 });

      // Percorre a entrega em tempo real.
      await moveTowAlongRouteRealTime(guinchoPage, pedidoId, realtimeDeliveryRoute.slice(1), 9_000);

      // Conclusão: em_reboque -> concluido, exige foto_destino + geofence do
      // destino + nonce ATUALIZADO (o embutido na página é de quando ela
      // carregou, ainda perto da origem — ver GuinchoController::evidenciaNonce,
      // correção aplicada após o bug real encontrado em atendimento-completo.spec.ts).
      const nonceResponse = await guinchoPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      const nonceData = await nonceResponse.json();
      if (!nonceResponse.ok() || !nonceData.ok) {
        throw new Error(`evidencia-nonce falhou: ${JSON.stringify(nonceData)}`);
      }
      const evidenceToken = nonceData.evidence_token as string;
      const csrfToken = await guinchoPage.locator('input[name="csrf_token"]').inputValue().catch(() => '');
      const deliveryImagePath = resolveEvidenceImage();
      const deliveryFile = readFileSync(deliveryImagePath);
      const deliveryResponse = await guinchoPage.request.post(appPath(`/guincho/pedido/status-atualizar/${pedidoId}`), {
        multipart: {
          csrf_token: csrfToken,
          pedido_id: String(pedidoId),
          evidence_token: evidenceToken,
          foto_destino: {
            name: path.basename(deliveryImagePath),
            mimeType: 'image/jpeg',
            buffer: deliveryFile
          }
        }
      });
      if (!deliveryResponse.ok()) {
        const errorBody = await deliveryResponse.text();
        throw new Error(`delivery api failed ${deliveryResponse.status()}: ${errorBody}`);
      }

      await expect.poll(async () => {
        const response = await guinchoPage.request.get(appPath(`/guincho/pedido/status-json/${pedidoId}`), {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        return data?.status || '';
      }, { timeout: 20000 }).toBe('concluido');

      await testInfo.attach('atendimento-tempo-real-route.json', {
        body: JSON.stringify({
          pedido_id: pedidoId,
          approach_points: realtimeApproachRoute,
          delivery_points: realtimeDeliveryRoute,
          interval_ms_real: 9000
        }, null, 2),
        contentType: 'application/json'
      });
    } finally {
      await clienteContext.close();
      await guinchoContext.close();
    }
  });
});
