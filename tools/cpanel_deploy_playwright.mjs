import { chromium } from 'playwright';
import { spawn } from 'node:child_process';
import fs from 'node:fs/promises';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';

const projectRoot = path.dirname(fileURLToPath(new URL('../package.json', import.meta.url)));
const envMap = await loadEnvFile(path.join(projectRoot, '.env'));
const host = envMap.CPANEL_HOST || 'https://ds3.hospedam.com:2083';
const loginUrl = envMap.CPANEL_LOGIN_URL || `${host}/frontend/jupiter/index.html?login=1`;
const chromeExe = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const chromeUserDataDir = 'C:\\Users\\sores\\AppData\\Local\\Google\\Chrome\\User Data';
const chromeProfileDir = 'Default';
const chromeCloneUserDataDir = 'C:\\Users\\sores\\AppData\\Local\\Temp\\soreservas-chrome-user-data';
const chromeDebugPort = 9222;
const accountUser = (envMap.FTP_ACCOUNT || envMap.CPANEL_USER || 'oawqoqml').toLowerCase();
const accountHome = envMap.CPANEL_ACCOUNT_HOME || `/home/${accountUser}/public_html`;
const deployBase = envMap.CPANEL_DEPLOY_BASE || `/home/${accountUser}`;
const deployArchiveName = envMap.CPANEL_DEPLOY_ARCHIVE || 'guinchafacil-deploy.zip';
const credentials = [
  ...(envMap.CPANEL_USER && envMap.CPANEL_PASS
    ? [{ username: envMap.CPANEL_USER, password: envMap.CPANEL_PASS }]
    : []),
  ...(envMap.FTP_ACCOUNT && envMap.FTP_PASS
    ? [{ username: envMap.FTP_ACCOUNT, password: envMap.FTP_PASS }]
    : []),
];
const deployEntries = [
  'index.php',
  'autoload.php',
  'config.php',
  '.htaccess',
  'robots.txt',
  'sitemap.xml',
  'politica-privacidade.php',
  'termos-servico.php',
  'gemini.js',
  'package.json',
  'package-lock.json',
  'playwright.config.js',
  'phpunit.xml',
  'public/assets',
  'src',
  'vendor',
  'database',
  'install',
  'prospeccao',
  'rotas',
  'files',
  'storage/cacert.pem',
  '.well-known',
];
const excludedDirNames = new Set([
  '.git',
  '.github',
  'node_modules',
  'qa',
  'tests',
  'tools',
  'logs',
  'doc',
  'deploy_min',
  'screenshot',
  'test-results',
  'public/uploads',
  'storage/private/uploads',
]);

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function loadEnvFile(filePath) {
  try {
    const text = await fs.readFile(filePath, 'utf8');
    const result = {};
    for (const rawLine of text.split(/\r?\n/)) {
      const line = rawLine.trim();
      if (!line || line.startsWith('#')) {
        continue;
      }
      const idx = line.indexOf('=');
      if (idx === -1) {
        continue;
      }
      const key = line.slice(0, idx).trim();
      let value = line.slice(idx + 1).trim();
      if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
        value = value.slice(1, -1);
      }
      result[key] = value;
    }
    return result;
  } catch {
    return {};
  }
}

function toBase64(buffer) {
  return Buffer.from(buffer).toString('base64');
}

async function textFromResponse(response) {
  const text = await response.text();
  try {
    return { json: JSON.parse(text), text };
  } catch {
    return { json: null, text };
  }
}

async function waitForCpanelReady(page) {
  for (let i = 0; i < 45; i += 1) {
    const title = await page.title().catch(() => '');
    const bodyText = await page.locator('body').innerText().catch(() => '');
    if (!/One moment, please/i.test(title) && !/Please wait while your request is being verified/i.test(bodyText)) {
      return;
    }
    await sleep(1000);
  }
}

async function waitForChromeDebugPort(port, timeoutMs = 30000) {
  const started = Date.now();
  while (Date.now() - started < timeoutMs) {
    try {
      const response = await fetch(`http://127.0.0.1:${port}/json/version`);
      if (response.ok) {
        return await response.json();
      }
    } catch {
      // Retry until Chrome finishes starting.
    }
    await sleep(1000);
  }

  throw new Error(`Chrome debug port ${port} did not become ready`);
}

async function runPowerShell(command) {
  return new Promise((resolve, reject) => {
    const ps = spawn('C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe', [
      '-NoProfile',
      '-ExecutionPolicy',
      'Bypass',
      '-Command',
      command,
    ], { stdio: 'inherit' });

    ps.on('error', reject);
    ps.on('exit', (code) => {
      if (code === 0) {
        resolve();
        return;
      }
      reject(new Error(`PowerShell exited with code ${code}`));
    });
  });
}

function launchChromeProfile() {
  return spawn(chromeExe, [
    `--remote-debugging-port=${chromeDebugPort}`,
    `--user-data-dir=${chromeUserDataDir}`,
    `--profile-directory=${chromeProfileDir}`,
    '--new-window',
    '--no-first-run',
    '--no-default-browser-check',
    '--disable-blink-features=AutomationControlled',
    '--disable-infobars',
  ], {
    detached: true,
    stdio: 'ignore',
  });
}

async function cloneChromeProfile() {
  await fs.rm(chromeCloneUserDataDir, { recursive: true, force: true });
  await fs.mkdir(path.dirname(chromeCloneUserDataDir), { recursive: true });
  console.log('copying chrome profile data');
  await fs.mkdir(chromeCloneUserDataDir, { recursive: true });
  await fs.cp(path.join(chromeUserDataDir, 'Local State'), path.join(chromeCloneUserDataDir, 'Local State'));

  const skipPath = (relativePath) => {
    const normalized = relativePath.replaceAll('\\', '/');
    return [
      '/Cache/',
      '/Code Cache/',
      '/GPUCache/',
      '/DawnGraphiteCache/',
      '/DawnWebGPUCache/',
      '/GrShaderCache/',
      '/ShaderCache/',
      '/Session Storage/',
      '/Sessions/',
      '/Service Worker/CacheStorage/',
      '/Service Worker/Database/',
      '/Network/Cookies',
      '/Network/Cookies-journal',
      '/Network/Network Persistent State',
      '/Network/Trust Tokens',
      '/Network/TransportSecurity',
    ].some((needle) => normalized.includes(needle));
  };

  const sourceProfileDir = path.join(chromeUserDataDir, chromeProfileDir);
  for (const entry of await fs.readdir(sourceProfileDir, { withFileTypes: true })) {
    const sourceEntry = path.join(sourceProfileDir, entry.name);
    const targetEntry = path.join(chromeCloneUserDataDir, chromeProfileDir, entry.name);
    if (entry.isDirectory()) {
      const walk = async (srcDir, dstDir) => {
        await fs.mkdir(dstDir, { recursive: true });
        for (const child of await fs.readdir(srcDir, { withFileTypes: true })) {
          const childSrc = path.join(srcDir, child.name);
          const childDst = path.join(dstDir, child.name);
          const rel = path.relative(sourceProfileDir, childSrc);
          if (skipPath(rel)) {
            continue;
          }
          if (child.isDirectory()) {
            await walk(childSrc, childDst);
            continue;
          }
          try {
            await fs.copyFile(childSrc, childDst);
          } catch (error) {
            console.log(`skip locked ${rel}: ${error?.code || error?.message || error}`);
          }
        }
      };
      await walk(sourceEntry, targetEntry);
      continue;
    }

    if (skipPath(entry.name)) {
      continue;
    }

    try {
      await fs.copyFile(sourceEntry, targetEntry);
    } catch (error) {
      console.log(`skip locked ${entry.name}: ${error?.code || error?.message || error}`);
    }
  }
  console.log('chrome profile copy complete');
}

async function tryLogin(page, username, password) {
  await page.goto(loginUrl, { waitUntil: 'load', timeout: 120000 });
  await waitForCpanelReady(page);
  console.log(`loaded ${await page.title().catch(() => '')} ${page.url()}`);

  for (let i = 0; i < 30; i += 1) {
    const userField = page.locator('input[name="user"], input#user, input[type="text"]').first();
    const passField = page.locator('input[name="pass"], input#pass, input[type="password"]').first();
    if (await userField.count() > 0 && await passField.count() > 0) {
      await userField.fill(username);
      await passField.fill(password);
      const form = passField.locator('xpath=ancestor::form').first();
      if (await form.count() > 0) {
        await form.evaluate((node) => node.submit());
      } else {
        await passField.press('Enter');
      }

      await sleep(5000);
      await waitForCpanelReady(page);
      console.log(`after submit ${await page.title().catch(() => '')} ${page.url()}`);
      const currentUrl = page.url();
      const bodyText = await page.locator('body').innerText().catch(() => '');
      if (!/invalid/i.test(bodyText) && !/incorrect/i.test(bodyText) && !/login/i.test(currentUrl)) {
        return true;
      }
    }

    await sleep(1000);
  }

  return false;
}

async function apiGet(page, urlPath) {
  return page.evaluate(async (url) => {
    const response = await fetch(url, { credentials: 'include' });
    return {
      status: response.status,
      text: await response.text(),
    };
  }, urlPath);
}

async function api2(page, func, params) {
  const query = new URLSearchParams({
    cpanel_jsonapi_user: accountUser,
    cpanel_jsonapi_apiversion: '2',
    cpanel_jsonapi_module: 'Fileman',
    cpanel_jsonapi_func: func,
    ...params,
  });
  return apiGet(page, `${host}/json-api/cpanel?${query.toString()}`);
}

async function ensureRemoteDir(page, dir) {
  if (dir === accountHome) {
    return;
  }

  const relative = path.posix.relative('/home/' + accountUser, dir);
  const segments = relative.split('/').filter(Boolean);
  let current = `/home/${accountUser}`;
  for (const segment of segments) {
    current = `${current}/${segment}`;
    const exists = await apiGet(page, `${host}/execute/Fileman/get_file_information?path=${encodeURIComponent(current)}`);
    if (!/\"status\"\s*:\s*1/.test(exists.text)) {
      const parent = path.posix.dirname(current);
      const name = path.posix.basename(current);
      const create = await api2(page, 'mkdir', {
        path: parent,
        name,
        permissions: '0755',
      });
      if (!/\"result\"\s*:\s*1/.test(create.text) && !/already exists/i.test(create.text)) {
        throw new Error(`mkdir failed for ${current}: ${create.text}`);
      }
    }
  }
}

async function collectDeployEntries() {
  const selected = [];

  async function walk(sourceRoot, relRoot = '') {
    for (const entry of await fs.readdir(sourceRoot, { withFileTypes: true })) {
      const sourcePath = path.join(sourceRoot, entry.name);
      const relPath = relRoot ? path.posix.join(relRoot, entry.name) : entry.name;
      const stats = await fs.stat(sourcePath);
      const normalized = relPath.replaceAll('\\', '/').replace(/^\.\/?/, '');
      if (
        normalized === '.env'
        || normalized === '.env.local'
        || normalized === '.env.backup'
        || normalized === '.ftpquota'
        || normalized === '.cpanel.yml'
        || normalized === 'deploy_min'
        || normalized === 'public/uploads'
        || normalized.startsWith('public/uploads/')
        || normalized === 'storage/private/uploads'
        || normalized.startsWith('storage/private/uploads/')
        || normalized === 'node_modules'
        || normalized.startsWith('node_modules/')
        || normalized === 'qa'
        || normalized.startsWith('qa/')
        || normalized === 'tests'
        || normalized.startsWith('tests/')
        || normalized === 'tools'
        || normalized.startsWith('tools/')
        || normalized === 'logs'
        || normalized.startsWith('logs/')
        || normalized === 'doc'
        || normalized.startsWith('doc/')
        || normalized === 'screenshot'
        || normalized.startsWith('screenshot/')
        || normalized === 'test-results'
        || normalized.startsWith('test-results/')
        || normalized.endsWith('.zip')
        || normalized.endsWith('.webm')
        || normalized.endsWith('.trace')
        || (normalized.endsWith('.txt') && normalized !== 'robots.txt')
        || normalized.endsWith('.csv')
        || (normalized.endsWith('.md') && !normalized.startsWith('storage/'))
        || (stats.isDirectory() && excludedDirNames.has(normalized))
      ) {
        continue;
      }

      if (entry.isDirectory()) {
        selected.push({ source: sourcePath, target: relPath, directory: true });
        await walk(sourcePath, relPath);
        continue;
      }

      selected.push({ source: sourcePath, target: relPath, directory: false });
    }
  }

  for (const entry of deployEntries) {
    const sourcePath = path.join(projectRoot, entry);
    const exists = await fs.stat(sourcePath).catch(() => null);
    if (!exists) {
      continue;
    }
    if (exists.isDirectory()) {
      await walk(sourcePath, entry);
    } else {
      selected.push({ source: sourcePath, target: entry, directory: false });
    }
  }

  return selected;
}

async function buildDeployArchive() {
  const stagingRoot = await fs.mkdtemp(path.join(os.tmpdir(), 'guinchafacil-deploy-'));
  const stagingPublicHtml = path.join(stagingRoot, 'public_html');
  await fs.mkdir(stagingPublicHtml, { recursive: true });

  const entries = await collectDeployEntries();
  for (const entry of entries) {
    const targetPath = path.join(stagingPublicHtml, entry.target);
    if (entry.directory) {
      await fs.mkdir(targetPath, { recursive: true });
      continue;
    }
    await fs.mkdir(path.dirname(targetPath), { recursive: true });
    await fs.copyFile(entry.source, targetPath);
  }

  const archivePath = path.join(stagingRoot, deployArchiveName);
  const command = [
    '$ErrorActionPreference = "Stop";',
    `$source = "${stagingPublicHtml.replaceAll('"', '""')}";`,
    `$archive = "${archivePath.replaceAll('"', '""')}";`,
    'if (Test-Path $archive) { Remove-Item -LiteralPath $archive -Force }',
    'Compress-Archive -Path (Join-Path $source "*") -DestinationPath $archive -CompressionLevel Optimal -Force',
  ].join(' ');
  await runPowerShell(command);

  return { archivePath, stagingRoot };
}

function buildAuthHeaders(kind = 'token') {
  const headers = {};
  const user = accountUser;
  const token = envMap.CPANELAPI || '';
  const password = envMap.CPANEL_PASS || envMap.FTP_PASS || '';

  if (kind === 'token' && token) {
    headers.Authorization = `cpanel ${user}:${token}`;
    return headers;
  }

  if (password) {
    headers.Authorization = `Basic ${Buffer.from(`${user}:${password}`).toString('base64')}`;
  }

  return headers;
}

async function uploadFileToCpanel(pageUrl, targetDir, filePath, fileName) {
  const bytes = await fs.readFile(filePath);
  const form = new FormData();
  form.append('dir', targetDir);
  form.append('file-1', new Blob([bytes]), fileName);

  const authKinds = envMap.CPANELAPI ? ['token', 'basic'] : ['basic'];
  let lastError = null;

  for (const kind of authKinds) {
    const response = await fetch(`${pageUrl}/execute/Fileman/upload_files`, {
      method: 'POST',
      headers: buildAuthHeaders(kind),
      body: form,
    }).catch((error) => {
      lastError = error;
      return null;
    });

    if (!response) {
      continue;
    }

    const text = await response.text();
    if (/\"status\"\s*:\s*1/.test(text)) {
      return text;
    }
    lastError = new Error(text);
  }

  throw lastError || new Error(`Upload failed for ${fileName}`);
}

async function callCpanelApi(pageUrl, pathSuffix, query, authKindOrder = null) {
  const authKinds = authKindOrder ?? (envMap.CPANELAPI ? ['token', 'basic'] : ['basic']);
  let lastError = null;

  for (const kind of authKinds) {
    const response = await fetch(`${pageUrl}${pathSuffix}?${new URLSearchParams(query).toString()}`, {
      headers: buildAuthHeaders(kind),
    }).catch((error) => {
      lastError = error;
      return null;
    });

    if (!response) {
      continue;
    }

    const text = await response.text();
    if (/\"result\"\s*:\s*1/.test(text) || /\"status\"\s*:\s*1/.test(text)) {
      return text;
    }
    lastError = new Error(text);
  }

  throw lastError || new Error('cPanel API call failed');
}

async function main() {
  const { archivePath, stagingRoot } = await buildDeployArchive();
  const deployScriptPath = path.join(stagingRoot, '__deploy_extract.php');
  const deployScript = `<?php
declare(strict_types=1);
$key = ${JSON.stringify(envMap.CPANEL_DEPLOY_KEY || envMap.CPANELAPI || 'guinchafacil-deploy')};
$expected = hash('sha256', $key);
$provided = (string)($_GET['key'] ?? '');
if (!hash_equals($expected, $provided)) {
    http_response_code(403);
    echo 'forbidden';
    exit;
}
$home = dirname(__DIR__);
$archive = $home . DIRECTORY_SEPARATOR . ${JSON.stringify(path.basename(archivePath))};
$zip = new ZipArchive();
if ($zip->open($archive) !== true) {
    http_response_code(500);
    echo 'unable to open archive';
    exit;
}
if (!$zip->extractTo($home)) {
    http_response_code(500);
    echo 'extract failed';
    exit;
}
$zip->close();
@unlink($archive);
@unlink(__FILE__);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
`;
  await fs.writeFile(deployScriptPath, deployScript, 'utf8');

  await uploadFileToCpanel(host, deployBase, archivePath, path.basename(archivePath));
  await uploadFileToCpanel(host, `${accountHome}`, deployScriptPath, '__deploy_extract.php');

  const deployUrl = `${envMap.APP_URL || 'https://guinchafacil.com.br'}/__deploy_extract.php?key=${encodeURIComponent(envMap.CPANEL_DEPLOY_KEY || envMap.CPANELAPI || 'guinchafacil-deploy')}`;
  const deployResponse = await fetch(deployUrl, { method: 'GET' });
  const deployText = await deployResponse.text();
  if (!deployResponse.ok || !/\"ok\"\s*:\s*true/.test(deployText)) {
    throw new Error(`Remote extraction failed: ${deployText.slice(0, 1000)}`);
  }

  const verify = await fetch(`${envMap.APP_URL || 'https://guinchafacil.com.br'}/`, { method: 'GET' });
  console.log(`verify ${verify.status}`);
  console.log((await verify.text()).slice(0, 500));
  console.log('DEPLOY_OK');
  await fs.rm(stagingRoot, { recursive: true, force: true });
}

main().catch(async (error) => {
  console.error(error?.stack || error?.message || String(error));
  process.exitCode = 1;
});
