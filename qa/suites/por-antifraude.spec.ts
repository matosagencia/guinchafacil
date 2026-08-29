import { test, expect } from '@playwright/test';
import { clienteCreds, guinchoCreds } from '../fixtures/test-data.fixture';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

function porConfig() {
  return {
    pedidoStatusId: process.env.TEST_PEDIDO_STATUS_ID || process.env.TEST_PEDIDO_ID || '',
    atendimentoPedidoId: process.env.TEST_ATENDIMENTO_PEDIDO_ID || process.env.TEST_PEDIDO_ATENDIMENTO_ID || ''
  };
}

test.describe('por e antifraude', () => {
  test('E2E-POR-001 | cliente visualiza snapshot operacional de rota e trilha confirmada', async ({ page }) => {
    const { email, password } = clienteCreds();
    const { pedidoStatusId } = porConfig();
    test.skip(!email || !password, 'Credenciais de cliente não configuradas.');
    test.skip(!pedidoStatusId, 'Defina TEST_PEDIDO_STATUS_ID para validar o acompanhamento POR.');

    await loginAs(page, email, password);
    await expectLoggedIn(page);
    await page.goto(appPath(`/cliente/pedido/${pedidoStatusId}`));

    await expect(page.locator('body')).toContainText(/rota, ETA e trilha confirmada|rastreamento em tempo real/i);
    await expect(page.locator('#rotaRuaAtual')).toBeVisible();
    await expect(page.locator('#rotaEta')).toBeVisible();
    await expect(page.locator('#rotaDistancia')).toBeVisible();
    await expect(page.locator('#rotaProgressoBar')).toHaveAttribute('style', /width:/i);
  });

  test('E2E-POR-002 | guincho recebe feedback visual quando ponto GPS é recusado pelo antifraude', async ({ page, context }) => {
    const { email, password } = guinchoCreds();
    const { atendimentoPedidoId } = porConfig();
    test.skip(!email || !password, 'Credenciais de guincho não configuradas.');
    test.skip(!atendimentoPedidoId, 'Defina TEST_ATENDIMENTO_PEDIDO_ID para validar antifraude operacional.');

    await context.grantPermissions(['geolocation']);
    await context.setGeolocation({ latitude: -23.5618, longitude: -46.6565 });

    await page.route('**/guincho/localizacao', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ ok: true, accepted: false, reason: 'antifraud_rejected' })
      });
    });

    await loginAs(page, email, password);
    await expectLoggedIn(page);
    await page.goto(appPath(`/guincho/atendimento/${atendimentoPedidoId}`));

    const badge = page.locator('#badgeGps');
    await expect(badge).toBeVisible({ timeout: 20000 });
    await expect(badge).toHaveClass(/bg-warning/, { timeout: 20000 });
  });
});
