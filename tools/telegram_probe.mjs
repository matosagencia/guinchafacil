import { chromium } from 'playwright';
import path from 'node:path';
import fs from 'node:fs';

const localAppData = process.env.LOCALAPPDATA || 'C:\\Users\\sores\\AppData\\Local';
const chromeUserData = path.join(localAppData, 'Google', 'Chrome', 'User Data');
const chromeDefault = path.join(chromeUserData, 'Default');
const tempRoot = path.join(process.env.TEMP || 'C:\\Users\\sores\\AppData\\Local\\Temp', 'telegram-chrome-profile');
const chromeExe = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

function copyIfExists(relativePath) {
  const source = path.join(chromeDefault, relativePath);
  const target = path.join(tempRoot, 'Default', relativePath);
  if (!fs.existsSync(source)) return;
  fs.mkdirSync(path.dirname(target), { recursive: true });
  try {
    fs.cpSync(source, target, { recursive: true, force: true });
  } catch (error) {
    console.warn(`skip ${relativePath}: ${error.message}`);
  }
}

fs.rmSync(tempRoot, { recursive: true, force: true });
fs.mkdirSync(path.join(tempRoot, 'Default'), { recursive: true });
if (fs.existsSync(path.join(chromeUserData, 'Local State'))) {
  fs.copyFileSync(path.join(chromeUserData, 'Local State'), path.join(tempRoot, 'Local State'));
}
[
  'Preferences',
  'Secure Preferences',
  'Cookies',
  'Cookies-journal',
  'Local Storage',
  'IndexedDB',
  'Session Storage',
  'Service Worker',
  'WebStorage',
  'Sessions',
].forEach(copyIfExists);

const context = await chromium.launchPersistentContext(tempRoot, {
  headless: true,
  executablePath: chromeExe,
  args: ['--profile-directory=Default'],
});

const page = context.pages()[0] || await context.newPage();
await page.goto('https://web.telegram.org/k/', { waitUntil: 'domcontentloaded', timeout: 60000 });
await page.waitForTimeout(30000);
const shot = path.join(process.env.TEMP || 'C:\\Users\\sores\\AppData\\Local\\Temp', 'telegram-web-probe.png');
await page.screenshot({ path: shot, fullPage: true });
const counts = await page.evaluate(() => ({
  inputs: document.querySelectorAll('input').length,
  buttons: document.querySelectorAll('button').length,
  textareas: document.querySelectorAll('textarea').length,
  editables: document.querySelectorAll('[contenteditable="true"]').length,
  images: document.querySelectorAll('img').length,
  titles: Array.from(document.querySelectorAll('title')).map((n) => n.textContent),
  bodyText: document.body ? (document.body.innerText || '').slice(0, 1000) : '',
}));

console.log(JSON.stringify({
  title: await page.title(),
  url: page.url(),
  shot,
  counts,
}, null, 2));

await context.close();
