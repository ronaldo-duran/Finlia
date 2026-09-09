/**
 * Captura las imágenes del README y del sitio público (public/img/).
 *
 * No es un test: es una herramienta de documentación, por eso vive fuera de
 * tests/e2e y no la recoge `npm run test:e2e`. Usa la API de Playwright
 * directamente contra un servidor que ya esté corriendo.
 *
 *   php artisan serve --port=8899                    (con datos del seeder)
 *   SHOTS_PASSWORD=<clave del usuario demo> npm run screenshots
 *
 * Los datos son los del DatabaseSeeder: FALSOS, generados con Faker es_CO.
 * Nunca se capturan datos reales de nadie.
 */
import { chromium } from '@playwright/test';
import { mkdir } from 'node:fs/promises';

const BASE = process.env.SHOTS_BASE_URL ?? 'http://127.0.0.1:8899';

// Viewport de teléfono: Finlia es mobile-first y así es como se usa a diario.
const TELEFONO = { width: 390, height: 844 };

// Pantallas de la app, para el README y la landing.
const PANTALLAS = [
    { ruta: '/dashboard', archivo: 'img/capturas/panel.png', espera: 'canvas' },
    { ruta: '/presupuestos', archivo: 'img/capturas/dinero-disponible.png' },
    { ruta: '/gastos/crear', archivo: 'img/capturas/registrar-gasto.png' },
    // Los gráficos viven bajo el pliegue: sin desplazar, la captura de
    // reportes sale toda de texto y no enseña Chart.js.
    { ruta: '/reportes', archivo: 'img/capturas/reportes.png', espera: 'canvas', desplazarA: 'canvas' },
    // El aviso de "valores aproximados" ocupa media pantalla la primera vez.
    // Se descarta como lo haría el usuario, para que se vean los KPIs.
    { ruta: '/deudas', archivo: 'img/capturas/deudas.png', descartarAviso: true },
    { ruta: '/metas', archivo: 'img/capturas/metas.png' },
];

const navegador = await chromium.launch();

async function capturar(contexto, { ruta, archivo, espera, desplazarA, descartarAviso }) {
    const pagina = await contexto.newPage();
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

    await pagina.screenshot({ path: `public/${archivo}` });
    await pagina.close();
    console.log(`✓ public/${archivo}  ←  ${ruta}`);
}

await mkdir('public/img/capturas', { recursive: true });

// ---- Pantallas de la app (requieren sesión) --------------------------------
const sesion = await navegador.newContext({
    viewport: TELEFONO,
    deviceScaleFactor: 2, // nítido en pantallas retina
    locale: 'es-CO',
    timezoneId: 'America/Bogota',
    // Sin animaciones: evita capturar una barra de progreso a medias.
    reducedMotion: 'reduce',
});

// Credenciales del usuario que crea DatabaseSeeder. Se piden por entorno y no
// van escritas aquí: es una clave de demostración sobre una base local y
// desechable —está en el README—, pero un literal junto a un campo `password`
// lo marca cualquier escáner de secretos, y con razón: no se distingue de una
// credencial de verdad.
const USUARIO = process.env.SHOTS_USER ?? 'demo@finlia.test';
const CLAVE = process.env.SHOTS_PASSWORD;

if (!CLAVE) {
    console.error('Falta SHOTS_PASSWORD.');
    console.error('Es la clave del usuario de demostración que siembra DatabaseSeeder;');
    console.error('está en el README, sección «Instalación local».');
    console.error('');
    console.error('  SHOTS_PASSWORD=... npm run screenshots');
    await navegador.close();
    process.exit(1);
}

const login = await sesion.newPage();
await login.goto(`${BASE}/login`);
await login.fill('input[name="email"]', USUARIO);
await login.fill('input[name="password"]', CLAVE);
await login.getByRole('button', { name: 'Iniciar sesión' }).click();
await login.waitForURL(/\/dashboard$/);
await login.close();

for (const pantalla of PANTALLAS) {
    await capturar(sesion, pantalla);
}
await sesion.close();

// ---- Tarjeta para compartir (1200×630, sin sesión) -------------------------
//
// Se renderiza como página real (/og) en vez de dibujarse a mano, para que se
// regenere sola cuando cambie la marca o el mensaje del hero.
const social = await navegador.newContext({
    viewport: { width: 1200, height: 630 },
    deviceScaleFactor: 1, // 1200×630 es ya el tamaño exacto que piden las redes
    locale: 'es-CO',
    reducedMotion: 'reduce',
});

const og = await social.newPage();
await og.goto(`${BASE}/og`, { waitUntil: 'networkidle' });
await og.waitForTimeout(400);
await og.screenshot({ path: 'public/img/og-finlia.png' });
await og.close();
await social.close();
console.log('✓ public/img/og-finlia.png  ←  /og');

await navegador.close();
console.log('\nRevisa que ninguna captura contenga datos reales antes de commitear.');
