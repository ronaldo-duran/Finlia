# Política de Seguridad — Finlia

> Finlia maneja **dinero real de familias**, será un **repositorio público** y se **monetizará**. Esta política es obligatoria. Cualquier cambio que la relaje debe revisarse explícitamente.

## Modelo de amenazas (resumen)

| # | Amenaza | Impacto | Probabilidad | Prioridad |
|---|---|---|---|---|
| 1 | Acceso a datos de otro hogar (IDOR / manipulación de ID/URL) | Crítico | Alta | 🔴 Máxima |
| 2 | Fuga de secretos al repo público (`.env`, keys, datos reales) | Crítico | Media | 🔴 Máxima |
| 3 | Mass assignment (escalar privilegios / inyectar `household_id`) | Alto | Media | 🟠 Alta |
| 4 | Datos reales de Ronaldo/Vanessa en el repo | Alto | Media | 🟠 Alta |
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
- `view/update/delete` verifican `$user->households->contains($resource->household_id)` **y** el rol si aplica.
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

---

## 2. Secrets (sección Secrets)

**Nunca** commitear:
- `.env`, `.env.production`, `.env.backup`, ningún `.env.*` con valores.
- Contraseñas, API keys, tokens de servicios, SMTP reales.
- `auth.json`, `storage/*.key`, certificados, `.pem`.
- **Datos financieros reales** de cualquier persona (Ronaldo, Vanessa, etc.).
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
| Tokens de invitación | 64 chars aleatorios; almacenar **hash**, enviar/plano solo por enlace; con expiración y un solo uso. |
| Número de tarjeta | **No almacenar completo**. Solo últimos 4 dígitos si se desea. |
| CVV / PIN / fecha vencimiento completa | **No almacenar nunca.** |
| Montos | Permitidos; evitar loguear junto a PII innecesaria. |
| Logs | Nunca contraseñas, tokens, números de tarjeta, datos personales sensibles. Usar canales y niveles adecuados. |

---

## 5. XSS · CSRF · SQLi

- **XSS**: render con `{{ }}` (auto-escape). Evitar `{!! !!}`; si es imprescindible, solo con contenido generado por el sistema, nunca por input. `Content-Type` correcto en endpoints.
- **CSRF**: `@csrf` en todos los forms; métodos `POST/PUT/PATCH/DELETE` vía form o con header `X-CSRF-TOKEN`/`X-XSRF-TOKEN`. Laravel lo gestiona, pero no desactivarlo.
- **SQLi**: Query Builder / Eloquent con bindings. **Nunca** `DB::raw`/`whereRaw` con concatenación de input; usar `?` y bindings.
- **Cabeceras** (producción): HSTS, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` o CSP. Configurar vía `.htaccess` (Hostinger) o middleware.

---

## 6. Autenticación y sesiones

- Rate limiting: `throttle:5,1` (o similar) en `/login`, `/register`, recuperación de contraseña.
- Lockout / notificación de intentos sospechosos (opcional, futuro).
- Sesiones: driver `database` (compatible Hostinger); `SESSION_LIFETIME` razonalbe; `SESSION_ENCRYPT` según necesidad.
- Logout invalida la sesión; `regenerate()` tras login para prevenir fixation.
- Verificación de email (Épica 1, si se habilita).
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
