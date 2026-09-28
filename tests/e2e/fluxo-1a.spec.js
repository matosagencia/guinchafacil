// ═══════════════════════════════════════════════════════════════════
// Fluxo 1a — Suite completa (v2 selectors)
// ═══════════════════════════════════════════════════════════════════
const { test, expect } = require('@playwright/test');

const BASE = process.env.E2E_BASE || 'http://localhost:8080';
const T = { short: 5000, med: 15000, long: 30000 };

// ─── Helpers ───
async function preencherOrigem(page, endereco = 'Praça Mauá, 1') {
  await page.goto(BASE + '/pre-cotacao');
  await page.waitForSelector('.gf-ap[data-role="origem"]', { timeout: T.med });
  const input = page.locator('.gf-ap[data-role="origem"] [data-ap-input]');
  await input.click();
  await input.fill(endereco);
  // Espera o item aparecer (API Photon + fallback pode demorar)
  const sugestao = page.locator('.gf-ap[data-role="origem"] .gf-ap__item').first();
  await sugestao.waitFor({ state: 'visible', timeout: 25000 });
  await sugestao.click();
  await page.waitForTimeout(500);
  // Confirma
  const btn = page.locator('.gf-ap[data-role="origem"] [data-ap-confirm-btn]');
  await btn.waitFor({ state: 'visible', timeout: T.short });
  await btn.click();
  await page.waitForTimeout(800);
}

async function escolherModo(page, modo = 'me_orientem') {
  const card = page.locator(`[data-mode="${modo}"]`);
  await card.waitFor({ state: 'visible', timeout: T.med });
  await card.click();
  await page.waitForTimeout(600);
}

async function escolherVeiculo(page, cat = 'popular') {
  const card = page.locator(`[data-categoria="${cat}"]`);
  await card.waitFor({ state: 'visible', timeout: T.med });
  await card.click();
  await page.waitForTimeout(800);
}

async function escolherServico(page, slug = 'pneu') {
  const card = page.locator(`[data-servico="${slug}"]`);
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
    await check(page, 'escolher Carro', async () => { await escolherVeiculo(page, 'popular'); });
    await check(page, 'escolher sintoma Pneu', async () => { await escolherServico(page, 'pneu'); });
    await check(page, 'card A visivel (assistencia)', async () => {
      const card = page.locator('[data-action="aceitar-local"]');
      await card.waitFor({ state: 'visible', timeout: T.long });
      const preco = await card.locator('.card-price').textContent();
      expect(preco).toMatch(/R\$\s*\d/);
      return preco;
    });
    await check(page, 'card B visivel (reboque)', async () => {
      const card = page.locator('[data-action="aceitar-reboque"]');
      await card.waitFor({ state: 'visible', timeout: T.med });
      return 'ok';
    });
  });

  test('1a.2 — Levar o carro pula cards e vai pro destino', async ({ page }) => {
    await check(page, 'preencher origem', async () => { await preencherOrigem(page); });
    await check(page, 'escolher Levar o carro', async () => { await escolherModo(page, 'levar_carro'); });
    await check(page, 'escolher Carro', async () => { await escolherVeiculo(page, 'popular'); });
    await check(page, 'destino aparece (sem cards)', async () => {
      const destino = page.locator('#stage-destino');
      await destino.waitFor({ state: 'visible', timeout: T.med });
      const cards = await page.locator('[data-action="aceitar-local"]').count();
      expect(cards).toBe(0);
      return 'destino direto';
    });
  });

  test('1a.3 - Fallback WhatsApp (mock sem oficina)', async ({ page }) => {
    await page.route('**/api/pre-cotacao/opcoes**', async (route) => {
      const url = route.request().url();
      if (url.includes('modo=orientacao')) {
        return route.fulfill({
          status: 200, contentType: 'application/json',
          body: JSON.stringify({ ok: true, data: {
            modo: 'orientacao', disponivel: false, fallback_tipo: 'suporte',
            servico: 'pneu', mensagem: 'Sem oficina para pneu.',
          }, error: null }),
        });
      }
      return route.continue();
    });

    await check(page, 'preencher origem', async () => { await preencherOrigem(page); });
    await check(page, 'escolher Me orientem', async () => { await escolherModo(page, 'me_orientem'); });
    await check(page, 'escolher Carro', async () => { await escolherVeiculo(page, 'popular'); });
    await check(page, 'sintoma Pneu cai no WhatsApp', async () => {
      await escolherServico(page, 'pneu');
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