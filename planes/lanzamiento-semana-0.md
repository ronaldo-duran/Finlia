# Plan — Semana 0: lo que tiene que estar antes de invitar a nadie

> El MVP está terminado y el siguiente paso es promocionarlo (redes personales
> primero, LinkedIn después). Este plan no añade funcionalidad: pone la
> **operación** en pie para que el lanzamiento se pueda vigilar, medir y
> revertir. Sin esto, "irla probando" significa esperar a que un usuario
> escriba para enterarse de que algo llevaba días roto.

## Contexto

El riesgo del lanzamiento no es la aplicación: es el **embudo** y la **ceguera
operativa**. Tres cosas pueden convertir un buen post en una mala primera
impresión, y ninguna se ve construyendo:

1. El registro exige verificar correo ([ADR-0029](../docs/DECISIONS.md#adr-0029))
   sobre Brevo free (**300 correos/día**, compartidos con el digest). Si el
   correo no llega o cae en spam, la persona queda atrapada en la pantalla de
   "revisa tu correo" y **nadie se entera**.
2. No hay aviso de errores en producción. Las páginas de error propias
   (v0.35.0) son buenas para el usuario y silenciosas para el dueño.
3. No hay copia de seguridad verificada. Se le va a pedir a familias reales que
   metan sus finanzas: perder la base una vez termina el proyecto.

## Estado verificado (2026-09-11) — lo que NO hay que hacer

Comprobado contra el código, para no repetir trabajo:

| Ya está | Dónde |
|---|---|
| Canal de reporte de errores, con sesión y contexto técnico adjunto | `routes/web.php:385`, enlazado en el nav y el pie de `layouts/app.blade.php` y desde `/contacto` |
| Previsualización al compartir enlaces (OG + Twitter card, 1200×630) | `resources/views/marketing/layout.blade.php:46`, `public/img/og-finlia.png` |
| Rate limiting en login, registro, reenvíos, export y reportes | `throttle:*` en `routes/web.php` |
| Sitio público, sitemap, `llms.txt`, términos y política de datos | `resources/views/marketing/`, ADR-0031, ADR-0034 |
| Export de datos y eliminación de cuenta (argumento de confianza real) | ADR-0033, ADR-0034 |
| `.env` nunca commiteado (solo `.env.example` en todo el historial) | `git log --all` |

## Tareas

### T1 — Copia de seguridad real y restauración probada · L · **bloqueante**

Hoy [DEPLOYMENT.md §11](../docs/DEPLOYMENT.md) lo tiene como *recomendación*,
no como hecho.

- Confirmar **qué backup da el plan de Hostinger y cada cuánto** — no asumirlo:
  en los planes básicos puede ser semanal, y una semana de movimientos perdidos
  ya es un problema de confianza.
- Cron nocturno con `mysqldump` (ruta absoluta del binario, como el resto de los
  comandos del servidor), destino **fuera de `public_html`**, rotación de 7 días,
  permisos `600`.
- **Probar la restauración**: cargar el dump de anoche en la base local y entrar
  a la app con él. Un backup no probado no es un backup.
- El dump contiene datos financieros de otras personas: nunca al repositorio, y
  si se descarga al portátil, cifrado.
- Pasar §11 de recomendación a procedimiento, con el comando exacto.

*Verificación*: dump de anoche restaurado en local y sesión iniciada con esos datos.

### T2 — Aviso de errores en producción · M · **bloqueante**

No hay nada instalado (producción son dos dependencias: framework y tinker).

**⚠ DECISIÓN** — dos caminos:

- **A (recomendado, cero dependencias nuevas):** comando
  `finlia:report-errors` en el Scheduler que lea `storage/logs/laravel.log`
  desde la última marca, agrupe por mensaje + archivo y envíe **un correo al
  dueño solo si hay algo**. Encaja con lo que ya existe: síncrono dentro del
  cron y SMTP de Brevo, como el digest ([ADR-0028](../docs/DECISIONS.md#adr-0028)),
  y mantiene el minimalismo de dependencias. Con menos de 100 usuarios, un
  correo por hora sobra.
- **B:** Sentry free. Mejor agrupación y tiempo real, pero dependencia nueva,
  servicio externo y **contexto de excepciones saliendo del hosting** — habría
  que revisar que no viajen montos ni correos en los payloads.

Aparte, y en el mismo paso: verificar contra el servidor real que
`APP_DEBUG=false` y `APP_ENV=production`. Con debug activo, un 500 pinta la
configuración en pantalla.

*Verificación*: provocar un error a propósito en producción y recibir el correo.

### T3 — Prueba de humo del registro, desde afuera · S · **bloqueante**

El registro cruza dos puertas que pueden dejar a alguien atrapado: la
verificación de correo (ADR-0029) y la aceptación de la versión vigente de los
términos (sin `TermsVersionSeeder` el login rebota, ADR-0031).

- Recorrer el camino completo **desde un móvil que no es tuyo y fuera de tu
  red**: registro → correo → aceptar términos → crear hogar → registrar un
  gasto → ver "puedes gastar hoy".
- Repetirlo con **Gmail y con Outlook**, revisando la carpeta de spam en ambos.
- Confirmar SPF/DKIM del dominio autenticados en Brevo y remitente del propio
  dominio ([DEPLOYMENT.md §4](../docs/DEPLOYMENT.md)).
- Nada de arreglarlo por SSH a mitad de camino: si hay que intervenir, es un bug.

*Verificación*: dos cuentas creadas de cero, una en Gmail y otra en Outlook, sin
tocar el servidor.

### T4 — Vigilar la cuota de correo · S

300/día es poco cuando los correos de verificación compiten con el digest
diario. El umbral conocido es ~200–250 digests
([ADR-0028](../docs/DECISIONS.md#adr-0028) §4).

- Añadir el conteo de correos del día al correo de T2 o al de T5.
- Anotar el gasto de un día normal **antes** de lanzar, para saber cuánto margen
  hay de verdad.
- Tener escrito el plan B: subir de plan en Brevo o activar la Fase 2 de
  ADR-0028 (un Job por destinatario).

*Verificación*: saber el número de correos que consume hoy un día cualquiera.

### T5 — Métricas del embudo · M

Sin esto, el lanzamiento no enseña nada. No hace falta Google Analytics: los
números ya están en la base.

Comando `finlia:metrics` (solo lectura, sin tablas nuevas) que imprima:

- registros y verificados (y el **% de verificación** — el primer sitio donde se
  pierde gente);
- hogares con al menos un movimiento;
- usuarios con **dos gastos en días distintos** — la señal de que la app sirve,
  no de que la probaron;
- usuarios activos 7 días después de registrarse;
- eliminaciones y suspensiones pedidas.

Programarlo semanal por correo al dueño.

*Verificación*: correrlo en producción y **anotar la línea base antes de
publicar nada**. Sin línea base, ningún número posterior significa algo.

### T6 — Licencia, colaboración y promesa a los primeros usuarios · M

**Decidido el 2026-09-12** ([ADR-0042](../docs/DECISIONS.md#adr-0042)): núcleo
AGPL + directorio `ee/` con licencia comercial, y CLA en `CONTRIBUTING.md`.
Hecho: `ee/LICENSE`, `ee/README.md`, `CONTRIBUTING.md`, la sección de licencia
del README y la regla de frontera en AGENTS.md §2.8.

Queda por cerrar:

- **Registro de marca en la SIC.** Ninguna licencia cede derechos de marca: es
  la protección que de verdad importa y la que AGPL no da. Clases de software y
  de servicios SaaS; la tasa por clase la publica la SIC. **Requisito previo
  del lanzamiento público**, no tarea futura.
- **La promesa a los primeros usuarios**: qué es gratis durante la beta y qué
  pasa con quien entre ahora, visible en el sitio público. Ser vago aquí es cómo
  se queman los primeros usuarios en la Épica 12.
- **Revisión de abogado** de `ee/LICENSE` y del consentimiento de Ley 1581 para
  el chat con IA (transferencia de datos a un tercero). No bloquea el
  lanzamiento del MVP: bloquea el primer cliente de pago y el encendido de esa
  función.

*Verificación*: marca radicada, promesa publicada y las dos revisiones legales
encargadas.

### T7 — Barrido de secretos y capturas · S

`.env` nunca entró al historial (verificado), pero el repositorio va a recibir
atención por primera vez.

- Pasada de `gitleaks` sobre `git log --all` por si una clave entró en otro
  archivo.
- Revisar que toda captura publicada sale del seeder demo (`npm run
  screenshots`), **nunca del hogar real**. Aplica igual a las historias de
  Instagram y WhatsApp, que es donde es más fácil olvidarlo.

*Verificación*: gitleaks sin hallazgos.

### T8 — Comprobar la previsualización del enlace · S

Está implementada; falta confirmar que se renderiza.

- Pegar `finlia.online` en WhatsApp y ver imagen y texto.
- Pasarlo por el **LinkedIn Post Inspector**: LinkedIn cachea la
  previsualización, así que un error descubierto después del post se queda
  pegado varios días.

*Verificación*: previsualización correcta en WhatsApp y en LinkedIn.

## Orden sugerido

| Bloque | Tareas | Por qué en ese orden |
|---|---|---|
| 1 | T1, T2 | Red de seguridad y ojos. Todo lo demás se hace a ciegas sin ellos |
| 2 | T3, T4 | El embudo, que es justo lo que un post pone a prueba |
| 3 | T5, T7, T8 | Paralelizables; T5 debe correr **antes** del primer post |
| 4 | T6 | Puede ir en paralelo, pero **cierra antes del post de LinkedIn** |

Bloqueantes del soft launch (semana 1, 5–10 personas por WhatsApp): **T1, T2, T3**.
Bloqueantes del post de LinkedIn: **todas**.

## Definición de terminado

- [ ] Un dump de anoche restaurado en local, con la app funcionando sobre él
- [ ] Un error provocado en producción llegó por correo
- [ ] `APP_DEBUG=false` confirmado en el servidor
- [ ] Dos cuentas nuevas creadas desde fuera (Gmail y Outlook) sin intervención
- [ ] Consumo diario de correo conocido, con margen y plan B escritos
- [ ] `finlia:metrics` corriendo y **línea base anotada**
- [x] Decisión de licencia registrada como ADR y `CONTRIBUTING.md` publicado
- [ ] Marca radicada en la SIC
- [ ] Promesa de gratuidad de la beta escrita y visible
- [ ] gitleaks limpio
- [ ] Previsualización verificada en WhatsApp y LinkedIn

## Lo que NO entra en la semana 0

- **Otra pasada de la Épica 11.** Su propio texto en
  [ROADMAP.md](../docs/ROADMAP.md) dice que quedó cerrada en dos pasadas y que
  lo que faltaba era infraestructura, ya desplegada — el 🟡 de la tabla está
  desactualizado. Lo que sí le faltaba es **operación** (T1, T2, T5), y de eso
  se encarga este plan. No hace falta una tercera auditoría para lanzar.
- **Épica 12 (monetización).** De ella solo se adelanta la *decisión* de T6, no
  la implementación.
- **Push de recordatorios.** Los recordatorios ya se ven in-app y llegan por
  correo.
- **Analítica de terceros.** Los números del embudo están en la base; el sitio
  público se conforma con lo que da Hostinger.
