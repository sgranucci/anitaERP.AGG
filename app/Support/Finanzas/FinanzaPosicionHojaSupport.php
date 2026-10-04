<?php

declare(strict_types=1);

namespace App\Support\Finanzas;

use App\Models\Caja\Cuentacaja;
use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaChequeAgingSupport;

/**
 * Hoja de la posición bancaria (Macro, BMA, BAPRO, Bi Bank, Bind)
 * a partir del banco de la cuenta de caja.
 */
final class FinanzaPosicionHojaSupport
{
    /** @var array<string, string> BCRA → título de hoja */
    private const HOJA_POR_BCRA = [
        '285' => 'Macro',
        '259' => 'Macro (BMA)',
        '014' => 'BAPRO',
        '147' => 'Bi Bank',
        '322' => 'Bind',
    ];

    /** @var array<int, string> empresa_id → columna Excel */
    public const COLUMNA_POR_EMPRESA = [
        1 => 'B',
        2 => 'C',
        3 => 'D',
    ];

    public static function hojaDesdeCuenta(?Cuentacaja $cuenta): ?string
    {
        if ($cuenta === null) {
            return null;
        }

        $bcra = str_pad(ltrim((string) ($cuenta->bancos?->codigo ?? ''), '0'), 3, '0', STR_PAD_LEFT);
        if ($bcra !== '000' && isset(self::HOJA_POR_BCRA[$bcra])) {
            return self::HOJA_POR_BCRA[$bcra];
        }

        $texto = strtoupper(trim((string) $cuenta->nombre.' '.(string) ($cuenta->bancos?->nombre ?? '')));
        if (str_contains($texto, 'ITAU') || str_contains($texto, 'BMA')) {
            return 'Macro (BMA)';
        }
        if (str_contains($texto, 'BI BANK') || str_contains($texto, 'BIBANK')) {
            return 'Bi Bank';
        }
        if (str_contains($texto, 'BAPRO') || str_contains($texto, 'PROVINCIA')) {
            return 'BAPRO';
        }
        if (str_contains($texto, 'BIND')) {
            return 'Bind';
        }
        if (str_contains($texto, 'MACRO')) {
            return 'Macro';
        }

        return match (PosicionBancariaChequeAgingSupport::normalizarBanco($texto)) {
            PosicionBancariaChequeAgingSupport::BANCO_MACRO => 'Macro',
            PosicionBancariaChequeAgingSupport::BANCO_ITAU => 'Macro (BMA)',
            PosicionBancariaChequeAgingSupport::BANCO_BIND => 'Bind',
            default => null,
        };
    }

    public static function empresaColumna(?Cuentacaja $cuenta, int $empresaPrecarga): ?int
    {
        $empresaId = (int) ($cuenta?->empresa_id ?: $empresaPrecarga);
        if (! isset(self::COLUMNA_POR_EMPRESA[$empresaId])) {
            return null;
        }

        return $empresaId;
    }
}
