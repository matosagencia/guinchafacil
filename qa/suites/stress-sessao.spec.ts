import { test, expect } from '@playwright/test';
import { loginAs, expectLoggedIn } from '../helpers/auth';
import { appPath } from '../helpers/paths';
import { seedAtendimentoGamboa } from '../helpers/seed';

// STRESS-SESSAO-001/002 — sessão sob condições adversas reais:
//   001: login simultâneo da MESMA conta em duas abas/contextos diferentes
//        — o sistema não invalida sessões concorrentes (SESSION_IDLE_TIMEOUT/
//        SESSION_ABSOLUTE_TIMEOUT em config.php não têm limite de 1 sessão
//        por usuário), então ambas devem continuar autenticadas.
//   002: cookie de sessão corrompido/adulterado batendo numa rota protegida
//        não deve derrubar o servidor (erro 500) nem vazar dado de outro
//        usuário — deve simplesmente redirecionar pro login.
test.describe('stress de sessão', () => {
  test('STRESS-SESSAO-001 | login simultâneo da mesma conta em duas abas mantém as duas autenticadas', async ({ browser }) => {
    const seeded = seedAtendimentoGamboa('pane-eletrica');
    expect(seeded.ok, 'seed falhou').toBeTruthy();

    const contextA = await browser.newContext();
    const contextB = await browser.newContext();
    const pageA = await contextA.newPage();
    const pageB = await contextB.newPage();

    try {
      await Promise.all([
        loginAs(pageA, seeded.cliente_email, 'test123'),
        loginAs(pageB, seeded.cliente_email, 'test123'),
      ]);
      await expectLoggedIn(pageA);
      await expectLoggedIn(pageB);

      // Ambas continuam válidas depois de uma navegação adicional — prova
      // que a segunda sessão não invalidou/derrubou a primeira.
      await pageA.goto(appPath('/cliente/dashboard'), { waitUntil: 'domcontentloaded' });
      await pageB.goto(appPath('/cliente/dashboard'), { waitUntil: 'domcontentloaded' });
      await expectLoggedIn(pageA);
      await expectLoggedIn(pageB);
    } finally {
      await contextA.close();
      await contextB.close();
    }
  });

  test('STRESS-SESSAO-002 | cookie de sessão adulterado é rejeitado sem erro 500', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
      await page.goto(appPath('/'), { waitUntil: 'domcontentloaded' });
      const cookies = await context.cookies();
      const sessionCookie = cookies.find((c) => /PHPSESSID|session/i.test(c.name));

      if (sessionCookie) {
        await context.addCookies([{ ...sessionCookie, value: 'qa-stress-cookie-adulterado-' + Date.now() }]);
      }

      const response = await page.goto(appPath('/cliente/dashboard'), { waitUntil: 'domcontentloaded' });
      // Nunca 500 — ou redireciona pro login (302/200 na página de login),
      // ou (menos provável) trata como sessão nova sem autenticação.
      expect(response?.status(), 'cookie adulterado não deveria gerar erro de servidor').toBeLessThan(500);
      await expect(page).toHaveURL(/\/login|\/$/i);
    } finally {
      await context.close();
    }
  });
});
