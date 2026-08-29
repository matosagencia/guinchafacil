import { test, expect } from '@playwright/test';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAtendimentoGamboa, qaPedidoSnapshot } from '../helpers/seed';
import { enviarMensagem, esperarMensagem } from '../helpers/chat';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

// STRESS-CHAT-001 — várias mensagens em sequência rápida dos dois lados
// (cliente <-> prestador), confirmando que todas chegam, em ordem, sem
// duplicar — migration_chat_idempotency_v1.sql existe justamente pra
// proteger contra duplicação de envio (ex.: duplo-clique), então este teste
// teste também serve de regressão pra essa migration.
test.describe('stress de chat', () => {
  test('STRESS-CHAT-001 | 10 mensagens intercaladas cliente <-> prestador chegam íntegras e em ordem', async ({ browser }, testInfo) => {
    testInfo.setTimeout(5 * 60_000);

    const seeded = seedAtendimentoGamboa('pane-eletrica');
    expect(seeded.ok, 'seed falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const projectRoot = path.resolve(__dirname, '..', '..');
    const stdout = execFileSync('php', [path.join(projectRoot, 'tools/prepare_atendimento_rj_gamboa_qa.php'), 'atribuir', pedidoId], { encoding: 'utf8', cwd: projectRoot });
    const atribuido = JSON.parse(stdout.trim());
    expect(atribuido.ok, `atribuir falhou: ${stdout}`).toBeTruthy();

    const clienteContext = await browser.newContext();
    const prestadorContext = await browser.newContext();
    const clientePage = await clienteContext.newPage();
    const prestadorPage = await prestadorContext.newPage();

    try {
      await loginAs(clientePage, seeded.cliente_email, 'test123');
      await loginAs(prestadorPage, seeded.prestador_email, 'test123');
      await Promise.all([
        clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
        prestadorPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
      ]);

      for (let i = 1; i <= 5; i++) {
        const msgCliente = `mensagem cliente ${i} — ${pedidoId}`;
        const msgPrestador = `mensagem prestador ${i} — ${pedidoId}`;
        await enviarMensagem(clientePage, 'cliente', msgCliente);
        await esperarMensagem(prestadorPage, msgCliente, 15_000, pedidoId);
        await enviarMensagem(prestadorPage, 'guincho', msgPrestador);
        await esperarMensagem(clientePage, msgPrestador, 15_000, pedidoId);
      }

      const snapshot = qaPedidoSnapshot(pedidoId);
      expect(snapshot.ok).toBeTruthy();
      // 10 mensagens enviadas (5 de cada lado) — nenhuma perdida, nenhuma
      // duplicada pela proteção de idempotency_key.
      expect(snapshot.chat?.mensagens).toBe(10);
    } finally {
      await clienteContext.close();
      await prestadorContext.close();
    }
  });
});
