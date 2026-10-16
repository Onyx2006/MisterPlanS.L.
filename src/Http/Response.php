<?php
declare(strict_types=1);

namespace App\Http;

final class Response
{
    /** @param array<string,string> $cabeceras */
    private function __construct(
        public readonly int $status,
        public readonly string $cuerpo,
        public readonly array $cabeceras
    ) {
    }

    /** @param array<string,string> $cabeceras */
    public static function json(mixed $datos, int $status = 200, array $cabeceras = []): self
    {
        $cuerpo = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return new self($status, $cuerpo, $cabeceras + [
            'Content-Type'  => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @param array<string,string> $cabeceras */
    public static function html(string $html, int $status = 200, array $cabeceras = []): self
    {
        return new self($status, $html, $cabeceras + ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function texto(string $texto, int $status): self
    {
        return new self($status, $texto, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function enviar(): void
    {
        http_response_code($this->status);
        foreach ($this->cabeceras as $nombre => $valor) {
            header($nombre . ': ' . $valor);
        }
        echo $this->cuerpo;
    }
}