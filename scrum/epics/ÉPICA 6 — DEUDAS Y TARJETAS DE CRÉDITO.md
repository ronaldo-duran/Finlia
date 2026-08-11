# ÉPICA 6 — DEUDAS Y TARJETAS DE CRÉDITO

Implementa el módulo de administración de deuda.

## Objetivo

Permitir controlar préstamos, tarjetas y otras obligaciones.

## Deudas

Crear entidad `debts`.

Campos:

- household_id;
- name;
- institution;
- type;
- original_amount;
- current_balance;
- interest_rate;
- interest_rate_type;
- minimum_payment;
- scheduled_payment;
- due_day;
- start_date;
- end_date;
- status;
- notes.

Tipos:

- tarjeta de crédito;
- préstamo;
- crédito de vehículo;
- préstamo familiar;
- otro.

## Tarjetas

Crear soporte específico para tarjetas.

Campos:

- credit_limit;
- available_credit;
- statement_date;
- payment_due_date;
- annual_fee;
- monthly_fee.

No almacenar información sensible como:

- número completo de tarjeta;
- CVV;
- PIN.

## Dashboard de deuda

Mostrar:

> Deuda total: $XX.XXX.XXX

Y:

> Pago mensual comprometido: $X.XXX.XXX

Mostrar progreso.

## Historial

Permitir registrar pagos.

Ejemplo:

Deuda inicial:
$4.800.000

Pago:
$800.000

Nuevo saldo:
$4.000.000

## Refinanciación

Permitir registrar que una deuda fue refinanciada.

Guardar:

- tasa;
- plazo;
- cuota;
- fecha de inicio;
- saldo refinanciado.

## Objetivos

Mostrar:

> Si mantienes este ritmo, terminarías aproximadamente en X.

Aclarar que las proyecciones son estimaciones.

## Estrategias

Preparar arquitectura para posteriormente implementar:

- avalancha;
- bola de nieve.

No es necesario implementar ambas todavía si complica demasiado el MVP.

## Tests

Probar:

- creación;
- pagos;
- saldos;
- cuotas;
- permisos;
- cálculos.