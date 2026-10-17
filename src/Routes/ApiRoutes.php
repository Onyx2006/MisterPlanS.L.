<?php
declare(strict_types=1);

namespace App\Routes;

use App\Controller\ReservaApiController;
use App\Http\Router;

final class ApiRoutes
{
    public static function registrar(Router $router, ReservaApiController $c): void
    {
        $router->agregar('GET', '/api/reservas', $c->listar(...));
        $router->agregar('POST', '/api/reservas', $c->crear(...));
        $router->agregar('GET', '/api/reservas/{id}', $c->ver(...));
        $router->agregar('PATCH', '/api/reservas/{id}/estado', $c->cambiarEstado(...));
    }
}