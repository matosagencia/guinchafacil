// File: guinchafacil/qa/helpers/onboarding.ts
//
// Extrai o cadastro completo (cliente e guincho) que hoje vive espalhado
// dentro de cadastro-cliente-bulk.spec.ts e cadastro-guincho-bulk.spec.ts,
// para reuso pelos NOVOS cenários de stress (onboarding-stress.spec.ts).
// Os dois specs originais NÃO são alterados nem substituídos — continuam
// sendo os testes de regressão específicos de cadastro em lote; este
// arquivo só evita duplicar a mesma sequência de seletores/URLs em cada
// spec novo que precise "só" criar uma conta funcional pra usar em outro
// fluxo (ex.: stress de concorrência, onboarding em massa).
//
// Todos os seletores/URLs abaixo foram conferidos direto nos dois specs
// reais (não inventados) — inclusive as pegadinhas já documentadas lá:
//   - [data-go-step="2"] existe duas vezes no DOM (Continuar do step1 e
//     Voltar do step3); usar `:visible` evita strict-mode violation.
//   - botões de /guincho/operacao e /guincho/bancario não têm
//     type="submit" explícito — localizar por texto.
//   - o radio de tipo de veículo é um btn-check do Bootstrap; quem recebe
//     o clique é o <label for="tipo_...">, não o input.

import { type Page, expect } from '@playwright/test';
import type { ClienteBatchAccount, GuinchoBatchAccount } from './account-factories';
import { expectLoggedIn, loginAs } from './auth';
import { appPath } from './paths';

export async function cadastrarCliente(page: Page, account: ClienteBatchAccount): Promise<void> {
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
    page.waitForURL(/\/login$/i, { timeout: 30_000 }),
    page.locator('#formCliente button[type="submit"]').click(),
  ]);
  await expect(page).toHaveURL(/\/login$/i);

  await loginAs(page, account.email, account.password);
  await expectLoggedIn(page);
}

export async function cadastrarVeiculo(page: Page, account: ClienteBatchAccount): Promise<void> {
  await page.goto(appPath('/cliente/veiculo/novo'), { waitUntil: 'domcontentloaded' });
  await page.locator(`label[for="tipo_${account.veiculoTipo}"]`).click();
  await expect(page.locator(`input[name="tipo"][value="${account.veiculoTipo}"]`)).toBeChecked();
  await page.locator('input[name="marca"]').fill(account.veiculoMarca);
  await page.locator('input[name="modelo"]').fill(account.veiculoModelo);
  await page.locator('input[name="ano"]').fill(account.veiculoAno);
  await page.locator('input[name="cor"]').fill(account.veiculoCor);
  await page.locator('input[name="placa"]').fill(account.veiculoPlaca);
  await Promise.all([
    page.waitForURL(/\/cliente\/veiculos\?salvo=1/i, { timeout: 30_000 }),
    page.locator('button[type="submit"]').click(),
  ]);
  await expect(page.locator('body')).toContainText(account.veiculoPlaca);
}

export async function cadastrarOficina(page: Page, account: ClienteBatchAccount): Promise<void> {
  await page.goto(appPath('/cliente/oficina/nova'), { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="nome"]').fill(account.oficinaNome);
  await page.locator('input[name="endereco"]').fill(account.oficinaEndereco);
  await page.locator('input[name="telefone"]').fill(account.oficinaTelefone);
  await page.locator('#latInput').evaluate((el: HTMLInputElement) => { el.value = '-22.896330'; });
  await page.locator('#lngInput').evaluate((el: HTMLInputElement) => { el.value = '-43.198420'; });
  await Promise.all([
    page.waitForURL(/\/cliente\/oficinas\?salvo=1/i, { timeout: 30_000 }),
    page.locator('button[type="submit"]').click(),
  ]);
  await expect(page.locator('body')).toContainText(account.oficinaNome);
}

/**
 * Cadastro completo (3 etapas + upload de 3 documentos) — não inclui
 * perfil/operação/bancário, que são telas separadas depois do login (ver
 * completarPerfilGuincho / configurarOperacaoGuincho / cadastrarDadosBancarios).
 */
export async function cadastrarGuincho(page: Page, account: GuinchoBatchAccount, uploadImage: string): Promise<void> {
  await page.goto(appPath('/registro/guincho'), { waitUntil: 'domcontentloaded' });
  await page.locator('#f_nome').fill(account.nome);
  await page.locator('#f_email').fill(account.email);
  await page.locator('#f_tel').fill(account.telefone);
  await page.locator('#f_cpf').fill(account.cpfFormatted);
  await page.locator('input[name="nascimento"]').fill(account.nascimento);
  await page.locator('#f_senha').fill(account.password);
  await page.locator('#f_conf').fill(account.password);
  await page.locator('#g_cep').fill(account.cep);
  await page.locator('#g_logradouro').fill(account.logradouro);
  await page.locator('input[name="numero"]').fill(account.numero);
  await page.locator('input[name="complemento"]').fill(account.complemento);
  await page.locator('#g_bairro').fill(account.bairro);
  await page.locator('#g_cidade').fill(account.cidade);
  await page.locator('#g_estado').selectOption(account.estado);
  // validateStep1() (auth-registro-guincho.js) exige ao menos um
  // .servico-chk marcado antes de liberar o passo 2 — sem isso ela dispara
  // window.alert(...) e o step2 (onde fica #f_placa) nunca fica visível.
  // Este helper preenche placa/CNH no passo 2, ou seja, assume reboque
  // habilitado; o checkbox de reboque é o único com id fixo (#chkReboque).
  await page.locator('#chkReboque').check();
  await page.locator('[data-go-step="2"]:visible').click();

  await page.locator('#f_placa').waitFor({ state: 'visible', timeout: 5_000 });
  await page.locator('#f_placa').fill(account.placaGuincho);
  await page.locator('input[name="capacidade_ton"]').fill(account.capacidadeTon);
  await page.locator('#raioRange').fill(account.raioKm);
  await page.locator('#f_cnh').fill(account.cnhNumero);
  await page.locator('input[name="cnh_validade"]').fill(account.cnhValidade);
  await page.locator('[data-go-step="3"]').click();

  await page.locator('input[name="doc_cnh_frente"]').setInputFiles(uploadImage);
  await page.locator('input[name="doc_cnh_verso"]').setInputFiles(uploadImage);
  await page.locator('input[name="foto_veiculo"]').setInputFiles(uploadImage);
  await page.locator('select[name="chave_pix_tipo"]').selectOption(account.pixTipo);
  await page.locator('input[name="chave_pix"]').fill(account.pixChave);

  await Promise.all([
    page.waitForURL(/\/login$/i, { timeout: 90_000 }),
    page.locator('#btnFinal').click(),
  ]);
  await expect(page).toHaveURL(/\/login$/i);

  await loginAs(page, account.email, account.password);
  await expectLoggedIn(page);
}

export async function completarPerfilGuincho(page: Page, account: GuinchoBatchAccount, uploadImage: string): Promise<void> {
  await page.goto(appPath('/guincho/perfil'), { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).toContainText(account.nome);
  await page.locator('input[name="telefone"]').fill(account.telefone);
  await page.locator('input[name="placa_guincho"]').fill(account.placaGuincho);
  await page.locator('input[name="capacidade_ton"]').fill(account.capacidadeTon);
  await page.locator('input[name="chave_pix"]').fill(account.pixChave);
  await page.locator('input[name="foto_caminhao"]').setInputFiles(uploadImage);
  await page.locator('#lat_operacao').evaluate((el: HTMLInputElement) => { el.value = '-22.895770'; });
  await page.locator('#lng_operacao').evaluate((el: HTMLInputElement) => { el.value = '-43.191860'; });
  await Promise.all([
    page.waitForURL(/\/guincho\/perfil$/i, { timeout: 30_000 }),
    page.locator('button[type="submit"]:has-text("Salvar Alterações")').click(),
  ]);
  await expect(page.locator('body')).toContainText(/perfil atualizado com sucesso/i);
}

export async function configurarOperacaoGuincho(page: Page, account: GuinchoBatchAccount): Promise<void> {
  await page.goto(appPath('/guincho/operacao'), { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="placa_guincho"]').fill(account.placaGuincho);
  await page.locator('input[name="capacidade_ton"]').fill(account.capacidadeTon);
  await page.locator('#raioRange').fill(account.raioKm);
  await page.locator('input[name="cnh_numero"]').fill(account.cnhNumero);
  await page.locator('input[name="cnh_validade"]').fill(account.cnhValidade);
  await Promise.all([
    page.waitForURL(/\/guincho\/operacao$/i, { timeout: 30_000 }),
    page.locator('button:has-text("Salvar dados do guincho")').click(),
  ]);
  await expect(page.locator('body')).toContainText(/dados operacionais atualizados com sucesso/i);
}

export async function cadastrarDadosBancarios(page: Page, account: GuinchoBatchAccount): Promise<void> {
  await page.goto(appPath('/guincho/bancario'), { waitUntil: 'domcontentloaded' });
  await page.locator('select[name="chave_pix_tipo"]').selectOption(account.pixTipo);
  await page.locator('input[name="chave_pix"]').fill(account.pixChave);
  await Promise.all([
    page.waitForURL(/\/guincho\/bancario$/i, { timeout: 30_000 }),
    page.locator('button:has-text("Salvar dados bancários")').click(),
  ]);
  await expect(page.locator('body')).toContainText(/dados bancários atualizados com sucesso/i);
}

export async function fazerLogout(page: Page): Promise<void> {
  await page.goto(appPath('/logout'), { waitUntil: 'domcontentloaded' });
  await page.waitForURL(/\/login$/i, { timeout: 20_000 });
}
