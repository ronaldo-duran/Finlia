import type { Page } from '@playwright/test';

/**
 * Credenciales del usuario demo que siembra DatabaseSeeder (datos FALSOS).
 */
export const DEMO_USER = {
  name: 'Usuario Demo Finlia',
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
