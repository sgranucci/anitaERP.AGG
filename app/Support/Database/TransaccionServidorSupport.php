<?php

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * La transacción que Laravel cree abierta tiene que seguir viva en el motor.
 *
 * MariaDB con innodb_snapshot_isolation, ante el error 1020, aborta la
 * transacción entera del servidor. Laravel puede seguir con el contador en 1
 * y el próximo INSERT se autocompromea. Así el asiento desaparece y la cuota
 * o el comprobante quedan grabados igual.
 */
final class TransaccionServidorSupport
{
    public static function assertSigueAbierta(): void
    {
        $conn = DB::connection();
        if ($conn->transactionLevel() <= 0) {
            return;
        }

        if (self::servidorEnTransaccion($conn)) {
            return;
        }

        throw new RuntimeException(
            'La operación contable se cortó porque otro proceso modificó un saldo al mismo tiempo. '
            .'No se grabó el asiento ni la cuenta corriente. Vuelva a contabilizar.'
        );
    }

    public static function esConflictoSnapshot(Throwable $e): bool
    {
        $mensaje = strtolower($e->getMessage());

        return str_contains($mensaje, '1020')
            || str_contains($mensaje, 'record has changed since last read');
    }

    /**
     * Deadlock, lock wait o el 1020 de MariaDB: se puede reintentar la operación
     * completa, no el statement de una transacción que el servidor ya abortó.
     */
    public static function esReintentoDeContencion(Throwable $e): bool
    {
        return DbContencionSupport::esErrorReintentable($e) || self::esConflictoSnapshot($e);
    }

    private static function servidorEnTransaccion(Connection $conn): bool
    {
        if (in_array($conn->getDriverName(), ['mysql', 'mariadb'], true)) {
            try {
                $fila = $conn->selectOne('SELECT @@in_transaction AS abierta');

                return (int) ($fila->abierta ?? 0) === 1;
            } catch (Throwable) {
                return false;
            }
        }

        try {
            return $conn->getPdo()->inTransaction();
        } catch (Throwable) {
            return false;
        }
    }
}
