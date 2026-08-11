# ÉPICA 11 — HARDENING, TESTS Y PREPARACIÓN PARA PRODUCCIÓN

Audita la aplicación completa.

## Seguridad

Revisar:

- autenticación;
- autorización;
- policies;
- CSRF;
- XSS;
- SQL injection;
- mass assignment;
- sesiones;
- rate limiting;
- validaciones;
- exposición de IDs;
- acceso entre hogares.

## Privacidad

La información financiera es privada.

Verificar que un usuario nunca pueda:

- consultar otro hogar;
- consultar movimientos externos;
- modificar recursos externos.

## Base de datos

Revisar:

- índices;
- foreign keys;
- constraints;
- cascades;
- tipos de datos monetarios.

Nunca utilizar FLOAT para dinero.

Utilizar DECIMAL apropiadamente.

## Tests

Crear pruebas para las funcionalidades críticas.

Priorizar:

- autenticación;
- hogares;
- gastos;
- ingresos;
- presupuestos;
- deudas;
- metas;
- permisos;
- cálculos financieros.

## Performance

Revisar:

- N+1 queries;
- eager loading;
- consultas innecesarias;
- índices;
- paginación.

## Producción

Preparar:

- `.env.example`;
- cache;
- config;
- routes;
- storage;
- logs;
- cron.

Crear documentación específica para Hostinger.

## README

Debe incluir:

- descripción;
- stack;
- instalación;
- configuración;
- migraciones;
- seeders;
- tests;
- deployment;
- cron;
- troubleshooting.

No finalizar hasta comprobar que el proyecto puede instalarse desde cero siguiendo el README.