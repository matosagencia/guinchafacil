// tests/e2e/admin-pedido-funil-modal.spec.js
// Executa: npx playwright test tests/e2e/admin-pedido-funil-modal.spec.js
// Variaveis: BASE_URL (default http://localhost:8080), ADMIN_EMAIL, ADMIN_PASS
const { test, expect } = require('@playwright/test');

const BASE      = process.env.BASE_URL    || 'http://localhost:8080';
const ADMIN_MAIL = process.env.ADMIN_EMAIL || 'admin@guinchafacil.local';
const ADMIN_PASS = process.env.ADMIN_PASS  || 'ChangeMe123!';
const STAMP      = Date.now();
const CLI_EMAIL  = `e2e_admin_${STAMP}@teste.local`;
const CLI_NAME   = `E2E Admin Cliente ${STAMP}`;
const PLACA      = 'E2E' + String(STAMP).slice(-4);

test.describe('Admin funnel — modal quick-create cliente + veiculo', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(BASE + '/login');
        await page.fill('input[name="email"]', ADMIN_MAIL);
        await page.fill('input[name="senha"]', ADMIN_PASS);
        await Promise.all([
            page.waitForURL(/\/admin/, { timeout: 15000 }),
            page.click('button[type="submit"]'),
        ]);
    });

    test('cria cliente + veiculo via modal e chega no funil', async ({ page }) => {
        await page.goto(BASE + '/admin/pedido/novo/v2');
        await expect(page.locator('#cliente_id')).toBeVisible();
        await expect(page.locator('#qf-btn-cli')).toBeVisible();
        await expect(page.locator('#qf-btn-vei')).toBeVisible();
        await expect(page.locator('#qf-btn-vei')).toBeDisabled();

        // Abre modal cliente
        await page.click('#qf-btn-cli');
        await expect(page.locator('#qf-dlg-cliente')).toBeVisible();
        await page.fill('#qf-form-cliente [name="nome"]', CLI_NAME);
        await page.fill('#qf-form-cliente [name="email"]', CLI_EMAIL);
        await page.fill('#qf-form-cliente [name="telefone"]', '21999998888');
        await page.click('#qf-submit-cliente');
        await expect(page.locator('#qf-dlg-cliente')).not.toBeVisible({ timeout: 8000 });

        // Cliente foi selecionado e botao "+ Novo veiculo" habilitou
        await expect(page.locator('#qf-btn-vei')).toBeEnabled();
        const cliSelected = await page.$eval('#cliente_id', el => el.selectedOptions[0].textContent);
        expect(cliSelected).toContain(CLI_NAME);

        // Abre modal veiculo
        await page.click('#qf-btn-vei');
        await expect(page.locator('#qf-dlg-veiculo')).toBeVisible();
        await page.fill('#qf-form-veiculo [name="marca"]', 'Fiat');
        await page.fill('#qf-form-veiculo [name="modelo"]', 'Uno E2E');
        await page.fill('#qf-form-veiculo [name="placa"]', PLACA);
        await page.click('#qf-submit-veiculo');
        await expect(page.locator('#qf-dlg-veiculo')).not.toBeVisible({ timeout: 8000 });

        const veiSelected = await page.$eval('#veiculo_id', el => el.selectedOptions[0].textContent);
        expect(veiSelected).toContain('Fiat');
        expect(veiSelected).toContain(PLACA);

        // Continua para o funil
        await Promise.all([
            page.waitForURL(/\/admin\/pedido\/novo\/funil/, { timeout: 10000 }),
            page.click('button[type="submit"]'),
        ]);

        // Título dinamico do funil (FIX 3)
        await expect(page.locator('#admin-flow-title')).toHaveText(/Onde está o veículo/);

        // Avança stages via eventos (endereco -> modo)
        await page.evaluate(() => {
            document.dispatchEvent(new CustomEvent('gf:address-confirmed', {
                detail: { role: 'origem', lat: -22.9068, lng: -43.1729, label: 'Av. E2E', numero: '100' }
            }));
        });
        await expect(page.locator('#admin-flow-title')).toHaveText(/O que você precisa/, { timeout: 5000 });
    });
});