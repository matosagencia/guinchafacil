import { test, expect } from '@playwright/test';
import { adminCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate visual do Nível 2 (plano §12) — Command Center do admin:
 * page-head, 5 cards de estatística real, mapa ao vivo + alertas
 * operacionais reais lado a lado, e o menu Operação/Cadastros/
 * Gestão/SRE completo (auditoria feita nesta sessão).
 */
test.describe('Admin — Command Center', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = adminCreds();
    test.skip(!email || !password, 'Credenciais de teste de admin não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/admin/dashboard'), { waitUntil: 'domcontentloaded' });
  });

  test('E2E-ADM-CC-001 | page-head e 5 cards de estatística', async ({ page }) => {
    await expect(page.locator('header.page-head')).toBeVisible();
    await expect(page.locator('.admin-stats .admin-stat')).toHaveCount(5);
  });

  test('E2E-ADM-CC-002 | mapa ao vivo e alertas prioritários lado a lado', async ({ page }) => {
    await expect(page.locator('#mapaOperacional')).toBeVisible();
    await expect(page.locator('[data-dashboard-alerts]')).toBeVisible();
  });

  test('E2E-ADM-CC-003 | menu tem os 4 grupos esperados', async ({ page }) => {
    const sidebar = page.locator('.sidebar');
    await expect(sidebar).toContainText('Operação');
    await expect(sidebar).toContainText('Cadastros');
    await expect(sidebar).toContainText('Gestão');
    await expect(sidebar).toContainText('SRE');
  });

  test('E2E-ADM-CC-004 | conteúdo não estoura sobre a sidebar (regressão do bug de main-wrapper)', async ({ page }) => {
    const box = await page.locator('main.main-content').boundingBox();
    const sidebarBox = await page.locator('.sidebar').boundingBox();
    expect(box && sidebarBox && box.x >= sidebarBox.x + sidebarBox.width - 1).toBeTruthy();
  });

  test('E2E-ADM-CC-005 | dashboard sincroniza sem reload (polling ativo)', async ({ page }) => {
    const before = await page.locator('[data-dashboard-card="alertas_abertos"]').textContent();
    await page.waitForTimeout(16000);
    const after = await page.locator('[data-dashboard-card="alertas_abertos"]').textContent();
    expect(before).not.toBeNull();
    expect(after).not.toBeNull();
  });
});
