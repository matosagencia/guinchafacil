// tests/e2e/fluxo-1a.spec.js
// Teste E2E do Fluxo 1a — "Me orientem"
// Roda contra http://localhost:8080

const { test, expect } = require('@playwright/test');

const BASE = 'http://localhost:8080';
const SCREENSHOT_DIR = './tests/e2e/screenshots';

test.describe('Fluxo 1a — Me orientem', () => {
  test.use({ viewport: { width: 1366, height: 900 } });

  test('Caminho completo: endereço → situação → sintoma → cards A/B → reboque → destino', async ({ page }) => {
    const log = [];
    const snap = async (name) => {
      await page.screenshot({ path: `${SCREENSHOT_DIR}/${name}.png`, fullPage: true });
      log.push(`📸 ${name}`);
    };
    const check = async (label, fn) => {
      try {
        const r = await fn();
        log.push(`✅ ${label}${r ? ' — ' + r : ''}`);
        return r;
      } catch (e) {
        log.push(`❌ ${label} — ${e.message.split('\n')[0]}`);
        throw e;
      }
    };

    console.log('\n═══ INÍCIO DO TESTE ═══\n');

    // ═══ PASSO 1: abrir pré-cotação ═══
    await check('GET /pre-cotacao responde 200', async () => {
      const resp = await page.goto(BASE + '/pre-cotacao', { waitUntil: 'domcontentloaded' });
      expect(resp.status()).toBe(200);
      return 'status ' + resp.status();
    });
    await snap('01-precotacao-inicial');

    // ═══ PASSO 2: preencher endereço ═══
    await check('campo #inputOrigem existe', async () => {
      await expect(page.locator('#inputOrigem')).toBeVisible({ timeout: 5000 });
      return 'visível';
    });

    await page.locator('#inputOrigem').fill('Rua da Gamboa, 247');
    await page.locator('#numeroOrigem').fill('247');
    await snap('02-endereco-preenchido');

    // ═══ PASSO 3: usar GPS (força localização próxima às oficinas) ═══
    await check('botão #btnGps existe', async () => {
      await expect(page.locator('#btnGps')).toBeVisible();
      return 'visível';
    });

    // Simula clique no GPS — a coordenada real vem do navegador
    await page.locator('#btnGps').click();
    await page.waitForTimeout(3000);
    await snap('03-apos-gps');

    // ═══ PASSO 4: verificar que situação apareceu ═══
    await check('evento prequote:location-confirmed disparou — sintomaStage oculto, situacaoStage visível', async () => {
      const state = await page.evaluate(() => ({
        situacaoHidden: document.getElementById('situacaoStage')?.hidden,
        sintomaHidden: document.getElementById('sintomaStage')?.hidden,
        decisionHidden: document.getElementById('decisionStage')?.hidden,
        destinoHidden: document.getElementById('destinoBox')?.hidden,
        bodyClass: document.body.className,
        lat: document.getElementById('lat_origem')?.value,
        lng: document.getElementById('lng_origem')?.value,
      }));
      log.push(`   estado: ${JSON.stringify(state)}`);
      expect(state.lat).not.toBe('');
      expect(state.lng).not.toBe('');
      return `lat=${state.lat} lng=${state.lng} body="${state.bodyClass}"`;
    });

    // ═══ PASSO 5: clicar em "Me orientem" ═══
    await check('card "Me orientem" visível', async () => {
      const card = page.locator('[data-choice-group="tipo_problema"][data-choice-value="me_orientem"]');
      await expect(card).toBeVisible();
      return 'visível';
    });

    await page.locator('[data-choice-group="tipo_problema"][data-choice-value="me_orientem"]').click();
    await page.waitForTimeout(500);
    await snap('04-me-orientem-clicado');

    // ═══ PASSO 6: verificar que sintomaStage apareceu ═══
    await check('sintomaStage visível após clique', async () => {
      const hidden = await page.locator('#sintomaStage').evaluate(el => el.hidden);
      expect(hidden).toBe(false);
      return 'visível';
    });

    await check('sintomaStage tem 5 cards', async () => {
      const cards = await page.locator('[data-choice-group="sintoma"]').count();
      expect(cards).toBeGreaterThanOrEqual(5);
      return `${cards} cards`;
    });

    // ═══ PASSO 7: clicar em "Pneu" ═══
    await check('card "Pneu" visível', async () => {
      const card = page.locator('[data-choice-group="sintoma"][data-choice-value="pneu"]');
      await expect(card).toBeVisible();
      return 'visível';
    });

    // Captura do console para ver o fetch
    const apiCalls = [];
    page.on('response', resp => {
      if (resp.url().includes('/api/pre-cotacao/decisao')) {
        apiCalls.push({ url: resp.url(), status: resp.status() });
      }
    });

    await page.locator('[data-choice-group="sintoma"][data-choice-value="pneu"]').click();
    await page.waitForTimeout(2500);
    await snap('05-pneu-clicado');

    // ═══ PASSO 8: verificar que decisionStage apareceu ═══
    await check('decisionStage visível após clique em Pneu', async () => {
      const hidden = await page.locator('#decisionStage').evaluate(el => el.hidden);
      expect(hidden).toBe(false);
      return 'visível';
    });

    await check('API /api/pre-cotacao/decisao foi chamada com 200', async () => {
      expect(apiCalls.length).toBeGreaterThan(0);
      const okCall = apiCalls.find(c => c.status === 200);
      expect(okCall).toBeTruthy();
      return `${apiCalls.length} chamada(s), status ${apiCalls[0].status}`;
    });

    await check('cards A e B visíveis', async () => {
      const aHidden = await page.locator('#decisionAssistencia').evaluate(el => el.hidden);
      const bHidden = await page.locator('#decisionReboque').evaluate(el => el.hidden);
      expect(aHidden).toBe(false);
      expect(bHidden).toBe(false);
      return 'ambos visíveis';
    });

    await check('card A tem SVG de mecânico', async () => {
      const svgCount = await page.locator('#decisionAssistencia svg').count();
      expect(svgCount).toBeGreaterThan(0);
      return `${svgCount} SVG(s)`;
    });

    await check('card B tem SVG de guincho', async () => {
      const svgCount = await page.locator('#decisionReboque svg').count();
      expect(svgCount).toBeGreaterThan(0);
      return `${svgCount} SVG(s)`;
    });

    await check('card A mostra preço de assistência', async () => {
      const txt = await page.locator('#assistenciaPrice').textContent();
      expect(txt).toMatch(/R\$\s*\d/);
      return txt.trim();
    });

    // ═══ PASSO 9: verificar que originMapPanel ESTÁ OCULTO ═══
    await check('originMapPanel oculto em decisão', async () => {
      const display = await page.locator('#originMapPanel').evaluate(el => getComputedStyle(el).display);
      const bodyClass = await page.locator('body').evaluate(el => el.className);
      log.push(`   originMapPanel display=${display} | body="${bodyClass}"`);
      expect(display).toBe('none');
      return `display=none`;
    });

    await check('#btnCotacao ainda OCULTO (só aparece em destino)', async () => {
      const display = await page.locator('#btnCotacao').evaluate(el => getComputedStyle(el).display);
      expect(display).toBe('none');
      return 'display=none';
    });

    // ═══ PASSO 10: clicar no card B (Reboque) ═══
    await check('card B é clicável', async () => {
      const cursor = await page.locator('#decisionReboque').evaluate(el => getComputedStyle(el).cursor);
      expect(cursor).toBe('pointer');
      return `cursor: ${cursor}`;
    });

    await page.locator('#decisionReboque').click();
    await page.waitForTimeout(1500);
    await snap('06-reboque-clicado');

    // ═══ PASSO 11: verificar destinoBox + mapa ═══
    await check('destinoBox visível após clicar reboque', async () => {
      const hidden = await page.locator('#destinoBox').evaluate(el => el.hidden);
      expect(hidden).toBe(false);
      return 'visível';
    });

    await check('body tem class "stage-destino"', async () => {
      const cls = await page.locator('body').evaluate(el => el.className);
      expect(cls).toContain('stage-destino');
      return cls;
    });

    await check('#btnCotacao VISÍVEL agora (destino)', async () => {
      const display = await page.locator('#btnCotacao').evaluate(el => getComputedStyle(el).display);
      expect(display).not.toBe('none');
      return `display=${display}`;
    });

    await check('mapa de destino carregou (Leaflet)', async () => {
      const mapExists = await page.locator('#destinoMapWrap .leaflet-container').count();
      expect(mapExists).toBeGreaterThan(0);
      return 'mapa renderizado';
    });

    // ═══ PASSO 12: simular clique no mapa de destino ═══
    await check('clique no mapa define lat_destino', async () => {
      const mapBox = await page.locator('#destinoMapWrap').boundingBox();
      if (!mapBox) throw new Error('mapa sem bounding box');
      await page.mouse.click(mapBox.x + mapBox.width / 2, mapBox.y + mapBox.height / 2);
      await page.waitForTimeout(1000);
      const latDest = await page.locator('#lat_destino').evaluate(el => el.value);
      expect(latDest).not.toBe('');
      return `lat_destino=${latDest}`;
    });
    await snap('07-destino-marcado');

    // ═══ PASSO 13: verificar que preço do reboque foi recalculado ═══
    await check('preço do reboque calculado após marcar destino', async () => {
      const txt = await page.locator('#reboqueDeslocamento').textContent();
      expect(txt).toMatch(/R\$\s*\d/);
      return txt.trim();
    });

    // ═══ RELATÓRIO FINAL ═══
    console.log('\n═══ RELATÓRIO ═══');
    log.forEach(l => console.log(l));
    console.log('\n═══ FIM ═══\n');
  });
});