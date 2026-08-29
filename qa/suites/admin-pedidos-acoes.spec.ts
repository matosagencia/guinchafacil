import { expect, test } from '@playwright/test';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAdmin, seedCancelamento } from '../helpers/seed';

test.describe('Admin — ações no workspace de pedidos', () => {
  test('exibe ações administrativas e abre o cancelamento existente', async ({ page }) => {
    const admin = seedAdmin();
    const seed = seedCancelamento();
    const pedidoId = seed.pedido_antes_aceite_id;

    await loginAs(page, admin.admin_email, admin.admin_password);
    await page.goto(appPath(`/admin/pedidos?busca=${pedidoId}`), { waitUntil: 'domcontentloaded' });

    const workspace = page.locator('#pedWorkspace');
    await expect(workspace.locator(`[data-admin-action="status"]`)).toBeVisible();
    await expect(workspace.locator(`[data-admin-action="assign"]`)).toBeVisible();
    await expect(workspace.locator(`[data-admin-action="cancel"]`)).toBeVisible();
    await expect(workspace.locator(`[data-admin-action="manage"]`)).toBeVisible();

    await workspace.locator(`[data-admin-action="cancel"]`).click();
    await expect(page).toHaveURL(new RegExp(`/admin/pedido/${pedidoId}\\?acao=cancelar`));
    await expect(page.locator('#modalCancelar')).toBeVisible();
    await expect(page.locator('#modalCancelar textarea[name="justificativa"]')).toBeVisible();
    await expect(page.locator('#modalCancelar input[name="senha"]')).toBeVisible();
  });
});
