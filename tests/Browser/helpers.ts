import { expect, type Page } from '@playwright/test';

export async function loginAsAdmin(page: Page) {
  await signIn(page, 'admin@civiclens.test', 'password', 'CivicLens Dashboard');
}

export async function loginAsStaff(page: Page) {
  await signIn(page, 'staff@civiclens.test', 'password', 'CivicLens Dashboard');
}

export async function loginAsCitizen(page: Page) {
  await signIn(page, 'citizen@civiclens.test', 'password', 'My reports and public information');
}

export async function registerCitizen(page: Page, email: string) {
  const heading = 'Verify your email';

  for (let attempt = 0; attempt < 2; attempt += 1) {
    await page.goto('/register');
    await page.getByLabel('Name').fill('Browser Citizen');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password', { exact: true }).fill('CivicLens12345');
    await page.getByLabel('Confirm Password').fill('CivicLens12345');
    await page.getByRole('button', { name: 'Register' }).click();

    try {
      await expect(page.getByRole('heading', { name: heading })).toBeVisible({ timeout: 3_000 });

      return;
    } catch {
    }
  }

  await expect(page.getByRole('heading', { name: heading })).toBeVisible();
}

async function signIn(page: Page, email: string, password: string, heading: string) {
  for (let attempt = 0; attempt < 2; attempt += 1) {
    await page.goto('/login');
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Login' }).click();

    try {
      await expect(page.getByRole('heading', { name: heading })).toBeVisible({ timeout: 3_000 });

      return;
    } catch {
    }
  }

  await expect(page.getByRole('heading', { name: heading })).toBeVisible();
}
