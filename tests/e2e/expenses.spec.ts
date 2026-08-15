import { test, expect } from '@playwright/test';

// La sesión del usuario demo llega por storageState (proyecto setup).
test.describe('Gastos (Épica 3)', () => {

  test('registra un gasto y aparece en movimientos', async ({ page }) => {
    await page.goto('/gastos/crear');

    await page.fill('input[name="amount"]', '12300');
    await page.selectOption('select[name="account_id"]', { label: 'Efectivo' });
    await page.selectOption('select[name="category_id"]', { label: 'Alimentación' });
    await page.fill('input[name="description"]', 'Mercado de la prueba e2e');
    await page.getByRole('button', { name: 'Guardar gasto' }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByText('Gasto registrado.')).toBeVisible();

    await page.goto('/movimientos');
    await expect(page.getByRole('heading', { name: 'Movimientos' })).toBeVisible();
    await expect(page.getByText('Mercado de la prueba e2e').first()).toBeVisible();
  });

  test('no permite guardar un gasto sin cuenta ni valor', async ({ page }) => {
    await page.goto('/gastos/crear');
    await page.getByRole('button', { name: 'Guardar gasto' }).click();

    // La validación nativa del navegador (required) bloquea el envío:
    // los campos quedan marcados como inválidos y no hay navegación.
    await expect(page.locator('input[name="amount"]:invalid')).toBeVisible();
    await expect(page.locator('select[name="account_id"]:invalid')).toBeVisible();
    await expect(page).toHaveURL(/\/gastos\/crear$/);
  });
});
