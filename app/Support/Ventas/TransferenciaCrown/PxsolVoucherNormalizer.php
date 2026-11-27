<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

/**
 * Convierte un voucher de PXSOL al formato de fila de transferencia Crown.
 */
final class PxsolVoucherNormalizer
{
    /**
     * @param  array<string, mixed>  $voucher
     * @return array<string, mixed>
     */
    public static function aFila(array $voucher): array
    {
        $tipoTexto = trim((string) ($voucher['voucher_type'] ?? ''));
        [$tipoAnita, $letra, $codigoAfip] = self::tipoDesdeTexto($tipoTexto);
        $sucursal = (int) ($voucher['fiscal_point_of_sale'] ?? 0);
        // Solo el número fiscal (cbte_number). El número temporal de la API es un borrador
        // sin comprobante emitido: no se usa como número de factura.
        $numero = (int) ($voucher['cbte_number'] ?? 0);
        $numeroTemporal = (int) ($voucher['temporal_voucher_number'] ?? 0);
        $fecha = self::fechaYmd((string) ($voucher['cbte_date'] ?? $voucher['date'] ?? ''));
        $vencimiento = self::fechaYmd((string) ($voucher['due_date'] ?? ''));
        if ($vencimiento === '') {
            $vencimiento = $fecha;
        }
        $pago = trim((string) ($voucher['payment_type'] ?? ''));
        $esCc = self::esCuentaCorriente($pago);
        $neto = abs((float) ($voucher['sub_total'] ?? 0));
        $iva = abs((float) ($voucher['iva'] ?? 0));
        $total = abs((float) ($voucher['total'] ?? 0));
        if ($total <= 0 && ($neto + $iva) > 0) {
            $total = round($neto + $iva, 2);
        }
        $doc = preg_replace('/\D+/', '', (string) ($voucher['document_number'] ?? '')) ?? '';
        $cae = trim((string) ($voucher['cae'] ?? ''));
        $vtoCae = self::fechaYmd((string) ($voucher['cae_expire_date'] ?? ''));
        $cotizacion = (float) ($voucher['currency_ctz'] ?? $voucher['exchange'] ?? 1);
        if ($cotizacion <= 0) {
            $cotizacion = 1.0;
        }

        return [
            'pxsol_voucher_id' => (int) ($voucher['id'] ?? 0),
            'fecha' => $fecha,
            'fechajornada' => $fecha,
            'fechavencimiento' => $vencimiento,
            'tipo' => $tipoAnita,
            'tipo_anita' => $tipoAnita,
            'letra' => $letra,
            'tipo_afip' => $codigoAfip,
            'codigo_afip' => $codigoAfip,
            'sucursal' => $sucursal,
            'numero' => $numero,
            'numero_temporal' => $numeroTemporal,
            'cae' => $cae,
            'fechavencimientocae' => $vtoCae,
            'cuit' => $doc,
            'nombre' => trim((string) ($voucher['social_reason'] ?? '')),
            'cotizacion' => $cotizacion,
            'moneda' => '1',
            'gravado21' => $neto,
            'iva21' => $iva,
            'gravado105' => 0.0,
            'iva105' => 0.0,
            'gravado27' => 0.0,
            'iva27' => 0.0,
            'exento' => 0.0,
            'noi' => 0.0,
            'percepcion_iva' => 0.0,
            'otros' => 0.0,
            'total' => $total,
            'payment_type' => $pago,
            'payment_status' => trim((string) ($voucher['payment_status'] ?? '')),
            'es_cuenta_corriente' => $esCc,
            'point_of_sale' => trim((string) ($voucher['point_of_sale'] ?? '')),
            'cash_pos_id' => (int) ($voucher['cash_pos_id'] ?? 0),
            'hotel_id' => (int) ($voucher['hotel_id'] ?? 0),
        ];
    }

    public static function esCuentaCorriente(string $paymentType): bool
    {
        $norm = str_replace([' ', '[', ']'], '', $paymentType);

        return str_contains($norm, 'Cuenta_Corriente') || str_contains($norm, 'CuentaCorriente');
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    public static function tipoDesdeTexto(string $texto): array
    {
        $t = mb_strtolower(trim($texto));
        $letra = 'B';
        if (preg_match('/\b([abct])\b/u', $t, $m) === 1) {
            $letra = strtoupper($m[1]);
        } elseif (preg_match('/([abct])$/u', preg_replace('/\s+/', '', $t) ?? '', $m) === 1) {
            $letra = strtoupper($m[1]);
        }

        $esNc = str_contains($t, 'nota de cr') || str_contains($t, 'nota_de_credito') || str_starts_with($t, 'nc');
        $esNd = str_contains($t, 'nota de d') || str_contains($t, 'nota_de_debito') || str_starts_with($t, 'nd');

        if ($esNc) {
            $tipo = 'NCD';
            $afip = match ($letra) {
                'A' => 3,
                'B' => 8,
                'T' => 197,
                default => 8,
            };
        } elseif ($esNd) {
            $tipo = 'NDV';
            $afip = match ($letra) {
                'A' => 2,
                'B' => 7,
                'T' => 196,
                default => 7,
            };
        } else {
            $tipo = 'FAC';
            $afip = match ($letra) {
                'A' => 1,
                'B' => 6,
                'T' => 195,
                'C' => 11,
                default => 6,
            };
        }

        return [$tipo, $letra, $afip];
    }

    private static function fechaYmd(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m) === 1) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})/', $valor, $m) === 1) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $valor, $m) === 1) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        try {
            return \Carbon\Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }
}
