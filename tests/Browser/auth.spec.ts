import { expect, test } from '@playwright/test';
import { loginAsAdmin, registerCitizen } from './helpers';

test('login shows the dashboard for the seeded administrator', async ({ page }) => {
  await loginAsAdmin(page);
  await expect(page.getByText('CivicLens v1.0 RC1 is ready for production-readiness validation')).toBeVisible();
});

test('registration creates a citizen account and protects admin routes', async ({ page }, testInfo) => {
  const email = `citizen-${testInfo.project.name}-${Date.now()}@example.com`;

  await registerCitizen(page, email);
  await page.goto('/admin/agencies');

  await expect(page.getByRole('heading', { name: 'This workspace is protected' })).toBeVisible();
  await expect(page.locator('body')).toContainText('permission-aware');
});

test('dashboard remains usable on a mobile viewport', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await loginAsAdmin(page);

  await expect(page.getByRole('link', { name: 'Dashboard' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'CivicLens Dashboard' })).toBeVisible();
});

test('profile appearance setting persists dark mode', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/profile');
  await page.getByLabel('Dark').check();
  await page.getByRole('button', { name: 'Save preferences' }).click();

  await expect(page.getByText('notifications-updated')).toBeVisible();
  await expect(page.locator('html')).toHaveClass(/dark/);

  await page.goto('/dashboard');
  await expect(page.locator('html')).toHaveClass(/dark/);
});

test('avatar picker displays the selected file', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/profile');
  await page.locator('input[name="avatar"]').setInputFiles({
    name: 'avatar.png',
    mimeType: 'image/png',
    buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9s4a8AAAAASUVORK5CYII=', 'base64'),
  });

  await expect(page.getByText('avatar.png')).toBeVisible();
});
