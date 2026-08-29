import { test, expect } from '@playwright/test';
import { adminCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Carteiras/Saques (Pacote L2.3) — itens que estavam "em breve". Decisão de
 * produto confirmada: painel de VISIBILIDADE sobre o repasse Pix já
 * automático (CarteiraService), sem saldo retido nem solicitação manual de
 * saque. Toda falha de query deve aparecer como alerta explícito na tela
 * (nunca como "R$ 0,00" silencioso) — os testes abaixo cobrem isso também.
 */
test.describe('Admin — Carteiras e Saques', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = adminCreds();
    test.skip(!email || !password, 'Credenciais de teste de admin não configuradas.');
    await loginAs(page, email, password);
  });

  test('E2E-ADM-CART-001 | /admin/carteiras carrega com os 4 cards de resumo', async ({ page }) => {
    await page.goto(appPath('/admin/carteiras'), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('header.page-head')).toBeVisible();
    await expect(page.getByText('Em compensação')).toBeVisible();
    await expect(page.getByText('Pago (repasse Pix confirmado)')).toBeVisible();
    await expect(page.getByText('Repasses com falha')).toBeVisible();
  });

  test('E2E-ADM-CART-002 | tabela lista guincheiros ou estado vazio explícito (nunca uma falha silenciosa)', async ({ page }) => {
    await page.goto(appPath('/admin/carteiras'), { waitUntil: 'domcontentloaded' });
    const rows = page.locator('table tbody tr').first();
    await expect(rows).toBeVisible();
    // Se a query tivesse falhado, o alerta vermelho apareceria — não deve
    // aparecer numa carga normal.
    await expect(page.getByText('Falha ao calcular o resumo de carteiras')).toHaveCount(0);
  });

  test('E2E-ADM-CART-003 | "Ver extrato" abre o detalhe do guincheiro com o extrato linha a linha', async ({ page }) => {
    await page.goto(appPath('/admin/carteiras'), { waitUntil: 'domcontentloaded' });
    const link = page.locator('a:has-text("Ver extrato")').first();
    const count = await link.count();
    test.skip(count === 0, 'Nenhum guincheiro com movimentação para abrir o extrato.');
    await link.click();
    await expect(page).toHaveURL(/\/admin\/carteira\/\d+/);
    await expect(page.getByText('Extrato (linha a linha')).toBeVisible();
  });

  test('E2E-ADM-CART-004 | /admin/saques carrega com aviso de que o repasse já é automático', async ({ page }) => {
    await page.goto(appPath('/admin/saques'), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('header.page-head')).toBeVisible();
    await expect(page.getByText('repasse ao guincheiro é automático')).toBeVisible();
  });

  test('E2E-ADM-CART-005 | sidebar reorganizada da Central Operacional linka Carteiras e Saques (não são mais "em breve")', async ({ page }) => {
    await page.goto(appPath('/admin/central'), { waitUntil: 'domcontentloaded' });
    const carteiras = page.locator('#opsSidebar a:has-text("Carteiras")');
    const saques = page.locator('#opsSidebar a:has-text("Saques")');
    await expect(carteiras).toBeVisible();
    await expect(saques).toBeVisible();
    await carteiras.click();
    await expect(page).toHaveURL(/\/admin\/carteiras/);
  });
});
