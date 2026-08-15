import { test, expect } from '@playwright/test';
import { DEMO_USER, loginAsDemo } from './helpers';

// Este spec necesita partir SIN sesión (el resto de la suite arranca logueado
// vía storageState del proyecto setup). Estado vacío explícito para
// sobreescribir el storageState del proyecto.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Autenticación', () => {
  test('la raíz redirige al login cuando no hay sesión', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('button', { name: 'Iniciar sesión' })).toBeVisible();
  });

  test('credenciales inválidas muestran error y permanecen en el login', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', DEMO_USER.email);
    await page.fill('input[name="password"]', 'contrasena-equivocada');
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();

    await expect(page.locator('.alert-danger').first()).toBeVisible();
    await expect(page).toHaveURL(/\/login$/);
  });

  test('el usuario demo puede iniciar y cerrar sesión', async ({ page }) => {
    await loginAsDemo(page);
    await expect(page.getByRole('heading', { name: `Hola, ${DEMO_USER.name}` })).toBeVisible();

    // Cerrar sesión desde el menú de usuario (avatar).
    await page.locator('.avatar-btn').click();
    await page.getByRole('button', { name: 'Cerrar sesión' }).click();
    await expect(page).toHaveURL(/\/login$/);
  });

  test('el registro crea la cuenta y el hogar inicial "Mi hogar"', async ({ page }) => {
    const email = `playwright-${Date.now()}@finlia.test`;

    await page.goto('/registro');
    await page.fill('input[name="name"]', 'Prueba Playwright');
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', 'ClavePlaywright1');
    await page.fill('input[name="password_confirmation"]', 'ClavePlaywright1');
    await page.getByRole('button', { name: 'Crear cuenta' }).click();

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByText('¡Bienvenido a Finlia')).toBeVisible();
    // Todo usuario arranca con su hogar personal activo (Épica 2).
    await expect(page.locator('.household-selector-btn')).toContainText('Mi hogar');
  });
});
