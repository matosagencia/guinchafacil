const { defineConfig } = require('@playwright/test');

const headless = process.env.PW_HEADLESS === 'true';
const slowMo = Number(process.env.PW_SLOWMO || '50');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://localhost:8080',
    headless,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure'
  },
  projects: [
    {
      name: 'chromium',
      use: {
        browserName: 'chromium',
        launchOptions: {
          executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
          slowMo
        }
      }
    },
    {
      name: 'firefox',
      use: {
        browserName: 'firefox',
        launchOptions: { slowMo }
      }
    }
  ]
});
