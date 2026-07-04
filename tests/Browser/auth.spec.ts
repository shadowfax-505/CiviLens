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

  await expect(page.locator('body')).toContainText('403');
});

test('dashboard remains usable on a mobile viewport', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await loginAsAdmin(page);

  await expect(page.getByRole('link', { name: 'Dashboard' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'CivicLens Dashboard' })).toBeVisible();
});
