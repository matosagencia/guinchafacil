import { expect, type Page } from '@playwright/test';
import { appPath } from './paths';
import { waitForLeafletMap } from './map';

export type CriarPedidoViaFormularioOpts = {
  origem: string;
  destino: string;
  tipoProblema: 'bateria' | 'pneu' | 'combustivel' | 'colisao' | 'eletrica' | 'outro';
};

/**
 * Cria um pedido de socorro pelo formulário real do dashboard
 * (src/Views/cliente/pedidonovo.php), buscando origem/destino por endereço
 * (geocoding real via Nominatim, rota /geocode) em vez de clicar em pontos
 * arbitrários no mapa ou inserir direto no banco via seed — dá o registro
 * completo (pedido, geocoding, cálculo de distância/custo) igual a um cliente
 * de verdade faria.
 *
 * Devolve o pedido_id. Se o ambiente exigir pagamento antecipado
 * (PedidoService::podeIniciarAtendimento() === false, cenário normal com
 * PAYMENT_GATEWAY_ACTIVE configurado), ClienteController::pedidoCriar()
 * redireciona direto para /pagamento/checkout/{id} — a página já fica aberta
 * nesse estado ao final desta função.
 */
/**
 * Preenche o formulário do zero (goto -> veículo -> tipo -> origem ->
 * destino) até o ponto em que o botão deveria habilitar. Extraído como
 * função própria pra dar pra REPETIR a tentativa inteira sem duplicar
 * pedido nenhum — nada aqui grava no banco até o clique final em
 * #btnSubmit, então repetir do zero é seguro/idempotente.
 */
async function preencherFormularioAteEstimativa(page: Page, opts: CriarPedidoViaFormularioOpts): Promise<void> {
  await page.goto(appPath('/cliente/pedido/novo'), { waitUntil: 'domcontentloaded' });
  await waitForLeafletMap(page);

  const origem = page.locator('[data-role="origem"]');
  const origemInput = origem.locator('[data-ap-input]');
  await origemInput.fill(opts.origem);
  await origem.locator('.gf-ap__item').first().click({ timeout: 30000 });
  const origemConfirm = origem.locator('[data-ap-confirm-btn]');
  if (await origemConfirm.isVisible().catch(() => false)) await origemConfirm.click();

  const modo = page.locator('#stage-modo [data-mode="levar_carro"], #gfModes [data-mode="levar_carro"]').first();
  if (await modo.count()) { await expect(modo).toBeVisible({ timeout: 20000 }); await modo.click(); }

  const destino = page.locator('[data-role="destino"]');
  if (await destino.count()) {
    const destinoInput = destino.locator('[data-ap-input]');
    await expect(destinoInput).toBeVisible({ timeout: 10000 });
    await destinoInput.fill(opts.destino);
    await destino.locator('.gf-ap__item').first().click({ timeout: 30000 });
    const destinoConfirm = destino.locator('[data-ap-confirm-btn]');
    if (await destinoConfirm.isVisible().catch(() => false)) await destinoConfirm.click();
  }

  await expect(page.locator('#gfBtnFinal, #btnSubmit').first()).toBeEnabled({ timeout: 20000 });
}
export async function criarPedidoViaFormulario(page: Page, opts: CriarPedidoViaFormularioOpts): Promise<number> {
  // Todo o preenchimento (goto -> veículo -> tipo -> geocode de origem ->
  // geocode de destino -> espera do botão habilitar) depende de chamadas de
  // rede reais (Nominatim pro geocode, GeoService::calcularCusto pro custo)
  // que já mostraram falhar de forma transitória sob carga/rate-limit — não
  // só o botão ficando desabilitado, mas o PRÓPRIO geocode de origem/destino
  // podendo devolver "não encontrado" numa tentativa e funcionar normalmente
  // na próxima (visto num run real: "Digite o endereço e clique em..." em
  // vez de "localização encontrada"). Por isso a retentativa cobre o
  // preenchimento INTEIRO, não só a espera do botão — é seguro/idempotente
  // porque nada é submetido até o clique em #btnSubmit, então cada
  // tentativa é um formulário limpo do zero, sem acumular estado.
  const MAX_TENTATIVAS = 3;
  let ultimoErro: unknown = null;
  for (let tentativa = 1; tentativa <= MAX_TENTATIVAS; tentativa++) {
    try {
      await preencherFormularioAteEstimativa(page, opts);
      await expect(page.locator('#btnSubmit')).toBeEnabled({ timeout: 20000 });
      ultimoErro = null;
      break;
    } catch (erro) {
      ultimoErro = erro;
    }
  }
  if (ultimoErro) {
    throw new Error(
      `Formulário de pedido falhou após ${MAX_TENTATIVAS} tentativas (geocode transitório ou estimativa de custo não resolveu a tempo) — ` +
      `último erro: ${ultimoErro instanceof Error ? ultimoErro.message : String(ultimoErro)}`
    );
  }

  await page.locator('#gfBtnFinal, #btnSubmit').first().click();

  await page.waitForURL(/\/(pagamento\/checkout|cliente\/dashboard)/i, { timeout: 20000 });
  const match = page.url().match(/\/pagamento\/checkout\/(\d+)/);
  if (match) {
    return parseInt(match[1], 10);
  }

  throw new Error(
    `Pedido criado mas não redirecionou para /pagamento/checkout/{id} (URL final: ${page.url()}). ` +
    'O ambiente pode estar com pagamento antecipado desligado (PedidoService::podeIniciarAtendimento() === true) — ' +
    'nesse caso o pedido nasce liberado direto, sem etapa de checkout.'
  );
}
