<?php
declare(strict_types=1);

namespace App\Domain;

/** Estados posibles y, sobre todo, qué transiciones entre ellos son legales. */
enum EstadoReserva: string
{
    case Pendiente = 'pendiente';
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';

    public function puedeTransitarA(self $destino): bool
    {
        return match ($this) {
            self::Pendiente  => $destino === self::Confirmada || $destino === self::Cancelada,
            self::Confirmada => $destino === self::Cancelada,
            self::Cancelada  => false, // estado final
        };
    }

    /** @return list<string> */
    public static function valores(): array
    {
        return array_map(static fn (self $e): string => $e->value, self::cases());
    }
}