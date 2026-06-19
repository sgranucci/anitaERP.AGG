<?php

namespace App\Support\Caja;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\InterbankingSaldoDiario;

final class InterbankingCuentacajaParametrosSupport
{
    public static function normalizarBankNumber3(string $raw): string
    {
        $d = preg_replace('/\D/', '', $raw) ?? '';
        $d = ltrim($d, '0');
        if ($d === '') {
            $d = '0';
        }

        return str_pad($d, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{bank_number: string, account_type: string, currency: string}|null
     */
    public static function parametrosApiDesdeCuentacaja(Cuentacaja $cuenta, int $empresaId): ?array
    {
        $accountNumber = trim((string) ($cuenta->cuenta_interbanking ?? ''));
        if ($accountNumber === '') {
            return null;
        }

        $saldo = InterbankingSaldoDiario::query()
            ->where('empresa_id', $empresaId)
            ->where('account_number', $accountNumber)
            ->orderByDesc('fecha')
            ->first(['bank_number', 'account_type', 'currency']);

        $bankRaw = (string) ($saldo->bank_number ?? $cuenta->bancos?->codigo ?? '');
        if ($bankRaw === '') {
            return null;
        }

        $currency = strtoupper((string) ($saldo->currency ?? self::currencyDesdeMoneda($cuenta)));
        if (! in_array($currency, ['ARS', 'USD'], true)) {
            $currency = 'ARS';
        }

        $accountType = strtoupper((string) ($saldo->account_type ?? 'CC'));
        if (! in_array($accountType, ['CC', 'CA'], true)) {
            $accountType = 'CC';
        }

        return [
            'bank_number' => self::normalizarBankNumber3($bankRaw),
            'account_type' => $accountType,
            'currency' => $currency,
        ];
    }

    private static function currencyDesdeMoneda(Cuentacaja $cuenta): string
    {
        $abrev = strtoupper(trim((string) ($cuenta->monedas?->abreviatura ?? '')));

        return in_array($abrev, ['USD', 'U$S', 'DOL', 'DOLAR'], true) ? 'USD' : 'ARS';
    }
}
