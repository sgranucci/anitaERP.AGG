<?php

namespace App\Support\Sueldos;

/**
 * Constantes del asiento de liquidación.
 * Devengamiento = este módulo. El tipo (SUEL o PER) lo elige el modo por empresa.
 * Pago del neto = solicitud de pago + Ingreso/Egreso (tipo TES), no un segundo asiento acá.
 */
final class SueldosAsientoSupport
{
    public const ABREV_TIPOASIENTO = 'SUEL';

    public const ABREV_TIPOASIENTO_ANITA = 'PER';

    public const PERMISO_CONTABILIZAR = 'contabilizar-liquidacion-sueldos';

    public const PERMISO_GENERAR_SP = 'generar-solicitudpago-liquidacion-sueldos';

    public const MONEDA_LOCAL_ID = 1;

    /** ctav_tipo (3 chars). ctav_tipo_asiento sale del tipoasiento (SUEL o PER). */
    public const CTAMOV_TIPO = 'SUE';

    /** ctav_sistema: P = Personal / sueldos en Anita. */
    public const CTAMOV_SISTEMA = 'P';

    public const CONCEPTO_SP_NOMBRE = 'SUELDOS';

    public const FORMA_PAGO_SP_NOMBRE = 'Transferencia';

    public static function observacionCabecera(int $numero, string $descripcion, string $periodo): string
    {
        $desc = trim($descripcion);
        $per = trim($periodo);

        return trim('Sueldos corrida '.$numero.($per !== '' ? ' '.$per : '').($desc !== '' ? ' — '.$desc : ''));
    }
}
