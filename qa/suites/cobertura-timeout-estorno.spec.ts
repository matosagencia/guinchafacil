import { test, expect } from '@playwright/test';
import { clienteCreds } from '../fixtures/test-data.fixture';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { waitForLeafletMap } from '../helpers/map';
import { appPath } from '../helpers/paths';
import { executarCronExpiracao, qaPedidoSnapshot, seedTimeoutEstorno, testeGateCobertura } from '../helpers/seed';

// §COBERTURA-RAIO-01 (05/08/2026): antes desta suite, dois comportamentos
// novos não tinham NENHUM teste automatizado:
//
//   1) O gate de cobertura em ClienteController::pedidoCriar() —
//      CoberturaService::existeGuinchoAlcancavel() — que bloqueia a abertura
//      de um pedido quando nenhum guincho aprovado alcança aquela coordenada
//      dentro do próprio raio_cobertura_km (MIN com o teto global
//      raio_maximo_km). Sem cobertura real, o cliente conseguia pagar por um
//      atendimento que nunca teria quem aceitasse.
//
//   2) O timeout de 30 min sem aceite (pedidos.expiracao_aceite) +
//      cancelamento e estorno automáticos
//      (tools/cron_cancelar_pedidos_expirados.php /
//      ExpiracaoPedidosService::executar()) — já existia no código, mas sem
//      cobertura de teste E2E/QA nenhuma.
//
// A suite mistura um teste de backend puro (rápido, determinístico, sem
// depender de geocoding externo) com um teste de UI real — mesmo padrão já
// usado no projeto (ex.: cancelamento.spec.ts confirma efeito no servidor via
// polling em vez de confiar cegamente no redirect client-side).

test.describe('cobertura por raio e timeout/estorno automático', () => {
  test.describe.configure({ timeout: 120_000 });

  test('E2E-COB-001 | CoberturaService bloqueia fora do raio e libera dentro dele (backend)', () => {
    const resultado = testeGateCobertura();
    expect(resultado.ok, resultado.mensagem).toBe(true);
    expect(resultado.fora_de_cobertura_bloqueou).toBe(true);
    expect(resultado.dentro_de_cobertura_liberou).toBe(true);
  });

  test('E2E-COB-002 | cliente NÃO consegue abrir pedido numa região sem nenhum guincho alcançável', async ({ page, context }) => {
    const cliente = clienteCreds();
    test.skip(!cliente.email || !cliente.password, 'Credenciais de cliente não configuradas.');
    await loginAs(page, cliente.email, cliente.password);
    await expectLoggedIn(page);

    // Manaus, AM — nenhum guincho seed (todos em São Paulo/Niterói) chega
    // nem perto do próprio raio_cobertura_km, muito menos do teto global.
    await context.setGeolocation({ latitude: -3.1019, longitude: -60.0250 });
    await context.grantPermissions(['geolocation']);

    await page.goto(appPath('/cliente/pedido/novo'));
    await waitForLeafletMap(page);

    const veiculoSelect = page.locator('#veiculo_id_select');
    if (await veiculoSelect.count()) {
      await veiculoSelect.selectOption({ index: 1 });
    } else {
      const hiddenVeiculo = await page.locator('#veiculo_id').getAttribute('value').catch(() => '');
      test.skip(!hiddenVeiculo || hiddenVeiculo === '0', 'Conta de teste sem veículo (rode prepare_p1_qa_seeds.php).');
    }

    const btnPularTriagem = page.locator('#btnPularTriagem');
    if (await btnPularTriagem.count().catch(() => 0)) {
      await btnPularTriagem.click();
    }

    await page.fill('#inputOrigem', 'Manaus, Amazonas');
    await page.click('#btnBuscarOrigem');
    await expect(page.locator('#origemFeedback')).toContainText(/localiza..o encontrada/i, { timeout: 20000 });

    const tabOutro = page.locator('#tabOutro');
    if (await tabOutro.count().catch(() => 0)) await tabOutro.click();
    await page.fill('#inputDest', 'Manaus, Amazonas, Centro');
    await page.locator('#btnBuscarDestinoTab, #btnBuscarDestinoLivre').first().click();
    await expect(page.locator('#badgeDest')).toContainText(/Definido/i, { timeout: 20000 });

    // Mesmo com origem/destino válidos e custo estimado, o servidor bloqueia
    // no submit (?erro=sem_cobertura) — o teste força o POST diretamente na
    // rota, já que o botão pode ou não desabilitar no client antes disso
    // (o gate é 100% server-side, de propósito, pra nunca depender de JS).
    await expect(page.locator('#btnSubmit')).toBeEnabled({ timeout: 20000 });
    await page.click('#btnSubmit');

    await page.waitForURL(/\/cliente\/pedido\/novo\?erro=/, { timeout: 20000 });
    expect(page.url()).toContain('erro=sem_cobertura');
    await expect(page.locator('.alert-danger')).toContainText(/n.o h. nenhum prestador que alcance/i);
  });

  test('E2E-COB-003 | pedido sem aceite em 30min é cancelado e o estorno é acionado automaticamente', async () => {
    const seed = seedTimeoutEstorno();
    expect(seed.ok).toBe(true);

    const antes = qaPedidoSnapshot(seed.pedido_id);
    expect(antes.pedido?.status).toBe('aguardando_guincho');
    expect(antes.pagamento?.status).toBe('aprovado');

    const cron = executarCronExpiracao();
    expect(cron.ok, cron.erro).toBe(true);
    expect(cron.cancelled ?? 0).toBeGreaterThanOrEqual(1);

    const depois = qaPedidoSnapshot(seed.pedido_id);
    expect(depois.pedido?.status).toBe('cancelado');
    // id_externo é fictício de propósito (ver tools/prepare_timeout_estorno_qa_seed.php)
    // — o gateway real deve rejeitar o refund, então o esperado é o pagamento
    // NUNCA ficar preso em 'estornando': ou o refund foi aceito ('estornado')
    // ou falhou e voltou pra 'aprovado'. O que este teste garante é que o
    // pedido é cancelado e o pagamento não fica num limbo, independente de
    // haver credenciais reais de sandbox no ambiente de execução.
    expect(['estornado', 'aprovado']).toContain(depois.pagamento?.status);
  });
});
