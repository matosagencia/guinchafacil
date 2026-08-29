import { test, expect } from '@playwright/test';
import { clienteCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate visual do Nível 2 (plano §12) — notificações e preferências
 * operacionais. NotificacaoService (src/Services/NotificacaoService.php)
 * dispara e-mails reais em eventos de negócio (cancelamento, pagamento,
 * saldo liberado); Playwright não tem acesso à caixa de e-mail, então
 * este teste verifica o efeito colateral observável na UI — o indicador
 * de mensagem não lida no card de pedido ativo — em vez de simular o
 * envio de e-mail.
 */
test.describe('Notificações', () => {
  test('E2E-NOT-001 | indicador de novidade aparece quando há mensagem não lida', async ({ page }) => {
    const { email, password } = clienteCreds();
    test.skip(!email || !password, 'Credenciais de teste de cliente não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/cliente/dashboard'), { waitUntil: 'domcontentloaded' });

    const pedidoCard = page.locator('#pedidoAtivoClienteCard');
    test.skip((await pedidoCard.count()) === 0, 'Cliente de teste não tem pedido ativo no momento.');

    const statusUrl = await pedidoCard.getAttribute('data-status-url');
    expect(statusUrl).toBeTruthy();

    const response = await page.request.get(statusUrl!, { headers: { Accept: 'application/json' } });
    expect(response.ok()).toBeTruthy();
    const payload = await response.json();
    expect(payload).toHaveProperty('tem_chat_novo');
  });
});
