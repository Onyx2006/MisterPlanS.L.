# Reservas de alojamiento - Prueba técnica MisterPlan
API REST en **PHP nativo** (sin framework), vistas con **Twig** y una interfaz en **JavaScript vanilla** que consume la API.
- **Stack:** PHP ≥ 8.1 · MySQL/MariaDB (PDO) · Twig 3 · JS (módulos ES, sin librerías) · PHPUnit

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