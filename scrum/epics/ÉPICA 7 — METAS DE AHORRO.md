# ÉPICA 7 — METAS DE AHORRO

Implementa metas financieras.

## Objetivo

Permitir transformar objetivos abstractos en objetivos medibles.

Ejemplos:

- Fondo de emergencia;
- viaje;
- cuota inicial vivienda;
- comprar computador;
- SOAT;
- vacaciones;
- inversión.

## Entidad

Crear `savings_goals`.

Campos:

- household_id;
- name;
- target_amount;
- current_amount;
- target_date;
- priority;
- status;
- notes.

## Funcionalidades

Permitir:

- crear meta;
- editar;
- pausar;
- completar;
- registrar aportes;
- retirar dinero;
- consultar historial.

## Cálculo

Mostrar:

- porcentaje completado;
- monto restante;
- tiempo restante;
- aporte mensual recomendado.

Ejemplo:

Meta:

$30.000.000

Actual:

$4.000.000

Faltan:

$26.000.000

## Dashboard

Mostrar progreso visual.

## Fondo de emergencia

Crear posibilidad de marcar una meta como:

`emergency_fund`

Esto permitirá posteriormente utilizarla en cálculos financieros.

## Tests

Probar:

- aportes;
- retiros;
- progreso;
- metas vencidas;
- cálculos.