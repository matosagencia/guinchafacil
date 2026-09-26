const { test, expect } = require('@playwright/test');

const cliente = {
  email: process.env.E2E_CLIENT_EMAIL || 'cliente.teste@guinchafacil.dev',
  senha: process.env.E2E_CLIENT_PASSWORD || 'Teste@123',
};

async function screenshot(page, name) {
  await page.screenshot({ path: `test-results/socorro-${name}.png`, fullPage: true });
}

async function login(page) {
  await page.goto('/login');
  await page.locator('input[type="email"], input[name="email"]').fill(cliente.email);
  await page.locator('input[type="password"], input[name="senha"]').fill(cliente.senha);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/cliente|dashboard/);
}

async function abrirPedido(page) {
  await page.goto('/cliente/pedido/novo');
  await expect(page.locator('#formPedido')).toBeVisible();
  await page.locator('#inputOrigem').fill('Rua do Riachuelo, 280, Rio de Janeiro - RJ');
  await page.locator('#btnBuscarOrigem').click();
  await expect(page.locator('#lat_origem')).not.toHaveValue('');
  await page.locator('[data-symptom="PRECISA_TRANSPORTAR"]').click();
  // A triagem de transporte pode ir direto para confirmar; os demais
  // sintomas exibem a etapa intermediária de detalhes.
  if (await page.locator('#btnContinuarDetalhes').isVisible()) {
    await page.locator('#btnContinuarDetalhes').click();
  }
  await expect(page.locator('[data-step="confirmar"]')).toBeVisible();
  await page.locator('#destinoBlock').waitFor({ state: 'visible' });
  const oficinaOptions = page.locator('#selectOficina option:not([value=""])');
  if (await oficinaOptions.count()) {
    await page.locator('#selectOficina').selectOption({ index: 1 });
  } else {
    await page.locator('#inputDest').fill('Rua da Gamboa, 247, Rio de Janeiro - RJ');
    await page.locator('#numeroDest').fill('247');
    await page.locator('#btnBuscarDestinoLivre').click();
  }
  // A cotação depende da geocodificação e pode passar alguns instantes por
  // “calculando…”. Só inspecionamos o preço quando o motor terminou essa etapa.
  await expect(page.locator('#custoValDisplay')).not.toContainText(/calculando|aguarde/i, { timeout: 30_000 });
  await expect(page.locator('#custoValDisplay')).toContainText('R$');
  await expect(page.locator('#btnSubmit')).toBeEnabled();
  console.log(`[COTACAO] ${await page.locator('#custoValDisplay').innerText()} | distancia=${await page.locator('#custoDistDisplay').innerText()}`);
  await screenshot(page, 'confirmacao');
}

async function enviarCartaoSandbox(page, codigo) {
  const consentir = page.getByRole('button', { name: /^Aceitar$/i });
  if (await consentir.isVisible().catch(() => false)) {
    await consentir.click();
  }
  const cardNumber = page.frameLocator('iframe[name="cardNumber"]').locator('#cardNumber');
  const expirationDate = page.frameLocator('iframe[name="expirationDate"]').locator('#expirationDate');
  const securityCode = page.frameLocator('iframe[name="securityCode"]').locator('#securityCode');
  await cardNumber.fill('');
  await cardNumber.pressSequentially('5031433215406351');
  await expirationDate.fill('');
  await expirationDate.pressSequentially('1130');
  await securityCode.fill('');
  await securityCode.pressSequentially('123');
  await page.getByRole('textbox', { name: /Maria Santos Pereira/i }).fill(codigo);
  await page.getByRole('textbox', { name: /999\.999\.999-99/ }).fill('12345678909');
  await page.getByRole('textbox', { name: /exemplo@email\.com/i }).fill(cliente.email);
  await page.getByRole('button', { name: /Pagar/i }).click();
}

test.describe('socorro completo', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('inspeciona cotação de reboque e abre checkout', async ({ page }) => {
    await abrirPedido(page);
    await page.locator('#btnSubmit').click();
    await expect(page).toHaveURL(/pagamento|checkout/);
    const checkoutTotal = page.locator('.checkout-recibo .recibo-total strong').first();
    await expect(checkoutTotal).toHaveText(/R\$\s*[\d.,]+/);
    console.log(`[COTACAO_CHECKOUT] ${await checkoutTotal.innerText()}`);
    // O checkout é válido mesmo quando a chave Sandbox não está disponível
    // para o processo local; nesse caso o Brick não é montado.
    await expect(page.getByText(/Checkout do Pagamento/i)).toBeVisible();
    await screenshot(page, 'checkout');
    // Sem token Sandbox, o sistema mantém a cotação e informa que o provedor
    // não está disponível; com token, o Payment Brick deve aparecer.
    if (true) {
      await expect(page.locator('#mp-payment-brick-container')).toBeVisible();
    } else {
      await expect(page.getByText('Nenhum provedor de pagamento está disponível no momento.')).toBeVisible();
    }
    await screenshot(page, 'checkout');
  });

  for (const [codigo, nome] of Object.entries({ APRO: 'aprovado', OTHE: 'recusado geral', FUND: 'fundos insuficientes', SECU: 'CVV inválido', CONT: 'pendente' })) {
    test(`Mercado Pago Sandbox - ${codigo} (${nome})`, async ({ page }) => {
      test.skip(!process.env.MP_SANDBOX_E2E, 'Defina MP_SANDBOX_E2E=1 e configure o Payment Brick para executar chamadas reais ao Sandbox.');
      let brickError = '';
      page.on('console', (message) => {
        if (/\[Brick\]\[onError\]/i.test(message.text())) brickError = message.text();
      });
      await abrirPedido(page);
      await page.locator('#btnSubmit').click();
      await expect(page).toHaveURL(/pagamento|checkout/);
      const brick = page.locator('#mp-payment-brick-container');
      await expect(brick).toBeVisible();
      await expect(brick.locator('iframe').first()).toBeVisible({ timeout: 30_000 });
      await page.getByText('Cartão de crédito', { exact: true }).click();
      await expect(page.getByText(/Número do cartão/i)).toBeVisible({ timeout: 30_000 });
      if (process.env.MP_SANDBOX_SUBMIT === '1') {
        await enviarCartaoSandbox(page, codigo);
        await page.waitForTimeout(1_000);
        if (brickError) {
          throw new Error(`Payment Brick rejeitou os dados: ${brickError}`);
        }
        await expect(page.locator('#mp-payment-status')).toContainText(/Pagamento aprovado|Pagamento pendente|Pagamento não aprovado|Pagamento recusado/i, { timeout: 30_000 });
      }
      // Os campos de cartão são iframes gerenciados pelo Payment Brick.
      // A automação real deve usar os frames do SDK e o token gerado no browser.
      await screenshot(page, `mp-${codigo.toLowerCase()}`);
    });
  }
});
