<?php

namespace App\Support\Caja;

/**
 * Mapeo ctermae (cheques de terceros / CHT) → atributos ERP `cheque` origen R.
 *
 * Clave de cartera Anita: cter_nro_interno (no el número físico).
 * Schema Ferli: sin cter_empresa; AGG suele traerla.
 */
final class ChequeTerceroCtermaeAnitaMapper
{
    /**
     * @param  object  $row  Fila Anita ctermae
     * @param  array{
     *   empresa_id:int,
     *   cliente_id:?int,
     *   proveedor_id:?int,
     *   banco_id:?int
     * }  $ids
     * @return array<string, mixed>
     */
    public static function aAtributosErp(object $row, array $ids): array
    {
        $nroInterno = (int) preg_replace('/\D/', '', (string) ($row->cter_nro_interno ?? '0'));
        $nroCheque = trim((string) ($row->cter_nro_cheque ?? ''));
        if ($nroCheque === '0') {
            $nroCheque = '';
        }

        $estadoAnita = (string) ($row->cter_estado ?? ' ');
        $estado = $estadoAnita !== '' ? $estadoAnita : ' ';

        $fechaCheque = self::fechaAYMD($row->cter_fecha_cheque ?? null);
        $fechaIngreso = self::fechaAYMD($row->cter_fecha_ingreso ?? null) ?: $fechaCheque;

        return [
            'origen' => 'R',
            'caracter' => 'R',
            'estado' => $estado,
            'fechaemision' => $fechaIngreso,
            'fechapago' => $fechaCheque,
            'empresa_id' => (int) $ids['empresa_id'],
            'numerocheque' => $nroCheque !== '' ? $nroCheque : (string) $nroInterno,
            'nro_interno_anita' => $nroInterno > 0 ? $nroInterno : null,
            'moneda_id' => (int) ($row->cter_cod_mon ?? 1) ?: 1,
            'monto' => (float) ($row->cter_importe ?? 0),
            'cotizacion' => ChequePropioCpromaeAnitaMapper::cotizacion((float) ($row->cter_cotizacion ?? 0)),
            'cliente_id' => $ids['cliente_id'] ?? null,
            'proveedor_id' => $ids['proveedor_id'] ?? null,
            'banco_id' => $ids['banco_id'] ?? null,
            'sucursalpago' => self::textoONull($row->cter_sucursal_bco ?? null),
            'cuentalibradora' => self::textoONull($row->cter_cta_libradora ?? null),
            'entregado' => self::textoONull($row->cter_entregado_por ?? null)
                ?: self::textoONull($row->cter_banco_emision ?? null),
            'anombrede' => self::textoONull($row->cter_entregado_a ?? null),
            'nro_caucion' => self::nroCaucion($row->cter_nro_caucion ?? null),
        ];
    }

    private static function nroCaucion($valor): ?string
    {
        $t = trim((string) ($valor ?? ''));
        if ($t === '' || $t === '0') {
            return null;
        }

        return mb_substr($t, 0, 20);
    }

    public static function fechaAYMD($valor): ?string
    {
        $ymd = ChequePropioCpromaeAnitaMapper::ymd((string) ($valor ?? ''));
        if ($ymd === '0' || strlen($ymd) !== 8) {
            return null;
        }

        return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2);
    }

    private static function textoONull($valor): ?string
    {
        $t = trim((string) ($valor ?? ''));
        if ($t === '' || $t === '0') {
            return null;
        }

        return $t;
    }
}
