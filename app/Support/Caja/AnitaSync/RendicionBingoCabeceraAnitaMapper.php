<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

/**
 * Mapeo ERP → columnas Informix rendbingo.
 */
final class RendicionBingoCabeceraAnitaMapper
{
    public static function nroOperDesdeCodigo(?string $codigo): int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '' || ! preg_match('/^(\d+)$/', $codigo, $m)) {
            return 0;
        }

        return (int) $m[1];
    }

    public static function decimal(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return number_format(0, 4, '.', '');
        }

        return number_format((float) $valor, 4, '.', '');
    }

    public static function entero(mixed $valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        return (int) $valor;
    }

    public static function texto(mixed $valor, int $maxLen): string
    {
        $s = str_replace("'", "''", (string) $valor);

        return substr($s, 0, $maxLen);
    }

    public static function camposInsert(): string
    {
        return implode(', ', array_map(
            fn (array $col) => $col['columna'],
            self::columnasInsert(),
        ));
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresInsert(array $ctx): string
    {
        $valores = [];
        foreach (self::columnasInsert() as $col) {
            $valores[] = self::valorSql($col, $ctx);
        }

        return implode(', ', $valores);
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresUpdate(array $ctx): string
    {
        $partes = [];
        foreach (self::columnasUpdate() as $col) {
            $partes[] = $col['columna'].' = '.self::valorSql($col, $ctx);
        }

        return implode(', ', $partes);
    }

    public static function whereClave(int $nroOper, string $tipoOper): string
    {
        $tipoOper = self::texto(substr($tipoOper, 0, 1), 1);

        return " WHERE rendb_nro_oper = {$nroOper} AND rendb_tipo_oper = '{$tipoOper}'";
    }

    /**
     * @return list<array{columna:string, tipo:string, ctx?:string, fijo?:mixed}>
     */
    private static function columnasInsert(): array
    {
        return self::columnasMapeadas();
    }

    /**
     * @return list<array{columna:string, tipo:string, ctx?:string, fijo?:mixed}>
     */
    private static function columnasUpdate(): array
    {
        return array_values(array_filter(
            self::columnasMapeadas(),
            fn (array $col) => ($col['columna'] ?? '') !== 'rendb_nro_oper'
                && ($col['columna'] ?? '') !== 'rendb_tipo_oper',
        ));
    }

    /**
     * @return list<array{columna:string, tipo:string, ctx?:string, fijo?:mixed}>
     */
    private static function columnasMapeadas(): array
    {
        return [
            ['columna' => 'rendb_nro_oper', 'tipo' => 'entero', 'ctx' => 'nro_oper'],
            ['columna' => 'rendb_tipo_oper', 'tipo' => 'texto', 'ctx' => 'tipo_oper', 'max' => 1],
            ['columna' => 'rendb_caja', 'tipo' => 'entero', 'ctx' => 'caja_id'],
            ['columna' => 'rendb_cajero', 'tipo' => 'entero', 'ctx' => 'usuario_id'],
            ['columna' => 'rendb_fecha', 'tipo' => 'entero', 'ctx' => 'fecha_entera'],
            ['columna' => 'rendb_hora', 'tipo' => 'texto', 'ctx' => 'hora', 'max' => 8],
            ['columna' => 'rendb_usuario', 'tipo' => 'entero', 'ctx' => 'usuario_habilitado_id'],
            ['columna' => 'rendb_sobrante', 'tipo' => 'decimal', 'ctx' => 'sobrante_faltante'],
            ['columna' => 'rendb_vales', 'tipo' => 'decimal', 'ctx' => 'vales'],
            ['columna' => 'rendb_redondeo', 'tipo' => 'decimal', 'ctx' => 'redondeo'],
            ['columna' => 'rendb_deposito', 'tipo' => 'decimal', 'ctx' => 'deposito'],
            ['columna' => 'rendb_cant_carton', 'tipo' => 'entero', 'ctx' => 'cant_cartones'],
            ['columna' => 'rendb_total_carton', 'tipo' => 'decimal', 'ctx' => 'total_cartones'],
            ['columna' => 'rendb_observacion', 'tipo' => 'texto', 'ctx' => 'observacion', 'max' => 200],
            ['columna' => 'rendb_empresa', 'tipo' => 'entero', 'ctx' => 'empresa_anita'],
            ['columna' => 'rendb_tipo_fac', 'tipo' => 'texto', 'fijo' => '', 'max' => 3],
            ['columna' => 'rendb_letra_fac', 'tipo' => 'texto', 'fijo' => '', 'max' => 1],
            ['columna' => 'rendb_sucursal_fac', 'tipo' => 'entero', 'fijo' => 0],
            ['columna' => 'rendb_nro_fac', 'tipo' => 'entero', 'fijo' => 0],
            ['columna' => 'rendb_fecha_fac', 'tipo' => 'entero', 'fijo' => 0],
            ['columna' => 'rendb_estado', 'tipo' => 'texto', 'ctx' => 'estado', 'max' => 1],
            ['columna' => 'rendb_refuer_prest', 'tipo' => 'decimal', 'fijo' => 0],
            ['columna' => 'rendb_fecha_alfa', 'tipo' => 'texto', 'ctx' => 'fecha_alfa', 'max' => 8],
            ['columna' => 'rendb_turno', 'tipo' => 'texto', 'ctx' => 'turno_letra', 'max' => 1],
            ['columna' => 'rendb_fecha_carga', 'tipo' => 'entero', 'ctx' => 'fecha_carga'],
            ['columna' => 'rendb_hora_carga', 'tipo' => 'texto', 'ctx' => 'hora_carga', 'max' => 8],
        ];
    }

    /**
     * @param  array{columna:string, tipo:string, ctx?:string, fijo?:mixed, max?:int}  $col
     * @param  array<string, mixed>  $ctx
     */
    private static function valorSql(array $col, array $ctx): string
    {
        if (array_key_exists('fijo', $col)) {
            $fijo = $col['fijo'];
            if (($col['tipo'] ?? '') === 'texto') {
                return "'".self::texto($fijo, (int) ($col['max'] ?? 255))."'";
            }
            if (($col['tipo'] ?? '') === 'decimal') {
                return self::decimal($fijo);
            }

            return (string) self::entero($fijo);
        }

        $key = (string) ($col['ctx'] ?? '');
        $valor = $ctx[$key] ?? null;

        return match ($col['tipo'] ?? 'texto') {
            'entero' => (string) self::entero($valor),
            'decimal' => self::decimal($valor),
            default => "'".self::texto($valor, (int) ($col['max'] ?? 255))."'",
        };
    }
}
