# Despliegue — Finlia en Hostinger

> Guía para desplegar Finlia en **hosting compartido de Hostinger**. El objetivo: cero procesos persistentes obligatorios; todo vía PHP-FPM + cron.

## 1. Requisitos del entorno

- PHP **8.3** (verificar en el panel de Hostinger → Advanced → PHP Configuration).
- Extensiones: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `gd`/`imagick` (si hay imágenes), `fileinfo`.
- MySQL/MariaDB con `utf8mb4`.
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
APP_URL=https://tudominio.com

APP_TIMEZONE=America/Bogota
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
> El **digest de recordatorios** (`finlia:send-reminder-digests`, 06:30) sí vive en el Scheduler y es el único correo en lote (ADR-0028): síncrono dentro de la corrida, con `try/catch` por destinatario para que un buzón que rebota no frene al resto. Si el volumen creciera hasta hacer larga la corrida, el seam es `ShouldQueue` en el Mailable + el `queue:work --stop-when-empty` de arriba.

## 7. Permisos

```bash
chmod -R 775 storage bootstrap/cache
chown -R <usuario>:<grupo> storage bootstrap/cache
```

`storage/` y `bootstrap/cache/` necesitan escritura; el resto, lectura/ejecución.

## 8. HTTPS y cabeceras

- Forzar HTTPS en el panel de Hostinger (certificado gratuito).
- En `public/.htaccess` (Opción A) redirigir HTTP → HTTPS y añadir cabeceras:
  ```apache
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "DENY"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  ```
  (HSTS y CSP según convenga.)

## 9. Despliegues posteriores (CI/CD opcional)

Para updates manuales:
```bash
cd .../finlia
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
npm ci && npm run build
```

O un script `deploy.sh` con estos pasos. CI/CD vía GitHub Actions a Hostinger (SSH/rsync) es posible más adelante.

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
