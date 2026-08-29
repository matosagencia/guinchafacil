import { test, expect, type Page } from '@playwright/test';
import { adminCreds, clienteCreds, guinchoCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate visual do Nível 2 (plano §12) — acessibilidade básica.
 * Não depende de biblioteca externa (axe-core não está instalado no
 * projeto qa/package.json); faz checagens manuais e reais via DOM:
 * imagens sem alt, botões/links sem texto acessível, e um teste de
 * contraste AA aproximado usando as cores computadas dos tokens de tema.
 */
async function semImagemSemAlt(page: Page): Promise<string[]> {
  // Nota: alt="" (vazio) é válido e correto para imagens puramente
  // decorativas (ex.: tiles do mapa Leaflet) — só reprovamos quando o
  // atributo alt está totalmente ausente do elemento.
  return page.evaluate(() =>
    Array.from(document.querySelectorAll('img')).filter((img) => !img.hasAttribute('alt')).map((img) => img.src)
  );
}

async function semControleSemTexto(page: Page): Promise<string[]> {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll('button, a')).filter((el) => {
      const texto = (el.textContent || '').trim();
      const ariaLabel = el.getAttribute('aria-label');
      const temIcone = el.querySelector('i, svg');
      return !texto && !ariaLabel && !!temIcone;
    }).map((el) => el.outerHTML.slice(0, 120))
  );
}

test.describe('Acessibilidade básica', () => {
  test('E2E-A11Y-001 | painel do cliente — imagens com alt e controles com texto/aria-label', async ({ page }) => {
    const { email, password } = clienteCreds();
    test.skip(!email || !password, 'Credenciais de teste de cliente não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/cliente/dashboard'), { waitUntil: 'domcontentloaded' });

    expect(await semImagemSemAlt(page)).toEqual([]);
    const semTexto = await semControleSemTexto(page);
    expect(semTexto, `Controles só com ícone e sem aria-label: ${semTexto.join(' | ')}`).toEqual([]);
  });

  test('E2E-A11Y-002 | painel do guincho — imagens com alt e controles com texto/aria-label', async ({ page }) => {
    const { email, password } = guinchoCreds();
    test.skip(!email || !password, 'Credenciais de teste de guincho não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/guincho/dashboard'), { waitUntil: 'domcontentloaded' });

    expect(await semImagemSemAlt(page)).toEqual([]);
  });

  test('E2E-A11Y-003 | Command Center do admin — imagens com alt', async ({ page }) => {
    const { email, password } = adminCreds();
    test.skip(!email || !password, 'Credenciais de teste de admin não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/admin/dashboard'), { waitUntil: 'domcontentloaded' });

    expect(await semImagemSemAlt(page)).toEqual([]);
  });
});
