<?php
declare(strict_types=1);

namespace Tests\Service;

use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Service\ReservaService;
use App\Validation\ReservaValidator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryEventoRepository;
use Tests\Support\InMemoryReservaRepository;
use Tests\Support\TransaccionDeMentira;

final class ReservaServiceTest extends TestCase
{
    private InMemoryReservaRepository $reservas;
    private InMemoryEventoRepository $eventos;
    private TransaccionDeMentira $tx;
    private ReservaService $servicio;

    protected function setUp(): void
    {
        $this->reservas = new InMemoryReservaRepository();
        $this->eventos = new InMemoryEventoRepository();
        $this->tx = new TransaccionDeMentira();
        $this->servicio = new ReservaService(
            $this->reservas,
            $this->eventos,
            $this->tx,
            new ReservaValidator(new DateTimeImmutable('2026-10-16'))
        );
    }

    /** @return array<string,mixed> */
    private function entrada(): array
    {
        return [
            'nombre_huesped' => 'Ana López', 'email_huesped' => 'ana@example.com', 'nombre_alojamiento' => 'Hotel Mar',
            'fecha_entrada' => '2026-11-01', 'fecha_salida' => '2026-11-04', 'importe' => '100',
        ];
    }

    public function testCrearGuardaPendienteYRegistraEventoEnUnaTransaccion(): void
    {
        $reserva = $this->servicio->crear($this->entrada());

        $this->assertSame('pendiente', $reserva['estado']);
        $this->assertCount(1, $reserva['eventos']);
        $this->assertSame('creada', $reserva['eventos'][0]['tipo']);
        $this->assertSame(1, $this->tx->ejecuciones);
    }

    public function testCrearConDatosInvalidosNoTocaLaPersistencia(): void
    {
        try {
            $this->servicio->crear(['nombre_huesped' => 'x']);
            $this->fail('Se esperaba ValidationException');
        } catch (ValidationException) {
            $this->assertCount(0, $this->reservas->filas);
            $this->assertCount(0, $this->eventos->eventos);
        }
    }

    public function testConfirmarYLuegoCancelarRegistraUnEventoPorCambio(): void
    {
        $id = $this->servicio->crear($this->entrada())['id'];

        $this->servicio->cambiarEstado($id, ['estado' => 'confirmada']);
        $final = $this->servicio->cambiarEstado($id, ['estado' => 'cancelada']);

        $this->assertSame('cancelada', $final['estado']);
        $this->assertSame(['creada', 'confirmada', 'cancelada'], array_column($final['eventos'], 'tipo'));
        $this->assertSame(2, $this->reservas->bloqueos, 'cada cambio de estado debe bloquear la fila');
    }

    public function testUnaReservaCanceladaNoSeReactiva(): void
    {
        $id = $this->servicio->crear($this->entrada())['id'];
        $this->servicio->cambiarEstado($id, ['estado' => 'cancelada']);
        $eventosAntes = count($this->eventos->eventos);

        $this->expectException(ConflictException::class);
        try {
            $this->servicio->cambiarEstado($id, ['estado' => 'confirmada']);
        } finally {
            $this->assertCount($eventosAntes, $this->eventos->eventos, 'una transición rechazada no deja rastro');
            $this->assertSame('cancelada', $this->reservas->filas[$id]['estado']);
        }
    }

    public function testNoSePuedeConfirmarDosVeces(): void
    {
        $id = $this->servicio->crear($this->entrada())['id'];
        $this->servicio->cambiarEstado($id, ['estado' => 'confirmada']);

        $this->expectException(ConflictException::class);
        $this->servicio->cambiarEstado($id, ['estado' => 'confirmada']);
    }

    public function testReservaInexistente(): void
    {
        $this->expectException(NotFoundException::class);
        $this->servicio->detalle(42);
    }

    public function testCambiarEstadoDeReservaInexistenteEs404(): void
    {
        $this->expectException(NotFoundException::class);
        $this->servicio->cambiarEstado(42, ['estado' => 'cancelada']);
    }

    public function testListarCalculaMeta(): void
    {
        $this->servicio->crear($this->entrada());

        $resultado = $this->servicio->listar(['per_page' => '10']);

        $this->assertSame(1, $resultado['meta']['total']);
        $this->assertSame(1, $resultado['meta']['total_pages']);
        $this->assertSame(10, $resultado['meta']['per_page']);
    }
}