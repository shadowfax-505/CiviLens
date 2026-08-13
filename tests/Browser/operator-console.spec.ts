import { expect, test } from '@playwright/test';
import { loginAsAdmin, loginAsCitizen } from './helpers';

/**
 * The operator console exists because every failure this pipeline has had was
 * invisible on every screen. These check that the numbers an operator needs are
 * actually rendered, that the review queue never quietly turns a hesitation into
 * a label, and that none of it is reachable without the source policy.
 */

test.describe('operator console', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('registry reports the counts that would have surfaced past failures', async ({ page }) => {
    await page.goto('/admin/sources', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: 'Is acquisition healthy?' })).toBeVisible();
    // Quarantine stopped the pipeline for a full cycle with nothing on screen
    // saying so, and a failing endpoint reported healthy while its cursor sat
    // still. Both are now countable at a glance.
    await expect(page.getByText('Quarantined', { exact: false })).toBeVisible();
    await expect(page.getByText('Failed jobs', { exact: false })).toBeVisible();
    await expect(page.getByText('Last crawl', { exact: false })).toBeVisible();
  });

  test('findings report counts and never characterise them', async ({ page }) => {
    await page.goto('/admin/sources/findings', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: 'What the sources have published' })).toBeVisible();

    // The wording guardrail is the whole point of this screen: an amendment is
    // routine, and a page that implied otherwise would be making an accusation
    // out of a count.
    const body = await page.locator('body').innerText();
    expect(body.toLowerCase()).not.toContain('suspicious');
    expect(body.toLowerCase()).not.toContain('irregular');
    expect(body.toLowerCase()).not.toContain('corrupt');
  });

  test('extraction shows abstention rather than folding it into a total', async ({ page }) => {
    await page.goto('/admin/sources/extraction', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: 'What the pipeline could read' })).toBeVisible();

    // A page counted as extracted is not a page that was read. Whether this
    // environment has extracted anything varies, and an absence of data must
    // read as an absence rather than as a page of zeroes.
    const abstained = page.getByText('Abstained', { exact: false });

    if (await abstained.count() > 0) {
      await expect(abstained.first()).toBeVisible();
      await expect(page.getByText('carry no vouched text')).toBeVisible();
    } else {
      await expect(page.getByText('Nothing extracted yet')).toBeVisible();
    }
  });

  test('review queue states the minimum a group needs before it certifies anything', async ({ page }) => {
    await page.goto('/admin/sources/review', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: 'Do these characters match the page?' })).toBeVisible();
    await expect(page.getByText('below this a group is certified for nothing')).toBeVisible();
  });

  test("review queue offers can't tell beside the verdicts", async ({ page }) => {
    await page.goto('/admin/sources/review', { waitUntil: 'domcontentloaded' });

    const cantTell = page.getByRole('button', { name: /Can't tell/ });

    // A queue that only offers matches and does-not-match turns every
    // hesitation into a label, and the bound is then computed from guesses.
    if (await cantTell.count() > 0) {
      await expect(cantTell.first()).toBeVisible();
      await expect(page.getByRole('button', { name: /Matches the page/ }).first()).toBeVisible();
      await expect(page.getByRole('button', { name: /Does not match/ }).first()).toBeVisible();
      // The page image is what makes the question answerable at all.
      await expect(page.getByRole('img', { name: /Scanned page/ })).toBeVisible();
    } else {
      await expect(page.getByText('Nothing waiting')).toBeVisible();
    }
  });

  test('acquisition navigation is grouped and reachable', async ({ page }) => {
    await page.goto('/admin/sources', { waitUntil: 'domcontentloaded' });

    // The rail nav sits inside the complementary landmark; the first navigation
    // on the page is the primary header nav.
    const rail = page.getByRole('complementary').getByRole('navigation');
    await expect(rail.getByText('Acquisition', { exact: true })).toBeVisible();
    await expect(rail.getByRole('link', { name: 'Findings' })).toBeVisible();
    await expect(rail.getByRole('link', { name: 'Review queue' })).toBeVisible();
  });

  test('console reads at mobile width', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/admin/sources/extraction', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: 'What the pipeline could read' })).toBeVisible();

    // Wide tables must scroll inside their own container rather than pushing
    // the page sideways.
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(2);
  });

  test('console reads in dark mode', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/admin/sources/findings', { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: 'What the sources have published' })).toBeVisible();
  });
});

test.describe('operator console authorization', () => {
  test('a citizen cannot reach acquisition screens', async ({ page }) => {
    await loginAsCitizen(page);

    for (const path of ['/admin/sources', '/admin/sources/findings', '/admin/sources/extraction', '/admin/sources/review']) {
      const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(response?.status()).toBeGreaterThanOrEqual(400);
    }
  });
});
