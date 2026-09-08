<?php

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\InterbankingSaldoDiario;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Mapea interbanking_saldo_diario → códigos canónicos de la hoja Saldos
 * (ej. BIY|MACRO|ARS|CC).
 */
final class PosicionBancariaSaldosInterbankingSupport
{
    /** @var array<string, string> bank_number BCRA → banco canónico */
    private const BANCO_POR_BCRA = [
        '285' => 'MACRO',
        '017' => 'FRANCES',
        '17' => 'FRANCES',
        '014' => 'BAPRO',
        '14' => 'BAPRO',
        '011' => 'BNA',
        '11' => 'BNA',
        '147' => 'BIBANK',
        '322' => 'BIND',
        '259' => 'ITAU',
    ];

    /** @var array<int, string> */
    private const SOC_POR_EMPRESA = [
        1 => 'BIY',
        2 => 'KAN',
        3 => 'REB',
    ];

    /**
     * @return array{
     *   fecha: string,
     *   por_codigo: array<string, float>,
     *   detalle: list<array<string, mixed>>,
     *   sin_mapear: list<array<string, mixed>>
     * }
     */
    public function saldosPorCodigo(Carbon $fecha, array $empresaIds = [1, 2, 3]): array
    {
        $fechaStr = $fecha->toDateString();
        $rows = InterbankingSaldoDiario::query()
            ->whereDate('fecha', $fechaStr)
            ->whereIn('empresa_id', $empresaIds)
            ->orderBy('empresa_id')
            ->orderBy('bank_number')
            ->get();

        // Si no hay exacto, usar último día disponible <= fecha
        if ($rows->isEmpty()) {
            $fechaAlt = InterbankingSaldoDiario::query()
                ->whereDate('fecha', '<=', $fechaStr)
                ->whereIn('empresa_id', $empresaIds)
                ->max('fecha');
            if ($fechaAlt) {
                $fechaStr = Carbon::parse($fechaAlt)->toDateString();
                $rows = InterbankingSaldoDiario::query()
                    ->whereDate('fecha', $fechaStr)
                    ->whereIn('empresa_id', $empresaIds)
                    ->orderBy('empresa_id')
                    ->orderBy('bank_number')
                    ->get();
            }
        }

        $cuentas = Cuentacaja::query()
            ->with('bancos')
            ->whereIn('empresa_id', $empresaIds)
            ->whereNotNull('cuenta_interbanking')
            ->where('cuenta_interbanking', '!=', '')
            ->get();

        $porCodigo = [];
        $detalle = [];
        $sinMapear = [];

        foreach ($rows as $ib) {
            $codigo = $this->resolverCodigo($ib, $cuentas);
            $importe = round((float) $ib->day_balance, 2);
            $item = [
                'empresa_id' => (int) $ib->empresa_id,
                'bank_number' => (string) $ib->bank_number,
                'account_number' => (string) $ib->account_number,
                'currency' => (string) $ib->currency,
                'account_type' => (string) ($ib->account_type ?? ''),
                'day_balance' => $importe,
                'account_label' => (string) ($ib->account_label ?? ''),
                'codigo' => $codigo,
            ];
            if ($codigo === null) {
                $sinMapear[] = $item;
                continue;
            }
            $porCodigo[$codigo] = round(($porCodigo[$codigo] ?? 0) + $importe, 2);
            $detalle[] = $item;
        }

        return [
            'fecha' => $fechaStr,
            'por_codigo' => $porCodigo,
            'detalle' => $detalle,
            'sin_mapear' => $sinMapear,
        ];
    }

    /**
     * @param  Collection<int, Cuentacaja>  $cuentas
     */
    private function resolverCodigo(InterbankingSaldoDiario $ib, Collection $cuentas): ?string
    {
        $soc = self::SOC_POR_EMPRESA[(int) $ib->empresa_id] ?? null;
        if ($soc === null) {
            return null;
        }

        $banco = $this->resolverBanco($ib, $cuentas);
        if ($banco === null) {
            return null;
        }

        $moneda = strtoupper(trim((string) $ib->currency));
        if ($moneda === 'U$S' || $moneda === 'DOL') {
            $moneda = 'USD';
        }
        if (! in_array($moneda, ['ARS', 'USD', 'EUR'], true)) {
            return null;
        }

        $tipo = strtoupper(trim((string) ($ib->account_type ?? 'CC')));
        if ($tipo === '' || ! in_array($tipo, ['CC', 'CA'], true)) {
            $tipo = 'CC';
        }

        // En Saldos el renglón principal de dólares Macro suele ser CC aunque IB diga CA.
        if ($banco === 'MACRO' && $moneda === 'USD') {
            $tipo = 'CC';
        }

        return $soc.'|'.$banco.'|'.$moneda.'|'.$tipo;
    }

    /**
     * @param  Collection<int, Cuentacaja>  $cuentas
     */
    private function resolverBanco(InterbankingSaldoDiario $ib, Collection $cuentas): ?string
    {
        $bank = ltrim((string) $ib->bank_number, '0');
        $bankPad = str_pad($bank, 3, '0', STR_PAD_LEFT);
        if (isset(self::BANCO_POR_BCRA[$bankPad])) {
            return self::BANCO_POR_BCRA[$bankPad];
        }
        if (isset(self::BANCO_POR_BCRA[$bank])) {
            return self::BANCO_POR_BCRA[$bank];
        }

        $acctDigits = $this->soloDigitos((string) $ib->account_number);
        foreach ($cuentas as $cc) {
            if ((int) $cc->empresa_id !== (int) $ib->empresa_id) {
                continue;
            }
            $ibDigits = $this->soloDigitos((string) $cc->cuenta_interbanking);
            if ($ibDigits === '' || $acctDigits === '') {
                continue;
            }
            if ($ibDigits === $acctDigits || str_ends_with($acctDigits, $ibDigits) || str_ends_with($ibDigits, $acctDigits)) {
                $fromCc = PosicionBancariaChequeAgingSupport::normalizarBanco(
                    (string) $cc->nombre.' '.(string) ($cc->bancos->nombre ?? '')
                );
                if ($fromCc !== null) {
                    return $fromCc;
                }
                $bcra = ltrim((string) ($cc->bancos->codigo ?? ''), '0');
                $bcraPad = str_pad($bcra, 3, '0', STR_PAD_LEFT);
                if (isset(self::BANCO_POR_BCRA[$bcraPad])) {
                    return self::BANCO_POR_BCRA[$bcraPad];
                }
            }
        }

        return null;
    }

    private function soloDigitos(string $valor): string
    {
        return ltrim((string) preg_replace('/\D+/', '', $valor), '0');
    }
}
