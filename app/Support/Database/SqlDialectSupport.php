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

    /**
     * CAST a texto (filtros LIKE / TRIM sobre numéricos o fechas).
     * MySQL: CHAR — PostgreSQL: TEXT.
     */
    public static function castTexto(string $columna, ?string $connection = null): string
    {
        $tipo = self::esPostgres($connection) ? 'TEXT' : 'CHAR';

        return 'CAST('.$columna.' AS '.$tipo.')';
    }

    /**
     * Texto o vacío: CAST + COALESCE(..., '').
     * Evita CONCAT(entero, '') que PostgreSQL rechaza (MySQL casteaba implícito).
     */
    public static function textoOVacio(string $columna, ?string $connection = null): string
    {
        return 'COALESCE('.self::castTexto($columna, $connection).", '')";
    }

    /**
     * Columna coincide con regex (usar con binding `?`).
     * MySQL: col REGEXP ? — PostgreSQL: col ~ ?
     * El patrón debe ser POSIX simple compatible (ej. ^[0-9]+$).
     */
    public static function coincideRegex(string $columna, ?string $connection = null): string
    {
        return self::esPostgres($connection)
            ? $columna.' ~ ?'
            : $columna.' REGEXP ?';
    }

    /**
     * Igualdad sensible a mayúsculas/minúsculas (y bytes en MySQL).
     * MySQL collations ci: BINARY a = BINARY b — PostgreSQL: a = b (ya case-sensitive).
     */
    public static function igualdadCaseSensitive(
        string $columnaIzq,
        string $columnaDer,
        ?string $connection = null
    ): string {
        if (self::esPostgres($connection)) {
            return $columnaIzq.' = '.$columnaDer;
        }

        return 'BINARY '.$columnaIzq.' = BINARY '.$columnaDer;
    }

    /** Expresión ORDER BY por código numérico ascendente. */
    public static function ordenCodigoAsc(string $columna, ?string $connection = null): string
    {
        return self::castEntero($columna, $connection).' ASC';
    }

    /** Expresión ORDER BY por código numérico descendente. */
    public static function ordenCodigoDesc(string $columna, ?string $connection = null): string
    {
        return self::castEntero($columna, $connection).' DESC';
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

    /**
     * Solo fecha (sin hora).
     * MySQL: DATE(col) — PostgreSQL: (col)::date
     */
    public static function fecha(string $columna, ?string $connection = null): string
    {
        if (self::esPostgres($connection)) {
            return '('.$columna.')::date';
        }

        return 'DATE('.$columna.')';
    }

    /**
     * Año calendario.
     * MySQL: YEAR(col) — PostgreSQL: EXTRACT(YEAR FROM col)
     */
    public static function anio(string $columna, ?string $connection = null): string
    {
        if (self::esPostgres($connection)) {
            return 'EXTRACT(YEAR FROM '.$columna.')';
        }

        return 'YEAR('.$columna.')';
    }

    /**
     * COALESCE portable (reemplazo de IFNULL).
     * Argumentos: expresiones SQL controladas por código.
     */
    public static function coalesce(string ...$expresiones): string
    {
        if ($expresiones === []) {
            throw new \InvalidArgumentException('SqlDialectSupport::coalesce requiere al menos una expresión');
        }

        return 'COALESCE('.implode(', ', $expresiones).')';
    }

    /**
     * Orden por lista de valores (reemplazo portable de FIELD()).
     * Genera CASE WHEN col = 'a' THEN 1 … END (válido en MySQL y PostgreSQL).
     *
     * @param  list<string|int|float>  $valores
     */
    public static function ordenPorLista(string $columna, array $valores): string
    {
        if ($valores === []) {
            throw new \InvalidArgumentException('SqlDialectSupport::ordenPorLista requiere valores');
        }

        $parts = [];
        foreach (array_values($valores) as $i => $valor) {
            if (is_int($valor) || is_float($valor)) {
                $lit = (string) $valor;
            } else {
                $lit = "'".str_replace("'", "''", (string) $valor)."'";
            }
            $parts[] = 'WHEN '.$columna.' = '.$lit.' THEN '.($i + 1);
        }

        return '(CASE '.implode(' ', $parts).' ELSE '.(count($valores) + 1).' END)';
    }

    /**
     * Período mensual ordenable: YYYY-MM.
     * MySQL: DATE_FORMAT — PostgreSQL: to_char
     */
    public static function periodoAnioMes(string $columna, ?string $connection = null): string
    {
        if (self::esPostgres($connection)) {
            return 'to_char('.$columna.", 'YYYY-MM')";
        }

        return 'DATE_FORMAT('.$columna.", '%Y-%m')";
    }

    /**
     * Período semanal ISO ordenable: YYYY-Www (ej. 2026-W32).
     * MySQL: YEAR + WEEK modo 3 — PostgreSQL: to_char IYYY/IW
     */
    public static function periodoAnioSemanaIso(string $columna, ?string $connection = null): string
    {
        if (self::esPostgres($connection)) {
            return 'to_char('.$columna.", 'IYYY-\"W\"IW')";
        }

        return "CONCAT(YEAR({$columna}), '-W', LPAD(WEEK({$columna}, 3), 2, '0'))";
    }

    /**
     * Posición 1-based de un literal en una expresión (LOCATE / POSITION).
     * El literal no debe venir de input de usuario.
     */
    public static function posicion(string $haystackExpr, string $literal, ?string $connection = null): string
    {
        $lit = str_replace("'", "''", $literal);
        if (self::esPostgres($connection)) {
            return "POSITION('{$lit}' IN {$haystackExpr})";
        }

        return "LOCATE('{$lit}', {$haystackExpr})";
    }

    /**
     * N-ésima parte (1-based) al dividir por delimitador.
     * MySQL: SUBSTRING_INDEX anidado — PostgreSQL: split_part
     */
    public static function parteDelimitada(
        string $expr,
        string $delimitador,
        int $indice,
        ?string $connection = null
    ): string {
        if ($indice < 1) {
            throw new \InvalidArgumentException('SqlDialectSupport::parteDelimitada índice debe ser >= 1');
        }
        $delim = str_replace("'", "''", $delimitador);
        if (self::esPostgres($connection)) {
            return "split_part({$expr}, '{$delim}', {$indice})";
        }

        return "SUBSTRING_INDEX(SUBSTRING_INDEX({$expr}, '{$delim}', {$indice}), '{$delim}', -1)";
    }

    /**
     * Parsea texto a fecha.
     * $formatoApp: 'Y-m-d' | 'd/m/Y'
     */
    public static function aFecha(string $expr, string $formatoApp, ?string $connection = null): string
    {
        $mysql = ['Y-m-d' => '%Y-%m-%d', 'd/m/Y' => '%d/%m/%Y'];
        $pg = ['Y-m-d' => 'YYYY-MM-DD', 'd/m/Y' => 'DD/MM/YYYY'];
        if (! isset($mysql[$formatoApp])) {
            throw new \InvalidArgumentException('SqlDialectSupport::aFecha formato no soportado: '.$formatoApp);
        }

        if (self::esPostgres($connection)) {
            return 'to_date(('.$expr."), '".$pg[$formatoApp]."')";
        }

        return 'STR_TO_DATE(('.$expr."), '".$mysql[$formatoApp]."')";
    }

    /**
     * Texto de fecha embebido tras un literal (ej. "…jornada 2026-08-10…").
     * Longitud típica 10 para Y-m-d.
     */
    public static function textoTrasLiteral(
        string $haystackExpr,
        string $literal,
        int $offsetTrasLiteral,
        int $longitud,
        ?string $connection = null
    ): string {
        $pos = self::posicion($haystackExpr, $literal, $connection);

        return 'SUBSTRING('.$haystackExpr.', '.$pos.' + '.$offsetTrasLiteral.', '.$longitud.')';
    }

    /**
     * Condición: la fecha cae en sábado.
     * MySQL: DAYOFWEEK = 7 — PostgreSQL: DOW = 6 — SQLite: strftime %w = 6.
     */
    public static function esSabado(string $columna, ?string $connection = null): string
    {
        $driver = self::driver($connection);

        return match ($driver) {
            'pgsql' => 'EXTRACT(DOW FROM '.$columna.') = 6',
            'sqlite' => "strftime('%w', ".$columna.") = '6'",
            default => 'DAYOFWEEK('.$columna.') = 7',
        };
    }

    /**
     * Condición WHERE portable: saldo de CC proveedor aún no cancelado del todo.
     * Evita HAVING sobre alias de subquery (MySQL lo permite sin GROUP BY; PG no).
     */
    public static function sqlSaldoPendienteProveedorCc(): string
    {
        return 'ABS(COALESCE((SELECT SUM(total) FROM proveedor_cuentacorriente_aplicacion'
            .' WHERE proveedor_cuentacorriente_id = proveedor_cuentacorriente.id), 0))'
            .' < ABS(proveedor_cuentacorriente.total)';
    }

    /**
     * Condición WHERE portable: saldo de CC cliente aún no cancelado del todo.
     */
    public static function sqlSaldoPendienteClienteCc(): string
    {
        return 'ABS(COALESCE((SELECT SUM(total) FROM cliente_cuentacorriente_aplicacion'
            .' WHERE cliente_cuentacorriente_id = cliente_cuentacorriente.id), 0))'
            .' < ABS(cliente_cuentacorriente.total)';
    }
}
