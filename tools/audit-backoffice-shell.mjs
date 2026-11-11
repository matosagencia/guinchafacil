import fs from 'node:fs';
import { chromium } from 'playwright';

const base = process.env.GF_BASE_URL || 'http://localhost:8080';
const source = fs.readFileSync(new URL('../index.php', import.meta.url), 'utf8');
const getBlock = source.slice(source.indexOf("'GET' => ["), source.indexOf("'POST' => ["));
const routeRe = /['"](\/(?:admin|guincho|oficina|cliente)[^'"]*)['"]\s*=>/g;
const routes = [...getBlock.matchAll(routeRe)].map((m) => m[1])
  .filter((route) => !route.includes('{') && !route.includes('/api/') && !route.endsWith('/json'));
const uniqueRoutes = [...new Set(routes)].sort();
const executable = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const browser = await chromium.launch(fs.existsSync(executable) ? { headless: true, executablePath: executable } : { headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
await page.goto(`${base}/login`);
await page.locator('input[name=email]').fill(process.env.GF_ADMIN_EMAIL || 'admin@teste.local');
await page.locator('input[name=senha]').fill(process.env.GF_ADMIN_PASSWORD || 'teste123');
await page.locator('button[type=submit]').click();
await page.waitForTimeout(500);

const report = [];
for (const route of uniqueRoutes) {
  const result = { route };
  try {
    const response = await page.goto(`${base}${route}`, { waitUntil: 'domcontentloaded', timeout: 15000 });
    await page.waitForTimeout(250);
    result.http = response?.status() ?? null;
    result.url = page.url();
    result.data = await page.evaluate(() => {
      const visible = (selector) => [...document.querySelectorAll(selector)].filter((el) => getComputedStyle(el).display !== 'none' && el.getBoundingClientRect().height > 0).length;
      const text = document.body?.innerText || '';
      return {
        profile: document.body?.className || '',
        mojibake: /Ãƒ|Ã‚|Ã¢â‚¬|ï¿½|�/.test(text),
        legacyHeader: visible('.gf-legacy-bo-header'),
        headers: visible('.bo-header'),
        sidebars: visible('.bo-sidebar, .admin-shell-sidebar, .shell-ops-sidebar'),
        content: visible('.bo-content, .main-content, .shell-ops-workspace'),
        overflow: document.documentElement.scrollWidth > window.innerWidth,
        consoleErrors: window.__gfAuditErrors || []
      };
    });
  } catch (error) {
    result.error = String(error.message || error);
  }
  report.push(result);
  console.log(JSON.stringify(result));
}
fs.writeFileSync(new URL('../qa/backoffice-shell-audit.json', import.meta.url), JSON.stringify({ generatedAt: new Date().toISOString(), routes: report }, null, 2));
await browser.close();
