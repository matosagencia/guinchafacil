import { test, expect } from '@playwright/test';
import { adminCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Tela de Despacho manual (/admin/despacho) — item que estava marcado
 * "em breve" na sidebar reorganizada da Central Operacional. Sem schema
 * novo: lista pedidos aguardando_guincho e prestadores aprovados/disponíveis
 * (Guincho::listarAprovados) ordenados por distância real (Haversine contra
 * a origem do pedido selecionado). A atribuição reaproveita 100% a action
 * já existente (AdminController::pedidoAtribuir).
 */
test.describe('Admin — Despacho', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = adminCreds();
    test.skip(!email || !password, 'Credenciais de teste de admin não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/admin/despacho'), { waitUntil: 'domcontentloaded' });
  });

  test('E2E-ADM-DESP-001 | página carrega com fila e painel de prestadores', async ({ page }) => {
    await expect(page.locator('header.page-head')).toBeVisible();
    await expect(page.getByText('Fila (aguardando prestador)')).toBeVisible();
  });

  test('E2E-ADM-DESP-002 | fila vazia ou painel de detalhe/prestadores mostram estado explícito', async ({ page }) => {
    const filaVazia = page.getByText('Nenhum pedido aguardando prestador no momento.');
    const semSelecao = page.getByText('Selecione um pedido na fila para ver os prestadores disponíveis.');
    const tabelaPrestadores = page.locator('table tbody tr').first();
    // Um dos três estados precisa estar visível: fila vazia, nada selecionado,
    // ou a tabela de prestadores (com dados ou com o "Nenhum prestador...").
    await expect(async () => {
      const algum =
        (await filaVazia.isVisible().catch(() => false)) ||
        (await semSelecao.isVisible().catch(() => false)) ||
        (await tabelaPrestadores.isVisible().catch(() => false));
      expect(algum).toBe(true);
    }).toPass();
  });

  test('E2E-ADM-DESP-003 | selecionar pedido na fila atualiza o painel (via querystring)', async ({ page }) => {
    const primeiroItem = page.locator('.list-group-item-action').first();
    const count = await primeiroItem.count();
    test.skip(count === 0, 'Nenhum pedido na fila para testar seleção.');
    await primeiroItem.click();
    await expect(page).toHaveURL(/pedido_id=\d+/);
    await expect(page.getByText(/Pedido #\d+/)).toBeVisible();
  });

  test('E2E-ADM-DESP-004 | sidebar reorganizada da Central Operacional linka para /admin/despacho (não é mais "em breve")', async ({ page }) => {
    await page.goto(appPath('/admin/central'), { waitUntil: 'domcontentloaded' });
    const link = page.locator('#opsSidebar a:has-text("Despacho")');
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/\/admin\/despacho/);
  });

  test('E2E-ADM-DESP-005 | link "Central Operacional" navega de volta', async ({ page }) => {
    await page.click('a:has-text("Central Operacional")');
    await expect(page).toHaveURL(/\/admin\/central/);
  });
});
