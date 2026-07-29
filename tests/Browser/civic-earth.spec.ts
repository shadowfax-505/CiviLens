import { expect, test, type Locator, type Page, type TestInfo } from '@playwright/test';
import sharp from 'sharp';

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
  await expect(journey).toHaveClass(/is-renderer-ready/, { timeout: 30_000 });

  return journey;
}

async function selectStage(page: Page, stage: string) {
  await page.getByRole('button', { name: `Go to ${stage}` }).click();
  await expect(page.locator('[data-earth-step]')).toHaveText(stage);
}

async function setExactStage(page: Page, index: number) {
  const scroll = page.locator('[data-earth-scroll]');
  await scroll.evaluate((element, stageIndex) => {
    element.scrollTop = (element.scrollHeight - element.clientHeight) * (stageIndex / 8);
    element.dispatchEvent(new Event('scroll'));
  }, index);
  await page.evaluate(() => new Promise<void>((resolve) => {
    requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
  }));
  await expect(page.locator('[data-earth-step]')).toHaveText(stages[index]);
}

async function attachStageCapture(page: Page, testInfo: TestInfo, stage: string) {
  const screenshot = await screenshotElement(page, page.locator('[data-civic-earth]'));
  await expectNonBlankImage(screenshot);
  await testInfo.attach(`civic-earth-${stage.slice(0, 2)}`, {
    body: screenshot,
    contentType: 'image/jpeg',
  });
}

async function screenshotElement(page: Page, locator: Locator) {
  const box = await locator.boundingBox();
  expect(box).not.toBeNull();

  return page.screenshot({
    clip: box!,
    type: 'jpeg',
    quality: 72,
    scale: 'css',
  });
}

async function expectNonBlankImage(image: Buffer) {
  const statistics = await sharp(image)
    .resize({ width: 360, height: 240, fit: 'inside' })
    .greyscale()
    .stats();

  expect(statistics.entropy).toBeGreaterThan(2);
  expect(statistics.channels[0].stdev).toBeGreaterThan(12);
}

test.describe('CivicLens Earth journey', () => {
  test.setTimeout(120_000);

  test('keeps the exact nine stages', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    await openJourney(page);

    for (const [index, stage] of stages.entries()) {
      await setExactStage(page, index);
      await expect(page.locator('[data-earth-step]')).toHaveText(stage);
    }

    await expect(page.locator('[data-earth-flight-dots] button[aria-current="step"]')).toHaveAttribute(
      'aria-label',
      'Go to 09 · Civic data',
    );
    expect(pageErrors).toEqual([]);
  });

  test('captures non-blank gap checks for stages 1-5', async ({ page }, testInfo) => {
    test.setTimeout(180_000);
    const pageErrors: string[] = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));
    await openJourney(page);

    for (const [index, stage] of stages.slice(0, 5).entries()) {
      await setExactStage(page, index);
      await attachStageCapture(page, testInfo, stage);
    }

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
    await page.addInitScript(() => {
      const original = Element.prototype.scrollTo;
      const observed: string[] = [];
      Reflect.set(window, '__civicEarthScrollBehaviors', observed);
      Element.prototype.scrollTo = function (...parameters: unknown[]) {
        const options = parameters[0];
        if (typeof options === 'object' && options !== null && 'behavior' in options) {
          observed.push(String((options as ScrollToOptions).behavior));
        }

        return (original as (...args: unknown[]) => void).apply(this, parameters);
      };
    });
    await page.emulateMedia({ colorScheme: 'dark', reducedMotion: 'reduce' });
    const journey = await openJourney(page);
    const scroll = journey.locator('[data-earth-scroll]');

    await page.getByRole('button', { name: 'Go to 05 · South Asia' }).click();
    await expect(page.locator('[data-earth-step]')).toHaveText('05 · South Asia');
    await expect(page.locator('html')).toHaveClass(/dark/);
    await expect(scroll).toHaveCSS('scroll-snap-type', /mandatory/);
    await expect(journey.locator('[data-earth-card-title]')).toBeVisible();
    expect(await page.evaluate(() => Reflect.get(window, '__civicEarthScrollBehaviors'))).toContain('auto');
  });

  test('uses the established static hero when the rollout flag is disabled', async ({ page }) => {
    await page.goto(`${disabledBaseUrl}/`, { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: /Public delivery,/ })).toBeVisible();
    await expect(page.locator('[data-civic-earth]')).toHaveCount(0);
  });

  test('keeps the validated local fallback visible when the runtime image fails', async ({ page }, testInfo) => {
    let runtimeRequests = 0;
    await page.route('**/nasa-blue-marble-cloud-observation-composite-5400x2700.jpg', (route) => {
      runtimeRequests += 1;
      return route.abort();
    });
    const journey = await openJourney(page);

    await expect(journey.locator('[data-earth-imagery]')).toContainText('resilient fallback', { timeout: 30_000 });
    const canvas = journey.locator('[data-earth-globe] canvas');
    await expect(canvas).toBeVisible();
    expect(runtimeRequests).toBeGreaterThan(0);
    await expectNonBlankImage(await screenshotElement(page, canvas));
    await expect(journey.locator('.civic-earth__fallback')).toHaveCSS(
      'background-image',
      /nasa-blue-marble-2004-12-5400x2700\.jpg/,
    );
    await testInfo.attach('runtime-failure-fallback', {
      body: await screenshotElement(page, journey),
      contentType: 'image/jpeg',
    });
  });

  test('retains a non-blank globe when regional imagery is unavailable', async ({ page }, testInfo) => {
    let regionalRequests = 0;
    await page.route('**/ArcGIS/rest/services/**', (route) => {
      regionalRequests += 1;
      return route.abort();
    });
    const journey = await openJourney(page);

    await selectStage(page, '05 · South Asia');
    const canvas = journey.locator('[data-earth-globe] canvas');
    await expect(canvas).toBeVisible();
    await expect.poll(() => regionalRequests).toBeGreaterThan(0);
    await expectNonBlankImage(await screenshotElement(page, canvas));
    await expect(journey.locator('.civic-earth__fallback')).toHaveCSS(
      'background-image',
      /nasa-blue-marble-2004-12-5400x2700\.jpg/,
    );
    await testInfo.attach('regional-failure-fallback', {
      body: await screenshotElement(page, journey),
      contentType: 'image/jpeg',
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
    expect(payload.data.length).toBeGreaterThan(0);
    expect(payload.data.map((marker: { name: string }) => marker.name)).toContain('Eastern Bus Corridor Signal Upgrade');
    expect(payload.data.map((marker: { name: string }) => marker.name)).not.toContain('Safe School Streets Pilot');
    for (const marker of payload.data) {
      expect(Object.keys(marker).sort()).toEqual(['agency', 'latitude', 'longitude', 'name', 'status', 'url']);
      expect(marker).not.toHaveProperty('id');
      expect(marker).not.toHaveProperty('budget');
      expect(marker).not.toHaveProperty('is_public');
    }
  });
});
