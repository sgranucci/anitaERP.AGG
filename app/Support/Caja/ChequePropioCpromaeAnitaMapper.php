<?php

namespace App\Support\Caja;

/**
 * Mapeo cpromae de cheques propios emitidos (CHP), alineado a lo que graba Anita pago.c.
 *
 * Muestra típica MACRO (79030350–79030365):
 * estado espacio (diferido / no debitado), cotización 1 si PES, para_dep E,
 * negociable N (física), estado_banco espacio, fecha_entrega/sucursal/tipo_distrib 0.
 */
final class ChequePropioCpromaeAnitaMapper
{
    /**
     * @param  array{
     *   cuenta?:string,
     *   nro?:int|string,
     *   fecha_emision?:string,
     *   fecha_pago?:string,
     *   importe?:float|int|string,
     *   proveedor?:string,
     *   entregado?:string,
     *   anombrede?:string,
     *   nro_op?:int|string,
     *   moneda_id?:int,
     *   cotizacion?:float|int|string,
     *   empresa?:int|string,
     *   chequera_codigo?:string,
     *   chequera_tipo?:string,
     *   caracter?:string,
     *   para_dep?:string,
     *   negociable?:string,
     *   nro_echeq?:string,
     *   fecha_entrega?:string,
     *   sucursal_pago?:string,
     *   tipo_distrib?:string,
     *   estado_erp?:string
     * }  $in
     * @return array<string, string>
     */
    public static function mapear(array $in): array
    {
        $cuenta = preg_replace('/\D+/', '', (string) ($in['cuenta'] ?? '')) ?? '';
        $cuenta = str_pad(ltrim($cuenta, '0') === '' ? '0' : ltrim($cuenta, '0'), 8, '0', STR_PAD_LEFT);
        $nro = (int) preg_replace('/\D/', '', (string) ($in['nro'] ?? ''));
        $tipoChequera = strtoupper(trim((string) ($in['chequera_tipo'] ?? 'F')));
        $negociableIn = strtoupper(trim((string) ($in['negociable'] ?? '')));
        $negociable = $negociableIn === 'E' || $negociableIn === 'N'
            ? $negociableIn
            : ChequePropioInstrumentoSupport::negociableDesdeChequera($tipoChequera);
        $paraDep = ChequePropioInstrumentoSupport::paraDep(
            (string) ($in['para_dep'] ?? ''),
            ChequePropioInstrumentoSupport::paraDepDefault()
        );
        $nroEcheq = trim((string) ($in['nro_echeq'] ?? ''));
        if ($nroEcheq === '') {
            $nroEcheq = ChequePropioInstrumentoSupport::nroEcheq($negociable, (string) $nro);
        }
        $fechaEntrega = self::ymd((string) ($in['fecha_entrega'] ?? ''));
        if ($fechaEntrega === '0' || $fechaEntrega === '') {
            $fechaEntrega = '0';
        }
        $sucursal = trim((string) ($in['sucursal_pago'] ?? ''));
        $tipoDist = trim((string) ($in['tipo_distrib'] ?? ''));
        $entregado = self::recortar((string) ($in['entregado'] ?? ''), 30);
        $aNombre = self::recortar((string) ($in['anombrede'] ?? $entregado), 40);
        $proveedor = str_pad(ltrim((string) ($in['proveedor'] ?? '0'), '0') ?: '0', 6, '0', STR_PAD_LEFT);
        $modelo = (int) preg_replace('/\D/', '', (string) ($in['chequera_codigo'] ?? '0'));

        return [
            'cpro_cuenta' => $cuenta,
            'cpro_nro_cheque' => (string) $nro,
            'cpro_fecha_cheque' => self::ymd($in['fecha_pago'] ?? ''),
            'cpro_fecha_emision' => self::ymd($in['fecha_emision'] ?? ''),
            'cpro_importe' => self::num((float) ($in['importe'] ?? 0)),
            'cpro_proveedor' => $proveedor,
            'cpro_entregado_a' => $entregado,
            'cpro_nro_op' => (string) ((int) ($in['nro_op'] ?? 0)),
            'cpro_cod_mon' => (string) ((int) ($in['moneda_id'] ?? 1) ?: 1),
            'cpro_cotizacion' => self::num(self::cotizacion((float) ($in['cotizacion'] ?? 0))),
            'cpro_estado' => self::estado(
                (string) ($in['fecha_emision'] ?? ''),
                (string) ($in['fecha_pago'] ?? ''),
                (string) ($in['estado_erp'] ?? '')
            ),
            'cpro_contrapartida' => ' ',
            'cpro_fecha_anula' => '0',
            'cpro_fl_imprimio' => ' ',
            'cpro_a_nombre_de' => $aNombre,
            'cpro_modelo' => (string) $modelo,
            'cpro_para_dep' => $paraDep,
            'cpro_fecha_entrega' => $fechaEntrega,
            'cpro_empresa' => (string) ((int) ($in['empresa'] ?? 1) ?: 1),
            'cpro_negociable' => $negociable,
            'cpro_estado_banco' => ' ',
            'cpro_sucursal_pago' => $sucursal !== '' ? $sucursal : '0',
            'cpro_tipo_distrib' => $tipoDist !== '' ? $tipoDist : '0',
            'cpro_nro_e_cheq' => $negociable === 'E' ? ($nroEcheq !== '' ? $nroEcheq : (string) $nro) : ' ',
        ];
    }

    public static function estado(string $fechaEmision, string $fechaPago, string $estadoErp = ''): string
    {
        $e = strtoupper(trim($estadoErp));
        if (in_array($e, ['A', 'R', 'C'], true)) {
            return $e;
        }
        if ($e === 'ANULADO') {
            return 'A';
        }
        if ($e === 'RECHAZADO') {
            return 'R';
        }
        if ($e === 'CIERRE') {
            return 'C';
        }

        $emi = self::ymd($fechaEmision);
        $pago = self::ymd($fechaPago);
        if ($emi !== '' && $pago !== '' && $pago > $emi) {
            return ' ';
        }

        return '*';
    }

    public static function cotizacion(float $cotizacion): float
    {
        return $cotizacion > 0 ? $cotizacion : 1.0;
    }

    public static function ymd(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return '0';
        }
        if (preg_match('/^(\d{8})$/', $fecha, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $fecha, $m)) {
            return $m[1].$m[2].$m[3];
        }

        $ts = strtotime($fecha);

        return $ts ? date('Ymd', $ts) : '0';
    }

    private static function num(float $n): string
    {
        if (abs($n - round($n)) < 0.0000001) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(sprintf('%.8F', $n), '0'), '.');
    }

    private static function recortar(string $valor, int $max): string
    {
        $valor = trim($valor);
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $max);
        }

        return substr($valor, 0, $max);
    }
}
