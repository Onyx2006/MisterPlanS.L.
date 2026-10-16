<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Domain\ReservaFiltro;
use App\Repository\ReservaRepositoryInterface;

/** Doble de prueba: guarda en memoria. Permite testear el servicio sin base de datos. */
final class InMemoryReservaRepository implements ReservaRepositoryInterface
{
    /** @var array<int,array<string,mixed>> */
    public array $filas = [];
    public int $bloqueos = 0;
    private int $siguiente = 1;

    public function buscar(ReservaFiltro $filtro): array
    {
        return [array_values($this->filas), count($this->filas)];
    }

    public function buscarPorId(int $id, bool $bloquear = false): ?array
    {
        if ($bloquear) {
            $this->bloqueos++;
        }
        return $this->filas[$id] ?? null;
    }

    public function insertar(array $datos): int
    {
        $id = $this->siguiente++;
        $this->filas[$id] = ['id' => $id, 'estado' => 'pendiente'] + $datos;
        return $id;
    }

    public function actualizarEstado(int $id, string $estado): void
    {
        $this->filas[$id]['estado'] = $estado;
    }
}