import { test, expect } from '@playwright/test';
import { guinchoCreds } from '../fixtures/test-data.fixture';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

function uploadConfig() {
  return {
    atendimentoPedidoId: process.env.TEST_ATENDIMENTO_PEDIDO_ID || process.env.TEST_PEDIDO_ATENDIMENTO_ID || ''
  };
}

test.describe('upload e segurança', () => {
  test('E2E-UPL-001 | cadastro de guincheiro restringe tipos aceitos nos uploads', async ({ page }) => {
    await page.goto(appPath('/registro/guincho'));

    const cnhFrente = page.locator('input[name="doc_cnh_frente"]');
    const cnhVerso = page.locator('input[name="doc_cnh_verso"]');
    const fotoVeiculo = page.locator('input[name="foto_veiculo"]');

    await expect(cnhFrente).toHaveAttribute('accept', /image\/\*,\.pdf/);
    await expect(cnhVerso).toHaveAttribute('accept', /image\/\*,\.pdf/);
    await expect(fotoVeiculo).toHaveAttribute('accept', /image\/\*/);
  });

  test('E2E-UPL-002 | atendimento restringe evidências a imagens quando a fase exige upload', async ({ page }) => {
    const { email, password } = guinchoCreds();
    const { atendimentoPedidoId } = uploadConfig();
    test.skip(!email || !password, 'Credenciais de guincho não configuradas.');
    test.skip(!atendimentoPedidoId, 'Defina TEST_ATENDIMENTO_PEDIDO_ID para validar upload operacional.');

    await loginAs(page, email, password);
    await expectLoggedIn(page);
    await page.goto(appPath(`/guincho/atendimento/${atendimentoPedidoId}`));

    const statusForm = page.locator('#statusForm');
    await expect(statusForm).toBeVisible();
    await expect(statusForm.locator('input[name="evidence_token"]')).toHaveCount(1);

    const fileInputs = statusForm.locator('input[type="file"]');
    const fileCount = await fileInputs.count();
    test.skip(fileCount === 0, 'Pedido informado não está em fase de evidência obrigatória.');

    for (let index = 0; index < fileCount; index += 1) {
      const input = fileInputs.nth(index);
      await expect(input).toHaveAttribute('accept', /image\/\*/);
      await expect(input).toHaveAttribute('required', '');
    }
  });
});
