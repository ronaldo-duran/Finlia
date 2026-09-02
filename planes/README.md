# Planes — Hardening de cuenta (pre-Épica 10)

Ajustes a la gestión de cuenta que se mapearon el 2026-08-30, tras cerrar la
Épica 9 y antes de continuar con la Épica 10. No son épicas: son requisitos
de cuenta/privacidad/cumplimiento que la app necesita tener bien puestos
antes de crecer.

## Orden sugerido y dependencias

| # | Plan | Depende de | Tamaño | Estado |
|---|---|---|---|---|
| 01 | [Verificación de correo en el registro](01-verificacion-de-correo-en-registro.md) | — | M | ✅ 2026-08-30 ([ADR-0029](../docs/DECISIONS.md#adr-0029)) |
| 02 | [Perfil: contraseña y cambio de correo](02-perfil-contrasena-y-correo.md) | 01 (reusa sus correos de verificación) | M | ✅ 2026-08-30 ([ADR-0030](../docs/DECISIONS.md#adr-0030)) |
| 03 | [Términos y condiciones versionados](03-terminos-y-condiciones-versionados.md) | — (paralelizable) | M | ✅ 2026-08-30 ([ADR-0031](../docs/DECISIONS.md#adr-0031)) |
| 04 | [Datos personales y perfil](04-datos-personales-y-perfil.md) | 02 (misma pantalla /perfil) | S–M | ✅ 2026-08-30 ([ADR-0032](../docs/DECISIONS.md#adr-0032)) |
| 05 | [Eliminación: suspensión 30 días y purga](05-eliminacion-suspension-y-purga.md) | 03 (rechazo de términos → eliminar), 04 (qué se purga) | L | ⬜ |
| 06 | [Política de retiro, migración y datos](06-politica-retiro-migracion-y-datos.md) | 05 (eliminación), 04 (perfil) | M | ⬜ |

Flujo natural: **01 → 02 → 04** (misma pantalla de perfil) por un lado,
**03** en paralelo, luego **05** (el más grande, y su puerta de entrada es
el rechazo de términos) y **06** de cierre. Todos antes de la Épica 10.

## Reglas para ejecutar cada plan

- Protocolo de siempre: inspeccionar → migraciones → modelos → Form Request
  + Policy → controladores → vistas → tests → docs. Ver CLAUDE.md §3–4.
- Cada plan registra **su ADR** al implementarse (numeración correlativa en
  `docs/DECISIONS.md`) y actualiza ROADMAP/CHANGELOG/DATA_MODEL/SECURITY.
- Al terminar cada plan: `/security-checklist`.
- Las decisiones marcadas como **⚠ DECISIÓN** dentro de cada plan necesitan
  respuesta del dueño del producto antes o durante la implementación.
- Cuando todos estén terminados, esta carpeta se elimina o archiva (como
  `scrum/epics/` en el MVP).

## Nota legal

Varios planos tocan la **Ley 1581 de 2012** (habeas data, Colombia):
verificación de identidad, consentimiento (términos), minimización de datos,
supresión y portabilidad. La implementación cubre el mecanismo técnico; la
**redacción legal** (términos, política de datos) es del dueño del producto,
idealmente con revisión de abogado. Ningún agente debe redactar texto legal
definitivo por su cuenta.
