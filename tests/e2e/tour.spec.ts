import { test, expect } from '@playwright/test';

/**
 * Guías de pantalla en un navegador de verdad (ADR-0045).
 *
 * Aquí solo va lo que PHPUnit no puede ver: que el globo se pinte, que avance,
 * que se cierre y —sobre todo— que al cerrarse devuelva la página. La decisión
 * de servidor (a quién le toca, con qué versión, el tope de una por sesión) ya
 * está fijada en tests/Feature/Tour/TourTest.php y no se repite aquí.
 *
 * Las guías se abren con `?guia=`, no esperando a que salten solas: eso las
 * hace deterministas —no dependen de qué haya visto ya el usuario demo ni del
 * cupo de la sesión— y sobreviven a un reintento del CI.
 *
 * Nada de esto comprueba el TEXTO de una guía. El texto se reescribe en cada
 * entrega con novedades (esa es su razón de ser), y un test que lo fije solo
 * serviría para estorbar.
 *
 * La sesión del usuario demo llega por storageState (proyecto setup).
 */
test.describe('Guías de pantalla', () => {
  test('se abre, avanza y al cerrarla la pantalla vuelve a responder', async ({ page }) => {
    await page.goto('/dashboard?guia=panel');

    const globo = page.locator('.tour-globo');
    await expect(globo).toBeVisible();
    await expect(page.locator('.tour-progreso')).toHaveText(/^1 de \d+$/);

    // El segundo paso señala un elemento, así que enciende el halo.
    await globo.getByRole('button', { name: 'Siguiente' }).click();
    await expect(page.locator('.tour-progreso')).toHaveText(/^2 de \d+$/);
    await expect(page.locator('.tour-foco')).toBeVisible();

    await page.locator('.tour-cerrar').click();
    await expect(globo).toBeHidden();

    // La prueba que importa: el fondo de la guía captura los clics a propósito,
    // así que cerrarla tiene que devolver la página. Esta es exactamente la
    // regresión que tumbó el CI la primera vez.
    await page.locator('.avatar-btn').click();
    await expect(page.getByRole('link', { name: 'Mi perfil' })).toBeVisible();
  });

  test('Escape también la cierra', async ({ page }) => {
    await page.goto('/dashboard?guia=panel');

    const globo = page.locator('.tour-globo');
    await expect(globo).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(globo).toBeHidden();
  });

  test('el menú del avatar ofrece la guía de la pantalla actual', async ({ page }) => {
    // Sin `?guia=`: la del Panel ya la vio el setup, así que no debe saltar sola.
    await page.goto('/dashboard');
    await expect(page.locator('.tour-globo')).toBeHidden();

    await page.locator('.avatar-btn').click();
    await page.locator('[data-tour-open]').click();

    await expect(page.locator('.tour-globo')).toBeVisible();
  });

  test('el perfil lista las guías y desde ahí se vuelve a ver cualquiera', async ({ page }) => {
    await page.goto('/perfil');

    const tarjeta = page.locator('.card').filter({ hasText: 'Guías de la app' });
    await expect(tarjeta).toBeVisible();
    await expect(tarjeta.getByRole('button', { name: 'Volver a verlas desde el principio' })).toBeVisible();

    // Sin fijar cuántas son: el catálogo crece con cada funcionalidad nueva.
    const verlas = tarjeta.getByRole('link', { name: 'Ver' });
    await expect(verlas).not.toHaveCount(0);

    await verlas.first().click();
    await expect(page.locator('.tour-globo')).toBeVisible();
  });
});
