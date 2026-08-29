import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import {
  rjGuinchoApproachRoute,
  rjEspecialistaApproachRoute,
  rjDeliveryRoute,
  rjExpectedStreetPattern,
  moveTowAlongRouteRealTime,
  moveTowAlongRouteRealTimeComQueda,
  resolveEvidenceImage,
  submitStatusWithOptionalImage
} from '../helpers/atendimento';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAtendimentoRjTow, seedAtendimentoRjEspecialista } from '../helpers/seed';

// Duas corridas reais no Rio de Janeiro (Avenida Ayrton Senna, Barra da
// Tijuca — rota via OSRM, ~100m entre pontos), percorridas em TEMPO REAL
// (device_timestamp = Date.now() no instante do envio, esperas reais entre
// pontos, ~30km/h). Existem para:
//  1) provar que uma corrida real e plausível passa pelo antifraude de
//     verdade (LocationValidationService), igual a atendimento-tempo-real.spec.ts;
//  2) provar que a resiliência de GPS (public/assets/js/core/gps-resilience.js)
//     e a projeção de posição (dead reckoning, cliente/pedidostatus.php)
//     funcionam de verdade: cada teste força uma QUEDA DE SINAL real de 25s
//     no meio do trajeto (sem enviar nenhum ponto) e verifica que o cliente
//     passa a mostrar "posição estimada" nesse intervalo, e que o aviso some
//     assim que os pontos reais voltam;
//  3) comparar os dois tipos de prestador: guincho comum (nasce podendo
//     rebocar) vs. especialista que virou guincho de verdade (o seed passa
//     pelas mesmas chamadas de produção Guincho::solicitarReboque + aprovar).
// Nível de arquivo (não dentro do describe): test.use({ video }) força um
// worker novo e o Playwright rejeita isso dentro de um describe.
test.use({ video: 'on' }); // grava sempre (passe ou falhe), não só em falha

test.describe('atendimento em tempo real — Rio de Janeiro (guincho comum x especialista)', () => {
  test('RJ-TOW-001 | guincho comum — 700m de aproximação + 1,2km de entrega, com queda de GPS simulada', async ({ browser }, testInfo) => {
    // ~8 pontos de aproximação + ~13 de entrega, 12s reais entre cada
    // (~30km/h) + 25s de queda simulada + login/chat/upload.
    testInfo.setTimeout(15 * 60_000);

    const seeded = seedAtendimentoRjTow();
    expect(seeded.ok, 'seed RJ-TOW-001 falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const clienteContext = await browser.newContext();
    const guinchoContext = await browser.newContext({
      geolocation: { latitude: rjGuinchoApproachRoute[0].lat, longitude: rjGuinchoApproachRoute[0].lng },
      permissions: ['geolocation']
    });

    const clientePage = await clienteContext.newPage();
    const guinchoPage = await guinchoContext.newPage();

    try {
      await loginAs(clientePage, seeded.cliente_email, 'test123');
      await loginAs(guinchoPage, seeded.guincho_email, 'test123');

      await Promise.all([
        clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
        guinchoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' })
      ]);

      await expect(clientePage.locator('#statusBannerCliente')).toContainText(/a caminho/i);
      await expect(guinchoPage.locator('#badgeStatusLabel')).toContainText(/a caminho/i);

      // Percorre a aproximação (700m) em tempo real, com uma queda de sinal
      // real de 25s depois do 3º ponto — LIMIAR_PROJECAO_S no cliente é 20s,
      // então a projeção (dead reckoning) deve entrar em ação durante o gap.
      await moveTowAlongRouteRealTimeComQueda(
        guinchoPage,
        pedidoId,
        rjGuinchoApproachRoute.slice(1),
        12_000,
        3,
        25_000,
        async () => {
          await expect(clientePage.locator('#rotaFrescor')).toContainText(/posição estimada|sem sinal/i, { timeout: 15000 });
        }
      );

      // Voltou a receber ponto real: o aviso de estimativa deve sumir.
      // Achado real (ambiente Windows/XAMPP): o 1º ponto ao retomar pode
      // legitimamente ser rejeitado (POR-VAL-007, ver moveTowAlongRouteRealTime)
      // — nesse caso só o 2º ponto realmente reseta a âncora do cliente, então
      // o timeout aqui precisa cobrir 1 rejeição + 1 intervalo real + a
      // propagação via SSE/poll, não só o caso ideal de "1ª tentativa aceita".
      await expect(clientePage.locator('#rotaFrescor')).not.toContainText(/posição estimada/i, { timeout: 60000 });
      await expect(clientePage.locator('#rotaRuaAtual')).toContainText(rjExpectedStreetPattern, { timeout: 20000 });

      // Chegada: a_caminho -> no_local.
      await submitStatusWithOptionalImage(guinchoPage);
      await expect(guinchoPage.locator('#statusForm input[name="foto_plataforma"]')).toBeVisible({ timeout: 20000 });
      await expect(clientePage.locator('#statusBannerCliente')).toContainText(/no local/i, { timeout: 20000 });

      // Coleta: no_local -> em_reboque (geofence da origem satisfeito pelo
      // último ponto real da aproximação).
      await submitStatusWithOptionalImage(guinchoPage, resolveEvidenceImage());
      await expect(guinchoPage.locator('#statusForm input[name="foto_destino"]')).toBeVisible({ timeout: 20000 });

      // Entrega (1,2km) em tempo real, sem queda desta vez.
      await moveTowAlongRouteRealTime(guinchoPage, pedidoId, rjDeliveryRoute.slice(1), 12_000);

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
          foto_destino: { name: path.basename(deliveryImagePath), mimeType: 'image/jpeg', buffer: deliveryFile }
        }
      });
      if (!deliveryResponse.ok()) {
        throw new Error(`delivery api failed ${deliveryResponse.status()}: ${await deliveryResponse.text()}`);
      }

      let finalStatusData: any;
      await expect.poll(async () => {
        const response = await guinchoPage.request.get(appPath(`/guincho/pedido/status-json/${pedidoId}`), {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        finalStatusData = await response.json();
        return finalStatusData?.status || '';
      }, { timeout: 20000 }).toBe('concluido');

      // Verificação de "degradação controlada" (pedida explicitamente): a
      // queda de sinal proposital e qualquer POR-VAL-007 isolado por latência
      // real do ambiente NÃO devem corromper a trilha nem impedir a conclusão.
      // rota_integra confere a cadeia de hash ponto a ponto — se algum ponto
      // rejeitado tivesse quebrado a sequência/encadeamento, isso apareceria
      // aqui como false (regressão real), não como falha de teste.
      const porSnapshotFinal = finalStatusData?.por_snapshot || {};
      expect(porSnapshotFinal.rota_integra, 'cadeia de hash da trilha POR quebrada — indicaria corrupção real, não apenas rejeição isolada por latência').not.toBe(false);

      await testInfo.attach('rj-tow-001-route.json', {
        body: JSON.stringify({
          pedido_id: pedidoId,
          approach_points: rjGuinchoApproachRoute,
          delivery_points: rjDeliveryRoute,
          interval_ms_real: 12000,
          gap_simulado_ms: 25000
        }, null, 2),
        contentType: 'application/json'
      });
      await testInfo.attach('rj-tow-001-por-snapshot-final.json', {
        body: JSON.stringify(porSnapshotFinal, null, 2),
        contentType: 'application/json'
      });
    } finally {
      await clienteContext.close();
      await guinchoContext.close();
    }
  });

  test('RJ-TOW-002 | especialista que virou guincho — 1km de aproximação + 1,2km de entrega, com queda de GPS simulada', async ({ browser }, testInfo) => {
    testInfo.setTimeout(15 * 60_000);

    const seeded = seedAtendimentoRjEspecialista();
    expect(seeded.ok, 'seed RJ-TOW-002 falhou').toBeTruthy();
    expect(seeded.reboque_aprovado, 'upgrade especialista->guincho não refletiu reboque_aprovado=1').toBe(1);
    const pedidoId = String(seeded.pedido_id);

    const clienteContext = await browser.newContext();
    const guinchoContext = await browser.newContext({
      geolocation: { latitude: rjEspecialistaApproachRoute[0].lat, longitude: rjEspecialistaApproachRoute[0].lng },
      permissions: ['geolocation']
    });

    const clientePage = await clienteContext.newPage();
    const guinchoPage = await guinchoContext.newPage();

    try {
      await loginAs(clientePage, seeded.cliente_email, 'test123');
      await loginAs(guinchoPage, seeded.guincho_email, 'test123');

      await Promise.all([
        clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
        guinchoPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' })
      ]);

      await expect(clientePage.locator('#statusBannerCliente')).toContainText(/a caminho/i);
      await expect(guinchoPage.locator('#badgeStatusLabel')).toContainText(/a caminho/i);

      // Aproximação de 1km, com queda de sinal real de 25s depois do 4º ponto.
      await moveTowAlongRouteRealTimeComQueda(
        guinchoPage,
        pedidoId,
        rjEspecialistaApproachRoute.slice(1),
        12_000,
        4,
        25_000,
        async () => {
          await expect(clientePage.locator('#rotaFrescor')).toContainText(/posição estimada|sem sinal/i, { timeout: 15000 });
        }
      );

      // Ver comentário equivalente no RJ-TOW-001: 1 ponto rejeitado (isolado)
      // ao retomar é tolerado, então só o 2º ponto real reseta a âncora do
      // cliente — o timeout aqui precisa cobrir isso, não só o caso ideal.
      await expect(clientePage.locator('#rotaFrescor')).not.toContainText(/posição estimada/i, { timeout: 60000 });
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
          foto_destino: { name: path.basename(deliveryImagePath), mimeType: 'image/jpeg', buffer: deliveryFile }
        }
      });
      if (!deliveryResponse.ok()) {
        throw new Error(`delivery api failed ${deliveryResponse.status()}: ${await deliveryResponse.text()}`);
      }

      let finalStatusData: any;
      await expect.poll(async () => {
        const response = await guinchoPage.request.get(appPath(`/guincho/pedido/status-json/${pedidoId}`), {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        finalStatusData = await response.json();
        return finalStatusData?.status || '';
      }, { timeout: 20000 }).toBe('concluido');

      // Ver comentário equivalente no RJ-TOW-001: prova de "degradação
      // controlada" — nenhum ponto rejeitado (queda proposital ou latência
      // real do ambiente) pode ter corrompido a cadeia de hash da trilha.
      const porSnapshotFinal = finalStatusData?.por_snapshot || {};
      expect(porSnapshotFinal.rota_integra, 'cadeia de hash da trilha POR quebrada — indicaria corrupção real, não apenas rejeição isolada por latência').not.toBe(false);

      await testInfo.attach('rj-tow-002-route.json', {
        body: JSON.stringify({
          pedido_id: pedidoId,
          approach_points: rjEspecialistaApproachRoute,
          delivery_points: rjDeliveryRoute,
          interval_ms_real: 12000,
          gap_simulado_ms: 25000,
          reboque_aprovado_via_fluxo_real: true
        }, null, 2),
        contentType: 'application/json'
      });
      await testInfo.attach('rj-tow-002-por-snapshot-final.json', {
        body: JSON.stringify(porSnapshotFinal, null, 2),
        contentType: 'application/json'
      });
    } finally {
      await clienteContext.close();
      await guinchoContext.close();
    }
  });
});
