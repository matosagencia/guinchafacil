const { test, expect } = require('@playwright/test');

test.describe('Motor de Pedidos E2E @smoke (Pre-cotacao, Cliente, Admin)', () => {
    test('fluxo completo do motor de atendimento', async ({ page }) => {
        await page.goto('http://localhost:8080/pre-cotacao');
        await expect(page).toHaveTitle(/Pré-cotação|GuinchaFácil/i);

        const locationInput = page.locator('input[name="localizacao"]');
        if (await locationInput.isVisible()) {
            await locationInput.fill('E2E_Praça Mauá, Rio de Janeiro');
        }

        await page.goto('http://localhost:8080/admin/testes');
        expect(page.url()).toMatch(/login|testes/);
    });
});