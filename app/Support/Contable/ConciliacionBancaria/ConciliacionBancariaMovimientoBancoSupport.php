<?php

namespace App\Support\Contable\ConciliacionBancaria;

/**
 * Transforma movimientos Interbanking persistidos en:
 * - fila EXTRACTO (crudo API)
 * - fila SALDO (codificada según tabla Codificacion bcos + P.C.C)
 */
final class ConciliacionBancariaMovimientoBancoSupport
{
    /**
     * @param  array<string, mixed>  $mov
     * @return array<string, mixed>
     */
    public static function filaExtracto(array $mov): array
    {
        $fecha = self::fechaMovimiento($mov);

        return [
            'fecha_valor' => $fecha,
            'fecha_ingreso' => $fecha,
            'concepto' => trim((string) ($mov['code_description_ib'] ?? '')),
            'cod_op' => trim((string) ($mov['operation_code_ib'] ?? '')),
            'comprobante' => self::formatearReferencia($mov['voucher_number'] ?? null),
            'comprobante_raw' => $mov['voucher_number'] ?? null,
            'sucursal' => trim((string) ($mov['branch_office_activity'] ?? '')),
            'importe' => ConciliacionBancariaHashSupport::importeFirmadoBanco($mov),
            'descripcion' => trim((string) ($mov['code_description_bank'] ?? '')),
            'cod_op_bco' => trim((string) ($mov['operation_code_bank'] ?? '')),
            'pcc' => self::normalizarPcc($mov['grouping_code_ib'] ?? null),
            'depositor_description' => trim((string) ($mov['depositor_description'] ?? '')),
            'dedupe_hash' => $mov['dedupe_hash'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $mov
     * @return array<string, mixed>
     */
    public static function filaSaldo(array $mov, float $saldoCorriente): array
    {
        $importe = ConciliacionBancariaHashSupport::importeFirmadoBanco($mov);
        $clasif = ConciliacionBancariaCodificacionSupport::clasificarMovimientoBanco($mov);
        $referencia = self::formatearReferencia($mov['voucher_number'] ?? null);

        return [
            'fecha' => self::fechaMovimiento($mov),
            'referencia' => $referencia,
            'referencia_raw' => $mov['voucher_number'] ?? null,
            'codigo' => $clasif['codigo'],
            'codigo_descripcion' => $clasif['descripcion'],
            'codigo_metodo' => $clasif['metodo'] ?? '',
            'concepto' => trim((string) ($mov['code_description_ib'] ?? '')),
            'importe' => $importe,
            'saldo' => round($saldoCorriente, 2),
            'pcc' => self::normalizarPcc($mov['grouping_code_ib'] ?? null),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos  Orden cronológico asc
     * @return array{extracto: list<array<string,mixed>>, saldo: list<array<string,mixed>>, saldo_inicial: float, saldo_final: float}
     */
    public static function procesarListado(array $movimientos, float $saldoInicial): array
    {
        $extracto = [];
        $saldo = [];
        $corriente = $saldoInicial;

        foreach ($movimientos as $mov) {
            $extracto[] = self::filaExtracto($mov);
            $corriente += ConciliacionBancariaHashSupport::importeFirmadoBanco($mov);
            $saldo[] = self::filaSaldo($mov, $corriente);
        }

        return [
            'extracto' => $extracto,
            'saldo' => $saldo,
            'saldo_inicial' => round($saldoInicial, 2),
            'saldo_final' => round($corriente, 2),
        ];
    }

    public static function formatearReferencia(mixed $voucher): string|int
    {
        if ($voucher === null || $voucher === '' || (int) $voucher === 0) {
            return 0;
        }

        $n = preg_replace('/\D/', '', (string) $voucher) ?? '';
        if ($n === '' || $n === '0') {
            return 0;
        }

        $partes = [];
        while (strlen($n) > 3) {
            $partes[] = substr($n, -3);
            $n = substr($n, 0, -3);
        }
        if ($n !== '') {
            $partes[] = $n;
        }

        $formateado = implode('.', array_reverse($partes));

        return str_contains($formateado, '.') ? $formateado : (int) $formateado;
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    public static function fechaMovimiento(array $mov): ?string
    {
        $fecha = $mov['process_date'] ?? null;
        if ($fecha === null || $fecha === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse((string) $fecha)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function normalizarPcc(mixed $pcc): string
    {
        $s = trim(preg_replace('/\s+/', ' ', (string) $pcc) ?? '');

        return $s;
    }
}
