<?php
declare(strict_types=1);

namespace App\Http;

use App\Exception\HttpException;
use JsonException;

final class Request
{
    /** Tope del cuerpo: una reserva ocupa pocos cientos de bytes; 64 KB es de sobra. */
    public const MAX_CUERPO = 65536;

    /**
     * @param array<string,mixed>  $query
     * @param array<string,string> $cabeceras claves en minúsculas
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $ruta,
        public readonly array $query,
        private readonly string $cuerpo = '',
        private readonly array $cabeceras = []
    ) {
    }

    public static function desdeGlobales(): self
    {
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $cuerpo = '';
        if (in_array($metodo, ['POST', 'PUT', 'PATCH'], true)) {
            // Se lee como mucho MAX+1 bytes: nunca cargo en memoria un cuerpo arbitrariamente grande.
            $cuerpo = (string) file_get_contents('php://input', false, null, 0, self::MAX_CUERPO + 1);
            if (strlen($cuerpo) > self::MAX_CUERPO) {
                throw new HttpException(413, 'El cuerpo de la petición es demasiado grande.', [], 'payload_too_large');
            }
        }

        $cabeceras = [];
        $mapa = ['CONTENT_TYPE' => 'content-type', 'HTTP_ORIGIN' => 'origin', 'HTTP_HOST' => 'host'];
        foreach ($mapa as $clave => $nombre) {
            if (isset($_SERVER[$clave])) {
                $cabeceras[$nombre] = (string) $_SERVER[$clave];
            }
        }

        return new self($metodo, $ruta, $_GET, $cuerpo, $cabeceras);
    }

    public function cabecera(string $nombre): ?string
    {
        return $this->cabeceras[strtolower($nombre)] ?? null;
    }

    /**
     * Cuerpo JSON como array asociativo. Exige Content-Type: application/json y un objeto (no lista ni escalar).
     * @return array<string,mixed>
     */
    public function json(): array
    {
        $tipo = strtolower($this->cabecera('content-type') ?? '');
        if (!str_starts_with($tipo, 'application/json')) {
            throw new HttpException(415, 'El Content-Type debe ser application/json.', [], 'unsupported_media_type');
        }

        try {
            $datos = json_decode($this->cuerpo, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpException(400, 'El cuerpo no es un JSON válido.', [], 'invalid_json');
        }

        if (!is_array($datos) || ($datos !== [] && array_is_list($datos))) {
            throw new HttpException(400, 'El cuerpo debe ser un objeto JSON.', [], 'invalid_json');
        }

        return $datos;
    }
}