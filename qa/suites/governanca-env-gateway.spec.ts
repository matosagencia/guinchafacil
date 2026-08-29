import { test, expect } from '@playwright/test';
import { appPath } from '../helpers/paths';
import { loginAs, clickFirstAvailable } from '../helpers/auth';
import { seedAdmin, envAuditoriaUltima, gatewayAtivo } from '../helpers/seed';
import { RegistroPassos } from '../helpers/step-logger';
import { criarPedidoViaFormulario } from '../helpers/pedido-form';

/**
 * Suite E — Governança (§21.2 da constituição mestra).
 *
 * Cobre os dois itens que ainda não tinham nenhum teste E2E: edição do
 * .env pelo admin com auditoria (E1) e seleção administrativa do gateway
 * de cobrança, sem o cliente poder escolher (E2). O backend de ambos já
 * existia (AdminController::envGovernanca/envSalvar/envAuditoria,
 * PAYMENT_GATEWAY_ACTIVE + gatewayEfetivo()) — o gap era só a suíte.
 */
test.describe('governança — env e gateway', () => {
  test.describe.configure({ mode: 'serial' });

  let adminEmail: string;
  let adminPassword: string;

  test.beforeAll(() => {
    const admin = seedAdmin();
    expect(admin.ok, `Falha ao seedar admin: ${JSON.stringify(admin)}`).toBe(true);
    adminEmail = admin.admin_email;
    adminPassword = admin.admin_password;
  });

  test('E2E-GOV-E1 | admin edita campo não sensível do .env, salva, e a auditoria registra a alteração', async ({ page }, testInfo) => {
    testInfo.setTimeout(60_000);
    const registro = new RegistroPassos(testInfo, page);

    try {
      await registro.passo('Login como admin', async () => {
        await loginAs(page, adminEmail, adminPassword);
      });

      await registro.passo('Abrir /admin/env', async () => {
        await page.goto(appPath('/admin/env'), { waitUntil: 'domcontentloaded' });
        await expect(page.locator('#formEnv')).toBeVisible();
      });

      // COMPANY_WHATSAPP é institucional, não sensível (não está na lista
      // $sensivel do controller/view) — seguro pra QA escrever/reescrever
      // repetidamente sem arriscar quebrar credencial nenhuma.
      const marcador = `2199999${String(Date.now()).slice(-4)}`;
      await registro.passo('Preencher COMPANY_WHATSAPP com valor marcado', async () => {
        const campo = page.locator('input[name="env[COMPANY_WHATSAPP]"]');
        await expect(campo).toBeVisible();
        await campo.fill(marcador);
      });

      await registro.passo('Abrir modal de confirmação e salvar', async () => {
        await clickFirstAvailable(page, ['button:has-text("Salvar e Auditar")']);
        const modal = page.locator('#modalConfirmar');
        await expect(modal).toBeVisible();
        await Promise.all([
          page.waitForURL(/\/admin\/env\?msg=/, { timeout: 15000 }),
          modal.locator('[data-submit-form="formEnv"]').click(),
        ]);
      });

      await registro.passo('Confirmar mensagem de sucesso na UI', async () => {
        await expect(page.locator('.alert-success')).toContainText('salvo com sucesso e auditado');
      });

      const auditoria = await registro.passo('Confirmar auditoria gravada no banco (env_auditoria)', async () => {
        const resultado = envAuditoriaUltima('COMPANY_WHATSAPP');
        expect(resultado.ok, `Auditoria não encontrada: ${JSON.stringify(resultado)}`).toBe(true);
        expect(resultado.acao).toBe('alterado');
        // Campo não sensível não é mascarado — deve bater com o valor gravado.
        expect(resultado.valor_mascarado).toBe(marcador);
        return resultado;
      });

      await testInfo.attach('env-auditoria-registro.json', {
        body: JSON.stringify(auditoria, null, 2),
        contentType: 'application/json',
      });

      await registro.passo('Confirmar valor persistido ao reabrir a tela', async () => {
        await page.goto(appPath('/admin/env'), { waitUntil: 'domcontentloaded' });
        await expect(page.locator('input[name="env[COMPANY_WHATSAPP]"]')).toHaveValue(marcador);
      });
    } finally {
      await registro.finalizar();
    }
  });

  test('E2E-GOV-E2 | admin troca o gateway ativo e o checkout do cliente reflete a troca sem o cliente escolher', async ({ page }, testInfo) => {
    testInfo.setTimeout(90_000);
    const registro = new RegistroPassos(testInfo, page);

    const original = gatewayAtivo();
    expect(original.ok, `Falha ao ler gateway ativo original: ${JSON.stringify(original)}`).toBe(true);
    const gatewayOriginal = (original.payment_gateway_active ?? 'mercadopago') as 'mercadopago' | 'pagseguro';
    const gatewayAlvo: 'mercadopago' | 'pagseguro' = gatewayOriginal === 'mercadopago' ? 'pagseguro' : 'mercadopago';

    async function selecionarGatewayViaAdmin(valor: 'mercadopago' | 'pagseguro'): Promise<void> {
      await page.goto(appPath('/admin/env'), { waitUntil: 'domcontentloaded' });
      await page.locator('select[name="env[PAYMENT_GATEWAY_ACTIVE]"]').selectOption(valor);
      await clickFirstAvailable(page, ['button:has-text("Salvar e Auditar")']);
      const modal = page.locator('#modalConfirmar');
      await expect(modal).toBeVisible();
      await Promise.all([
        page.waitForURL(/\/admin\/env(\?|$)/, { timeout: 15000 }),
        modal.locator('[data-submit-form="formEnv"]').click(),
      ]);
    }

    try {
      await registro.passo('Login como admin', async () => {
        await loginAs(page, adminEmail, adminPassword);
      });

      await registro.passo(`Trocar gateway ativo para ${gatewayAlvo}`, async () => {
        await selecionarGatewayViaAdmin(gatewayAlvo);
      });

      await registro.passo('Confirmar persistência do novo gateway (banco/config)', async () => {
        const atual = gatewayAtivo();
        expect(atual.ok).toBe(true);
        expect(atual.payment_gateway_active).toBe(gatewayAlvo);
      });

      // Precisa de uma sessão de CLIENTE (não admin) pra criar o pedido e
      // ver o checkout como o cliente veria — troca de contexto de login
      // na mesma página, igual outras suítes fazem.
      const clienteEmail = process.env.TEST_CLIENTE_EMAIL || process.env.TEST_USER_EMAIL || 'pw_teste@guinchafacil.com';
      const clienteSenha = process.env.TEST_CLIENTE_PASSWORD || process.env.TEST_USER_PASSWORD || 'test123';

      await registro.passo('Login como cliente e criar novo pedido', async () => {
        // A sessão de admin ainda está ativa — /login redireciona quem já
        // está autenticado direto pro dashboard (sem mostrar o formulário),
        // por isso precisa de /logout explícito antes de trocar de usuário
        // (mesmo padrão de onboarding-completo.spec.ts).
        await page.goto(appPath('/logout'), { waitUntil: 'domcontentloaded' });
        await page.waitForURL(/login$/i, { timeout: 20000 });
        await loginAs(page, clienteEmail, clienteSenha);
        await criarPedidoViaFormulario(page, {
          origem: 'Rua da Gamboa, 131, Rio de Janeiro',
          destino: 'Rua Riachuelo, 221, Rio de Janeiro',
          tipoProblema: 'bateria',
        });
      });

      await registro.passo(`Confirmar que o checkout reflete ${gatewayAlvo} e não expõe escolha ao cliente`, async () => {
        await expect(page).toHaveURL(/\/pagamento\/checkout\//);

        if (gatewayAlvo === 'mercadopago') {
          // Painel MP só aparece quando o gateway ativo é mercadopago E as
          // credenciais MP validam (MP_ACCESS_TOKEN real de sandbox — já
          // confirmado nesta suíte por pagamento-sandbox.spec.ts).
          await expect(page.locator('#mp-payment-brick-container')).toBeVisible();
          await expect(page.locator('#ps-form')).toHaveCount(0);
        } else {
          // NOTA: este ambiente de QA tem PS_TOKEN com valor placeholder
          // (A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6), que
          // PagamentoController::pagSeguroConfigurado() rejeita de propósito
          // (guarda contra token fake). Por isso o painel #ps-form não
          // renderiza aqui — comportamento correto e seguro do sistema
          // (nenhum formulário de pagamento quebrado é exibido), mas não dá
          // pra validar o form do PagSeguro de verdade sem uma credencial de
          // sandbox real. Pendência conhecida: obter PS_EMAIL/PS_TOKEN reais
          // de sandbox do PagSeguro pra fechar esta ponta.
          //
          // O que este teste SEGUE validando com valor real mesmo assim: o
          // painel do MercadoPago não aparece mais (prova que a troca de
          // gateway teve efeito real no checkout, não só no banco).
          await expect(page.locator('#mp-payment-brick-container')).toHaveCount(0);
        }

        // O cliente não pode ter nenhum controle de seleção de gateway na
        // tela — nem radio, nem select, nem botão com esse propósito. A
        // decisão é 100% do admin via PAYMENT_GATEWAY_ACTIVE.
        await expect(page.locator('[name*="gateway" i]')).toHaveCount(0);
      });

      await testInfo.attach('checkout-gateway-trocado.png', {
        body: await page.screenshot(),
        contentType: 'image/png',
      });
    } finally {
      // Restaura o gateway original pra não deixar o ambiente sujo pros
      // outros specs financeiros (pagamento-sandbox.spec.ts, etc.), mesmo
      // se algum passo acima falhar.
      try {
        await page.goto(appPath('/logout'), { waitUntil: 'domcontentloaded' });
        await page.waitForURL(/login$/i, { timeout: 20000 });
        await loginAs(page, adminEmail, adminPassword);
        await selecionarGatewayViaAdmin(gatewayOriginal);
        const confirmado = gatewayAtivo();
        if (confirmado.payment_gateway_active !== gatewayOriginal) {
          throw new Error(`Gateway restaurado não bateu: esperava ${gatewayOriginal}, banco tem ${confirmado.payment_gateway_active}.`);
        }
        registro.registrar('Restaurar gateway original', true, `Restaurado para ${gatewayOriginal}.`);
      } catch (e) {
        registro.registrar('Restaurar gateway original', false, `Falha ao restaurar: ${(e as Error).message}`);
      }
      await registro.finalizar();
    }
  });
});
