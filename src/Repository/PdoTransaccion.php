<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;
use Throwable;

final class PdoTransaccion implements Transaccion
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ejecutar(callable $operacion): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $resultado = $operacion();
            $this->pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}