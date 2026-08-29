import { test, expect } from '@playwright/test';
import { adminCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Tela dedicada de Alertas Operacionais (/admin/alertas) — item que estava
 * marcado "em breve" na sidebar reorganizada da Central Operacional.
 * Reaproveita AdminAlertService (mesmo motor do widget do Command Center),
 * agora sem o corte de 8 itens, com filtro por nível e link pro pedido.
 */
test.describe('Admin — Alertas Operacionais', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = adminCreds();
    test.skip(!email || !password, 'Credenciais de teste de admin não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/admin/alertas'), { waitUntil: 'domcontentloaded' });
  });

  test('E2E-ADM-ALERT-001 | página carrega com os 3 cards de nível', async ({ page }) => {
    await expect(page.locator('header.page-head')).toBeVisible();
    await expect(page.getByText('Críticos')).toBeVisible();
    await expect(page.getByText('Atenção')).toBeVisible();
    await expect(page.getByText('Informativos')).toBeVisible();
  });

  test('E2E-ADM-ALERT-002 | tabela lista alertas ou estado vazio explícito', async ({ page }) => {
    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('E2E-ADM-ALERT-003 | filtro por nível crítico funciona via querystring', async ({ page }) => {
    await page.goto(appPath('/admin/alertas?nivel=erro'), { waitUntil: 'domcontentloaded' });
    const badges = page.locator('table tbody tr td .badge');
    const total = await badges.count();
    for (let i = 0; i < total; i++) {
      await expect(badges.nth(i)).toHaveText('Crítico');
    }
  });

  test('E2E-ADM-ALERT-004 | link "Central Operacional" navega para /admin/central', async ({ page }) => {
    await page.click('a:has-text("Central Operacional")');
    await expect(page).toHaveURL(/\/admin\/central/);
  });

  test('E2E-ADM-ALERT-005 | sidebar reorganizada da Central Operacional linka para /admin/alertas (não é mais "em breve")', async ({ page }) => {
    await page.goto(appPath('/admin/central'), { waitUntil: 'domcontentloaded' });
    const link = page.locator('#opsSidebar a:has-text("Alertas Operacionais")');
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/\/admin\/alertas/);
  });
});
