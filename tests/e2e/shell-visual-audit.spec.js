const { test, expect } = require('@playwright/test');

const BASE = process.env.BASE_URL || 'http://localhost:8080';
const users = [
  ['admin', 'admin@teste.local', '/admin/dashboard'],
  ['cliente', 'cliente@teste.local', '/cliente/dashboard'],
  ['guincho', 'guincho@teste.local', '/guincho/dashboard'],
  ['oficina', 'oficina@teste.local', '/oficina/dashboard'],
];


for (const [role, email, target] of users) {
  test(`shell visual ${role}`, async ({ page }) => {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="email"]').fill(email);
    await page.locator('input[name="senha"]').fill('teste123');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(new RegExp(role === 'admin' ? '/admin' : `/${role}`), { timeout: 15000 });
    await page.goto(`${BASE}${target}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);
    await expect(page.locator('body')).toHaveClass(new RegExp(`\\b${role}\\b`));
    await page.screenshot({ path: `test-results/shell-${role}.png`, fullPage: true });
    const metrics = await page.evaluate(() => {
      const q = s => document.querySelector(s);
      const rect = el => el ? (() => { const r = el.getBoundingClientRect(); return { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height) }; })() : null;
      return {
        viewport: { w: innerWidth, h: innerHeight },
        bodyClass: document.body.className,
        header: rect(q('nav.navbar, nav.gf-admin-topbar, .bo-header')),
        sidebar: rect(q('.sidebar, .bo-sidebar')),
        main: rect(q('.main-content, .bo-content, main')),
        footer: rect(q('footer.gf-site-footer, .bo-footer')),
        logo: rect(q('.navbar-brand img, .bo-brand, .gf-brand')),
        shell: q('.bo') ? 'backoffice_shell' : 'legacy_shell',
      };
    });
    console.log(`SHELL ${role} ${JSON.stringify(metrics)}`);
  });
}

test('admin pedido novo usa shell do funil claro', async ({ page }) => {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill('admin@teste.local');
  await page.locator('input[name="senha"]').fill('teste123');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/\/admin/, { timeout: 15000 });
  await page.goto(`${BASE}/admin/pedido/novo`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  await expect(page.locator('.gf-admin-order-v3')).toBeVisible();
  await expect(page.locator('.gf-admin-order-v3 .gf-order-progress')).toBeVisible();
  const bg = await page.locator('.gf-admin-order-v3').evaluate(el => getComputedStyle(el).backgroundColor);
  expect(bg).toBe('rgb(243, 245, 247)');
  await page.screenshot({ path: 'test-results/admin-pedido-novo.png', fullPage: true });
});

test('admin configuracoes usa shell central', async ({ page }) => {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill('admin@teste.local');
  await page.locator('input[name="senha"]').fill('teste123');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/\/admin/, { timeout: 15000 });
  await page.goto(`${BASE}/admin/configuracoes`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(600);
  await expect(page.locator('#gfAdminConfigRoot.gf-f18c-root')).toBeVisible();
  await expect(page.locator('#gfAdminConfigRoot .gf-f18c-sidebar')).toBeVisible();
  await expect(page.locator('#gfAdminConfigRoot .bo-header')).toBeVisible();
  await expect(page.locator('#gfAdminConfigRoot .gf-admin-config-content')).toBeVisible();
  await page.screenshot({ path: 'test-results/admin-configuracoes.png', fullPage: true });
});

test('subpaginas cliente nao exibem navbar legada', async ({ page }) => {
  await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill('cliente@teste.local');
  await page.locator('input[name="senha"]').fill('teste123');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(/\/cliente/, { timeout: 15000 });
  for (const route of ['/cliente/historico', '/cliente/financeiro', '/cliente/perfil', '/cliente/veiculos', '/cliente/oficinas']) {
    await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(250);
    await expect(page.locator('body')).toHaveClass(/\bcliente\b/);
    const state = await page.evaluate(() => ({
      nav: getComputedStyle(document.querySelector('nav.navbar')).display,
      mainY: Math.round(document.querySelector('.main-wrapper')?.getBoundingClientRect().y || 0),
    }));
    expect(state.nav).toBe('none');
    expect(state.mainY).toBe(0);
  }
});

for (const [role, routes] of [
  ['guincho', ['/guincho/historico', '/guincho/financeiro', '/guincho/capacidades', '/guincho/estoque', '/guincho/perfil']],
  ['oficina', ['/oficina/historico', '/oficina/financeiro', '/oficina/pedidos', '/oficina/perfil']],
]) {
  test(`subpaginas ${role} nao exibem topo legado`, async ({ page }) => {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="email"]').fill(`${role}@teste.local`);
    await page.locator('input[name="senha"]').fill('teste123');
    await page.locator('button[type="submit"]').click();
    await page.waitForURL(new RegExp(`/${role}`), { timeout: 15000 });
    for (const route of routes) {
      await page.goto(`${BASE}${route}`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(250);
      const state = await page.evaluate(() => ({
        nav: getComputedStyle(document.querySelector('nav.navbar')).display,
        sidebar: !!document.querySelector('.gf-profile-sidebar'),
        mainY: Math.round(document.querySelector('.main-wrapper')?.getBoundingClientRect().y || 0),
      }));
      expect(state.nav).toBe('none');
      expect(state.sidebar).toBeTruthy();
      expect(state.mainY).toBe(0);
    }
  });
}
