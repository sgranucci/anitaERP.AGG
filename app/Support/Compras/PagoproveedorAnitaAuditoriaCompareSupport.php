<?php

namespace App\Support\Compras;

use App\Support\Caja\ChequePropioCpromaeAnitaMapper;
use App\Support\Contable\AsientoBalanceSupport;

/**
 * Comparaciones puras ERP ↔ Anita para la auditoría de OP (sin I/O).
 */
final class PagoproveedorAnitaAuditoriaCompareSupport
{
    public const TOLERANCIA_IMPORTE = 0.05;

    public static function normalizarChar1(string $valor): string
    {
        $v = strtoupper(trim($valor));

        return $v === '' ? ' ' : $v;
    }

    public static function importesCercanos(float|int|string $a, float|int|string $b, float $tol = self::TOLERANCIA_IMPORTE): bool
    {
        return abs((float) $a - (float) $b) <= $tol;
    }

    public static function fechaYmd(string $fecha): string
    {
        return ChequePropioCpromaeAnitaMapper::ymd($fecha);
    }

    /**
     * @param  iterable<mixed>  $movimientos
     * @return array{total_debe: float, total_haber: float, diferencia: float, lineas_con_importe: int, balanceado: bool}
     */
    public static function balanceDesdeAsientoMovimientos(iterable $movimientos): array
    {
        $debes = [];
        $haberes = [];
        foreach ($movimientos as $mov) {
            $monto = is_array($mov)
                ? (float) ($mov['monto'] ?? 0)
                : (float) ($mov->monto ?? 0);
            if (abs($monto) < 0.0001) {
                continue;
            }
            $debes[] = $monto > 0 ? $monto : 0;
            $haberes[] = $monto < 0 ? abs($monto) : 0;
        }

        return AsientoBalanceSupport::totalesDesdeDebeHaber($debes, $haberes);
    }

    /**
     * @param  iterable<mixed>  $filas  filas ctamov (ctav_d_h / ctav_importe)
     * @return array{total_debe: float, total_haber: float, diferencia: float, lineas_con_importe: int, balanceado: bool}
     */
    public static function totalesDesdeCtamov(iterable $filas): array
    {
        $debes = [];
        $haberes = [];
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : get_object_vars($fila);
            $importe = abs((float) ($row['ctav_importe'] ?? 0));
            if ($importe < 0.0001) {
                continue;
            }
            $dh = strtoupper(trim((string) ($row['ctav_d_h'] ?? 'D')));
            if ($dh === 'H') {
                $debes[] = 0;
                $haberes[] = $importe;
            } else {
                $debes[] = $importe;
                $haberes[] = 0;
            }
        }

        return AsientoBalanceSupport::totalesDesdeDebeHaber($debes, $haberes);
    }

    /**
     * @param  array{total_debe: float, total_haber: float, lineas_con_importe: int, balanceado?: bool}  $erp
     * @param  array{total_debe: float, total_haber: float, lineas_con_importe: int, balanceado?: bool}  $ctamov
     * @return list<string>
     */
    public static function discrepanciasCtamovVsErp(array $erp, array $ctamov): array
    {
        $problemas = [];
        $lineasErp = (int) ($erp['lineas_con_importe'] ?? 0);
        $lineasAnita = (int) ($ctamov['lineas_con_importe'] ?? 0);
        if ($lineasAnita <= 0) {
            return ['Falta ctamov'];
        }
        if ($lineasErp > 0 && $lineasErp !== $lineasAnita) {
            $problemas[] = 'ctamov líneas '.$lineasAnita.' ≠ asiento ERP '.$lineasErp;
        }
        if (! self::importesCercanos($erp['total_debe'] ?? 0, $ctamov['total_debe'] ?? 0)) {
            $problemas[] = 'ctamov Debe '.($ctamov['total_debe'] ?? 0).' ≠ asiento ERP '.($erp['total_debe'] ?? 0);
        }
        if (! self::importesCercanos($erp['total_haber'] ?? 0, $ctamov['total_haber'] ?? 0)) {
            $problemas[] = 'ctamov Haber '.($ctamov['total_haber'] ?? 0).' ≠ asiento ERP '.($erp['total_haber'] ?? 0);
        }
        if (! ($ctamov['balanceado'] ?? true)) {
            $problemas[] = 'ctamov desbalanceado Debe '.($ctamov['total_debe'] ?? 0)
                .' vs Haber '.($ctamov['total_haber'] ?? 0);
        }

        return $problemas;
    }

    /**
     * @param  array<string, mixed>  $esperado  salida de ChequePropioCpromaeAnitaMapper::mapear
     * @param  array<string, mixed>|object  $anita
     * @return list<string>
     */
    public static function discrepanciasCpromae(array $esperado, array|object $anita): array
    {
        $a = is_array($anita) ? $anita : (array) $anita;
        $problemas = [];

        $pares = [
            'cpro_cuenta' => 'cuenta',
            'cpro_nro_cheque' => 'nro',
            'cpro_fecha_cheque' => 'fecha pago',
            'cpro_fecha_emision' => 'fecha emisión',
            'cpro_nro_op' => 'nro OP',
            'cpro_para_dep' => 'para_dep',
            'cpro_negociable' => 'negociable',
        ];
        foreach ($pares as $campo => $etiqueta) {
            $exp = self::normalizarComparable((string) ($esperado[$campo] ?? ''));
            $got = self::normalizarComparable((string) ($a[$campo] ?? ''));
            if ($exp !== $got) {
                $problemas[] = $etiqueta.' Anita '.$got.' ≠ ERP '.$exp;
            }
        }

        if (! self::importesCercanos($esperado['cpro_importe'] ?? 0, $a['cpro_importe'] ?? 0)) {
            $problemas[] = 'importe Anita '.($a['cpro_importe'] ?? '?').' ≠ ERP '.($esperado['cpro_importe'] ?? '?');
        }

        $cotExp = ChequePropioCpromaeAnitaMapper::cotizacion((float) ($esperado['cpro_cotizacion'] ?? 0));
        $cotGot = ChequePropioCpromaeAnitaMapper::cotizacion((float) ($a['cpro_cotizacion'] ?? 0));
        if (! self::importesCercanos($cotExp, $cotGot, 0.0001)) {
            $problemas[] = 'cotización Anita '.$cotGot.' ≠ ERP '.$cotExp;
        }

        $estExp = self::normalizarChar1((string) ($esperado['cpro_estado'] ?? ''));
        $estGot = self::normalizarChar1((string) ($a['cpro_estado'] ?? ''));
        if ($estExp !== $estGot) {
            $problemas[] = 'estado Anita "'.$estGot.'" ≠ ERP "'.$estExp.'"';
        }

        return $problemas;
    }

    public static function normalizarComparable(string $valor): string
    {
        $v = trim($valor);
        if ($v === '' || $v === '0') {
            return $v === '0' ? '0' : '';
        }
        if (is_numeric($v)) {
            if (str_contains($v, '.')) {
                return rtrim(rtrim(sprintf('%.8F', (float) $v), '0'), '.');
            }

            return (string) (int) $v;
        }

        return strtoupper($v);
    }
}
