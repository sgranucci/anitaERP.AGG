<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Expresiones SQL portables MySQL/MariaDB ↔ PostgreSQL.
 *
 * Las columnas deben ser identificadores controlados por el código (nunca input de usuario).
 * Ver docs/arquitectura/portabilidad-base-datos.md (Fase 2).
 */
final class SqlDialectSupport
{
    public static function driver(?string $connection = null): string
    {
        return DB::connection($connection)->getDriverName();
    }

    public static function esPostgres(?string $connection = null): bool
    {
        return self::driver($connection) === 'pgsql';
    }

    /**
     * CAST a entero (códigos numéricos guardados como texto).
     * MySQL/MariaDB: UNSIGNED — PostgreSQL: INTEGER.
     */
    public static function castEntero(string $columna, ?string $connection = null): string
    {
        $tipo = self::esPostgres($connection) ? 'INTEGER' : 'UNSIGNED';

        return 'CAST('.$columna.' AS '.$tipo.')';
    }

    /** Expresión ORDER BY por código numérico ascendente. */
    public static function ordenCodigoAsc(string $columna, ?string $connection = null): string
    {
        return self::castEntero($columna, $connection).' ASC';
    }

    /**
     * Extracción de hora (0–23).
     * MySQL: HOUR(col) — PostgreSQL: EXTRACT(HOUR FROM col).
     */
    public static function hora(string $columna, ?string $connection = null): string
    {
        if (self::esPostgres($connection)) {
            return 'EXTRACT(HOUR FROM '.$columna.')';
        }

        return 'HOUR('.$columna.')';
    }

    /**
     * Año*100+mes (p. ej. filtros de período).
     * MySQL: YEAR(col)*100+MONTH(col) — PostgreSQL: EXTRACT.
     */
    public static function anioMes(string $columna, ?string $connection = null): string
    {
        if (self::esPostgres($connection)) {
            return '(EXTRACT(YEAR FROM '.$columna.')::int * 100 + EXTRACT(MONTH FROM '.$columna.')::int)';
        }

        return '(YEAR('.$columna.') * 100 + MONTH('.$columna.'))';
    }

    public static function lower(string $columna): string
    {
        return 'LOWER('.$columna.')';
    }
}
