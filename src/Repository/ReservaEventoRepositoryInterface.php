<?php
declare(strict_types=1);

namespace App\Repository;

interface ReservaEventoRepositoryInterface
{
    public function registrar(int $reservaId, string $tipo, string $descripcion): void;

    /** @return list<array<string,mixed>> orden cronológico */
    public function listarPorReserva(int $reservaId): array;
}