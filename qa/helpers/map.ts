import { expect, type Locator, type Page } from '@playwright/test';

export async function waitForLeafletMap(page: Page): Promise<Locator> {
  await page.waitForSelector('.leaflet-container', { timeout: 15000 });
  const map = page.locator('#map');
  await expect(map).toBeVisible();
  return map;
}

export async function clickMapPoint(map: Locator, x: number, y: number, waitMs = 1000): Promise<void> {
  await map.click({ position: { x, y } });
  await map.page().waitForTimeout(waitMs);
}
