<?php
declare(strict_types=1);

namespace App;

/**
 * Configuración desde variables de entorno o, en su defecto, desde un fichero .env.
 * Las variables de entorno reales tienen prioridad (así producción no necesita fichero).
 */
final class Config
{
    /** @param array<string,string> $valores */
    private function __construct(private readonly array $valores)
    {
    }

    public static function cargar(string $raiz): self
    {
        $valores = [];
        $fichero = $raiz . '/.env';
        if (is_file($fichero)) {
            $leidos = parse_ini_file($fichero, false, INI_SCANNER_RAW);
            if ($leidos !== false) {
                $valores = array_map('strval', $leidos);
            }
        }
        return new self($valores);
    }

    public function get(string $clave, ?string $porDefecto = null): ?string
    {
        $entorno = getenv($clave);
        return $entorno !== false ? $entorno : ($this->valores[$clave] ?? $porDefecto);
    }

    public function debug(): bool
    {
        return filter_var($this->get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL);
    }
}