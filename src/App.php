<?php
declare(strict_types=1);

namespace App;

use App\Controller\ReservaApiController;
use App\Controller\ReservaViewController;
use App\Http\CabecerasSeguridad;
use App\Http\ProteccionOrigen;
use App\Http\Request;
use App\Http\Router;
use App\Repository\PdoTransaccion;
use App\Repository\ReservaEventoRepository;
use App\Repository\ReservaRepository;
use App\Routes\ApiRoutes;
use App\Routes\WebRoutes;
use App\Service\ReservaService;
use App\Validation\ReservaValidator;
use App\View\ViewRenderer;
use ErrorException;
use Throwable;

// Raíz de composición
final class App
{
    public function __construct(private readonly string $raiz)
    {
    }

    public function ejecutar(): void
    {
        $config = Config::cargar($this->raiz);
        $debug = $config->debug();

        date_default_timezone_set('Europe/Madrid');
        ini_set('display_errors', $debug ? '1' : '0');
        // Avisos y notices de PHP pasan a ser excepciones: acaban en el manejador central, no en la salida.
        set_error_handler(static function (int $nivel, string $mensaje, string $fichero, int $linea): bool {
            if (!(error_reporting() & $nivel)) {
                return false;
            }
            throw new ErrorException($mensaje, 0, $nivel, $fichero, $linea);
        });

        CabecerasSeguridad::enviar();

        $vistas = new ViewRenderer($this->raiz . '/templates', $debug ? null : $this->raiz . '/var/cache/twig', $debug); // vistas
        $manejador = new ErrorHandler($debug, $vistas);

        try {
            $peticion = Request::desdeGlobales();
            ProteccionOrigen::verificar($peticion);

            $pdo = Database::conectar($config);
            $servicio = new ReservaService(
                new ReservaRepository($pdo),
                new ReservaEventoRepository($pdo),
                new PdoTransaccion($pdo),
                new ReservaValidator()
            );

            $router = new Router();
            ApiRoutes::registrar($router, new ReservaApiController($servicio));
            WebRoutes::registrar($router, new ReservaViewController($servicio, $vistas)); // vistas

            $router->despachar($peticion)->enviar();
        } catch (Throwable $e) {
            $ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $manejador->manejar($e, $ruta)->enviar();
        }
    }
}