# ÉPICA 15 — CUENTAS POR COBRAR

> Estado: 🔴 No iniciada · Dependencias: **3** (cuentas/gastos/ingresos), **6** (patrón deudas), **9** (recordatorios).
>
> Reportada por usuario real vía WhatsApp el **2026-09-16**. Simétrica a la
> Épica 6 (deudas): en lugar de "yo debo", **"me deben"**. Sin esto, la app
> responde bien la pregunta "cuánto puedo gastar" pero se queda ciega ante el
> dinero comprometido a favor del hogar.

## Alcance

Un módulo para registrar que **alguien le debe dinero al hogar**, seguirle el
rastro, cobrarlo y — cuando entra el pago — registrar el ingreso sin doble
captura.

Casos de uso reportados:

- Un amigo me pidió prestado, quiero saber cuánto me debe y cuándo prometió pagar.
- Le facturé un trabajo a un cliente y espera 15 días para pagarme.
- Un familiar me está pagando una deuda por cuotas y quiero verlas todas.
- Necesito posponer la fecha de cobro sin perder el histórico.

## Modelo de datos

Un patrón espejo del de deudas (Épica 6), acotado por `household_id`:

```
receivables
├─ id, household_id, name, description
├─ debtor_name (string, quién debe: puede no estar en el sistema)
├─ debtor_user_id (nullable FK: solo si es otro miembro del hogar)
├─ original_amount DECIMAL(15,2)
├─ current_balance DECIMAL(15,2)   -- se recalcula desde payments
├─ currency (COP por defecto)
├─ status (pending, partial, paid, written_off)
├─ due_date (date, nullable)       -- fecha tentativa de cobro
├─ notes
├─ timestamps + soft deletes

receivable_payments
├─ id, receivable_id
├─ amount DECIMAL(15,2), date, type (received, forgiven, adjustment)
├─ account_id (nullable FK: la cuenta que recibió el pago)
├─ income_id (nullable FK: ingreso generado si hubo cuenta, ADR-0021 espejo)
├─ notes
├─ timestamps
```

**Reglas de aislamiento** (obligatorias, ver [docs/SECURITY.md](../../docs/SECURITY.md)):
policy por recurso, global scope por `household_id`, Form Requests que
validen la pertenencia de `account_id` al mismo hogar. Un cobro NUNCA acepta
`account_id` de otro hogar.

## Reglas financieras

- **Dinero**: siempre `DECIMAL(15,2)`, cast `decimal:2`. Nunca FLOAT.
- **Ingreso automático al cobrar**: si `account_id` viene en el pago, se
  crea un `Income` real y sube el saldo de esa cuenta. Mismo patrón que
  ADR-0021 (pagos de deuda). Si no hay cuenta, se registra el pago pero no
  se toca ningún saldo (equivalente a "me pagaron en efectivo y no lo
  ingresé a ninguna cuenta").
- **Saldo pendiente**: `current_balance = original_amount − Σ payments.amount`
  recalculado en un Service (`ReceivableService::recomputeBalance()`), no en
  el controlador.
- **Estados**:
  - `pending`: saldo == original.
  - `partial`: 0 < saldo < original.
  - `paid`: saldo == 0. Automático desde el Service.
  - `written_off`: dado por perdido. Se marca a mano; permite cerrar sin
    ingreso real.
- **Posponer el cobro**: editar `due_date` deja un rastro (evento en
  `activity_log` o similar). No hay borrado silencioso.

## Recordatorios

Enchufar al motor de ADR-0028 (Épica 9). Un `Receivable` con `due_date` a X
días entra en el digest del cobrador exactamente como una obligación
próxima entra en la Épica 5. Cuando `due_date` pasa sin pago, sube a
"urgente" y desbloquea el correo del digest (misma regla de 300/día de
Brevo).

## Servicios y controladores

Espejo de deudas:

- `ReceivableService`: crear, registrar cobro, borrar cobro, recomputar
  saldo, listar próximas a cobrar. **No depende de HTTP** ([ADR-0010](../../docs/DECISIONS.md#adr-0010)).
- `ReceivableController` (index/show/create/store/edit/update/destroy).
- `ReceivablePaymentController` (store/destroy).
- Policies: `ReceivablePolicy`, `ReceivablePaymentPolicy`.

## UI

Sigue [docs/UI_DESIGN.md](../../docs/UI_DESIGN.md): `.chip-row` para filtrar
por estado, `.hero-card` con total por cobrar, listado con progreso, ficha
individual con formulario de cobro (idéntico patrón al de pago de deuda).
Entrada al módulo desde el bottom nav o el menú de "Deudas → Deudas y
cobros", a decidir en implementación.

## Dashboard (Épica 8)

Añadir tarjeta "Por cobrar" al lado de "Deuda". Es dinero comprometido a
favor del hogar; el usuario tiene que verlo junto al comprometido en contra.

## Alertas de seguridad específicas

- `debtor_name` es texto libre → escapar en Blade (`{{ }}`, no `{!! !!}`).
- No mostrar cuánto deben a un usuario los cobros de otro hogar en un panel
  de "personas": el aislamiento por hogar aplica aquí igual.
- `receivable_payments.type = adjustment` puede reducir el saldo sin ingreso
  real. Auditar los usos para que no se cuelen negativos.

## Definición de terminado (siguiendo [AGENTS.md](../../AGENTS.md))

- Migraciones + índices + FKs con onDelete correcto.
- Modelos con `$fillable` (nunca `$guarded = []`).
- Form Requests por operación de escritura.
- Policies con test de aislamiento entre hogares (feature test que verifique
  IDOR imposible).
- Seeders/Factories.
- Recordatorios enganchados a Épica 9 con tests del digest.
- Métrica en `finlia:metrics`: número de `receivables` con saldo > 0 y suma
  del `current_balance` del hogar.
- Documentación en `docs/DATA_MODEL.md` y ADR si hay decisiones no
  triviales (p. ej. si un cobro puede ligarse a `debtor_user_id` de otro
  miembro, para "mi hija me está pagando el préstamo").

## Fuera de alcance

- **Recordar al deudor** por correo o WhatsApp. Este módulo lleva la
  contabilidad; los mensajes al tercero son manuales por ahora.
- **Facturas o comprobantes** adjuntos. Se puede añadir después con el mismo
  mecanismo que Épica 6 no incluyó.
- **Divisas mezcladas**. El cobro se registra en la moneda del `receivable`;
  la conversión no entra en el MVP del módulo, igual que en deudas.
