<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Helpers para migraciones históricas con SQL MySQL-only.
 * Usar al tocar una migración vieja para que también corra en PostgreSQL.
 */
final class MigrationDialectSupport
{
    public static function driver(?string $connection = null): string
    {
        return Schema::connection($connection)->getConnection()->getDriverName();
    }

    public static function esPostgres(?string $connection = null): bool
    {
        return self::driver($connection) === 'pgsql';
    }

    public static function esMysql(?string $connection = null): bool
    {
        return in_array(self::driver($connection), ['mysql', 'mariadb'], true);
    }

    /**
     * Ejecuta el statement MySQL o el de PostgreSQL según el driver.
     */
    public static function statementPorDriver(string $mysql, string $pgsql, ?string $connection = null): void
    {
        DB::connection($connection)->statement(
            self::esPostgres($connection) ? $pgsql : $mysql
        );
    }

    /**
     * Ajusta el siguiente valor de secuencia / AUTO_INCREMENT.
     */
    public static function reiniciarAutoincrement(string $tabla, string $columna, int $siguiente, ?string $connection = null): void
    {
        if ($siguiente < 1) {
            $siguiente = 1;
        }

        if (self::esPostgres($connection)) {
            DB::connection($connection)->statement(
                'SELECT setval(pg_get_serial_sequence(?, ?), ?, false)',
                [$tabla, $columna, $siguiente]
            );

            return;
        }

        if (self::esMysql($connection)) {
            DB::connection($connection)->statement(
                'ALTER TABLE '.$tabla.' AUTO_INCREMENT = '.$siguiente
            );
        }
    }

    /**
     * Renombra una columna (MySQL CHANGE / PostgreSQL RENAME COLUMN).
     */
    public static function renombrarColumna(
        string $tabla,
        string $desde,
        string $hacia,
        string $definicionMysql,
        ?string $connection = null
    ): void {
        if (self::esPostgres($connection)) {
            DB::connection($connection)->statement(
                'ALTER TABLE '.$tabla.' RENAME COLUMN '.$desde.' TO '.$hacia
            );

            return;
        }

        DB::connection($connection)->statement(
            'ALTER TABLE '.$tabla.' CHANGE '.$desde.' '.$hacia.' '.$definicionMysql
        );
    }

    public static function tieneIndice(string $tabla, string $indice, ?string $connection = null): bool
    {
        return Schema::connection($connection)->hasIndex($tabla, $indice);
    }

    public static function tieneForeignKey(string $tabla, string $nombreFk, ?string $connection = null): bool
    {
        foreach (Schema::connection($connection)->getForeignKeys($tabla) as $fk) {
            if (($fk['name'] ?? '') === $nombreFk) {
                return true;
            }
        }

        return false;
    }

    /**
     * CAST a entero portable (MySQL UNSIGNED / PostgreSQL INTEGER).
     */
    public static function castEntero(string $expresion, ?string $connection = null): string
    {
        return self::esPostgres($connection)
            ? 'CAST('.$expresion.' AS INTEGER)'
            : 'CAST('.$expresion.' AS UNSIGNED)';
    }

    /**
     * Elimina un índice o constraint UNIQUE (en PG el unique es CONSTRAINT).
     */
    public static function dropIndiceOUnique(string $tabla, string $nombre, ?string $connection = null): void
    {
        if (! self::tieneIndice($tabla, $nombre, $connection)) {
            return;
        }

        if (self::esPostgres($connection)) {
            DB::connection($connection)->statement(
                'ALTER TABLE '.$tabla.' DROP CONSTRAINT IF EXISTS '.$nombre
            );

            // Por si era índice no-unique
            DB::connection($connection)->statement(
                'DROP INDEX IF EXISTS '.$nombre
            );

            return;
        }

        DB::connection($connection)->statement(
            'ALTER TABLE `'.$tabla.'` DROP INDEX `'.$nombre.'`'
        );
    }
}
