import type { Page } from '@playwright/test';

/**
 * Credenciales del usuario demo que siembra DatabaseSeeder (datos FALSOS).
 */
export const DEMO_USER = {
  name: 'Camila Restrepo',
  email: 'demo@finlia.test',
  password: 'finlia123',
};

/**
 * Inicia sesión con el usuario demo y espera llegar al panel.
 */
export async function loginAsDemo(page: Page): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', DEMO_USER.email);
  await page.fill('input[name="password"]', DEMO_USER.password);
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await page.waitForURL(/\/dashboard$/);
}

/**
 * Cierra la guía de pantalla si está abierta (ADR-0045).
 *
 * Una guía es modal a propósito: su fondo captura los clics para que nadie la
 * atraviese sin querer. Eso significa que cualquier recorrido que empiece con
 * sesión nueva tiene que descartarla antes de tocar la interfaz — igual que
 * haría una persona. Sin esto, el clic se queda esperando al fondo.
 *
 * Si no hay guía (pantalla ya vista, o guías apagadas) no hace nada.
 */
export async function cerrarGuia(page: Page): Promise<void> {
  const globo = page.locator('.tour-globo');

  // Se abre desde JS al cargar, así que puede tardar un instante; y en una
  // pantalla ya vista no llega nunca. Sondeo corto y seguimos.
  try {
    await globo.waitFor({ state: 'visible', timeout: 3000 });
  } catch {
    return;
  }

  await page.locator('.tour-cerrar').click();
  await globo.waitFor({ state: 'hidden' });
}
