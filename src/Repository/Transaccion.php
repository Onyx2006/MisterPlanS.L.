<?php
declare(strict_types=1);

namespace App\Repository;

/** Frontera transaccional: el servicio decide QUÉ es atómico sin saber que por debajo hay PDO. */
interface Transaccion
{
    /**
     * @template T
     * @param callable(): T $operacion
     * @return T
     */
    public function ejecutar(callable $operacion): mixed;
}