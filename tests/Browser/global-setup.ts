import { execFileSync } from 'node:child_process';
import { closeSync, existsSync, openSync } from 'node:fs';
import path from 'node:path';

const databasePath = path.resolve('database/browser.sqlite');

function artisan(args: string[]) {
  execFileSync('php', ['artisan', ...args], {
    stdio: 'inherit',
    env: {
      ...process.env,
      APP_ENV: 'testing',
      APP_DEBUG: 'true',
      APP_URL: 'http://127.0.0.1:8010',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: databasePath,
      CACHE_STORE: 'array',
      SESSION_DRIVER: 'database',
      QUEUE_CONNECTION: 'sync',
      MAIL_MAILER: 'array',
    },
  });
}

export default async function globalSetup() {
  if (!existsSync(databasePath)) {
    closeSync(openSync(databasePath, 'w'));
  }

  artisan(['config:clear']);
  artisan(['migrate:fresh', '--seed', '--force']);
  artisan(['db:seed', '--class=Tests\\Support\\BrowserVisibilitySeeder', '--force']);
}
