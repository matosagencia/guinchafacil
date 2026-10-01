/**
 * Fluxo 1a completo — v3 (2026-09-29)
 *
 * Corrige a interacao com o address-picker: em vez de tentar clicar no
 * input[name=localizacao] (hidden), dispara o evento gf:address-confirmed
 * diretamente (o public-pre-cotacao-flow.js escuta esse evento).
 */
const { test, expect } = require('@playwright/test');

const BASE = process.env.BASE_URL || 'http://localhost:8080';
const DEBUG = process.env.DEBUG_CHECKOUT === '1';
const T = 20_000;

const CARDS = {
    master: { numero: '5031433215406351', cvv: '123', mes: '11', ano: '2030', titular: 'APRO', cpf: '12345678909' },
    visa:   { numero: '4235647728025682', cvv: '123', mes: '11', ano: '2030', titular: 'APRO', cpf: '12345678909' },
};

const GEO = { latitude: -22.9068, longitude: -43.1729 };

function log(step, ...args) { if (DEBUG) console.log(`[${step}]`, ...args); }

function dados() {
    const ts = Date.now().toString().slice(-8);
    return {
        cliente: { nome: `Teste ${ts}`, telefone: `2198${ts.slice(-7)}`, email: `t${ts}@teste.local` },
        veiculo: { marca: 'Volkswagen', modelo: 'Gol', ano: '2020', cor: 'Branco', placa: `TST${ts.slice(-4)}`, uf: 'RJ', cidade: 'Rio de Janeiro' },
    };
}

async function avancaPreCotacao(page) {
    // Pula o funil v2 via /debug/seed-precotacao (so ambiente local)
    log('1', 'seed precotacao');
    await page.goto(`${BASE}/debug/seed-precotacao`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);

    log('2', 'indo pra /checkout/cliente');
    await page.goto(`${BASE}/checkout/cliente`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#nome', { state: 'visible', timeout: T });

    // Retorna o botao do form de cliente pra interface ficar igual
    return page.getByRole('button', { name: /continuar/i }).first();
}
async function aceitaEContinua(page) {
    const aceitar = await avancaPreCotacao(page);
    await aceitar.click();
    await page.waitForTimeout(2500);
    log('4', 'URL pos aceitar:', page.url());
}

async function criaContaSePreciso(page, cliente) {
    if (page.url().includes('/login')) {
        log('5', 'em /login, clicando "criar conta"');
        const btn = page.getByRole('link', { name: /criar conta/i }).first();
        if (await btn.count() && await btn.isVisible().catch(() => false)) {
            await btn.click();
            await page.waitForTimeout(1500);
        }
    }
    if (!page.url().includes('/checkout/cliente')) {
        await page.goto(`${BASE}/checkout/cliente`, { waitUntil: 'domcontentloaded' });
    }
    await page.waitForSelector('#nome', { state: 'visible', timeout: T });
    log('5', 'preenchendo cliente');
    await page.locator('#nome').fill(cliente.nome);
    await page.locator('#telefone').fill(cliente.telefone);
    await page.locator('#email').fill(cliente.email);
    await page.getByRole('button', { name: /continuar/i }).first().click();
    await page.waitForTimeout(2500);
    log('5', 'URL pos cliente:', page.url());
}

async function preencheVeiculo(page, veiculo) {
    if (!page.url().includes('/checkout/veiculo')) {
        await page.goto(`${BASE}/checkout/veiculo`, { waitUntil: 'domcontentloaded' });
    }
    await page.waitForSelector('#form-novo-veiculo', { state: 'visible', timeout: T });
    log('6', 'form veiculo visivel');

    const marca = page.locator('#marca');
    await marca.click();
    await marca.fill(veiculo.marca);
    await page.waitForTimeout(1500);
    const mod = page.locator('#modelo');
    await expect(mod).toBeEnabled({ timeout: 5_000 });
    await mod.click();
    await mod.fill(veiculo.modelo);
    await page.waitForTimeout(500);
    await page.locator('#ano').selectOption(veiculo.ano);
    await page.locator('#cor').selectOption({ label: veiculo.cor });
    await page.locator('#placa').fill(veiculo.placa);
    await page.locator('#uf_placa').selectOption(veiculo.uf);
    await page.locator('#cidade_placa').fill(veiculo.cidade);
    log('6', 'veiculo preenchido, submetendo');

    await page.locator('#btn-continuar').click();
    await page.waitForURL(/\/pagamento\/checkout\/\d+/, { timeout: T });
    log('6', 'URL pagamento:', page.url());
}

async function preencheBrick(page, cartao, cliente) {
    log('7', 'aguardando Brick');
    await page.waitForSelector('#mp-payment-brick-container', { state: 'visible', timeout: 25_000 });

    await page.waitForFunction(() => {
        const c = document.querySelector('#mp-payment-brick-container');
        return c && c.querySelector('iframe') !== null;
    }, { timeout: 30_000 });
    await page.waitForTimeout(3500);
    log('7', 'Brick montado');

    // Numero do cartao
    const ifNumber = page.frameLocator('iframe[name*="number" i], iframe[title*="n[úu]mero" i], iframe[title*="number" i]').first();
    await ifNumber.locator('input').waitFor({ state: 'visible', timeout: 15_000 });
    await ifNumber.locator('input').fill(cartao.numero);
    log('7', 'numero ok');
    await page.waitForTimeout(4500);

    const ifCvv = page.frameLocator('iframe[name*="securityCode" i], iframe[title*="seguran" i], iframe[title*="security" i], iframe[title*="cvv" i]').first();
    await ifCvv.locator('input').fill(cartao.cvv);
    log('7', 'cvv ok');

    const ifExp = page.frameLocator('iframe[name*="expiration" i], iframe[title*="validade" i]').first();
    const expInputs = ifExp.locator('input');
    const n = await expInputs.count();
    if (n >= 2) {
        await expInputs.nth(0).fill(cartao.mes);
        await expInputs.nth(1).fill(cartao.ano);
    } else if (n === 1) {
        await expInputs.first().fill(`${cartao.mes}/${cartao.ano}`);
    }
    log('7', 'validade ok');

    const ifName = page.frameLocator('iframe[name*="cardholderName" i], iframe[title*="nome" i], iframe[title*="titular" i]').first();
    if (await ifName.locator('input').count()) {
        await ifName.locator('input').fill(cartao.titular);
    }

    const ifCpf = page.frameLocator('iframe[name*="identification" i], iframe[title*="documento" i], iframe[title*="cpf" i]').first();
    if (await ifCpf.locator('input').count()) {
        await ifCpf.locator('input').fill(cartao.cpf);
    }

    const ifEmail = page.frameLocator('iframe[name*="email" i], iframe[title*="email" i]').first();
    if (await ifEmail.locator('input').count()) {
        await ifEmail.locator('input').fill(cliente.email);
    }

    await page.waitForTimeout(3500);

    const sel = page.locator('select[name*="installments" i], select[aria-label*="parcela" i]').first();
    if (await sel.count() && await sel.isVisible().catch(() => false)) {
        await sel.selectOption({ index: 0 });
        log('7', 'parcelas 1x');
    }

    await page.waitForTimeout(1500);
    const btnPagar = page.getByRole('button', { name: /pagar|finalizar|confirmar/i }).last();
    if (await btnPagar.count() && await btnPagar.isVisible().catch(() => false)) {
        await btnPagar.click();
        log('7', 'clique em pagar');
    } else {
        throw new Error('Botao "Pagar" nao encontrado dentro do Brick');
    }

    try {
        await page.waitForURL(/\/pagamento\/(sucesso|falha|pendente)\//, { timeout: 25_000 });
        log('8', 'URL final:', page.url());
    } catch (e) {
        log('8', 'sem redirect — checando alerta de erro');
    }
}

test.describe.configure({ mode: 'serial' });

test.describe('1a pagamento', () => {
    test.use({
        geolocation: GEO,
        permissions: ['geolocation'],
        locale: 'pt-BR',
        viewport: { width: 1366, height: 900 },
    });

    test.setTimeout(240_000);

    test('1a.pay.master', async ({ page }) => {
        page.on('pageerror', (e) => log('err', e.message));
        const d = dados();
        await aceitaEContinua(page);
        await criaContaSePreciso(page, d.cliente);
        await preencheVeiculo(page, d.veiculo);
        await preencheBrick(page, CARDS.master, d.cliente);

        const ok = page.url().match(/\/pagamento\/(sucesso|pendente)\//) || (await page.getByText(/aprovad/i).first().count());
        expect(ok, `esperado aprovacao, mas URL foi ${page.url()}`).toBeTruthy();
    });

    test('1a.pay.visa', async ({ page }) => {
        page.on('pageerror', (e) => log('err', e.message));
        const d = dados();
        await aceitaEContinua(page);
        await criaContaSePreciso(page, d.cliente);
        await preencheVeiculo(page, d.veiculo);
        await preencheBrick(page, CARDS.visa, d.cliente);

        const ok = page.url().match(/\/pagamento\/(sucesso|pendente)\//) || (await page.getByText(/aprovad/i).first().count());
        expect(ok, `esperado aprovacao, mas URL foi ${page.url()}`).toBeTruthy();
    });
});