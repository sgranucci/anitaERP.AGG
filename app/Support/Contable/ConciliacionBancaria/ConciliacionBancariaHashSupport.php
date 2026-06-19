<?php

namespace App\Support\Contable\ConciliacionBancaria;

final class ConciliacionBancariaHashSupport
{
    /**
     * @param  array<string, mixed>  $movimiento
     */
    public static function hashContable(int $cuentacajaId, array $movimiento): string
    {
        $payload = implode('|', [
            $cuentacajaId,
            (string) ($movimiento['origen'] ?? 'mayor'),
            (string) ($movimiento['fecha'] ?? ''),
            (string) ($movimiento['nro_asiento'] ?? ''),
            (string) ($movimiento['tipo_comp'] ?? ''),
            (string) ($movimiento['nro'] ?? ''),
            number_format((float) ($movimiento['debe'] ?? 0), 2, '.', ''),
            number_format((float) ($movimiento['haber'] ?? 0), 2, '.', ''),
            mb_substr(trim((string) ($movimiento['descripcion'] ?? '')), 0, 120),
        ]);

        return hash('sha256', $payload);
    }

    public static function hashBanco(int $cuentacajaId, string $dedupeHash): string
    {
        return hash('sha256', $cuentacajaId.'|'.$dedupeHash);
    }

    /**
     * Importe firmado desde el mayor (cuenta banco activo: debe − haber).
     *
     * @param  array<string, mixed>  $movimiento
     */
    public static function importeFirmadoContable(array $movimiento): float
    {
        return round((float) ($movimiento['debe'] ?? 0) - (float) ($movimiento['haber'] ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $movimiento
     */
    public static function importeFirmadoBanco(array $movimiento): float
    {
        $monto = (float) ($movimiento['amount'] ?? 0);
        $tipo = strtoupper(trim((string) ($movimiento['debit_credit_type'] ?? '')));

        if ($tipo === 'D') {
            return round(-abs($monto), 2);
        }

        return round(abs($monto), 2);
    }
}
