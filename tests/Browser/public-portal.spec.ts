import { expect, test } from '@playwright/test';
import { loginAsAdmin, registerCitizen } from './helpers';

test('guest can browse public portal projects procurement documents and search', async ({ page }) => {
  await page.goto('/public');
  await expect(page.getByText('Civic Intelligence Platform')).toBeVisible();

  await page.goto('/public/projects', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: 'Public Projects' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'CivicLens Baseline Road Improvement' })).toBeVisible();

  await page.getByRole('link', { name: 'CivicLens Baseline Road Improvement' }).click();
  await expect(page.getByRole('heading', { name: 'CivicLens Baseline Road Improvement', exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Procurement' })).toBeVisible();

  await page.goto('/public/procurement');
  await expect(page.getByRole('heading', { name: 'Public Procurement' })).toBeVisible();
  await expect(page.getByText('CVL-TDR-2026-001')).toBeVisible();

  await page.goto('/public/documents');
  await expect(page.getByRole('heading', { name: 'Public Documents' })).toBeVisible();

  await page.goto('/public/search?q=Baseline');
  await expect(page.getByRole('heading', { name: 'Public Search' })).toBeVisible();
  await expect(page.getByText('CivicLens Baseline Road Improvement')).toBeVisible();
});

test('guest can access the resilient published-project portfolio map', async ({ page }) => {
  await page.goto('/public/projects', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('region', { name: 'Published project locations' })).toBeVisible();
  await expect(page.locator('[data-portfolio-map-canvas]')).toBeVisible();
  await expect(page.getByRole('status')).toContainText(/mapped projects|Loading mapped projects|unavailable/i);
});

test('registered citizen can submit and track a public report', async ({ page }, testInfo) => {
  const email = `public-citizen-${testInfo.project.name}-${Date.now()}@example.com`;

  await registerCitizen(page, email);
  await page.goto('/public/reports/create');
  await page.getByLabel('Title').fill('Browser safety concern report');
  await page.getByLabel('Description').fill('The roadside barrier near the public project needs a safety inspection soon.');
  await page.getByLabel('Location').fill('Ward 7 public road');
  await page.getByRole('button', { name: 'Submit' }).click();

  await expect(page.getByText('Your report was submitted for review.')).toBeVisible();
  await expect(page.getByRole('link', { name: /Browser safety concern report/ })).toBeVisible();

  await page.goto('/citizen/reports');
  await expect(page.getByRole('link', { name: /Browser safety concern report/ })).toBeVisible();
});

test('administrator can moderate a submitted citizen report', async ({ page }, testInfo) => {
  const email = `moderation-citizen-${testInfo.project.name}-${Date.now()}@example.com`;
  const reportTitle = `Browser moderation report ${testInfo.project.name}-${Date.now()}`;

  await registerCitizen(page, email);
  await page.goto('/public/reports/create');
  await page.getByLabel('Title').fill(reportTitle);
  await page.getByLabel('Description').fill('This report is created so an administrator can moderate it in the browser.');
  await page.getByRole('button', { name: 'Submit' }).click();

  await page.context().clearCookies();

  await loginAsAdmin(page);
  await page.goto('/admin/citizen-reports');
  await page.getByRole('link', { name: new RegExp(reportTitle) }).click();
  await page.getByLabel('Status').selectOption({ label: 'Accepted' });
  await page.getByLabel('Moderation Notes').fill('Accepted from Playwright moderation.');
  await page.getByRole('button', { name: 'Update Status' }).click();

  await expect(page.getByText('Citizen report status updated.')).toBeVisible();
  await expect(page.getByText('Status Changed')).toBeVisible();
});
