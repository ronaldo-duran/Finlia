/**
 * Captura los screenshots del README (docs/img/).
 *
 * No es un test: es una herramienta de documentación, por eso vive fuera de
 * tests/e2e y no la recoge `npm run test:e2e`. Usa la API de Playwright
 * directamente contra un servidor que ya esté corriendo.
 *
 *   php artisan serve --port=8899        (con datos del seeder)
 *   npm run screenshots
 *
 * Los datos son los del DatabaseSeeder: FALSOS, generados con Faker es_CO.
 * Nunca se capturan datos reales de nadie.
 */
import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.SHOTS_BASE_URL ?? 'http://127.0.0.1:8899';
const SALIDA = 'docs/img';

// Viewport de teléfono: Finlia es mobile-first y así es como se usa a diario.
const VIEWPORT = { width: 390, height: 844 };

const PANTALLAS = [
    { ruta: '/dashboard', archivo: 'panel.png', espera: 'canvas' },
    { ruta: '/presupuestos', archivo: 'dinero-disponible.png' },
    { ruta: '/gastos/crear', archivo: 'registrar-gasto.png' },
    // Los gráficos viven bajo el pliegue: sin desplazar, la captura de
    // reportes sale toda de texto y no enseña Chart.js.
    { ruta: '/reportes', archivo: 'reportes.png', espera: 'canvas', desplazarA: 'canvas' },
    // El aviso de "valores aproximados" ocupa media pantalla la primera vez.
    // Se descarta como lo haría el usuario, para que se vean los KPIs.
    { ruta: '/deudas', archivo: 'deudas.png', descartarAviso: true },
    { ruta: '/metas', archivo: 'metas.png' },
];

const navegador = await chromium.launch();
const contexto = await navegador.newContext({
    viewport: VIEWPORT,
    deviceScaleFactor: 2, // nítido en pantallas retina
    locale: 'es-CO',
    timezoneId: 'America/Bogota',
    // Sin animaciones: evita capturar una barra de progreso a medias.
    reducedMotion: 'reduce',
});

const pagina = await contexto.newPage();

await mkdir(SALIDA, { recursive: true });

// Login con el usuario de demostración del seeder.
await pagina.goto(`${BASE}/login`);
await pagina.fill('input[name="email"]', 'demo@finlia.test');
await pagina.fill('input[name="password"]', 'finlia123');
await pagina.getByRole('button', { name: 'Iniciar sesión' }).click();
await pagina.waitForURL(/\/dashboard$/);

for (const { ruta, archivo, espera, desplazarA, descartarAviso } of PANTALLAS) {
    await pagina.goto(`${BASE}${ruta}`, { waitUntil: 'networkidle' });

    if (descartarAviso) {
        const boton = pagina.getByRole('button', { name: /no mostrar de nuevo/i });
        if (await boton.count()) {
            await boton.first().click();
            await pagina.waitForLoadState('networkidle');
        }
    }

    // Los gráficos de Chart.js pintan tras el load: sin esto salen en blanco.
    if (espera) {
        await pagina.waitForSelector(espera, { timeout: 10_000 }).catch(() => {});
        await pagina.waitForTimeout(600);
    }

    if (desplazarA) {
        await pagina.locator(desplazarA).first().scrollIntoViewIfNeeded().catch(() => {});
        await pagina.waitForTimeout(400);
    }

    await pagina.screenshot({ path: `${SALIDA}/${archivo}` });
    console.log(`✓ ${SALIDA}/${archivo}  ←  ${ruta}`);
}

await navegador.close();
console.log('\nListo. Revisa que ninguna captura contenga datos reales antes de commitear.');
