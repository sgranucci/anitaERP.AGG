<?php

namespace App\Support\Sueldos\Lsd;

/**
 * Armado de registros oficiales LSD (guía G15 / diseño interfaz ARCA).
 */
class LsdRegistroSupport
{
    /**
     * @param  array<string, mixed>  $d
     */
    public static function registro01(array $d): string
    {
        $re = strtoupper((string) ($d['identificacion'] ?? 'SJ')) === 'RE';

        return LsdAnsiSupport::n('01', 2)
            .LsdAnsiSupport::cuil11($d['cuit'] ?? '')
            .LsdAnsiSupport::a($re ? 'RE' : 'SJ', 2)
            .LsdAnsiSupport::n($d['periodo'] ?? '', 6)
            .($re ? ' ' : LsdAnsiSupport::a($d['tipo_liquidacion'] ?? 'M', 1))
            .LsdAnsiSupport::n($d['nro_liquidacion'] ?? 0, 5)
            .($re ? '  ' : LsdAnsiSupport::n($d['dias_base'] ?? 30, 2))
            .LsdAnsiSupport::n($d['cantidad_04'] ?? 0, 6);
    }

    /**
     * @param  array<string, mixed>  $d
     */
    public static function registro02(array $d): string
    {
        return LsdAnsiSupport::n('02', 2)
            .LsdAnsiSupport::cuil11($d['cuil'] ?? '')
            .LsdAnsiSupport::a($d['legajo'] ?? '', 10)
            .LsdAnsiSupport::a($d['dependencia'] ?? '', 50)
            .LsdAnsiSupport::n($d['cbu'] ?? '', 22)
            .LsdAnsiSupport::n($d['dias_tope'] ?? 0, 3)
            .LsdAnsiSupport::fechaYmd($d['fecha_pago'] ?? null)
            .LsdAnsiSupport::fechaYmd($d['fecha_rubrica'] ?? null)
            .LsdAnsiSupport::a($d['forma_pago'] ?? '1', 1);
    }

    /**
     * @param  array<string, mixed>  $d
     */
    public static function registro03(array $d): string
    {
        return LsdAnsiSupport::n('03', 2)
            .LsdAnsiSupport::cuil11($d['cuil'] ?? '')
            .LsdAnsiSupport::a($d['codigo_empleador'] ?? '', 10)
            .LsdAnsiSupport::dec($d['cantidad'] ?? 0, 5, 2)
            .LsdAnsiSupport::a($d['unidad'] ?? '', 1)
            .LsdAnsiSupport::dec($d['importe'] ?? 0, 15, 2)
            .LsdAnsiSupport::a($d['dh'] ?? 'C', 1)
            .LsdAnsiSupport::n($d['periodo_ajuste'] ?? 0, 6);
    }

    /**
     * @param  array<string, mixed>  $d
     */
    public static function registro04(array $d): string
    {
        $rev1 = $d['situacion_1'] ?? ($d['situacion'] ?? '');
        $dia1 = $d['dia_inicio_1'] ?? 1;

        return LsdAnsiSupport::n('04', 2)
            .LsdAnsiSupport::cuil11($d['cuil'] ?? '')
            .LsdAnsiSupport::n($d['conyuge'] ?? 0, 1)
            .LsdAnsiSupport::n($d['hijos'] ?? 0, 2)
            .LsdAnsiSupport::n($d['cct'] ?? 0, 1)
            .LsdAnsiSupport::n($d['scvo'] ?? 1, 1)
            .LsdAnsiSupport::a($d['reduccion'] ?? '0', 1)
            .LsdAnsiSupport::a($d['tipo_empresa'] ?? '0', 1)
            .LsdAnsiSupport::a($d['tipo_operacion'] ?? '0', 1)
            .LsdAnsiSupport::a($d['codigo_situacion'] ?? '01', 2)
            .LsdAnsiSupport::a($d['codigo_condicion'] ?? '01', 2)
            .LsdAnsiSupport::a($d['actividad'] ?? '000', 3)
            .LsdAnsiSupport::a($d['modalidad'] ?? '001', 3)
            .LsdAnsiSupport::a($d['siniestrado'] ?? '00', 2)
            .LsdAnsiSupport::a($d['localidad'] ?? '00', 2)
            .LsdAnsiSupport::a($rev1, 2)
            .LsdAnsiSupport::n($dia1, 2)
            .LsdAnsiSupport::a($d['situacion_2'] ?? '', 2)
            .LsdAnsiSupport::n($d['dia_inicio_2'] ?? 0, 2)
            .LsdAnsiSupport::a($d['situacion_3'] ?? '', 2)
            .LsdAnsiSupport::n($d['dia_inicio_3'] ?? 0, 2)
            .LsdAnsiSupport::n($d['dias_trabajados'] ?? 0, 2)
            .LsdAnsiSupport::n($d['horas_trabajadas'] ?? 0, 3)
            .LsdAnsiSupport::dec($d['aporte_adicional_ss'] ?? 0, 5, 2)
            .LsdAnsiSupport::dec($d['contrib_tarea_dif'] ?? 0, 5, 2)
            .LsdAnsiSupport::a($d['codigo_os'] ?? '', 6)
            .LsdAnsiSupport::n($d['adherentes'] ?? 0, 2)
            .LsdAnsiSupport::dec($d['aporte_adicional_os'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['contrib_adicional_os'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_dif_aportes_os'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_dif_os_fsr'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_dif_lrt'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['rem_maternidad'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['rem_bruta'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_1'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_2'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_3'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_4'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_5'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_6'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_7'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_8'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_9'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_dif_ap_ss'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_dif_co_ss'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['base_10'] ?? 0, 15, 2)
            .LsdAnsiSupport::dec($d['importe_detraer'] ?? 0, 15, 2);
    }

    /**
     * @param  array<string, mixed>  $d
     */
    public static function registro05(array $d): string
    {
        return LsdAnsiSupport::n('05', 2)
            .LsdAnsiSupport::cuil11($d['cuil'] ?? '')
            .LsdAnsiSupport::n($d['categoria'] ?? 0, 6)
            .LsdAnsiSupport::n($d['puesto'] ?? 0, 4)
            .LsdAnsiSupport::fechaYmd($d['fecha_ingreso'] ?? null)
            .LsdAnsiSupport::fechaYmd($d['fecha_egreso'] ?? null)
            .LsdAnsiSupport::dec($d['importe'] ?? 0, 15, 2)
            .LsdAnsiSupport::cuil11($d['cuit_agencia'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $d
     */
    public static function registro06(array $d): string
    {
        return LsdAnsiSupport::n('06', 2)
            .LsdAnsiSupport::cuil11($d['cuil'] ?? '')
            .LsdAnsiSupport::a($d['observacion'] ?? '', 250);
    }

    /**
     * @param  array<string, mixed>  $d
     */
    public static function registroConcepto(array $d): string
    {
        $tipo = (string) ($d['tipo'] ?? LsdConceptoAfipCatalogo::tipoDesdeCodigo($d['concepto_afip'] ?? '') ?? 'remunerativo');
        $flags = LsdSubsistemaSupport::normalizar($d['subsistemas'] ?? null, $tipo);

        return LsdAnsiSupport::a($d['concepto_afip'] ?? '', 6)
            .LsdAnsiSupport::a($d['codigo_empleador'] ?? '', 10)
            .LsdAnsiSupport::a($d['descripcion'] ?? '', 150)
            .LsdAnsiSupport::a(! empty($d['repetible']) ? 'S' : 'N', 1)
            .LsdSubsistemaSupport::bloquetxt($flags);
    }

    public static function debitoCredito(string $tipoConcepto, float $importe): string
    {
        if (in_array($tipoConcepto, ['descuento', 'aporte', 'retencion'], true)) {
            return 'D';
        }
        if ($importe < 0) {
            return 'D';
        }

        return 'C';
    }
}
