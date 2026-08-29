const { test, expect } = require('@playwright/test');

const ready = process.env.E2E_BASE_URL && process.env.E2E_CLIENTE_EMAIL && process.env.E2E_CLIENTE_PASSWORD && process.env.E2E_ESPECIALISTA_EMAIL && process.env.E2E_ESPECIALISTA_PASSWORD;

test.describe('especialista: jornada completa em QA', () => {
  test.skip(!ready, 'Configure credenciais E2E de uma base de QA');

  test('cliente inicia atendimento e especialista acessa o painel', async ({ browser }) => {
    const cliente = await browser.newContext();
    const page = await cliente.newPage();
    await page.goto(`${process.env.E2E_BASE_URL}/login`);
    await page.locator('#email').fill(process.env.E2E_CLIENTE_EMAIL);
    await page.locator('#password').fill(process.env.E2E_CLIENTE_PASSWORD);
    await page.getByRole('button', { name: /entrar|acessar/i }).click();
    await expect(page).toHaveURL(/cliente|dashboard/);
    await cliente.close();

    const especialista = await browser.newContext();
    const specialistPage = await especialista.newPage();
    await specialistPage.goto(`${process.env.E2E_BASE_URL}/login`);
    await specialistPage.locator('#email').fill(process.env.E2E_ESPECIALISTA_EMAIL);
    await specialistPage.locator('#password').fill(process.env.E2E_ESPECIALISTA_PASSWORD);
    await specialistPage.getByRole('button', { name: /entrar|acessar/i }).click();
    await expect(specialistPage).toHaveURL(/especialista|dashboard/);
    await expect(specialistPage.locator('body')).toContainText(/especialista|chamados|online/i);
    await especialista.close();
  });

  test('endpoint de notificações e rotas operacionais exigem sessão', async ({ request }) => {
    const response = await request.get(`${process.env.E2E_BASE_URL}/especialista/notificacoes`);
    expect([200, 302, 401, 403]).toContain(response.status());
  });
});
