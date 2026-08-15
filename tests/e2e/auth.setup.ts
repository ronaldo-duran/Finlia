import { test as setup } from '@playwright/test';
import { DEMO_USER } from './helpers';

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

  await page.context().storageState({ path: 'playwright/.auth/demo.json' });
});
