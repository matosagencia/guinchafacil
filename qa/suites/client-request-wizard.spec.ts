import { test, expect } from '@playwright/test';
import { clienteCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate da tela de novo pedido REDESENHADA (mapa estilo Uber + triagem
 * embutida — src/Views/cliente/pedidonovo.php). Atualizado após o redesign
 * (tarefa "mesclar triagem no /cliente/pedido/novo") que substituiu o
 * formulário antigo (select de tipo_problema visível + #badgeOrigem) pelo
 * wizard sintoma -> detalhes -> confirmar, com origem por GPS/busca e
 * veículo/tipo de problema em campos ocultos.
 */
test.describe('Cliente — assistente de novo pedido', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = clienteCreds();
    test.skip(!email || !password, 'Credenciais de teste de cliente não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/cliente/pedido/novo'), { waitUntil: 'domcontentloaded' });
  });

  test('E2E-CLI-WIZ-001 | shell do mapa e mapa carregam', async ({ page }) => {
    await expect(page.locator('#socorroShell')).toBeVisible();
    await expect(page.locator('#map')).toBeVisible();
  });

  test('E2E-CLI-WIZ-002 | triagem inicial e campos de veículo/tipo presentes', async ({ page }) => {
    // Primeiro passo do wizard: triagem "O que aconteceu?".
    await expect(page.locator('[data-step="sintoma"]')).toBeVisible();
    await expect(page.locator('.socorro-title', { hasText: /o que aconteceu/i }).first()).toBeVisible();
    // Veículo e tipo de problema seguem no form como campos que o JS preenche
    // (veículo único = hidden #veiculo_id; múltiplos = select #veiculo_id_select).
    const veiculoCount = await page.locator('#veiculo_id, #veiculo_id_select').count();
    expect(veiculoCount).toBeGreaterThan(0);
    await expect(page.locator('#tipo_problema')).toHaveCount(1);
  });

  test('E2E-CLI-WIZ-003 | botão de confirmar começa desabilitado sem origem/destino', async ({ page }) => {
    await expect(page.locator('#btnSubmit')).toBeDisabled();
  });

  test('E2E-CLI-WIZ-004 | etapa de confirmação começa oculta até avançar na triagem', async ({ page }) => {
    // No wizard novo, mapa/destino/custo/submit ficam na etapa "confirmar",
    // que só aparece depois da triagem — não mais um select pré-selecionado
    // por query string como no formulário antigo.
    await expect(page.locator('[data-step="confirmar"]')).toHaveClass(/d-none/);
    await expect(page.locator('#btnSubmit')).toBeDisabled();
  });
});
