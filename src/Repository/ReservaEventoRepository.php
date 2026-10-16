<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class ReservaEventoRepository implements ReservaEventoRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function registrar(int $reservaId, string $tipo, string $descripcion): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reserva_evento (reserva_id, tipo, descripcion) VALUES (:reserva, :tipo, :descripcion)'
        );
        $stmt->execute([':reserva' => $reservaId, ':tipo' => $tipo, ':descripcion' => $descripcion]);
    }

    public function listarPorReserva(int $reservaId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, tipo, descripcion, fecha_creacion FROM reserva_evento WHERE reserva_id = :reserva ORDER BY fecha_creacion, id'
        );
        $stmt->execute([':reserva' => $reservaId]);

        return array_map(static function (array $fila): array {
            $fila['id'] = (int) $fila['id'];
            return $fila;
        }, $stmt->fetchAll());
    }
}