import { expect, type Page } from '@playwright/test';

export async function loginAsAdmin(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Email').fill('test@example.com');
  await page.getByLabel('Password').fill('password');
  await page.getByRole('button', { name: 'Login' }).click();
  await expect(page.getByRole('heading', { name: 'CivicLens Dashboard' })).toBeVisible();
}

export async function registerCitizen(page: Page, email: string) {
  await page.goto('/register');
  await page.getByLabel('Name').fill('Browser Citizen');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password', { exact: true }).fill('CivicLens12345');
  await page.getByLabel('Confirm Password').fill('CivicLens12345');
  await page.getByRole('button', { name: 'Register' }).click();
  await expect(page.getByRole('heading', { name: 'CivicLens Dashboard' })).toBeVisible();
}
