import { expect, test } from '@playwright/test';

test('guest buys a T-shirt and follows the order status', async ({ page }) => {
    const email = `e2e-${Date.now()}@example.com`;

    await page.goto('/product/classic-cotton-tshirt');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Classic Cotton T-Shirt');

    await page.locator('[data-add-to-cart]').click();
    await expect(page.locator('[data-cart-count]').first()).toHaveText('1');

    await page.goto('/checkout');
    const form = page.locator('form[data-checkout-form]');
    await form.locator('input[name$="[email]"]').fill(email);
    await form.locator('input[name$="[firstName]"]').fill('Erika');
    await form.locator('input[name$="[lastName]"]').fill('Mustermann');
    await form.locator('input[name$="[street]"]').fill('Musterweg 1');
    await form.locator('input[name$="[postalCode]"]').fill('12345');
    await form.locator('input[name$="[city]"]').fill('Berlin');
    await form.locator('select[name$="[country]"]').selectOption('DE');
    await form.locator('input[name="payment_method"][value="testbank"]').check();
    await form.locator('[data-place-order]').click();

    // Payment page of the sandbox provider: simulate a successful payment
    await page.locator('a[href*="/_dev/payment/simulate/"][href$="/success"]').click();

    await expect(page).toHaveURL(/\/checkout\/success/);
    const orderNumber = (await page.locator('main p.font-mono').first().innerText()).trim();
    expect(orderNumber).toMatch(/^ORD-/);

    // Guest order status lookup
    await page.goto('/order-status');
    const lookup = page.locator('form[action="/order-status"]');
    await lookup.locator('input[name="number"]').fill(orderNumber);
    await lookup.locator('input[name="email"]').fill(email);
    await lookup.locator('button[type="submit"]').click();

    await expect(page).toHaveURL(new RegExp(`/order-status/${orderNumber}$`));
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(orderNumber);
    await expect(page.locator('[aria-current="step"]')).toContainText('Paid');
});

test('stock limit is shown when adding more than available', async ({ page }) => {
    await page.goto('/product/classic-cotton-tshirt');
    await page.locator('#product-qty').fill('9999');
    await page.locator('[data-add-to-cart]').click();

    await expect(page.locator('#toast-region > *').first()).toBeVisible();
    await expect(page.locator('[data-cart-count]').first()).toBeHidden();
});
