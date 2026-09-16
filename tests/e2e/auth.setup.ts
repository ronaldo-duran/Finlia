import { test as setup } from '@playwright/test';
import { DEMO_USER, cerrarGuia } from './helpers';

/**
 * Login único para toda la suite: guarda la sesión (storageState) que reutilizan
 * los specs autenticados. Evita agotar el rate-limit de /login (throttle:5,1).
 */
setup('autenticar usuario demo', async ({ page }) => {
  await page.goto('/login');
  await page.fill('input[name="email"]', DEMO_USER.email);
  await page.fill('input[name="password"]', DEMO_USER.password);
  await page.getByRole('button', { name: 'Iniciar sesión' }).click();
  await page.waitForURL(/\/dashboard$/);

  // La guía de bienvenida (ADR-0045) se abre aquí, en la primera visita al
  // Panel. Se descarta ANTES de guardar la sesión para que el resto de la
  // suite parta de un estado explícito: guía del Panel vista, y ninguna otra
  // saltando sola. Sin esto funcionaba igual, pero de rebote —por el tope de
  // «una por sesión»—, y bastaba tocar esa regla para romper la suite entera.
  await cerrarGuia(page);

  await page.context().storageState({ path: 'playwright/.auth/demo.json' });
});
