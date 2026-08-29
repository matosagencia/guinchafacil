import { test, expect } from '@playwright/test';
import { buildClienteBatch, isValidCpf } from '../helpers/account-factories';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

test.describe.serial('cadastro cliente em lote', () => {
  test.setTimeout(20 * 60 * 1000);

  test('E2E-REG-CLI-001 | cria 15 contas de cliente com CPF válido, perfil, veículo e oficina', async ({ page }, testInfo) => {
    const runTag = process.env.QA_BATCH_RUN_TAG || String(Date.now());
    const accounts = buildClienteBatch(15, runTag);
    const created: Array<Record<string, string>> = [];

    for (const account of accounts) {
      expect(isValidCpf(account.cpfDigits)).toBeTruthy();

      await page.goto(appPath('/registro/cliente'), { waitUntil: 'domcontentloaded' });
      await page.locator('input[name="nome"]').fill(account.nome);
      await page.locator('input[name="email"]').fill(account.email);
      await page.locator('input[name="telefone"]').fill(account.telefone);
      await page.locator('input[name="cpf"]').fill(account.cpfFormatted);
      await page.locator('input[name="senha"]').fill(account.password);
      await page.locator('input[name="confirmar_senha"]').fill(account.password);
      await page.locator('input[name="cep"]').fill(account.cep);
      await page.locator('input[name="logradouro"]').fill(account.logradouro);
      await page.locator('input[name="numero"]').fill(account.numero);
      await page.locator('input[name="complemento"]').fill(account.complemento);
      await page.locator('input[name="bairro"]').fill(account.bairro);
      await page.locator('input[name="cidade"]').fill(account.cidade);
      await page.locator('select[name="estado"]').selectOption(account.estado);

      await Promise.all([
        page.waitForURL(/\/login$/i, { timeout: 30000 }),
        page.locator('#formCliente button[type="submit"]').click(),
      ]);
      await expect(page).toHaveURL(/\/login$/i);
      await expect(page.locator('form[action$="/login"]')).toBeVisible();

      await loginAs(page, account.email, account.password);
      await expectLoggedIn(page);

      await page.goto(appPath('/cliente/perfil'), { waitUntil: 'domcontentloaded' });
      await expect(page.locator('body')).toContainText(account.nome);
      await Promise.all([
        page.waitForURL(/\/cliente\/perfil$/i, { timeout: 20000 }),
        page.locator('button[type="submit"]:has-text("Salvar Alterações")').click(),
      ]);
      await expect(page.locator('body')).toContainText(/perfil atualizado com sucesso/i);

      await page.goto(appPath('/cliente/veiculo/novo'), { waitUntil: 'domcontentloaded' });
      // O input é um radio Bootstrap "btn-check" (visualmente oculto); quem
      // recebe o clique de verdade é o <label for="tipo_..."> estilizado
      // como botão, então .check() no input trava tentando clicar em algo
      // que o label sempre intercepta. Clicamos no label em vez do input.
      await page.locator(`label[for="tipo_${account.veiculoTipo}"]`).click();
      await expect(page.locator(`input[name="tipo"][value="${account.veiculoTipo}"]`)).toBeChecked();
      await page.locator('input[name="marca"]').fill(account.veiculoMarca);
      await page.locator('input[name="modelo"]').fill(account.veiculoModelo);
      await page.locator('input[name="ano"]').fill(account.veiculoAno);
      await page.locator('input[name="cor"]').fill(account.veiculoCor);
      await page.locator('input[name="placa"]').fill(account.veiculoPlaca);
      await Promise.all([
        page.waitForURL(/\/cliente\/veiculos\?salvo=1/i, { timeout: 30000 }),
        page.locator('button[type="submit"]').click(),
      ]);
      await expect(page.locator('body')).toContainText(account.veiculoPlaca);

      await page.goto(appPath('/cliente/oficina/nova'), { waitUntil: 'domcontentloaded' });
      await page.locator('input[name="nome"]').fill(account.oficinaNome);
      await page.locator('input[name="endereco"]').fill(account.oficinaEndereco);
      await page.locator('input[name="telefone"]').fill(account.oficinaTelefone);
      await page.locator('#latInput').evaluate((el: HTMLInputElement) => { el.value = '-22.896330'; });
      await page.locator('#lngInput').evaluate((el: HTMLInputElement) => { el.value = '-43.198420'; });
      await Promise.all([
        page.waitForURL(/\/cliente\/oficinas\?salvo=1/i, { timeout: 30000 }),
        page.locator('button[type="submit"]').click(),
      ]);
      await expect(page.locator('body')).toContainText(account.oficinaNome);

      created.push({
        nome: account.nome,
        email: account.email,
        cpf: account.cpfFormatted,
        placa_veiculo: account.veiculoPlaca,
        oficina: account.oficinaNome,
      });

      await page.goto(appPath('/logout'), { waitUntil: 'domcontentloaded' });
      await page.waitForURL(/\/login$/i, { timeout: 20000 });
    }

    await testInfo.attach('cadastro-cliente-bulk.json', {
      body: JSON.stringify({ runTag, total: created.length, contas: created }, null, 2),
      contentType: 'application/json',
    });
  });
});
