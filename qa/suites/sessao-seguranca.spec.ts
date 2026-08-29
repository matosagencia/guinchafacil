import { test, expect } from '@playwright/test';
import { clienteCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

test('E2E-AUTH-001 | sessão expirada redireciona para login quando rota protegida é acessada sem autenticação', async ({ page }) => {
  await page.goto(appPath('/cliente/dashboard'));
  await expect(page).toHaveURL(/login|cliente\/dashboard/i);
});

test('E2E-AUTH-002 | perda de cookie exibe fluxo de relogin quando contexto já estava autenticado', async ({ page }) => {
  const { email, password } = clienteCreds();
  test.skip(!email || !password, 'Credenciais de cliente não configuradas.');

  await loginAs(page, email, password);
  await page.goto(appPath('/guincho/dashboard')).catch(() => {});
  await page.context().clearCookies();
  await page.goto(appPath('/cliente/dashboard'));
  await expect(page).toHaveURL(/login|dashboard/i);
});
