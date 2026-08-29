import { test, expect } from '@playwright/test';
import { adminCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * ROADMAP socorro automotivo — Etapas 15/16/8/9.
 * Gate das telas admin novas (catálogo unificado, compatibilidade veicular,
 * produtos/estoque, checklists incompletos) e da proteção do reboque como
 * serviço de sistema. Não fabrica pedidos: valida que as telas de gestão
 * carregam e expõem os controles esperados. Pula se não houver credencial
 * de admin de teste configurada.
 */
test.describe('Admin — catálogo, compatibilidade e estoque', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = adminCreds();
    test.skip(!email || !password, 'Credenciais de admin de teste não configuradas.');
    await loginAs(page, email, password);
  });

  test('E2E-CMP-ADM-001 | /admin/servicos redireciona para o catálogo estruturado', async ({ page }) => {
    await page.goto(appPath('/admin/servicos'), { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/catalogo-servicos\/tipos$/);
    await expect(page.locator('body')).toContainText(/Tipos de serviço/i);
  });

  test('E2E-CMP-ADM-002 | reboque aparece como serviço de sistema (protegido)', async ({ page }) => {
    await page.goto(appPath('/admin/catalogo-servicos/tipos'), { waitUntil: 'domcontentloaded' });
    // Ao menos um serviço TOWING deve exibir o selo "Sistema".
    await expect(page.locator('.badge', { hasText: /Sistema/i }).first()).toBeVisible();
  });

  test('E2E-CMP-ADM-003 | tela de compatibilidade carrega o seletor de serviço', async ({ page }) => {
    await page.goto(appPath('/admin/catalogo-servicos/compatibilidade'), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText(/Compatibilidade prestador/i);
    await expect(page.locator('select[name="service_type_id"]')).toBeVisible();
  });

  test('E2E-CMP-ADM-004 | editar tipo de serviço renderiza o formulário (regressão do card vazio)', async ({ page }) => {
    await page.goto(appPath('/admin/catalogo-servicos/tipos'), { waitUntil: 'domcontentloaded' });
    const editar = page.locator('a[href*="/admin/catalogo-servicos/tipo/novo?id="]').first();
    test.skip((await editar.count()) === 0, 'Nenhum tipo de serviço para editar.');
    await editar.click();
    // O formulário não pode vir vazio: os campos estruturais devem existir.
    await expect(page.locator('select[name="category_id"]')).toBeVisible();
    await expect(page.locator('input[name="name"]')).toBeVisible();
    await expect(page.locator('select[name="attendance_mode"]')).toBeVisible();
  });

  test('E2E-CMP-ADM-005 | catálogo de produtos carrega', async ({ page }) => {
    await page.goto(appPath('/admin/produtos'), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText(/Produtos/i);
    await expect(page.locator('a[href*="/admin/produto/novo"]').first()).toBeVisible();
  });

  test('E2E-CMP-ADM-006 | fila de checklists incompletos carrega', async ({ page }) => {
    await page.goto(appPath('/admin/checklists-incompletos'), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText(/Checklists incompletos/i);
  });
});
