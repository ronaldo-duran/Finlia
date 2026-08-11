# ÉPICA 12 — PREPARACIÓN PARA MONETIZACIÓN Y MODELO SAAS

Preparar la aplicación para monetización sin perjudicar la experiencia gratuita.

## Objetivo

Permitir posteriormente:

- plan gratuito;
- plan Premium;
- publicidad;
- funciones avanzadas.

## Arquitectura

Crear concepto de:

- plans;
- subscriptions;
- features;
- usage limits.

No integrar todavía una pasarela de pagos si no es necesaria.

## Plan gratuito

Inicialmente podría incluir:

- 1 hogar;
- 2 miembros;
- gastos;
- ingresos;
- presupuestos;
- metas;
- deudas;
- reportes básicos.

## Premium futuro

Preparar soporte para:

- hogares adicionales;
- miembros adicionales;
- reportes avanzados;
- exportación PDF;
- análisis avanzados;
- mayor historial;
- múltiples monedas;
- automatizaciones;
- eliminación de publicidad.

## Publicidad

Preparar puntos de integración, pero NO introducir publicidad invasiva.

No mostrar anuncios:

- dentro de formularios;
- inmediatamente después de registrar gastos;
- en información financiera crítica.

## Principio

La versión gratuita debe seguir siendo útil.

No crear artificialmente problemas para obligar al usuario a pagar.

## Seguridad

Nunca permitir que cambiar un parámetro frontend permita desbloquear funciones Premium.

La autorización debe realizarse en backend.