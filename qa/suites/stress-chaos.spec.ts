import { test, expect } from '@playwright/test';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAtendimentoGamboa, qaPorSnapshot } from '../helpers/seed';
import { driveRoute } from '../helpers/gps-simulator';
import { rjGamboaToVenezuelaRoute } from '../fixtures/stress-scenarios.fixture';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

// Caos controlado: cenários que já causaram travamentos reais em campo (ver
// diagnóstico de 29/07/2026) e alguns clássicos de resiliência de rede.
// Cada um aqui é um cenário REAL já observado ou um risco concreto do
// próprio código, não uma lista genérica de "boas práticas de chaos".
test.describe('caos controlado', () => {
  test('STRESS-CHAOS-001 | reload no meio do envio de GPS não trava o teste (regressão §POR-QA-NAV-01)', async ({ browser }, testInfo) => {
    testInfo.setTimeout(3 * 60_000);

    // Este é o bug real diagnosticado em 29/07/2026: um reload disparado
    // pela própria página (SSE status_update, ver atendimento.php:834) no
    // meio de um envio de GPS destruía o page.evaluate() em voo e travava o
    // teste por 18min até o timeout global. A correção (postTowLocation com
    // corrida contra timeout + retry em moveTowAlongRouteRealTime) deve
    // fazer o teste abaixo terminar rápido em vez de travar.
    const seeded = seedAtendimentoGamboa('colisao');
    expect(seeded.ok, 'seed falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const projectRoot = path.resolve(__dirname, '..', '..');
    const stdout = execFileSync('php', [path.join(projectRoot, 'tools/prepare_atendimento_rj_gamboa_qa.php'), 'atribuir', pedidoId], { encoding: 'utf8', cwd: projectRoot });
    expect(JSON.parse(stdout.trim()).ok).toBeTruthy();

    const context = await browser.newContext({
      geolocation: { latitude: rjGamboaToVenezuelaRoute[0].lat, longitude: rjGamboaToVenezuelaRoute[0].lng },
      permissions: ['geolocation'],
    });
    const page = await context.newPage();

    try {
      await loginAs(page, seeded.prestador_email, 'test123');
      await page.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });

      const rotaPromise = driveRoute(page, pedidoId, rjGamboaToVenezuelaRoute.slice(1), 3);

      // Força o reload exatamente no meio do envio — simula o que o SSE
      // faria de propósito, sem depender de um status_update real chegar
      // no timing certo (o que seria não-determinístico).
      await page.waitForTimeout(800);
      await page.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});

      // Se a correção não estivesse em vigor, esta linha travaria até o
      // timeout do teste (3min) em vez de retornar em segundos.
      await rotaPromise;

      const snapshot = qaPorSnapshot(pedidoId);
      expect(snapshot.ok).toBeTruthy();
      expect(snapshot.total_pontos ?? 0).toBeGreaterThan(0);
    } finally {
      await context.close();
    }
  });

  test('STRESS-CHAOS-002 | queda de rede real durante o rastreamento recupera sozinha', async ({ browser }, testInfo) => {
    testInfo.setTimeout(3 * 60_000);

    const seeded = seedAtendimentoGamboa('colisao');
    expect(seeded.ok, 'seed falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const projectRoot = path.resolve(__dirname, '..', '..');
    const stdout = execFileSync('php', [path.join(projectRoot, 'tools/prepare_atendimento_rj_gamboa_qa.php'), 'atribuir', pedidoId], { encoding: 'utf8', cwd: projectRoot });
    expect(JSON.parse(stdout.trim()).ok).toBeTruthy();

    const context = await browser.newContext({
      geolocation: { latitude: rjGamboaToVenezuelaRoute[0].lat, longitude: rjGamboaToVenezuelaRoute[0].lng },
      permissions: ['geolocation'],
    });
    const page = await context.newPage();

    try {
      await loginAs(page, seeded.prestador_email, 'test123');
      await page.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });

      await driveRoute(page, pedidoId, rjGamboaToVenezuelaRoute.slice(1, 4), 2);

      await context.setOffline(true);
      await page.waitForTimeout(1500);
      await context.setOffline(false);

      // Depois de voltar, o restante da rota precisa continuar funcionando
      // normalmente — sem exigir reload manual nem intervenção.
      await driveRoute(page, pedidoId, rjGamboaToVenezuelaRoute.slice(4), 3);

      const snapshot = qaPorSnapshot(pedidoId);
      expect(snapshot.ok).toBeTruthy();
      expect(snapshot.total_pontos ?? 0).toBeGreaterThanOrEqual(rjGamboaToVenezuelaRoute.length - 3);
    } finally {
      await context.close();
    }
  });
});
