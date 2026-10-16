<?php
declare(strict_types=1);

namespace App\Http;

use App\Exception\HttpException;

/**
 * Defensa anti-CSRF para una API sin sesiones ni cookies: si el navegador declara un Origin
 * distinto del Host al que escribe, se rechaza. Se suma a que exijo Content-Type JSON
 * (un formulario HTML cruzado no puede enviarlo sin pasar por CORS, que no habilito)
 */
final class ProteccionOrigen
{
    public static function verificar(Request $peticion): void
    {
        if (in_array($peticion->metodo, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $origen = $peticion->cabecera('origin');
        if ($origen === null) {
            return; // clientes que no son navegador (curl, tests) no envían Origin
        }

        $partes = parse_url($origen);
        $declarado = ($partes['host'] ?? '') . (isset($partes['port']) ? ':' . $partes['port'] : '');

        if (!hash_equals(strtolower($peticion->cabecera('host') ?? ''), strtolower($declarado))) {
            throw new HttpException(403, 'Origen no permitido.', [], 'forbidden_origin');
        }
    }
}