import { expect, test, type Page, type TestInfo } from '@playwright/test';

const enabledBaseUrl = 'http://127.0.0.1:8010';
const disabledBaseUrl = 'http://127.0.0.1:8011';
const stages = [
  '01 · Earth system',
  '02 · Orbital Earth',
  '03 · Hemisphere',
  '04 · Indian Ocean',
  '05 · South Asia',
  '06 · Delta basin',
  '07 · Bangladesh',
  '08 · Districts',
  '09 · Civic data',
];

async function openJourney(page: Page) {
  await page.goto(`${enabledBaseUrl}/`, { waitUntil: 'domcontentloaded' });
  const journey = page.locator('[data-civic-earth]');
  await expect(journey).toBeVisible();
  await expect(journey.locator('[data-earth-flight-dots] button')).toHaveCount(9);

  return journey;
}

async function selectStage(page: Page, stage: string) {
  await page.getByRole('button', { name: `Go to ${stage}` }).click();
  await expect(page.locator('[data-earth-step]')).toHaveText(stage);
}

async function attachStageCapture(page: Page, testInfo: TestInfo, stage: string) {
  await testInfo.attach(`civic-earth-${stage.slice(0, 2)}`, {
    body: await page.locator('[data-civic-earth]').screenshot({ animations: 'disabled' }),
    contentType: 'image/png',
  });
}

test.describe('CivicLens Earth journey', () => {
  test.setTimeout(120_000);

  test('keeps the exact nine stages and captures gap checks for stages 1-5', async ({ page }, testInfo) => {
    const pageErrors: string[] = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    await openJourney(page);

    for (const [index, stage] of stages.entries()) {
      await selectStage(page, stage);
      if (index < 5) {
        await attachStageCapture(page, testInfo, stage);
      }
    }

    await expect(page.locator('[data-earth-flight-dots] button[aria-current="step"]')).toHaveAttribute(
      'aria-label',
      'Go to 09 · Civic data',
    );
    expect(pageErrors).toEqual([]);
  });

  test('supports bounded wheel and keyboard navigation before continuing the page', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name.includes('mobile'), 'Desktop wheel coverage; mobile native scrolling has a dedicated test.');
    const journey = await openJourney(page);
    const scroll = journey.locator('[data-earth-scroll]');

    await scroll.focus();
    await scroll.press('End');
    await expect(journey.locator('[data-earth-step]')).toHaveText('09 · Civic data');
    await scroll.press('Home');
    await expect(journey.locator('[data-earth-step]')).toHaveText('01 · Earth system');
    await scroll.press('ArrowDown');
    await expect(journey.locator('[data-earth-step]')).toHaveText('02 · Orbital Earth');
    await scroll.press('ArrowUp');
    await expect(journey.locator('[data-earth-step]')).toHaveText('01 · Earth system');

    const box = await scroll.boundingBox();
    expect(box).not.toBeNull();
    await page.mouse.move(box!.x + (box!.width / 2), box!.y + (box!.height / 2));
    await page.mouse.wheel(0, 700);
    await expect.poll(() => scroll.evaluate((element) => element.scrollTop)).toBeGreaterThan(0);

    await scroll.evaluate((element) => {
      element.scrollTop = element.scrollHeight - element.clientHeight;
      element.dispatchEvent(new Event('scroll'));
    });
    const innerMaximum = await scroll.evaluate((element) => element.scrollHeight - element.clientHeight);
    const pageBefore = await page.evaluate(() => window.scrollY);
    await page.mouse.wheel(0, 900);
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(pageBefore);
    expect(await scroll.evaluate((element) => Math.round(element.scrollTop))).toBe(Math.round(innerMaximum));
  });

  test('supports native mobile scrolling and keeps responsive text inside the scene', async ({ page }, testInfo) => {
    test.skip(!testInfo.project.name.includes('mobile'), 'Mobile-only touch/native scroll coverage.');
    const journey = await openJourney(page);
    const scroll = journey.locator('[data-earth-scroll]');
    const stage = journey.locator('.civic-earth__stage');
    const copy = journey.locator('.civic-earth__copy');

    await expect(scroll).toHaveCSS('overflow-y', 'auto');
    const box = await scroll.boundingBox();
    expect(box).not.toBeNull();
    const client = await page.context().newCDPSession(page);
    const x = Math.round(box!.x + (box!.width / 2));
    const startY = Math.round(box!.y + (box!.height * 0.72));
    await client.send('Input.dispatchTouchEvent', {
      type: 'touchStart',
      touchPoints: [{ x, y: startY }],
    });
    for (const distance of [80, 160, 240]) {
      await client.send('Input.dispatchTouchEvent', {
        type: 'touchMove',
        touchPoints: [{ x, y: startY - distance }],
      });
    }
    await client.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await expect.poll(() => scroll.evaluate((element) => element.scrollTop)).toBeGreaterThan(0);

    const [stageBox, copyBox] = await Promise.all([stage.boundingBox(), copy.boundingBox()]);
    expect(stageBox).not.toBeNull();
    expect(copyBox).not.toBeNull();
    expect(copyBox!.x).toBeGreaterThanOrEqual(stageBox!.x);
    expect(copyBox!.x + copyBox!.width).toBeLessThanOrEqual(stageBox!.x + stageBox!.width + 1);
    expect(copyBox!.y).toBeGreaterThanOrEqual(stageBox!.y);
    expect(copyBox!.y + copyBox!.height).toBeLessThanOrEqual(stageBox!.y + stageBox!.height + 1);
  });

  test('honours reduced motion and dark mode without removing stage navigation', async ({ page }) => {
    await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });
    const journey = await openJourney(page);
    const scroll = journey.locator('[data-earth-scroll]');

    await selectStage(page, '05 · South Asia');
    await expect(page.locator('html')).toHaveClass(/dark/);
    await expect(scroll).toHaveCSS('scroll-snap-type', /mandatory/);
    await expect(journey.locator('[data-earth-card-title]')).toBeVisible();
  });

  test('uses the established static hero when the rollout flag is disabled', async ({ page }) => {
    await page.goto(`${disabledBaseUrl}/`, { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: /Public delivery,/ })).toBeVisible();
    await expect(page.locator('[data-civic-earth]')).toHaveCount(0);
  });

  test('keeps the validated local fallback visible when the runtime image fails', async ({ page }, testInfo) => {
    await page.route('**/nasa-blue-marble-cloud-observation-composite-5400x2700.jpg', (route) => route.abort());
    const journey = await openJourney(page);

    await expect(journey.locator('[data-earth-imagery]')).toContainText('resilient fallback', { timeout: 30_000 });
    await expect(journey.locator('[data-earth-globe] canvas')).toBeVisible();
    await expect(journey.locator('.civic-earth__fallback')).toHaveCSS(
      'background-image',
      /nasa-blue-marble-2004-12-5400x2700\.jpg/,
    );
    await testInfo.attach('runtime-failure-fallback', {
      body: await journey.screenshot({ animations: 'disabled' }),
      contentType: 'image/png',
    });
  });

  test('retains a non-blank globe when regional imagery is unavailable', async ({ page }, testInfo) => {
    await page.route('**/ArcGIS/rest/services/**', (route) => route.abort());
    const journey = await openJourney(page);

    await selectStage(page, '05 · South Asia');
    await expect(journey.locator('[data-earth-globe] canvas')).toBeVisible();
    await expect(journey.locator('.civic-earth__fallback')).toHaveCSS(
      'background-image',
      /nasa-blue-marble-2004-12-5400x2700\.jpg/,
    );
    await testInfo.attach('regional-failure-fallback', {
      body: await journey.screenshot({ animations: 'disabled' }),
      contentType: 'image/png',
    });
  });

  test('uses the existing permission-safe public marker endpoint', async ({ request }) => {
    const response = await request.get(
      `${enabledBaseUrl}/public/projects/map-data?south=20.5&west=88&north=26.8&east=92.8`,
      { headers: { Accept: 'application/json' } },
    );

    expect(response.ok()).toBe(true);
    const payload = await response.json();
    expect(Array.isArray(payload.data)).toBe(true);
    for (const marker of payload.data) {
      expect(Object.keys(marker).sort()).toEqual(['agency', 'latitude', 'longitude', 'name', 'status', 'url']);
      expect(marker).not.toHaveProperty('id');
      expect(marker).not.toHaveProperty('budget');
      expect(marker).not.toHaveProperty('is_public');
    }
  });
});
