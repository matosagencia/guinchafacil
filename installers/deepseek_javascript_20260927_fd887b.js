const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 60000,
  fullyParallel: false,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: 'http://localhost:8080',
    screenshot: 'on',
    trace: 'retain-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: true,
    // Simula geolocalização em cima da Gamboa (onde está a oficina Barão Car)
    geolocation: { latitude: -22.897, longitude: -43.187 },
    permissions: ['geolocation'],
  },
});