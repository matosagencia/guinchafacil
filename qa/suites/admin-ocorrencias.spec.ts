import { test, expect } from '@playwright/test';
import { adminCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Tela dedicada de Ocorrências (/admin/ocorrencias) — item que estava
 * marcado "em breve" na sidebar reorganizada da Central Operacional.
 * Requer a migração install/migration_ocorrencias_v1.sql aplicada
 * (tabela pedido_ocorrencias) antes de rodar contra o servidor real.
 */
test.describe('Admin — Ocorrências', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = adminCreds();
    test.skip(!email || !password, 'Credenciais de teste de admin não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/admin/ocorrencias'), { waitUntil: 'domcontentloaded' });
  });

  test('E2E-ADM-OCOR-001 | página carrega com os 4 cards de status', async ({ page }) => {
    await expect(page.locator('header.page-head')).toBeVisible();
    await expect(page.getByText('Aberta')).toBeVisible();
    await expect(page.getByText('Em análise')).toBeVisible();
    await expect(page.getByText('Resolvida')).toBeVisible();
    await expect(page.getByText('Arquivada')).toBeVisible();
  });

  test('E2E-ADM-OCOR-002 | tabela lista ocorrências ou estado vazio explícito', async ({ page }) => {
    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('E2E-ADM-OCOR-003 | registrar nova ocorrência via modal persiste e aparece na lista', async ({ page }) => {
    // Precisa de um pedido real existente pra vincular a ocorrência — usa o
    // primeiro pedido citado na lista de pedidos (/admin/pedidos), se houver.
    await page.goto(appPath('/admin/pedidos'), { waitUntil: 'domcontentloaded' });
    const primeiroLink = page.locator('a[href*="/admin/pedido/"]').first();
    const count = await primeiroLink.count();
    test.skip(count === 0, 'Nenhum pedido existente para vincular a ocorrência de teste.');
    const href = await primeiroLink.getAttribute('href');
    const match = href?.match(/\/admin\/pedido\/(\d+)/);
    test.skip(!match, 'Não foi possível extrair o ID do pedido.');
    const pedidoId = match![1];

    await page.goto(appPath('/admin/ocorrencias'), { waitUntil: 'domcontentloaded' });
    await page.click('button:has-text("Registrar ocorrência")');
    await page.fill('#modalNovaOcorrencia input[name="pedido_id"]', pedidoId);
    await page.selectOption('#modalNovaOcorrencia select[name="tipo"]', 'atraso');
    await page.selectOption('#modalNovaOcorrencia select[name="severidade"]', 'alta');
    const descricao = `Ocorrência de teste E2E ${Date.now()}`;
    await page.fill('#modalNovaOcorrencia textarea[name="descricao"]', descricao);
    await page.click('#modalNovaOcorrencia button[type="submit"]');
    await expect(page).toHaveURL(/\/admin\/ocorrencias/);
    await expect(page.getByText(descricao)).toBeVisible();
  });

  test('E2E-ADM-OCOR-004 | filtro por status funciona via querystring', async ({ page }) => {
    await page.goto(appPath('/admin/ocorrencias?status=aberta'), { waitUntil: 'domcontentloaded' });
    await expect(page.getByText('Limpar filtro')).toBeVisible();
  });

  test('E2E-ADM-OCOR-005 | sidebar reorganizada da Central Operacional linka para /admin/ocorrencias (não é mais "em breve")', async ({ page }) => {
    await page.goto(appPath('/admin/central'), { waitUntil: 'domcontentloaded' });
    const link = page.locator('#opsSidebar a:has-text("Ocorrências")');
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/\/admin\/ocorrencias/);
  });
});
