<?php
declare(strict_types=1);

namespace App\Routes;

use App\Controller\ReservaViewController;
use App\Http\Router;

final class WebRoutes
{
    public static function registrar(Router $router, ReservaViewController $c): void
    {
        $router->agregar('GET', '/', $c->lista(...));
        $router->agregar('GET', '/reservas/{id}', $c->detalle(...));
    }
}