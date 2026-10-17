<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\EstadoReserva;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Service\ReservaService;
use App\View\ViewRenderer;

// Páginas renderizadas en servidor. Usa el MISMO servicio que la API: una sola fuente de reglas
final class ReservaViewController
{
    private const CAMPOS_FILTRO = ['estado', 'desde', 'hasta', 'huesped'];

    public function __construct(
        private readonly ReservaService $servicio,
        private readonly ViewRenderer $vistas
    ) {
    }

    /** @param array<string,string> $parametros */
    public function lista(Request $peticion, array $parametros): Response
    {
        $filtros = $this->filtrosComoTexto($peticion->query);
        $reservas = [];
        $meta = ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1];
        $errores = [];
        $status = 200;

        try {
            $resultado = $this->servicio->listar($peticion->query);
            $reservas = $resultado['data'];
            $meta = $resultado['meta'];
        } catch (ValidationException $e) {
            // La página sigue siendo usable: explico qué filtro falla en vez de mostrar un error en blanco y no saber que pasa
            $errores = $e->errores;
            $status = 400;
        }

        return Response::html($this->vistas->render('reservas/lista.html.twig', [
            'reservas'      => $reservas,
            'meta'          => $meta,
            'filtros'       => $filtros,
            'errores'       => $errores,
            'estados'       => EstadoReserva::valores(),
            'url_anterior'  => $meta['page'] > 1 ? $this->urlPagina($filtros, $meta['page'] - 1) : null,
            'url_siguiente' => $meta['page'] < $meta['total_pages'] ? $this->urlPagina($filtros, $meta['page'] + 1) : null,
        ]), $status);
    }

    /** @param array<string,string> $parametros */
    public function detalle(Request $peticion, array $parametros): Response
    {
        $reserva = $this->servicio->detalle((int) $parametros['id']); // NotFoundException -> 404 HTML

        return Response::html($this->vistas->render('reservas/detalle.html.twig', [
            'reserva' => $reserva,
            'eventos' => $reserva['eventos'],
        ]));
    }

    /**
     * Solo los campos conocidos y solo como string: lo que no encaja se descarta para repintar el formulario
     * @param array<string,mixed> $consulta
     * @return array<string,string>
     */
    private function filtrosComoTexto(array $consulta): array
    {
        $resultado = [];
        foreach (self::CAMPOS_FILTRO as $campo) {
            $valor = $consulta[$campo] ?? '';
            $resultado[$campo] = is_string($valor) ? $valor : '';
        }
        return $resultado;
    }

    /** @param array<string,string> $filtros */
    private function urlPagina(array $filtros, int $pagina): string
    {
        $parametros = array_filter($filtros, static fn (string $v): bool => $v !== '');
        if ($pagina > 1) {
            $parametros['page'] = (string) $pagina;
        }
        return $parametros === [] ? '/' : '/?' . http_build_query($parametros);
    }
}