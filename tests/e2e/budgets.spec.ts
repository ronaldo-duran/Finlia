import { test, expect } from '@playwright/test';

test.describe('Presupuestos y dinero disponible (Épica 4)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/presupuestos');
  });

  test('muestra la tarjeta principal "puedes gastar"', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Presupuestos' })).toBeVisible();

    const card = page.getByTestId('available-money');
    await expect(card).toBeVisible();
    await expect(card).toContainText(/Puedes gastar hoy|Te falta plata antes de tu próximo pago|Te has pasado del plan/);
    await expect(page.getByTestId('available-money-amount')).toContainText(/\$\s[\d.]+,\d{2}/);

    for (const label of ['Saldo en cuentas', 'Reservado', 'Gastado']) {
      await expect(page.getByText(label, { exact: true })).toBeVisible();
    }
    await expect(page.getByText(/Días (para tu pago|del mes)/)).toBeVisible();
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

    const desglose = page.locator('#comoSeCalcula');
    await expect(desglose).toBeVisible();
    await expect(desglose.getByText('Saldo en cuentas hoy', { exact: true })).toBeVisible();
    await expect(desglose.getByText('− Apartado en metas de ahorro')).toBeVisible();
    await expect(desglose.getByText(/− Cuotas de deuda/)).toBeVisible();
    await expect(desglose.getByText('− Ahorro programado hasta el cobro')).toBeVisible();
    await expect(desglose.getByText('= Disponible hasta el cobro')).toBeVisible();
  });

  test('el próximo mes es una proyección con los ingresos esperados', async ({ page }) => {
    await page.goto('/presupuestos?periodo=proximo-mes');
    await expect(page.getByTestId('available-money')).toContainText(/Te quedaría según tu plan|Tu plan no cuadra/);

    await page.locator('a[href="#comoSeCalcula"]').click();

    const desglose = page.locator('#comoSeCalcula');
    await expect(desglose.getByText('Ingresos esperados', { exact: true })).toBeVisible();
    await expect(desglose.getByText('− Cuotas de deuda pendientes')).toBeVisible();
    await expect(desglose.getByText('= Te sobraría')).toBeVisible();
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

    await page
      .locator('form[action*="/presupuestos/"]')
      .last()
      .getByRole('button', { name: 'Eliminar' })
      .click();

    await page.getByRole('button', { name: 'Sí, continuar' }).click();

    await expect(page.getByText('Presupuesto eliminado.')).toBeVisible();
  });

  test('los ingresos esperados alimentan el cálculo', async ({ page }) => {
    await page.goto('/ingresos-esperados');

    await expect(page.getByRole('heading', { name: 'Ingresos esperados' })).toBeVisible();
    await expect(page.getByText(/Total mensual:/)).toBeVisible();

    const configurados = page.locator('.list-group-item');
    await expect(configurados.filter({ hasText: 'Salario titular' })).toBeVisible();
    await expect(configurados.filter({ hasText: 'Salario del miembro' })).toBeVisible();
  });

  test('el dashboard enlaza al panel de presupuestos', async ({ page }) => {
    await page.goto('/dashboard');

    await page.getByRole('link', { name: 'Ver presupuestos' }).click();
    await expect(page).toHaveURL(/\/presupuestos$/);
  });
});
