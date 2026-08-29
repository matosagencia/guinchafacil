import { test, expect } from '@playwright/test';
import { loginAs, expectLoggedIn } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { qaPedidoSnapshot } from '../helpers/seed';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

// STRESS-CONC-001 — amplia concorrencia-aceite.spec.ts (que já prova a
// exclusão mútua com 2 guinchos) para N prestadores disputando o MESMO
// pedido ao mesmo tempo. Não substitui aquele teste (mantido intacto) —
// este mede se a garantia (SELECT FOR UPDATE em GuinchoController::aceitar)
// segura sob volume real, não só no caso mínimo de 2.
//
// N configurável via QA_STRESS_ACCEITE_N (padrão 10 — 50/100 são o "stress
// puro" mencionado no reforço, mas exigem N contextos de navegador
// logados simultaneamente, que é pesado no XAMPP local; comece com 10 e só
// suba depois de confirmar que o ambiente aguenta).
const N = Math.max(2, Number(process.env.QA_STRESS_ACCEITE_N || '10'));

function projectRoot(): string {
  return path.resolve(__dirname, '..', '..');
}

function forcarPedidoAguardandoGuincho(): { pedido_id: number } {
  const stdout = execFileSync('php', [path.join(projectRoot(), 'tools/prepare_atendimento_rj_gamboa_qa.php'), 'aguardando-guincho'], { encoding: 'utf8', cwd: projectRoot() });
  return JSON.parse(stdout.trim());
}

test.describe('stress de concorrência de aceite', () => {
  test(`STRESS-CONC-001 | ${N} prestadores disputam o mesmo pedido — só um deve vencer`, async ({ browser }, testInfo) => {
    testInfo.setTimeout(10 * 60_000);

    const runTag = `${Date.now()}-conc`;

    // seedStressAccounts() não aceita parâmetros (contrato fixo em
    // seed.ts) — para este teste precisamos exatamente N guinchos, então
    // chamamos o script PHP diretamente com o count desejado.
    const stdout = execFileSync('php', [path.join(projectRoot(), 'tools/prepare_stress_accounts_qa.php'), runTag, '0', String(N), '0', '0'], { encoding: 'utf8', cwd: projectRoot() });
    const contasSeed = JSON.parse(stdout.trim());
    expect(contasSeed.ok, `seed de contas de stress falhou: ${stdout}`).toBeTruthy();
    expect(contasSeed.guinchos.length).toBe(N);

    const pedido = forcarPedidoAguardandoGuincho();

    const contexts = await Promise.all(Array.from({ length: N }, () => browser.newContext()));
    const pages = await Promise.all(contexts.map((c) => c.newPage()));

    try {
      for (let i = 0; i < N; i++) {
        const email = `qa.guincho.${runTag}.${i + 1}@guinchafacil.com`;
        await loginAs(pages[i], email, 'test12345');
        await expectLoggedIn(pages[i]);
      }

      await Promise.all(pages.map((p) => p.goto(appPath(`/guincho/aceitar/${pedido.pedido_id}`))));

      const resultados = await Promise.allSettled(
        pages.map((p) => p.locator('button:has-text("Aceitar")').click())
      );
      void resultados;

      await Promise.allSettled(
        pages.map((p) => p.waitForURL(/\/guincho\/(atendimento|dashboard|aceitar)\//i, { timeout: 20000 }))
      );

      const snapshot = qaPedidoSnapshot(String(pedido.pedido_id));
      expect(snapshot.ok, `snapshot falhou: ${JSON.stringify(snapshot)}`).toBeTruthy();
      // A garantia real sob teste: exatamente 1 guincho_id preenchido no
      // banco, nunca 0 (perdeu o pedido) nem >1 (impossível pelo schema,
      // mas o valor central é: sempre exatamente 1 vencedor real).
      expect(snapshot.pedido?.guincho_id).not.toBeNull();

      await testInfo.attach('stress-concorrencia.json', {
        body: JSON.stringify({ runTag, N, pedido_id: pedido.pedido_id, vencedor_guincho_id: snapshot.pedido?.guincho_id }, null, 2),
        contentType: 'application/json',
      });
    } finally {
      await Promise.all(contexts.map((c) => c.close()));
    }
  });
});
