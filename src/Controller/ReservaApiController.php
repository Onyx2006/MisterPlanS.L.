<?php
declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Service\ReservaService;

/** Capa fina: traduce HTTP <-> servicio */
final class ReservaApiController
{
    public function __construct(private readonly ReservaService $servicio)
    {
    }

    /** @param array<string,string> $parametros */
    public function listar(Request $peticion, array $parametros): Response
    {
        return Response::json($this->servicio->listar($peticion->query));
    }

    /** @param array<string,string> $parametros */
    public function ver(Request $peticion, array $parametros): Response
    {
        return Response::json(['data' => $this->servicio->detalle((int) $parametros['id'])]);
    }

    /** @param array<string,string> $parametros */
    public function crear(Request $peticion, array $parametros): Response
    {
        $reserva = $this->servicio->crear($peticion->json());

        return Response::json(['data' => $reserva], 201, ['Location' => '/api/reservas/' . $reserva['id']]);
    }

    /** @param array<string,string> $parametros */
    public function cambiarEstado(Request $peticion, array $parametros): Response
    {
        $reserva = $this->servicio->cambiarEstado((int) $parametros['id'], $peticion->json());

        return Response::json(['data' => $reserva]);
    }
}