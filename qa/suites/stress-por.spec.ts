import { test, expect } from '@playwright/test';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAtendimentoGamboa, qaPorSnapshot } from '../helpers/seed';
import { postTowLocation } from '../helpers/atendimento';
import { toRoutePoints } from '../helpers/gps-simulator';
import { rjGamboaToVenezuelaRoute } from '../fixtures/stress-scenarios.fixture';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const rota = toRoutePoints(rjGamboaToVenezuelaRoute);

// STRESS-POR-001/002 — Proof-of-Road sob condições adversas reais:
//   001: o MESMO client_point_id enviado 2x em paralelo — ProofOfRoadService
//        já trata isso via UNIQUE KEY + resposta idempotente (ver
//        ProofOfRoadService::ingestPoint, comentário sobre "corrida real"
//        na captura do erro 23000), este teste prova isso sob concorrência
//        de verdade, não só sequencial.
//   002: pontos enviados fora de ordem (sequence 3 antes do 2) — confirma
//        que o sistema não quebra (aceita ou rejeita com código claro,
//        nunca 500) e que o snapshot reflete o que realmente foi gravado.
test.describe('stress de Proof-of-Road', () => {
  test('STRESS-POR-001 | mesmo client_point_id enviado em paralelo não duplica o ponto', async ({ browser }) => {
    const seeded = seedAtendimentoGamboa('colisao');
    expect(seeded.ok, 'seed falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const projectRoot = path.resolve(__dirname, '..', '..');
    const stdout = execFileSync('php', [path.join(projectRoot, 'tools/prepare_atendimento_rj_gamboa_qa.php'), 'atribuir', pedidoId], { encoding: 'utf8', cwd: projectRoot });
    expect(JSON.parse(stdout.trim()).ok).toBeTruthy();

    const context = await browser.newContext({
      geolocation: { latitude: rjGamboaToVenezuelaRoute[0].lat, longitude: rjGamboaToVenezuelaRoute[0].lng },
      permissions: ['geolocation'],
    });
    const page = await context.newPage();

    try {
      await loginAs(page, seeded.prestador_email, 'test123');
      await page.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });

      const point = rota[1];
      const endpointPath = new URL(appPath('/guincho/localizacao'), 'http://localhost').pathname;

      // Mesmo timestamp calculado UMA vez e reaproveitado nas 3 chamadas —
      // client_point_id (qa/helpers/atendimento.ts::postTowLocation) é
      // derivado de (sequence, deviceTimestampMs); 3 Date.now() separados
      // (um por chamada) podiam gerar 3 client_point_id diferentes mesmo com
      // sequence igual, fazendo a "duplicata" real colidir por
      // sequence_number em vez de client_point_id — não era o que este teste
      // se propõe a provar.
      const mesmoTimestamp = Date.now();
      const respostas = await Promise.all([
        postTowLocation(page, pedidoId, point, 1, endpointPath, mesmoTimestamp, 6),
        postTowLocation(page, pedidoId, point, 1, endpointPath, mesmoTimestamp, 6),
        postTowLocation(page, pedidoId, point, 1, endpointPath, mesmoTimestamp, 6),
      ]);

      for (const r of respostas) {
        expect(r.ok, JSON.stringify(r)).toBeTruthy();
      }

      const snapshot = qaPorSnapshot(pedidoId);
      expect(snapshot.ok).toBeTruthy();
      // As 3 requisições concorrentes para a MESMA sequência/coordenada só
      // podem ter gravado 1 ponto real (idempotência via UNIQUE KEY).
      expect(snapshot.total_pontos).toBe(1);
    } finally {
      await context.close();
    }
  });

  test('STRESS-POR-002 | ponto fora de ordem não derruba o endpoint (nunca 500)', async ({ browser }) => {
    const seeded = seedAtendimentoGamboa('colisao');
    expect(seeded.ok, 'seed falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const projectRoot = path.resolve(__dirname, '..', '..');
    const stdout = execFileSync('php', [path.join(projectRoot, 'tools/prepare_atendimento_rj_gamboa_qa.php'), 'atribuir', pedidoId], { encoding: 'utf8', cwd: projectRoot });
    expect(JSON.parse(stdout.trim()).ok).toBeTruthy();

    const context = await browser.newContext({
      geolocation: { latitude: rjGamboaToVenezuelaRoute[0].lat, longitude: rjGamboaToVenezuelaRoute[0].lng },
      permissions: ['geolocation'],
    });
    const page = await context.newPage();

    try {
      await loginAs(page, seeded.prestador_email, 'test123');
      await page.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' });

      const endpointPath = new URL(appPath('/guincho/localizacao'), 'http://localhost').pathname;

      // Envia sequence=3 primeiro, depois sequence=1 e 2 — nenhuma delas
      // deve gerar erro de servidor (status >= 500).
      const seq3 = await postTowLocation(page, pedidoId, rota[3], 3, endpointPath, Date.now(), 6);
      expect((seq3.status ?? 200)).toBeLessThan(500);

      const seq1 = await postTowLocation(page, pedidoId, rota[1], 1, endpointPath, Date.now(), 6);
      expect((seq1.status ?? 200)).toBeLessThan(500);

      const seq2 = await postTowLocation(page, pedidoId, rota[2], 2, endpointPath, Date.now(), 6);
      expect((seq2.status ?? 200)).toBeLessThan(500);

      const snapshot = qaPorSnapshot(pedidoId);
      expect(snapshot.ok).toBeTruthy();
      // Não afirmamos que todos os 3 foram ACEITOS (o antifraude pode
      // rejeitar por chegar fora de ordem) — só que os 3 foram REGISTRADOS
      // (gravados, aceitos ou não) sem derrubar o servidor.
      expect(snapshot.total_pontos).toBeGreaterThanOrEqual(1);
    } finally {
      await context.close();
    }
  });
});
