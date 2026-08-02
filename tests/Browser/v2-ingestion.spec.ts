import { expect, test } from '@playwright/test';
import { loginAsAdmin } from './helpers';

test('administrator can reach governed source controls while citizens cannot', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/sources');

    await expect(page.getByRole('heading', { name: 'Approved source registry' })).toBeVisible();
    await expect(page.getByRole('heading', { name: '1. Approve a publisher' })).toBeVisible();
    await expect(page.getByRole('heading', { name: '2. Add an allowlisted endpoint' })).toBeVisible();
    await expect(page.getByText('Browser connectors stay fail-closed')).toBeVisible();
});
