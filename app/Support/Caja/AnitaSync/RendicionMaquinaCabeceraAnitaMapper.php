<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

/**
 * Mapeo contexto → columnas Informix rendmaquina (91 campos).
 * INSERT: numéricos sin mapeo → 0; textos → vacío/espacio según columna.
 */
final class RendicionMaquinaCabeceraAnitaMapper
{
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

    public static function whereClave(int $nroOper): string
    {
        return ' WHERE rendm_nro_oper = '.$nroOper;
    }

    /**
     * @return list<array{columna:string, tipo:string, ctx?:string, fijo?:mixed, max?:int}>
     */
    private static function columnasInsert(): array
    {
        return self::columnasMapeadas();
    }

    /**
     * @return list<array{columna:string, tipo:string, ctx?:string, fijo?:mixed, max?:int}>
     */
    private static function columnasUpdate(): array
    {
        return array_values(array_filter(
            self::columnasMapeadas(),
            fn (array $col) => ($col['columna'] ?? '') !== 'rendm_nro_oper',
        ));
    }

    /**
     * @return list<array{columna:string, tipo:string, ctx?:string, fijo?:mixed, max?:int}>
     */
    private static function columnasMapeadas(): array
    {
        return [
            ['columna' => 'rendm_nro_oper', 'tipo' => 'entero', 'ctx' => 'nro_oper'],
            ['columna' => 'rendm_tipo_oper', 'tipo' => 'texto', 'ctx' => 'tipo_oper', 'max' => 1],
            ['columna' => 'rendm_caja', 'tipo' => 'entero', 'ctx' => 'caja_id'],
            ['columna' => 'rendm_cajero', 'tipo' => 'entero', 'ctx' => 'cajero_id'],
            ['columna' => 'rendm_fecha', 'tipo' => 'entero', 'ctx' => 'fecha_entera'],
            ['columna' => 'rendm_hora', 'tipo' => 'texto', 'ctx' => 'hora', 'max' => 8],
            ['columna' => 'rendm_usuario', 'tipo' => 'entero', 'ctx' => 'usuario_id'],
            ['columna' => 'rendm_sobrantes', 'tipo' => 'decimal', 'ctx' => 'sobrantes'],
            ['columna' => 'rendm_vales', 'tipo' => 'decimal', 'fijo' => 0],
            ['columna' => 'rendm_reintegros', 'tipo' => 'decimal', 'fijo' => 0],
            ['columna' => 'rendm_deposito', 'tipo' => 'decimal', 'ctx' => 'deposito'],
            ['columna' => 'rendm_venta_ficha', 'tipo' => 'decimal', 'ctx' => 'venta_ficha'],
            ['columna' => 'rendm_drop_billant', 'tipo' => 'decimal', 'ctx' => 'drop_bill_ant'],
            ['columna' => 'rendm_drop_billete', 'tipo' => 'decimal', 'ctx' => 'drop_billete'],
            ['columna' => 'rendm_pago_manual', 'tipo' => 'decimal', 'ctx' => 'pago_manual'],
            ['columna' => 'rendm_tito', 'tipo' => 'decimal', 'ctx' => 'tito'],
            ['columna' => 'rendm_hopper', 'tipo' => 'decimal', 'ctx' => 'hopper'],
            ['columna' => 'rendm_venta_ruleta', 'tipo' => 'decimal', 'ctx' => 'venta_ruleta'],
            ['columna' => 'rendm_drop_rul_ant', 'tipo' => 'decimal', 'ctx' => 'drop_rul_ant'],
            ['columna' => 'rendm_drop_ruleta', 'tipo' => 'decimal', 'ctx' => 'drop_ruleta'],
            ['columna' => 'rendm_salida_rul', 'tipo' => 'decimal', 'ctx' => 'salida_ruleta'],
            ['columna' => 'rendm_tito_ruleta', 'tipo' => 'decimal', 'ctx' => 'tito_ruleta'],
            ['columna' => 'rendm_vale_post', 'tipo' => 'decimal', 'ctx' => 'vale_anterior'],
            ['columna' => 'rendm_observacion', 'tipo' => 'texto', 'ctx' => 'observacion', 'max' => 200],
            ['columna' => 'rendm_empresa', 'tipo' => 'entero', 'ctx' => 'empresa_anita'],
            ['columna' => 'rendm_tipo_fac', 'tipo' => 'texto', 'fijo' => '', 'max' => 3],
            ['columna' => 'rendm_letra_fac', 'tipo' => 'texto', 'fijo' => '', 'max' => 1],
            ['columna' => 'rendm_sucursal_fac', 'tipo' => 'entero', 'fijo' => 0],
            ['columna' => 'rendm_nro_fac', 'tipo' => 'entero', 'fijo' => 0],
            ['columna' => 'rendm_fecha_fac', 'tipo' => 'entero', 'fijo' => 0],
            ['columna' => 'rendm_estado', 'tipo' => 'texto', 'ctx' => 'estado', 'max' => 1],
            ['columna' => 'rendm_dif_caja', 'tipo' => 'decimal', 'ctx' => 'dif_caja'],
            ['columna' => 'rendm_reint_ff_ger', 'tipo' => 'decimal', 'ctx' => 'reint_ff_ger'],
            ['columna' => 'rendm_reint_ff_adm', 'tipo' => 'decimal', 'ctx' => 'reint_ff_adm'],
            ['columna' => 'rendm_reint_ff_fin', 'tipo' => 'decimal', 'ctx' => 'reint_ff_fin'],
            ['columna' => 'rendm_perd_de_pers', 'tipo' => 'decimal', 'ctx' => 'perd_de_pers'],
            ['columna' => 'rendm_reconoc_cli', 'tipo' => 'decimal', 'ctx' => 'reconoc_cli'],
            ['columna' => 'rendm_desperf_maq', 'tipo' => 'decimal', 'ctx' => 'desperf_maq'],
            ['columna' => 'rendm_retiros_adm', 'tipo' => 'decimal', 'ctx' => 'retiros_adm'],
            ['columna' => 'rendm_gastos_div', 'tipo' => 'decimal', 'ctx' => 'gastos_div'],
            ['columna' => 'rendm_fecha_alfa', 'tipo' => 'texto', 'ctx' => 'fecha_alfa', 'max' => 8],
            ['columna' => 'rendm_turno', 'tipo' => 'texto', 'ctx' => 'turno', 'max' => 1],
            ['columna' => 'rendm_fondo_ini', 'tipo' => 'decimal', 'ctx' => 'fondo_inicial'],
            ['columna' => 'rendm_comprobante', 'tipo' => 'decimal', 'ctx' => 'comprobante'],
            ['columna' => 'rendm_fondo_fijo', 'tipo' => 'decimal', 'ctx' => 'fondo_fijo'],
            ['columna' => 'rendm_dr_bill_rod', 'tipo' => 'decimal', 'ctx' => 'drop_bill_rodillo'],
            ['columna' => 'rendm_dr_bill_rul', 'tipo' => 'decimal', 'ctx' => 'drop_bill_ruleta'],
            ['columna' => 'rendm_ent_ruleta', 'tipo' => 'decimal', 'ctx' => 'entrada_ruleta'],
            ['columna' => 'rendm_vta_ficha', 'tipo' => 'decimal', 'ctx' => 'venta_ficha_calc'],
            ['columna' => 'rendm_sobrante_sup', 'tipo' => 'decimal', 'ctx' => 'sobrante_supervisor'],
            ['columna' => 'rendm_tot_ingreso', 'tipo' => 'decimal', 'ctx' => 'total_ingreso'],
            ['columna' => 'rendm_tito_rodillo', 'tipo' => 'decimal', 'ctx' => 'tito_rodillo'],
            ['columna' => 'rendm_tito_rul', 'tipo' => 'decimal', 'ctx' => 'tito_ruleta_calc'],
            ['columna' => 'rendm_dep_pesos', 'tipo' => 'decimal', 'ctx' => 'deposito_pesos'],
            ['columna' => 'rendm_sal_ruleta', 'tipo' => 'decimal', 'ctx' => 'salida_ruleta'],
            ['columna' => 'rendm_pago_man', 'tipo' => 'decimal', 'ctx' => 'pago_manual'],
            ['columna' => 'rendm_total_salida', 'tipo' => 'decimal', 'ctx' => 'total_salida'],
            ['columna' => 'rendm_vale_fondo', 'tipo' => 'decimal', 'ctx' => 'vale_rep_fondo'],
            ['columna' => 'rendm_deposito_efe', 'tipo' => 'decimal', 'ctx' => 'deposito_efectivo'],
            ['columna' => 'rendm_resul_turno', 'tipo' => 'decimal', 'ctx' => 'resultado_turno'],
            ['columna' => 'rendm_comp_cie', 'tipo' => 'decimal', 'ctx' => 'comprobante_cierre'],
            ['columna' => 'rendm_fondo_cierre', 'tipo' => 'decimal', 'ctx' => 'fondo_cierre'],
            ['columna' => 'rendm_transfer', 'tipo' => 'decimal', 'ctx' => 'transferencia'],
            ['columna' => 'rendm_fecha_carga', 'tipo' => 'entero', 'ctx' => 'fecha_carga'],
            ['columna' => 'rendm_hora_carga', 'tipo' => 'texto', 'ctx' => 'hora_carga', 'max' => 8],
            ['columna' => 'rendm_variacion_ff', 'tipo' => 'decimal', 'ctx' => 'variacion_ff'],
            ['columna' => 'rendm_supervisor', 'tipo' => 'entero', 'ctx' => 'supervisor_id'],
            ['columna' => 'rendm_auxiliar', 'tipo' => 'entero', 'ctx' => 'auxiliar_id'],
            ['columna' => 'rendm_drop', 'tipo' => 'entero', 'fijo' => 0],
            ['columna' => 'rendm_billem_rod', 'tipo' => 'decimal', 'ctx' => 'billem_rodillo'],
            ['columna' => 'rendm_billem_rul', 'tipo' => 'decimal', 'ctx' => 'billem_ruleta'],
            ['columna' => 'rendm_ref_bingo', 'tipo' => 'decimal', 'ctx' => 'ref_bingo'],
            ['columna' => 'rendm_ff_maquina', 'tipo' => 'decimal', 'ctx' => 'ff_maquina'],
            ['columna' => 'rendm_ff_legales', 'tipo' => 'decimal', 'ctx' => 'ff_legales'],
            ['columna' => 'rendm_canje_puntos', 'tipo' => 'decimal', 'ctx' => 'canje_puntos'],
            ['columna' => 'rendm_canje_gastro', 'tipo' => 'decimal', 'ctx' => 'canje_gastro'],
            ['columna' => 'rendm_forma_pago', 'tipo' => 'texto', 'fijo' => '', 'max' => 1],
            ['columna' => 'rendm_cuenta', 'tipo' => 'texto', 'fijo' => '', 'max' => 8],
            ['columna' => 'rendm_nro_cheque', 'tipo' => 'entero', 'fijo' => 0],
            ['columna' => 'rendm_pago_dif', 'tipo' => 'decimal', 'ctx' => 'pago_diferido'],
            ['columna' => 'rendm_nro_op', 'tipo' => 'entero', 'fijo' => 0],
            ['columna' => 'rendm_imp_drop', 'tipo' => 'decimal', 'ctx' => 'impuesto_drop'],
            ['columna' => 'rendm_imp_venta', 'tipo' => 'decimal', 'ctx' => 'impuesto_venta'],
            ['columna' => 'rendm_ticket_prom', 'tipo' => 'decimal', 'ctx' => 'ticket_prom'],
            // Campo Anita obsoleto en ERP: siempre 0 (el drop se edita directo).
            ['columna' => 'rendm_aj_wigosd', 'tipo' => 'decimal', 'ctx' => 'ajuste_wigosd'],
            ['columna' => 'rendm_vtaant_gast', 'tipo' => 'decimal', 'fijo' => 0],
            ['columna' => 'rendm_ajwigtitorod', 'tipo' => 'decimal', 'fijo' => 0],
            ['columna' => 'rendm_ajuste_wige', 'tipo' => 'decimal', 'fijo' => 0],
            ['columna' => 'rendm_dropqr_rod', 'tipo' => 'decimal', 'ctx' => 'dropqr_rodillo'],
            ['columna' => 'rendm_dropqr_rul', 'tipo' => 'decimal', 'ctx' => 'dropqr_ruleta'],
            ['columna' => 'rendm_impuesto_qr', 'tipo' => 'decimal', 'ctx' => 'impuesto_qr'],
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
