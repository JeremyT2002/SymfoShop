import { expect, test } from '@playwright/test';

test('mobile menu opens over the whole screen and closes again', async ({ page }) => {
    await page.goto('/');

    const toggle = page.locator('button[aria-controls="mobile-menu"]');
    await expect(toggle).toBeVisible();
    await toggle.click();

    const menu = page.locator('#mobile-menu');
    await expect(menu).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');

    // The panel must not be clipped by the sticky header (regression: backdrop-filter containing block)
    const viewport = page.viewportSize()!;
    const panel = await menu.boundingBox();
    expect(panel).not.toBeNull();
    expect(panel!.height).toBeGreaterThanOrEqual(viewport.height - 1);

    await expect(menu.getByRole('navigation').first().getByRole('link').first()).toBeVisible();
    await expect(menu.locator('#mobile-search')).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
});

test('mobile menu links navigate and close the menu', async ({ page }) => {
    await page.goto('/');
    await page.locator('button[aria-controls="mobile-menu"]').click();
    await page.locator('#mobile-menu').getByRole('link', { name: 'Login' }).click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.locator('#mobile-menu')).toBeHidden();
});
