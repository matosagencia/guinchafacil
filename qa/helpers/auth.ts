import { expect, type Page } from '@playwright/test';
import { appPath } from './paths';

export async function fillFirstAvailable(page: Page, selectors: string[], value: string): Promise<void> {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (await locator.count()) {
      await locator.fill(value);
      return;
    }
  }
  throw new Error(`Nenhum seletor disponível para preenchimento: ${selectors.join(', ')}`);
}

export async function clickFirstAvailable(page: Page, selectors: string[]): Promise<void> {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (await locator.count()) {
      await locator.click();
      return;
    }
  }
  throw new Error(`Nenhum seletor disponível para clique: ${selectors.join(', ')}`);
}

export async function loginAs(page: Page, email: string, password: string): Promise<void> {
  await page.goto(appPath('/login'), { waitUntil: 'domcontentloaded' });
  await fillFirstAvailable(page, ['input[name="email"]', '#email'], email);
  await fillFirstAvailable(page, ['input[name="senha"]', 'input[name="password"]', '#password'], password);
  await clickFirstAvailable(page, ['button[type="submit"]', 'button:has-text("Entrar")']);
  // 30s bastava em runs isolados, mas no gate completo (150 testes, 1 worker,
  // ~25-30min totais) o servidor local (XAMPP) fica sob contenção de CPU/IO
  // sustentada, e logins que rodam mais tarde na fila (ex: projeto firefox)
  // já foram vistos estourar 30s sem nenhum problema real de login — só
  // lentidão acumulada do ambiente. 60s dá folga sem mascarar uma falha real
  // (um login realmente quebrado ainda estoura isso).
  await page.waitForURL(/\/(cliente|guincho|admin)(\/|$)|\/dashboard/i, { timeout: 60000 });
  await page.waitForLoadState('domcontentloaded');
}

export async function expectLoggedIn(page: Page): Promise<void> {
  await expect(page).toHaveURL(/dashboard|cliente|guincho|admin/i);
}
