# ÉPICA 2 — HOGARES, FAMILIAS Y MIEMBROS

Implementa el sistema de hogares compartidos.

## Objetivo

Permitir que varios usuarios administren conjuntamente las finanzas de un hogar.

Ejemplo:

Ronaldo crea:

> Hogar: Ronaldo & Vanessa

Posteriormente invita a Vanessa.

Ambos pueden registrar y consultar información financiera del hogar.

## Funcionalidades

### Hogares

Crear:

- household.

Campos mínimos:

- id;
- name;
- owner_id;
- currency;
- timezone;
- timestamps.

### Miembros

Crear relación entre:

- usuarios;
- hogares.

Un usuario puede pertenecer a uno o varios hogares si la arquitectura lo permite sin complicar innecesariamente el sistema.

## Roles

Implementar inicialmente:

- owner;
- member.

El owner puede:

- modificar hogar;
- invitar miembros;
- eliminar miembros;
- administrar configuración.

El member puede:

- registrar movimientos;
- consultar información;
- utilizar las funcionalidades financieras.

## Invitaciones

Implementar un sistema básico de invitaciones.

El owner podrá introducir un correo.

El sistema deberá generar una invitación.

La invitación debe tener:

- token seguro;
- fecha de expiración;
- estado;
- hogar;
- usuario invitado.

Si inicialmente no se configura envío de correo, implementar el mecanismo de invitación de forma que posteriormente pueda conectarse a email.

## Seguridad

Un usuario solamente puede:

- ver hogares de los cuales sea miembro;
- ver información financiera perteneciente a dichos hogares.

Implementar Policies/Gates donde corresponda.

Probar específicamente intentos de acceso no autorizado.

## UI

Crear:

- selector de hogar;
- pantalla de configuración del hogar;
- miembros;
- invitaciones.

El dashboard deberá mostrar el hogar activo.

## Entregables

- migraciones;
- modelos;
- relaciones;
- policies;
- controladores;
- requests;
- vistas;
- rutas;
- tests.

No implementar todavía gastos ni ingresos.