<?php
declare(strict_types=1);

namespace App\Repository;

use App\Domain\ReservaFiltro;

interface ReservaRepositoryInterface
{
    /** @return array{0:list<array<string,mixed>>,1:int} [filas de la página, total sin paginar] */
    public function buscar(ReservaFiltro $filtro): array;

    /** @return array<string,mixed>|null  con $bloquear=true se toma un lock de fila (SELECT ... FOR UPDATE) */
    public function buscarPorId(int $id, bool $bloquear = false): ?array;

    /** @param array<string,mixed> $datos datos ya validados */
    public function insertar(array $datos): int;

    public function actualizarEstado(int $id, string $estado): void;
}