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
    /**
     * MariaDB expone @@in_transaction. MySQL 8 no: preguntarla tira 1193 y,
     * si eso se lee como «transacción muerta», se aborta un asiento sano.
     */
    private static ?bool $motorExponeInTransaction = null;

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

    public static function esTransaccionServidorAbortada(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'modificó un saldo al mismo tiempo');
    }

    /**
     * Deadlock, lock wait, el 1020 de MariaDB, o la transacción que el servidor
     * ya abortó: se reintenta la operación completa, no el statement suelto.
     */
    public static function esReintentoDeContencion(Throwable $e): bool
    {
        return DbContencionSupport::esErrorReintentable($e)
            || self::esConflictoSnapshot($e)
            || self::esTransaccionServidorAbortada($e);
    }

    private static function servidorEnTransaccion(Connection $conn): bool
    {
        if (self::motorExponeVariableInTransaction($conn)) {
            try {
                $fila = $conn->selectOne('SELECT @@in_transaction AS abierta');

                return (int) ($fila->abierta ?? 0) === 1;
            } catch (Throwable $e) {
                if (self::esVariableDesconocida($e)) {
                    self::$motorExponeInTransaction = false;

                    return self::pdoEnTransaccion($conn);
                }

                return false;
            }
        }

        return self::pdoEnTransaccion($conn);
    }

    private static function motorExponeVariableInTransaction(Connection $conn): bool
    {
        if (self::$motorExponeInTransaction !== null) {
            return self::$motorExponeInTransaction;
        }

        if (! in_array($conn->getDriverName(), ['mysql', 'mariadb'], true)) {
            self::$motorExponeInTransaction = false;

            return false;
        }

        try {
            $fila = $conn->selectOne('SELECT VERSION() AS version');
            self::$motorExponeInTransaction = str_contains(
                strtolower((string) ($fila->version ?? '')),
                'mariadb'
            );
        } catch (Throwable) {
            self::$motorExponeInTransaction = false;
        }

        return self::$motorExponeInTransaction;
    }

    private static function pdoEnTransaccion(Connection $conn): bool
    {
        try {
            return $conn->getPdo()->inTransaction();
        } catch (Throwable) {
            return false;
        }
    }

    private static function esVariableDesconocida(Throwable $e): bool
    {
        $mensaje = strtolower($e->getMessage());

        return str_contains($mensaje, '1193')
            || str_contains($mensaje, 'unknown system variable');
    }
}
