import { test, expect } from '@playwright/test';
import { clienteCreds } from '../fixtures/test-data.fixture';
import { expectLoggedIn, loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

test('E2E-SMK-001 | login carrega', async ({ page }) => {
  await page.goto(appPath('/login'));
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.locator('body')).toContainText(/login|entrar|email/i);
});

test('E2E-SMK-002 | login de cliente funciona quando credenciais existem', async ({ page }) => {
  const { email, password } = clienteCreds();
  test.skip(!email || !password, 'Credenciais de teste não configuradas.');
  await loginAs(page, email, password);
  await expectLoggedIn(page);
});
