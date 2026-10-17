<?php
declare(strict_types=1);

namespace App;

use App\Exception\HttpException;
use App\Http\Response;
use App\View\ViewRenderer;
use Throwable;

/**
 * Único punto donde una excepción se convierte en respuesta.
 *  - HttpException: el mensaje es público (lo escribo yo para el cliente).
 *  - Cualquier otra cosa: se registra en el log y el cliente recibe un mensaje genérico
 *    (nunca trazo, SQL ni rutas del servidor, salvo APP_DEBUG=true en local al igual que un framework como Simfony o Laravel)
 */
final class ErrorHandler
{
    public function __construct(
        private readonly bool $debug,
        private readonly ?ViewRenderer $vistas = null
    ) {
    }

    public function manejar(Throwable $e, string $ruta): Response
    {
        $http = $e instanceof HttpException ? $e : null;

        if ($http === null) {
            error_log(sprintf('[%s] %s en %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
        }

        $status = $http->status ?? 500;
        $codigo = $http->codigo ?? 'server_error';
        $mensaje = $http?->getMessage() ?? ($this->debug ? $e->getMessage() : 'Error interno del servidor.');
        $cabeceras = $http->cabeceras ?? [];

        $esApi = $ruta === '/api' || str_starts_with($ruta, '/api/');
        if (!$esApi && $this->vistas !== null) {
            try {
                return Response::html(
                    $this->vistas->render('error.html.twig', ['status' => $status, 'mensaje' => $mensaje]),
                    $status,
                    $cabeceras
                );
            } catch (Throwable) {
                return Response::texto("Error $status: $mensaje", $status);
            }
        }

        $cuerpo = ['error' => ['code' => $codigo, 'message' => $mensaje]];
        if ($http !== null && $http->errores !== []) {
            $cuerpo['error']['errors'] = $http->errores;
        }

        return Response::json($cuerpo, $status, $cabeceras);
    }
}