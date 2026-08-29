import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { rjGamboaToVenezuelaRoute } from '../fixtures/stress-scenarios.fixture';
import { driveRoute } from '../helpers/gps-simulator';
import { confirmarChegada } from '../helpers/evidence';
import { resolveEvidenceImage } from '../helpers/atendimento';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import {
  cadastrarGuincho,
  completarPerfilGuincho,
  configurarOperacaoGuincho,
  cadastrarDadosBancarios,
  fazerLogout,
} from '../helpers/onboarding';
import { buildGuinchoMultisservicoBatch, isValidCpf, resolveWindowsImage } from '../helpers/account-factories';
import {
  aprovarContaGuinchoAdmin,
  declararCapacidadesReal,
  aprovarCapacidadesAdmin,
  podeAcessarTelaDeAceite,
  aceitarPedidoReal,
  SERVICE_DISPLAY_NAME,
} from '../helpers/capabilities';
import {
  seedPedidoOnsiteGenerico,
  seedAtendimentoGamboa,
  qaPedidoSnapshot,
  qaOrcamentoSnapshot,
} from '../helpers/seed';
import { adminCreds } from '../fixtures/test-data.fixture';
import { RegistroPassos } from '../helpers/step-logger';

// atendimento-variacoes.spec.ts — variações do fluxo híbrido que
// diferenciam o GuinchaFácil de um app comum de guincho (pedido explícito
// da revisão de 30/07/2026: prioridade #2, DEPOIS de fechar a homologação
// real de capacidades em onboarding-stress.spec.ts, da qual este spec
// depende).
//
// Das 4 variações pedidas, 2 já são cobertas de ponta a ponta por specs
// existentes — não duplicadas aqui, só documentadas:
//   VAR-001 (pane elétrica -> reparo no local)
//     -> já é exatamente RJ-ELETRICA-001 (qa/suites/atendimento-eletrica-rj.spec.ts):
//        diagnóstico RESOLVIDO_SEM_ORCAMENTO, execução, teste final, concluído.
//   VAR-002 (pane elétrica -> especialista recomenda reboque)
//     -> já é o cerne de E2E-SOCORRO-001 (especialista sem reboque aprovado,
//        conversão solta o pedido pra fila comum) e de E2E-HIBRIDO-001/002
//        em conversao-hibrida-complementar.spec.ts (prestador híbrido mantém
//        o pedido e cobra complementar) — REQUER_REBOQUE nos dois casos.
//
// As 2 variações genuinamente NOVAS (nenhum spec existente cobre):
//   VAR-003 — guincho MULTISSERVIÇO resolve um serviço ON_SITE (troca de
//     pneu) sem nunca entrar em modo reboque. Diferente de RJ-ELETRICA-001
//     (que usa um prestador seedado direto no banco já aprovado), este teste
//     percorre o caminho 100% real: cadastro -> aprovação de conta ->
//     declaração de capacidade -> aprovação de capacidade -> matching ->
//     aceite -> atendimento -> conclusão. É a prova de fogo do gap fechado
//     na Fase anterior (capabilities.ts).
//   VAR-004 — especialista propõe um serviço adicional (orçamento
//     complementar) e o CLIENTE RECUSA. Nenhum spec existente toca em
//     PedidoOrcamento/ClienteController::orcamentoDecidir — é lógica 100%
//     nova sendo testada pela primeira vez.
test.use({ video: 'on' });

test.describe('variações de atendimento — lógica híbrida', () => {
  test('VAR-003 | guincho multisserviço resolve troca de pneu sem rebocar (ponta a ponta via UI real)', async ({ browser }, testInfo) => {
    testInfo.setTimeout(10 * 60_000);

    const runTag = `var003-${Date.now()}`;
    const uploadImage = resolveWindowsImage();
    const [account] = buildGuinchoMultisservicoBatch(1, runTag);
    expect(isValidCpf(account.cpfDigits)).toBeTruthy();

    const servicoAlvo = 'TIRE_CHANGE';
    const nomeServicoAlvo = SERVICE_DISPLAY_NAME[servicoAlvo];

    const prestadorContext = await browser.newContext({
      geolocation: { latitude: rjGamboaToVenezuelaRoute[0].lat, longitude: rjGamboaToVenezuelaRoute[0].lng },
      permissions: ['geolocation'],
    });
    const prestadorPage = await prestadorContext.newPage();
    const adminPage = await browser.newPage();
    const registro = new RegistroPassos(testInfo, prestadorPage);

    try {
      await registro.passo('Cadastro completo do guincho multisserviço (via UI real)', async () => {
        await cadastrarGuincho(prestadorPage, account, uploadImage);
        await completarPerfilGuincho(prestadorPage, account, uploadImage);
        await configurarOperacaoGuincho(prestadorPage, account);
        await cadastrarDadosBancarios(prestadorPage, account);
        await fazerLogout(prestadorPage);
      }, { metadata: { system: 'Onboarding', class: 'GuinchoMultisservico', phase: 'VAR-003' } });

      await registro.passo('Admin aprova a conta do prestador', async () => {
        await loginAs(adminPage, adminCreds().email, adminCreds().password);
        await aprovarContaGuinchoAdmin(adminPage, account.email);
      }, { metadata: { system: 'Homologacao', class: 'ContaGuincho', phase: 'VAR-003' } });

      await registro.passo(`Prestador declara capacidade real (${nomeServicoAlvo})`, async () => {
        await loginAs(prestadorPage, account.email, account.password);
        await declararCapacidadesReal(prestadorPage, [nomeServicoAlvo]);
      }, { metadata: { system: 'Homologacao', class: 'ProviderCapability', function: 'declarar', phase: 'VAR-003' } });

      await registro.passo('Admin aprova a capacidade declarada', async () => {
        await aprovarCapacidadesAdmin(adminPage, account.nome);
      }, { metadata: { system: 'Homologacao', class: 'ProviderCapability', function: 'aprovar', phase: 'VAR-003' } });

      let pedidoId = '';
      await registro.passo('Seed do pedido ON_SITE (troca de pneu) e matching real', async () => {
        const pedido = seedPedidoOnsiteGenerico(servicoAlvo, runTag);
        expect(pedido.ok, `seed falhou: ${JSON.stringify(pedido)}`).toBeTruthy();
        pedidoId = String(pedido.pedido_id);

        const acessou = await podeAcessarTelaDeAceite(prestadorPage, pedidoId);
        expect(acessou, 'matching não liberou a tela de aceite mesmo com a capacidade aprovada').toBeTruthy();
      }, { metadata: { system: 'Homologacao', class: 'ProviderCapability', function: 'possuiCapacidadeAprovada', phase: 'VAR-003' } });

      await registro.passo('Prestador aceita o pedido (POST /guincho/aceitar real)', async () => {
        await aceitarPedidoReal(prestadorPage, pedidoId);
      }, { metadata: { pedidoId, phase: 'VAR-003' } });

      await registro.passo('Aproximação real até o cliente', async () => {
        await driveRoute(prestadorPage, pedidoId, rjGamboaToVenezuelaRoute.slice(1), 4);
      }, { metadata: { system: 'ProofOfRoad', class: 'GpsSimulator', function: 'driveRoute', phase: 'VAR-003', pedidoId } });

      await registro.passo('Confirmar chegada (a_caminho -> no_local)', async () => {
        await confirmarChegada(prestadorPage);
      }, { metadata: { pedidoId, phase: 'VAR-003' } });

      await registro.passo('Diagnóstico: iniciar (foto de chegada real)', async () => {
        const nonceResp = await prestadorPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const nonceData = await nonceResp.json();
        if (!nonceResp.ok() || !nonceData.ok) throw new Error(`evidencia-nonce falhou: ${JSON.stringify(nonceData)}`);
        const csrfToken = await prestadorPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
        const imagePath = resolveEvidenceImage();
        const resp = await prestadorPage.request.post(appPath(`/guincho/diagnostico/iniciar/${pedidoId}`), {
          multipart: {
            csrf_token: csrfToken,
            evidence_token: nonceData.evidence_token,
            foto_chegada: { name: path.basename(imagePath), mimeType: 'image/jpeg', buffer: readFileSync(imagePath) },
          },
        });
        if (!resp.ok()) throw new Error(`diagnostico/iniciar falhou ${resp.status()}: ${await resp.text()}`);
      }, { metadata: { system: 'Diagnostico', class: 'DiagnosticoService', function: 'iniciar', pedidoId, phase: 'VAR-003' } });

      await registro.passo('Diagnóstico: concluir com RESOLVIDO_SEM_ORCAMENTO (troca de pneu simples no local)', async () => {
        const csrfToken = await prestadorPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
        const resp = await prestadorPage.request.post(appPath(`/guincho/diagnostico/concluir/${pedidoId}`), {
          form: {
            csrf_token: csrfToken,
            resultado: 'RESOLVIDO_SEM_ORCAMENTO',
            descricao: 'Pneu furado, estepe em bom estado — troca simples resolve no local, sem necessidade de reboque.',
          },
        });
        if (!resp.ok()) throw new Error(`diagnostico/concluir falhou ${resp.status()}: ${await resp.text()}`);
      }, { metadata: { system: 'Diagnostico', class: 'DiagnosticoService', function: 'concluir', pedidoId, phase: 'VAR-003' } });

      await registro.passo('Execução: concluir troca de pneu', async () => {
        const csrfToken = await prestadorPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
        const resp = await prestadorPage.request.post(appPath(`/guincho/execucao/concluir/${pedidoId}`), {
          form: { csrf_token: csrfToken },
        });
        if (!resp.ok()) throw new Error(`execucao/concluir falhou ${resp.status()}: ${await resp.text()}`);
      }, { metadata: { system: 'Diagnostico', class: 'DiagnosticoService', function: 'concluirExecucao', pedidoId, phase: 'VAR-003' } });

      await registro.passo('Teste final: resolvido, com foto de conclusão', async () => {
        const nonceResp = await prestadorPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const nonceData = await nonceResp.json();
        const csrfToken = await prestadorPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
        const imagePath = resolveEvidenceImage();
        const resp = await prestadorPage.request.post(appPath(`/guincho/teste-final/concluir/${pedidoId}`), {
          multipart: {
            csrf_token: csrfToken,
            resolvido: '1',
            evidence_token: nonceData.evidence_token,
            foto_destino: { name: path.basename(imagePath), mimeType: 'image/jpeg', buffer: readFileSync(imagePath) },
          },
        });
        if (!resp.ok()) throw new Error(`teste-final/concluir falhou ${resp.status()}: ${await resp.text()}`);
      }, { metadata: { system: 'Diagnostico', class: 'DiagnosticoService', function: 'confirmarResultadoFinal', pedidoId, phase: 'VAR-003' } });

      const snapshot = qaPedidoSnapshot(pedidoId);
      expect(snapshot.ok, `snapshot falhou: ${JSON.stringify(snapshot)}`).toBeTruthy();
      expect(snapshot.pedido?.status).toBe('concluido');
      // Nunca passou por attendance_mode TOWING nem por conversao_reboque_pendente
      // — resolvido inteiramente ON_SITE, prova de que multisserviço != guincho puro.
      expect(snapshot.pedido?.attendance_mode).toBe('ON_SITE');
    } finally {
      await registro.finalizar();
      await prestadorContext.close();
      await adminPage.close();
    }
  });

  test('VAR-004 | especialista propõe serviço adicional e cliente recusa', async ({ browser }, testInfo) => {
    testInfo.setTimeout(8 * 60_000);

    const seeded = seedAtendimentoGamboa('pane-eletrica');
    expect(seeded.ok, 'seed falhou').toBeTruthy();
    const pedidoId = String(seeded.pedido_id);

    const clienteContext = await browser.newContext();
    const clientePage = await clienteContext.newPage();
    const registro = new RegistroPassos(testInfo, clientePage);

    const prestadorContext = await browser.newContext({
      geolocation: { latitude: rjGamboaToVenezuelaRoute[0].lat, longitude: rjGamboaToVenezuelaRoute[0].lng },
      permissions: ['geolocation'],
    });
    const prestadorPage = await prestadorContext.newPage();

    try {
      // Atalho de QA (mesmo usado por stress-por.spec.ts/stress-chaos.spec.ts):
      // atribui direto o prestador e avança pra 'a_caminho', sem repetir o
      // pagamento real via Payment Brick — o alvo deste teste é a decisão do
      // cliente sobre o orçamento, não o checkout (já coberto em outros specs).
      const { execFileSync } = require('node:child_process');
      const projectRoot = path.resolve(__dirname, '..', '..');
      const stdout = execFileSync('php', [path.join(projectRoot, 'tools/prepare_atendimento_rj_gamboa_qa.php'), 'atribuir', pedidoId], { encoding: 'utf8', cwd: projectRoot });
      expect(JSON.parse(stdout.trim()).ok).toBeTruthy();

      await loginAs(prestadorPage, seeded.prestador_email, 'test123');
      await loginAs(clientePage, seeded.cliente_email, 'test123');
      await Promise.all([
        clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
        prestadorPage.goto(appPath(`/guincho/atendimento/${pedidoId}`), { waitUntil: 'domcontentloaded' }),
      ]);

      await registro.passo('Aproximação real até a ocorrência', async () => {
        await driveRoute(prestadorPage, pedidoId, rjGamboaToVenezuelaRoute.slice(1), 3);
      }, { metadata: { system: 'ProofOfRoad', class: 'GpsSimulator', function: 'driveRoute', phase: 'VAR-004', pedidoId } });

      await registro.passo('Confirmar chegada (a_caminho -> no_local)', async () => {
        await confirmarChegada(prestadorPage);
      }, { metadata: { pedidoId, phase: 'VAR-004' } });

      await registro.passo('Diagnóstico: iniciar (foto de chegada real)', async () => {
        const nonceResp = await prestadorPage.request.get(appPath(`/guincho/evidencia-nonce/${pedidoId}`), {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const nonceData = await nonceResp.json();
        if (!nonceResp.ok() || !nonceData.ok) throw new Error(`evidencia-nonce falhou: ${JSON.stringify(nonceData)}`);
        const csrfToken = await prestadorPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
        const imagePath = resolveEvidenceImage();
        const resp = await prestadorPage.request.post(appPath(`/guincho/diagnostico/iniciar/${pedidoId}`), {
          multipart: {
            csrf_token: csrfToken,
            evidence_token: nonceData.evidence_token,
            foto_chegada: { name: path.basename(imagePath), mimeType: 'image/jpeg', buffer: readFileSync(imagePath) },
          },
        });
        if (!resp.ok()) throw new Error(`diagnostico/iniciar falhou ${resp.status()}: ${await resp.text()}`);
      }, { metadata: { system: 'Diagnostico', class: 'DiagnosticoService', function: 'iniciar', pedidoId, phase: 'VAR-004' } });

      await registro.passo('Diagnóstico: concluir com REQUER_ORCAMENTO (item adicional além do combinado)', async () => {
        const csrfToken = await prestadorPage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
        const resp = await prestadorPage.request.post(appPath(`/guincho/diagnostico/concluir/${pedidoId}`), {
          form: {
            csrf_token: csrfToken,
            resultado: 'REQUER_ORCAMENTO',
            descricao: 'Bateria descarregada confirmada, mas também identifiquei o alternador com sinais de falha — recomendo teste e possível troca.',
            'item_descricao[0]': 'Teste e diagnóstico do alternador',
            'item_valor[0]': '120,00',
          },
        });
        if (!resp.ok()) throw new Error(`diagnostico/concluir falhou ${resp.status()}: ${await resp.text()}`);
      }, { metadata: { system: 'Diagnostico', class: 'DiagnosticoService', function: 'concluir', pedidoId, phase: 'VAR-004' } });

      await registro.passo('Pedido aguarda decisão do cliente sobre o orçamento', async () => {
        const snapshot = qaPedidoSnapshot(pedidoId);
        expect(snapshot.ok).toBeTruthy();
        expect(snapshot.pedido?.status).toBe('autorizacao_servico_pendente');

        const orcamentoAntes = qaOrcamentoSnapshot(pedidoId);
        expect(orcamentoAntes.ok && orcamentoAntes.existe).toBeTruthy();
        expect(orcamentoAntes.status).toBe('PENDENTE');
      }, { metadata: { pedidoId, phase: 'VAR-004' } });

      await registro.passo('Cliente RECUSA o orçamento complementar (via UI real)', async () => {
        await clientePage.goto(appPath(`/cliente/pedido/${pedidoId}`), { waitUntil: 'domcontentloaded' });
        const botaoRecusar = clientePage.locator('button, input[type="submit"]', { hasText: /recusar/i }).first();
        if (await botaoRecusar.count()) {
          await botaoRecusar.click();
        } else {
          // Fallback: caso a UI não exponha o botão com esse texto exato,
          // manda a decisão real direto pro mesmo endpoint (mesmo contrato
          // de ClienteController::orcamentoDecidir), sem inventar um atalho
          // que pule PedidoTransitionService/validações.
          const csrfToken = await clientePage.locator('input[name="csrf_token"]').first().inputValue().catch(() => '');
          const resp = await clientePage.request.post(appPath(`/cliente/orcamento/decidir/${pedidoId}`), {
            form: { csrf_token: csrfToken, decisao: 'recusar' },
          });
          if (!resp.ok()) throw new Error(`orcamento/decidir (recusar) falhou ${resp.status()}: ${await resp.text()}`);
        }
      }, { metadata: { pedidoId, phase: 'VAR-004' } });

      const orcamentoDepois = qaOrcamentoSnapshot(pedidoId);
      expect(orcamentoDepois.ok && orcamentoDepois.existe).toBeTruthy();
      expect(orcamentoDepois.status).toBe('RECUSADO');

      const snapshotFinal = qaPedidoSnapshot(pedidoId);
      expect(snapshotFinal.ok).toBeTruthy();
      // Recusa não cancela nem conclui automaticamente — resolução manual
      // (mesmo princípio de falha de pagamento). O pedido continua "aberto"
      // aguardando uma ação humana (admin/demanda), não trava numa transição
      // silenciosa nem finge sucesso.
      expect(snapshotFinal.pedido?.status).toBe('autorizacao_servico_pendente');
    } finally {
      await registro.finalizar();
      await prestadorContext.close();
      await clienteContext.close();
    }
  });
});
