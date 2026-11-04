const { test, expect } = require('@playwright/test');

const BASE = process.env.BASE_URL || 'http://localhost:8080';
const routes = [
  '/admin/central',
  '/admin/pedido/novo',
  '/admin/pedido/novo/funil',
  '/admin/configuracoes',
];

test.beforeEach(async ({ page }) => {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill('admin@teste.local');
  await page.locator('input[name="senha"]').fill('teste123');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/\/admin/, { timeout: 15000 });
});

for (const route of routes) {
  test(`shell e encoding ${route}`, async ({ page }) => {
    const response = await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
    expect(response && response.status()).toBeLessThan(400);
    await page.waitForTimeout(400);

    const result = await page.evaluate(() => {
      const text = document.body.innerText || '';
      const header = document.querySelectorAll('.bo-header');
      const sidebars = document.querySelectorAll('.bo-sidebar, .gf-f18c-sidebar');
      const legacyNav = document.querySelectorAll('nav.navbar, nav.gf-admin-topbar');
      const main = document.querySelector('.bo-content, .main-content, main');
      const mainBg = main ? getComputedStyle(main).backgroundColor : '';
      return {
        mojibake: /Ã|Â|â€|ï¿½|�/.test(text),
        headers: header.length,
        sidebars: sidebars.length,
        legacyNav: legacyNav.length,
        mainBg,
        bodyClass: document.body.className,
      };
    });

    expect(result.mojibake, `mojibake em ${route}`).toBeFalsy();
    expect(result.headers, `bo-header ausente/duplicado em ${route}`).toBe(1);
    expect(result.sidebars, `sidebar ausente/duplicado em ${route}`).toBe(1);
    expect(result.legacyNav, `navbar legada em ${route}`).toBe(0);
    expect(result.mainBg, `conteudo preto em ${route}`).not.toBe('rgb(0, 0, 0)');
  });
}
