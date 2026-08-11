# ÉPICA 9 — RECORDATORIOS Y NOTIFICACIONES

Implementa recordatorios financieros.

## Objetivo

Evitar olvidos de:

- cuotas;
- tarjetas;
- arriendo;
- servicios;
- SOAT;
- tecnomecánica;
- metas.

## Tipos

Crear recordatorios para:

- gasto recurrente;
- deuda;
- meta;
- obligación anual.

## Estados

- pendiente;
- próximo;
- vencido;
- completado.

## Canales

Inicialmente:

- notificación dentro de la aplicación.

Preparar arquitectura para posteriormente:

- email;
- WhatsApp;
- push notifications.

## Scheduler

Utilizar Laravel Scheduler cuando sea apropiado.

Debe ser compatible con cron de Hostinger.

No depender de workers persistentes.

## Dashboard

Mostrar:

> 🔔 Tienes 3 obligaciones próximas.

## Configuración

Permitir activar/desactivar recordatorios.