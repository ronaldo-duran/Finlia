import { test, expect } from '@playwright/test';
import { DEMO_USER } from './helpers';

// La sesión del usuario demo llega por storageState (proyecto setup).
test.describe('Panel (dashboard)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/dashboard');
  });

  test('muestra el saludo y los KPIs del mes', async ({ page }) => {
    await expect(page.getByRole('heading', { name: `Hola, ${DEMO_USER.name}` })).toBeVisible();

    for (const label of ['Ingresos del mes', 'Gastos del mes', 'Saldo en cuentas']) {
      await expect(page.getByText(label, { exact: true })).toBeVisible();
    }
  });

  test('la navegación lateral lleva a los módulos de las épicas 2 y 3', async ({ page }) => {
    const nav = page.locator('aside .nav-link');

    for (const item of ['Panel', 'Hogares', 'Cuentas', 'Movimientos', 'Categorías']) {
      await expect(nav.filter({ hasText: item })).toBeVisible();
    }

    await nav.filter({ hasText: 'Cuentas' }).click();
    await expect(page).toHaveURL(/\/cuentas$/);
  });

  test('el footer muestra la versión y la moneda del mercado', async ({ page }) => {
    await expect(page.locator('footer.app-footer')).toContainText('COP');
  });
});
