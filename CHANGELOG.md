# Changelog

Todos los cambios notables de **Finlia** se documentan en este archivo.

El formato se basa en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y el
versionado sigue [SemVer](https://semver.org/lang/es/). La versión vigente del software
se declara en `config/finlia.php` y `package.json`, y debe coincidir con la versión más
reciente de este archivo.

> **Estado actual: desarrollo pre-MVP (sin tags).** Cada entrega mayor de
> funcionalidad publica un **minor** `0.x` y cada corrección un **patch**. El primer
> tag marcará el lanzamiento del MVP con la versión vigente de ese momento. Para
> actualizar este archivo usa la skill `/update-changelog`.

## [0.20.0] - 2026-09-05 — Política de datos y exportación ZIP (Plan 06)

### Añadido
- **`DataExportService`**: genera un ZIP con 12 CSVs (cuentas, ingresos, gastos, presupuestos,
  gastos recurrentes, deudas, pagos de deuda, refinanciaciones, metas de ahorro, aportes,
  recordatorios, perfil del usuario) + `finlia.json` (todos los datos en JSON para migración)
  + `README.txt` explicativo del formato. Reglas de privacidad: nunca exporta la contraseña
  ni datos personales de otros miembros del hogar. `collect()` separado de `buildZip()` para
  testabilidad sin disco.
- **CSV con BOM UTF-8 y separador `;`**: abre directamente en Excel Colombia sin configuración.
  Montos con coma decimal (ej. `1500000,00`). Fechas en DD/MM/AAAA.
- **Ruta `GET /perfil/exportar` → `profile.export`** con throttle de 3 descargas por día
  (`throttle:3,1440`). Responde ZIP descargable; 404 si el usuario no tiene hogar activo.
- **Página pública `GET /datos` → `data.policy`** (`DataPolicyController`): accesible sin
  cuenta. Explica qué datos guarda Finlia, portabilidad, eliminación de cuenta, política de
  retiro del software y cómo migrar a otra herramienta.
- **"Mis datos" en `/perfil`**: tarjeta con descripción y botón "Exportar mis datos" (enlaza
  a `profile.export`). Máximo 3 descargas al día.
- **Footer actualizado** en `app.blade.php` y `guest.blade.php`: incluye enlace "Tus datos"
  junto a "Términos".
- **18 tests** cubren: redirect de invitado, ZIP descargable, nombre con ID + fecha, 404 sin
  hogar, throttle 3/día, perfil sin contraseña, gastos con montos, deudas, metas, JSON
  maestro sin contraseña, BOM UTF-8, separador `;`, aislamiento entre hogares, privacidad
  de otros miembros, y página `/datos` accesible y con secciones de portabilidad/eliminación.

### Notas
- `DataExportService` no depende de HTTP (ADR-0010): recibe `Household` + `User` explícitos,
  no usa `request()` ni `Auth::`. Listo para futura API REST (Épica 14).
- Los campos enum se serializan con `->value` (no como objeto PHP) para compatibilidad CSV/JSON.
- ADR-0034 registrado en `docs/DECISIONS.md`.

## [0.19.0] - 2026-09-04 — Eliminación de cuenta: suspensión 30 días y purga (Plan 05)

### Añadido
- **Columna `users.deletion_requested_at`**: timestamp nullable. `null` = cuenta activa;
  timestamp = suspendida. Migración `2026_09_04_000200_add_deletion_requested_at_to_users_table`.
- **`User::isSuspended()` / `User::deletionDeadline()`**: helpers limpios sin lógica de fechas
  en controladores ([ADR-0033](docs/DECISIONS.md#adr-0033)).
- **`AccountDeletionService`**: tres reglas de purga (cascade para dueño único, transferencia
  al miembro más antiguo con notificación por correo, anonimización para el resto). Separado
  de HTTP: recibe el `User`, devuelve resultado — listo para el futuro endpoint de API.
- **`EnsureAccountActive` middleware** alias `account.active`: bloquea el acceso al nivel-3 de
  rutas si la cuenta está suspendida y redirige a `/cuenta/suspendida`.
- **`AccountSuspensionController`**: muestra la pantalla de suspensión con fecha límite y
  ofrece reactivación en un clic (limpia `deletion_requested_at`, redirige al dashboard).
- **Zona de peligro en `/perfil`**: sección colapsable con confirmación de contraseña
  (`DeleteAccountRequest`) → marca suspensión, cierra todas las sesiones y redirige al login.
- **`AccountDeletionRequestedMail`** + vista HTML/texto: correo antifraude con fecha límite y
  enlace de reactivación, enviado fuera de la transacción (un SMTP caído no revierte la
  suspensión).
- **`OwnershipTransferredMail`** + vista HTML/texto: avisa al nuevo propietario del hogar.
- **`PurgePendingDeletions` command** (`finlia:purge-pending-deletions`): purga diaria de
  cuentas con ventana expirada (>30 días) y cuentas fantasma sin verificar (>14 días).
  Programado a las 05:30 (antes de recurrentes y digest).
- **`SendReminderDigests` actualizado**: excluye miembros con `deletion_requested_at` no nulo.
- **15 tests** cubren: bloqueo de navegación, reactivación, solicitud con contraseña errónea,
  correo antifraude, tres reglas de purga, purga de fantasmas y exclusión del digest.

### Notas
- La reactivación es deliberadamente fácil: iniciar sesión + un botón (no hace falta un
  "código de arrepentimiento"). El correo antifraude cumple la doble función de aviso y
  enlace de regreso.
- `deletion_requested_at` no está en `$fillable` (campo interno, no expuesto en forms de
  usuario); el servicio usa asignación directa (`$user->deletion_requested_at = …; save()`).
- `purge()` usa `forceFill` para sobrescribir en un solo paso y captura el email original
  antes de anonimizarlo (limpia `password_reset_tokens` con la dirección real).

## [0.18.0] - 2026-08-30 — Datos personales: nacimiento, región y género (Plan 04)

### Añadido
- **Fecha de nacimiento en el registro (18+)**: obligatoria y solo para mayores de
  edad — regla compartida `AdultBirthDate` (fecha real del pasado, no anterior a
  1900, no futura) con el corte inclusivo de "quien cumple 18 hoy entra". Finlia
  maneja las finanzas de un hogar: nada de consentimiento parental
  ([ADR-0032](docs/DECISIONS.md#adr-0032)).
- **Sección "Datos personales" en `/perfil`**: nacimiento + región y género
  **opcionales**. Región = los 32 departamentos y Bogotá D.C. (enum fijo
  `ColombianRegion`, no tabla); género en lista cerrada (`Gender`) donde
  **"Prefiero no decirlo" no almacena nada** (NULL). La pantalla declara la
  finalidad: mayoría de edad y estadísticas agregadas, nunca decisiones
  financieras ni compartirlos (minimización, Ley 1581).
- **`User::age()`**: la edad se calcula desde `birth_date`, nunca se guarda — lo
  derivable no ocupa columna (listo para la analítica de la Épica 12).

### Notas
- Región y género **no** se piden en el registro (menos fricción de entrada); el
  perfil los completa. Hobbies e intereses quedan fuera por diseño: cada dato
  futuro necesitará caso de uso escrito y su eliminatoria en la purga (plan 05).
- Usuarios heredados: `birth_date` es nullable en DB; su primera edición del
  perfil la completa. La purga (plan 05) pondrá los tres campos en NULL y el
  export (plan 06) los incluirá.
- Sin cambios en `.env`, correos ni rutas. Migración: `users` gana
  `birth_date`/`region`/`gender`.

## [0.17.0] - 2026-08-30 — Términos y condiciones versionados (Plan 03)

### Añadido
- **Re-aceptación obligatoria de términos**: sin aceptar la versión vigente, el
  middleware `terms.current` redirige **toda** la app a la pantalla de aceptación —
  un solo mecanismo cubre el registro nuevo y el cambio de términos
  ([ADR-0031](docs/DECISIONS.md#adr-0031)). Orden de puertas: primero correo
  verificado, luego términos.
- **Prueba de consentimiento inmutable**: cada aceptación registra **versión exacta,
  fecha e IP** en `user_terms_acceptances`; una versión aceptada **no se puede borrar**
  de la base de datos (FK RESTRICT). Las versiones son filas que nunca se editan:
  cambiar los términos = publicar una nueva (prueba ante un reclamo, Ley 1581).
- **Pantalla de aceptación sin trampas oscuras**: texto completo en scroll, resumen
  de "qué cambió" cuando ya había una aceptada, y dos salidas — **Aceptar y
  continuar**, o **No aceptar**, que lleva a una pantalla honesta que **no destruye
  nada** (rechazar jamás borra datos; exportar/eliminar llegarán con los planes
  05/06 y enlazarán allí).
- **Lectura pública** de la vigente en `/terminos` y del histórico en
  `/terminos/{version}` (la referencia externa de qué aceptó cada usuario); enlace
  "Términos" en el footer.
- **Seeder de la versión inicial** `2026-09-v1` como **BORRADOR** con marcadores: la
  redacción legal definitiva es del dueño del producto; al existir, se publica como
  versión nueva — la actual jamás se edita.

### Notas
- Sin versión publicada la app se usa normal (fail-open): el mecanismo solo obliga
  cuando hay texto que consentir. Los usuarios demo del seeder ya traen la
  aceptación registrada.
- Sin cambios en `.env`, correos ni cron. Migraciones: las tablas nuevas
  `terms_versions` y `user_terms_acceptances`.

## [0.16.0] - 2026-08-30 — Perfil: contraseña y cambio de correo (Plan 02)

### Añadido
- **Pantalla `/perfil`** (menú del avatar → "Mi perfil"): nombre, contraseña y correo
  con su estado. Preferencia del usuario, no del hogar — sin IDs ajenos en la URL,
  solo el autenticado existe ([ADR-0030](docs/DECISIONS.md#adr-0030)).
- **Cambio de contraseña con re-autenticación**: pide la contraseña actual y al
  cambiarla **revoca las demás sesiones** y cookies de "recuérdame" (middleware
  `AuthenticateSession`); la sesión actual sigue viva. Aviso al propio correo con la
  vía de recuperación si no fuiste tú.
- **Cambio de correo con doble confirmación**: el correo nuevo queda pendiente hasta
  que **esa bandeja** confirma el enlace (token de un uso, 60 min, guardado hasheado).
  Confirmar marca el correo como verificado, conserva hogares/preferencias/sesión, y
  **avisa al correo anterior** — la pierna antifraude del flujo. Coherente con el
  anti-squatting del registro: un fantasma sin verificar no bloquea el cambio, una
  cuenta verificada sí.

### Cambiado
- La política de correo pasa a **7 correos** (+3 transaccionales de seguridad del
  perfil — tabla de [ARCHITECTURE §7](docs/ARCHITECTURE.md)).
- La recuperación de contraseña por correo ahora **cierra las demás sesiones** por
  construcción (re-hashea la contraseña): una cuenta comprometida se limpia con un
  reset.

### Notas
- Sin cambios en `.env` ni en el cron. Migración solo añade las columnas
  `pending_email*` a `users`.

## [0.15.0] - 2026-08-30 — Verificación de correo en el registro (Plan 01)

### Añadido
- **Verificación de correo obligatoria** al registrarse: la cuenta se crea completa
  (usuario + hogar) pero la app queda bloqueada hasta confirmar el correo — el
  middleware `verified` cubre todo lo privado; sin confirmar solo son alcanzables
  cerrar sesión, el aviso "Revisa tu correo" y el reenvío
  ([ADR-0029](docs/DECISIONS.md#adr-0029)). Corolario: un usuario sin verificar no
  puede crear ningún dato.
- **Correo de verificación propio** en español (estilo de marca, HTML + texto plano,
  patrón de la invitación y el digest) con **enlace firmado a 60 min**, público: el
  click funciona desde el buzón con o sin sesión. Reenvío desde el aviso con
  **throttle de 3/min por usuario** y pista de "revisa spam".
- **Anti-squatting**: si alguien registró tu correo y nunca lo verificó, ese registro
  no te bloquea — al registrarte de nuevo, el fantasma y su hogar vacío se borran y
  tu cuenta se crea limpia. Un correo solo cuenta como tomado si está verificado.
  "Olvidé mi contraseña" queda como vía de recuperación universal.

### Cambiado
- El digest de recordatorios **excluye correos sin verificar** (cinturón y
  suspenderes: nunca correos periódicos a direcciones sin confirmar, que pueden ser
  de otra persona).
- La política de correo pasa a **4 correos** (invitación, contraseña, digest,
  verificación — tabla de [ARCHITECTURE §7](docs/ARCHITECTURE.md)).

### Notas
- Migración de datos: los usuarios ya registrados quedan verificados de oficio
  (base de desarrollo; nadie "verificó de verdad" porque el flujo no existía).
- Sin cambios en `.env` ni en el cron: usa el SMTP ya configurado y no añade
  comandos.

## [0.14.0] - 2026-08-29 — Recordatorios y notificaciones (Épica 9)

### Añadido
- **Página `/recordatorios`** con la lista unificada de todo lo que vence: gastos
  recurrentes, cuotas de deuda, metas con fecha y avisos propios del usuario, agrupada
  en vencidas / vencen pronto (7 días) / más adelante, con la acción de cada origen al
  alcance (marcar pagado, ir a la deuda, aportar a la meta). Los avisos derivados **no
  se duplican en tabla**: se calculan en vivo desde su fuente, así que nunca quedan
  caducados aunque el cron no corra ([ADR-0027](docs/DECISIONS.md#adr-0027)).
- **Avisos sueltos del usuario** ("obligación anual": tecnomecánica, pasaporte, predial)
  con alta, edición, borrado y "atender": si se repite avanza una frecuencia y sigue
  pendiente; si es de una sola vez queda completado. **Atender no genera gasto** — un
  recordatorio es un aviso, no un movimiento.
- **Campanita en el navbar** con el conteo urgente (vencidas + próximas) y desplegable
  de resumen, presente en todas las páginas autenticadas.
- **Banner en el Panel**: "🔔 Tienes N obligaciones próximas (M vencidas)" con enlace
  directo a la lista, en tono informativo (no de alarma).
- **Casilla "Registrar el pago solo cuando llegue la fecha"** en gastos recurrentes
  (`auto_generate`) y comando del Scheduler `finlia:generate-recurring-payments`
  (diario 06:00, compatible con el cron de Hostinger): registra el gasto vencido
  reutilizando "Marcar pagado" — **una ocurrencia por corrida con su fecha real**, para
  que un atraso de N meses se recupere en N días sin una ráfaga fechada "hoy"
  ([ADR-0018](docs/DECISIONS.md#adr-0018)).
- **Interruptor del hogar** (`households.reminders_enabled`, solo el administrador):
  apaga campanita, banner y listado de todo el hogar de una vez, sin borrar datos.
- **Campanita sin costo por página**: el conteo de la campanita va con **caché corta
  invalidada por eventos de modelo** (`cachedSummary` + `ReminderSummaryCacheObserver`:
  cualquier cambio en recurrentes, deudas/pagos, metas/aportes, avisos o el hogar borra
  la clave de ese hogar; el TTL de 10 min solo cubre el paso de medianoche). La página
  `/recordatorios` sigue leyendo el estado fresco (`list()` no se cachea).
- **Digest diario opcional por correo** ([ADR-0028](docs/DECISIONS.md#adr-0028)): quien lo
  activa en `/recordatorios` ("Resumen por correo") recibe **un correo al día (06:30)**
  con sus obligaciones urgentes — solo si las hay, opt-in por miembro y hogar, jamás
  marketing. Comando del Scheduler `finlia:send-reminder-digests`, envío síncrono por
  SMTP (Brevo free en producción, ver `docs/DEPLOYMENT.md` §4) e idempotente
  (`last_reminder_digest_at`: reintentos del cron no duplican); las tareas del Scheduler
  llevan `withoutOverlapping()`. El Mailable `ReminderDigest`
  trae HTML autocontenido en español + texto plano y **no marca nada como leído**: el
  aviso se apaga pagando.
- **Baja de un click desde el correo** (RFC 8058): el digest trae "Ya no quiero recibir
  este resumen" con **URL firmada por usuario y hogar** (60 días, sin sesión); las
  cabeceras `List-Unsubscribe`/`List-Unsubscribe-Post` hacen que Gmail/Yahoo ofrezcan su
  botón nativo de cancelación, que ejecuta la baja con un POST (respuesta 204). Bajar del
  digest de un hogar no toca el de otro; la firma inválida da 403.
- 46 tests nuevos (estados derivados, cuota de deuda pagada/impaga, resumen, atender,
  aislamiento multi-hogar, comando del scheduler, invalidación de caché, digest por
  correo y baja desde el correo) — suite total: **407 en verde**.

### Cambiado
- El seeder de demo incluye tres avisos sueltos (uno vencido, uno próximo, uno anual
  lejano) y activa la generación automática en la suscripción de internet, para que la
  página y la campanita muestren los tres estados desde el primer arranque.

## [0.13.0] - 2026-08-29 — Reportes financieros (Épica 8)

### Añadido
- **Pantalla de reportes** (`/reportes`) con los cinco gráficos de la épica: gastos por categoría,
  ingresos vs gastos, evolución mensual del balance, **evolución de la deuda** y progreso de metas
  (barras horizontales apiladas: ahorrado + faltante). Cada gráfico con su estado vacío; la deuda
  se dibuja a fin de cada mes de los últimos seis y respeta refinanciaciones y pagos reales
  ([ADR-0020](docs/DECISIONS.md#adr-0020)).
- **Comparación de períodos** con chips — mes actual, mes anterior, últimos 3, últimos 6, año —
  que muestran ingresos, gastos y balance del período contra su **equivalente anterior** (mes
  contra mes previo, año YTD contra el mismo tramo del año pasado: nunca contra meses que aún no
  han ocurrido). Deltas absolutos siempre; porcentuales solo cuando la base anterior existe.
- **Observaciones (insights) descriptivas**: hechos calculados — "Gastaste $ 150.000,00 menos que
  en julio 2026", "«Alimentación» aumentó 18 %", "balance en rojo" — con **umbrales anti-ruido**
  (≥ 5 % el total, ≥ 15 % una categoría) para que la pantalla no grite con cambios de calendario.
  Sin recomendaciones financieras; máximo cuatro por período.
- **Exportación CSV** de los movimientos del período (`/reportes/exportar`), en streaming con BOM
  UTF-8, separador `;` y coma decimal: Excel en español lo abre bien sin importar nada. Con
  rate limiting (`throttle:10,1`). El enum `ReportFormat` queda como **seam del PDF Premium**
  (Épica 12): añadirlo es un caso nuevo del enum y una rama del controlador, no un rediseño.
- **El Panel gana solo lo que le falta**: KPIs de deuda total y ahorro en metas, y el enlace "Ver
  reportes". La guía mobile de la propia épica pedía no amontonar gráficos; el Panel responde
  "¿cuánto puedo gastar hoy?" y los reportes "¿qué ha pasado?" ([ADR-0026](docs/DECISIONS.md#adr-0026)).

### Cambiado
- **Las tortas (Panel y Reportes) muestran top 5 categorías + "Otras"**: con muchas categorías,
  el gráfico de gastos por categoría era una rueda de porciones ilegibles. El resto se pliega
  en una fila "Otras" (gris neutro, suma real del resto) tanto en el Panel como en Reportes;
  los cálculos internos (insights, comparaciones) siguen usando la lista completa.
- **La lista de Movimientos carga de a 20 con "Cargar más"** (antes traía 200 de golpe). El
  botón pide la siguiente página de la misma ruta conservando los filtros y anexa los grupos
  con JavaScript mínimo (fetch + insertAdjacentHTML). El corte de página **nunca parte un día
  por la mitad**: si los 20 caen dentro de un grupo, la página se extiende hasta cerrarlo,
  para no ver el mismo día repetido en dos pantallas. El "Balance del filtro" sigue siendo el
  de **todo** el filtro, no el de la página visible.

### Corregido
- **Los gráficos del Panel llevaban rotos desde la Épica 3**: el JSON de datos se inyectaba con
  `{{ }}` dentro de `<script type="application/json">` y Blade escapa `"` a `&quot;`, que los
  navegadores no decodifican dentro de `<script>` — `JSON.parse` fallaba en silencio. Ahora se
  inyecta con `json_encode(..., JSON_HEX_TAG)`, que es JSON válido e inyectable con seguridad.

### Seguridad
- Sin hogares ajenos en los reportes: todas las consultas salen del hogar activo en sesión y hay
  test que verifica que el CSV de un hogar **no contiene** movimientos de otro.
- El período y el formato llegan por query y se validan con reglas `in:` contra los enums
  cerrados: un valor inventado es un error de validación con mensaje, no una excepción ni un
  camino nuevo. No hay `household_id` en la petición que suplantar.
- Ruta de exportación con `throttle:10,1` (peticiones por minuto y usuario).
- **El CSV neutraliza la inyección de fórmulas** (OWASP): los textos controlados por el
  usuario —descripción, categoría, cuenta, quien registra— que empiezan por `=`, `+`, `-` o `@`
  se prefijan con `'` para que Excel no los evalúe como fórmula al abrir el archivo. Con test
  de regresión; detectado en la pasada `/security-checklist`.

## [0.12.0] - 2026-08-31 — Metas de ahorro (Épica 7)

### Añadido
- **Panel de metas de ahorro** (`/metas`) con resumen del hogar (total ahorrado, objetivo,
  aporte mensual programado), filtro por estado —vigentes, logradas, archivadas— y aviso de
  metas vencidas. Alta con prioridad, fecha objetivo opcional (una meta sin fecha es abierta,
  como el fondo de emergencia) y marca de **fondo de emergencia**.
- **Detalle con historial**: progreso hacia el objetivo, aporte o retiro puntual con nota, y
  **aporte mensual recomendado** — lo que falta repartido en los meses que quedan — marcado
  siempre como estimación (misma honestidad que las proyecciones de deuda).
- **Acciones de estado**: pausar, reactivar, marcar lograda y archivar, cada una con su
  propósito (pausar deja de comprometer el aporte; archivar saca la meta del panel sin perder
  el historial). Nada de cambiar el estado con un select del formulario.
- **El ahorro entra al dinero disponible** ([ADR-0025](docs/DECISIONS.md#adr-0025)): el
  «aporte mensual que destinarás» de cada meta activa suma al término `savings` del
  comprometido ([ADR-0014](docs/DECISIONS.md#adr-0014)), tope el faltante de la meta — la
  última cuota nunca supera lo que falta. Pausar una meta libera ese dinero al instante.
- **Tarjeta de metas en el dashboard** con el progreso de las tres metas más urgentes
  (prioridad alta primero, luego fecha objetivo).

### Decisiones ([ADR-0025](docs/DECISIONS.md#adr-0025))
- **Lo ahorrado no se teclea**: es derivado del historial (Σ aportes − Σ retiros), como el
  saldo de las deudas ([ADR-0020](docs/DECISIONS.md#adr-0020)). Lo que ya tenías se registra
  como aporte inicial. Al cubrir el objetivo la meta pasa a lograda sola, y si borras el
  movimiento vuelve a activa.
- **Registrar un aporte no mueve cuentas ni crea gastos**: ahorrar no es gastar, y contar el
  aporte como salida haría bajar el disponible dos veces. La transferencia real entre cuentas
  llega con el botón "+" de la Épica 10.
- Los retiros no pueden superar lo ahorrado, y las metas logradas o archivadas no aceptan
  movimientos — con mensajes que dicen el estado, no un «dato inválido».

### Seguridad
- Aislamiento por hogar cubierto con Policy y test: otro hogar recibe **403** al ver, editar,
  borrar o aportar a una meta ajena; `household_id` no es asignable en masa y un intento de
  suplantarlo al crear se ignora (test incluido).
- **La autorización corre antes de validar** en el registro de aportes (mismo patrón que
  gastos/ingresos): las reglas que dependen de la meta incrustan su estado y su saldo en los
  mensajes de error, así que un usuario ajeno recibe 403 sin que la validación llegue a
  revelar cifras de otro hogar — detectado en revisión de seguridad y cubierto con test.
- Dinero en `DECIMAL(15,2)` y fechas de movimiento acotadas a hoy hacia atrás.

## [0.11.0] - 2026-08-31 — El aviso de estimación se puede dar por leído

### Añadido
- **El aviso de deudas se puede descartar, pero no desaparece: se reduce a una línea**
  ([ADR-0024](docs/DECISIONS.md#adr-0024)). En móvil ocupaba media pantalla en cada visita, y un
  aviso que se ve siempre deja de leerse. Ahora la primera vez sale completo con «Entendido, no
  mostrar de nuevo» y a partir de ahí queda un recordatorio discreto que **sigue junto a las
  cifras**: que la advertencia se esfume del todo es el escenario que en una app de finanzas paga
  el usuario con su dinero.
- **Mecanismo genérico de avisos leídos** (`user_acknowledgements`), por clave y no por columna:
  las metas de ahorro y los reportes traerán advertencias parecidas y lo reutilizan sin tocar
  `users`. La constancia va en el servidor, así que persiste entre dispositivos, sobrevive a un
  borrado de datos de navegación y queda con fecha.

### Seguridad
- La clave del aviso llega en la URL y se valida contra un **enum cerrado**: una clave inventada
  es un 404, no una fila basura en la tabla.
- El acuse es siempre del usuario autenticado; el `user_id` no sale nunca de la petición, así que
  no hay forma de marcar el aviso de otra persona. Cubierto con test.
- Funciona sin JavaScript: es un formulario normal con `@csrf`.

## [0.10.1] - 2026-08-30 — Aviso de estimación y ancho de la barra lateral

### Añadido
- **Aviso visible de que las cifras de deuda son aproximadas**, en las tres pantallas donde el
  usuario lee un número: al registrar, en el panel y en el detalle. Nuevo componente
  `<x-debt-disclaimer />`, para que el texto viva en un solo sitio. Sustituye al pie de página en
  gris que solo estaba en el panel y al inciso escondido dentro de la proyección: en una app de
  finanzas, confundir una estimación con un estado de cuenta lo paga el usuario con su dinero.

### Corregido
- Al registrar un pago, el tipo «Cuota pactada» pasa a llamarse **«Cuota mensual»**, que es como
  se llama el campo en el formulario de la deuda desde la 0.9.0. El valor guardado (`scheduled`)
  no cambia: renombrar la etiqueta no obliga a migrar los pagos ya registrados.
- **La barra lateral se encogía según el contenido de la página** (132 px en el panel de deudas,
  201 en presupuestos, 230 en el panel, frente a los 264 previstos), partiendo los rótulos en dos
  líneas. Le faltaba `flex-shrink: 0`: como flex item, `width` no impide que el hermano la
  comprima. Bug preexistente, no introducido por las deudas; ahora mide 264 px en todas las
  pantallas y en móvil sigue siendo un offcanvas oculto.
- El detalle de deuda sugería «añade el pago mínimo o la cuota pactada», nombres que dejaron de
  existir en la 0.9.0.

## [0.10.0] - 2026-08-30 — El alta de deudas, como un simulador de crédito

### Cambiado
- **Registrar una deuda se mueve a su propia pantalla** con un botón en la cabecera del panel
  (`w-100 w-sm-auto`: ancho completo en móvil, botón normal en escritorio). Adquirir deuda es
  puntual y no tenía sentido que el formulario ocupara media pantalla siempre ([ADR-0023](docs/DECISIONS.md#adr-0023)).
- **El formulario funciona como un simulador de crédito**: declaras monto, tasa y número de
  cuotas, y la aplicación calcula **cuota mensual**, **fecha de fin** e **intereses totales**
  mientras escribes. Es el orden en que lo pide cualquier banco, y el inverso del anterior.
- **La cuota se muestra calculada y bloqueada**, con un interruptor «Mi entidad cobra otra cuota»
  para ajustarla cuando hay seguros o cuota de manejo de por medio.
- Los campos de dinero pasan a `data-money-input` (punto de miles colombiano), como manda
  `docs/UI_DESIGN.md`; antes usaban `type="number"`.

### Corregido
- **Se podía registrar una deuda imposible.** Con 10.000.000 en 120 cuotas y un plan de 20.000 al
  mes la aplicación guardaba sin protestar y luego calculaba, con razón, 500 meses. Ahora se
  valida **en el servidor** que la cuota cubra los intereses y baste para el plazo pactado, y el
  mensaje dice cuánto haría falta (83.333,34 en ese caso), no solo que está mal.
- **La tasa anual se interpretaba como nominal.** Se dividía entre 12 cuando en Colombia el
  crédito se cotiza en **efectiva anual**: la mensual equivalente es `(1+EA)^(1/12)−1`. Con
  28,5 % E.A. se calculaba 2,375 % mensual en vez del 2,114 % real, sobreestimando los intereses.
- **El simulador y la proyección podían discrepar en un mes** por redondeo: la cuota se redondeaba
  al céntimo más cercano y se quedaba corta. Ahora se redondea hacia arriba, como hacen los bancos,
  y pagar la cuota calculada salda la deuda en el plazo exacto (verificado de 36 a 240 cuotas).
- Los datos de demostración tenían una cuota mínima incoherente con su monto y plazo. Ahora la
  deriva el Service, así que el seeder no puede generar una deuda imposible.

### Seguridad
- La confirmación de borrado pasa a `data-confirm`, el mecanismo que ya existía en `app.js` para
  no meter datos del usuario dentro de JavaScript en línea. Sustituye al parche con `@js()` de la
  0.8.2 y elimina el JS en línea por completo; el test de regresión ahora comprueba que **ningún**
  manejador en línea lleva datos del usuario.

## [0.9.0] - 2026-08-30 — Correcciones de uso y plazo de las deudas

### Corregido
- **«Balance del mes» no mostraba icono**: `bi-scale` no existe en Bootstrap Icons, y un `bi-*`
  mal escrito no da error, simplemente no pinta nada. Se usa `bi-plus-slash-minus`. Un barrido
  de los 2078 iconos del paquete confirma que era el único roto, y ahora hay un test que lo
  comprueba en cada ejecución.
- **El mes de la proyección de deuda salía en inglés**: `translatedFormat` usa el locale
  **global** de Carbon, que su service provider sincroniza con `APP_LOCALE`. Con `APP_LOCALE=en`
  —lo que trae la plantilla de Laravel— se veía «December de 2028» aunque el resto de la interfaz
  estuviera en español, porque esa está escrita a mano en Blade. Se fija el locale en la instancia,
  como ya hacían los otros cinco sitios del repo que formatean fechas.
- **Los últimos movimientos se ordenaban de forma arbitraria y perdían registros.** La columna
  `date` es DATE, sin hora, y el orden era `orderByDesc('date')` a secas: todos los movimientos
  del mismo día empatan y su orden queda a criterio del motor. Como el LIMIT se aplica sobre ese
  orden, un movimiento recién creado —el de «marcar pagado» de un recurrente, o un pago de deuda—
  podía **no aparecer**. Ahora se desempata por `created_at` y luego por `id`.
- El mismo método tomaba N movimientos de cada tabla y las mezclaba sin recortar: el panel pedía
  6 y recibía hasta 12.

### Cambiado
- **La deuda se pacta en cuotas, no en una fecha** ([ADR-0022](docs/DECISIONS.md#adr-0022)):
  `end_date` deja de teclearse y pasa a derivarse de `start_date + term_months`. Cada tipo tiene
  su tope de cuotas (tarjeta 100, vehículo 96, hipotecario 480, resto 120), pensados para atajar
  errores de dedo, no como límites legales.
- **Nuevo tipo de deuda: crédito hipotecario.** Su plazo no cabía en ningún tipo existente.
- **`scheduled_payment` pasa a `planned_payment`**, y los dos campos se explican por lo que son:
  *cuota mínima* es lo que **exige la entidad**, *lo que planeas pagar* es lo que **tú decides**
  (vacío = solo el mínimo). Se valida que el plan nunca quede por debajo del mínimo. El
  comportamiento no cambia: lo que sale del bolsillo cada mes ya era `plan ?? mínimo`.

### Seguridad
- `type[]=x` en el alta de deuda habría devuelto 500 por el mismo cast inseguro que se corrigió
  en 0.8.2 para `?estrategia[]=`. Se comprueba el tipo antes de resolver el enum, con test.

## [0.8.2] - 2026-08-29 — Revisión de seguridad de la Épica 6

### Seguridad
- **XSS almacenado en el detalle de deuda (introducido en 0.8.0).** El nombre de la deuda se
  interpolaba con `{{ }}` dentro del `onsubmit` del formulario de borrado. En un manejador en
  línea el navegador **decodifica las entidades HTML antes de compilar el JavaScript**, así que
  el `&#039;` del escape volvía a ser una comilla y cerraba el literal: un nombre como
  `x');alert(1);//` ejecutaba código en el navegador de cualquier miembro del hogar que pulsara
  «Eliminar deuda». Ahora se usa `@js()` (`Js::from()`), que escapa para contexto JavaScript.
  El test de regresión decodifica el atributo igual que hace el navegador y se ha comprobado
  que **falla** si se revierte a `{{ }}`.
- Se documenta la regla general en `docs/SECURITY.md` §5: `{{ }}` protege contexto HTML, no
  contexto JS; para datos de usuario dentro de JavaScript, `@js()` o un atributo `data-*`.

### Corregido
- `?estrategia[]=x` en el panel de deuda devolvía **500**: `query()` devuelve un array y
  castearlo a string emite un warning que Laravel convierte en excepción. Ahora se comprueba
  el tipo y se cae a la estrategia por defecto.

## [0.8.1] - 2026-08-29 — El correo de recuperación, en español

### Corregido
- **El correo de recuperación de contraseña salía en inglés.** Usa la notificación nativa
  de Laravel, cuyo texto vive en el framework, y el proyecto no tenía `lang/es.json`. Ahora
  el asunto, el cuerpo, el botón, la subcopia y el pie están en español, de modo que los dos
  únicos correos de Finlia (ADR-0015) hablan el mismo idioma.
- Las claves de `lang/es.json` deben coincidir **carácter a carácter** con las cadenas del
  framework —incluidos el salto de línea y las comillas escapadas de la subcopia del botón—
  o la traducción se ignora sin avisar. Se añaden 5 tests que **renderizan el correo real** y
  fallan si vuelve a aparecer texto en inglés, en lugar de comprobar solo que el archivo existe.

### Notas
- `.env.example` sigue **sin** el bloque `MAIL_*`: el archivo está bloqueado para escritura en
  el entorno de trabajo actual. La configuración completa (SMTP de Hostinger, SPF/DKIM) está
  en `docs/DEPLOYMENT.md` §4 y hay que copiarla a mano.

## [0.8.0] - 2026-08-29 — Deudas y tarjetas de crédito (Épica 6)

### Añadido
- **Panel de deuda**: deuda total, pago mensual comprometido y progreso de amortización
  del hogar, con listado por deuda y su barra de avance.
- **`debts`**: tarjetas, préstamos, crédito de vehículo, préstamo familiar y otras, con
  entidad, tasa (fija/variable), pago mínimo, cuota pactada, día de pago, fechas y estado
  (`DebtType`, `DebtStatus`, `InterestRateType`). Borrado lógico: una deuda saldada es
  historia financiera, no ruido.
- **Historial de pagos** (`debt_payments`) con tipo mínimo/cuota/abono extra. Registrar un
  pago baja el saldo; borrarlo lo devuelve.
- **Refinanciación** (`debt_refinancings`): nuevas condiciones (tasa, plazo, cuota) y nuevo
  saldo de partida, con su historial.
- **Tarjetas de crédito** (`credit_cards`) como atributos de una cuenta `type=credit_card`
  (ADR-0002, que estaba ACEPTADA y quedaba por implementar): cupo, cupo disponible, día de
  corte, día límite de pago y cuota de manejo, con indicador de uso del cupo y aviso al
  superar el 30 %.
- **Proyección de fin de deuda**: "si mantienes este ritmo, terminarías hacia…", amortizando
  mes a mes con la tasa. Se presenta **siempre como estimación** y detecta el caso en que la
  cuota no cubre los intereses, donde decirlo es más útil que dar una fecha inventada.
- **Estrategias avalancha y bola de nieve** (`DebtStrategy`) como criterio de orden. La
  épica pedía preparar la arquitectura, no resolver el plan de pagos: el reparto del
  excedente entre cuotas queda fuera de alcance.
- Deudas de demostración en `DatabaseSeeder` (datos siempre falsos).
- Tests: 19 unitarios de `DebtService` (saldo derivado, refinanciación, proyecciones,
  estrategias, seam del dinero disponible) y 26 de feature con CRUD, validación, pagos con
  y sin cuenta, mass assignment sobre el saldo y **11 de aislamiento entre hogares**.

### Cambiado
- **`BudgetCalculatorService` rellena el término `debt`**, que la Épica 4 dejó declarado en
  cero (ADR-0014). El desglose "¿Cómo se calcula?" ya no muestra "Épica 6" sino las cuotas
  pendientes reales.
- **El saldo de una deuda es derivado, no se teclea** ([ADR-0020](docs/DECISIONS.md#adr-0020)):
  `línea base − pagos`, donde la línea base es el monto original o el saldo de la última
  refinanciación. No es fillable y el Form Request no lo acepta.
- **Un pago con cuenta asociada genera el movimiento real**
  ([ADR-0021](docs/DECISIONS.md#adr-0021)), en la misma transacción, para que el saldo de la
  cuenta no mienta. Las cuotas **ya pagadas** salen del comprometido: sin esa resta, pagar
  una deuda bajaría el "puedes gastar" dos veces.
- `DATA_MODEL.md` cierra el marcador ⚖️ de ADR-0002, que llevaba pendiente desde la Épica 3
  pese a que la decisión ya estaba ACEPTADA en `DECISIONS.md`.
- "Deudas" pasa de marcador deshabilitado a entrada real del menú.

### Corregido
- Vista de detalle de cuenta: un `@php(...)` en línea combinado con un bloque
  `@php … @endphp` posterior hacía que Blade emparejara mal las etiquetas y dejara de
  compilar el resto del archivo. Se usa la forma de bloque.

### Seguridad
- **Nunca se almacena número completo de tarjeta, CVV ni PIN**: esas columnas no existen en
  `credit_cards` y el Form Request no las acepta. Hay un test que lo verifica contra el
  esquema real, no contra el código.
- Los intereses **no se acumulan solos** (ADR-0020): el saldo solo baja con los pagos. Está
  documentado en la UI y se corrige registrando una refinanciación con el saldo real, en
  lugar de simular una amortización precisa pero falsa.

## [0.7.0] - 2026-08-27 — Gastos recurrentes y obligaciones futuras (Épica 5)

### Añadido
- **Gastos recurrentes** (`recurring_expenses`): frecuencias semanal, quincenal, mensual,
  trimestral, semestral, anual y personalizada (cada N días), con próxima fecha, categoría y
  cuenta opcionales, pausa y notas. Vista "Próximas obligaciones" agrupada (vencidas / esta
  semana / más adelante) con días restantes y alerta de obligaciones vencidas y a ≤ 30 días
  también en el Panel.
- **"Separa ~X al mes"**: ahorro mensual necesario por obligación (SOAT $600.000 anual →
  separa $50.000/mes) y total mensual en la cabecera de la vista.
- **"Marcar pagado"**: registra el gasto real en la cuenta asociada (recomputando su saldo)
  y avanza la próxima fecha dentro de una transacción — la ocurrencia sale del comprometido
  exactamente cuando entra al gastado, sin duplicar
  ([ADR-0018](docs/DECISIONS.md#adr-0018)).
- Enum `Frequency` con aritmética de fechas segura en años bisiestos
  (`addMonthNoOverflow`/`addYearNoOverflow`), factory, policy, form requests y 44 pruebas
  nuevas (unitarias del servicio + CRUD/aislamiento por hogar + integración con el
  calculador).

### Cambiado
- El cálculo de **dinero disponible** ahora resta los gastos recurrentes reales del hogar,
  rellenando los *seams* `fixed_expenses` (arriendo, servicios — alta frecuencia) y
  `recurring` (SOAT, matrícula — baja frecuencia) declarados en cero por la Épica 4
  ([ADR-0014](docs/DECISIONS.md#adr-0014)); la fórmula y la UI del calculador no cambian, y
  el desglose "¿Cómo se calcula?" de Presupuestos pasa de "próximamente" a montos reales.
- El seeder de demo incluye arriendo, internet, SOAT y mantenimiento para que el Panel y las
  obligaciones muestren datos desde el arranque.

### Seguridad
- **Corregida una fuga entre hogares presente desde la 0.3.0** (amenaza #1). Las Policies
  autorizaban contra el hogar *del recurso* mientras los Form Requests acotaban
  `account_id`/`category_id` al hogar *activo en sesión*. Para un usuario miembro de varios
  hogares esos dos hogares no coinciden: con el hogar A activo se podía editar un recurso
  del hogar B enlazándole una **cuenta de A**. Consecuencias reproducidas: el saldo de una
  cuenta de A cambiaba por actividad de B, y un miembro de B **sin relación alguna con A**
  veía el nombre de esa cuenta en sus movimientos.
- Autorizar un recurso financiero exige ahora **dos** condiciones: ser miembro del hogar
  dueño **y** que ese hogar sea el activo ([ADR-0019](docs/DECISIONS.md#adr-0019)). El
  invariante vive en un trait único, `ChecksHouseholdAccess`, compartido por las siete
  policies de recursos financieros. `HouseholdPolicy` queda fuera a propósito: gestionar o
  activar un hogar debe poder hacerse desde fuera de él.
- `UpdateRecurringExpenseRequest` autoriza **antes** de validar, como ya hacía
  `UpdateExpenseRequest`: un usuario ajeno recibe 403 y no 422, sin importar los datos.
- Nuevo `MultiHouseholdIsolationTest` (9 casos): cubre el escenario multi-hogar que los
  tests de aislamiento clásicos no alcanzaban, porque usan un intruso que no es miembro de
  nada y la membresía ya lo frenaba.

---

## [0.6.0] - 2026-08-27 — Identidad de marca

### Añadido
- **Identidad de marca Finlia adoptada** ([ADR-0017](docs/DECISIONS.md#adr-0017)): símbolo de
  treinta puntos (los días del mes, en rejilla 6×5) en petróleo `#0B3F44` (claro) / teal oscuro
  `#3F8F8A` (oscuro), con cobre `#C08A3E`/`#D9A45E` reservado siempre para "dinero disponible".
  Reemplaza el verde-teal provisional del rediseño mobile-first.
- Componente `<x-brandmark>` (isotipo real, ya no `bi-wallet2`) en navbar, sidebar móvil y
  login/registro; favicon real (SVG + PNG + apple-touch-icon) vía
  `layouts/partials/favicon.blade.php`.
- `docs/BRAND.md`: paleta, reglas de uso del logo y qué archivo de `public/` usar para cada caso.

### Cambiado
- La cifra de "dinero disponible" (Panel y Presupuestos) usa el acento cobre de marca en vez del
  color primario, siguiendo la regla explícita del entregable de identidad.
- Todo hex de marca hardcodeado que quedaba del rediseño anterior (color de categorías, gráfico de
  ingresos, plantilla de email de invitación) se sincroniza al nuevo petróleo.

---

## [0.5.0] - 2026-08-27 — Rediseño mobile-first

### Añadido
- **Rediseño del Panel, Movimientos y Registrar gasto/ingreso**: navegación inferior con botón
  central "+" en móvil (el "Más" abre el sidebar completo desde la derecha, del mismo lado que el
  botón), sidebar de escritorio reordenado, tarjeta hero de dinero disponible, listado de
  movimientos agrupado por día con chips de filtro, y formularios con importe grande (con
  separador de miles en vivo), atajos de categoría sincronizados con el `<select>` real, y campos
  secundarios bajo "Más detalles" ([ADR-0016](docs/DECISIONS.md#adr-0016)).
- **Sistema de diseño documentado** en [docs/UI_DESIGN.md](docs/UI_DESIGN.md): componentes
  reutilizables (`.chip`, `.segmented`, `.hero-card`, input de dinero con formato de miles) con
  guía de cuándo usar cada uno. Referenciado desde `CLAUDE.md`, `AGENTS.md`,
  `docs/CONVENTIONS.md`, `docs/ARCHITECTURE.md` y los skills/agentes de Claude Code para que las
  próximas épicas lo usen por defecto.

### Cambiado
- Paleta de marca y de estados (éxito/peligro/aviso) desaturada en claro y oscuro — feedback
  directo de la sesión de diseño: "se ve demasiado neón".
- Selector de período de Presupuestos pasa de `btn-group` a chips, mismo idioma visual que
  Movimientos.
- El Panel se probó con dos variantes conmutables ("Enfoque"/"Control") y, tras verlas en uso, se
  quedó con una sola: "Control" no aportaba y hacía ver rara la barra lateral de escritorio
  ([ADR-0016](docs/DECISIONS.md#adr-0016)).

---

## [0.4.1] - 2026-08-26 — Invitaciones por correo

### Añadido
- **Las invitaciones a un hogar se envían por correo al invitado** (`HouseholdInvitationMail`).
  Hasta ahora el administrador tenía que copiar el enlace y mandarlo a mano, justo en el
  paso más importante del producto. El correo es Blade HTML autocontenido, en español,
  con versión en texto plano y sin imágenes remotas.
- Interruptor `finlia.mail.enabled` en `config/finlia.php` y detección de transports que
  **no entregan** (`log`, `array`): con ellos la pantalla no le promete al administrador un
  correo que nadie va a recibir.
- Configuración SMTP de producción documentada en `docs/DEPLOYMENT.md` §4 (Hostinger, SPF/DKIM).
- Tests: 7 de feature con `Mail::fake()` — envío al invitado, enlace con el token plano en el
  cuerpo, asunto, interruptor apagado, transport falso, caída del SMTP y aviso en la UI.

### Cambiado
- **Política de correo escrita y acotada ([ADR-0015](docs/DECISIONS.md#adr-0015))**: Finlia envía
  correo **solo cuando el destinatario no puede ver el mensaje dentro de la app** — invitación a un
  hogar y recuperación de contraseña. Recordatorios (Épica 9), resúmenes y comunicaciones de
  producto van **in-app**. Ningún correo transporta datos financieros. Añadir un correo nuevo exige
  un ADR. Se corrigen `docs/ARCHITECTURE.md` §7 y la Épica 9 de `docs/ROADMAP.md`, que dejaban la
  puerta abierta a notificar por email.
- `HouseholdService::inviteMember()` devuelve un tercer elemento (`bool $emailSent`) y acepta el
  nombre de quien invita como dato explícito, sin romper el seam de ADR-0010.
- La pantalla del hogar distingue "invitación **enviada**" de "invitación **creada**": el enlace
  manual sigue siempre visible como respaldo.

### Seguridad
- El envío nunca bloquea la operación: un SMTP caído deja la invitación creada y registra un
  `Log::warning` **sin token ni enlace** (`docs/SECURITY.md` §4).

## [0.4.0] - 2026-08-18 — Presupuestos y dinero disponible

### Añadido
- **`BudgetCalculatorService`**: responde "¿cuánto puedo gastar?" separando cuatro
  conceptos que no se mezclan — *balance actual*, *comprometido*, *disponible* y
  *libre* (ADR-0014). Sin dependencias HTTP, reutilizable por la futura API
  (ADR-0010).
- **Presupuestos** (`budgets`): total del mes y/o por categoría, con CRUD, unicidad por
  hogar/categoría/mes y `DECIMAL(15,2)` (ADR-0006). El comprometido toma el **mayor**
  entre el total y la suma de categorías para no contar dos veces.
- **Ingresos esperados** (`expected_incomes`): el usuario configura sus ingresos
  mensuales fijos (salario, arriendos, inversiones) con monto, día de cobro y estado
  activo. Son la entrada del cálculo; si no hay ninguno configurado se degrada a los
  ingresos ya registrados.
- Tarjeta **"💰 Puedes gastar aproximadamente"** en el panel de presupuestos y en el
  dashboard, con reparto diario y días restantes del período.
- Consulta por **esta semana / este mes / próximo mes**: los presupuestos se guardan
  mensuales y la vista semanal los **prorratea** (`BudgetScope` vs `BudgetPeriod`).
- **Alertas visuales al 80 % y al 100 %** por categoría (`BudgetAlertLevel`), con
  banners en presupuestos y dashboard, barras de progreso e indicador de **tendencia**
  (gasto proyectado a fin de período).
- Desglose opcional "¿Cómo se calcula?" que muestra la fórmula sin imponerla, marcando
  los términos que llegarán en las épicas 5-7.
- Enums `BudgetPeriod`, `BudgetScope` y `BudgetAlertLevel`; componente Blade
  `available-money-card`; helper y directiva `@percent` (formato colombiano `332,4 %`).
- Presupuestos e ingresos esperados de demo en `DatabaseSeeder` (datos siempre falsos).
- Tests: 26 unitarios de `BudgetCalculatorService` (prorrateo semanal, doble conteo,
  umbrales 80/100, próximo mes, aislamiento) y 36 de feature con CRUD, validación,
  mass assignment y aislamiento entre hogares (403).

### Corregido
- Los componentes `form-input` y `form-select` ignoraban el prop `id` (lo fijaban a
  `name`), de modo que el **modal de edición de categorías** de la Épica 3 no podía
  rellenarse y la página generaba `id` duplicados. Ahora aceptan `id` (y `step`).
- **Scroll horizontal en móvil en todas las pantallas**: `<main>` es un flex item sin
  `min-width: 0`, así que no podía encogerse por debajo del ancho intrínseco de su
  contenido (hasta 101 px de desbordamiento en el panel a 375 px). Verificado a 360,
  375, 414, 768, 1280 y 1440 px.
- Los **importes truncados** en las tarjetas KPI del panel ocultaban dígitos. Ahora usan
  tipografía fluida (`.money-figure`) en vez de `text-truncate`: en una app de finanzas
  un número a medias es peor que uno pequeño.
- La línea de categoría/cuenta/fecha de "Últimos movimientos" perdía la fecha en móvil
  por truncamiento; ahora envuelve.

### Notas
- El cálculo aún **no** descuenta gastos recurrentes, deuda ni ahorro programado: esos
  términos existen en el resultado con valor `0.0` y los rellenarán las épicas 5, 6 y 7
  sin cambiar la fórmula ni la UI (ADR-0014). El "puedes gastar" de hoy es, por tanto,
  optimista respecto al definitivo.

## [0.3.0] - 2026-08-14 — Cuentas, ingresos y gastos

### Añadido
- Cuentas financieras (efectivo, ahorros, corriente,…) con CRUD completo y **saldo
  persistido y recomputado en cada escritura** vía `AccountBalanceService` (ADR-0012).
- Ingresos y gastos en **tablas separadas** (ADR-0001), con dinero en `DECIMAL(15,2)`
  (ADR-0006), Form Requests y Policies por hogar; `MovementService` actualiza
  automáticamente el saldo de la cuenta afectada.
- Enums de dominio: `AccountType`, `CategoryType` y `PaymentMethod`.
- Categorías: catálogo global precargado (`CategorySeeder`) + categorías
  personalizadas por hogar, gestionadas en una única pantalla.
- Vista unificada **Movimientos** con filtros por cuenta, categoría, tipo y rango de
  fechas (`MovementSummaryService`).
- Dashboard del mes con Chart.js: totales de ingresos/gastos, gasto por categoría y
  tendencia mensual.
- Componente Blade `form-select` reutilizable para los formularios.
- Factories y seeders de datos demo (siempre falsos) y tests: Feature por recurso
  (incluye aislamiento entre hogares) y unitarios de `MovementSummaryService`.

## [0.2.0] - 2026-08-12 — Hogares, familias y miembros

### Añadido
- **Multi-tenancy por hogar** (ADR-0005): tablas `households`, `household_user` (roles
  `owner`/`member`) y `household_invitations`, con aislamiento por `household_id` en
  todas las consultas.
- CRUD completo de hogares con `HouseholdPolicy`.
- **Hogar personal auto-creado al registrarse** y concepto de *hogar activo* en sesión
  con selector en la barra superior (ADR-0011).
- Sistema de **invitaciones por email**: token aleatorio de 64 caracteres almacenado
  hasheado (sha256), expiración configurable, aceptación por enlace público y
  revocación por el owner (ADR-0003).
- Gestión de miembros desde la pantalla del hogar (expulsar, con reglas para el
  owner).
- `HouseholdService` con la lógica de dominio (crear, actualizar, invitar, aceptar,
  revocar, expulsar), sin dependencias HTTP para reutilizarla en la futura app móvil
  (ADR-0010).
- Helpers globales de hogar activo.
- Tests de hogares, invitaciones y hogar activo, incluyendo aislamiento entre hogares
  (403 ante manipulación de IDs).

## [0.1.0] - 2026-08-11 — Fundación y configuración

### Añadido
- Base operativa del repositorio: `CLAUDE.md`, `AGENTS.md`, documentación completa en
  `docs/` (arquitectura, modelo de datos, seguridad, despliegue, convenciones, ADR y
  roadmap) y la planificación Scrum en `scrum/epics/`.
- Agentes y skills de Claude Code para el flujo de trabajo: `implement-epic`,
  `security-checklist`, `epic-implementer`, `laravel-reviewer` y `security-auditor`.
- Autenticación completa a medida **sin Breeze** (ADR-0009): registro, inicio y cierre
  de sesión, y recuperación de contraseña, con validación en español (`lang/es/`),
  rate limiting en login/registro y Form Requests dedicados.
- Layout responsive con Bootstrap 5: navbar, sidebar móvil, footer, mensajes flash y
  componentes Blade reutilizables (`form-input`).
- Dashboard inicial y pantalla de bienvenida orientada al producto.
- Configuración del producto para Colombia (COP, timezone `America/Bogota`, locale
  `es`) centralizada en `config/finlia.php`.
- Tests PHPUnit de autenticación (registro, sesión, reset) y del dashboard.

### Cambiado
- **Tailwind 4 reemplazado por Bootstrap 5** vía Vite/npm (ADR-0004); eliminada la
  `welcome.blade.php` por defecto de Laravel.
- Renombrado del proyecto **Finami → Finlia** en configuración, README y documentación.
- Rediseño del front: estética glassmorphism, modo claro/oscuro con preferencia
  persistida y enfoque mobile-first.
- Sesiones, colas y caché con driver `database` para compatibilidad con hosting
  compartido (ADR-0008).
- Registrado el plan de **API REST para la app móvil (futura)** junto al ADR-0010: la
  lógica de dominio vive en Services sin dependencias HTTP.
