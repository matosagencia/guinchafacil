const { test, expect } = require('@playwright/test');

// Execute com E2E_BASE_URL e credenciais de uma base de QA. Sem credenciais,
// o arquivo permanece descoberto pelo runner sem tocar dados reais.
test.describe('especialista: jornada web', () => {
  test.skip(!process.env.E2E_BASE_URL || !process.env.E2E_ESPECIALISTA_EMAIL, 'Configure ambiente E2E de QA');
  test('registro e painel respondem', async ({ page }) => {
    await page.goto(`${process.env.E2E_BASE_URL}/registro/especialista`);
    await expect(page).toHaveTitle(/especialista|guincha/i);
    await page.goto(`${process.env.E2E_BASE_URL}/especialista/dashboard`);
    await expect(page).toHaveURL(/login|especialista\/dashboard/);
  });
});
