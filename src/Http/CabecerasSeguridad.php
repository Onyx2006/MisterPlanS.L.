<?php
declare(strict_types=1);

namespace App\Http;

/**
 * Cabeceras de defensa en profundidad. La CSP prohíbe scripts y estilos inline:
 * por eso el JS vive en ficheros y no hay lógica en atributos HTML.
 */
final class CabecerasSeguridad
{
    public static function enviar(): void
    {
        header_remove('X-Powered-By');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}