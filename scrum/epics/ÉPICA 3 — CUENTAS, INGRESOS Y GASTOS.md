# ÉPICA 3 — CUENTAS, INGRESOS Y GASTOS

Implementa el núcleo de registro financiero.

## Objetivo

Permitir registrar y consultar ingresos y gastos del hogar.

## Cuentas

Crear entidad `accounts`.

Ejemplos:

- efectivo;
- cuenta bancaria;
- cuenta de ahorros;
- billetera digital;
- tarjeta de crédito;
- otros.

Campos sugeridos:

- id;
- household_id;
- name;
- type;
- initial_balance;
- current_balance;
- is_active;
- notes;
- timestamps.

No duplicar innecesariamente balances si pueden calcularse de forma segura a partir de movimientos.

## Categorías

Crear categorías de gastos.

Ejemplos:

- Alimentación;
- Vivienda;
- Transporte;
- Salud;
- Mascotas;
- Entretenimiento;
- Educación;
- Deudas;
- Servicios;
- Compras;
- Otros.

Permitir posteriormente categorías personalizadas por hogar.

## Ingresos

Crear entidad de ingresos.

Campos:

- household_id;
- user_id;
- account_id;
- category;
- amount;
- date;
- description;
- notes.

## Gastos

Crear entidad de gastos.

Campos:

- household_id;
- user_id;
- account_id;
- category_id;
- amount;
- date;
- description;
- notes;
- payment_method.

## UX

Crear un botón principal:

> + Registrar gasto

El formulario debe ser rápido.

Orden recomendado:

1. valor;
2. categoría;
3. cuenta/medio de pago;
4. fecha;
5. descripción.

Permitir registrar un gasto en pocos segundos.

## Dashboard

Mostrar:

- ingresos del mes;
- gastos del mes;
- balance;
- gastos por categoría;
- últimos movimientos.

Utilizar Chart.js para gráficos.

## Filtros

Permitir filtrar por:

- fecha;
- categoría;
- cuenta;
- usuario;
- tipo de movimiento.

## Seguridad

Todos los movimientos deben estar asociados a un household.

Nunca permitir acceso entre hogares.

## Tests

Crear pruebas para:

- crear gasto;
- crear ingreso;
- editar;
- eliminar;
- filtros;
- permisos;
- cálculo de totales.

## Entregable adicional

Crear datos de demostración mediante factories/seeders.

El sistema debe quedar usable después de ejecutar:

php artisan migrate --seed