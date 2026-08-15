import { defineConfig, devices } from '@playwright/test';

/**
 * E2E de Finlia con Playwright (Chromium).
 *
 * El servidor se levanta sobre un entorno aislado de e2e: SQLite en archivo
 * (`database/playwright.sqlite`), sesiones/cache en BD y datos FALSOS del
 * `DatabaseSeeder` (usuario demo@finlia.test). La configuración se inyecta
 * como variables de entorno reales para no depender de ningún archivo .env:
 * funciona igual en local que en el runner de GitHub Actions.
 */
export default defineConfig({
  testDir: './tests/e2e',

  // `php artisan serve` atiende con un único worker en Windows: sin paralelismo
  // para que las peticiones nunca compitan por el servidor integrado.
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,

  reporter: [['list'], ['html', { open: 'never' }]],

  use: {
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8890',
    locale: 'es-CO',
    timezoneId: 'America/Bogota',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    // Login único (guarda la sesión en storageState) para no agotar el
    // rate-limit de /login (throttle:5,1) con un login por test.
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
    },
    {
      name: 'chromium',
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'playwright/.auth/demo.json',
      },
    },
  ],

  webServer: {
    // BD fresca con seed en cada corrida + servidor integrado de Laravel.
    command:
      'php artisan migrate:fresh --seed --force && php artisan serve --host=127.0.0.1 --port=8890',
    url: 'http://127.0.0.1:8890/login',
    reuseExistingServer: !process.env.CI,
    timeout: 240_000,
    env: {
      APP_ENV: 'playwright',
      // Key exclusiva de pruebas (no es un secreto de producción).
      APP_KEY: 'base64:jMSqqw8tIIbJovE7naPPagl7Gr3hxtYHzosTkGsJL/s=',
      APP_URL: 'http://127.0.0.1:8890',
      APP_DEBUG: 'true',
      APP_LOCALE: 'es',
      APP_FALLBACK_LOCALE: 'es',
      APP_FAKER_LOCALE: 'es_CO',
      APP_TIMEZONE: 'America/Bogota',
      LOG_LEVEL: 'warning',
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: 'database/playwright.sqlite',
      SESSION_DRIVER: 'database',
      CACHE_STORE: 'database',
      QUEUE_CONNECTION: 'sync',
      MAIL_MAILER: 'log',
      // Hashing barato para acelerar el login en la suite.
      BCRYPT_ROUNDS: '4',
    },
  },
});
