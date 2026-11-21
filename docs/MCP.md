# MCP (Model Context Protocol) — Finlia

> Fija el contrato del servidor MCP de Finlia: cómo se autentica, qué expone y
> cómo se consume. Decisión arquitectónica en [docs/DECISIONS.md#adr-0049](DECISIONS.md#adr-0049).

Finlia publica un servidor MCP para que un asistente de IA que el usuario ya
tenga (Claude Desktop, por ejemplo) pueda consultar los datos del hogar
activo sin leer la UI web. El servidor vive **dentro** de la misma app
Laravel para no multiplicar despliegues ni romper la restricción de hosting
compartido (CLAUDE.md §2).

## Endpoint y transporte

- **URL:** `POST https://<dominio-app>/mcp`
- **Content-Type:** `application/json`
- **Formato:** JSON-RPC 2.0 (una request → una response, sin SSE).
- **Rate limit:** 60 req/min por token (Laravel `throttle:60,1`).
- **CSRF:** excluido (`bootstrap/app.php`), porque los clientes MCP no
  manejan el token de sesión web.

Métodos JSON-RPC implementados en este primer PR:

| Método                        | Qué hace                                          |
|-------------------------------|---------------------------------------------------|
| `initialize`                  | Handshake con `protocolVersion` y `serverInfo`.   |
| `ping`                        | Liveness.                                         |
| `notifications/initialized`   | Notificación del cliente, responde 202 vacío.     |
| `tools/list`                  | Tools disponibles para el token.                  |
| `tools/call`                  | Ejecuta una tool.                                 |

## Autenticación

El endpoint acepta dos tipos de credencial en el mismo header `Authorization:
Bearer <token>`. El middleware las distingue por prefijo.

### Tokens personales (`finlia_mcp_...`)

Para clientes que no implementan OAuth: Claude Desktop, Claude Code, Cursor
local, scripts con `curl`, etc.

- Se emiten en **/perfil/mcp** y quedan atados al usuario Y al hogar activo
  del momento. Un token nunca ve datos de otro hogar aunque el usuario cambie
  el hogar activo en su sesión web — cualquier referencia a hogar que llegue
  en el payload se ignora.
- Se guardan hasheados con SHA-256. El string en claro se muestra UNA sola vez
  tras emitirlo.
- El header es `Authorization: Bearer finlia_mcp_<64 hex chars>`.
- Un token se puede revocar en cualquier momento; la revocación es definitiva
  (no se regenera el string en claro).
- `abilities`: lista de habilidades. En v0.43 solo existe `read`.

### OAuth 2.1 + PKCE (`finlia_oat_...`)

Para clientes alojados que descubren y autentican automáticamente: Claude.ai
en navegador, ChatGPT (donde el plan lo permita), Cursor web y cualquier
cliente MCP que hable OAuth.

Endpoints publicados en el authorization server:

| Endpoint                                   | Función                          |
|--------------------------------------------|----------------------------------|
| `/.well-known/oauth-protected-resource`    | RFC 9728 — apunta al AS.         |
| `/.well-known/oauth-authorization-server`  | RFC 8414 — describe capacidades. |
| `POST /oauth/register`                     | RFC 7591 — DCR abierta.          |
| `GET  /oauth/authorize`                    | Pantalla de consentimiento.      |
| `POST /oauth/authorize`                    | Emite authorization_code.        |
| `POST /oauth/token`                        | `authorization_code` / `refresh_token`. |
| `POST /oauth/revoke`                       | RFC 7009.                        |

Reglas del flujo:

- **PKCE S256 obligatorio** (`code_challenge_method=S256`). No se permite
  `plain`. Ningún cliente se autentica con secret: son públicos.
- El cliente se **registra dinámicamente** enviando `client_name` y
  `redirect_uris`. Finlia devuelve un `client_id` y no persiste secret.
- En `/oauth/authorize` el usuario elige **qué hogar** autoriza. Ese hogar
  queda fijado en el authorization_code y luego en el access/refresh token.
  Como con los PAT, cualquier mención de hogar en el payload MCP se ignora.
- **Rotación de refresh tokens**: al refrescar se emite un par nuevo y el
  anterior queda revocado (OAuth 2.1 §4.3.1).
- Scopes: en v0.44 solo `mcp:read`. El scope se mapea a la ability `read`
  para reusar el mismo seam de autorización de las tools.
- Un 401 contra `/mcp` incluye el header
  `WWW-Authenticate: Bearer resource_metadata="https://.../.well-known/oauth-protected-resource"`
  para que el cliente descubra el AS automáticamente.

Si el usuario deja de pertenecer al hogar o el hogar se borra, el token deja
de servir sin necesidad de revocarlo.

## Tools (v0.43 — solo lectura)

| Nombre                  | Qué devuelve                                       |
|-------------------------|----------------------------------------------------|
| `get_available_money`   | Cupo diario de hoy (ADR-0040, ADR-0047).           |
| `get_budget_summary`    | Plan del mes en curso + desglose por categoría.    |
| `list_recent_movements` | Últimos ingresos y gastos (ventana 60 días).       |

Cada tool es un adaptador fino sobre un Service de dominio y respeta el seam
([ADR-0010](DECISIONS.md#adr-0010)): la misma cifra que ve la web sale por
MCP sin duplicar lógica.

## Ejemplo de uso

Handshake:

```json
POST /mcp
Authorization: Bearer finlia_mcp_XXXX
Content-Type: application/json

{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}
```

Listar tools:

```json
{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}
```

Consultar el cupo diario:

```json
{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"get_available_money","arguments":{}}}
```

## Qué NO expone todavía

- Escritura (`register_expense`, `register_income`, etc.).
- Datos de tarjeta (número, CVV, PIN — nunca; CLAUDE.md §6).
- Cualquier recurso de un hogar distinto al del token.
- Prompts canned (planeados para una entrega posterior).

Las tools de escritura y la URI del hogar via `resources/*` se añadirán en
un PR aparte, cuando el patrón de solo lectura esté probado en producción.

## Compatibilidad con PostgreSQL y MySQL

El servidor MCP solo expone tools que reusan Services existentes. No añade
SQL crudo; cualquier tool que a futuro lo necesite usará `getDriverName()`
con `match` exhaustivo, igual que el resto del código
([CLAUDE.md §2](../CLAUDE.md), [ADR-0036](DECISIONS.md#adr-0036)).
