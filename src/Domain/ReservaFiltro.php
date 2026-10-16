<?php
declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/** Filtros ya validados y tipados: el repositorio nunca ve datos "crudos" del usuario. */
final class ReservaFiltro
{
    public function __construct(
        public readonly ?EstadoReserva $estado = null,
        public readonly ?DateTimeImmutable $desde = null,
        public readonly ?DateTimeImmutable $hasta = null,
        public readonly ?string $huesped = null,
        public readonly int $pagina = 1,
        public readonly int $porPagina = 20
    ) {
    }

    public function offset(): int
    {
        return ($this->pagina - 1) * $this->porPagina;
    }
}