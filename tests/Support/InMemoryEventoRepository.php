<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Repository\ReservaEventoRepositoryInterface;

final class InMemoryEventoRepository implements ReservaEventoRepositoryInterface
{
    /** @var list<array<string,mixed>> */
    public array $eventos = [];

    public function registrar(int $reservaId, string $tipo, string $descripcion): void
    {
        $this->eventos[] = ['reserva_id' => $reservaId, 'tipo' => $tipo, 'descripcion' => $descripcion];
    }

    public function listarPorReserva(int $reservaId): array
    {
        return array_values(array_filter($this->eventos, static fn (array $e): bool => $e['reserva_id'] === $reservaId));
    }
}