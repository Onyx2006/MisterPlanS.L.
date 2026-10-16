<?php
declare(strict_types=1);

namespace App\Validation;

use App\Domain\EstadoReserva;
use App\Domain\ReservaFiltro;
use App\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Toda entrada externa pasa por aquí. Principios:
 *  - lista blanca de campos: lo que no se nombra se ignora (el cliente no puede fijar `estado` ni `id`);
 *  - comprobación de tipo antes de valor (un array donde se espera un string es un error, no un fatal);
 *  - devuelve datos normalizados; el resto del sistema no vuelve a desconfiar de su forma.
 */
final class ReservaValidator
{
    private const MAX_NOCHES = 365;

    private DateTimeImmutable $hoy;

    public function __construct(?DateTimeImmutable $hoy = null)
    {
        $this->hoy = ($hoy ?? new DateTimeImmutable('today'))->setTime(0, 0);
    }

    /**
     * @param array<string,mixed> $entrada
     * @return array{nombre_huesped:string,email_huesped:string,nombre_alojamiento:string,fecha_entrada:string,fecha_salida:string,importe:string,notas:?string}
     */
    public function validarNueva(array $entrada): array
    {
        $err = [];

        $nombre = $this->texto($entrada, 'nombre_huesped', 2, 120, true, $err);
        $email = $this->email($entrada, $err);
        $alojamiento = $this->texto($entrada, 'nombre_alojamiento', 2, 150, true, $err);
        $entrada_ = $this->fecha($entrada, 'fecha_entrada', $err);
        $salida = $this->fecha($entrada, 'fecha_salida', $err);
        $importe = $this->importe($entrada, $err);
        $notas = $this->texto($entrada, 'notas', 0, 1000, false, $err);

        if ($entrada_ !== null && $entrada_ < $this->hoy) {
            $err['fecha_entrada'] = 'La fecha de entrada no puede estar en el pasado.';
        }
        if ($entrada_ !== null && $salida !== null) {
            if ($salida <= $entrada_) {
                $err['fecha_salida'] = 'La fecha de salida debe ser posterior a la de entrada.';
            } elseif ($entrada_->diff($salida)->days > self::MAX_NOCHES) {
                $err['fecha_salida'] = 'La estancia no puede superar ' . self::MAX_NOCHES . ' noches.';
            }
        }

        if ($err !== []) {
            throw new ValidationException($err);
        }

        return [
            'nombre_huesped'     => $nombre,
            'email_huesped'      => $email,
            'nombre_alojamiento' => $alojamiento,
            'fecha_entrada'      => $entrada_->format('Y-m-d'),
            'fecha_salida'       => $salida->format('Y-m-d'),
            'importe'            => $importe,
            'notas'              => $notas,
        ];
    }

    /** @param array<string,mixed> $entrada */
    public function validarCambioEstado(array $entrada): EstadoReserva
    {
        $valor = $entrada['estado'] ?? null;
        $estado = is_string($valor) ? EstadoReserva::tryFrom($valor) : null;

        // 'pendiente' es el estado inicial: nadie puede "volver" a él.
        if ($estado === null || $estado === EstadoReserva::Pendiente) {
            throw new ValidationException(['estado' => "Debe ser 'confirmada' o 'cancelada'."]);
        }

        return $estado;
    }

    /**
     * Filtros del listado (query string). Los errores aquí son un 400: la petición está mal formada
     * @param array<string,mixed> $consulta
     */
    public function validarFiltros(array $consulta): ReservaFiltro
    {
        $err = [];

        $estado = null;
        $bruto = $consulta['estado'] ?? '';
        if ($bruto !== '') {
            $estado = is_string($bruto) ? EstadoReserva::tryFrom($bruto) : null;
            if ($estado === null) {
                $err['estado'] = 'Estado no válido. Valores: ' . implode(', ', EstadoReserva::valores()) . '.';
            }
        }

        $desde = $this->fechaOpcional($consulta, 'desde', $err);
        $hasta = $this->fechaOpcional($consulta, 'hasta', $err);
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            $err['hasta'] = 'La fecha "hasta" no puede ser anterior a "desde".';
        }

        $huesped = $this->texto($consulta, 'huesped', 0, 120, false, $err);

        $pagina = $this->entero($consulta, 'page', 1, 1, 1_000_000, $err);
        $porPagina = $this->entero($consulta, 'per_page', 20, 1, 100, $err);

        if ($err !== []) {
            throw new ValidationException($err, 400);
        }

        return new ReservaFiltro($estado, $desde, $hasta, $huesped, $pagina, $porPagina);
    }

    /** Parseo estricto: rechaza 2026-02-30 (que DateTime "arreglaría" a marzo) y cualquier formato distinto. */
    public static function parsearFecha(string $valor): ?DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return ($fecha !== false && $fecha->format('Y-m-d') === $valor) ? $fecha : null;
    }

    /** @param array<string,string> $err */
    private function texto(array $in, string $campo, int $min, int $max, bool $obligatorio, array &$err): ?string
    {
        $valor = $in[$campo] ?? null;
        if ($valor === null || $valor === '') {
            if ($obligatorio) {
                $err[$campo] = 'Campo obligatorio.';
            }
            return null;
        }
        if (!is_string($valor)) {
            $err[$campo] = 'Debe ser un texto.';
            return null;
        }

        $valor = trim($valor);
        if ($valor === '') {
            if ($obligatorio) {
                $err[$campo] = 'Campo obligatorio.';
            }
            return null;
        }
        // UTF-8 válido y sin caracteres de control (se permiten salto de línea, retorno y tabulador).
        if (!preg_match('//u', $valor) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $valor)) {
            $err[$campo] = 'Contiene caracteres no permitidos.';
            return null;
        }

        $longitud = mb_strlen($valor);
        if ($longitud < $min) {
            $err[$campo] = "Mínimo $min caracteres.";
            return null;
        }
        if ($longitud > $max) {
            $err[$campo] = "Máximo $max caracteres.";
            return null;
        }

        return $valor;
    }

    /** @param array<string,string> $err */
    private function email(array $in, array &$err): ?string
    {
        $valor = $in['email_huesped'] ?? null;
        if (!is_string($valor) || trim($valor) === '') {
            $err['email_huesped'] = 'Campo obligatorio.';
            return null;
        }
        $valor = trim($valor);
        // FILTER_VALIDATE_EMAIL rechaza saltos de línea: de paso evita inyección de cabeceras si algún día se envían correos.
        if (strlen($valor) > 190 || filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            $err['email_huesped'] = 'Email no válido.';
            return null;
        }
        return $valor;
    }

    /** @param array<string,string> $err */
    private function fecha(array $in, string $campo, array &$err): ?DateTimeImmutable
    {
        $valor = $in[$campo] ?? null;
        if ($valor === null || $valor === '') {
            $err[$campo] = 'Campo obligatorio.';
            return null;
        }
        $fecha = is_string($valor) ? self::parsearFecha(trim($valor)) : null;
        if ($fecha === null) {
            $err[$campo] = 'Fecha no válida (formato AAAA-MM-DD).';
        }
        return $fecha;
    }

    /** @param array<string,string> $err */
    private function fechaOpcional(array $in, string $campo, array &$err): ?DateTimeImmutable
    {
        $valor = $in[$campo] ?? '';
        if ($valor === '') {
            return null;
        }
        $fecha = is_string($valor) ? self::parsearFecha($valor) : null;
        if ($fecha === null) {
            $err[$campo] = 'Fecha no válida (formato AAAA-MM-DD).';
        }
        return $fecha;
    }

    /**
     * El dinero viaja como string decimal ("120.50") y nunca como float: evita errores de redondeo
     * @param array<string,string> $err
     */
    private function importe(array $in, array &$err): ?string
    {
        $valor = $in['importe'] ?? null;
        if ($valor === null || $valor === '') {
            $err['importe'] = 'Campo obligatorio.';
            return null;
        }
        if (is_int($valor)) {
            $valor = (string) $valor;
        } elseif (is_float($valor)) {
            if (abs($valor * 100 - round($valor * 100)) > 1e-6) {
                $err['importe'] = 'Máximo 2 decimales.';
                return null;
            }
            $valor = number_format($valor, 2, '.', '');
        }

        if (!is_string($valor) || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $valor)) {
            $err['importe'] = 'Importe no válido (número positivo, hasta 8 enteros y 2 decimales).';
            return null;
        }
        if ((float) $valor <= 0) {
            $err['importe'] = 'El importe debe ser mayor que 0.';
            return null;
        }

        return number_format((float) $valor, 2, '.', '');
    }

    /** @param array<string,string> $err */
    private function entero(array $in, string $campo, int $porDefecto, int $min, int $max, array &$err): int
    {
        $valor = $in[$campo] ?? '';
        if ($valor === '') {
            return $porDefecto;
        }
        $entero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($entero === false) {
            $err[$campo] = "Debe ser un entero entre $min y $max.";
            return $porDefecto;
        }
        return $entero;
    }
}