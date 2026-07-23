import { defineConfig, devices } from '@playwright/test';

const isCi = Boolean(process.env['CI']);

export default defineConfig({
  testDir: './e2e',
  fullyParallel: true,
  forbidOnly: isCi,
  retries: isCi ? 2 : 0,
  workers: isCi ? 2 : undefined,
  timeout: 45_000,
  expect: {
    timeout: 8_000,
  },
  outputDir: '../output/playwright/test-results',
  reporter: [['list'], ['html', { outputFolder: '../output/playwright/report', open: 'never' }]],
  use: {
    baseURL: 'http://127.0.0.1:4300',
    locale: 'es-ES',
    timezoneId: 'Europe/Madrid',
    colorScheme: 'light',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium-desktop',
      testMatch: /design-lab\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 1000 },
      },
    },
    {
      name: 'chromium-mobile',
      testMatch: /design-lab\.spec\.ts/,
      use: {
        ...devices['Pixel 7'],
      },
    },
    {
      name: 'staff-chromium-desktop',
      testMatch: /staff-portal\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        baseURL: 'http://127.0.0.1:4302',
        viewport: { width: 1440, height: 1000 },
      },
    },
    {
      name: 'staff-chromium-mobile',
      testMatch: /staff-portal\.spec\.ts/,
      use: {
        ...devices['Pixel 7'],
        baseURL: 'http://127.0.0.1:4302',
      },
    },
    {
      name: 'patient-chromium-desktop',
      testMatch: /patient-portal\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        baseURL: 'http://127.0.0.1:4301',
        viewport: { width: 1440, height: 1000 },
      },
    },
    {
      name: 'patient-chromium-mobile',
      testMatch: /patient-portal\.spec\.ts/,
      use: {
        ...devices['Pixel 7'],
        baseURL: 'http://127.0.0.1:4301',
      },
    },
  ],
  webServer: [
    {
      command: 'npm.cmd run start:design -- --host 127.0.0.1 --port 4300',
      url: 'http://127.0.0.1:4300',
      reuseExistingServer: !isCi,
      timeout: 120_000,
    },
    {
      command: 'npm.cmd run start:staff -- --host 127.0.0.1 --port 4302',
      url: 'http://127.0.0.1:4302',
      reuseExistingServer: !isCi,
      timeout: 120_000,
    },
    {
      command: 'npm.cmd run start:patient -- --host 127.0.0.1 --port 4301',
      url: 'http://127.0.0.1:4301',
      reuseExistingServer: !isCi,
      timeout: 120_000,
    },
  ],
});
