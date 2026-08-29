import { test, expect } from '@playwright/test';
import { adminCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Filtro "Especialistas" (/admin/guinchos?tipo=especialista) — item que
 * estava marcado "em breve" na sidebar reorganizada. Não é entidade nova:
 * reaproveita guinchos.oferece_reboque (migration_prestador_tipo_v1.sql já
 * existente) para separar guincheiros (reboque) de especialistas
 * (chaveiro/elétrica/pneu, sem reboque) na mesma tela /admin/guinchos.
 */
test.describe('Admin — Especialistas (filtro de prestadores)', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = adminCreds();
    test.skip(!email || !password, 'Credenciais de teste de admin não configuradas.');
    await loginAs(page, email, password);
  });

  test('E2E-ADM-ESPEC-001 | tela de guinchos mostra os 3 chips de filtro', async ({ page }) => {
    await page.goto(appPath('/admin/guinchos'), { waitUntil: 'domcontentloaded' });
    await expect(page.getByText('Todos', { exact: true })).toBeVisible();
    await expect(page.getByText('Guincheiros (reboque)')).toBeVisible();
    await expect(page.getByText('Especialistas', { exact: true })).toBeVisible();
  });

  test('E2E-ADM-ESPEC-002 | filtro ?tipo=especialista muda o título e não quebra a página', async ({ page }) => {
    await page.goto(appPath('/admin/guinchos?tipo=especialista'), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('h1')).toContainText('Especialistas');
  });

  test('E2E-ADM-ESPEC-003 | sidebar reorganizada da Central Operacional linka para o filtro (não é mais "em breve")', async ({ page }) => {
    await page.goto(appPath('/admin/central'), { waitUntil: 'domcontentloaded' });
    const link = page.locator('#opsSidebar a:has-text("Especialistas")');
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/tipo=especialista/);
  });
});
