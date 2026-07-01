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

test('version endpoint returns deploy metadata without secrets', async ({ page }) => {
  const response = await page.goto('/version');

  expect(response?.ok()).toBeTruthy();
  await expect(page.locator('body')).toContainText('"app":"CivicLens"');
  await expect(page.locator('body')).toContainText('"version"');
  await expect(page.locator('body')).not.toContainText('APP_KEY');
});

test('executive command center summarizes production readiness', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/dashboard');

  await expect(page.getByRole('heading', { name: 'CivicLens Dashboard' })).toBeVisible();
  await expect(page.getByText('Executive Command Center')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'System Health' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Risk Summary' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Recent Integrity Runs' })).toBeVisible();
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

test('rule management console supports dry-run review', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/admin/intelligence/rules');

  await expect(page.getByText('Rule Management Console')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Deterministic Integrity Rules' })).toBeVisible();
  await expect(page.getByText('Dry Run').first()).toBeVisible();
});

test('analytics reports screen exposes downloadable formats', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/admin/analytics/reports');

  await expect(page.getByRole('heading', { name: 'Analytics Reports' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Queue CSV' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Queue Excel' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Queue PDF' })).toBeVisible();
});

test('intelligence dashboard remains usable on mobile and dark mode', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.addInitScript(() => document.documentElement.classList.add('dark'));
  await loginAsAdmin(page);
  await page.goto('/admin/intelligence');

  await expect(page.getByRole('heading', { name: 'Evidence Review & Risk Indicators' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Run Civic Integrity Engine' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Civic Integrity Engine' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Integrity Timeline' })).toBeVisible();
});
