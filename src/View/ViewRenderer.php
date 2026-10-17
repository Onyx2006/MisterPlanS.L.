<?php
declare(strict_types=1);

namespace App\View;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// Envoltorio mínimo de Twig: el resto de la app no depende de la librería concreta
final class ViewRenderer
{
    private readonly Environment $twig;

    public function __construct(string $directorioPlantillas, ?string $directorioCache, bool $debug)
    {
        if ($directorioCache !== null && !is_dir($directorioCache) && !@mkdir($directorioCache, 0775, true)) {
            $directorioCache = null;
        }
        if ($directorioCache !== null && !is_writable($directorioCache)) {
            $directorioCache = null;
        }

        $this->twig = new Environment(new FilesystemLoader($directorioPlantillas), [
            'autoescape'       => 'html',   // escapado por defecto en TODA salida {{ }}
            'strict_variables' => true,     // una variable mal escrita falla en vez de pintarse vacía
            'debug'            => $debug,
            'cache'            => $directorioCache ?? false,
        ]);
    }

    /** @param array<string,mixed> $contexto */
    public function render(string $plantilla, array $contexto = []): string
    {
        return $this->twig->render($plantilla, $contexto);
    }
}