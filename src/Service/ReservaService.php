<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\EstadoReserva;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Repository\ReservaEventoRepositoryInterface;
use App\Repository\ReservaRepositoryInterface;
use App\Repository\Transaccion;
use App\Validation\ReservaValidator;

/** Lógica de negocio. No sabe de HTTP ni de SQL: recibe arrays, devuelve arrays, lanza excepciones de dominio. */
final class ReservaService
{
    public function __construct(
        private readonly ReservaRepositoryInterface $reservas,
        private readonly ReservaEventoRepositoryInterface $eventos,
        private readonly Transaccion $transaccion,
        private readonly ReservaValidator $validador
    ) {
    }

    /**
     * @param array<string,mixed> $consulta
     * @return array{data:list<array<string,mixed>>,meta:array{page:int,per_page:int,total:int,total_pages:int}}
     */
    public function listar(array $consulta): array
    {
        $filtro = $this->validador->validarFiltros($consulta);
        [$filas, $total] = $this->reservas->buscar($filtro);

        return [
            'data' => $filas,
            'meta' => [
                'page'        => $filtro->pagina,
                'per_page'    => $filtro->porPagina,
                'total'       => $total,
                'total_pages' => max(1, (int) ceil($total / $filtro->porPagina)),
            ],
        ];
    }

    /** @return array<string,mixed> reserva con su historial en la clave `eventos` */
    public function detalle(int $id): array
    {
        $reserva = $this->reservas->buscarPorId($id) ?? throw new NotFoundException('La reserva no existe.');
        $reserva['eventos'] = $this->eventos->listarPorReserva($id);

        return $reserva;
    }

    /**
     * @param array<string,mixed> $entrada
     * @return array<string,mixed>
     */
    public function crear(array $entrada): array
    {
        $datos = $this->validador->validarNueva($entrada);

        // Reserva + evento de historial: o ambos o ninguno.
        $id = $this->transaccion->ejecutar(function () use ($datos): int {
            $id = $this->reservas->insertar($datos);
            $this->eventos->registrar($id, 'creada', 'Reserva creada en estado pendiente.');
            return $id;
        });

        return $this->detalle($id);
    }

    /**
     * @param array<string,mixed> $entrada
     * @return array<string,mixed>
     */
    public function cambiarEstado(int $id, array $entrada): array
    {
        $destino = $this->validador->validarCambioEstado($entrada);

        $this->transaccion->ejecutar(function () use ($id, $destino): void {
            // Bloqueo de fila: dos peticiones simultáneas sobre la misma reserva se serializan
            // y la segunda ve el estado ya cambiado (en vez de pisarlo).
            $reserva = $this->reservas->buscarPorId($id, true) ?? throw new NotFoundException('La reserva no existe.');
            $actual = EstadoReserva::from($reserva['estado']);

            if (!$actual->puedeTransitarA($destino)) {
                throw new ConflictException(
                    "No se puede pasar una reserva de '{$actual->value}' a '{$destino->value}'."
                );
            }

            $this->reservas->actualizarEstado($id, $destino->value);
            $this->eventos->registrar(
                $id,
                $destino->value,
                "Estado cambiado de '{$actual->value}' a '{$destino->value}'."
            );
        });

        return $this->detalle($id);
    }
}