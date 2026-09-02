# Política de Seguridad — Finlia

> Finlia maneja **dinero real de familias**, será un **repositorio público** y se **monetizará**. Esta política es obligatoria. Cualquier cambio que la relaje debe revisarse explícitamente.

## Modelo de amenazas (resumen)

| # | Amenaza | Impacto | Probabilidad | Prioridad |
|---|---|---|---|---|
| 1 | Acceso a datos de otro hogar (IDOR / manipulación de ID/URL) | Crítico | Alta | 🔴 Máxima |
| 2 | Fuga de secretos al repo público (`.env`, keys, datos reales) | Crítico | Media | 🔴 Máxima |
| 3 | Mass assignment (escalar privilegios / inyectar `household_id`) | Alto | Media | 🟠 Alta |
| 4 | Datos reales en el repo | Alto | Media | 🟠 Alta |
| 5 | Almacenamiento de datos sensibles de tarjetas | Alto | Baja | 🟠 Alta |
| 6 | Desbordamiento de funcionalidad Premium por frontend | Medio | Media | 🟡 Media |
| 7 | XSS / CSRF / SQLi | Alto | Baja | 🟠 Alta |
| 8 | Brute force de login / registro | Medio | Alta | 🟡 Media |
| 9 | `APP_DEBUG=true` en producción | Crítico | Baja | 🟠 Alta |

---

## 1. Aislamiento multi-hogar (IDOR) — amenaza #1

> **Regla**: un usuario **nunca** puede leer, crear, editar ni eliminar recursos de un `household` al que no pertenece, **ni por manipulación de URL o ID**.

### Controles obligatorios (defensa en profundidad)

**a) Resolver el hogar activo de forma segura**
- El hogar activo se obtiene **siempre** del usuario autenticado (su membresía), nunca de un parámetro del cliente.
- Middleware/helper `activeHousehold()` que valida que el usuario pertenece a ese hogar.

**b) Consultas acotadas**
- ❌ `Expense::find($id)` (o `Income::find`) → expone cualquier registro.
- ✅ `$household->expenses()->findOrFail($id)` → acota por membresía.
- ✅ Route-model-binding con scope: `Route::bind('expense', fn($v) => $household->expenses()->where('id', $v)->firstOrFail())`.

**c) Policies (autorización por recurso)**
- Una `Policy` por recurso perteneciente a un hogar.
- `view/update/delete` exigen **dos** condiciones (ver [ADR-0019](DECISIONS.md#adr-0019)): que el usuario sea **miembro** del hogar dueño del recurso **y** que ese hogar sea su **hogar activo**. Usar el trait `App\Policies\Concerns\ChecksHouseholdAccess`; no reimplementar la comprobación a mano.
- ⚠️ **La membresía sola no basta.** Un usuario puede pertenecer a varios hogares, y los Form Requests acotan `account_id`/`category_id` al hogar **activo**. Si la Policy autoriza contra el hogar **del recurso**, ambas capas miden hogares distintos y se puede enlazar una cuenta del hogar A a un recurso del hogar B.
- Excepción deliberada: `HouseholdPolicy` y `HouseholdInvitationPolicy` **no** aplican el invariante — gestionar o activar un hogar debe poder hacerse desde fuera de él.
- Llamar siempre a `$this->authorize()` o `authorizeResource()` en el controlador.

**d) Form Requests blindan `household_id`**
- **Nunca** aceptar `household_id` del cliente. Se fuerza desde el servidor (`$request->user()->activeHousehold()->id`).
- Filtrar cualquier `household_id` entrante aunque venga en el payload.

**e) Global scope (recomendado para recursos sensibles)**
- `HouseholdScope` que añada `where household_id = contexto` automáticamente, para que un olvido no filtre datos.

### Tests obligatorios de aislamiento
Por cada recurso, un test tipo:
```php
public function test_usuario_no_puede_ver_gasto_de_otro_hogar(): void
{
    $hogarA = Household::factory()->create();
    $hogarB = Household::factory()->create();
    $gastoB = Expense::factory()->for($hogarB)->create();

    $userA = User::factory()->create();
    $hogarA->users()->attach($userA, ['role' => 'member']);

    $this->actingAs($userA)
         ->get("/expenses/{$gastoB->id}")
         ->assertForbidden(); // 403
}
```
Cubrir `index`, `show`, `store` (con `household_id` forzado), `update`, `destroy`.

**Y además el caso multi-hogar**, que el test de arriba **no** cubre: con un intruso que no es miembro de nada, la membresía ya lo frena y el test pasa aunque la Policy sea insuficiente. Hace falta un usuario que sea miembro de **los dos** hogares, con A activo, intentando operar sobre un recurso de B (debe dar 403). Ver `tests/Feature/Household/MultiHouseholdIsolationTest.php`.

---

## 2. Secrets (sección Secrets)

**Nunca** commitear:
- `.env`, `.env.production`, `.env.backup`, ningún `.env.*` con valores.
- Contraseñas, API keys, tokens de servicios, SMTP reales.
- `auth.json`, `storage/*.key`, certificados, `.pem`.
- **Datos financieros reales** de cualquier persona.
- Screenshots, dumps CSV/SQL, logs con datos reales.
- Tokens de invitación en texto plano en fixtures/seeders.

**Buenas prácticas**
- `.gitignore` ya cubre `.env`, `storage/*.key`, `auth.json`. **Verifícalo** antes de cada commit.
- `.env.example` **solo** placeholders, sin valores reales.
- Revisa el diff antes de commitear: `git diff --cached | grep -iE "password|secret|key|token|cvv"` debe dar vacío (salvo nombres de variable).
- Si se commitea un secreto por error: **no basta con borrarlo**. Rotar la credencial y, si es necesario, purgar el historial (`git filter-repo` / BFG) + forzar push. Avisar al maintainer.
- `composer audit` y `npm audit` de forma periódica.

### Datos de demostración
- **Solo** Factories + Faker para datos de prueba.
- Los seeders generan hogares/usuarios ficticios ("Demo", "Usuario Prueba"), **nunca** nombres reales con montos reales.

---

## 3. Validación y Mass assignment

- **Form Request por cada `store`/`update`**. Reglas explícitas, estrictas: `required`, tipos, `numeric|min:0`, `Rule::in([...])`, `exists:categories,id`, rangos.
- **`$fillable`** (o atributo `#[Fillable([...])]`) en cada modelo, con **exactamente** los campos asignables. **Prohibido** `$guarded = []`.
- Validar que los IDs referenciados (categoría, cuenta, etc.) pertenezcan al hogar (`exists` + scope, o regla custom).
- Sanitizar entradas largas (descripción/notes) con `max:` y tratarse como texto (Blade escapa).

---

## 4. Manejo de datos sensibles

| Dato | Política |
|---|---|
| Contraseñas | bcrypt/argon2 (Laravel default). Nunca en texto plano, nunca logueadas. |
| Tokens de invitación | 64 chars aleatorios; almacenar **hash**, enviar en plano solo por enlace o correo de invitación; con expiración y un solo uso. **Nunca loguearlos.** |
| Contenido de los correos | **Ningún dato financiero** (saldos, montos, movimientos, nombres de cuentas). La invitación lleva solo nombre del hogar, quién invita y el enlace. Ver [ADR-0015](DECISIONS.md#adr-0015). |
| Número de tarjeta | **No almacenar completo.** Implementado en Épica 6: la tabla `credit_cards` **no tiene** columna para el número (ni siquiera para los últimos 4 dígitos: `name` e `institution` bastan para identificarla). |
| CVV / PIN / fecha vencimiento completa | **No almacenar nunca.** Esas columnas no existen; el Form Request tampoco las acepta, así que un campo así en la petición se descarta. Verificado contra el esquema real en `DebtTest::test_nunca_se_almacenan_datos_sensibles_de_la_tarjeta`. |
| Montos | Permitidos; evitar loguear junto a PII innecesaria. |
| Datos demográficos (`users`) | **Minimización** (Ley 1581, [ADR-0032](DECISIONS.md#adr-0032)): solo `birth_date` (mayoría de edad, obligatoria 18+) y `region`/`gender` opcionales en listas cerradas (enums) — nada de hobbies u ocupación "por si acaso". "Prefiero no decirlo" de género es **NULL** (no almacenar). La edad se calcula (`User::age()`), nunca se guarda. Finalidad declarada en la pantalla; la purga (plan 05) los pone en NULL y el export (plan 06) los incluye. |
| Logs | Nunca contraseñas, tokens, números de tarjeta, datos personales sensibles. Usar canales y niveles adecuados. |

---

## 5. XSS · CSRF · SQLi

- **XSS**: render con `{{ }}` (auto-escape). Evitar `{!! !!}`; si es imprescindible, solo con contenido generado por el sistema, nunca por input. `Content-Type` correcto en endpoints.
- **XSS en contexto JavaScript — `{{ }}` NO basta.** Dentro de un manejador en línea (`onclick`, `onsubmit`…) el navegador **decodifica las entidades HTML antes de compilar el JS**, así que el `&#039;` con el que `{{ }}` escapa una comilla vuelve a ser `'` y cierra el literal de cadena. Un nombre de deuda como `x');alert(1);//` ejecutaba código (corregido en 0.8.2).
  - Para interpolar datos de usuario en JavaScript se usa **`@js($valor)`** (`Illuminate\Support\Js::from()`), que emite JSON escapado para ese contexto.
  - Mejor aún: evitar el JS en línea y pasar el dato por un atributo `data-*` leído desde un listener.
  - `{{ }}` sigue siendo lo correcto en contexto HTML (texto, `value`, `title`): ahí `&#039;` es seguro.
- **CSRF**: `@csrf` en todos los forms; métodos `POST/PUT/PATCH/DELETE` vía form o con header `X-CSRF-TOKEN`/`X-XSRF-TOKEN`. Laravel lo gestiona, pero no desactivarlo.
- **SQLi**: Query Builder / Eloquent con bindings. **Nunca** `DB::raw`/`whereRaw` con concatenación de input; usar `?` y bindings.
- **Cabeceras** (producción): HSTS, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` o CSP. Configurar vía `.htaccess` (Hostinger) o middleware.

---

## 6. Autenticación y sesiones

- Rate limiting: `throttle:5,1` (o similar) en `/login`, `/register`, recuperación de contraseña; reenvío de verificación 3/min por usuario (ADR-0029).
- Lockout / notificación de intentos sospechosos (opcional, futuro).
- Sesiones: driver `database` (compatible Hostinger); `SESSION_LIFETIME` razonalbe; `SESSION_ENCRYPT` según necesidad.
- Logout invalida la sesión; `regenerate()` tras login para prevenir fixation.
- **Verificación de correo obligatoria** en el registro ([ADR-0029](DECISIONS.md#adr-0029)): sin confirmar, el middleware `verified` bloquea toda la app (solo logout, aviso y reenvío) — un usuario sin verificar **no puede crear ningún dato**. El enlace es público + firmado (60 min); el reclaim anti-squatting borra registros nunca verificados en cuanto el dueño real del correo se registra (un `unique` que solo cuenta correos verificados). El digest y la recuperación jamás salen a correos sin confirmar.
- **Gestión de credenciales en `/perfil`** ([ADR-0030](DECISIONS.md#adr-0030)):
  - La contraseña solo rota con la **actual** (`current_password`); el cambio revoca las demás sesiones y cookies de "recuérdame" (`logoutOtherDevices` + `AuthenticateSession` en el grupo `web`) y avisa al propio correo. La sesión actual sobrevive. La recuperación por correo también revoca sesiones por construcción (re-hashea).
  - El correo **nunca cambia directo**: el nuevo queda `pending_email` hasta que su bandeja confirma el enlace público `confirmar-correo/{token}` (token aleatorio guardado como **sha256**, 60 min, throttle). Confirmar marca el correo verificado y avisa al correo **antiguo**. Así ningún correo sin probar entra a `users.email`, y el unique/validación solo reconocen correos **verificados** o pendientes ajenos como tomados.
  - Sin `{user}` en las URLs de perfil: solo el autenticado existe (`UserPolicy`); no hay superficie IDOR sobre usuarios.
- **Consentimiento de términos versionado** ([ADR-0031](DECISIONS.md#adr-0031)):
  - Sin aceptar la versión vigente, el middleware `terms.current` bloquea **toda** la app (tras `auth` + `verified`): no hay navegación sin consentimiento, ni para el registro nuevo ni cuando cambian los términos. El flujo de aceptación vive fuera de ese middleware (nivel 2½) para no crear bucle.
  - La prueba de consentimiento es **inmutable por diseño**: cada aceptación registra versión exacta + fecha + IP en `user_terms_acceptances`; el `restrictOnDelete` de la FK impide borrar una versión aceptada (verificado por test). Cambiar los términos = publicar fila nueva, nunca editar.
  - La IP se guarda con **finalidad exclusiva** de prueba de aceptación (dato personal, Ley 1581; nullable). La política de datos (plan 06) debe documentarla.
  - Rechazar **no destruye nada** — ni cuenta, ni datos, ni sesión: la pantalla de salida solo informa y ofrece cerrar sesión. Las acciones destructivas siempre son explícitas (planes 05/06).
- **Correo transaccional mínimo (ADR-0015)**: Finlia solo envía correo cuando el destinatario **no puede ver el mensaje dentro de la app** — invitación a un hogar, recuperación de contraseña, verificación del registro y los tres de seguridad del perfil (aviso de contraseña, confirmación del correo nuevo, aviso al correo anterior). Recordatorios, resúmenes y comunicaciones de producto van **in-app**. Menos correos = menos superficie de exposición de datos familiares. Añadir un correo nuevo exige un ADR.
- El envío de correo **nunca bloquea la operación**: si el SMTP falla se registra un `Log::warning` (sin token ni enlace) y el flujo continúa con el enlace manual.
- Recuperación de contraseña y creación de invitación devuelven **el mismo mensaje exista o no la dirección**, para no permitir enumeración de usuarios.
- Cookies: `secure` (HTTPS en prod), `httponly`, `samesite=lax|strict`.

---

## 7. Producción

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` generada y secreta.
- HTTPS forzado (Hostinger certificado).
- Permisos de `storage/` y `bootstrap/cache/` correctos (escritura, no ejecución pública).
- `public/` como document root; el resto del framework **fuera** de la raíz pública (configuración de Hostinger, ver [DEPLOYMENT.md](DEPLOYMENT.md)).
- Backups de DB cifrados; nunca en el repo.
- `composer install --no-dev --optimize-autoloader`, `php artisan config:route:cache` (ojo: no cachear con valores de entorno dinámicos).

---

## 8. Monetización (Premium) — backend es la fuente de verdad

- La verificación de **planes, límites y features** se hace **siempre en el backend** (policies/middleware de suscripción).
- El frontend solo **oculta** UI según el plan; ocultar **no es denegar**.
- Nunca confiar en un parámetro del cliente (`?plan=premium`). El plan se lee de la suscripción activa del hogar en el servidor.
- Limites (max hogares, max miembros, export PDF, historial extendido) se chequean antes de mutar, no solo al renderizar.

---

## 9. Checklist de seguridad por épica (resumen)

Antes de declarar una épica "hecha", confirma:

- [ ] Cada recurso del hogar tiene Policy y se autoriza en cada acción.
- [ ] No existe `Model::find($id)` suelto en controladores; todo acotado por `household`.
- [ ] `household_id` nunca se acepte del cliente.
- [ ] `$fillable` definido en cada modelo; sin `$guarded = []`.
- [ ] Form Request por cada escritura, con reglas estrictas.
- [ ] Dinero en `DECIMAL(15,2)` (revisar migraciones).
- [ ] No se loguean secretos/PII sensible.
- [ ] No se almacenan datos sensibles de tarjetas.
- [ ] Rate limiting en endpoints sensibles.
- [ ] Test de aislamiento entre hogares (403) por recurso.
- [ ] No se commitean `.env`, credenciales ni datos reales (`git diff --cached` revisado).
- [ ] `composer audit` sin críticos.

Para ejecutar la verificación guiada, usa la skill `/security-checklist`.

---

## 10. Reportar una vulnerabilidad

Si descubres una vulnerabilidad:
- **No** abras un issue público con detalles explícitos.
- Contacta al maintainer por canal privado.
- Incluye: descripción, pasos para reproducir, impacto y, si puedes, una mitigación sugerida.

Se agradece la divulgación responsable.
