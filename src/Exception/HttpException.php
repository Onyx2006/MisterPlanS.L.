<?php
declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/** Error con significado HTTP: el manejador central lo traduce a respuesta */
class HttpException extends RuntimeException
{
    /**
     * @param array<string,string> $errores   errores por campo
     * @param array<string,string> $cabeceras cabeceras extra (p. ej. Allow en un 405)
     */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errores = [],
        public readonly string $codigo = 'error',
        public readonly array $cabeceras = []
    ) {
        parent::__construct($message, $status);
    }
}