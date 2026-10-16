<?php
declare(strict_types=1);

namespace App\Http;

use App\Exception\HttpException;
use App\Exception\NotFoundException;

final class Router
{
    /** @var list<array{0:string,1:string,2:callable}> */
    private array $rutas = [];

    /**
     * El patrón admite {id}, que solo casa con dígitos: /api/reservas/abc ni siquiera llega al controlador.
     * @param callable(Request, array<string,string>): Response $manejador
     */
    public function agregar(string $metodo, string $patron, callable $manejador): void
    {
        $regex = '#^' . str_replace('{id}', '(?P<id>\d{1,18})', $patron) . '$#';
        $this->rutas[] = [$metodo, $regex, $manejador];
    }

    public function despachar(Request $peticion): Response
    {
        $permitidos = [];

        foreach ($this->rutas as [$metodo, $regex, $manejador]) {
            if (!preg_match($regex, $peticion->ruta, $coincidencias)) {
                continue;
            }
            if ($metodo !== $peticion->metodo) {
                $permitidos[] = $metodo;
                continue;
            }
            $parametros = array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);
            return $manejador($peticion, $parametros);
        }

        if ($permitidos !== []) {
            throw new HttpException(
                405,
                'Método no permitido.',
                [],
                'method_not_allowed',
                ['Allow' => implode(', ', array_unique($permitidos))]
            );
        }

        throw new NotFoundException('Ruta no encontrada.');
    }
}