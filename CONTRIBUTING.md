# Cómo contribuir a Finlia

Gracias por el interés. Finlia es un proyecto de una sola persona que se
mantiene en público, y tiene decisiones de arquitectura bastante cerradas: este
documento existe para que nadie invierta un fin de semana en algo que no se va a
poder integrar.

## Lo que más ayuda (en este orden)

1. **Usar la app y reportar lo que no cuadra.** Desde tu cuenta, con
   [Reportar un error](https://app.finlia.online/reportar-error) — llega con el
   contexto técnico ya adjunto. Un reporte de algo que no cuadra en un cálculo
   vale más que cualquier PR.
2. **Revisar el cálculo.** El corazón del proyecto es `BudgetCalculatorService`
   y la fórmula de "puedes gastar hoy"
   ([ADR-0040](docs/DECISIONS.md#adr-0040)). Si crees que se equivoca en un
   caso, dilo: ya pasó una vez y cambió el diseño entero.
3. **Revisión de seguridad.** El aislamiento entre hogares es la amenaza #1
   ([docs/SECURITY.md](docs/SECURITY.md)). Si encuentras una forma de ver datos
   de otro hogar, **no abras un issue público**: escribe por
   [contacto](https://finlia.online/contacto) y dame margen para corregirlo.
4. **Correcciones concretas y pequeñas** vía Pull Request.

**Antes de escribir una funcionalidad nueva, abre un issue y pregunta.** El
orden de trabajo está en [docs/ROADMAP.md](docs/ROADMAP.md) y las épicas se
construyen en secuencia; un PR que implementa algo de una épica futura, por
bueno que sea, no se va a integrar todavía.

## Antes de abrir un PR

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
```

Y que pase todo lo que pasa en CI:

```bash
vendor/bin/pint            # formato (en CI: pint --test)
composer test              # PHPUnit, SQLite en memoria
npm run build              # los assets tienen que compilar
npm run test:e2e           # Playwright (necesita: npx playwright install chromium)
```

CI corre cuatro jobs: PHP, **BD** (la misma suite contra MySQL 8 y PostgreSQL
16), Assets y E2E. Ese job de BD es el que suele sorprender: **SQLite perdona
SQL que los motores de producción rechazan**. Si tocas SQL crudo, tiene que
funcionar en los dos ([ADR-0036](docs/DECISIONS.md#adr-0036)).

## Reglas de arquitectura que no se negocian

Están completas en [CLAUDE.md](CLAUDE.md) y [AGENTS.md](AGENTS.md) (escritas
para agentes de IA, pero valen igual para personas). Lo mínimo:

- **La lógica financiera va en Services**, sin tocar la capa HTTP: nada de
  `request()`, `session()` ni `Auth::id()` dentro de un Service
  ([ADR-0010](docs/DECISIONS.md#adr-0010)). Es lo que permitirá que una futura
  API móvil reutilice la lógica sin reescribirla.
- **Cada escritura lleva Form Request + Policy.** Sin excepciones.
- **Todo dato financiero está acotado al `household`** del usuario autenticado.
- **Dinero en `DECIMAL(15,2)`**, cast a `decimal:2`. Nunca FLOAT.
- **`$fillable` explícito** en cada modelo. Nunca `$guarded = []`.
- **La UI sigue [docs/UI_DESIGN.md](docs/UI_DESIGN.md)**: el sistema de diseño
  propio (glass, chips, barra inferior, FAB), no Bootstrap genérico suelto.
- **Identificadores en inglés, UI y mensajes en español.** Commits en estilo
  Conventional Commits (`feat:`, `fix:`, `docs:`…).
- **Nunca** commitear `.env`, secretos, ni datos financieros reales. Los datos
  de demostración van por Factories/Seeders con Faker (`es_CO`).

Si el código contradice lo que dice un documento, **dilo en el PR** en vez de
sobrescribirlo: puede que el documento esté desactualizado, o puede que el
código tenga un bug.

## Licencia de tus aportes (importante)

Finlia tiene **dos licencias**:

- El **núcleo** (todo el repositorio) está bajo **AGPL-3.0-or-later**
  ([LICENSE](LICENSE)).
- El directorio **`ee/`** (funciones Premium) está bajo una **licencia
  comercial** ([ee/LICENSE](ee/LICENSE)): se publica para poder leerse y
  auditarse, no para operarse como servicio. El núcleo funciona completo sin
  ese directorio.

Las razones están en [ADR-0042](docs/DECISIONS.md#adr-0042).

Por eso, **al abrir un Pull Request aceptas lo siguiente**:

1. Que el código que aportas es tuyo y tienes derecho a aportarlo, y que no
   incluye código de terceros con licencia incompatible ni material sujeto a un
   acuerdo con tu empleador.
2. Que **cedes al titular del proyecto (Ronaldo Duran) una licencia perpetua,
   mundial, no exclusiva, gratuita e irrevocable** para usar, modificar,
   sublicenciar y redistribuir tu aporte, **incluido el derecho a
   relicenciarlo** —lo que permite publicarlo bajo AGPL, incorporarlo a `ee/`
   bajo la licencia comercial, u ofrecerlo como parte del servicio alojado.
3. Que **conservas tu copyright** sobre tu aporte y puedes seguir usándolo
   libremente donde quieras. No lo cedes: lo licencias.

Por qué se pide esto: Finlia se sostiene vendiendo el servicio alojado y las
funciones Premium. Sin esa licencia, cada decisión futura de licenciamiento
necesitaría el permiso de cada persona que haya aportado una línea, y el
proyecto quedaría congelado. Si no te sientes cómodo con este punto, un reporte
de error o una revisión del cálculo siguen siendo aportes enormes y no requieren
ceder nada.

Los aportes se reconocen en el historial de git y, si quieres, en los
agradecimientos de la app.

## Qué esperar

Esto es un proyecto de tiempo libre: una respuesta puede tardar días. Un PR
pequeño, con su prueba y con Pint pasando, se integra rápido; uno grande sin
issue previo probablemente no se integre. No es falta de interés — es que el
orden de las épicas es lo que mantiene el proyecto sostenible para una persona.
