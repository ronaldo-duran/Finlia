# `ee/` — Funciones Premium

> **Licencia distinta.** Este directorio **no** está bajo la AGPL del resto del
> repositorio: se rige por [ee/LICENSE](LICENSE). Se publica para que pueda
> leerse y auditarse, no para que pueda operarse como servicio.
> Decisión y razones: [ADR-0042](../docs/DECISIONS.md#adr-0042).

Hoy está **vacío a propósito**. Las funciones Premium llegan con la
[Épica 12](../docs/ROADMAP.md); el directorio existe desde ahora para que la
frontera esté puesta antes de que haya código que moverla cueste caro.

## La regla que no se rompe: la dependencia va en un solo sentido

```
ee/  ──depende de──>  núcleo (app/, resources/, routes/)
ee/  <──NUNCA──────   núcleo
```

**Borrar `ee/` debe dejar una aplicación que arranca, pasa la suite y sirve
todas las funciones gratuitas.** No es una preferencia de estilo: es lo que
sostiene la licencia. Si el núcleo AGPL necesitara algo de aquí para
funcionar, `ee/` sería parte de la obra AGPL y esta licencia no tendría
sentido.

En la práctica:

- **Ningún archivo de `app/` importa una clase `Finlia\Ee\*`.** La única
  excepción es el punto de registro condicional descrito abajo.
- El núcleo no pregunta "¿existe `ee/`?" por todas partes. Pregunta a una
  puerta de features si la función está disponible, y la respuesta por defecto
  —sin `ee/`— es *no disponible*.
- El patrón ya está en uso en el núcleo: `ReportFormat` es un enum con un
  `match` donde el PDF Premium añade su caso
  ([ADR-0026](../docs/DECISIONS.md#adr-0026) §4). Esa es la forma de coser algo
  nuevo: un caso más en un seam que ya existe, no un `if` repartido por las
  vistas.

## Estructura prevista (Épica 12, no implementada)

```
ee/
├── LICENSE              # licencia comercial (este directorio)
├── README.md            # este archivo
├── src/                 # namespace Finlia\Ee\  (PSR-4, se añade en la Épica 12)
│   └── EeServiceProvider.php
└── tests/               # suite propia, añadida a phpunit.xml en la Épica 12
```

El proveedor se registra **condicionalmente**, para que la ausencia del
directorio sea un caso normal y no un error:

```php
// Punto único de contacto del núcleo con ee/.
if (class_exists(\Finlia\Ee\EeServiceProvider::class)) {
    // registrar
}
```

## Reglas que siguen aplicando aquí

Estar bajo otra licencia no exime de nada de lo que rige el resto del
repositorio ([CLAUDE.md](../CLAUDE.md), [AGENTS.md](../AGENTS.md)):

- **Aislamiento por hogar**, Form Request y Policy en cada escritura.
- **La autorización de Premium es de backend.** Que el código sea visible hace
  esto más importante, no menos: nunca un flag de frontend, nunca un dato del
  cliente decidiendo si una función está pagada. La comprobación se hace contra
  la suscripción en la base de datos.
- `DECIMAL(15,2)` para dinero. Nada de FLOAT.
- **Ningún secreto aquí.** Claves de API de modelos de IA, credenciales de
  correo y tokens van por `.env`, igual que siempre. Este directorio es
  código, no configuración.
- La lógica financiera vive en Services sin dependencias de HTTP
  ([ADR-0010](../docs/DECISIONS.md#adr-0010)), para que la Épica 14 (API móvil)
  la reutilice.

## Lo que NO va aquí

Cualquier cosa de la que dependa una función gratuita. Si al implementar algo
resulta que el núcleo lo necesita, la respuesta correcta es **moverlo al
núcleo bajo AGPL**, no crear una dependencia hacia dentro de `ee/`.
