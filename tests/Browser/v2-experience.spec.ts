import { expect, test, type Page } from '@playwright/test';
import { loginAsAdmin, loginAsCitizen, loginAsStaff } from './helpers';

async function useEarthFallback(page: Page) {
  await page.route('**/build/assets/Cesium-*.js', (route) => route.abort());
}

test('public journey leads with district exploration and a filterable evidence timeline', async ({ page }) => {
  await useEarthFallback(page);
  await page.goto('/');

  await expect(page.locator('[data-role-shell]')).toHaveAttribute('data-role-shell', 'public');
  await expect(page.getByRole('button', { name: 'Explore my district' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Recently indexed' })).toBeVisible();

  await page.goto('/?timeline_type=procurement&source_class=government');
  const entries = page.locator('.cl-timeline-item');
  await expect(entries.first()).toBeVisible();
  await expect(entries.first()).toContainText('Procurement');

  await page.goto('/?source_class=non-government');
  await expect(page.getByText('No approved records match these filters.')).toBeVisible();
});

test('district location is requested only after interaction and denial falls back to Dhaka', async ({ page }) => {
  let locationCalls = 0;
  await page.exposeFunction('recordDistrictLocationCall', () => {
    locationCalls += 1;
  });
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'geolocation', {
      configurable: true,
      value: {
        getCurrentPosition: (_success, error) => {
          Reflect.get(window, 'recordDistrictLocationCall')();
          error?.({ code: 1, message: 'Denied', PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 });
        },
      },
    });
  });
  await useEarthFallback(page);
  await page.goto('/');

  expect(locationCalls).toBe(0);
  await page.getByRole('button', { name: 'Explore my district' }).click();

  await expect(page).toHaveURL(/\/public\/projects\?district_id=\d+$/);
  await expect.poll(() => locationCalls).toBe(1);
  await expect(page.getByRole('heading', { name: 'Public Projects' })).toBeVisible();
});

test('role shells keep public, citizen, staff, and administration work distinct', async ({ page }) => {
  await loginAsAdmin(page);
  await expect(page.locator('[data-role-shell]')).toHaveAttribute('data-role-shell', 'administrator');
  await expect(page.getByRole('complementary', { name: 'Administration navigation' })).toBeVisible();

  await page.context().clearCookies();
  await loginAsStaff(page);
  await expect(page.locator('[data-role-shell]')).toHaveAttribute('data-role-shell', 'staff');
  await expect(page.getByRole('complementary', { name: 'Staff workspace navigation' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Users', exact: true })).toHaveCount(0);

  await page.context().clearCookies();
  await loginAsCitizen(page);
  await expect(page.locator('[data-role-shell]')).toHaveAttribute('data-role-shell', 'citizen');
  await expect(page.getByRole('complementary', { name: 'My CivicLens navigation' })).toBeVisible();
  await expect(page.getByRole('complementary', { name: 'My CivicLens navigation' }).getByRole('link', { name: 'Submit a report' })).toBeVisible();
});

test('citizens control district and approved-change preferences', async ({ page }) => {
  await loginAsCitizen(page);
  await page.goto('/profile');

  const majorChanges = page.getByLabel('All approved major changes');
  const procurementUpdates = page.getByLabel('Procurement updates');
  if (!await majorChanges.isChecked()) {
    await majorChanges.check();
    await procurementUpdates.uncheck();
    await page.getByRole('button', { name: 'Save preferences' }).click();
    await expect(page.getByText('notifications-updated')).toBeVisible();
  }

  await expect(majorChanges).toBeChecked();
  await page.getByLabel('Dhaka').check();
  await page.getByRole('button', { name: 'Save district preferences' }).click();
  await expect(page.getByText('district-preferences-updated')).toBeVisible();
  await expect(page.getByLabel('Dhaka')).toBeChecked();

  await majorChanges.uncheck();
  await procurementUpdates.check();
  await page.getByRole('button', { name: 'Save preferences' }).click();
  await expect(page.getByText('notifications-updated')).toBeVisible();
  await expect(majorChanges).not.toBeChecked();
  await expect(procurementUpdates).toBeChecked();
});
