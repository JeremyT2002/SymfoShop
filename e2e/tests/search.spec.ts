import { expect, test } from '@playwright/test';

test('live search suggests products while typing', async ({ page }) => {
    await page.goto('/');

    await page.locator('#header-search').fill('cotton');
    const suggestions = page.locator('#search-suggestions');
    await expect(suggestions).toBeVisible();

    const option = suggestions.getByRole('option', { name: /Classic Cotton T-Shirt/ });
    await expect(option).toBeVisible();
    await option.click();

    await expect(page).toHaveURL(/\/product\/classic-cotton-tshirt$/);
});

test('language switch shows translated interface and hreflang links', async ({ page }) => {
    await page.goto('/product/classic-cotton-tshirt?lang=de');

    await expect(page.locator('html')).toHaveAttribute('lang', 'de');
    await expect(page.locator('link[rel="alternate"][hreflang="fr"]')).toHaveAttribute('href', /\/product\/classic-cotton-tshirt\?lang=fr$/);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /\?lang=de$/);
});
