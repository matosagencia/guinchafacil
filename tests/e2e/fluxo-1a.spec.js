// ═══════════════════════════════════════════════════════════════════
// Fluxo 1a — Suite completa (v2 selectors)
// ═══════════════════════════════════════════════════════════════════
const { test, expect } = require('@playwright/test');

const BASE = process.env.E2E_BASE || 'http://localhost:8080';
const T = { short: 5000, med: 15000, long: 30000 };

// ─── Helpers ───
async function preencherOrigem(page, endereco = 'Praça Mauá, 1') {
  if (!page.__skipCoverage) await mockCoverage(page);
  await page.goto(BASE + '/pre-cotacao');
  const input = page.locator('#localizacao');
  await input.fill(endereco);
  // Espera o item aparecer (API Photon + fallback pode demorar)
  await page.evaluate(() => {
    document.querySelector('#lat_origem').value = '-22.9105';
    document.querySelector('#lng_origem').value = '-43.1755';
  });
  await page.locator('[data-step="1"] [data-next="2"]').click();
  if (page.__fallback) {
    await page.locator('#gfRecommendation').waitFor({ state: 'visible', timeout: T.med });
    return;
  }
  await page.locator('[data-step="2"]').waitFor({ state: 'visible', timeout: T.med });
  // Confirma
}

async function escolherModo(page, modo = 'me_orientem') {
  const card = page.locator(`[data-mode="${modo}"]`);
  await card.waitFor({ state: 'visible', timeout: T.med });
  await card.click();
  await page.waitForTimeout(600);
}

async function mockCoverage(page, data = { qtd_oficinas: 1, tem_oficina: true, tem_guincho: true }) {
  await page.route('**/api/pre-cotacao/cobertura**', async (route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ ok: true, data, error: null }),
  }));
}

async function escolherVeiculo(page, cat = 'popular') {
  const card = page.locator(`[data-categoria="${cat}"]`);
  await card.waitFor({ state: 'visible', timeout: T.med });
  await card.click();
  await page.waitForTimeout(800);
}

async function escolherServico(page, slug = 'pneu') {
  const card = page.locator(`[data-sintoma="${slug}"]`);
  await card.waitFor({ state: 'visible', timeout: T.med });
  await card.click();
  await page.waitForTimeout(1500);
}

async function check(page, nome, fn) {
  console.log('\n▶ PASSO: ' + nome);
  try {
    const res = await fn();
    console.log('  ✅ ' + nome + (res ? ' → ' + res : ''));
    return res;
  } catch (err) {
    console.error('  ❌ FALHA em "' + nome + '": ' + err.message.split('\n')[0]);
    throw err;
  }
}

// ═══════════════════════════════════════════════════════════════════
test.describe('Fluxo 1a — v2', () => {

  test('1a.1 — Happy path: origem → modo → veiculo → sintoma → opcoes', async ({ page }) => {
    await check(page, 'preencher origem', async () => { await preencherOrigem(page); });
    await check(page, 'escolher Me orientem', async () => { await escolherModo(page, 'me_orientem'); });
    await check(page, 'escolher sintoma Pneu', async () => { await escolherServico(page, 'pneu'); });
    await check(page, 'escolher Carro', async () => { await escolherVeiculo(page, 'popular'); });
    await check(page, 'card A visivel (assistencia)', async () => {
      const card = page.locator('[data-decision="assistencia"]');
      await card.waitFor({ state: 'visible', timeout: T.long });
      const preco = await card.locator('[data-price="assistencia"]').textContent();
      expect(preco).toMatch(/R\$\s*\d/);
      return preco;
    });
    await check(page, 'card B visivel (reboque)', async () => {
      const card = page.locator('[data-decision="reboque"]');
      await card.waitFor({ state: 'visible', timeout: T.med });
      return 'ok';
    });
  });

  test('1a.2 — Levar o carro pula cards e vai pro destino', async ({ page }) => {
    await check(page, 'preencher origem', async () => { await preencherOrigem(page); });
    await check(page, 'escolher Levar o carro', async () => { await escolherModo(page, 'levar_carro'); });
    await check(page, 'escolher Carro', async () => { await escolherVeiculo(page, 'popular'); });
    await check(page, 'destino aparece (sem cards)', async () => {
      const destino = page.locator('[data-step="5"]');
      await destino.waitFor({ state: 'visible', timeout: T.med });
      const cards = await page.locator('[data-decision="assistencia"]:visible').count();
      expect(cards).toBe(0);
      return 'destino direto';
    });
  });

  test('1a.3 - Fallback WhatsApp (sem cobertura)', async ({ page }) => {
    page.__skipCoverage = true;
    page.__fallback = true;
    await page.route('**/api/pre-cotacao/cobertura**', async (route) => route.fulfill({
      status: 200, contentType: 'application/json',
      body: JSON.stringify({ ok: true, data: {
        qtd_oficinas: 0, tem_oficina: false, tem_guincho: false,
      }, error: null }),
    }));
    await page.route('**/api/pre-cotacao/whatsapp**', async (route) => route.fulfill({
      status: 200, contentType: 'application/json',
      body: JSON.stringify({ ok: true, data: {
        link: 'https://wa.me/5521999999999',
      }, error: null }),
    }));

    await check(page, 'preencher origem', async () => { await preencherOrigem(page); });
    await check(page, 'sem cobertura cai no WhatsApp', async () => {
      const wa = page.locator('a[href*="wa.me"]').first();
      const aviso = page.locator('.alert-warning').first();
      await Promise.race([
        wa.waitFor({ state: 'visible', timeout: T.med }),
        aviso.waitFor({ state: 'visible', timeout: T.med }),
      ]);
      return 'fallback ok';
    });
  });

  test('1a.4 — CEP direto (autocomplete por CEP)', async ({ page }) => {
    await page.goto(BASE + '/pre-cotacao');
    const input = page.locator('.gf-ap[data-role="origem"] [data-ap-input]');
    await input.fill('22240004');
    await page.waitForTimeout(1500);
    const item = page.locator('.gf-ap[data-role="origem"] .gf-ap__item').first();
    await item.waitFor({ state: 'visible', timeout: T.med });
    const txt = await item.textContent();
    expect(txt).toMatch(/Laranjeiras|Rio de Janeiro/i);
    return txt.trim();
  });
});
