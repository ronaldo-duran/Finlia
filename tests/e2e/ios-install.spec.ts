import { test, expect } from '@playwright/test';

/**
 * Aviso de instalación en iOS.
 *
 * Safari no dispara `beforeinstallprompt`, así que en iPhone el único camino
 * es explicarle al usuario los pasos del menú Compartir. Lo que estos tests
 * protegen no es el texto, sino **a quién se le enseña**: mostrarlo donde no
 * toca (Android, escritorio, o dentro de la app ya instalada) es ruido, y
 * mostrar los pasos de Safari en Chrome iOS es enviar al usuario a un callejón
 * sin salida, porque ahí "Añadir a pantalla de inicio" crea un marcador y no
 * una app.
 *
 * Cada caso usa su propio contexto con el user-agent correspondiente, pero
 * reutiliza el `storageState` de la suite: un login por test agotaría el
 * `throttle:5,1` de /login.
 */
const UA = {
  iphoneSafari:
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  iphoneChrome:
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0.0.0 Mobile/15E148 Safari/604.1',
  android:
    'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
};

/** Abre el panel con un user-agent concreto, reutilizando la sesión guardada. */
async function panelCon(browser, userAgent: string, initScript?: () => void) {
  const context = await browser.newContext({
    userAgent,
    storageState: 'playwright/.auth/demo.json',
    viewport: { width: 390, height: 844 },
    isMobile: true,
    hasTouch: true,
  });

  if (initScript) {
    await context.addInitScript(initScript);
  }

  const page = await context.newPage();
  await page.goto('/dashboard');

  return { context, page };
}

test.describe('Instalación en iOS (PWA)', () => {
  test('se ofrece en iPhone con Safari', async ({ browser }) => {
    const { context, page } = await panelCon(browser, UA.iphoneSafari);

    await expect(page.locator('#iosInstallBanner')).toBeVisible();

    await context.close();
  });

  test('no se ofrece en Android, que ya tiene el aviso nativo', async ({ browser }) => {
    const { context, page } = await panelCon(browser, UA.android);

    await expect(page.locator('#iosInstallBanner')).toBeHidden();

    await context.close();
  });

  test('no se ofrece dentro de la app ya instalada', async ({ browser }) => {
    const { context, page } = await panelCon(browser, UA.iphoneSafari, () => {
      // `navigator.standalone === true` es como iOS marca que la página corre
      // desde el icono del inicio y no dentro de Safari.
      Object.defineProperty(window.navigator, 'standalone', { value: true });
    });

    await expect(page.locator('#iosInstallBanner')).toBeHidden();

    await context.close();
  });

  test('no reaparece una vez descartado', async ({ browser }) => {
    const { context, page } = await panelCon(browser, UA.iphoneSafari, () => {
      localStorage.setItem('finlia_ios_install_dismissed', '1');
    });

    await expect(page.locator('#iosInstallBanner')).toBeHidden();

    await context.close();
  });

  test('en Safari muestra los pasos reales del menú Compartir', async ({ browser }) => {
    const { context, page } = await panelCon(browser, UA.iphoneSafari);

    await page.locator('#iosInstallOpen').click();
    await expect(page.locator('#iosInstallModal')).toBeVisible();

    await expect(page.locator('#iosInstallStepsSafari')).toBeVisible();
    await expect(page.locator('#iosInstallStepsOtro')).toBeHidden();
    await expect(page.locator('#iosInstallStepsSafari .ios-install-steps li')).toHaveCount(3);
    await expect(page.getByText('Añadir a pantalla de inicio').first()).toBeVisible();

    await context.close();
  });

  test('en Chrome de iPhone redirige a Safari en vez de dar pasos inútiles', async ({ browser }) => {
    const { context, page } = await panelCon(browser, UA.iphoneChrome);

    await expect(page.locator('#iosInstallBanner')).toBeVisible();
    await page.locator('#iosInstallOpen').click();
    await expect(page.locator('#iosInstallModal')).toBeVisible();

    // Los pasos de Safari aquí no valen: su "añadir" crea un marcador.
    await expect(page.locator('#iosInstallStepsSafari')).toBeHidden();
    await expect(page.locator('#iosInstallStepsOtro')).toBeVisible();
    await expect(page.getByText('solo', { exact: false }).first()).toBeVisible();

    await context.close();
  });

  test('descartarlo lo quita y lo recuerda tras recargar', async ({ browser }) => {
    const { context, page } = await panelCon(browser, UA.iphoneSafari);

    await expect(page.locator('#iosInstallBanner')).toBeVisible();
    await page.locator('#iosInstallDismiss').click();
    await expect(page.locator('#iosInstallBanner')).toHaveCount(0);

    await page.reload();
    await expect(page.locator('#iosInstallBanner')).toBeHidden();

    await context.close();
  });
});
