import { test, expect } from '@playwright/test';

// La sesión del usuario demo llega por storageState (proyecto setup).
test.describe('Hogares (Épica 2)', () => {

  test('lista el hogar demo sembrado', async ({ page }) => {
    await page.goto('/hogares');
    await expect(page.getByRole('heading', { name: 'Tus hogares' })).toBeVisible();
    await expect(page.getByText('Hogar Demo').first()).toBeVisible();
  });

  test('crea un hogar nuevo y lo deja activo', async ({ page }) => {
    await page.goto('/hogares');
    await page.getByRole('link', { name: 'Crear hogar' }).first().click();
    await expect(page).toHaveURL(/\/hogares\/crear$/);

    await page.fill('input[name="name"]', 'Hogar Playwright');
    await page.selectOption('select[name="currency"]', 'COP');
    await page.selectOption('select[name="timezone"]', 'America/Bogota');
    await page.getByRole('button', { name: 'Crear hogar' }).click();

    await expect(page).toHaveURL(new RegExp('/hogares/\\d+$'));
    await expect(page.getByText('Hogar "Hogar Playwright" creado.')).toBeVisible();
    // Al crear un hogar pasa a ser el activo del selector.
    await expect(page.locator('.household-selector-btn')).toContainText('Hogar Playwright');
  });
});
