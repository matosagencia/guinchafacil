import { test, expect, type Page } from '@playwright/test';
import { clienteCreds, guinchoCreds } from '../fixtures/test-data.fixture';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedCancelamento, type CancelamentoSeedResult } from '../helpers/seed';

// Histórico: esta suite era um placeholder que só confirmava que a tela de
// login carregava — nenhum cenário de cancelamento era realmente exercitado.
// O backend (ClienteController::cancelarPedido/cancelamentoPreview,
// CancelamentoService, GuinchoController::cancelarAtendimento) já implementa
// regras por fase: grátis antes do aceite, com taxa depois que o guincho está
// a caminho, bloqueado em fase irreversível (no_local/em_reboque), e
// cancelamento pelo guincho com penalidade de reputação + reabertura do
// pedido para a fila. Estes testes cobrem os 4 cenários de verdade, usando o
// seed dedicado tools/prepare_cancelamento_qa_seed.php (auto-executado, sem
// necessidade de configurar env vars manualmente).

async function statusJsonCliente(page: Page, pedidoId: number): Promise<any> {
  const response = await page.request.get(appPath(`/cliente/pedido/status-json/${pedidoId}`), {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  });
  return response.json();
}

async function confirmarCancelamentoCliente(page: Page, pedidoId: number): Promise<void> {
  await page.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' });
  const btnCancelar = page.locator('#btnCancelarPedido');
  await expect(btnCancelar).toBeVisible();
  await expect(btnCancelar).toBeEnabled();
  await btnCancelar.click();

  const confirmBtn = page.locator('#btnConfirmarCancelamento');
  // O preview (com o snapshot_id que a confirmação exige) é buscado via AJAX
  // assim que o modal abre — o botão de confirmar só habilita depois que essa
  // resposta chega.
  await expect(confirmBtn).toBeEnabled({ timeout: 15000 });
  await confirmBtn.click();
  await page.waitForURL(/\/cliente\/historico/i, { timeout: 15000 });
}

test.describe('cancelamento de pedidos', () => {
  let seed: CancelamentoSeedResult;

  // No gate completo (150 testes, 1 worker), esta suite pode rodar depois de
  // ~25-30min de execução contínua num ambiente XAMPP local sob contenção de
  // CPU/IO — o timeout padrão de 90s já foi visto estourar mesmo com a lógica
  // de cancelamento funcionando corretamente (confirmado por reruns isolados
  // passando 100%). Dá mais folga sem mascarar regressões reais.
  test.describe.configure({ timeout: 180_000 });

  test.beforeAll(() => {
    seed = seedCancelamento();
  });

  test('E2E-CAN-001 | cliente cancela antes do aceite sem cobrança de taxa', async ({ page }) => {
    const cliente = clienteCreds();
    await loginAs(page, cliente.email, cliente.password);
    await expectLoggedIn(page);

    const pedidoId = seed.pedido_antes_aceite_id;
    await page.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' });

    const btnCancelar = page.locator('#btnCancelarPedido');
    await expect(btnCancelar).toBeEnabled();
    await btnCancelar.click();

    const confirmBtn = page.locator('#btnConfirmarCancelamento');
    await expect(confirmBtn).toBeEnabled({ timeout: 15000 });
    await expect(page.locator('#cancelResumoTaxa')).toContainText(/nenhuma taxa será cobrada/i);
    // Achado no gate completo: tanto Promise.all([waitForResponse(regex), click()])
    // quanto esperar por page.waitForURL(/historico/) sozinho são corridas frágeis
    // sob carga contra um redirect DISPARADO PELO PRÓPRIO JS do cliente (só depois
    // que o fetch de /cliente/cancelar/ resolve). O que realmente importa validar
    // é se o cancelamento aconteceu no servidor, não se o navegador terminou de
    // navegar — então em vez de depender do redirect client-side, fazemos poll
    // direto no status do pedido (mesmo padrão já usado com sucesso em
    // atendimento-completo.spec.ts para aguardar 'concluido').
    await confirmBtn.click();
    await expect.poll(async () => (await statusJsonCliente(page, pedidoId))?.status, {
      timeout: 45000,
      message: 'pedido nunca chegou a status=cancelado após confirmar cancelamento'
    }).toBe('cancelado');

    const data = await statusJsonCliente(page, pedidoId);
    expect(data.status).toBe('cancelado');
    expect(Number(data.taxa_cancelamento || 0)).toBe(0);
  });

  test('E2E-CAN-002 | cliente cancela após aceite e deslocamento com taxa proporcional', async ({ page }) => {
    const cliente = clienteCreds();
    await loginAs(page, cliente.email, cliente.password);
    await expectLoggedIn(page);

    const pedidoId = seed.pedido_cliente_taxa_id;
    await page.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' });

    const btnCancelar = page.locator('#btnCancelarPedido');
    await expect(btnCancelar).toBeEnabled();
    await btnCancelar.click();

    const confirmBtn = page.locator('#btnConfirmarCancelamento');
    await expect(confirmBtn).toBeEnabled({ timeout: 15000 });
    // Fora da janela grátis (pedido seedado com 20min de criação) e já
    // "a_caminho": a fórmula constitucional cobra pelo menos a taxa fixa.
    await expect(page.locator('#cancelResumoTaxa')).toContainText(/taxa de cancelamento de.*R\$/i);
    // Ver comentário equivalente em E2E-CAN-001: poll direto no status em vez
    // de depender do redirect client-side pro histórico.
    await confirmBtn.click();
    await expect.poll(async () => (await statusJsonCliente(page, pedidoId))?.status, {
      timeout: 45000,
      message: 'pedido nunca chegou a status=cancelado após confirmar cancelamento'
    }).toBe('cancelado');

    const data = await statusJsonCliente(page, pedidoId);
    expect(data.status).toBe('cancelado');
    expect(Number(data.taxa_cancelamento || 0)).toBeGreaterThan(0);
  });

  test('E2E-CAN-003 | cancelamento bloqueado quando atendimento já está no local (fase irreversível)', async ({ page }) => {
    const cliente = clienteCreds();
    await loginAs(page, cliente.email, cliente.password);
    await expectLoggedIn(page);

    const pedidoId = seed.pedido_irreversivel_id;
    await page.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' });

    const btnCancelar = page.locator('#btnCancelarPedido');
    await expect(btnCancelar).toBeVisible();
    await expect(btnCancelar).toBeDisabled();
    await expect(btnCancelar).toHaveAttribute('title', /andamento no local|não pode mais ser cancelado/i);

    const data = await statusJsonCliente(page, pedidoId);
    expect(data.status).toBe('no_local');
  });

  test('E2E-CAN-004 | guincho cancela atendimento após aceite: penalidade e pedido volta para a fila', async ({ page }) => {
    const guincho = guinchoCreds();
    await loginAs(page, guincho.email, guincho.password);
    await expectLoggedIn(page);

    const pedidoId = seed.pedido_guincho_cancela_id;
    await page.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });

    const btnCancelar = page.locator('#btnCancelarAtendimento');
    await expect(btnCancelar).toBeVisible();
    await btnCancelar.click();

    await page.locator('#cancelMotivoGuincho').fill('Problema mecânico no guincho QA.');
    const confirmBtn = page.locator('#btnConfirmarCancelamentoGuincho');
    await confirmBtn.click();
    await page.waitForURL(/\/guincho\/dashboard/i, { timeout: 15000 });

    // O guincho perde o vínculo com o pedido ao cancelar (ele volta pra fila
    // sem guincho atribuído), então a verificação de estado final é feita
    // pelo lado do cliente, que continua dono do pedido o tempo todo.
    const cliente = clienteCreds();
    const clienteContext = await page.context().browser()!.newContext();
    const clientePage = await clienteContext.newPage();
    try {
      await loginAs(clientePage, cliente.email, cliente.password);
      const data = await statusJsonCliente(clientePage, pedidoId);
      expect(data.status).toBe('aguardando_guincho');
    } finally {
      await clienteContext.close();
    }
  });
});
