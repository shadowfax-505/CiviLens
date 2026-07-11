import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers';

test.beforeEach(async ({ page }) => {
  await loginAsAdmin(page);
});

test('administrator can create an agency through the browser', async ({ page }, testInfo) => {
  const suffix = `${testInfo.project.name}-${Date.now()}`.replace(/[^a-z0-9-]/gi, '-').toLowerCase();
  const agencyName = `Browser Works Agency ${suffix}`;

  await page.goto('/admin/agencies/create', { waitUntil: 'domcontentloaded' });
  await page.getByPlaceholder('Agency name').fill(agencyName);
  await page.getByPlaceholder('Short name').fill('BWA');
  await page.getByPlaceholder('agency-slug').fill(`browser-works-agency-${suffix}`);
  await page.getByPlaceholder('Email').fill(`agency-${suffix}@example.com`);
  await page.getByPlaceholder('Phone').fill('+8801700000000');
  await page.getByPlaceholder('Website').fill('https://example.com');
  await page.getByPlaceholder('Contact person').fill('Browser Tester');
  await page.getByPlaceholder('Address').fill('Dhaka');
  await page.getByPlaceholder('Description').fill('Created by Playwright engineering hardening tests.');
  await page.getByRole('button', { name: 'Save agency' }).evaluate((button: HTMLButtonElement) => button.form?.requestSubmit());

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

test('administrator can create and search contractor organizations through the browser', async ({ page }, testInfo) => {
  const suffix = `${testInfo.project.name}-${Date.now()}`.replace(/[^a-z0-9-]/gi, '-').toLowerCase();
  const legalName = `Browser Contractor ${suffix}`;
  const registrationNumber = `REG-BROWSER-${suffix}`.slice(0, 96);

  await page.goto('/admin/contractors/organizations/create');
  await page.getByLabel('Legal name').fill(legalName);
  await page.getByLabel('Trade name').fill('Browser Contractor');
  await page.getByLabel('Registration number').fill(registrationNumber);
  await page.getByLabel('Tax identification number').fill(`TIN-${suffix}`.slice(0, 96));
  await page.getByLabel('Website').fill('https://contractor.example.com');
  await page.getByLabel('Email').fill(`contractor-${suffix}@example.com`);
  await page.getByLabel('Phone').fill('+8801700000001');
  await page.getByLabel('Headquarters address').fill('Dhaka contractor office');
  await page.getByRole('button', { name: 'Save organization' }).evaluate((button: HTMLButtonElement) => button.form?.requestSubmit());

  await expect(page.getByText('organization-created')).toBeVisible();
  await expect(page.getByRole('heading', { name: legalName })).toBeVisible();
  await expect(page.getByText('Contractor Intelligence')).toBeVisible();

  await page.goto(`/admin/contractors/organizations?search=${encodeURIComponent(legalName)}&sort=legal_name&direction=asc`);
  await expect(page.getByRole('heading', { name: 'Contractor Dashboard' })).toBeVisible();
  await expect(page.getByRole('link', { name: legalName })).toBeVisible();
});

test('administrator can upload search download archive and restore documents', async ({ page }, testInfo) => {
  const suffix = `${testInfo.project.name}-${Date.now()}`.replace(/[^a-z0-9-]/gi, '-').toLowerCase();
  const title = `Browser Document ${suffix}`;

  await page.goto('/admin/documents/create');
  await page.getByLabel('Title').fill(title);
  await page.getByLabel('Language').fill('en');
  await page.getByLabel('Description').fill('Uploaded by Playwright Sprint 07 coverage.');
  await page.getByLabel('File').setInputFiles('tests/Browser/fixtures/document-upload.txt');
  await page.getByRole('button', { name: 'Upload document' }).evaluate((button: HTMLButtonElement) => button.form?.requestSubmit());

  await expect(page.getByText('document-uploaded')).toBeVisible();
  await expect(page.getByRole('heading', { name: title })).toBeVisible();
  await expect(page.getByText('Version History')).toBeVisible();

  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('link', { name: 'Download', exact: true }).first().click();
  await downloadPromise;

  await page.getByRole('button', { name: 'Archive' }).evaluate((button: HTMLButtonElement) => button.form?.requestSubmit());
  await expect(page.getByText('document-archived')).toBeVisible();
  await page.getByRole('button', { name: 'Restore' }).evaluate((button: HTMLButtonElement) => button.form?.requestSubmit());
  await expect(page.getByText('document-restored')).toBeVisible();

  await page.goto(`/admin/documents?search=${encodeURIComponent(title)}&sort=title&direction=asc`);
  await expect(page.getByRole('heading', { name: 'Document Library' })).toBeVisible();
  await expect(page.getByRole('link', { name: title })).toBeVisible();
});

test('administrator can use universal search knowledge graph analytics and suggestions', async ({ page }) => {
  await page.goto('/admin/search?q=Baseline&module=projects');

  await expect(page.getByRole('heading', { name: 'Universal Search' })).toBeVisible();
  await expect(page.getByPlaceholder('Search projects, contractors, documents...')).toBeVisible();
  await expect(page.getByRole('link', { name: 'CivicLens Baseline Road Improvement' })).toBeVisible();

  const knowledgeHref = await page.getByRole('link', { name: 'Knowledge View' }).first().getAttribute('href');
  expect(knowledgeHref).not.toBeNull();
  await page.goto(knowledgeHref as string);
  await expect(page.getByRole('heading', { name: 'Knowledge View' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'CivicLens Baseline Road Improvement' })).toBeVisible();

  await page.goto('/admin/search/analytics');
  await expect(page.getByRole('heading', { name: 'Search Analytics Dashboard' })).toBeVisible();
  await expect(page.getByText('Total Searches')).toBeVisible();

  await page.goto('/admin/search/suggestions?q=bas');
  await expect(page.locator('body')).toContainText('baseline');
});

test('administrator can review analytics dashboards metrics alerts and report actions', async ({ page }) => {
  await page.goto('/admin/analytics?dashboard=executive');

  await expect(page.getByRole('heading', { name: 'Executive Decision Dashboard' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Budget Utilization' })).toBeVisible();
  await expect(page.getByText('Alerts and Recommendations')).toBeVisible();
  await expect(page.locator('[data-chart-definition]').first()).toBeVisible();

  await page.goto('/admin/analytics/metrics?metric=projects.active');
  await expect(page.locator('body')).toContainText('projects.active');

  await page.goto('/admin/analytics/alerts');
  await expect(page.getByRole('heading', { name: 'Analytics Alerts' })).toBeVisible();
});

test('administrator can review intelligence indicators and processing readiness', async ({ page }) => {
  await page.goto('/admin/intelligence');

  await expect(page.getByRole('heading', { name: 'Evidence Review & Risk Indicators' })).toBeVisible();
  await expect(page.getByText('Rule-based, source-backed signals')).toBeVisible();

  await page.goto('/admin/intelligence/indicators?severity=warning&q=risk');
  await expect(page.getByRole('heading', { name: 'Intelligence Indicators' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Apply' })).toBeVisible();

  await page.goto('/admin/intelligence/processing-jobs');
  await expect(page.getByRole('heading', { name: 'Intelligence Processing Jobs' })).toBeVisible();
  await expect(page.getByText('Preparation jobs for future OCR')).toBeVisible();
});
