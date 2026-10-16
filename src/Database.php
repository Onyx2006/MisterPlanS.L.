<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Database
{
    public static function conectar(Config $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config->get('DB_HOST', '127.0.0.1'),
            $config->get('DB_PORT', '3306'),
            $config->get('DB_NAME', 'misterplan_reservas')
        );

        return new PDO($dsn, $config->get('DB_USER', 'root'), $config->get('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Prepared statements reales en el servidor: los datos nunca se mezclan con el SQL.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}