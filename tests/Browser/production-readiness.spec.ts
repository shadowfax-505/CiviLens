import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers';

test('production health endpoint returns public-safe readiness checks', async ({ page }) => {
  const response = await page.goto('/healthz');

  expect(response?.ok()).toBeTruthy();
  await expect(page.locator('body')).toContainText('"status":"ok"');
  await expect(page.locator('body')).toContainText('"database"');
  await expect(page.locator('body')).not.toContainText('APP_KEY');
  await expect(page.locator('body')).not.toContainText('password');
});

test('intelligence dashboard exposes deterministic engine workflow accessibly', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/admin/intelligence');

  await expect(page.getByRole('heading', { name: 'Evidence Review & Risk Indicators' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Run Civic Integrity Engine' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Civic Integrity Engine' })).toBeVisible();
  await expect(page.getByText('Deterministic, evidence-backed analysis')).toBeVisible();

  await page.keyboard.press('Tab');
  await expect(page.locator(':focus')).toBeVisible();
});

test('intelligence dashboard remains usable on mobile and dark mode', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.addInitScript(() => document.documentElement.classList.add('dark'));
  await loginAsAdmin(page);
  await page.goto('/admin/intelligence');

  await expect(page.getByRole('heading', { name: 'Evidence Review & Risk Indicators' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Run Civic Integrity Engine' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Civic Integrity Engine' })).toBeVisible();
});
