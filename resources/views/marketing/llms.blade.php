# Finlia

> Aplicación web de finanzas personales y familiares para Colombia. Responde a una
> pregunta que un saldo bancario no responde: cuánto dinero puede gastar hoy una
> persona sin comprometer el arriendo, las cuotas de deuda ni sus metas de ahorro.

Finlia calcula cuánto se puede gastar hoy **con la plata que ya se tiene**: toma el
saldo real de las cuentas, le resta lo que vence antes del próximo pago (arriendo,
servicios, cuotas de deuda) y lo ya apartado para metas de ahorro, y reparte el resto
entre los días que faltan para ese pago. Los ingresos esperados no se suman hasta que
llegan: sirven para saber hasta cuándo debe alcanzar el dinero y para proyectar el mes
siguiente. Esa cifra —no el saldo— es la que muestra en primer plano, y es correcta
desde el primer día de uso, sin necesidad de historial.

## Datos básicos

- **Sitio**: {{ route('home') }}
- **Precio**: gratuito. Sin tarjeta de crédito para registrarse.
- **País y moneda**: Colombia, pesos colombianos (COP). Interfaz en español.
- **Plataformas**: navegador web, e instalable como aplicación (PWA) en Android e iOS.
- **Código fuente**: https://github.com/ronaldo-duran/Finlia — software libre bajo AGPL-3.0.

## Qué hace

- Registro de gastos e ingresos con categorías, cuentas y medios de pago.
- Cálculo de cuánto se puede gastar hoy con el saldo real hasta el próximo pago, y
  proyección del mes siguiente con los ingresos esperados.
- Presupuestos mensuales, totales o por categoría, con alertas antes de pasarse.
- Control de deudas y tarjetas de crédito: saldo, cuota mensual, progreso, proyección
  de fin de deuda y orden de pago por estrategia avalancha o bola de nieve.
- Metas de ahorro con aporte mensual recomendado y marca de fondo de emergencia.
- Gastos recurrentes y obligaciones anuales (SOAT, tecnomecánica, matrículas) con
  recordatorios y resumen diario opcional por correo.
- Reportes con gráficos y exportación a CSV.
- Hogares compartidos: varios miembros con cuenta propia sobre las mismas finanzas.

## Qué NO hace

- **No se conecta a bancos** ni pide credenciales bancarias. Los movimientos los
  registra la persona.
- **No da asesoría financiera ni de inversión.** Las proyecciones de deuda se
  presentan explícitamente como estimaciones, no como estados de cuenta.
- **No vende datos** a terceros. Los datos son exportables y la cuenta es eliminable
  por quien la creó.

## Preguntas frecuentes

{{ route('home') }}#preguntas

## Términos y privacidad

- Términos: {{ route('terms.show') }}
- Tratamiento de datos: {{ route('data.policy') }}
