# Despliegue — Finlia en Hostinger

> Guía para desplegar Finlia en **hosting compartido de Hostinger**. El objetivo: cero procesos persistentes obligatorios; todo vía PHP-FPM + cron.

## 1. Requisitos del entorno

- PHP **8.4** (verificar en el panel de Hostinger → Advanced → PHP Configuration).
  No vale 8.3: las dependencias bloqueadas en `composer.lock` incluyen Symfony 8.1, que exige `php >= 8.4.1`. Con 8.3 `composer install` falla antes de empezar.
- Extensiones: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `gd`/`imagick` (si hay imágenes), `fileinfo`.
- **MySQL/MariaDB** (con `utf8mb4`) **o PostgreSQL** — ambos soportados ([ADR-0036](DECISIONS.md#adr-0036)). En hosting compartido lo habitual es MySQL.
- Acceso **SSH** (recomendado) o File Manager + terminal.
- Cron disponible (Hostinger lo permite).

> ⚠️ **El `php` del shell NO es el de producción.** En Hostinger la terminal responde con una versión antigua (8.2 en la máquina donde se desplegó por primera vez) mientras el sitio web corre la que elegiste en el panel. Los binarios por versión viven en `/opt/alt/phpXX/usr/bin/php`:
>
> ```bash
> php -v                          # 8.2 — NO sirve
> /opt/alt/php84/usr/bin/php -v   # 8.4 — este
> ```
>
> **Todos** los comandos de artisan y **la línea del cron** tienen que usar la ruta absoluta. Con el `php` por defecto los comandos fallan con errores de sintaxis desconcertantes, y el cron lo haría en silencio cada noche. Para no repetirla en cada comando de una sesión SSH:
>
> ```bash
> export PHP84=/opt/alt/php84/usr/bin/php
> $PHP84 artisan migrate --force
> ```
>
> La versión del subdominio **se configura por separado**: Hostinger lo trata como otro sitio y puede dejarlo en la versión por defecto aunque el dominio principal esté en 8.4. Lo mismo vale para su certificado SSL.

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

### Crear el subdominio: tres trampas, todas encontradas en el primer despliegue

**1. No uses «carpeta personalizada».** El campo está restringido a rutas dentro de `/public_html/`, así que no puede apuntar a `domains/finlia.online/finlia/public`. Marca en su lugar la opción de **usar el directorio `public_html`**: como el `public_html` del dominio principal ya es un enlace simbólico a `finlia/public`, los dos hosts acaban compartiendo el mismo document root, que es justo lo que se quiere.

En la lista de subdominios debe quedar así, **sin nada detrás de `public_html`**:

```
app.finlia.online    /home/uXXXXXXXX/domains/finlia.online/public_html
```

**2. Si usaste carpeta personalizada, Hostinger escribe dentro del `public/` de Laravel.** Como `public_html` es un enlace, todo lo que el panel «crea» en el document root cae en la raíz web real: deja una carpeta vacía con el nombre que pusiste y su página de bienvenida `default.php` — que es servible, así que `finlia.online/default.php` mostraría la página de aparcamiento de Hostinger.

```bash
rm -rf public/app public/default.php
```

Esos ficheros **no están en git**, así que el `git reset --hard` de los despliegues siguientes no los va a borrar. Hay que quitarlos a mano una vez.

**3. La trampa cara: Hostinger crea el subdominio como ALIAS a su CDN.** En la zona DNS aparece:

```
ALIAS   app   →   app.finlia.online.cdn.hstgr.net
```

Ese nodo del CDN no tiene vhost ni certificado para el nombre, así que **el handshake TLS falla** (`ERR_SSL_PROTOCOL_ERROR` en el navegador) aunque el panel muestre el SSL como activo y aunque por HTTP responda un 301. Es un síntoma que engaña: todo parece correcto menos el resultado.

El arreglo, **en la cuenta que administra la zona DNS** (que puede no ser la del hosting, si el dominio se compró en otra):

1. Borra el registro **ALIAS** de `app`. El panel rechaza tener ALIAS y A en el mismo nombre — da `RRset ... must not be used with A on the same name`.
2. Crea un registro **A** de `app` apuntando a la IP del hosting, con TTL 300 mientras pruebas.

> **No añadas un `AAAA` para `app`** salvo que sea exactamente la IPv6 de ese vhost. Los navegadores prefieren IPv6, así que un `AAAA` mal apuntado reproduce el mismo error pero ya sin pista de por qué. Con solo el registro A funciona.

**Sacar la aplicación del CDN no es un parche, es lo correcto.** Todo lo que sirve `app.` es autenticado y personalizado —panel, movimientos, saldos— y nada de eso es cacheable: un CDN delante no ahorra nada y añade el riesgo de que una respuesta con datos de sesión se sirva a otra persona. En una aplicación de finanzas familiares ese es el peor fallo posible. El CDN sí tiene sentido en `finlia.online`, donde la landing es estática: si la raíz tiene su propio ALIAS al CDN y funciona, **déjalo**.

### Diagnóstico cuando un host no responde

Estos cuatro comandos separan las causas en lugar de adivinar. Córrelos **desde el servidor**, cuyo `curl` usa OpenSSL y da mensajes fiables:

```bash
# ¿Responde por HTTP? Un 301 significa que el vhost existe y solo falta el TLS.
curl -sI -m 15 http://app.finlia.online/login | head -1

# ¿Es solo el certificado? -k ignora su validez.
curl -skI -m 15 https://app.finlia.online/login | head -1

# ¿Falla por un protocolo y no por el otro? Si IPv6 falla e IPv4 no, sospecha del AAAA.
curl -4 -sI -m 15 https://app.finlia.online/login | head -1
curl -6 -sI -m 15 https://app.finlia.online/login | head -1

# ¿A qué IP resuelve de verdad, sin caché local? Debe coincidir con la del dominio raíz.
getent ahostsv4 finlia.online | head -1
nslookup app.finlia.online 8.8.8.8
```

> 🚪 **Salida de emergencia.** Mientras un host no funcione, **vacía las dos variables de dominio** y todo vuelve a responder en un solo host —landing en la raíz, aplicación en `/login`—, que es la configuración que corre en local y en toda la suite de tests:
>
> ```bash
> sed -i 's/^FINLIA_MARKETING_DOMAIN=.*/FINLIA_MARKETING_DOMAIN=/; s/^FINLIA_APP_DOMAIN=.*/FINLIA_APP_DOMAIN=/' .env
> php artisan config:cache && php artisan route:cache
> ```
>
> Mientras estés así, **no instales la PWA** ni se lo pidas a nadie: quedaría atada al host equivocado y una aplicación instalada no se puede migrar de origen ([ADR-0038](DECISIONS.md#adr-0038)).


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
* * * * * cd /home/u123456/domains/finlia.online/finlia && /opt/alt/php84/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

> ⚠️ **La ruta del PHP es la de 8.4, no `php` a secas ni `/usr/local/bin/php`** (ver [§1](#1-requisitos-del-entorno)). Con la versión por defecto del shell el cron falla **en silencio** cada noche: no se generan los gastos recurrentes automáticos, no sale el digest y no se purgan las cuentas vencidas, sin un solo error visible en la aplicación. Compruébalo la primera vez ejecutando la misma línea a mano y mirando que no imprima nada raro.

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
| `ERR_SSL_PROTOCOL_ERROR` en `app.` y el panel dice que el SSL está activo | El subdominio es un **ALIAS al CDN** de Hostinger, que no tiene certificado para ese nombre | Borrar el ALIAS y crear un registro **A** a la IP del hosting ([§2](#crear-el-subdominio-tres-trampas-todas-encontradas-en-el-primer-despliegue)) |
| `Please provide a valid cache path` en cualquier comando de artisan | Falta el árbol de `storage/framework` | `mkdir -p storage/framework/{views,cache/data,sessions} storage/logs bootstrap/cache` |
| El comando de artisan falla con errores de sintaxis raros | El `php` del shell no es el de producción | Usar la ruta absoluta del binario correcto ([§1](#1-requisitos-del-entorno)) |
| Cambié el `.env` y no pasa nada | La configuración está cacheada | `php artisan config:cache` — y `route:cache` si cambiaron las variables de dominio |

## 11. Backups

- Exportar la base de datos periódicamente (cron con `mysqldump` + cifrado, o panel de Hostinger).
- **Nunca** guardar backups en el repositorio.
