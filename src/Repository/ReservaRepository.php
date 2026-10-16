<?php
declare(strict_types=1);

namespace App\Repository;

use App\Domain\ReservaFiltro;
use PDO;

/** Único sitio que conoce el SQL de `reserva`. Todo valor variable va como parámetro enlazado. */
final class ReservaRepository implements ReservaRepositoryInterface
{
    private const COLUMNAS = 'id, nombre_huesped, email_huesped, nombre_alojamiento, fecha_entrada, fecha_salida, estado, importe, notas, fecha_creacion';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function buscar(ReservaFiltro $filtro): array
    {
        [$where, $parametros] = $this->construirWhere($filtro);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reserva $where");
        $stmt->execute($parametros);
        $total = (int) $stmt->fetchColumn();

        // Los nombres de columna y el ORDER BY son constantes del código; nunca se interpolan datos del usuario.
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNAS . " FROM reserva $where ORDER BY fecha_entrada DESC, id DESC LIMIT :limite OFFSET :offset"
        );
        foreach ($parametros as $nombre => $valor) {
            $stmt->bindValue($nombre, $valor);
        }
        $stmt->bindValue(':limite', $filtro->porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $filtro->offset(), PDO::PARAM_INT);
        $stmt->execute();

        return [array_map($this->normalizar(...), $stmt->fetchAll()), $total];
    }

    public function buscarPorId(int $id, bool $bloquear = false): ?array
    {
        $sql = 'SELECT ' . self::COLUMNAS . ' FROM reserva WHERE id = :id' . ($bloquear ? ' FOR UPDATE' : '');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila === false ? null : $this->normalizar($fila);
    }

    public function insertar(array $datos): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reserva (nombre_huesped, email_huesped, nombre_alojamiento, fecha_entrada, fecha_salida, importe, notas)
             VALUES (:nombre, :email, :alojamiento, :entrada, :salida, :importe, :notas)'
        );
        $stmt->execute([
            ':nombre'      => $datos['nombre_huesped'],
            ':email'       => $datos['email_huesped'],
            ':alojamiento' => $datos['nombre_alojamiento'],
            ':entrada'     => $datos['fecha_entrada'],
            ':salida'      => $datos['fecha_salida'],
            ':importe'     => $datos['importe'],
            ':notas'       => $datos['notas'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function actualizarEstado(int $id, string $estado): void
    {
        $stmt = $this->pdo->prepare('UPDATE reserva SET estado = :estado WHERE id = :id');
        $stmt->execute([':estado' => $estado, ':id' => $id]);
    }

    /** @return array{0:string,1:array<string,string>} */
    private function construirWhere(ReservaFiltro $f): array
    {
        $condiciones = [];
        $parametros = [];

        if ($f->estado !== null) {
            $condiciones[] = 'estado = :estado';
            $parametros[':estado'] = $f->estado->value;
        }
        // Rango = solapamiento: reservas cuya estancia toca el intervalo [desde, hasta].
        if ($f->desde !== null) {
            $condiciones[] = 'fecha_salida >= :desde';
            $parametros[':desde'] = $f->desde->format('Y-m-d');
        }
        if ($f->hasta !== null) {
            $condiciones[] = 'fecha_entrada <= :hasta';
            $parametros[':hasta'] = $f->hasta->format('Y-m-d');
        }
        if ($f->huesped !== null) {
            // Se escapan % y _ para que el usuario no pueda inyectar comodines; '|' como carácter de escape
            // evita depender del modo NO_BACKSLASH_ESCAPES del servidor.
            $condiciones[] = "nombre_huesped LIKE :huesped ESCAPE '|'";
            $parametros[':huesped'] = '%' . str_replace(['|', '%', '_'], ['||', '|%', '|_'], $f->huesped) . '%';
        }

        $where = $condiciones === [] ? '' : 'WHERE ' . implode(' AND ', $condiciones);
        return [$where, $parametros];
    }

    /** @param array<string,mixed> $fila @return array<string,mixed> */
    private function normalizar(array $fila): array
    {
        $fila['id'] = (int) $fila['id'];
        return $fila; // importe se mantiene como string decimal ("120.50"): sin floats con dinero
    }
}