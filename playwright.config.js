const { defineConfig } = require('@playwright/test');
module.exports = defineConfig({
  testDir: './tests/e2e',
  use: { headless: true, launchOptions: { executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' } },
});
