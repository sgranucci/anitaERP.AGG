<?php

namespace App\Support\Caja\AnitaSync;

/**
 * Mapeo contexto → columnas Informix rendgastro.
 *
 * INSERT: todos los numéricos sin mapeo ERP → 0 (evitar NULL en alta).
 * UPDATE: solo columnas mapeadas (ctx) + las listadas en config (opt-in).
 * Informix no toca columnas ausentes del SET; en update no se pisan campos
 * que Anita gestiona por fuera del bridge salvo que estén en config.
 */
final class RendicionGastronomiaCabeceraAnitaMapper
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
        $nombres = array_map(
            fn (array $col) => $col['columna'],
            self::columnasInsert(),
        );

        return implode(', ', $nombres);
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

        return implode(",\n            ", $partes);
    }

    public static function whereClave(int $nroOper, string $tipoOper): string
    {
        return " WHERE rendg_nro_oper = '".$nroOper."' AND rendg_tipo_oper = '"
            .self::texto($tipoOper, 1)."' ";
    }

    /**
     * @return list<array{
     *   columna: string,
     *   clave?: bool,
     *   tipo: 'entero'|'decimal'|'texto',
     *   ctx?: string,
     *   fijo_entero?: int,
     *   fijo_decimal?: float,
     *   fijo_texto?: string,
     *   max_len?: int
     * }>
     */
    private static function columnasInsert(): array
    {
        $columnas = [
            ['columna' => 'rendg_nro_oper', 'clave' => true, 'tipo' => 'entero', 'ctx' => 'nro_oper'],
            ['columna' => 'rendg_tipo_oper', 'clave' => true, 'tipo' => 'texto', 'ctx' => 'tipo_oper', 'max_len' => 1],
            ['columna' => 'rendg_caja', 'tipo' => 'entero', 'ctx' => 'caja_id'],
            ['columna' => 'rendg_cajero', 'tipo' => 'entero', 'ctx' => 'usuario_id'],
            ['columna' => 'rendg_fecha', 'tipo' => 'entero', 'ctx' => 'fecha_entera'],
            ['columna' => 'rendg_hora', 'tipo' => 'texto', 'ctx' => 'hora', 'max_len' => 8],
            ['columna' => 'rendg_usuario', 'tipo' => 'entero', 'ctx' => 'usuario_id'],
            ['columna' => 'rendg_total_x', 'tipo' => 'decimal', 'ctx' => 'total_x'],
            ['columna' => 'rendg_invitacion', 'tipo' => 'decimal', 'ctx' => 'invitacion'],
            ['columna' => 'rendg_total_z', 'tipo' => 'decimal', 'ctx' => 'total_z'],
            ['columna' => 'rendg_ult_ticket', 'tipo' => 'entero', 'ctx' => 'ultimo_ticket'],
            ['columna' => 'rendg_nro_z', 'tipo' => 'entero', 'ctx' => 'nro_z'],
            ['columna' => 'rendg_tot_nc', 'tipo' => 'decimal', 'ctx' => 'tot_nc'],
            ['columna' => 'rendg_tot_redondeo', 'tipo' => 'decimal', 'ctx' => 'tot_redondeo'],
            ['columna' => 'rendg_dif_caja', 'tipo' => 'decimal', 'ctx' => 'dif_caja'],
            ['columna' => 'rendg_ab_pago', 'tipo' => 'decimal', 'fijo_decimal' => 0],
            ['columna' => 'rendg_iva_fac', 'tipo' => 'decimal', 'fijo_decimal' => 0],
            ['columna' => 'rendg_iva_nc', 'tipo' => 'decimal', 'fijo_decimal' => 0],
            ['columna' => 'rendg_empresa', 'tipo' => 'entero', 'ctx' => 'empresa_id'],
            ['columna' => 'rendg_tipo_fac', 'tipo' => 'texto', 'fijo_texto' => '', 'max_len' => 3],
            ['columna' => 'rendg_letra_fac', 'tipo' => 'texto', 'fijo_texto' => '', 'max_len' => 1],
            ['columna' => 'rendg_sucursal_fac', 'tipo' => 'entero', 'fijo_entero' => 0],
            ['columna' => 'rendg_nro_fac', 'tipo' => 'entero', 'fijo_entero' => 0],
            ['columna' => 'rendg_fecha_fac', 'tipo' => 'entero', 'fijo_entero' => 0],
            ['columna' => 'rendg_estado', 'tipo' => 'texto', 'fijo_texto' => ' ', 'max_len' => 1],
            ['columna' => 'rendg_fecha_alfa', 'tipo' => 'texto', 'ctx' => 'fecha_alfa', 'max_len' => 8],
            ['columna' => 'rendg_turno', 'tipo' => 'texto', 'ctx' => 'turno_letra', 'max_len' => 1],
            ['columna' => 'rendg_sucursal', 'tipo' => 'entero', 'ctx' => 'sucursal_cae'],
            ['columna' => 'rendg_fecha_carga', 'tipo' => 'entero', 'ctx' => 'fecha_carga'],
            ['columna' => 'rendg_hora_carga', 'tipo' => 'texto', 'ctx' => 'hora_carga', 'max_len' => 8],
            ['columna' => 'rendg_fallo_mozo', 'tipo' => 'decimal', 'fijo_decimal' => 0],
            ['columna' => 'rendg_nro_rend_vta', 'tipo' => 'entero', 'ctx' => 'nro_rend_vta'],
            ['columna' => 'rendg_suc_caea', 'tipo' => 'entero', 'ctx' => 'suc_caea'],
            ['columna' => 'rendg_tot_fc_caea', 'tipo' => 'decimal', 'ctx' => 'tot_fc_caea'],
            ['columna' => 'rendg_tot_nc_caea', 'tipo' => 'decimal', 'ctx' => 'tot_nc_caea'],
            ['columna' => 'rendg_tipo_fc_caea', 'tipo' => 'texto', 'fijo_texto' => '', 'max_len' => 3],
            ['columna' => 'rendg_l_fc_caea', 'tipo' => 'texto', 'fijo_texto' => '', 'max_len' => 1],
            ['columna' => 'rendg_suc_fac_caea', 'tipo' => 'entero', 'fijo_entero' => 0],
            ['columna' => 'rendg_nro_fac_caea', 'tipo' => 'entero', 'fijo_entero' => 0],
            ...RendicionGastronomiaRendgastroEsquema::definicionesDecimalCero(),
            ['columna' => 'rendg_host', 'tipo' => 'texto', 'ctx' => 'host', 'max_len' => 15],
            ['columna' => 'rendg_otros_cred', 'tipo' => 'decimal', 'fijo_decimal' => 0],
        ];

        return $columnas;
    }

    /**
     * Solo mapeo ERP (ctx) + columnas explícitas en config para forzar 0 en update.
     *
     * @return list<array<string, mixed>>
     */
    private static function columnasUpdate(): array
    {
        $columnas = [];
        foreach (self::columnasInsert() as $col) {
            if ($col['clave'] ?? false) {
                continue;
            }
            if (isset($col['ctx'])) {
                $columnas[] = $col;
            }
        }

        $yaIncluidas = array_column($columnas, 'columna');
        foreach (self::columnasNumericasCeroEnUpdateConfig() as $nombre) {
            if (in_array($nombre, $yaIncluidas, true)) {
                continue;
            }
            $columnas[] = [
                'columna' => $nombre,
                'tipo' => 'decimal',
                'fijo_decimal' => 0,
            ];
        }

        return $columnas;
    }

    /**
     * Opt-in: numéricas que el bridge sí pisa en UPDATE (resto no se tocan).
     *
     * @return list<string>
     */
    private static function columnasNumericasCeroEnUpdateConfig(): array
    {
        $campos = config('rendicion_gastronomia_anita.cabecera_campos_numericos_cero_en_update', []);

        return is_array($campos) ? array_values($campos) : [];
    }

    /**
     * @param  array<string, mixed>  $col
     * @param  array<string, mixed>  $ctx
     */
    private static function valorSql(array $col, array $ctx): string
    {
        $tipo = $col['tipo'];

        if ($tipo === 'decimal') {
            if (array_key_exists('fijo_decimal', $col)) {
                return "'".self::decimal($col['fijo_decimal'])."'";
            }
            $ctxKey = (string) ($col['ctx'] ?? '');

            return "'".self::decimal($ctx[$ctxKey] ?? 0)."'";
        }

        if ($tipo === 'entero') {
            if (array_key_exists('fijo_entero', $col)) {
                return "'".self::entero($col['fijo_entero'])."'";
            }
            $ctxKey = (string) ($col['ctx'] ?? '');

            return "'".self::entero($ctx[$ctxKey] ?? 0)."'";
        }

        if (array_key_exists('fijo_texto', $col)) {
            return "'".self::texto($col['fijo_texto'], (int) ($col['max_len'] ?? 1))."'";
        }
        $ctxKey = (string) ($col['ctx'] ?? '');

        return "'".self::texto($ctx[$ctxKey] ?? '', (int) ($col['max_len'] ?? 1))."'";
    }
}
