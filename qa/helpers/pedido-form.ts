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

  // Tela redesenhada (wizard sintoma -> detalhes -> confirmar): o veículo é um
  // <select> #veiculo_id_select só quando a conta tem 2+ veículos; com 1 só,
  // vira <input hidden> #veiculo_id já preenchido. Detecta os dois casos.
  const veiculoSelect = page.locator('#veiculo_id_select');
  if (await veiculoSelect.count()) {
    await veiculoSelect.selectOption({ index: 1 });
  } else {
    const hiddenVeiculo = await page.locator('#veiculo_id').getAttribute('value').catch(() => '');
    if (!hiddenVeiculo || hiddenVeiculo === '0') {
      throw new Error('Conta de teste sem veículo cadastrado — não dá pra criar pedido pelo formulário. Cadastre um veículo em /cliente/veiculo/novo antes.');
    }
  }

  // Pula a triagem (chips de sintoma) e vai direto ao passo de confirmação:
  // #btnPularTriagem seta tipo_problema='outro', marca destino como necessário
  // e mostra a etapa "confirmar" (destino/custo/submit). Caminho estável que
  // não depende de qual sintoma tem perguntas extras.
  await page.click('#btnPularTriagem');

  // Origem: busca por endereço (geocode real), não clique no mapa. O feedback
  // fica em #origemFeedback (o antigo #badgeOrigem não existe mais).
  await page.fill('#inputOrigem', opts.origem);
  await page.click('#btnBuscarOrigem');
  await expect(page.locator('#origemFeedback'), `Falha ao geocodificar origem "${opts.origem}"`)
    .toContainText(/localiza..o encontrada/i, { timeout: 15000 });

  // Destino: se houver oficinas cadastradas, o campo de endereço livre fica
  // atrás da aba "Outro Endereço" (#tabOutro); senão já é o campo principal.
  const tabOutro = page.locator('#tabOutro');
  if (await tabOutro.count().catch(() => 0)) {
    await tabOutro.click();
  }
  await page.fill('#inputDest', opts.destino);
  await page.locator('#btnBuscarDestinoTab, #btnBuscarDestinoLivre').first().click();
  await expect(page.locator('#destFeedback'), `Falha ao geocodificar destino "${opts.destino}"`)
    .toContainText(/destino encontrado/i, { timeout: 15000 });
  await expect(page.locator('#badgeDest')).toContainText(/Definido/i);
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

  await page.click('#btnSubmit');

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
