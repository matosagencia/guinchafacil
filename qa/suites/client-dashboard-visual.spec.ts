import { test, expect } from '@playwright/test';
import { clienteCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate visual do Nível 2 (plano §12) — painel do cliente.
 * Cobre: page-head presente, hero com saudação/chips, sem IDs duplicados,
 * sem overflow horizontal em 360px, tema branco do cliente aplicado.
 */
test.describe('Cliente — dashboard visual', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = clienteCreds();
    test.skip(!email || !password, 'Credenciais de teste de cliente não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/cliente/dashboard'), { waitUntil: 'domcontentloaded' });
  });

  test('E2E-CLI-VIS-001 | page-head e hero renderizam com dados reais', async ({ page }) => {
    await expect(page.locator('header.page-head')).toBeVisible();
    await expect(page.locator('.client-hero')).toBeVisible();
    await expect(page.locator('.client-hero-title')).toContainText(/olá/i);
  });

  test('E2E-CLI-VIS-002 | atalhos de serviço vêm do catálogo administrável', async ({ page }) => {
    const tiles = page.locator('.client-service-tile');
    await expect(tiles.first()).toBeVisible();
    expect(await tiles.count()).toBeGreaterThan(0);
  });

  test('E2E-CLI-VIS-003 | sem IDs duplicados no DOM', async ({ page }) => {
    const duplicated = await page.evaluate(() => {
      const ids = Array.from(document.querySelectorAll('[id]')).map((el) => el.id);
      const seen = new Set<string>();
      const dupes: string[] = [];
      for (const id of ids) {
        if (seen.has(id)) dupes.push(id);
        seen.add(id);
      }
      return dupes;
    });
    expect(duplicated, `IDs duplicados encontrados: ${duplicated.join(', ')}`).toEqual([]);
  });

  test('E2E-CLI-VIS-004 | sem overflow horizontal em 360px', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 800 });
    await page.waitForTimeout(300);
    // scrollWidth/clientWidth pode acusar "overflow" por elementos internos
    // do Leaflet (proxy de animação de zoom, sempre clipado por
    // .map-container { overflow: hidden }) ou pelo menu colapsado do
    // Bootstrap, que nunca são visíveis nem roláveis de verdade pelo
    // usuário. O teste real e observável é: a página consegue ser rolada
    // horizontalmente de fato?
    const scrollXAlcancado = await page.evaluate(() => {
      window.scrollTo(9999, 0);
      return window.scrollX;
    });
    expect(scrollXAlcancado, 'A página rolou horizontalmente de verdade (overflow real, visível ao usuário)').toBeLessThanOrEqual(1);
  });

  test('E2E-CLI-VIS-005 | tema claro do cliente aplicado no body', async ({ page }) => {
    await expect(page.locator('body.cliente')).toHaveCount(1);
  });
});
