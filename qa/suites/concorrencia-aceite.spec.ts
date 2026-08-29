import { test, expect, type Page } from '@playwright/test';
import { guincho2Creds, guinchoCreds } from '../fixtures/test-data.fixture';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedP1 } from '../helpers/seed';

function concorrenciaConfig() {
  return {
    pedidoId: process.env.TEST_CONCORRENCIA_PEDIDO_ID || process.env.TEST_PEDIDO_ACEITE_ID || ''
  };
}

async function acceptedState(page: Page): Promise<boolean> {
  const currentUrl = page.url();
  if (/\/guincho\/atendimento\//i.test(currentUrl)) {
    return true;
  }

  const bodyText = (await page.locator('body').textContent()) || '';
  return /Atendimento #|Corrida Conclu.da|cancelado=1/i.test(bodyText);
}

test.describe('concorrência de aceite', () => {
  test('E2E-CONC-001 | painel do guincho expõe fila de pedidos disponíveis', async ({ page }) => {
    const { email, password } = guinchoCreds();
    test.skip(!email || !password, 'Credenciais de guincho não configuradas.');

    await loginAs(page, email, password);
    await expectLoggedIn(page);
    await page.goto(appPath('/guincho/dashboard'));
    // O painel usa a terminologia "solicitação/oferta" (não "pedido") desde o
    // redesign do dashboard — este regex ficou desatualizado e nunca batia
    // com o texto real da tela, fazendo o teste sempre falhar aqui.
    await expect(page.locator('body')).toContainText(/solicita[cç][aã]o|oferta/i);
  });

  test('E2E-CONC-002 | apenas um guincho deve assumir o mesmo pedido em disputa', async ({ browser }) => {
    const credsA = guinchoCreds();
    let credsB = guincho2Creds();
    let { pedidoId } = concorrenciaConfig();

    // O pedido de disputa é consumido assim que um dos guinchos aceita (sai de
    // "aguardando_guincho"), então não dá pra deixar um ID fixo em variável de
    // ambiente — cada rodada do gate precisa de um pedido novo. Antes isso
    // exigia rodar tools/prepare_p1_qa_seeds.php manualmente e colar o
    // concorrencia_pedido_id/credenciais do 2º guincho em env vars; agora o
    // próprio teste roda o seed PHP quando essas variáveis não estão setadas.
    if (!pedidoId || !credsB.email || !credsB.password) {
      const seeded = seedP1();
      pedidoId = pedidoId || String(seeded.concorrencia_pedido_id);
      credsB = {
        email: credsB.email || seeded.guincho_2_email,
        password: credsB.password || 'test123',
      };
    }

    test.skip(!pedidoId, 'Falha ao auto-seedar pedido de concorrência de aceite.');
    test.skip(!credsA.email || !credsA.password || !credsB.email || !credsB.password, 'Defina TEST_GUINCHO_EMAIL/PASSWORD e TEST_GUINCHO_2_EMAIL/PASSWORD.');

    const contextA = await browser.newContext();
    const contextB = await browser.newContext();
    const pageA = await contextA.newPage();
    const pageB = await contextB.newPage();

    try {
      await loginAs(pageA, credsA.email, credsA.password);
      await loginAs(pageB, credsB.email, credsB.password);
      await expectLoggedIn(pageA);
      await expectLoggedIn(pageB);

      await Promise.all([
        pageA.goto(appPath(`/guincho/aceitar/${pedidoId}`)),
        pageB.goto(appPath(`/guincho/aceitar/${pedidoId}`))
      ]);

      await Promise.allSettled([
        pageA.locator('button:has-text("Aceitar")').click(),
        pageB.locator('button:has-text("Aceitar")').click()
      ]);

      await Promise.allSettled([
        pageA.waitForURL(/\/guincho\/(atendimento|dashboard|aceitar)\//i, { timeout: 15000 }),
        pageB.waitForURL(/\/guincho\/(atendimento|dashboard|aceitar)\//i, { timeout: 15000 })
      ]);

      const acceptedA = await acceptedState(pageA);
      const acceptedB = await acceptedState(pageB);

      expect(Number(acceptedA) + Number(acceptedB)).toBeLessThanOrEqual(1);
    } finally {
      await contextA.close();
      await contextB.close();
    }
  });
});
