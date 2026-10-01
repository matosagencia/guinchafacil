/**
 * Fluxo 1a COMPLETO — funciona em produção ou local.
 *
 *   /pre-cotacao (funil v2: endereco -> modo -> veiculo -> sintoma -> opcoes -> cotacao)
 *   -> /login (cliente existente)
 *   -> /checkout/veiculo
 *   -> /pagamento/checkout/{id}
 *   -> Brick MP -> cartao teste APRO
 *   -> /pagamento/sucesso/{id}
 *
 * Como rodar:
 *   Producao (default):
 *     npx playwright test tests/e2e/checkout-1a-completo-prod.spec.js --headed --workers=1
 *
 *   Local:
 *     $env:BASE_URL='http://localhost:8080'
 *     npx playwright test tests/e2e/checkout-1a-completo-prod.spec.js --headed --workers=1
 *
 * Pre-requisitos:
 *   - Cliente cliente1@teste.local / teste123 existente (rodar tools/criar_clientes_teste.php)
 *   - Se local: MP sandbox aceitando (bypass isLocal em validarConfigMercadoPago)
 */
const { test, expect } = require('@playwright/test');

const BASE = process.env.BASE_URL || 'https://guinchafacil.com.br';
const DEBUG = process.env.DEBUG_CHECKOUT === '1';
const T = 30_000;

// Cartao de teste oficial MP sandbox
const CARTAO = {
    numero: '5031433215406351',   // Mastercard aprovado
    cvv: '123',
    mes: '11',
    ano: '2030',
    titular: 'APRO',              // sem sobrenome
    cpf: '12345678909',
};

// Cliente de teste (deve existir na base)
const CLIENTE = {
    email: 'cliente1@teste.local',
    senha: 'teste123',
};

// Endereco de origem (fixo, RJ centro)
const ORIGEM = {
    lat: -22.9068,
    lng: -43.1729,
    label: 'Avenida Presidente Antonio Carlos, 280',
    numero: '280',
    endereco: 'Avenida Presidente Antonio Carlos, 280, Rio de Janeiro',
};

function log(...a) { if (DEBUG) console.log('[1a]', ...a); }

test.describe.configure({ mode: 'serial' });
test.setTimeout(240_000);

test.describe('1a completo (prod/local)', () => {
    test.use({
        locale: 'pt-BR',
        viewport: { width: 1366, height: 900 },
    });

    test('1a.full.master', async ({ page }) => {
        page.on('pageerror', (e) => log('pageerror:', e.message));
        page.on('console', (msg) => {
            const txt = msg.text();
            if (DEBUG && /\[flow|\[checkout-veiculo|\[Brick|\[gf-/.test(txt)) log('console:', txt);
        });

        // ============================================================
        // PASSO 1 — /pre-cotacao + funil v2
        // ============================================================
        await test.step('1. abre /pre-cotacao', async () => {
            log('goto /pre-cotacao');
            await page.goto(`${BASE}/pre-cotacao`, { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(3500);
        });

        await test.step('2. confirma endereco de origem via evento', async () => {
            // O address-picker usa input hidden + dispara gf:address-confirmed em document
            await page.evaluate((o) => {
                document.dispatchEvent(new CustomEvent('gf:address-confirmed', {
                    detail: {
                        role: 'origem',
                        lat: o.lat,
                        lng: o.lng,
                        label: o.label,
                        numero: o.numero,
                        endereco: o.endereco,
                    }
                }));
            }, ORIGEM);
            await page.waitForTimeout(2500);
            log('URL pos endereco:', page.url());
        });

        await test.step('3. avanca funil clicando cards/botoes', async () => {
            for (let i = 0; i < 25; i++) {
                // Saiu do funil?
                const url = page.url();
                if (url.includes('/login') || url.includes('/checkout/')) {
                    log('saiu do funil em:', url);
                    return;
                }

                // Achou "Aceitar e pagar"?
                const aceitar = page.getByRole('button', { name: /aceitar.*pagar|aceitar.*cota/i }).first();
                if (await aceitar.count() && await aceitar.isVisible().catch(() => false)) {
                    log('clicando "Aceitar e pagar"');
                    await aceitar.click();
                    await page.waitForTimeout(2500);
                    return;
                }

                // Tenta cards com data-attr
                let clicou = false;
                const sels = [
                    '[data-mode]', '[data-modo]', '[data-categoria]', '[data-veiculo]',
                    '[data-sintoma]', '[data-servico]', '[data-opcao]', '[data-option]',
                ];
                for (const s of sels) {
                    const c = page.locator(s).first();
                    if (await c.count() && await c.isVisible().catch(() => false)) {
                        log(i, 'clica card:', s);
                        await c.click({ timeout: 2000 }).catch(() => {});
                        await page.waitForTimeout(1200);
                        clicou = true;
                        break;
                    }
                }

                // Se nao achou card, tenta botoes de texto
                if (!clicou) {
                    const nomes = [/confirmar/i, /continuar/i, /avan[çc]ar/i, /pr[óo]ximo/i, /ver cota/i, /calcular/i];
                    for (const n of nomes) {
                        const b = page.getByRole('button', { name: n }).first();
                        if (await b.count() && await b.isVisible().catch(() => false)) {
                            log(i, 'clica botao:', n.toString());
                            await b.click({ timeout: 2000 }).catch(() => {});
                            await page.waitForTimeout(1200);
                            clicou = true;
                            break;
                        }
                    }
                }

                if (!clicou) await page.waitForTimeout(1000);
            }
            throw new Error('Nao saiu do funil. URL: ' + page.url());
        });

        // ============================================================
        // PASSO 2 — login
        // ============================================================
        await test.step('4. login com cliente de teste', async () => {
            await page.waitForURL(/\/login/, { timeout: T });
            log('em /login');

            // Se houver retorno=/checkout/veiculo, o form de login ja esta pronto
            await page.waitForSelector('input[name="email"], #email', { state: 'visible', timeout: T });

            const emailField = page.locator('input[name="email"], #email').first();
            const passField = page.locator('input[name="password"], input[name="senha"], #password').first();

            await emailField.fill(CLIENTE.email);
            await passField.fill(CLIENTE.senha);

            await page.getByRole('button', { name: /entrar/i }).first().click();
            await page.waitForTimeout(2500);
            log('URL pos login:', page.url());
        });

        // ============================================================
        // PASSO 3 — /checkout/veiculo
        // ============================================================
        await test.step('5. preenche veiculo', async () => {
            await page.waitForURL(/\/checkout\/veiculo/, { timeout: T });
            await page.waitForSelector('#form-novo-veiculo', { state: 'visible', timeout: T });
            log('form veiculo visivel');

            await page.locator('#marca').click();
            await page.locator('#marca').fill('Volkswagen');
            await page.waitForTimeout(1500);

            await expect(page.locator('#modelo')).toBeEnabled({ timeout: 6000 });
            await page.locator('#modelo').click();
            await page.locator('#modelo').fill('Gol');
            await page.waitForTimeout(500);

            await page.locator('#ano').selectOption('2020');
            await page.locator('#cor').selectOption({ label: 'Branco' });
            await page.locator('#placa').fill('TST1A23');
            await page.locator('#uf_placa').selectOption('RJ');
            await page.locator('#cidade_placa').fill('Rio de Janeiro');

            await page.locator('#btn-continuar').click();
            await page.waitForURL(/\/pagamento\/checkout\/\d+/, { timeout: T });
            log('URL pagamento:', page.url());
        });

        // ============================================================
        // PASSO 4 — Brick MP
        // ============================================================
        await test.step('6. aguarda Brick', async () => {
            if (!page.url().match(/\/pagamento\/checkout\/\d+/)) {
                throw new Error('Nao esta em /pagamento/checkout/. URL: ' + page.url());
            }
            await page.waitForSelector('#mp-payment-brick-container', { state: 'attached', timeout: 30_000 });
            await page.waitForFunction(() => {
                const c = document.querySelector('#mp-payment-brick-container');
                return c && c.querySelector('iframe') !== null;
            }, { timeout: 30_000 });
            await page.waitForTimeout(4000);
            log('Brick montado');
        });

        await test.step('7. preenche cartao', async () => {
            // Numero
            const num = page.frameLocator('iframe[name*="number" i], iframe[title*="n[úu]mero" i], iframe[title*="number" i]').first();
            await num.locator('input').waitFor({ state: 'visible', timeout: 15_000 });
            await num.locator('input').fill(CARTAO.numero);
            log('numero ok');
            await page.waitForTimeout(4500);

            // CVV
            const cvv = page.frameLocator('iframe[name*="securityCode" i], iframe[title*="seguran" i], iframe[title*="cvv" i]').first();
            await cvv.locator('input').fill(CARTAO.cvv);

            // Validade
            const exp = page.frameLocator('iframe[name*="expiration" i], iframe[title*="validade" i]').first();
            const n = await exp.locator('input').count();
            if (n >= 2) {
                await exp.locator('input').nth(0).fill(CARTAO.mes);
                await exp.locator('input').nth(1).fill(CARTAO.ano);
            } else if (n === 1) {
                await exp.locator('input').first().fill(`${CARTAO.mes}/${CARTAO.ano}`);
            }

            // Nome do titular
            const nome = page.frameLocator('iframe[name*="cardholderName" i], iframe[title*="nome" i], iframe[title*="titular" i]').first();
            if (await nome.locator('input').count()) {
                await nome.locator('input').fill(CARTAO.titular);
            }

            // CPF
            const cpf = page.frameLocator('iframe[name*="identification" i], iframe[title*="cpf" i], iframe[title*="documento" i]').first();
            if (await cpf.locator('input').count()) {
                await cpf.locator('input').fill(CARTAO.cpf);
            }

            await page.waitForTimeout(3500);

            // Parcelas (1x)
            const sel = page.locator('select[name*="installments" i]').first();
            if (await sel.count() && await sel.isVisible().catch(() => false)) {
                await sel.selectOption({ index: 0 });
            }
        });

        await test.step('8. clica Pagar e aguarda', async () => {
            await page.waitForTimeout(1500);
            const btn = page.getByRole('button', { name: /pagar|finalizar|confirmar/i }).last();
            await expect(btn).toBeVisible({ timeout: 10_000 });
            await btn.click();
            log('clicou pagar');

            try {
                await page.waitForURL(/\/pagamento\/(sucesso|pendente)\//, { timeout: 30_000 });
                log('URL final:', page.url());
            } catch (e) {
                log('sem redirect; URL atual:', page.url());
            }
        });

        // ============================================================
        // VALIDACAO FINAL
        // ============================================================
        await test.step('9. valida aprovacao', async () => {
            const url = page.url();
            const ok = url.match(/\/pagamento\/(sucesso|pendente)\//) !== null;
            const textoOk = await page.getByText(/aprovad|pagamento.*confirm/i).first().count();

            // Se ainda esta em /pagamento/checkout/, checa alerta
            if (!ok && !textoOk) {
                const alerta = await page.locator('.alert-danger, .alert-warning').first().textContent().catch(() => '');
                throw new Error(`Pagamento nao aprovado. URL=${url}. Alerta="${alerta}"`);
            }
        });
    });
});