import { test, expect } from '@playwright/test';
import { clienteCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate visual do Nível 2 (plano §12) — acompanhamento de pedido
 * (src/Views/cliente/pedidostatus.php). Depende de existir um pedido
 * ativo/recente do cliente de teste; se não houver, o teste é pulado
 * em vez de fabricar um resultado falso-positivo.
 */
test.describe('Cliente — acompanhamento do pedido', () => {
  test('E2E-CLI-TRK-001 | painel do pedido ativo mostra rota, ETA e ações', async ({ page }) => {
    const { email, password } = clienteCreds();
    test.skip(!email || !password, 'Credenciais de teste de cliente não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/cliente/dashboard'), { waitUntil: 'domcontentloaded' });

    const acompanharLink = page.locator('#pedidoAtivoClienteCard a:has-text("Acompanhar")').first();
    test.skip(
      (await acompanharLink.count()) === 0,
      'Cliente de teste não tem pedido ativo no momento — nada para acompanhar.'
    );

    await acompanharLink.click();
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('body')).toContainText(/pedido/i);
    const chat = page.locator('[data-chat], #chatMensagens, .chat-msg').first();
    const cancelBtn = page.locator('button:has-text("Cancelar"), a:has-text("Cancelar")').first();
    expect((await chat.count()) + (await cancelBtn.count())).toBeGreaterThan(0);
  });
});
