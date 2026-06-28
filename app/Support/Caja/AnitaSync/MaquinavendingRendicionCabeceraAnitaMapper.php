<?php

namespace App\Support\Caja\AnitaSync;

/**
 * Mapeo rendgastro vending: rendg_estado en blanco (como estacionamiento), no 'F' (gastro).
 */
final class MaquinavendingRendicionCabeceraAnitaMapper
{
    /** Estado pendiente de cierre contable (vending / estacionamiento; no 'F' de gastro). */
    public const ESTADO_PENDIENTE_CONTABILIDAD = ' ';

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
     * @return list<array<string, mixed>>
     */
    private static function columnasInsert(): array
    {
        return [
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
            ['columna' => 'rendg_estado', 'tipo' => 'texto', 'fijo_texto' => self::ESTADO_PENDIENTE_CONTABILIDAD, 'max_len' => 1],
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
    }

    /**
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

                continue;
            }
            if (($col['columna'] ?? '') === 'rendg_estado') {
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
     * @return list<string>
     */
    private static function columnasNumericasCeroEnUpdateConfig(): array
    {
        $campos = config('rendicion_maquinavending_anita.cabecera_campos_numericos_cero_en_update', []);

        return is_array($campos) ? array_values($campos) : [];
    }

    public static function valoresUpdatePresentacionCaja(float $totalZ): string
    {
        return 'rendg_total_z = '.self::decimal($totalZ)
            .', rendg_estado = \''
            .self::texto(self::ESTADO_PENDIENTE_CONTABILIDAD, 1)
            .'\'';
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
