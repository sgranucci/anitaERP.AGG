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

        $numerocheque = $nroCheque !== '' ? $nroCheque : (string) $nroInterno;
        $negociable = self::negociableDesdeInterior($row->cter_interior ?? null);
        $nroEcheq = self::nroEcheqDesdeFila($row, $negociable, $numerocheque);

        return [
            'origen' => 'R',
            'caracter' => 'R',
            'estado' => $estado,
            'fechaemision' => $fechaIngreso,
            'fechapago' => $fechaCheque,
            'empresa_id' => (int) $ids['empresa_id'],
            'numerocheque' => $numerocheque,
            'nro_interno_anita' => $nroInterno > 0 ? $nroInterno : null,
            'negociable' => $negociable,
            'nro_echeq' => $nroEcheq,
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

    /**
     * Anita cter_interior: '3' = e-cheq; resto (0/1/2 cámara) = físico.
     */
    public static function negociableDesdeInterior($interior): string
    {
        return trim((string) ($interior ?? '')) === '3' ? 'E' : 'N';
    }

    /**
     * Cámara Anita al grabar CHT: e-cheq fuerza '3'; si no, 1 al día / 2 diferido.
     */
    public static function interiorDesdeNegociable(string $negociable, string $camaraFallback): string
    {
        if (strtoupper(trim($negociable)) === 'E') {
            return '3';
        }

        $fb = trim($camaraFallback);

        return $fb !== '' ? $fb : '1';
    }

    private static function nroEcheqDesdeFila(object $row, string $negociable, string $numerocheque): ?string
    {
        if ($negociable !== 'E') {
            return null;
        }

        $desdeAnita = self::textoONull($row->cter_nro_e_cheq ?? null);
        if ($desdeAnita !== null) {
            return mb_substr($desdeAnita, 0, 50);
        }

        $nro = trim($numerocheque);

        return $nro !== '' ? mb_substr($nro, 0, 50) : null;
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
