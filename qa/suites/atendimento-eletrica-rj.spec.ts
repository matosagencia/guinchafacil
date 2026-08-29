import { test, expect, type Page, type TestInfo } from '@playwright/test';
import { rjGamboaToVenezuelaRoute } from '../fixtures/stress-scenarios.fixture';
import { driveRoute } from '../helpers/gps-simulator';
import { confirmarChegada } from '../helpers/evidence';
import { enviarMensagem, esperarMensagem } from '../helpers/chat';
import { waitQaMinutes } from '../helpers/qa-clock';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAtendimentoGamboa, qaPedidoSnapshot } from '../helpers/seed';
import { CPF_TESTE_PADRAO, MP_SCENARIOS, MP_TEST_CARDS } from '../fixtures/mercadopago-test-cards.fixture';
import { RegistroPassos } from '../helpers/step-logger';

// RJ-ELETRICA-001 — pane elétrica na Av. Venezuela, 134 (Gamboa), atendida
// ON_SITE (ELECTRICAL_DIAGNOSIS) por um prestador que sai da sede real da
// GuinchaFácil (Rua da Gamboa, 131). Diferente de E2E-SOCORRO-001 (que
// termina em REQUER_REBOQUE), este cenário resolve no local: diagnóstico ->
// RESOLVIDO_SEM_ORCAMENTO -> execução -> teste final -> concluído. Prazos de
// negócio (3min pra aceitar, 5min pra chegar) rodam via QaClock — em modo
// acelerado (padrão), viram segundos; QA_TIME_MODE=realtime roda no tempo
// real de verdade.
test.use({ video: 'on' });

async function fillInAnyFrame(page: Page, selector: string, value: string, timeoutMs = 20000): Promise<void> {
  const deadline = Date.now() + timeoutMs;
  let lastErr: unknown = null;
  while (Date.now() < deadline) {
    for (const frame of page.frames()) {
      try {
        const loc = frame.locator(selector);
        if (await loc.count()) {
          await loc.first().fill(value, { timeout: 3000 });
          return;
        }
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
  }, { metadata: { system: 'MercadoPago', class: 'PaymentBrick', function: 'render', phase: rotulo } });

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
  }, { metadata: { system: 'MercadoPago', class: 'PaymentBrick', function: 'pagar', phase: rotulo, pedidoId: undefined } });

  if (!respostaPagamento.sucesso || respostaPagamento.status !== 'aprovado') {
    throw new Error(`[${rotulo}] Pagamento não foi aprovado: ${JSON.stringify(respostaPagamento)}`);
  }
  await registro.passo(`[${rotulo}] Aguardar redirecionamento para /pagamento/sucesso/`, async () => {
    await page.waitForURL(/\/pagamento\/sucesso\//, { timeout: 15000 });
  });
}

test.describe('atendimento pane elétrica — Gamboa (Rio de Janeiro)', () => {
  test('RJ-ELETRICA-001 | pane elétrica resolvida no local, prestador saindo da sede real', async ({ browser }, testInfo) => {
    testInfo.setTimeout(15 * 60_000);

    const seeded = seedAtendimentoGamboa('pane-eletrica');
    expect(seeded.ok, 'seed RJ-ELETRICA-001 falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const clienteContext = await browser.newContext();
    const clientePage = await clienteContext.newPage();
    const registro = new RegistroPassos(testInfo, clientePage);

    try {
      await loginAs(clientePage, seeded.cliente_email, 'test123');
      await clientePage.goto(appPath(seeded.checkout_url), { waitUntil: 'domcontentloaded' });
      await pagarComBrickReal(clientePage, testInfo, registro, 'pane-eletrica');

      await waitQaMinutes(3, 'prestador aguardando antes de aceitar (RJ-ELETRICA-001)');

      const prestadorContext = await browser.newContext({
        geolocation: { latitude: rjGamboaToVenezuelaRoute[0].lat, longitude: rjGamboaToVenezuelaRoute[0].lng },
        permissions: ['geolocation']
      });
      const prestadorPage = await prestadorContext.newPage();

      try {
        // Reaproveita o subcomando 'atribuir' do próprio seed (ver
        // tools/prepare_atendimento_rj_gamboa_qa.php) — chamado direto aqui
        // porque seedAtendimentoGamboa() só expõe o setup inicial.
        await registro.passo('Seed: atribuir prestador ao pedido', async () => {
          const { execFileSync } = require('node:child_process');
          const path = require('node:path');
          const projectRoot = path.resolve(__dirname, '..', '..');
          const stdout = execFileSync('php', [path.join(projectRoot, 'tools/prepare_atendimento_rj_gamboa_qa.php'), 'atribuir', pedidoId], { encoding: 'utf8', cwd: projectRoot });
          const atribuido = JSON.parse(stdout.trim());
          expect(atribuido.ok, `atribuir prestador falhou: ${stdout}`).toBeTruthy();
        }, { metadata: { pedidoId, phase: 'ATRIBUICAO' } });

        await loginAs(prestadorPage, seeded.prestador_email, 'test123');
        await Promise.all([
          clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
          prestadorPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' })
        ]);

        await registro.passo('Aproximação real até a ocorrência (Gamboa 131 -> Venezuela 134)', async () => {
          await driveRoute(prestadorPage, pedidoId, rjGamboaToVenezuelaRoute.slice(1), 5);
        }, { metadata: { system: 'ProofOfRoad', class: 'GpsSimulator', function: 'driveRoute', phase: 'TO_OCORRENCIA', pedidoId } });

        await registro.passo('Confirmar chegada (a_caminho -> no_local)', async () => {
          await confirmarChegada(prestadorPage);
        }, { metadata: { pedidoId, phase: 'CHEGADA' } });

        await enviarMensagem(prestadorPage, 'guincho', 'Cheguei — vou verificar a bateria e o sistema de carga.');
        await esperarMensagem(clientePage, /bateria|sistema de carga/i, 15_000, pedidoId);

        await registro.passo('Diagnóstico: iniciar (foto de chegada real)', async () => {
          const { readFileSync } = require('node:fs');
          const path = require('node:path');
          const { resolveEvidenceImage } = require('../helpers/atendimento');
          const nonceResp = await prestadorPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const nonceData = await nonceResp.json();
          if (!nonceResp.ok() || !nonceData.ok) throw new Error(`evidencia-nonce falhou: ${JSON.stringify(nonceData)}`);
          const csrfToken = await prestadorPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const imagePath = resolveEvidenceImage();
          const resp = await prestadorPage.request.post(appPath(`/guincho/diagnostico/iniciar/${pedidoId}`), {
            multipart: {
              csrf_token: csrfToken,
              evidence_token: nonceData.evidence_token,
              foto_chegada: { name: path.basename(imagePath), mimeType: 'image/jpeg', buffer: readFileSync(imagePath) }
            }
          });
          if (!resp.ok()) throw new Error(`diagnostico/iniciar falhou ${resp.status()}: ${await resp.text()}`);
        }, { metadata: { system: 'Diagnostico', class: 'DiagnosticoService', function: 'iniciar', pedidoId } });

        await registro.passo('Diagnóstico: concluir com RESOLVIDO_SEM_ORCAMENTO (troca de bateria simples no local)', async () => {
          const csrfToken = await prestadorPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const resp = await prestadorPage.request.post(appPath(`/guincho/diagnostico/concluir/${pedidoId}`), {
            form: {
              csrf_token: csrfToken,
              resultado: 'RESOLVIDO_SEM_ORCAMENTO',
              descricao: 'Bateria descarregada, sem danos no sistema de carga — troca simples resolve no local.'
            }
          });
          if (!resp.ok()) throw new Error(`diagnostico/concluir falhou ${resp.status()}: ${await resp.text()}`);
        }, { metadata: { system: 'Diagnostico', class: 'DiagnosticoService', function: 'concluir', pedidoId } });

        await expect(clientePage.locator('body')).toContainText(/execu.*servi.o|em execu/i, { timeout: 20000 });

        await registro.passo('Execução: concluir troca de bateria', async () => {
          const csrfToken = await prestadorPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const resp = await prestadorPage.request.post(appPath(`/guincho/execucao/concluir/${pedidoId}`), {
            form: { csrf_token: csrfToken }
          });
          if (!resp.ok()) throw new Error(`execucao/concluir falhou ${resp.status()}: ${await resp.text()}`);
        }, { metadata: { system: 'Diagnostico', class: 'DiagnosticoService', function: 'concluirExecucao', pedidoId } });

        await registro.passo('Teste final: resolvido, com foto de conclusão', async () => {
          const { readFileSync } = require('node:fs');
          const path = require('node:path');
          const { resolveEvidenceImage } = require('../helpers/atendimento');
          const nonceResp = await prestadorPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const nonceData = await nonceResp.json();
          // Guarda contra o próprio bug que este passo achou: se
          // evidenciaNonce() não reconhecer o status atual, ele responde
          // {ok:false} sem evidence_token — passar `undefined` num campo do
          // `multipart` do Playwright trava com um erro interno opaco
          // ("Cannot read properties of undefined (reading 'on')") em vez de
          // reportar a causa real. Falha explícita aqui é mais fácil de
          // diagnosticar da próxima vez.
          if (!nonceData.ok || !nonceData.evidence_token) {
            throw new Error(`evidencia-nonce não retornou evidence_token para o teste final: ${JSON.stringify(nonceData)}`);
          }
          const csrfToken = await prestadorPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const imagePath = resolveEvidenceImage();
          const resp = await prestadorPage.request.post(appPath(`/guincho/teste-final/concluir/${pedidoId}`), {
            multipart: {
              csrf_token: csrfToken,
              resolvido: '1',
              evidence_token: nonceData.evidence_token,
              foto_destino: { name: path.basename(imagePath), mimeType: 'image/jpeg', buffer: readFileSync(imagePath) }
            }
          });
          if (!resp.ok()) throw new Error(`teste-final/concluir falhou ${resp.status()}: ${await resp.text()}`);
        }, { metadata: { system: 'Diagnostico', class: 'DiagnosticoService', function: 'confirmarResultadoFinal', pedidoId } });

        const snapshot = qaPedidoSnapshot(pedidoId);
        expect(snapshot.ok, `snapshot falhou: ${JSON.stringify(snapshot)}`).toBeTruthy();
        expect(snapshot.pedido?.status).toBe('concluido');
      } finally {
        await prestadorContext.close();
      }
    } finally {
      await registro.finalizar();
      await clienteContext.close();
    }
  });
});
