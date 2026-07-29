import { defineConfig, devices } from '@playwright/test';

const browserDatabase = 'database/browser.sqlite';
const browserEnv = {
  ...process.env,
  APP_ENV: 'testing',
  APP_DEBUG: 'true',
  APP_URL: 'http://127.0.0.1:8010',
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: browserDatabase,
  CACHE_STORE: 'array',
  SESSION_DRIVER: 'database',
  QUEUE_CONNECTION: 'sync',
  MAIL_MAILER: 'array',
};

const browserEnvironment = (port: number, enabled: boolean) => ({
  ...browserEnv,
  APP_URL: `http://127.0.0.1:${port}`,
  CIVICLENS_EARTH_JOURNEY_ENABLED: enabled ? 'true' : 'false',
});

export default defineConfig({
  testDir: './tests/Browser',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never' }]],
  globalSetup: './tests/Browser/global-setup.ts',
  use: {
    baseURL: 'http://127.0.0.1:8010',
    trace: 'on-first-retry',
    video: 'retain-on-failure',
    screenshot: {
      mode: 'only-on-failure',
      fullPage: true,
    },
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'mobile-chrome',
      use: { ...devices['Pixel 5'] },
    },
  ],
  webServer: [
    {
      command: 'php artisan serve --host=127.0.0.1 --port=8010',
      url: 'http://127.0.0.1:8010/up',
      reuseExistingServer: false,
      timeout: 120_000,
      env: browserEnvironment(8010, true),
    },
    {
      command: 'php artisan serve --host=127.0.0.1 --port=8011',
      url: 'http://127.0.0.1:8011/up',
      reuseExistingServer: false,
      timeout: 120_000,
      env: browserEnvironment(8011, false),
    },
  ],
});
