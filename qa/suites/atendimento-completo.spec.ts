import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { clienteCreds, guinchoCreds } from '../fixtures/test-data.fixture';
import {
  atendimentoConfig,
  atendimentoRoutePoints,
  clickChatButton,
  moveTowAlongRoute,
  submitStatusWithOptionalImage,
  waitForChatMessage
} from '../helpers/atendimento';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAtendimentoCompleto } from '../helpers/seed';

test.describe('atendimento completo', () => {
  test('E2E-ORD-001 | deslocamento, chat e evidências de chegada/entrega funcionam ponta a ponta em free-payment', async ({ browser }, testInfo) => {
    // Este é o teste mais pesado da suite (2 contextos de browser, upload real
    // de 2 imagens, deslocamento simulado em 2 trechos, chat). No gate
    // completo (150 testes, 1 worker, ~25-30min totais numa máquina comum) o
    // timeout padrão de 90s pode não sobrar headroom suficiente quando esta
    // spec roda mais tarde na fila (ex: projeto firefox/webkit), sob
    // contenção de CPU/IO do restante da execução.
    testInfo.setTimeout(180_000);
    const cliente = clienteCreds();
    const guincho = guinchoCreds();
    const config = atendimentoConfig();
    const route = config.route;

    // Antes esta suite exigia TEST_ATENDIMENTO_COMPLETO_PEDIDO_ID configurado
    // manualmente (pedido_id muda a cada seed) — na prática isso nunca era
    // feito e o teste ficava pulado silenciosamente em todo gate. Agora, se a
    // env var não estiver setada, o próprio teste roda o seed PHP
    // (tools/prepare_atendimento_completo_qa_seed.php) e usa o pedido_id
    // devolvido. A env var continua funcionando como override manual.
    if (!config.pedidoId) {
      const seeded = seedAtendimentoCompleto();
      config.pedidoId = String(seeded.pedido_id);
    }

    test.skip(!cliente.email || !cliente.password, 'Defina TEST_CLIENTE_EMAIL e TEST_CLIENTE_PASSWORD.');
    test.skip(!guincho.email || !guincho.password, 'Defina TEST_GUINCHO_EMAIL e TEST_GUINCHO_PASSWORD.');
    test.skip(!config.pedidoId, 'Falha ao auto-seedar pedido de atendimento completo.');
    test.skip(!Array.isArray(route) || route.length < 3, 'Defina TEST_ATENDIMENTO_ROUTE_JSON com ao menos 3 pontos.');

    const clienteContext = await browser.newContext();
    // A geofence real (PedidoTransitionService::validatePreconditions) só libera
    // a transição para 'no_local' se o ÚLTIMO ponto GPS válido do guincho
    // estiver a até por_arrival_radius_m (150m padrão) de lat_origem/lng_origem
    // do pedido — e para 'concluido' exige o mesmo em relação ao destino. O
    // seed define route[0] (Praça da Sé) como origem e route[4] (Av. Paulista)
    // como destino, então o approach precisa TERMINAR em route[0] (chegando à
    // origem) e a entrega precisa TERMINAR em route[4] (chegando ao destino).
    // Antes o approach terminava em route[2] (~1,4km da origem) e a transição
    // para no_local era sempre rejeitada silenciosamente pelo backend — o
    // teste só falhava mais adiante, ao checar o input foto_plataforma, sem
    // deixar claro que a causa real era a geofence.
    const approachRoute = [
      route[2],
      route[1],
      route[0]
    ];
    const deliveryRoute = [
      route[1],
      route[3] || route[0],
      route[4] || route[route.length - 1]
    ];
    const guinchoContext = await browser.newContext({
      geolocation: {
        latitude: approachRoute[0].lat,
        longitude: approachRoute[0].lng
      },
      permissions: ['geolocation']
    });

    const clientePage = await clienteContext.newPage();
    const guinchoPage = await guinchoContext.newPage();
    let clienteFinalContext: Awaited<ReturnType<typeof browser.newContext>> | null = null;
    let guinchoFinalContext: Awaited<ReturnType<typeof browser.newContext>> | null = null;

    try {
      await loginAs(clientePage, cliente.email, cliente.password);
      await loginAs(guinchoPage, guincho.email, guincho.password);
      await expectLoggedIn(clientePage);
      await expectLoggedIn(guinchoPage);

      await Promise.all([
        clientePage.goto(appPath(`/cliente/pedido/${config.pedidoId}`), { waitUntil: 'domcontentloaded' }),
        guinchoPage.goto(appPath(`/guincho/atendimento/${config.pedidoId}`), { waitUntil: 'domcontentloaded' })
      ]);

      await expect(clientePage.locator('#statusBannerCliente')).toContainText(/a caminho/i);
      await expect(guinchoPage.locator('#badgeStatusLabel')).toContainText(/a caminho/i);
      await expect(clientePage.locator('#map')).toBeVisible();
      await expect(guinchoPage.locator('#map')).toBeVisible();

      const clienteMsg = `Cliente QA ${Date.now()} solicitando ETA.`;
      await clickChatButton(clientePage, '#msgInput', '#btnEnviar', clienteMsg);
      await waitForChatMessage(guinchoPage, clienteMsg);

      const guinchoMsg = `Guincho QA ${Date.now()} em deslocamento.`;
      await clickChatButton(guinchoPage, '#msgInput', '#btnEnviarMsg', guinchoMsg);
      await waitForChatMessage(clientePage, guinchoMsg);

      const acceptedAproachRoute = await moveTowAlongRoute(guinchoPage, config.pedidoId, approachRoute);
      await expect(clientePage.locator('#rotaRuaAtual')).toContainText(config.expectedStreetPattern, { timeout: 20000 });
      await expect(clientePage.locator('#rotaRuas .badge')).toHaveCount(1, { timeout: 20000 });

      await submitStatusWithOptionalImage(guinchoPage);
      await expect(guinchoPage.locator('#statusForm input[name="foto_plataforma"]')).toBeVisible({ timeout: 20000 });
      await expect(clientePage.locator('#statusBannerCliente')).toContainText(/no local/i, { timeout: 20000 });

      await submitStatusWithOptionalImage(guinchoPage, config.arrivalImage);
      await expect(guinchoPage.locator('#statusForm input[name="foto_destino"]')).toBeVisible({ timeout: 20000 });
      await expect(clientePage.locator('#cardProvas')).toBeVisible({ timeout: 20000 });
      await expect(clientePage.locator('#colFotoPlataforma')).toBeVisible();

      const acceptedDeliveryRoute = await moveTowAlongRoute(guinchoPage, config.pedidoId, deliveryRoute);
      await clickChatButton(guinchoPage, '#msgInput', '#btnEnviarMsg', 'Entrega em andamento.');
      await waitForChatMessage(clientePage, 'Entrega em andamento.');

      // O evidence_token no DOM é o emitido quando a página carregou (logo
      // após coletar o veículo, perto da origem) — depois de percorrer a
      // deliveryRoute o guincho já está perto do destino, então esse nonce
      // antigo seria rejeitado por geofence (bug real corrigido em
      // GuinchoController::evidenciaNonce). Buscamos um nonce atualizado,
      // vinculado ao último ponto GPS válido, antes de enviar a foto.
      const nonceResponse = await guinchoPage.request.get(appPath(`/guincho/evidencia-nonce/${config.pedidoId}`), {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      const nonceData = await nonceResponse.json();
      if (!nonceResponse.ok() || !nonceData.ok) {
        throw new Error(`evidencia-nonce falhou: ${JSON.stringify(nonceData)}`);
      }
      const evidenceToken = nonceData.evidence_token as string;
      const csrfToken = await guinchoPage.locator('input[name="csrf_token"]').inputValue().catch(() => '');
      const deliveryFile = readFileSync(config.deliveryImage);
      const deliveryResponse = await guinchoPage.request.post(appPath(`/guincho/pedido/status-atualizar/${config.pedidoId}`), {
        multipart: {
          csrf_token: csrfToken,
          pedido_id: String(config.pedidoId),
          evidence_token: evidenceToken,
          foto_destino: {
            name: path.basename(config.deliveryImage),
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
        const response = await guinchoPage.request.get(appPath(`/guincho/pedido/status-json/${config.pedidoId}`), {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        return data?.status || '';
      }, { timeout: 20000 }).toBe('concluido');

      await expect.poll(async () => {
        const response = await clientePage.request.get(appPath(`/cliente/pedido/status-json/${config.pedidoId}`), {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();
        return data?.status || '';
      }, { timeout: 20000 }).toBe('concluido');

      await testInfo.attach('atendimento-route.json', {
        body: JSON.stringify({
          pedido_id: config.pedidoId,
          free_payment: config.freePayment,
          approach_points: acceptedAproachRoute,
          delivery_points: acceptedDeliveryRoute,
          arrival_image: config.arrivalImage,
          delivery_image: config.deliveryImage
        }, null, 2),
        contentType: 'application/json'
      });
    } finally {
      await clienteContext.close();
      await guinchoContext.close();
    }
  });
});
