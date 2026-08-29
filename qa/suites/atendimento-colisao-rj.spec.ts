import { test, expect, type Page, type TestInfo } from '@playwright/test';
import { rjGamboaToVenezuelaRoute, rjVenezuelaToOficinaRoute } from '../fixtures/stress-scenarios.fixture';
import { driveRoute } from '../helpers/gps-simulator';
import { confirmarChegada, enviarEvidenciaColeta, enviarEvidenciaEntrega } from '../helpers/evidence';
import { enviarMensagem, esperarMensagem } from '../helpers/chat';
import { waitQaMinutes } from '../helpers/qa-clock';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAtendimentoGamboa, qaPedidoSnapshot } from '../helpers/seed';
import { CPF_TESTE_PADRAO, MP_SCENARIOS, MP_TEST_CARDS } from '../fixtures/mercadopago-test-cards.fixture';
import { RegistroPassos } from '../helpers/step-logger';

// RJ-COLISAO-001 — colisão na Av. Venezuela, 134 (Gamboa), reboque direto
// (attendance_mode TOWING) até a oficina da Rua da Gamboa, 275. Prestador
// sai da sede real da GuinchaFácil (Rua da Gamboa, 131). Fluxo simples e
// direto (sem diagnóstico/conversão): pagamento -> aceite -> aproximação
// real -> chegada -> evidência de coleta -> reboque real até a oficina ->
// evidência de entrega -> concluído. Prazos de negócio (5min aceitar,
// 10min chegar) via QaClock — acelerados por padrão.
test.use({ video: 'on' });

async function fillInAnyFrame(page: Page, selector: string, value: string, timeoutMs = 20000): Promise<void> {
  const deadline = Date.now() + timeoutMs;
  let lastErr: unknown = null;
  while (Date.now() < deadline) {
    for (const frame of page.frames()) {
      try {
        const loc = frame.locator(selector);
        if (await loc.count()) { await loc.first().fill(value, { timeout: 3000 }); return; }
      } catch (e) { lastErr = e; }
    }
    await page.waitForTimeout(300);
  }
  throw new Error(`Não encontrei campo "${selector}" em nenhum frame (timeout ${timeoutMs}ms). Último erro: ${lastErr}`);
}

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

/** Mesmo padrão já validado em E2E-SOCORRO-001/E2E-PAY-004 — Payment Brick real. */
async function pagarComBrickReal(page: Page, testInfo: TestInfo, registro: RegistroPassos, rotulo: string): Promise<void> {
  const card = MP_TEST_CARDS.mastercard;
  const scenario = MP_SCENARIOS.aprovado;
  const documento = scenario.documento || CPF_TESTE_PADRAO;

  await registro.passo(`[${rotulo}] Aguardar Payment Brick renderizar`, async () => {
    await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 30000).catch(() => {});
  });

  await registro.passo(`[${rotulo}] Selecionar "Cartão de crédito"`, async () => {
    const jaExpandido = await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 1500).then(() => true).catch(() => false);
    if (jaExpandido) return;
    const candidatos = [
      page.getByRole('radio', { name: /cart.o de cr.dito/i }),
      page.locator('label').filter({ hasText: /^Cart.o de cr.dito/i }),
      page.getByText('Cartão de crédito', { exact: false })
    ];
    for (const candidato of candidatos) {
      const alvo = candidato.first();
      if (await alvo.count().catch(() => 0)) { await alvo.click({ timeout: 5000 }).catch(() => {}); break; }
    }
    await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', '', 15000);
  });

  await registro.passo(`[${rotulo}] Preencher dados do cartão`, async () => {
    await fillInAnyFrame(page, 'input[placeholder="1234 1234 1234 1234"]', card.numero.replace(/\s+/g, ''), 30000);
    await fillInAnyFrame(page, 'input[placeholder="mm/aa"]', card.vencimento, 15000);
    await fillInAnyFrame(page, 'input[placeholder="Ex.: 123"]', card.cvv, 15000);
    await fillInAnyFrame(page, 'input[placeholder="Maria Santos Pereira"]', scenario.codigo, 15000);
    await fillInAnyFrame(page, 'input[placeholder="999.999.999-99"]', documento, 15000);
  });

  const parcelaSelecionada = await registro.passo(`[${rotulo}] Selecionar parcelamento (1x)`, async () => selectInAnyFrame(page, 'select:has(option[aria-label*="À Vista"])', '1', 20000));
  if (!parcelaSelecionada) registro.registrar(`[${rotulo}] Selecionar parcelamento (1x)`, true, 'Dropdown não apareceu — ok para métodos sem parcelamento.');

  await registro.passo(`[${rotulo}] Preencher e-mail do comprador`, async () => {
    const campoEmail = page.getByPlaceholder(/exemplo@email\.com/i).or(page.getByRole('textbox', { name: /e-?mail/i }));
    if (await campoEmail.count().catch(() => 0)) {
      const { clienteCreds } = await import('../fixtures/test-data.fixture');
      await campoEmail.first().fill(clienteCreds().email);
    }
  });

  const respostaPagamento = await registro.passo(`[${rotulo}] Confirmar pagamento ("Pagar")`, async () => {
    const { clickFirstAvailable } = await import('../helpers/auth');
    await clickFirstAvailable(page, ['button:has-text("Pagar")', 'button[type="submit"]:has-text("Pagar")']);
    return Promise.race([
      page.waitForURL(/\/pagamento\/sucesso\//, { timeout: 60000 }).then(() => ({ sucesso: true, status: 'aprovado' } as any)),
      page.waitForFunction(() => {
        const r = (window as any).__qaUltimaRespostaPagamento;
        return !!r && r.sucesso === false;
      }, { timeout: 60000 }).then(() => page.evaluate(() => (window as any).__qaUltimaRespostaPagamento))
    ]);
  });

  if (!respostaPagamento.sucesso || respostaPagamento.status !== 'aprovado') {
    throw new Error(`[${rotulo}] Pagamento não foi aprovado: ${JSON.stringify(respostaPagamento)}`);
  }
  await registro.passo(`[${rotulo}] Aguardar redirecionamento para /pagamento/sucesso/`, async () => {
    await page.waitForURL(/\/pagamento\/sucesso\//, { timeout: 15000 });
  });
}

function atribuirPrestador(pedidoId: string): { ok: boolean } {
  const { execFileSync } = require('node:child_process');
  const path = require('node:path');
  const projectRoot = path.resolve(__dirname, '..', '..');
  const stdout = execFileSync('php', [path.join(projectRoot, 'tools/prepare_atendimento_rj_gamboa_qa.php'), 'atribuir', pedidoId], { encoding: 'utf8', cwd: projectRoot });
  return JSON.parse(stdout.trim());
}

test.describe('atendimento colisão com reboque — Gamboa (Rio de Janeiro)', () => {
  test('RJ-COLISAO-001 | colisão com reboque completo até a oficina', async ({ browser }, testInfo) => {
    testInfo.setTimeout(18 * 60_000);

    const seeded = seedAtendimentoGamboa('colisao');
    expect(seeded.ok, 'seed RJ-COLISAO-001 falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const clienteContext = await browser.newContext();
    const clientePage = await clienteContext.newPage();
    const registro = new RegistroPassos(testInfo, clientePage);

    try {
      await loginAs(clientePage, seeded.cliente_email, 'test123');
      await clientePage.goto(appPath(seeded.checkout_url), { waitUntil: 'domcontentloaded' });
      await pagarComBrickReal(clientePage, testInfo, registro, 'colisao');

      await waitQaMinutes(5, 'prestador aguardando antes de aceitar (RJ-COLISAO-001)');

      const prestadorContext = await browser.newContext({
        geolocation: { latitude: rjGamboaToVenezuelaRoute[0].lat, longitude: rjGamboaToVenezuelaRoute[0].lng },
        permissions: ['geolocation']
      });
      const prestadorPage = await prestadorContext.newPage();

      try {
        await registro.passo('Seed: atribuir prestador ao pedido', async () => {
          const atribuido = atribuirPrestador(pedidoId);
          expect(atribuido.ok, 'atribuir prestador falhou').toBeTruthy();
        }, { metadata: { pedidoId, phase: 'ATRIBUICAO' } });

        await loginAs(prestadorPage, seeded.prestador_email, 'test123');
        await Promise.all([
          clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
          prestadorPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' })
        ]);

        await registro.passo('Aproximação real até a colisão (Gamboa 131 -> Venezuela 134)', async () => {
          await driveRoute(prestadorPage, pedidoId, rjGamboaToVenezuelaRoute.slice(1), 10);
        }, { metadata: { system: 'ProofOfRoad', class: 'GpsSimulator', function: 'driveRoute', phase: 'TO_ORIGEM', pedidoId } });

        await registro.passo('Confirmar chegada (a_caminho -> no_local)', async () => {
          await confirmarChegada(prestadorPage);
        }, { metadata: { pedidoId, phase: 'CHEGADA' } });

        await enviarMensagem(prestadorPage, 'guincho', 'Cheguei no local da colisão, vou preparar o veículo pro reboque.');
        await esperarMensagem(clientePage, /reboque/i, 15_000, pedidoId);

        await registro.passo('Evidência de coleta (no_local -> em_reboque)', async () => {
          await enviarEvidenciaColeta(prestadorPage);
        }, { metadata: { pedidoId, phase: 'COLETA' } });

        await expect(clientePage.locator('body')).toContainText(/em reboque/i, { timeout: 20000 });

        await registro.passo('Reboque real até a oficina (Venezuela 134 -> Gamboa 275)', async () => {
          await driveRoute(prestadorPage, pedidoId, rjVenezuelaToOficinaRoute.slice(1), 8);
        }, { metadata: { system: 'ProofOfRoad', class: 'GpsSimulator', function: 'driveRoute', phase: 'TO_DESTINO', pedidoId } });

        await registro.passo('Evidência de entrega (em_reboque -> concluido)', async () => {
          await enviarEvidenciaEntrega(prestadorPage);
        }, { metadata: { pedidoId, phase: 'ENTREGA' } });

        const snapshot = qaPedidoSnapshot(pedidoId);
        expect(snapshot.ok, `snapshot falhou: ${JSON.stringify(snapshot)}`).toBeTruthy();
        expect(snapshot.pedido?.status).toBe('concluido');
        expect(snapshot.evidencias?.coleta).toBeTruthy();
        expect(snapshot.evidencias?.entrega).toBeTruthy();
      } finally {
        await prestadorContext.close();
      }
    } finally {
      await registro.finalizar();
      await clienteContext.close();
    }
  });
});
