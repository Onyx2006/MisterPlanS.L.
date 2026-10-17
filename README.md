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