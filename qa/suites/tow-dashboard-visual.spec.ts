import { test, expect } from '@playwright/test';
import { guinchoCreds } from '../fixtures/test-data.fixture';
import { loginAs } from '../helpers/auth';
import { appPath } from '../helpers/paths';

/**
 * Gate visual do Nível 2 (plano §12) — painel do guincho: hero com
 * saudação/cobertura/GPS/disponibilidade, fila de ofertas e painel
 * Proof-of-Road reais (não texto técnico solto), tema verde do guincho.
 */
test.describe('Guincho — dashboard visual', () => {
  test.beforeEach(async ({ page }) => {
    const { email, password } = guinchoCreds();
    test.skip(!email || !password, 'Credenciais de teste de guincho não configuradas.');
    await loginAs(page, email, password);
    await page.goto(appPath('/guincho/dashboard'), { waitUntil: 'domcontentloaded' });
  });

  test('E2E-TOW-VIS-001 | page-head e hero com disponibilidade', async ({ page }) => {
    await expect(page.locator('header.page-head')).toBeVisible();
    await expect(page.locator('.tow-hero')).toBeVisible();
    // O checkbox real fica visualmente oculto por trás do slider
    // (padrão de toggle-switch em style.css: ".toggle-switch input {
    // display: none }"), então checamos presença/estado no DOM e a
    // visibilidade do afordance visível (.toggle-slider), não do input cru.
    await expect(page.locator('#toggleDisponivel')).toBeAttached();
    await expect(page.locator('.toggle-slider')).toBeVisible();
  });

  test('E2E-TOW-VIS-002 | fila de ofertas e Proof-of-Road presentes', async ({ page }) => {
    await expect(page.locator('text=Fila de ofertas')).toBeVisible();
    await expect(page.locator('text=Proof-of-Road')).toBeVisible();
  });

  test('E2E-TOW-VIS-003 | tema escuro do guincho aplicado, sem overflow em 360px', async ({ page }) => {
    await expect(page.locator('body.guincho')).toHaveCount(1);
    await page.setViewportSize({ width: 360, height: 800 });
    await page.waitForTimeout(300);
    // scrollWidth/clientWidth pode acusar "overflow" por elementos internos
    // do Leaflet (proxy de animação de zoom, sempre clipado por
    // .map-container { overflow: hidden }) ou pelo menu colapsado do
    // Bootstrap, que nunca são visíveis nem roláveis de verdade pelo
    // usuário. O teste real e observável é: a página consegue ser rolada
    // horizontalmente de fato?
    const scrollXAlcancado = await page.evaluate(() => {
      window.scrollTo(9999, 0);
      return window.scrollX;
    });
    expect(scrollXAlcancado, 'A página rolou horizontalmente de verdade (overflow real, visível ao usuário)').toBeLessThanOrEqual(1);
  });
});
