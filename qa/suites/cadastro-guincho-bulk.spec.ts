import { test, expect } from '@playwright/test';
import { buildGuinchoBatch, isValidCpf, resolveWindowsImage } from '../helpers/account-factories';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

test.describe.serial('cadastro guincho em lote', () => {
  test.setTimeout(25 * 60 * 1000);

  test('E2E-REG-GUI-001 | cria 15 contas de guincho com CPF válido, placa válida e perfil operacional completo', async ({ page }, testInfo) => {
    const runTag = process.env.QA_BATCH_RUN_TAG || String(Date.now());
    const accounts = buildGuinchoBatch(15, runTag);
    const uploadImage = resolveWindowsImage();
    const created: Array<Record<string, string>> = [];

    // Diagnóstico: validateStep1() (auth-registro-guincho.js) dispara window.alert()
    // e NÃO avança pro passo 2 quando um campo do passo 1 é inválido. O Playwright
    // auto-dismissa o alert, então antes isso virava só um timeout opaco no
    // #f_placa. Capturamos a mensagem para o erro dizer QUAL campo falhou.
    let ultimoAlerta = '';
    page.on('dialog', async (d) => { ultimoAlerta = d.message(); await d.dismiss().catch(() => {}); });
    // Também capturamos erros de JS não tratados da página: se validateStep1()
    // ou irStep() lançar (ex.: getElementById null), não há alert e o passo 2
    // simplesmente não abre — o pageerror revela a causa.
    let ultimoPageError = '';
    page.on('pageerror', (e) => { ultimoPageError = e.message; });
    // Status HTTP do JS que define irStep — se 404/bloqueado, o passo 2 nunca
    // navega e não há pageerror (falha de recurso não é erro de JS).
    let statusRegistroJs = '(não requisitado)';
    page.on('response', (r) => {
      if (r.url().includes('auth-registro-guincho.js')) statusRegistroJs = `${r.status()} ${r.url()}`;
    });
    let ultimoConsole = '';
    page.on('console', (m) => { if (m.type() === 'error') ultimoConsole = m.text(); });

    for (const account of accounts) {
      expect(isValidCpf(account.cpfDigits)).toBeTruthy();

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
      // O formulário tem 2 botões com data-go-step="2" no DOM: o "Continuar"
      // do step1 e o "Voltar" do step3 (que também aponta pra step2). Ambos
      // existem simultaneamente (só um fica visível por vez), então o
      // locator sem filtro dá strict-mode violation. `:visible` restringe
      // ao botão realmente clicável no momento.
      await page.locator('[data-go-step="2"]:visible').click();

      try {
        await page.locator('#f_placa').waitFor({ state: 'visible', timeout: 5000 });
      } catch {
        const estado = await page.evaluate(() => {
          const disp = (id: string) => (document.getElementById(id) as HTMLElement | null)?.style.display ?? '(sem elemento)';
          const btn = document.querySelector('[data-go-step="2"]') as HTMLElement | null;
          return {
            step1: disp('step1'), step2: disp('step2'), step3: disp('step3'),
            temIrStep: typeof (window as any).irStep,
            btnGoStep2Existe: !!btn,
          };
        }).catch(() => null);
        throw new Error(
          `Passo 2 do cadastro de guincho não abriu. ` +
          `Alerta: "${ultimoAlerta || '(nenhum)'}" | PageError: "${ultimoPageError || '(nenhum)'}" | ` +
          `RegistroJS: "${statusRegistroJs}" | Console: "${ultimoConsole || '(nenhum)'}" | ` +
          `Estado: ${JSON.stringify(estado)} — conta ${account.email}.`
        );
      }
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

      // Este é o único cadastro em lote com uploads reais (3 arquivos por
      // conta) — diferente de cadastro-cliente-bulk, que não envia arquivo
      // nenhum e por isso é bem mais rápido. Os drivers de automação do
      // Firefox e do WebKit são consistentemente mais lentos que o Chromium
      // para codificar/transmitir corpos multipart grandes, então o mesmo
      // envio que é instantâneo no Chromium pode legitimamente passar de
      // 60s nesses navegadores sem que isso seja uma falha de verdade.
      await Promise.all([
        page.waitForURL(/\/login$/i, { timeout: 90000 }),
        page.locator('#btnFinal').click(),
      ]);
      await expect(page).toHaveURL(/\/login$/i);
      await expect(page.locator('form[action$="/login"]')).toBeVisible();

      await loginAs(page, account.email, account.password);
      await expectLoggedIn(page);

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
        page.waitForURL(/\/guincho\/perfil$/i, { timeout: 30000 }),
        page.locator('button[type="submit"]:has-text("Salvar Alterações")').click(),
      ]);
      await expect(page.locator('body')).toContainText(/perfil atualizado com sucesso/i);

      await page.goto(appPath('/guincho/operacao'), { waitUntil: 'domcontentloaded' });
      await page.locator('input[name="placa_guincho"]').fill(account.placaGuincho);
      await page.locator('input[name="capacidade_ton"]').fill(account.capacidadeTon);
      await page.locator('#raioRange').fill(account.raioKm);
      await page.locator('input[name="cnh_numero"]').fill(account.cnhNumero);
      await page.locator('input[name="cnh_validade"]').fill(account.cnhValidade);
      // O botão em perfil_operacao.php não tem o atributo `type="submit"`
      // explícito no HTML (só herda o comportamento padrão do navegador),
      // então o seletor de atributo `button[type="submit"]` nunca casava
      // com nada e o .click() ficava girando por 25 minutos esperando um
      // elemento que jamais "aparecia" para aquele seletor. Localizamos
      // pelo texto do botão em vez do atributo.
      await Promise.all([
        page.waitForURL(/\/guincho\/operacao$/i, { timeout: 30000 }),
        page.locator('button:has-text("Salvar dados do guincho")').click(),
      ]);
      await expect(page.locator('body')).toContainText(/dados operacionais atualizados com sucesso/i);

      await page.goto(appPath('/guincho/bancario'), { waitUntil: 'domcontentloaded' });
      await page.locator('select[name="chave_pix_tipo"]').selectOption(account.pixTipo);
      await page.locator('input[name="chave_pix"]').fill(account.pixChave);
      // Mesmo caso de perfil_operacao.php: botão sem atributo type="submit"
      // explícito (perfil_bancario.php linha 164) — localizamos pelo texto.
      await Promise.all([
        page.waitForURL(/\/guincho\/bancario$/i, { timeout: 30000 }),
        page.locator('button:has-text("Salvar dados bancários")').click(),
      ]);
      await expect(page.locator('body')).toContainText(/dados bancários atualizados com sucesso/i);

      created.push({
        nome: account.nome,
        email: account.email,
        cpf: account.cpfFormatted,
        placa_guincho: account.placaGuincho,
        pix: account.pixChave,
      });

      await page.goto(appPath('/logout'), { waitUntil: 'domcontentloaded' });
      await page.waitForURL(/\/login$/i, { timeout: 20000 });
    }

    await testInfo.attach('cadastro-guincho-bulk.json', {
      body: JSON.stringify({ runTag, total: created.length, contas: created }, null, 2),
      contentType: 'application/json',
    });
  });
});
