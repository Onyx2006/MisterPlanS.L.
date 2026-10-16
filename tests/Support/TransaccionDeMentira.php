<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Repository\Transaccion;

/** Ejecuta la operación tal cual y cuenta cuántas veces se usó (para comprobar qué es atómico). */
final class TransaccionDeMentira implements Transaccion
{
    public int $ejecuciones = 0;

    public function ejecutar(callable $operacion): mixed
    {
        $this->ejecuciones++;
        return $operacion();
    }
}