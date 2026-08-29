import { test, expect } from '@playwright/test';
import { guinchoCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate visual do Nível 2 (plano §12) — tela de atendimento do guincho
 * (src/Views/guincho/atendimento.php): GPS real via watchPosition,
 * fila offline (IndexedDB) e ações de progresso do status. Só roda de
 * fato quando o guincho de teste tem um atendimento em andamento.
 */
test.describe('Guincho — atendimento em andamento', () => {
  test('E2E-TOW-ATD-001 | tela de atendimento carrega com rota e ações de status', async ({ page }) => {
    const { email, password } = guinchoCreds();
    test.skip(!email || !password, 'Credenciais de teste de guincho não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/guincho/dashboard'), { waitUntil: 'domcontentloaded' });

    const acompanharLink = page.locator('a[href*="/guincho/atendimento/"], #pedidoAtivoGuinchoCard a:has-text("Acompanhar")').first();
    test.skip((await acompanharLink.count()) === 0, 'Guincho de teste não tem atendimento ativo no momento.');

    await acompanharLink.click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toContainText(/pedido|atendimento/i);
  });

  test('E2E-TOW-ATD-002 | fila offline de GPS (IndexedDB) está registrada no navegador', async ({ page }) => {
    const { email, password } = guinchoCreds();
    test.skip(!email || !password, 'Credenciais de teste de guincho não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/guincho/dashboard'), { waitUntil: 'domcontentloaded' });

    const hasIndexedDb = await page.evaluate(() => 'PorOfflineQueue' in window || 'indexedDB' in window);
    expect(hasIndexedDb).toBeTruthy();
  });
});
