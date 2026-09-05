import { test, expect } from '@playwright/test';

// La sesión del usuario demo llega por storageState (proyecto setup).
// Los datos vienen de DatabaseSeeder: presupuesto total + 3 categorías.
test.describe('Presupuestos y dinero disponible (Épica 4)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/presupuestos');
  });

  test('muestra la tarjeta principal "puedes gastar"', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Presupuestos' })).toBeVisible();

    // El titular cambia si el disponible es negativo y los datos del seeder son
    // aleatorios, así que se comprueba la tarjeta y su importe, no el signo.
    const card = page.getByTestId('available-money');
    await expect(card).toBeVisible();
    await expect(card).toContainText(/Puedes gastar aproximadamente|Te has pasado del plan/);
    await expect(page.getByTestId('available-money-amount')).toContainText(/\$\s[\d.]+,\d{2}/);

    for (const label of ['Gastado', 'Comprometido', 'Libre en cuentas', 'Días restantes']) {
      await expect(page.getByText(label, { exact: true })).toBeVisible();
    }
  });

  test('permite cambiar entre semana, mes y próximo mes', async ({ page }) => {
    await page.getByRole('link', { name: 'Esta semana' }).click();
    await expect(page).toHaveURL(/periodo=semana/);
    await expect(page.getByText('presupuesto mensual prorrateado')).toBeVisible();

    await page.getByRole('link', { name: 'Próximo mes' }).click();
    await expect(page).toHaveURL(/periodo=proximo-mes/);
  });

  test('el desglose "cómo se calcula" revela los términos de la fórmula', async ({ page }) => {
    await page.locator('a[href="#comoSeCalcula"]').click();

    // Acotado al colapsable: "Ingresos esperados" también es un enlace del menú.
    const desglose = page.locator('#comoSeCalcula');
    await expect(desglose).toBeVisible();
    await expect(desglose.getByText('Ingresos esperados', { exact: true })).toBeVisible();
    await expect(desglose.getByText('− Ya gastado')).toBeVisible();
    await expect(desglose.getByText('= Puedes gastar')).toBeVisible();
    // Los términos de la fórmula se anuncian, no se ocultan: los ya
    // implementados con su cifra y los pendientes con su épica.
    await expect(desglose.getByText('− Cuotas de deuda pendientes')).toBeVisible();
    await expect(desglose.getByText('− Ahorro programado')).toBeVisible();
  });

  test('crea un presupuesto para el próximo mes y lo elimina', async ({ page }) => {
    await page.goto('/presupuestos?periodo=proximo-mes');
    await page.getByRole('link', { name: 'Nuevo presupuesto' }).click();

    await expect(page.getByRole('heading', { name: 'Nuevo presupuesto' })).toBeVisible();
    await page.fill('input[name="amount"]', '1234500');
    await page.getByRole('button', { name: 'Guardar presupuesto' }).click();

    await expect(page).toHaveURL(/periodo=proximo-mes/);
    await expect(page.getByText('Presupuesto guardado.')).toBeVisible();
    await expect(page.getByText('Presupuesto total del mes')).toBeVisible();

    // Limpieza: el seeder no crea presupuestos del próximo mes.
    // El formulario de eliminación usa data-confirm → modal Bootstrap (no browser dialog).
    await page
      .locator('form[action*="/presupuestos/"]')
      .last()
      .getByRole('button', { name: 'Eliminar' })
      .click();

    // Confirmar en el modal genérico de la app.
    await page.getByRole('button', { name: 'Sí, continuar' }).click();

    await expect(page.getByText('Presupuesto eliminado.')).toBeVisible();
  });

  test('los ingresos esperados alimentan el cálculo', async ({ page }) => {
    await page.goto('/ingresos-esperados');

    await expect(page.getByRole('heading', { name: 'Ingresos esperados' })).toBeVisible();
    await expect(page.getByText(/Total mensual:/)).toBeVisible();

    // Acotado al listado: "Salario" también existe como <option> de categoría.
    const configurados = page.locator('.list-group-item');
    await expect(configurados.filter({ hasText: 'Salario' }).first()).toBeVisible();
    await expect(configurados.filter({ hasText: 'Arriendo local' })).toBeVisible();
  });

  test('el dashboard enlaza al panel de presupuestos', async ({ page }) => {
    await page.goto('/dashboard');

    await page.getByRole('link', { name: 'Ver presupuestos' }).click();
    await expect(page).toHaveURL(/\/presupuestos$/);
  });
});
