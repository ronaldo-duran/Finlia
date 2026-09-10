# Despliegue — Finlia en Hostinger

> Guía para desplegar Finlia en **hosting compartido de Hostinger**. El objetivo: cero procesos persistentes obligatorios; todo vía PHP-FPM + cron.

## 1. Requisitos del entorno

- PHP **8.4** (verificar en el panel de Hostinger → Advanced → PHP Configuration).
  No vale 8.3: las dependencias bloqueadas en `composer.lock` incluyen Symfony 8.1, que exige `php >= 8.4.1`. Con 8.3 `composer install` falla antes de empezar.
- Extensiones: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `gd`/`imagick` (si hay imágenes), `fileinfo`.
- **MySQL/MariaDB** (con `utf8mb4`) **o PostgreSQL** — ambos soportados ([ADR-0036](DECISIONS.md#adr-0036)). En hosting compartido lo habitual es MySQL.
- Acceso **SSH** (recomendado) o File Manager + terminal.
- Cron disponible (Hostinger lo permite).

## 2. Estructura de carpetas en Hostinger

Hostinger sirve desde `public_html`. Laravel sirve desde `public/`. Dos opciones:

**Opción A (recomendada): dominio apunta a `public/`**
- Sube el proyecto a `domains/tudominio/finlia/` (fuera de `public_html`).
- En el panel, apunta el **document root** del dominio a `.../finlia/public`.
- Así `storage/`, `.env`, `app/` quedan **fuera** de la raíz pública. ✅

**Opción B (si no se puede cambiar el document root)**
- Sube todo dentro de `public_html` y mueve `public/index.php` y `public/.htaccess` a la raíz, ajustando rutas (`__DIR__.'/../laravel/bootstrap/app.php'` → path real).
- Asegúrate de que `.env` y `vendor/` **no** sean accesibles vía web (bloquear con `.htaccess`).

> La **Opción A** es más segura. Úsala siempre que sea posible.

### Dos hosts: sitio público y aplicación

Finlia se sirve desde **dos dominios que apuntan al mismo `public/`** ([ADR-0038](DECISIONS.md#adr-0038)):

| Host | Qué sirve |
|---|---|
| `finlia.online` | Sitio público: landing y, más adelante, precios y testimonios |
| `app.finlia.online` | La aplicación. Aquí se instala la PWA — y de aquí **no se mueve nunca** |

En Hostinger: crea el subdominio `app` con el **mismo document root** que el dominio principal. No hace falta un segundo despliegue ni una segunda base de datos: es la misma aplicación Laravel, que reparte por `Host`.

> ⚠️ **`APP_URL` apunta al host de la aplicación**, no a la raíz. La landing genera sus enlaces de «Entrar» y «Crear cuenta» con `route()`, y sin esto mandarían al host equivocado.

> 🔒 La cookie de sesión queda acotada a `app.finlia.online` (con `SESSION_DOMAIN=null`, que es el valor por defecto). **No la abras a `.finlia.online`**: el sitio público no necesita sesión, y compartir la cookie con la raíz solo amplía la superficie sin dar nada.


## 3. Pasos de despliegue (SSH)

```bash
# En el servidor
cd domains/tudominio/        # o donde alojes el proyecto
git clone https://github.com/<usuario>/finlia.git
cd finlia

composer install --no-dev --optimize-autoloader

# .env (NO subas el .env local; crea uno de producción en el servidor)
cp .env.example .env
php artisan key:generate
# Edita .env con los valores de producción (ver sección 4)

php artisan migrate --force          # correr migraciones
php artisan storage:link             # enlace simbólico de storage
npm install --ignore-scripts
npm run build                        # genera public/build
```

## 4. `.env` de producción (valores clave)

```env
APP_NAME=Finlia
APP_ENV=production
APP_KEY=            # generada con php artisan key:generate
APP_DEBUG=false
APP_URL=https://app.tudominio.com    # el host de la APLICACIÓN (ver §2)

APP_TIMEZONE=America/Bogota

# Reparto en dos hosts (ADR-0038). Vacíos = un solo host, con la landing en «/».
FINLIA_MARKETING_DOMAIN=finlia.online
FINLIA_APP_DOMAIN=app.finlia.online
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_CO

LOG_CHANNEL=stack
LOG_STACK=single        # o 'stderr'/'syslog' según Hostinger; archivo en storage/logs

DB_CONNECTION=mysql
DB_HOST=localhost       # Hostinger suele usar localhost
DB_PORT=3306
DB_DATABASE=u123456_finlia
DB_USERNAME=u123456_finlia
DB_PASSWORD=contraseña_fuerte_y_secreta

SESSION_DRIVER=database
CACHE_STORE=database            # o 'file' (compatible hosting compartido)
QUEUE_CONNECTION=database       # se procesa vía cron, no worker persistente

FILESYSTEM_DISK=local           # o 'public' para assets accesibles

# Correo: invitaciones + recuperación (ADR-0015) y digest de recordatorios
# (ADR-0028). Proveedor elegido: Brevo free (300 correos/día) vía SMTP puro
# — cero código acoplado al proveedor, cambiarlo es cambiar este bloque.
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=no-responder@tudominio.com
MAIL_PASSWORD=smtp-key-de-brevo     # Brevo → SMTP & API → SMTP keys (NO la contraseña de la cuenta)
MAIL_SCHEME=smtp                   # 587 = smtp (STARTTLS)
MAIL_FROM_ADDRESS=no-responder@tudominio.com
MAIL_FROM_NAME="${APP_NAME}"
FINLIA_MAIL_ENABLED=true        # ponlo en false para apagar TODO el correo (invitaciones y digest)
FINLIA_CONTACT_EMAIL=hola@tudominio.com   # buzón del formulario de contacto y los reportes de error
```

> **Brevo en 4 pasos** ([ADR-0028](DECISIONS.md#adr-0028)): (1) cuenta gratis en brevo.com → 300 correos/día; (2) **autenticar el dominio** en Brevo → Senders & IP → Senders (añade los registros SPF/DKIM que te da en la zona DNS de Hostinger; sin esto acaba en spam); (3) crear una **SMTP key** y usarla como `MAIL_PASSWORD`; (4) usar un remitente **del propio dominio** — Brevo no deja enviar desde gmail/outlook sin verificación. La alternativa sin Brevo es el SMTP del propio Hostinger (`smtp.hostinger.com:465`, `MAIL_SCHEME=smtps`), pero su límite diario es menor.
>
> **Cuota**: 300/día cubre con holgura el digest (máx. 1 por miembro y hogar al día, solo con urgentes). Si algún día se queda corta, subir de plan o cambiar de proveedor es solo `.env`.
>
> **Sin SMTP la app funciona igual.** Con `MAIL_MAILER=log` los correos van a `storage/logs`, la invitación se comparte con el enlace manual y el digest ni siquiera corre: `mail_is_deliverable()` trata `log`/`array` como "no hay bandeja real" y el comando se salta el envío sin marcar el pivote (ADR-0015).
>
> **Entregabilidad**: además del SPF/DKIM de Brevo, comprueba el envío con `php artisan tinker` → `Mail::raw('prueba', fn ($m) => $m->to('tucorreo@ejemplo.com')->subject('Prueba Finlia'));`

> Configuración de Colombia (timezone, locale, COP) se establece en **Épica 1**. Los valores exactos para `config/app.php` y `.env` se documentan aquí como referencia.

## 5. Optimización (producción)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

⚠️ **Cuidado con `config:cache`**: si usas `env()` fuera de archivos de config, dejará de funcionar en producción. Llama siempre a `config(...)`, no a `env(...)` en código de aplicación.

## 6. Cron (Scheduler y colas)

Hostinger → **Advanced → Cron Jobs**. Ejecutar cada minuto:

```bash
* * * * * cd /home/u123456/domains/tudominio/finlia && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Para **colas** (si se usan) sin worker persistente, procesar dentro del scheduler o con un cron dedicado:

```bash
* * * * * cd .../finlia && /usr/local/bin/php artisan queue:work --stop-when-empty --tries=1 >> /dev/null 2>&1
```

> **Principio**: nada que requiera un proceso **24/7**. Si una función lo necesita, rediseñarla para cron/cola programada. Ver Épica 9.
>
> El correo transaccional (invitaciones, recuperación de contraseña) se envía **de forma síncrona** y **no depende del cron** (ADR-0015). Son dos correos puntuales disparados por una acción del usuario; encolarlos sin este cron activo los perdería en silencio.
>
> El **digest de recordatorios** (`finlia:send-reminder-digests`, 06:30) sí vive en el Scheduler y es el único correo en lote (ADR-0028): síncrono dentro de la corrida — corre en el proceso del cron, nunca añade latencia a la app — con `try/catch` por destinatario y `withoutOverlapping()` para que una corrida larga no se solape con la del minuto siguiente. Si el volumen creciera hasta hacer larga la corrida (umbral: ~200–250 digest diarios, donde también se cruza la cuota free de Brevo), la Fase 2 de ADR-0028 es un Job `SendReminderDigest` por destinatario despachado desde el comando y procesado por el `queue:work --stop-when-empty` de arriba; el Job marca el pivote tras enviar de verdad, y el comando pasa de enviar a despachar.

## 7. Permisos

```bash
chmod -R 775 storage bootstrap/cache
chown -R <usuario>:<grupo> storage bootstrap/cache
```

`storage/` y `bootstrap/cache/` necesitan escritura; el resto, lectura/ejecución.

## 8. HTTPS y cabeceras

**Ya viene resuelto en el repo**: `public/.htaccess` trae la redirección a HTTPS y las
cabeceras de seguridad. No hay que añadir nada a mano en el servidor.

- Activar el certificado gratuito en el panel de Hostinger. Con eso basta: la
  redirección HTTP → HTTPS del `.htaccess` empieza a funcionar sola.
- El desarrollo local **no** redirige: `localhost`, `127.0.0.1` y los dominios
  `.test`/`.localhost` están excluidos, porque XAMPP sirve por http y una
  redirección incondicional dejaría el proyecto inaccesible al trabajar.
- Detrás de un proxy que termine el TLS, `X-Forwarded-Proto: https` también
  evita el bucle de redirección.

Cabeceras que se emiten (con `always`, así salen también en 403/404/500):

| Cabecera | Valor |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=(), usb=()` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` — **solo sobre TLS** |

> ⚠️ **HSTS se activa solo** en cuanto el dominio sirva por HTTPS (va condicionada a
> la variable `HTTPS`, así que en local nunca se emite). Es un compromiso de un año:
> el navegador se negará a hablar http con el dominio hasta que expire. Si el
> certificado aún no está listo, comenta esa línea del `.htaccess` antes de subir.

> **Sin CSP a propósito.** Una decena de vistas llevan `<script>` en línea (datos de
> los gráficos, chips de categoría, simulador de deuda), así que un `script-src 'self'`
> rompería la app. Añadirla exige antes mover esos bloques a ficheros o darles un
> nonce por petición.

> `mod_headers` y `mod_rewrite` están activos por defecto en Hostinger (LiteSpeed).
> Si algún día no lo estuvieran, los bloques `<IfModule>` hacen que el fichero se
> ignore en silencio: comprueba las cabeceras con `curl -I https://tudominio.com`
> después del primer despliegue.

## 9. Despliegue: artefacto construido en CI

Hostinger **no tiene Node ni Composer**, así que no se puede compilar en el servidor. Y publicar el código fuente sin compilar no sirve: `vendor/` y `public/build` están en `.gitignore`, de modo que un `git pull` del repositorio de código deja la aplicación sin dependencias y sin estilos.

La solución son **dos repositorios**:

| Repositorio | Qué contiene |
|---|---|
| `ronaldo-duran/Finlia` (público) | El código. Sin `vendor/` ni `public/build` |
| `ronaldo-duran/finlia-produccion` (privado) | El **artefacto desplegable**: lo mismo **más** `vendor/` y `public/build` |

### Cómo se publica

Al empujar un tag `v*`, el workflow `.github/workflows/deploy-to-production.yml`:

1. Saca **el tag** (no `main`: se despliega lo etiquetado, no lo que haya avanzado después).
2. Instala con **PHP 8.3**, la misma versión de Hostinger. Resolver con una más nueva puede traer paquetes que el servidor no ejecute.
3. `composer install --no-dev --optimize-autoloader` y `npm ci && npm run build`.
4. Comprueba que `public/build/manifest.json` y `vendor/` existen — si no, falla en vez de publicar un artefacto roto.
5. Copia todo al repositorio de producción **con su propio `.gitignore`**, donde `vendor/` y `public/build` sí se versionan.

Se excluyen del artefacto: `.github`, `node_modules`, `tests`, `scrum`, cualquier `.env` y los ficheros que genera el servidor en caliente (logs, caché de vistas y sesiones).

### En el servidor

```bash
cd ~/domains/finlia.online/finlia
git fetch origin && git reset --hard origin/main
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

> `reset --hard` y no `pull`: el artefacto se reescribe entero en cada despliegue, así que un merge no tiene sentido. Todo lo que el servidor necesita conservar —`.env`, `storage/`— está fuera del control de git.

### El token

El workflow empuja con `FINLIA_PROD_TOKEN`, un secreto del repositorio de código. Si es un **token de acceso personal de alcance fino**, necesita:

- **Repository access** → incluir explícitamente `finlia-produccion`.
- **Repository permissions** → **Contents: Read and write**.

Si es un token clásico, el permiso `repo`. Sin alguna de las dos cosas GitHub responde `Repository not found` — un 404 en lugar de un 403, para no revelar que el repositorio existe.

> Alternativa más segura: una **clave de despliegue** con permiso de escritura en `finlia-produccion`. Solo alcanza a ese repositorio, no a toda la cuenta.

## 10. Troubleshooting

| Síntoma | Causa probable | Solución |
|---|---|---|
| 500 en todas las rutas | `.env` falta o `APP_KEY` vacía | `php artisan key:generate` |
| `storage/logs/laravel.log` no escribe | Permisos de `storage/` | `chmod -R 775 storage` |
| Assets 404 | No se compiló o falta `storage:link` | `npm run build` + `php artisan storage:link` |
| `env()` devuelve null en prod | `config:cache` activo | Usar `config(...)`; nunca `env()` en app |
| Cron no ejecuta scheduler | Path de PHP o ruta incorrecta | Usar ruta absoluta de `php` y del proyecto |
| Pantalla blanca | `APP_DEBUG=false` oculta error | Revisar `storage/logs/laravel.log` |

## 11. Backups

- Exportar la base de datos periódicamente (cron con `mysqldump` + cifrado, o panel de Hostinger).
- **Nunca** guardar backups en el repositorio.
