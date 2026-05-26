<?php

namespace App\Services\Ventas\Gastronomia;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Consulta tickets de canje de premios en Wigos (spVoucherGiftData).
 * Basado en track_wigos.php.
 */
final class WigosCanjePremioService
{
    /**
     * @return list<object{
     *   GIFT_ID:string,
     *   GIFT_NAME:string,
     *   SPENT_POINTS:mixed,
     *   QUANTITY:mixed,
     *   REQUESTED:mixed,
     *   ACCOUNT:mixed,
     *   CUSTOMER:string,
     *   DOCUMENT_NUMBER:string,
     *   STATUS:string
     * }>
     */
    public function consultarPorCodigoBarras(string $numerocupon): array
    {
        if (! config('wigos.habilitado', false)) {
            throw new RuntimeException(
                'Integración Wigos deshabilitada. Configure WIGOS_HABILITADO=true y credenciales SQL Server.'
            );
        }

        $codigo = trim($numerocupon);
        if ($codigo === '') {
            throw new InvalidArgumentException('Debe indicar el número de cupón.');
        }

        $primario = strtoupper(trim((string) config('wigos.curr_wigos', 'A')));
        if (! in_array($primario, ['A', 'B'], true)) {
            $primario = 'A';
        }
        $secundario = $primario === 'A' ? 'B' : 'A';

        $filas = $this->ejecutarSp($primario, $codigo);
        if ($filas === [] && $this->conexionConfigurada($secundario)) {
            $filas = $this->ejecutarSp($secundario, $codigo);
        }

        if ($filas === []) {
            throw new InvalidArgumentException(
                'No se encontró el ticket de canje '.$codigo.' en Wigos o no está pendiente.'
            );
        }

        $pendientes = array_values(array_filter(
            $filas,
            fn ($fila) => strtoupper(trim((string) ($fila->STATUS ?? ''))) === 'PENDING'
        ));

        if ($pendientes === []) {
            $estado = strtoupper(trim((string) ($filas[0]->STATUS ?? '')));
            throw new InvalidArgumentException(
                'El ticket no está disponible para canje en Wigos'
                .($estado !== '' ? ' (estado: '.$estado.')' : '.')
            );
        }

        return $pendientes;
    }

    /**
     * @return list<object>
     */
    private function ejecutarSp(string $alias, string $codigo): array
    {
        if (! $this->conexionConfigurada($alias)) {
            return [];
        }

        $connectionName = 'wigos_'.$alias;
        $this->registrarConexion($alias, $connectionName);

        try {
            $pdo = DB::connection($connectionName)->getPdo();
            $stmt = $pdo->prepare('EXEC spVoucherGiftData @pBarcode = ?');
            $stmt->execute([$codigo]);

            $filas = [];
            do {
                while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
                    $filas[] = $row;
                }
            } while ($stmt->nextRowset());

            return $filas;
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Error al consultar Wigos ('.$alias.'): '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    private function conexionConfigurada(string $alias): bool
    {
        $cfg = (array) config('wigos.connections.'.$alias, []);
        $host = trim((string) ($cfg['host'] ?? ''));

        return $host !== '';
    }

    private function registrarConexion(string $alias, string $connectionName): void
    {
        $cfg = (array) config('wigos.connections.'.$alias, []);
        Config::set('database.connections.'.$connectionName, [
            'driver' => 'sqlsrv',
            'host' => $cfg['host'] ?? '',
            'port' => $cfg['port'] ?? '1433',
            'database' => $cfg['database'] ?? 'wgdb_000',
            'username' => $cfg['username'] ?? '',
            'password' => $cfg['password'] ?? '',
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ]);
        DB::purge($connectionName);
    }
}
