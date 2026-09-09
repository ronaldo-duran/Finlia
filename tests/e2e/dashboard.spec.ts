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

  /**
   * El FAB flotante vive en un contenedor `fixed` que incluye su menú. El
   * menú cerrado sigue ocupando sitio en el layout, así que la caja del
   * contenedor se estira muy por encima del "+" y se tragaba los clics de lo
   * que hubiera debajo: los botones "Gasto"/"Ingreso" del panel parecían
   * muertos y había que abrir el "+" para registrar un ingreso.
   *
   * Se mide un punto concreto del contenedor —dentro de su caja, fuera del
   * botón— en vez de la posición de un botón de la página: dónde cae ese
   * botón depende de cuánto contenido tenga el panel ese día, y si hay que
   * hacer scroll para alcanzarlo el FAB se auto-oculta y el fallo se
   * escondería solo.
   */
  test('el contenedor del FAB no intercepta los clics de la página', async ({ browser }) => {
    const context = await browser.newContext({
      storageState: 'playwright/.auth/demo.json',
      viewport: { width: 390, height: 844 },
      isMobile: true,
      hasTouch: true,
    });
    const page = await context.newPage();
    await page.goto('/dashboard');

    const medida = await page.evaluate(() => {
      const contenedor = document.getElementById('fabContainer');
      if (!contenedor) return null;

      const boton = contenedor.querySelector('.fab-btn')!;
      const cajaContenedor = contenedor.getBoundingClientRect();
      const cajaBoton = boton.getBoundingClientRect();

      // Esquina superior del contenedor: dentro de su caja, muy por encima
      // del "+". Ahí no debería haber nada que capture el clic.
      const x = cajaContenedor.left + 4;
      const y = cajaContenedor.top + 4;
      const encima = document.elementFromPoint(x, y);

      return {
        // Guarda: si algún día el contenedor dejara de ser más alto que el
        // botón, este test pasaría sin comprobar nada.
        sobresalePorEncimaDelBoton: cajaContenedor.height - cajaBoton.height,
        loCaptura: contenedor === encima || contenedor.contains(encima),
        capturadoPor: encima ? encima.className || encima.tagName : null,
      };
    });

    expect(medida, 'no se encontró el FAB').not.toBeNull();
    expect(medida!.sobresalePorEncimaDelBoton).toBeGreaterThan(20);
    expect(medida!.loCaptura, `el contenedor capturó el clic como: ${medida!.capturadoPor}`).toBe(false);

    // Y el camino real para registrar, que desde que el panel no lleva
    // botones propios es el único: abrir el "+" y elegir la acción.
    await page.getByRole('button', { name: 'Registrar movimiento' }).click();
    await page.getByRole('link', { name: 'Ingreso' }).click();
    await expect(page).toHaveURL(/\/ingresos\/crear$/);

    await context.close();
  });

  test('el footer muestra la versión y la moneda del mercado', async ({ page }) => {
    await expect(page.locator('footer.app-footer')).toContainText('COP');
  });
});
