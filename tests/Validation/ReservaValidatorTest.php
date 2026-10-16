<?php
declare(strict_types=1);

namespace Tests\Validation;

use App\Domain\EstadoReserva;
use App\Exception\ValidationException;
use App\Validation\ReservaValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReservaValidatorTest extends TestCase
{
    private ReservaValidator $validador;

    protected function setUp(): void
    {
        // Fecha fija: los tests no dependen del día en que se ejecuten.
        $this->validador = new ReservaValidator(new DateTimeImmutable('2026-10-16'));
    }

    /** @return array<string,mixed> */
    private function valida(array $cambios = []): array
    {
        return $cambios + [
            'nombre_huesped'     => '  Ana López ',
            'email_huesped'      => 'ana@example.com',
            'nombre_alojamiento' => 'Hotel Mar',
            'fecha_entrada'      => '2026-11-01',
            'fecha_salida'       => '2026-11-04',
            'importe'            => '120.5',
        ];
    }

    /** @return array<string,string> */
    private function erroresDe(array $entrada): array
    {
        try {
            $this->validador->validarNueva($entrada);
        } catch (ValidationException $e) {
            return $e->errores;
        }
        $this->fail('Se esperaba ValidationException');
    }

    public function testEntradaValidaSeNormaliza(): void
    {
        $datos = $this->validador->validarNueva($this->valida());

        $this->assertSame('Ana López', $datos['nombre_huesped']);   // trim
        $this->assertSame('120.50', $datos['importe']);              // string con 2 decimales
        $this->assertNull($datos['notas']);
    }

    public function testElClienteNoPuedeFijarEstadoNiId(): void
    {
        $datos = $this->validador->validarNueva($this->valida(['estado' => 'confirmada', 'id' => 99]));

        $this->assertArrayNotHasKey('estado', $datos);
        $this->assertArrayNotHasKey('id', $datos);
    }

    public function testCamposObligatorios(): void
    {
        $errores = $this->erroresDe([]);

        foreach (['nombre_huesped', 'email_huesped', 'nombre_alojamiento', 'fecha_entrada', 'fecha_salida', 'importe'] as $campo) {
            $this->assertArrayHasKey($campo, $errores);
        }
    }

    public function testEmailInvalidoYConSaltoDeLinea(): void
    {
        $this->assertArrayHasKey('email_huesped', $this->erroresDe($this->valida(['email_huesped' => 'no-es-email'])));
        $this->assertArrayHasKey('email_huesped', $this->erroresDe($this->valida(['email_huesped' => "a@b.es\nBcc: x@y.es"])));
    }

    public function testTiposInesperadosNoRompenNada(): void
    {
        $errores = $this->erroresDe($this->valida(['nombre_huesped' => ['a'], 'importe' => ['1'], 'fecha_entrada' => 20261101]));

        $this->assertArrayHasKey('nombre_huesped', $errores);
        $this->assertArrayHasKey('importe', $errores);
        $this->assertArrayHasKey('fecha_entrada', $errores);
    }

    public function testFechasIncoherentes(): void
    {
        $this->assertArrayHasKey('fecha_salida', $this->erroresDe($this->valida(['fecha_salida' => '2026-11-01'])));   // misma fecha
        $this->assertArrayHasKey('fecha_salida', $this->erroresDe($this->valida(['fecha_salida' => '2026-10-30'])));   // antes
        $this->assertArrayHasKey('fecha_entrada', $this->erroresDe($this->valida(['fecha_entrada' => '2026-10-01']))); // pasado
    }

    public function testFechaInexistenteSeRechazaEnVezDeCorregirse(): void
    {
        // DateTime "arreglaría" 30 de febrero a 2 de marzo; aquí es un error.
        $this->assertArrayHasKey('fecha_entrada', $this->erroresDe($this->valida(['fecha_entrada' => '2026-02-30'])));
        $this->assertArrayHasKey('fecha_entrada', $this->erroresDe($this->valida(['fecha_entrada' => '01/11/2026'])));
    }

    public function testImporte(): void
    {
        foreach (['0', '-5', '1e3', '12.345', 'abc', '1,50'] as $malo) {
            $this->assertArrayHasKey('importe', $this->erroresDe($this->valida(['importe' => $malo])), "importe $malo");
        }
        $this->assertSame('99.00', $this->validador->validarNueva($this->valida(['importe' => 99]))['importe']);
        $this->assertSame('10.25', $this->validador->validarNueva($this->valida(['importe' => 10.25]))['importe']);
    }

    public function testCambioDeEstadoSoloAdmiteConfirmarOCancelar(): void
    {
        $this->assertSame(EstadoReserva::Cancelada, $this->validador->validarCambioEstado(['estado' => 'cancelada']));

        foreach ([['estado' => 'pendiente'], ['estado' => 'borrada'], ['estado' => 1], []] as $malo) {
            try {
                $this->validador->validarCambioEstado($malo);
                $this->fail('Se esperaba ValidationException');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('estado', $e->errores);
            }
        }
    }

    public function testFiltrosValidosYPorDefecto(): void
    {
        $filtro = $this->validador->validarFiltros(['estado' => 'pendiente', 'desde' => '2026-10-01', 'huesped' => ' ana ']);

        $this->assertSame(EstadoReserva::Pendiente, $filtro->estado);
        $this->assertSame('ana', $filtro->huesped);
        $this->assertSame(1, $filtro->pagina);
        $this->assertSame(20, $filtro->porPagina);
    }

    public function testFiltrosInvalidosSonUn400(): void
    {
        $casos = [
            ['estado' => 'x'], ['desde' => '2026-13-01'], ['huesped' => ['a']],
            ['desde' => '2026-11-10', 'hasta' => '2026-11-01'], ['per_page' => '1000'], ['page' => '0'],
        ];
        foreach ($casos as $consulta) {
            try {
                $this->validador->validarFiltros($consulta);
                $this->fail('Se esperaba ValidationException para ' . json_encode($consulta));
            } catch (ValidationException $e) {
                $this->assertSame(400, $e->status);
            }
        }
    }
}