const { test, expect } = require('@playwright/test');

test.describe('motor de decisão da cotação pública', () => {
  test('exige número e permite escolher a cidade de uma rua ambígua', async ({ page }) => {
    await page.route('**/geocode/public**', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          ok: true,
          items: [
            {
              display_name: 'Rua do Mercado, Centro, Rio de Janeiro, RJ, Brasil',
              cidade: 'Rio de Janeiro',
              uf: 'Rio de Janeiro',
              house_number: '280',
              lat: '-22.9068',
              lng: '-43.1729',
            },
            {
              display_name: 'Rua do Mercado, Centro, Fortaleza, CE, Brasil',
              cidade: 'Fortaleza',
              uf: 'Ceará',
              house_number: '280',
              lat: '-3.7319',
              lng: '-38.5267',
            },
          ],
        }),
      });
    });

    await page.goto('/pre-cotacao');
    const origem = page.locator('#localizacao');
    const numero = page.locator('#numero_origem');
    const status = page.locator('#gpsStatus');
    await expect(page.locator('#originMapPanel')).toBeVisible();
    await expect(page.locator('#originMap')).toBeVisible();

    await origem.fill('Rua do Mercado');
    await expect(status).toContainText('número');
    await expect(page.locator('.public-address-suggestion')).toHaveCount(0);

    await origem.fill('Rua do Mercado');
    await numero.fill('280');
    const sugestoes = page.locator('.public-address-suggestion');
    await expect(sugestoes).toHaveCount(2);
    await expect(page.locator('.public-address-suggestions.col-12').first()).toBeVisible();
    await expect(sugestoes.nth(0)).toContainText('Rio de Janeiro');
    await expect(sugestoes.nth(1)).toContainText('Fortaleza');

    await sugestoes.nth(0).click();
    await expect(status).toContainText('Rio de Janeiro');
    await expect(page.locator('#lat_origem')).toHaveValue('-22.9068');
    await expect(page.locator('#lng_origem')).toHaveValue('-43.1729');
    await expect(page.locator('.origin-map-composition')).toBeHidden();
    await expect(page.locator('#situacaoStage')).toBeVisible();
    await expect(page.locator('#vehicleStage')).toBeHidden();
    await page.locator('#btnSituacaoAvancar').click();
    await expect(page.locator('#orientationStage')).toBeVisible();
    await page.locator('[data-solution="tow"]').click();
    await page.locator('#btnSituacaoAvancar').click();
    await expect(page.locator('#vehicleStage')).toBeVisible();
    await page.locator('#btnSituacaoVoltar').click();
    await expect(page.locator('#orientationStage')).toBeVisible();
  });

  test('apresenta a mesma sequência de decisão na pré-cotação', async ({ page }) => {
    await page.goto('/pre-cotacao');

    const motor = page.locator('.gf-decision-model');
    await expect(motor).toBeVisible();
    await expect(motor.locator('.gf-decision-model__steps article')).toHaveCount(4);
    await expect(motor).toContainText('Localização');
    await expect(motor).toContainText('Situação');
    await expect(motor).toContainText('Veículo');
    await expect(motor).toContainText('Confirmar');
  });
});
