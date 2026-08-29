import { test, expect } from '@playwright/test';
import { adminCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate visual da remodelação do backoffice admin (Pacote L2.3) — Central
 * Operacional (/admin/central): shell de 3 colunas (sidebar reorganizada +
 * fila de pedidos + workspace do item selecionado), seleção sem reload,
 * filtro local da fila, não-regressão do menu antigo (/admin/dashboard), e
 * (Fase 2) painel de detalhe consumindo a API real
 * (src/Api/Admin/OrdersApiController.php): mapa Leaflet, timeline e chat.
 */
test.describe('Admin — Central Operacional', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = adminCreds();
    test.skip(!email || !password, 'Credenciais de teste de admin não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/admin/central'), { waitUntil: 'domcontentloaded' });
  });

  test('E2E-ADM-OPS-001 | shell de 3 colunas renderiza (sidebar + worklist + workspace)', async ({ page }) => {
    await expect(page.locator('#opsSidebar')).toBeVisible();
    await expect(page.locator('#opsWorklistResults')).toBeVisible();
    await expect(page.locator('#opsWorkspace')).toBeVisible();
  });

  test('E2E-ADM-OPS-002 | sidebar reorganizada tem as 6 seções esperadas', async ({ page }) => {
    const sidebar = page.locator('#opsSidebar');
    await expect(sidebar).toContainText('Operação');
    await expect(sidebar).toContainText('Pessoas e Frota');
    await expect(sidebar).toContainText('Serviços');
    await expect(sidebar).toContainText('Financeiro');
    await expect(sidebar).toContainText('Qualidade e Segurança');
    await expect(sidebar).toContainText('Sistema');
  });

  test('E2E-ADM-OPS-003 | resumo operacional mostra 5 métricas', async ({ page }) => {
    await expect(page.locator('.ops-summary .ops-metric')).toHaveCount(5);
  });

  test('E2E-ADM-OPS-004 | selecionar pedido na fila atualiza o workspace sem navegar', async ({ page }) => {
    const items = page.locator('[data-order-id]');
    const total = await items.count();
    test.skip(total < 2, 'Precisa de ao menos 2 pedidos ativos para testar troca de seleção.');

    const urlAntes = page.url();
    const segundoId = await items.nth(1).getAttribute('data-order-id');
    await items.nth(1).click();

    await expect(page.locator('#opsWorkspace h1')).toHaveText(`GF-${segundoId}`);
    expect(page.url()).toBe(urlAntes); // confirma que não houve reload/navegação
    await expect(items.nth(1)).toHaveAttribute('aria-selected', 'true');
  });

  test('E2E-ADM-OPS-005 | filtro local da fila esconde itens que não combinam com a busca', async ({ page }) => {
    const items = page.locator('[data-order-id]');
    const total = await items.count();
    test.skip(total < 1, 'Precisa de ao menos 1 pedido ativo para testar o filtro.');

    await page.fill('#opsWorklistSearch', 'zzz-termo-que-nao-deve-existir-em-nenhum-pedido');
    await expect(items.first()).toBeHidden();

    await page.fill('#opsWorklistSearch', '');
    await expect(items.first()).toBeVisible();
  });

  test('E2E-ADM-OPS-006 | primeiro pedido da fila já vem selecionado ao carregar', async ({ page }) => {
    const items = page.locator('[data-order-id]');
    const total = await items.count();
    test.skip(total < 1, 'Precisa de ao menos 1 pedido ativo.');
    await expect(items.first()).toHaveAttribute('aria-selected', 'true');
    await expect(page.locator('#opsWorkspace .ops-order-header h1')).toBeVisible();
  });

  test('E2E-ADM-OPS-007 | dashboard antigo (/admin/dashboard) continua intacto em paralelo', async ({ page }) => {
    await page.goto(appPath('/admin/dashboard'), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('header.page-head')).toBeVisible();
    await expect(page.locator('.admin-stats .admin-stat')).toHaveCount(5);
  });

  test('E2E-ADM-OPS-008 | painel de detalhe carrega da API real (fatos + mapa + abas)', async ({ page }) => {
    const items = page.locator('[data-order-id]');
    test.skip((await items.count()) < 1, 'Precisa de ao menos 1 pedido ativo.');

    await expect(page.locator('#opsWorkspace .ops-order-facts')).toBeVisible({ timeout: 10000 });
    // Leaflet aplica a classe leaflet-container no próprio elemento alvo
    // (L.map(canvas)), não cria um filho — o seletor certo é o próprio nó.
    await expect(page.locator('#opsMapCanvas.leaflet-container')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('#opsMapCanvas .leaflet-marker-icon').first()).toBeVisible();
    await expect(page.locator('.ops-tabs .ops-tab')).toHaveCount(3);
  });

  test('E2E-ADM-OPS-009 | aba timeline mostra eventos reais ou estado vazio explícito', async ({ page }) => {
    const items = page.locator('[data-order-id]');
    test.skip((await items.count()) < 1, 'Precisa de ao menos 1 pedido ativo.');

    await page.locator('.ops-tab[data-tab="timeline"]').click();
    const timelinePanel = page.locator('[data-panel="timeline"]');
    await expect(timelinePanel).toBeVisible();
    await expect(timelinePanel.locator('#opsTimelineContent')).not.toContainText('Carregando', { timeout: 10000 });
  });

  test('E2E-ADM-OPS-010 | aba chat carrega mensagens reais via API', async ({ page }) => {
    const items = page.locator('[data-order-id]');
    test.skip((await items.count()) < 1, 'Precisa de ao menos 1 pedido ativo.');

    await page.locator('.ops-tab[data-tab="chat"]').click();
    await expect(page.locator('#opsChatMessages')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('#opsChatForm')).toBeVisible();
  });

  test('E2E-ADM-OPS-011 | enviar mensagem de chat pela Central Operacional persiste via API', async ({ page }) => {
    const items = page.locator('[data-order-id]');
    test.skip((await items.count()) < 1, 'Precisa de ao menos 1 pedido ativo.');

    await page.locator('.ops-tab[data-tab="chat"]').click();
    const marker = `QA central-operacional ${Date.now()}`;
    await page.fill('#opsChatInput', marker);
    await page.click('#opsChatForm button[type="submit"]');

    await expect(page.locator('#opsChatMessages')).toContainText(marker, { timeout: 10000 });
  });
});
