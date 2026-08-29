import { test, expect } from '@playwright/test';
import {
  buildClienteBatch,
  buildGuinchoBatch,
  buildGuinchoMultisservicoBatch,
  buildEspecialistaBatch,
  isValidCpf,
  resolveWindowsImage,
} from '../helpers/account-factories';
import {
  cadastrarCliente,
  cadastrarVeiculo,
  cadastrarOficina,
  cadastrarGuincho,
  completarPerfilGuincho,
  configurarOperacaoGuincho,
  cadastrarDadosBancarios,
  fazerLogout,
} from '../helpers/onboarding';
import {
  aprovarContaGuinchoAdmin,
  declararCapacidadesReal,
  aprovarCapacidadesAdmin,
  podeAcessarTelaDeAceite,
  SERVICE_DISPLAY_NAME,
} from '../helpers/capabilities';
import { qaGuinchoIdPorEmail, qaCapacidadeStatus, seedPedidoOnsiteGenerico } from '../helpers/seed';
import { adminCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { RegistroPassos } from '../helpers/step-logger';

// STRESS-ONB-001 — stress de onboarding: 5 contas de cada perfil (cliente,
// guincho comum, guincho multisserviço, especialista), todas passando pelo
// cadastro REAL via UI (mesmo caminho de cadastro-cliente-bulk.spec.ts /
// cadastro-guincho-bulk.spec.ts, reaproveitado via qa/helpers/onboarding.ts
// — não duplicado). Roda cada grupo em série (não os 20 em paralelo): o
// próprio playwright.config.ts já documenta contenção real do XAMPP/MySQL
// sob carga concorrente — depois de validar que os 4 grupos passam em
// série, um teste separado de concorrência pura fica em
// stress-concorrencia.spec.ts (Fase 4).
test.describe.serial('stress de onboarding', () => {
  test('STRESS-ONB-001 | cria 20 contas completas (5 de cada perfil)', async ({ browser }, testInfo) => {
    testInfo.setTimeout(25 * 60_000);

    const runTag = process.env.QA_BATCH_RUN_TAG || String(Date.now());
    const uploadImage = resolveWindowsImage();

    const clientes = buildClienteBatch(5, runTag);
    const guinchos = buildGuinchoBatch(5, runTag);
    const multis = buildGuinchoMultisservicoBatch(5, runTag);
    const especialistas = buildEspecialistaBatch(5, runTag);

    const page = await browser.newPage();
    const adminPage = await browser.newPage();
    const registro = new RegistroPassos(testInfo, page);
    const criados = { clientes: 0, guinchos: 0, multisservico: 0, especialistas: 0 };
    const homologacao = { multisservico: null as null | Record<string, unknown>, especialista: null as null | Record<string, unknown> };

    try {
      // adminPage faz login UMA vez e permanece autenticada pelo resto do
      // teste (nunca há fazerLogout(adminPage) em nenhum bloco). Chamar
      // loginAs(adminPage, ...) de novo mais adiante navegaria para /login
      // já autenticado — o app redireciona pro dashboard sem exibir o form,
      // e fillFirstAvailable() falha por não achar input[name="email"].
      await registro.passo('[admin] login único para toda a homologação de capacidades', async () => {
        await loginAs(adminPage, adminCreds().email, adminCreds().password);
      }, { metadata: { system: 'Homologacao', class: 'Admin', phase: 'STRESS-CAP' } });

      for (const account of clientes) {
        expect(isValidCpf(account.cpfDigits)).toBeTruthy();
        await registro.passo(`[cliente] cadastro completo — ${account.email}`, async () => {
          await cadastrarCliente(page, account);
          await cadastrarVeiculo(page, account);
          await cadastrarOficina(page, account);
          await fazerLogout(page);
        }, { metadata: { system: 'Onboarding', class: 'Cliente', phase: 'STRESS' } });
        criados.clientes++;
      }

      for (const account of guinchos) {
        expect(isValidCpf(account.cpfDigits)).toBeTruthy();
        await registro.passo(`[guincho] cadastro completo — ${account.email}`, async () => {
          await cadastrarGuincho(page, account, uploadImage);
          await completarPerfilGuincho(page, account, uploadImage);
          await configurarOperacaoGuincho(page, account);
          await cadastrarDadosBancarios(page, account);
          await fazerLogout(page);
        }, { metadata: { system: 'Onboarding', class: 'Guincho', phase: 'STRESS' } });
        criados.guinchos++;
      }

      for (const [indice, account] of multis.entries()) {
        expect(isValidCpf(account.cpfDigits)).toBeTruthy();
        await registro.passo(`[multisservico] cadastro completo — ${account.email}`, async () => {
          await cadastrarGuincho(page, account, uploadImage);
          await completarPerfilGuincho(page, account, uploadImage);
          await configurarOperacaoGuincho(page, account);
          await cadastrarDadosBancarios(page, account);
          await fazerLogout(page);
        }, { metadata: { system: 'Onboarding', class: 'GuinchoMultisservico', phase: 'STRESS' } });
        criados.multisservico++;

        // Homologação real de capacidade (gap identificado na revisão de
        // 30/07/2026): só na PRIMEIRA conta do lote — o objetivo aqui é
        // provar que o caminho conta -> declarar -> aprovar -> matching
        // funciona de ponta a ponta via UI real, não repetir 5x a mesma
        // prova (isso já é coberto em volume pelo próprio cadastro acima).
        if (indice === 0) {
          const servicoAlvo = 'TIRE_CHANGE';
          const nomeServicoAlvo = SERVICE_DISPLAY_NAME[servicoAlvo];

          await registro.passo(`[multisservico] admin aprova a CONTA de ${account.email}`, async () => {
            await aprovarContaGuinchoAdmin(adminPage, account.email);
          }, { metadata: { system: 'Homologacao', class: 'ContaGuincho', phase: 'STRESS-CAP' } });

          await registro.passo(`[multisservico] declara capacidades reais (${account.services.join(', ')})`, async () => {
            await loginAs(page, account.email, account.password);
            const nomes = account.services
              .map((codigo) => SERVICE_DISPLAY_NAME[codigo])
              .filter((nome): nome is string => Boolean(nome));
            await declararCapacidadesReal(page, nomes);
            await fazerLogout(page);
          }, { metadata: { system: 'Homologacao', class: 'ProviderCapability', function: 'declarar', phase: 'STRESS-CAP' } });

          await registro.passo(`[multisservico] admin aprova as capacidades declaradas`, async () => {
            await aprovarCapacidadesAdmin(adminPage, account.nome);
          }, { metadata: { system: 'Homologacao', class: 'ProviderCapability', function: 'aprovar', phase: 'STRESS-CAP' } });

          await registro.passo(`[multisservico] matching real reconhece a capacidade aprovada (${servicoAlvo})`, async () => {
            const guinchoId = qaGuinchoIdPorEmail(account.email);
            expect(guinchoId.ok, `não achou guincho_id para ${account.email}: ${JSON.stringify(guinchoId)}`).toBeTruthy();

            const statusCapacidade = qaCapacidadeStatus(guinchoId.guincho_id!, servicoAlvo);
            expect(statusCapacidade.ok).toBeTruthy();
            expect(statusCapacidade.approval_status).toBe('APPROVED');
            expect(statusCapacidade.possui_capacidade_aprovada).toBeTruthy();

            const pedido = seedPedidoOnsiteGenerico(servicoAlvo, `stress-onb-${Date.now()}`);
            expect(pedido.ok, `seed do pedido ON_SITE genérico falhou: ${JSON.stringify(pedido)}`).toBeTruthy();

            await loginAs(page, account.email, account.password);
            const acessou = await podeAcessarTelaDeAceite(page, pedido.pedido_id!);
            expect(acessou, 'tela de aceite não abriu — matching por capacidade não reconheceu a aprovação real').toBeTruthy();
            await fazerLogout(page);

            homologacao.multisservico = { email: account.email, servico: servicoAlvo, nomeServicoAlvo, pedido_id: pedido.pedido_id, aceite_liberado: acessou };
          }, { metadata: { system: 'Homologacao', class: 'ProviderCapability', function: 'possuiCapacidadeAprovada', phase: 'STRESS-CAP' } });
        }
      }

      for (const [indice, account] of especialistas.entries()) {
        expect(isValidCpf(account.cpfDigits)).toBeTruthy();
        await registro.passo(`[especialista] cadastro completo — ${account.email}`, async () => {
          await cadastrarGuincho(page, account, uploadImage);
          await completarPerfilGuincho(page, account, uploadImage);
          await configurarOperacaoGuincho(page, account);
          await cadastrarDadosBancarios(page, account);
          await fazerLogout(page);
        }, { metadata: { system: 'Onboarding', class: 'Especialista', phase: 'STRESS' } });
        criados.especialistas++;

        if (indice === 0) {
          const servicoAlvo = 'ELECTRICAL_DIAGNOSIS';

          await registro.passo(`[especialista] admin aprova a CONTA de ${account.email}`, async () => {
            await aprovarContaGuinchoAdmin(adminPage, account.email);
          }, { metadata: { system: 'Homologacao', class: 'ContaGuincho', phase: 'STRESS-CAP' } });

          await registro.passo(`[especialista] declara capacidades reais (${account.services.join(', ')})`, async () => {
            await loginAs(page, account.email, account.password);
            const nomes = account.services
              .map((codigo) => SERVICE_DISPLAY_NAME[codigo])
              .filter((nome): nome is string => Boolean(nome));
            await declararCapacidadesReal(page, nomes);
            await fazerLogout(page);
          }, { metadata: { system: 'Homologacao', class: 'ProviderCapability', function: 'declarar', phase: 'STRESS-CAP' } });

          await registro.passo(`[especialista] admin aprova as capacidades declaradas`, async () => {
            await aprovarCapacidadesAdmin(adminPage, account.nome);
          }, { metadata: { system: 'Homologacao', class: 'ProviderCapability', function: 'aprovar', phase: 'STRESS-CAP' } });

          await registro.passo(`[especialista] matching real reconhece a capacidade aprovada (${servicoAlvo})`, async () => {
            const guinchoId = qaGuinchoIdPorEmail(account.email);
            expect(guinchoId.ok, `não achou guincho_id para ${account.email}: ${JSON.stringify(guinchoId)}`).toBeTruthy();

            const statusCapacidade = qaCapacidadeStatus(guinchoId.guincho_id!, servicoAlvo);
            expect(statusCapacidade.ok).toBeTruthy();
            expect(statusCapacidade.approval_status).toBe('APPROVED');
            expect(statusCapacidade.possui_capacidade_aprovada).toBeTruthy();

            const pedido = seedPedidoOnsiteGenerico(servicoAlvo, `stress-onb-esp-${Date.now()}`);
            expect(pedido.ok, `seed do pedido ON_SITE genérico falhou: ${JSON.stringify(pedido)}`).toBeTruthy();

            await loginAs(page, account.email, account.password);
            const acessou = await podeAcessarTelaDeAceite(page, pedido.pedido_id!);
            expect(acessou, 'tela de aceite não abriu — matching por capacidade não reconheceu a aprovação real').toBeTruthy();
            await fazerLogout(page);

            homologacao.especialista = { email: account.email, servico: servicoAlvo, pedido_id: pedido.pedido_id, aceite_liberado: acessou };
          }, { metadata: { system: 'Homologacao', class: 'ProviderCapability', function: 'possuiCapacidadeAprovada', phase: 'STRESS-CAP' } });
        }
      }

      await testInfo.attach('onboarding-stress.json', {
        body: JSON.stringify({ runTag, criados, homologacao }, null, 2),
        contentType: 'application/json',
      });
    } finally {
      await registro.finalizar();
      await page.close();
      await adminPage.close();
    }
  });
});
