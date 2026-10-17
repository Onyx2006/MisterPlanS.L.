# Reservas de alojamiento - Prueba técnica MisterPlan
API REST en **PHP nativo** (sin framework), vistas con **Twig** y una interfaz en **JavaScript vanilla** que consume la API.
- **Stack:** PHP ≥ 8.1 · MySQL/MariaDB (PDO) · Twig 3 · JS (módulos ES, sin librerías) · PHPUnit

Sitio web accesible: https://misterplan.infinityfree.io/

## ¿Cómo ponerlo en marcha?
Requisitos: PHP 8.1+ con `pdo_mysql` y `mbstring`, Composer y MySQL/MariaDB (MAMP/WAMP sirven).

```bash
# 1. Dependencias
composer install

# 2. Base de datos (un solo import: esquema + datos de ejemplo)
mysql -u root -p -e "CREATE DATABASE misterplan_reservas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p misterplan_reservas < database/reservas.sql
#   En phpMyAdmin: crear la BD -> entrar en ella -> Importar -> database/reservas.sql

# 3. Configuración de la conexión
cp .env.example .env        # y edita DB_USER / DB_PASS

# 4. Arrancar
composer serve
# abre http://localhost:8000

# Tests
composer test
```

Con Apache (MAMP/WAMP/XAMP) apunta el *document root* a `public/`. Si no puedes cambiarlo (hosting compartido), el `.htaccess` de la raíz reescribe todo hacia `public/` y deja `src/`, `vendor/` y `.env` fuera de alcance.

## API
Base: `/api`. Entrada y salida en JSON (`Content-Type: application/json`).

| Método | Ruta | Descripción | Códigos |
|---|---|---|---|
| GET | `/api/reservas` | Listado con filtros y paginación | 200, 400 |
| GET | `/api/reservas/{id}` | Detalle + historial de eventos | 200, 404 |
| POST | `/api/reservas` | Crear (siempre nace `pendiente`) | 201 + `Location`, 400, 413, 415, 422 |
| PATCH | `/api/reservas/{id}/estado` | `{"estado":"confirmada"\|"cancelada"}` | 200, 404, 409, 422 |

**Filtros** (combinables): `estado`, `desde`, `hasta` (AAAA-MM-DD), `huesped` (búsqueda parcial), `page`, `per_page` (1-100, por defecto 20).
`desde`/`hasta` filtran por **solapamiento**: reservas cuya estancia toca el intervalo.

**Errores**, siempre con la misma forma:
```json
{ "error": { "code": "validation_error", "message": "...", "errors": { "email_huesped": "Email no válido." } } }
```

Criterio de códigos: 
- `400` petición mal formada (JSON roto, filtros inválidos) 
- `422` JSON correcto pero datos inválidos 
- `409` transición de estado no permitida 
- `415`/`413` tipo o tamaño de cuerpo no aceptado 
- `403` origen no permitido 
- `405` con cabecera `Allow`

`importe` viaja como **string decimal** (`"120.50"`), no como número: con dinero no quiero que un float decida el redondeo.

## Decisiones de diseño
**Capas y responsabilidades** (cada clase tiene una razón para cambiar):
| Capa | Clases | Sabe de | No sabe de |
|---|---|---|---|
| HTTP | `Request`, `Response`, `Router`, controladores | HTTP | SQL, reglas de negocio |
| Validación | `ReservaValidator` | Forma y límites de los datos | Base de datos |
| Negocio | `ReservaService`, `EstadoReserva` | Reglas, transiciones, qué es atómico | HTTP, SQL, PDO |
| Persistencia | `ReservaRepository`, `ReservaEventoRepository`, `PdoTransaccion` | SQL | Reglas de negocio |
| Raíz de composición | `App` | Cómo se conecta todo | — |

El servicio depende de **interfaces** (repositorios y `Transaccion`): por eso sus tests usan dobles en memoria, sin base de datos, y se ejecutan en milisegundos.

**Máquina de estados** en el enum `EstadoReserva`: `pendiente → confirmada | cancelada`, `confirmada → cancelada`, `cancelada` es final. Una reserva cancelada no se reactiva. Está en un único sitio y la API y las vistas la comparten.

**Atomicidad y concurrencia.** Crear o cambiar de estado escribe en dos tablas (reserva + evento): va en una transacción. El cambio de estado hace `SELECT … FOR UPDATE` antes de decidir, de modo que dos peticiones simultáneas sobre la misma reserva se serializan y la segunda ve el estado ya cambiado (y recibe un 409 en vez de pisarlo).

**Por qué MySQL.** Es lo que usa el equipo y el modelo es relacional (reserva 1—N eventos con integridad referencial). Los `CHECK` y las claves foráneas del DDL son la última barrera si alguien escribe en la BD sin pasar por la API.

**Una sola fuente de reglas.** Las páginas Twig usan el mismo `ReservaService` que la API; no hay una segunda implementación de filtros.

**Manejo de errores centralizado** (`ErrorHandler`): las `HttpException` llevan un mensaje público; cualquier otra excepción se registra en el log y el cliente solo recibe un mensaje genérico (nunca SQL, rutas ni trazas, salvo `APP_DEBUG=true` en local). Los warnings de PHP se convierten en excepciones, así acaban en el mismo sitio.

## Seguridad
| Riesgo | Medida |
|---|---|
| Inyección SQL | PDO con `ATTR_EMULATE_PREPARES=false` (sentencias preparadas reales); ningún dato del usuario se concatena en SQL; el `ORDER BY` es una constante |
| Comodines en `LIKE` | `%`, `_` y el carácter de escape se escapan en la búsqueda por huésped |
| XSS (servidor) | Autoescape de Twig en todas las salidas. **No uso `\|raw` en ningún sitio**; para respetar saltos de línea en las notas uso `\|nl2br`, que escapa primero y añade el `<br>` después |
| XSS (cliente) | El JS escribe con `textContent` / `createElement`, nunca con `innerHTML` |
| Defensa en profundidad XSS | CSP `default-src 'self'` (sin scripts ni estilos inline), `nosniff`, `X-Frame-Options: DENY` |
| CSRF | La API exige `Content-Type: application/json` (un formulario cruzado no puede enviarlo sin CORS, que no habilito) y además rechazo peticiones de escritura con `Origin` distinto del `Host` |
| Mass assignment | Lista blanca de campos: `estado`, `id` o `fecha_creacion` enviados por el cliente se ignoran |
| Entradas con tipos raros | Se comprueba el tipo antes del valor (`"nombre": ["a"]` es un 422, no un fatal); fechas con parseo estricto (rechaza `2026-02-30`) |
| Cuerpos enormes | Límite de 64 KB (se leen como mucho 64 KB + 1 byte) |
| Fuga de información | Errores genéricos al cliente, detalle solo en el log |
| Secretos | Credenciales en `.env` (fuera de Git) o variables de entorno |
| Acceso a ficheros | El único punto de entrada es `public/`; `src/`, `vendor/`, `.env` y `database/` no se sirven |

## Frontend
- Módulos ES pequeños (`api.js`, `ui.js`, `lista.js`, `detalle.js`), sin lógica inline en el HTML.
- `api.js` normaliza los errores (`ApiError`): sin red, 400/422 con detalle por campo, 409, 5xx o respuestas no JSON (p. ej. un 502 de un proxy) acaban en un mensaje legible.
- Estado de carga (`aria-busy`), avisos en una región `aria-live` y errores del servidor junto a su campo.
- Si lanzas un filtro mientras otro sigue en vuelo, el anterior se cancela (`AbortController`): una respuesta lenta y vieja no pisa a la nueva.
- Progressive enhancement: el formulario de filtros funciona sin JS (GET normal); con JS se intercepta y se usa la API.
- La validación de cliente es solo cortesía; la que manda es la del servidor.

## Tests
`composer test`: validador (casos límite y hostiles) y servicio (transiciones, atomicidad, no-reactivación, 404) con dobles en memoria. Priorizo pocos tests con sentido sobre cobertura amplia.

## Qué haría diferente con más tiempo
- Tests de integración de los repositorios contra una BD real (MariaDB en CI) y del flujo HTTP completo
- Disponibilidad real: ahora no hay catálogo de alojamientos; dos reservas del mismo alojamiento en las mismas fechas son posibles
- Alojamiento como select si tuviese por ejemplo 5 alojamientos que el usuario tenga que escoger uno con un select y varios options y no escribirlos a mano
- Guardar fechas/hora en UTC y convertir en la presentación; hoy usa la zona del servidor
- Un endpoint que devuelva el fragmento HTML de la tabla (o plantillas compartidas) para no tener el pintado de filas duplicado en Twig y JS
- Autenticación y autorización (quién puede confirmar/cancelar) y registro de *quién* hizo cada evento
- Contenedor Docker + CI (lint, tests) y migraciones versionadas en lugar de un único `.sql`

## Escalabilidad: 1000 reservas/hora
1000/hora son ~0,3 escrituras por segundo: **no es un problema de carga** para este diseño; un solo servidor y una sola BD lo absorben con holgura. Lo que cambiaría, por orden de utilidad:
1. **Integridad bajo concurrencia** (lo importante a esta escala): control de disponibilidad con restricción/locking por alojamiento y fechas, e **idempotencia** en el POST (cabecera `Idempotency-Key`) para que un reintento del cliente no duplique reservas.
2. **Índices y paginación**: ya hay índices por estado/fechas y paginación obligatoria. El `LIKE '%x%'` por nombre no usa índice; con muchas filas pasaría a `FULLTEXT` o a búsqueda por prefijo.
3. **Rate limiting** en el borde (proxy/WAF) y **observabilidad**: logs estructurados, métricas de latencia y tasa de 4xx/5xx.
4. **Trabajo asíncrono**: si crear una reserva dispara emails o integraciones, a una cola, fuera de la petición.
5. Solo si las lecturas dominaran: caché de listados y una réplica de lectura. Hoy sería sobreingeniería.