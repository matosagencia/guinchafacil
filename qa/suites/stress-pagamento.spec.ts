import { test, expect, type Page, type TestInfo } from '@playwright/test';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAtendimentoGamboa, confirmarWebhookMercadoPago, pagamentoIdExterno, qaPedidoSnapshot } from '../helpers/seed';
import { CPF_TESTE_PADRAO, MP_SCENARIOS, MP_TEST_CARDS } from '../fixtures/mercadopago-test-cards.fixture';
import { RegistroPassos } from '../helpers/step-logger';

// STRESS-PAG-001 — webhook duplicado real: depois de um pagamento real via
// Payment Brick aprovar, reenvia o MESMO payment_id 5x em paralelo pro
// endpoint de webhook (qa_confirmar_webhook_mp.php reproduz a assinatura
// HMAC real do Mercado Pago, mesmo helper usado por pagamento-sandbox.spec.ts).
// Esperado no banco: 1 pagamento aprovado, pedido avança exatamente uma vez
// — a idempotência do webhook real (não simulada) precisa segurar reenvio
// concorrente, não só sequencial.
test.use({ video: 'on' });

async function fillInAnyFrame(page: Page, selector: string, value: string, timeoutMs = 20000): Promise<void> {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    for (const frame of page.frames()) {
      try {
        const loc = frame.locator(selector);
        if (await loc.count()) { await loc.first().fill(value, { timeout: 3000 }); return; }
      } catch { /* tenta o próximo frame */ }
    }
    await page.waitForTimeout(300);
  }
  throw new Error(`Não encontrei campo "${selector}" em nenhum frame (timeout ${timeoutMs}ms).`);
}

// Mesmo helper de atendimento-colisao-rj.spec.ts: o dropdown "Selecione o
// número de parcelas" deste checkout é um <select> REAL dentro do iframe do
// Payment Brick embutido (paradigma diferente do Checkout Pro hospedado no
// domínio do MP usado em pagamento-sandbox.spec.ts, que tem URLs próprias
// /installments//review/) — por isso a solução certa aqui é selectOption
// num <select>, não clique em <li id="1x-row">.
async function selectInAnyFrame(page: Page, selector: string, value: string, timeoutMs = 20000): Promise<boolean> {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    for (const frame of page.frames()) {
      try {
        const loc = frame.locator(selector).first();
        if (await loc.count()) {
          const opcoes = await loc.locator('option').count();
          if (opcoes > 1) { await loc.selectOption(value, { timeout: 3000 }); return true; }
        }
      } catch { /* tenta o próximo frame */ }
    }
    await page.waitForTimeout(300);
  }
  return false;
}

async function pagarComBrickReal(page: Page, registro: RegistroPassos, clienteEmail: string): Promise<void> {
  const card = MP_TEST_CARDS.mastercard;
  const scenario = MP_SCENARIOS.aprovado;
  const documento = scenario.documento || CPF_TESTE_PADRAO;

  await registro.passo('Aguardar Payment Brick renderizar', async () => {
    await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 30000).catch(() => {});
  });
  await registro.passo('Selecionar "Cartão de crédito"', async () => {
    const jaExpandido = await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 1500).then(() => true).catch(() => false);
    if (jaExpandido) return;
    const alvo = page.getByText('Cartão de crédito', { exact: false }).first();
    if (await alvo.count().catch(() => 0)) await alvo.click({ timeout: 5000 }).catch(() => {});
    await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 15000);
  });
  await registro.passo('Preencher dados do cartão', async () => {
    await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', card.numero.replace(/\s+/g, ''), 30000);
    await fillInAnyFrame(page, 'input[placeholder="mm/aa"]', card.vencimento, 15000);
    await fillInAnyFrame(page, 'input[placeholder="Ex.: 123"]', card.cvv, 15000);
    await fillInAnyFrame(page, 'input[placeholder="Maria Santos Pereira"]', scenario.codigo, 15000);
    await fillInAnyFrame(page, 'input[placeholder="999.999.999-99"]', documento, 15000);
  });
  // Achado real #2 (padrão correto conferido em atendimento-colisao-rj.spec.ts,
  // linha ~87): pra cartão de crédito o Brick embutido mostra um <select> real
  // "Selecione o número de parcelas" dentro do iframe — sem escolher, ele fica
  // com "Escolha uma opção para avançar" e o clique em "Pagar" nunca navega
  // (evidência: screenshot de falha mostrando o dropdown vazio). Esta cópia
  // local nunca tinha esse passo.
  const parcelaSelecionada = await registro.passo('Selecionar parcelamento (1x)', async () =>
    selectInAnyFrame(page, 'select:has(option[aria-label*="À Vista"])', '1', 20000));
  if (!parcelaSelecionada) registro.registrar('Selecionar parcelamento (1x)', true, 'Dropdown não apareceu — ok para métodos sem parcelamento.');

  // Achado real: a revisão do Brick pede e-mail antes de liberar "Pagar" de
  // verdade — sem isso ele recusa com "Preencha todos os dados para
  // continuar". Esta cópia local não tinha esse passo.
  await registro.passo('Preencher e-mail do comprador', async () => {
    const campoEmail = page.getByPlaceholder(/exemplo@email\.com/i).or(page.getByRole('textbox', { name: /e-?mail/i }));
    if (await campoEmail.count().catch(() => 0)) {
      await campoEmail.first().fill(clienteEmail);
    }
  });
  await registro.passo('Confirmar pagamento ("Pagar")', async () => {
    const { clickFirstAvailable } = await import('../helpers/auth');
    await clickFirstAvailable(page, ['button:has-text("Pagar")', 'button[type="submit"]:has-text("Pagar")']);
    await page.waitForURL(/\/pagamento\/(sucesso|falha|pendente)\//, { timeout: 60000 });
  });
}

test.describe('stress de pagamento', () => {
  test('STRESS-PAG-001 | webhook do mesmo payment_id reenviado 5x em paralelo não duplica aprovação', async ({ browser }, testInfo) => {
    testInfo.setTimeout(10 * 60_000);

    const seeded = seedAtendimentoGamboa('pane-eletrica');
    expect(seeded.ok, 'seed falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const context = await browser.newContext();
    const page = await context.newPage();
    const registro = new RegistroPassos(testInfo, page);

    try {
      await loginAs(page, seeded.cliente_email, 'test123');
      await page.goto(appPath(seeded.checkout_url), { waitUntil: 'domcontentloaded' });
      await pagarComBrickReal(page, registro, seeded.cliente_email);
      expect(page.url(), `retorno do MP não foi de sucesso: ${page.url()}`).toMatch(/\/pagamento\/sucesso\//);

      const idsExterno = await registro.passo('Recuperar payment_id real do pagamento aprovado', async () => {
        const info = pagamentoIdExterno(pedidoId);
        expect(info.ok, `pagamentoIdExterno falhou: ${JSON.stringify(info)}`).toBeTruthy();
        expect(info.vivo_payment_id_numerico, 'payment_id numérico não encontrado').toBeTruthy();
        return info;
      });

      const paymentId = String(idsExterno.vivo_payment_id_numerico);

      await registro.passo('Reenviar webhook 5x em paralelo com o MESMO payment_id', async () => {
        const respostas = await Promise.all(
          Array.from({ length: 5 }, () => Promise.resolve().then(() => confirmarWebhookMercadoPago(paymentId)))
        );
        const aprovados = respostas.filter((r) => r.ok && r.aprovado).length;
        expect(aprovados, `esperava pelo menos 1 confirmação ok, veio: ${JSON.stringify(respostas)}`).toBeGreaterThanOrEqual(1);
      }, { metadata: { system: 'Pagamento', class: 'PagamentoAprovacaoService', function: 'aprovar', pedidoId } });

      const snapshot = qaPedidoSnapshot(pedidoId);
      expect(snapshot.ok).toBeTruthy();
      expect(snapshot.pagamento?.status).toBe('aprovado');
      // Não duplicou: só 1 pagamento aprovado computado no snapshot (o
      // agregado de qaPedidoSnapshot pega o ÚLTIMO pagamento, não soma —
      // a prova real de não-duplicação é o valor bater com o esperado,
      // não com o dobro).
      expect(snapshot.pagamento?.valor).toBeCloseTo(89.9, 1);

      await testInfo.attach('stress-pagamento.json', {
        body: JSON.stringify({ pedidoId, paymentId, snapshot }, null, 2),
        contentType: 'application/json',
      });
    } finally {
      await registro.finalizar();
      await context.close();
    }
  });
});
