# ÉPICA 4 — PRESUPUESTOS Y DINERO DISPONIBLE

Implementa un sistema de presupuestos que ayude al usuario a saber cuánto puede gastar realmente.

## Objetivo

No limitarse a mostrar gastos históricos.

El sistema debe responder:

> "¿Cuánto puedo gastar este mes sin comprometer mis obligaciones?"

## Presupuestos

Permitir definir:

- presupuesto mensual total;
- presupuesto por categoría.

Ejemplo:

Alimentación: $800.000
Transporte: $300.000
Entretenimiento: $150.000

## Cálculo

Crear un servicio especializado para calcular:

### Dinero disponible

Ingresos esperados
- gastos fijos
- gastos recurrentes próximos
- obligaciones de deuda
- ahorro programado
- presupuesto comprometido

= dinero disponible

Separar claramente:

- balance actual;
- dinero disponible;
- dinero comprometido;
- dinero libre.

No mezclar estos conceptos.

## Dashboard

Mostrar una tarjeta principal:

> 💰 Puedes gastar aproximadamente:
>
> $XXX.XXX

Y debajo:

- gastado;
- comprometido;
- disponible;
- días restantes del mes.

## Indicadores

Mostrar:

- presupuesto consumido;
- porcentaje;
- tendencia;
- categorías excedidas.

## Alertas

Cuando una categoría supere:

- 80%;
- 100%.

Mostrar advertencias visuales.

## UX

El usuario debe entender el resultado sin conocimientos financieros.

Evitar fórmulas complejas visibles.

Permitir consultar:

- esta semana;
- este mes;
- próximo mes.

## Arquitectura

Crear un servicio dedicado, por ejemplo:

BudgetCalculatorService

No colocar toda la lógica financiera dentro de controladores.

Crear tests exhaustivos para los cálculos.