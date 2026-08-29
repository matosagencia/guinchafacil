import { test, expect } from '@playwright/test';
import { clienteCreds } from '../fixtures/test-data.fixture';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { waitForLeafletMap } from '../helpers/map';
import { appPath } from '../helpers/paths';

test.describe('pedido novo com mapa', () => {
  test.beforeEach(async ({ page, context }) => {
    await context.setGeolocation({ latitude: -23.5618, longitude: -46.6565 });
    await context.grantPermissions(['geolocation']);
    const { email, password } = clienteCreds();
    test.skip(!email || !password, 'Credenciais de cliente não configuradas.');
    await loginAs(page, email, password);
    await expectLoggedIn(page);
  });

  test('E2E-ORD-UI-001 | tela carrega e submit inicia bloqueado', async ({ page }) => {
    await page.goto(appPath('/cliente/pedido/novo'));
    await waitForLeafletMap(page);
    await expect(page.locator('#btnSubmit')).toBeDisabled();
  });

  test('E2E-ORD-UI-002 | origem/destino por busca habilitam submit e estimam custo', async ({ page }) => {
    // Reescrito para a tela redesenhada (wizard sintoma->detalhes->confirmar).
    // O formulário antigo (clique no mapa p/ origem + #badgeOrigem + #btnModeDest)
    // não existe mais: origem/destino agora vêm de busca por endereço (geocode
    // real), e a etapa de confirmação só aparece após a triagem (aqui pulada
    // via #btnPularTriagem). Exige conta com ao menos 1 veículo (rodar o seed
    // tools/prepare_p1_qa_seeds.php).
    await page.goto(appPath('/cliente/pedido/novo'));
    await waitForLeafletMap(page);

    const veiculoSelect = page.locator('#veiculo_id_select');
    if (await veiculoSelect.count()) {
      await veiculoSelect.selectOption({ index: 1 });
    } else {
      const hiddenVeiculo = await page.locator('#veiculo_id').getAttribute('value').catch(() => '');
      test.skip(!hiddenVeiculo || hiddenVeiculo === '0', 'Conta de teste sem veículo (rode prepare_p1_qa_seeds.php).');
    }

    await page.click('#btnPularTriagem');

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

    // Botão habilita depois que a estimativa de custo (AJAX) resolve.
    await expect(page.locator('#btnSubmit')).toBeEnabled({ timeout: 20000 });
    await expect(page.locator('#custoValDisplay')).toContainText(/R\$/, { timeout: 20000 });
  });

  test('E2E-ORD-NIT-001 | corrida dentro da célula 1 de Niterói (Icaraí) calcula tarifa e libera envio', async ({ page, context }) => {
    await context.setGeolocation({ latitude: -22.905642, longitude: -43.100263 });
    await page.goto(appPath('/cliente/pedido/novo'));
    await waitForLeafletMap(page);

    const veiculoSelect = page.locator('#veiculo_id_select');
    if (await veiculoSelect.count()) {
      await veiculoSelect.selectOption({ index: 1 });
    } else {
      const hiddenVeiculo = await page.locator('#veiculo_id').getAttribute('value').catch(() => '');
      test.skip(!hiddenVeiculo || hiddenVeiculo === '0', 'Conta de teste sem veículo.');
    }

    await page.click('#btnPularTriagem');
    await page.fill('#inputOrigem', 'Rua Gavião Peixoto, 100, Icaraí, Niterói, RJ');
    await page.click('#btnBuscarOrigem');
    await expect(page.locator('#origemFeedback')).toContainText(/localiza..o encontrada/i, { timeout: 20000 });

    const tabOutro = page.locator('#tabOutro');
    if (await tabOutro.count().catch(() => 0)) await tabOutro.click();
    await page.fill('#inputDest', 'Rua Moreira César, 26, Icaraí, Niterói, RJ');
    await page.locator('#btnBuscarDestinoTab, #btnBuscarDestinoLivre').first().click();
    await expect(page.locator('#badgeDest')).toContainText(/Definido/i, { timeout: 20000 });

    await expect(page.locator('#btnSubmit')).toBeEnabled({ timeout: 20000 });
    await expect(page.locator('#custoValDisplay')).toContainText(/R\$/, { timeout: 20000 });
  });
  test('E2E-ORD-NIT-002 | pedido de socorro real na zona Praias da Baía Central entra no fluxo operacional', async ({ page, context }) => {
    await context.setGeolocation({ latitude: -22.905642, longitude: -43.100263 });
    await page.goto(appPath('/cliente/pedido/novo'));
    await waitForLeafletMap(page);

    const veiculoSelect = page.locator('#veiculo_id_select');
    if (await veiculoSelect.count()) {
      await veiculoSelect.selectOption({ index: 1 });
    } else {
      const hiddenVeiculo = await page.locator('#veiculo_id').getAttribute('value').catch(() => '');
      test.skip(!hiddenVeiculo || hiddenVeiculo === '0', 'Conta de teste sem veículo.');
    }

    await page.click('#btnPularTriagem');
    await page.fill('#inputOrigem', 'Rua Gavião Peixoto, 100, Icaraí, Niterói, RJ');
    await page.click('#btnBuscarOrigem');
    await expect(page.locator('#origemFeedback')).toContainText(/localiza..o encontrada/i, { timeout: 20000 });

    const tabOutro = page.locator('#tabOutro');
    if (await tabOutro.count().catch(() => 0)) await tabOutro.click();
    await page.fill('#inputDest', 'Rua Moreira César, 26, Icaraí, Niterói, RJ');
    await page.locator('#btnBuscarDestinoTab, #btnBuscarDestinoLivre').first().click();
    await expect(page.locator('#badgeDest')).toContainText(/Definido/i, { timeout: 20000 });
    await expect(page.locator('#btnSubmit')).toBeEnabled({ timeout: 20000 });

    await page.click('#btnSubmit');
    await page.waitForURL(/\/(pagamento\/checkout\/|cliente\/dashboard(?:\/|[?#]|$)|cliente\/pedido(?:\/|[?#]|$))/, { timeout: 30000 });
    if (/\/pagamento\/checkout\//i.test(page.url())) {
      await expect(page.locator('body')).toContainText(/checkout|pagamento/i);
    } else {
      await expect(page.locator('body')).toContainText(/aguardando guincho|buscando prestador/i);
    }
  });});
