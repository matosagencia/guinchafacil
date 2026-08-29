import { test, expect } from '@playwright/test';
import { guinchoCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate visual do Nível 2 (plano §12) — card "Nova solicitação" (oferta).
 * Só valida o conteúdo mínimo exigido pelo plano §7.6 quando existe uma
 * oferta real disponível; caso não haja pedido esperando aceite no
 * momento do teste, pula em vez de fabricar dados.
 */
test.describe('Guincho — card de oferta', () => {
  test('E2E-TOW-OFR-001 | oferta mostra distância, ETA, valor e ações', async ({ page }) => {
    const { email, password } = guinchoCreds();
    test.skip(!email || !password, 'Credenciais de teste de guincho não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/guincho/dashboard'), { waitUntil: 'domcontentloaded' });

    const offerCard = page.locator('.tow-offer').first();
    test.skip((await offerCard.count()) === 0, 'Nenhuma oferta ativa no momento do teste.');

    await expect(offerCard).toBeVisible();
    await expect(page.locator('.tow-offer-metric')).toHaveCount(4);
    await expect(page.locator('.tow-offer-actions button, .tow-offer-actions a')).toHaveCount(2);
  });
});
