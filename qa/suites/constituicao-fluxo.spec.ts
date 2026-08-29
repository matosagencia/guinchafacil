import { test, expect } from '@playwright/test';
import { clienteCreds } from '../fixtures/test-data.fixture';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

function constituicaoConfig() {
  return {
    documentoPath: process.env.TEST_CONSTITUICAO_DOCUMENTO_PATH || '',
    emailConfirmacao: process.env.TEST_CONSTITUICAO_EMAIL || process.env.TEST_CLIENTE_EMAIL || ''
  };
}

test.describe('fluxo constitucional oficial', () => {
  test('E2E-CONST-001 | acesso autenticado ao fluxo constitucional', async ({ page }) => {
    const { email, password } = clienteCreds();
    test.skip(!email || !password, 'Credenciais de cliente não configuradas.');

    await loginAs(page, email, password);
    await expectLoggedIn(page);
    await page.goto(appPath('/cliente/dashboard'));
    await expect(page.locator('body')).toContainText(/pedido|dashboard|cliente/i);
  });

  test('E2E-CONST-002 | suíte oficial exige artefatos seguros via ambiente', async ({ page }) => {
    const { email, password } = clienteCreds();
    const { documentoPath, emailConfirmacao } = constituicaoConfig();
    test.skip(!email || !password, 'Credenciais de cliente não configuradas.');
    test.skip(!documentoPath || !emailConfirmacao, 'Defina TEST_CONSTITUICAO_DOCUMENTO_PATH e TEST_CONSTITUICAO_EMAIL para executar o fluxo completo.');

    await loginAs(page, email, password);
    await expectLoggedIn(page);
    await page.goto(appPath('/cliente/dashboard'));
    await expect(page.locator('body')).toContainText(/pedido|dashboard|cliente/i);
  });
});
