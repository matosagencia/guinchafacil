import { test, expect } from '@playwright/test';
import { buildClienteBatch, buildGuinchoBatch, resolveWindowsImage } from '../helpers/account-factories';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { waitForLeafletMap } from '../helpers/map';
import { appPath } from '../helpers/paths';
import { findPedidoByMarker, seedAdmin, simularPagamentoAprovado } from '../helpers/seed';

// Diferente de todos os outros specs de atendimento (que usam pedidos
// seedados direto no banco pra pular rápido pra "a_caminho"), este cobre o
// ciclo de vida REAL do zero, do jeito que um usuário de verdade passaria:
//   1) cria conta de guincho, admin aprova, guincho completa o cadastro
//      operacional e fica disponível;
//   2) cria conta de cliente, completa perfil + veículo + oficina, e pede
//      socorro pelo formulário de verdade (mapa, não seed);
//   3) o guincho recebe a oferta e aceita — fechando o ciclo
//      cadastro → aprovação → operação → pedido → aceite.
// As coordenadas de operação do guincho e do pedido são forçadas pra São
// Paulo (mesma região usada pelos demais specs de atendimento) só pra caber
// dentro do raio de cobertura e ficar fácil de acompanhar no mapa do admin —
// os campos de endereço textual (CEP/rua) continuam com os dados de
// account-factories.ts, que não influenciam o matching geográfico.
const SAO_PAULO_LAT = -23.55052;
const SAO_PAULO_LNG = -46.63331;

test.describe.serial('onboarding completo (cadastro, aprovação e pedido do zero)', () => {
  test.setTimeout(6 * 60_000);

  test('E2E-ONB-001 | guincho e cliente nascem do zero, guincho é aprovado, cliente pede socorro e o guincho aceita', async ({ page, context, browser }, testInfo) => {
    const runTag = String(Date.now());
    const guinchoAccount = buildGuinchoBatch(1, runTag)[0];
    const clienteAccount = buildClienteBatch(1, runTag)[0];
    const marker = `QA Onboarding ${runTag}`;
    const uploadImage = resolveWindowsImage();

    // ── 1. Registro do guincho (wizard de 3 passos) ────────────────────
    // Captura o alert() de validateStep1() para, se o passo 1 falhar, o erro
    // dizer qual campo bloqueou em vez de dar timeout opaco no #f_placa.
    let ultimoAlerta = '';
    page.on('dialog', async (d) => { ultimoAlerta = d.message(); await d.dismiss().catch(() => {}); });

    await page.goto(appPath('/registro/guincho'), { waitUntil: 'domcontentloaded' });
    await page.locator('#f_nome').fill(guinchoAccount.nome);
    await page.locator('#f_email').fill(guinchoAccount.email);
    await page.locator('#f_tel').fill(guinchoAccount.telefone);
    await page.locator('#f_cpf').fill(guinchoAccount.cpfFormatted);
    await page.locator('input[name="nascimento"]').fill(guinchoAccount.nascimento);
    await page.locator('#f_senha').fill(guinchoAccount.password);
    await page.locator('#f_conf').fill(guinchoAccount.password);
    await page.locator('#g_cep').fill(guinchoAccount.cep);
    await page.locator('#g_logradouro').fill(guinchoAccount.logradouro);
    await page.locator('input[name="numero"]').fill(guinchoAccount.numero);
    await page.locator('input[name="complemento"]').fill(guinchoAccount.complemento);
    await page.locator('#g_bairro').fill(guinchoAccount.bairro);
    await page.locator('#g_cidade').fill(guinchoAccount.cidade);
    await page.locator('#g_estado').selectOption(guinchoAccount.estado);
    await page.locator('[data-go-step="2"]:visible').click();

    try {
      await page.locator('#f_placa').waitFor({ state: 'visible', timeout: 5000 });
    } catch {
      throw new Error(
        `Passo 2 do cadastro de guincho não abriu (validação do passo 1 falhou). ` +
        `Alerta: "${ultimoAlerta || '(nenhum capturado)'}".`
      );
    }
    await page.locator('#f_placa').fill(guinchoAccount.placaGuincho);
    await page.locator('input[name="capacidade_ton"]').fill(guinchoAccount.capacidadeTon);
    await page.locator('#raioRange').fill(guinchoAccount.raioKm);
    await page.locator('#f_cnh').fill(guinchoAccount.cnhNumero);
    await page.locator('input[name="cnh_validade"]').fill(guinchoAccount.cnhValidade);
    await page.locator('[data-go-step="3"]').click();

    await page.locator('input[name="doc_cnh_frente"]').setInputFiles(uploadImage);
    await page.locator('input[name="doc_cnh_verso"]').setInputFiles(uploadImage);
    await page.locator('input[name="foto_veiculo"]').setInputFiles(uploadImage);
    await page.locator('select[name="chave_pix_tipo"]').selectOption(guinchoAccount.pixTipo);
    await page.locator('input[name="chave_pix"]').fill(guinchoAccount.pixChave);

    await Promise.all([
      page.waitForURL(/\/login$/i, { timeout: 90000 }),
      page.locator('#btnFinal').click(),
    ]);

    // ── 2. Admin aprova o guincho recém-cadastrado ──────────────────────
    const admin = seedAdmin();
    await loginAs(page, admin.admin_email, admin.admin_password);
    await expectLoggedIn(page);
    await page.goto(appPath('/admin/guinchospendentes'), { waitUntil: 'domcontentloaded' });

    const pendingCard = page.locator('.gp-card', { hasText: guinchoAccount.email });
    await expect(pendingCard).toBeVisible({ timeout: 15000 });
    await Promise.all([
      page.waitForURL(/\/admin\/guinchospendentes/i, { timeout: 20000 }),
      pendingCard.locator('form[action$="/admin/guincho/aprovar"] button').click(),
    ]);
    await expect(page.locator('body')).toContainText(/aprovado/i);

    await page.goto(appPath('/logout'), { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/\/login$/i, { timeout: 20000 });

    // ── 3. Guincho aprovado completa operação e fica disponível ────────
    await loginAs(page, guinchoAccount.email, guinchoAccount.password);
    await expectLoggedIn(page);

    await page.goto(appPath('/guincho/perfil'), { waitUntil: 'domcontentloaded' });
    // Coordenadas de operação forçadas pra São Paulo (ver comentário no topo
    // do arquivo) — o endereço textual do cadastro continua no Rio, mas o
    // matching de pedidos usa lat/lng, não o endereço.
    await page.locator('#lat_operacao').evaluate((el: HTMLInputElement) => { el.value = String(-23.55052); });
    await page.locator('#lng_operacao').evaluate((el: HTMLInputElement) => { el.value = String(-46.63331); });
    await page.locator('input[name="foto_caminhao"]').setInputFiles(uploadImage);
    await Promise.all([
      page.waitForURL(/\/guincho\/perfil$/i, { timeout: 30000 }),
      page.locator('button[type="submit"]:has-text("Salvar Alterações")').click(),
    ]);
    await expect(page.locator('body')).toContainText(/perfil atualizado com sucesso/i);

    await page.goto(appPath('/guincho/dashboard'), { waitUntil: 'domcontentloaded' });
    // O checkbox #toggleDisponivel é visualmente oculto (CSS custom
    // "toggle-switch") — quem recebe o clique de verdade é o <label> que o
    // envolve (mesmo padrão já documentado em cadastro-guincho-bulk.spec.ts
    // pro radio btn-check de tipo de veículo).
    const toggle = page.locator('#toggleDisponivel');
    if (!(await toggle.isChecked())) {
      await page.locator('label.toggle-switch').click();
      await expect(page.locator('#labelDisponivel')).toContainText(/online/i, { timeout: 15000 });
    }

    await page.goto(appPath('/logout'), { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/\/login$/i, { timeout: 20000 });

    // ── 4. Registro do cliente + perfil + veículo + oficina ─────────────
    await page.goto(appPath('/registro/cliente'), { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="nome"]').fill(clienteAccount.nome);
    await page.locator('input[name="email"]').fill(clienteAccount.email);
    await page.locator('input[name="telefone"]').fill(clienteAccount.telefone);
    await page.locator('input[name="cpf"]').fill(clienteAccount.cpfFormatted);
    await page.locator('input[name="senha"]').fill(clienteAccount.password);
    await page.locator('input[name="confirmar_senha"]').fill(clienteAccount.password);
    await page.locator('input[name="cep"]').fill(clienteAccount.cep);
    await page.locator('input[name="logradouro"]').fill(clienteAccount.logradouro);
    await page.locator('input[name="numero"]').fill(clienteAccount.numero);
    await page.locator('input[name="complemento"]').fill(clienteAccount.complemento);
    await page.locator('input[name="bairro"]').fill(clienteAccount.bairro);
    await page.locator('input[name="cidade"]').fill(clienteAccount.cidade);
    await page.locator('select[name="estado"]').selectOption(clienteAccount.estado);
    await Promise.all([
      page.waitForURL(/\/login$/i, { timeout: 30000 }),
      page.locator('#formCliente button[type="submit"]').click(),
    ]);

    await loginAs(page, clienteAccount.email, clienteAccount.password);
    await expectLoggedIn(page);

    await page.goto(appPath('/cliente/veiculo/novo'), { waitUntil: 'domcontentloaded' });
    await page.locator(`label[for="tipo_${clienteAccount.veiculoTipo}"]`).click();
    await page.locator('input[name="marca"]').fill(clienteAccount.veiculoMarca);
    await page.locator('input[name="modelo"]').fill(clienteAccount.veiculoModelo);
    await page.locator('input[name="ano"]').fill(clienteAccount.veiculoAno);
    await page.locator('input[name="cor"]').fill(clienteAccount.veiculoCor);
    await page.locator('input[name="placa"]').fill(clienteAccount.veiculoPlaca);
    await Promise.all([
      page.waitForURL(/\/cliente\/veiculos\?salvo=1/i, { timeout: 30000 }),
      page.locator('button[type="submit"]').click(),
    ]);

    await page.goto(appPath('/cliente/oficina/nova'), { waitUntil: 'domcontentloaded' });
    await page.locator('input[name="nome"]').fill(clienteAccount.oficinaNome);
    await page.locator('input[name="endereco"]').fill(clienteAccount.oficinaEndereco);
    await page.locator('input[name="telefone"]').fill(clienteAccount.oficinaTelefone);
    await page.locator('#latInput').evaluate((el: HTMLInputElement) => { el.value = '-22.896330'; });
    await page.locator('#lngInput').evaluate((el: HTMLInputElement) => { el.value = '-43.198420'; });
    await Promise.all([
      page.waitForURL(/\/cliente\/oficinas\?salvo=1/i, { timeout: 30000 }),
      page.locator('button[type="submit"]').click(),
    ]);

    // ── 5. Cliente pede socorro de verdade (mapa, não seed) ─────────────
    await context.setGeolocation({ latitude: SAO_PAULO_LAT, longitude: SAO_PAULO_LNG });
    await context.grantPermissions(['geolocation']);

    await page.goto(appPath('/cliente/pedido/novo'), { waitUntil: 'domcontentloaded' });
    await waitForLeafletMap(page);

    // Tela redesenhada (wizard sintoma->detalhes->confirmar). Veículo único
    // vira hidden #veiculo_id (já preenchido); múltiplos, select #veiculo_id_select.
    const veiculoSelect = page.locator('#veiculo_id_select');
    if (await veiculoSelect.count()) {
      await veiculoSelect.selectOption({ index: 1 });
    }

    // Pula a triagem e vai direto ao passo de confirmação.
    await page.click('#btnPularTriagem');

    // Origem/destino por busca (geocode real) — o antigo clique no mapa +
    // #badgeOrigem/#btnModeDest não existe mais.
    await page.fill('#inputOrigem', 'Praça da Sé, São Paulo');
    await page.click('#btnBuscarOrigem');
    await expect(page.locator('#origemFeedback')).toContainText(/localiza..o encontrada/i, { timeout: 20000 });

    const tabOutro = page.locator('#tabOutro');
    if (await tabOutro.count().catch(() => 0)) {
      await tabOutro.click();
    }
    await page.fill('#inputDest', 'Avenida Paulista, São Paulo');
    await page.locator('#btnBuscarDestinoTab, #btnBuscarDestinoLivre').first().click();
    await expect(page.locator('#badgeDest')).toContainText(/Definido/i, { timeout: 20000 });

    // Marcador na descrição para localizar o pedido depois.
    const descricaoField = page.locator('textarea[name="descricao"]');
    if (await descricaoField.count()) {
      await descricaoField.fill(marker);
    }

    await expect(page.locator('#btnSubmit')).toBeEnabled({ timeout: 20000 });
    await page.locator('#btnSubmit').click();
    // O redirect pode ir tanto pra /cliente/dashboard?msg=pedido_criado
    // (fluxo livre) quanto pra /pagamento/checkout/{id} (pagamento
    // obrigatório) — em vez de adivinhar pela URL, localizamos o pedido pelo
    // marcador escrito no campo descrição.
    await page.waitForURL(/\/cliente\/dashboard|\/pagamento\/checkout/i, { timeout: 30000 });

    let found = findPedidoByMarker(marker);
    for (let attempt = 0; attempt < 5 && !found.ok; attempt += 1) {
      await page.waitForTimeout(1000);
      found = findPedidoByMarker(marker);
    }
    if (!found.ok || !found.pedido_id) {
      throw new Error(`Não encontrei o pedido criado (marcador: ${marker}). Resposta: ${JSON.stringify(found)}`);
    }
    const pedidoId = found.pedido_id;

    if (found.status === 'aguardando_pagamento') {
      const pago = simularPagamentoAprovado(pedidoId);
      if (!pago.ok) {
        throw new Error(`Falha ao simular pagamento aprovado: ${JSON.stringify(pago)}`);
      }
    }

    await page.goto(appPath('/logout'), { waitUntil: 'domcontentloaded' });
    await page.waitForURL(/\/login$/i, { timeout: 20000 });

    // ── 6. Guincho recebe a oferta e aceita ──────────────────────────────
    const guinchoContext = await browser.newContext();
    const guinchoPage = await guinchoContext.newPage();
    try {
      await loginAs(guinchoPage, guinchoAccount.email, guinchoAccount.password);
      await expectLoggedIn(guinchoPage);

      // O dashboard só expõe um link "Aceitar" clicável para a oferta EM
      // DESTAQUE ("Nova solicitação") — se houver qualquer outro pedido
      // "aguardando_guincho" com ranking igual ou melhor (ex: sobra de uma
      // rodada de teste anterior que nunca foi aceita/expirada), é ELE que
      // fica em destaque, e o pedido novo desta rodada aparece só na lista
      // "Fila de ofertas" sem link direto — foi exatamente isso que
      // aconteceu aqui (pedido #1338 de uma execução anterior ainda
      // "aguardando_guincho" ficou em destaque na frente do #1339 novo).
      // Navegar direto pra /guincho/aceitar/{id} (mesmo padrão de
      // concorrencia-aceite.spec.ts) elimina essa ambiguidade por completo.
      await guinchoPage.goto(appPath(`/guincho/aceitar/${pedidoId}`), { waitUntil: 'domcontentloaded' });

      await Promise.all([
        guinchoPage.waitForURL(/\/guincho\/(atendimento|dashboard)\//i, { timeout: 20000 }),
        guinchoPage.locator('button:has-text("Aceitar")').click(),
      ]);

      const bodyText = (await guinchoPage.locator('body').textContent()) || '';
      const aceito = /\/guincho\/atendimento\//i.test(guinchoPage.url()) || /Atendimento #/i.test(bodyText);
      expect(aceito, `Guincho não conseguiu aceitar o pedido ${pedidoId}. URL: ${guinchoPage.url()}`).toBeTruthy();

      await testInfo.attach('onboarding-completo.json', {
        body: JSON.stringify({
          run_tag: runTag,
          guincho_email: guinchoAccount.email,
          cliente_email: clienteAccount.email,
          pedido_id: pedidoId,
        }, null, 2),
        contentType: 'application/json',
      });
    } finally {
      await guinchoContext.close();
    }
  });
});
