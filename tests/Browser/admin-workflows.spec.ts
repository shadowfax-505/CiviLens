import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers';

test.beforeEach(async ({ page }) => {
  await loginAsAdmin(page);
});

test('administrator can create an agency through the browser', async ({ page }, testInfo) => {
  const suffix = `${testInfo.project.name}-${Date.now()}`.replace(/[^a-z0-9-]/gi, '-').toLowerCase();
  const agencyName = `Browser Works Agency ${suffix}`;

  await page.goto('/admin/agencies/create');
  await page.getByPlaceholder('Agency name').fill(agencyName);
  await page.getByPlaceholder('Short name').fill('BWA');
  await page.getByPlaceholder('agency-slug').fill(`browser-works-agency-${suffix}`);
  await page.getByPlaceholder('Email').fill(`agency-${suffix}@example.com`);
  await page.getByPlaceholder('Phone').fill('+8801700000000');
  await page.getByPlaceholder('Website').fill('https://example.com');
  await page.getByPlaceholder('Contact person').fill('Browser Tester');
  await page.getByPlaceholder('Address').fill('Dhaka');
  await page.getByPlaceholder('Description').fill('Created by Playwright engineering hardening tests.');
  await page.getByRole('button', { name: 'Save agency' }).click();

  await expect(page.getByText('agency-created')).toBeVisible();
  await expect(page.getByRole('link', { name: agencyName })).toBeVisible();
});

test('agency listing supports search filtering sorting and pagination controls', async ({ page }) => {
  await page.goto('/admin/agencies?search=Ministry&status=active&sort=name');

  await expect(page.getByRole('heading', { name: 'Government Agencies' })).toBeVisible();
  await expect(page.getByText('Ministry of Planning')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Apply' })).toBeVisible();
});

test('procurement dashboard and tender filters render for administrators', async ({ page }) => {
  await page.goto('/admin/procurement/tenders?search=Baseline&sort=tender_number&direction=asc');

  await expect(page.getByRole('heading', { name: 'Procurement Dashboard' })).toBeVisible();
  await expect(page.getByText('CVL-TDR-2026-001')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Create tender' })).toBeVisible();
});
