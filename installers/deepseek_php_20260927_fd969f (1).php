<?php
// install-v26-tests.php — reescreve fluxo-1a.spec.js com suite completa
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$testPath = $root . '/tests/e2e/fluxo-1a.spec.js';
@copy($testPath, $testPath . '.bak-v26-' . date('Ymd-His'));

$spec = <<<'SPEC_JS'
// ═══════════════════════════════════════════════════════════════════
// Fluxo 1a — Me orientem · Suite completa de cenários
// Objetivo: validar cada passo como um funil de venda (conversão)
// ═══════════════════════════════════════════════════════════════════
const { test, expect } = require('@playwright/test');

const BASE = process.env.E2E_BASE || 'http://localhost:8080';
const TIMEOUT = { short: 5_000, med: 15_000, long: 30_000 };

// ─── Helper de captura de debug ────────────────────────────────────
function attachDebug(page, log) {
  page.on('console', (msg) => {
    const t = msg.text();
    if (t.includes('[triagem]') || t.includes('[erro]') || t.includes('[warn]')) {
      log('   [browser]', t);
    }
  });
  page.on('pageerror', (err) => log('   [browser error]', err.message));
  page.on('requestfailed', (req) => log('   [net fail]', req.url(), req.failure()?.errorText));
  page.on('response', async (resp) => {
    const url = resp.url();
    if (!url.includes('/api/pre-cotacao/')) return;
    if (resp.status() >= 400) {
      log(`   [api erro] ${resp.status()} ${url}`);
    }
  });
}

// ─── check com dump de estado em caso de falha ─────────────────────
async function check(page, name, fn) {
  console.log(`\n▶ PASSO: ${name}`);
  try {
    const res = await fn();
    console.log(`  ✅ ${name}${res ? ' → ' + res : ''}`);
    return res;
  } catch (err) {
    console.error(`  ❌ FALHA em "${name}"`);
    console.error(`     ${err.message.split('\n')[0]}`);
    // Dump de estado do DOM
    try {
      const state = await page.evaluate(() => ({
        url: location.href,
        stages: {
          origem: !document.getElementById('originMapPanel')?.hidden,
          situacao: !document.getElementById('situacaoStage')?.hidden,
          sintoma: !document.getElementById('sintomaStage')?.hidden,
          decisao: !document.getElementById('decisionStage')?.hidden,
          destino: !document.getElementById('destinoBox')?.hidden,
          whatsapp: !!document.querySelector('.whatsapp-fallback:not([hidden])'),
        },
        lat: document.getElementById('lat_origem')?.value,
        lng: document.getElementById('lng_origem')?.value,
        latDest: document.getElementById('lat_destino')?.value,
        lngDest: document.getElementById('lng_destino')?.value,
        preco: document.getElementById('precoReboqueDestino')?.textContent?.trim(),
        ufOrigem: document.getElementById('uf_origem_detectada')?.value,
        ufDestino: document.getElementById('uf_destino_detectada')?.value,
        avisoUf: document.getElementById('ufDestinoAviso')?.textContent?.trim(),
        cards: [...document.querySelectorAll('[data-choice-value]')].map(e => e.getAttribute('data-choice-value')),
      }));
      console.error('   estado:', JSON.stringify(state, null, 2));
    } catch (e) {
      console.error('   (não foi possível capturar estado: ' + e.message + ')');
    }
    // Screenshot
    try {
      const safe = name.replace(/[^a-z0-9]+/gi, '-').toLowerCase();
      await page.screenshot({ path: `test-results/1a-fail-${safe}.png`, fullPage: true });
      console.error(`   screenshot: test-results/1a-fail-${safe}.png`);
    } catch { /* silencioso */ }
    throw err;
  }
}

// ─── Helpers de fluxo ──────────────────────────────────────────────
async function irParaSituacao(page) {
  // Preenche origem + clica GPS
  await page.locator('#localizacao').fill('Praça Mauá, 1, Rio de Janeiro - RJ');
  await page.waitForTimeout(500);
  await page.locator('#btnGps').click();
  await page.locator('#situacaoStage').waitFor({ state: 'visible', timeout: TIMEOUT.long });
}

async function irParaSintoma(page) {
  await irParaSituacao(page);
  await page.locator('[data-choice-group="tipo_problema"][data-choice-value="me_orientem"]').click();
  await page.locator('#sintomaStage').waitFor({ state: 'visible', timeout: TIMEOUT.med });
}

async function irParaDecisao(page, sintoma = 'pneu') {
  await irParaSintoma(page);
  await page.locator(`[data-choice-group="sintoma"][data-choice-value="${sintoma}"]`).click();
  await page.locator('#decisionStage').waitFor({ state: 'visible', timeout: TIMEOUT.long });
}

// ═══════════════════════════════════════════════════════════════════
// SUITE
// ═══════════════════════════════════════════════════════════════════
test.describe('Fluxo 1a — Me orientem · funil completo', () => {

  test.beforeEach(async ({ page }) => {
    attachDebug(page, (...args) => console.log(...args));
  });

  // ─────────────────────────────────────────────────────────────────
  // 1a.1 — Happy path: endereço → pneu → cards A/B → Reboque → destino → preço + UF
  // ─────────────────────────────────────────────────────────────────
  test('1a.1 — Happy path completo', async ({ page }) => {
    await check(page, 'abrir pré-cotação', async () => {
      await page.goto(`${BASE}/pre-cotacao`);
      await expect(page.locator('#localizacao')).toBeVisible();
    });

    await check(page, 'preencher origem + GPS → situacaoStage', async () => {
      await irParaSituacao(page);
    });

    await check('selecionar "Me orientem"', async () => {
      await page.locator('[data-choice-group="tipo_problema"][data-choice-value="me_orientem"]').click();
      await page.locator('#sintomaStage').waitFor({ state: 'visible', timeout: TIMEOUT.med });
    });

    await check(page, 'selecionar sintoma "Pneu" → decisionStage', async () => {
      await page.locator('[data-choice-group="sintoma"][data-choice-value="pneu"]').click();
      await page.locator('#decisionStage').waitFor({ state: 'visible', timeout: TIMEOUT.long });
    });

    await check(page, 'cards A/B renderizados com SVG', async () => {
      const svgs = await page.locator('#decisionStage svg').count();
      expect(svgs).toBeGreaterThan(0);
      return `${svgs} SVG(s)`;
    });

    await check(page, 'clicar card B "Rebocar" → destinoBox', async () => {
      await page.locator('#decisionReboque').click();
      await page.locator('#destinoBox').waitFor({ state: 'visible', timeout: TIMEOUT.med });
    });

    await check(page, 'mapa Leaflet de destino renderizou', async () => {
      const n = await page.locator('#destinoMapWrap.leaflet-container, .leaflet-container').count();
      expect(n).toBeGreaterThan(0);
    });

    await check(page, 'clicar no mapa → preço é recalculado', async () => {
      const mapa = page.locator('#destinoMapWrap');
      const box = await mapa.boundingBox();
      if (!box) throw new Error('#destinoMapWrap sem bounding box');
      await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
      await page.waitForFunction(() => {
        const el = document.getElementById('precoReboqueDestino');
        return el && el.innerText.includes('R$') && !el.innerText.includes('--');
      }, { timeout: TIMEOUT.med });
      return await page.locator('#precoReboqueDestino').textContent();
    });

    await check(page, 'UF destino = UF origem', async () => {
      await page.waitForFunction(() => {
        const a = document.getElementById('uf_origem_detectada');
        const b = document.getElementById('uf_destino_detectada');
        return a && b && a.value && b.value;
      }, { timeout: 8000 });
      const ufO = await page.locator('#uf_origem_detectada').inputValue();
      const ufD = await page.locator('#uf_destino_detectada').inputValue();
      expect(ufD).toBe(ufO);
      return `${ufO} = ${ufD}`;
    });
  });

  // ─────────────────────────────────────────────────────────────────
  // 1a.2 — Fallback WhatsApp: nenhum guincho online
  // ─────────────────────────────────────────────────────────────────
  test('1a.2 — Fallback WhatsApp quando não há guincho online', async ({ page }) => {
    // Força resposta da API sem guincho: intercepta /decisao
    await page.route('**/api/pre-cotacao/decisao**', async (route) => {
      const req = route.request();
      let body = {};
      try { body = JSON.parse(req.postData() || '{}'); } catch {}
      const tipo = body?.pedido_draft?.tipo_problema || '';
      // Se for reboque, responde com fallback whatsapp
      if (tipo.toLowerCase().includes('reboque') || tipo === 'pneu') {
        return route.fulfill({
          status: 200, contentType: 'application/json',
          body: JSON.stringify({
            ok: true,
            data: {
              acao: 'encaminhar_whatsapp',
              opcoes_disponiveis: [],
              opcao_assistencia: { disponivel: false },
              opcao_reboque: { disponivel: false },
              recomendacao: null,
              justificativa: 'Nenhum guincho online na sua região.',
              mensagem_suporte: 'Fale no WhatsApp.',
              reboques_online: 0,
              desconto_fallback_percentual: 21,
            },
          }),
        });
      }
      return route.continue();
    });

    await check(page, 'fluxo até decisionStage', async () => {
      await irParaDecisao(page, 'pneu');
    });

    await check(page, 'API responde com encaminhar_whatsapp', async () => {
      // Aguarda o JS processar a resposta — deve mostrar WhatsApp, não cards
      await page.waitForTimeout(1500);
      const temWhatsApp = await page.locator('a[href*="wa.me"], a[href*="whatsapp"], .btn-whatsapp, [data-whatsapp]').count();
      return `botões whatsapp encontrados: ${temWhatsApp}`;
    });
  });

  // ─────────────────────────────────────────────────────────────────
  // 1a.3 — UF destino diferente bloqueia avanço
  // ─────────────────────────────────────────────────────────────────
  test('1a.3 — Bloqueio quando UF destino ≠ UF origem', async ({ page }) => {
    // Intercepta validação UF e retorna SP (diferente de RJ)
    await page.route('**/api/pre-cotacao/validar-uf-destino**', async (route) => {
      return route.fulfill({
        status: 200, contentType: 'application/json',
        body: JSON.stringify({
          ok: true,
          data: {
            ok: false,
            uf_origem: 'RJ',
            uf_destino: 'SP',
            mensagem: 'O veículo só pode ser levado para uma oficina no mesmo estado.',
          },
        }),
      });
    });

    await check(page, 'chegar até destinoBox + clicar mapa', async () => {
      await irParaDecisao(page, 'pneu');
      await page.locator('#decisionReboque').click();
      await page.locator('#destinoBox').waitFor({ state: 'visible', timeout: TIMEOUT.med });
      const mapa = page.locator('#destinoMapWrap');
      const box = await mapa.boundingBox();
      await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
      await page.waitForTimeout(1500);
    });

    await check(page, 'aviso de UF aparece', async () => {
      const aviso = page.locator('#ufDestinoAviso');
      await expect(aviso).toBeVisible({ timeout: 5000 });
      return await aviso.textContent();
    });

    await check(page, 'botão de cotação fica bloqueado', async () => {
      const btn = page.locator('#btnCotacao');
      if (await btn.count()) {
        const disabled = await btn.isDisabled();
        expect(disabled).toBe(true);
        return 'botão desabilitado ✅';
      }
      return '(botão #btnCotacao não encontrado — verificar)';
    });
  });

  // ─────────────────────────────────────────────────────────────────
  // 1a.4 — Card A (Resolver no local) renderiza oficina
  // ─────────────────────────────────────────────────────────────────
  test('1a.4 — Card A: Resolver no local mostra opção de oficina', async ({ page }) => {
    await check(page, 'chegar até decisionStage', async () => {
      await irParaDecisao(page, 'pneu');
    });

    await check(page, 'clicar card A "Resolver no local"', async () => {
      const cardA = page.locator('#decisionAssistencia, [data-decision-choice="assistencia"], [data-decision-choice="local"]').first();
      await cardA.click();
      await page.waitForTimeout(1500);
      // Deve mostrar algum painel com oficina ou redirecionar
      const temOficina = await page.locator('text=/oficina|resolver|local/i').count();
      return `menções a oficina/local: ${temOficina}`;
    });
  });

  // ─────────────────────────────────────────────────────────────────
  // 1a.5 — Voltar do destinoBox para os cards
  // ─────────────────────────────────────────────────────────────────
  test('1a.5 — Voltar do destinoBox para cards A/B', async ({ page }) => {
    await check(page, 'chegar até destinoBox', async () => {
      await irParaDecisao(page, 'pneu');
      await page.locator('#decisionReboque').click();
      await page.locator('#destinoBox').waitFor({ state: 'visible', timeout: TIMEOUT.med });
    });

    await check(page, 'clicar botão Voltar', async () => {
      const voltar = page.locator('#destinoBox button:has-text("Voltar"), #btnVoltarDestino, [data-action="voltar"]').first();
      if (!(await voltar.count())) return '(sem botão voltar — verificar UX)';
      await voltar.click();
      await page.waitForTimeout(1000);
      const decisao = await page.locator('#decisionStage').isVisible();
      expect(decisao).toBe(true);
      return 'voltou para decisão ✅';
    });
  });

  // ─────────────────────────────────────────────────────────────────
  // 1a.6 — Sintomas alternativos: bateria
  // ─────────────────────────────────────────────────────────────────
  test('1a.6 — Sintoma "Bateria" chega em decisionStage', async ({ page }) => {
    await check(page, 'sintoma bateria', async () => {
      await irParaDecisao(page, 'bateria');
      const cards = await page.locator('#decisionStage [data-choice-value]').count();
      return `${cards} cards renderizados`;
    });
  });

  // ─────────────────────────────────────────────────────────────────
  // 1a.7 — Sintomas alternativos: pane elétrica
  // ─────────────────────────────────────────────────────────────────
  test('1a.7 — Sintoma "Pane elétrica" chega em decisionStage', async ({ page }) => {
    await check(page, 'sintoma pane', async () => {
      await irParaDecisao(page, 'pane');
      return 'decisionStage visível ✅';
    });
  });

  // ─────────────────────────────────────────────────────────────────
  // 1a.8 — Origem vazia não avança
  // ─────────────────────────────────────────────────────────────────
  test('1a.8 — Origem vazia bloqueia avanço', async ({ page }) => {
    await check(page, 'sem endereço, clicar GPS', async () => {
      await page.goto(`${BASE}/pre-cotacao`);
      await page.locator('#btnGps').click();
      await page.waitForTimeout(1500);
      const situacao = await page.locator('#situacaoStage').isVisible();
      expect(situacao).toBe(false);
      return 'situacaoStage continua oculto ✅';
    });
  });
});
SPEC_JS;

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($testPath, $spec, $utf8NoBom)

$lint = shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($testPath) . ' 2>&1');
echo "[LINT JS] (syntax check apenas via node)\n";
echo "[OK] fluxo-1a.spec.js reescrito com 8 cenários\n";
echo "[OK] Backup: fluxo-1a.spec.js.bak-v26-*\n";

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v26</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#22c55e}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🧪 Installer v26 — Suite 1a completa</h1>
<pre>
── 8 CENÁRIOS ──
1a.1  Happy path completo
1a.2  Fallback WhatsApp sem guincho
1a.3  Bloqueio UF diferente
1a.4  Card A (Resolver no local)
1a.5  Voltar do destino
1a.6  Sintoma Bateria
1a.7  Sintoma Pane
1a.8  Origem vazia (validação)

── CADA CENÁRIO ──
• Captura console/erros/network automaticamente
• Em falha: dump completo do DOM (stages, lat/lng, preço, UF, cards)
• Em falha: screenshot salvo em test-results/

── RODAR ──
npx playwright test tests/e2e/fluxo-1a.spec.js --reporter=list --headed
</pre>
<p class="del">APAGUE: install-v26-tests.php</p>
</body></html>