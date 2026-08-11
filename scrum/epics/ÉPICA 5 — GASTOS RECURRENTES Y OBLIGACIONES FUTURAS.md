# ÉPICA 5 — GASTOS RECURRENTES Y OBLIGACIONES FUTURAS

Implementa planificación de gastos futuros.

## Objetivo

Evitar que gastos previsibles aparezcan como emergencias.

Ejemplos:

- SOAT;
- tecnomecánica;
- seguro;
- matrícula;
- impuestos;
- arriendo;
- internet;
- servicios;
- vacunas de mascotas;
- mantenimiento de moto;
- cumpleaños;
- viajes;
- suscripciones.

## Entidad

Crear `recurring_expenses`.

Debe soportar:

- nombre;
- monto estimado;
- frecuencia;
- fecha próxima;
- categoría;
- cuenta;
- activo/inactivo;
- notas.

Frecuencias:

- semanal;
- quincenal;
- mensual;
- trimestral;
- semestral;
- anual;
- personalizada.

## Gastos anuales

Ejemplo:

SOAT:

Monto: $600.000
Próximo pago: noviembre 2026
Frecuencia: anual

La aplicación debe calcular:

> Necesitas separar aproximadamente $50.000 mensuales.

## Próximos pagos

Crear sección:

> Próximas obligaciones

Mostrar:

- nombre;
- fecha;
- monto;
- días restantes;
- monto recomendado para ahorrar.

## Alertas

Mostrar avisos como:

> El SOAT vence en 30 días.

## Integración

Estos gastos deben alimentar el cálculo de:

> "¿Cuánto puedo gastar?"

No duplicar gastos cuando el usuario marque uno como pagado.

## Tests

Probar correctamente:

- mensual;
- anual;
- fechas futuras;
- años bisiestos;
- obligaciones vencidas;
- cálculo de ahorro mensual necesario.