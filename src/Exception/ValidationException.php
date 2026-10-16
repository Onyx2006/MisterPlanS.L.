<?php
declare(strict_types=1);

namespace App\Exception;

final class ValidationException extends HttpException
{
    /**
     * @param array<string,string> $errores
     * @param int $status 422 para el cuerpo de la petición, 400 para parámetros de consulta mal formados
     */
    public function __construct(array $errores, int $status = 422)
    {
        parent::__construct($status, 'Los datos enviados no son válidos.', $errores, 'validation_error');
    }
}