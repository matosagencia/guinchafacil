import { test, expect } from '@playwright/test';
import { adminCreds, clienteCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate visual do Nível 2 (plano §12 / §9) — Central de Comunicados:
 * CRUD real no admin (AdminComunicadoController) e exibição real no
 * painel do cliente via ComunicadoService::resolveActiveForProfile.
 */
test.describe('Comunicados', () => {
  test('E2E-COM-001 | admin vê contadores e lista de comunicados', async ({ page }) => {
    const { email, password } = adminCreds();
    test.skip(!email || !password, 'Credenciais de teste de admin não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/admin/comunicados'), { waitUntil: 'domcontentloaded' });

    // §COBERTURA-RAIO-01 (05/08/2026): a tela foi reestruturada pro padrão
    // shell-ops (mesma arquitetura de /admin/central, /admin/documentos,
    // /admin/saques — ver src/Views/admin/comunicados/index.php) e não usa
    // mais o layout antigo main.main-content/.sidebar. Sidebar e workspace
    // agora são #comunicadosSidebar e #comunicadosWorkspace, dentro de
    // .shell-ops#comunicadosShell.
    await expect(page.locator('.shell-ops#comunicadosShell')).toBeVisible();
    const sidebarBox = await page.locator('#comunicadosSidebar').boundingBox();
    const workspaceBox = await page.locator('#comunicadosWorkspace').boundingBox();
    expect(workspaceBox && sidebarBox && workspaceBox.x >= sidebarBox.x + sidebarBox.width - 1).toBeTruthy();
    await expect(page.locator('.ops-metric__label', { hasText: 'Publicados' })).toBeVisible();
  });

  test('E2E-COM-002 | comunicado publicado aparece no painel do cliente', async ({ page }) => {
    const { email, password } = clienteCreds();
    test.skip(!email || !password, 'Credenciais de teste de cliente não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/cliente/dashboard'), { waitUntil: 'domcontentloaded' });

    const carousel = page.locator('.communication-carousel, .communication-card').first();
    test.skip((await carousel.count()) === 0, 'Nenhum comunicado publicado para o perfil cliente no momento do teste.');
    await expect(carousel).toBeVisible();
  });
});
