<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\InterbankingMovimiento;
use App\Models\Caja\InterbankingSaldoDiario;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;

final class ConciliacionBancariaEngancheSupport
{
    /**
     * @return array<string, mixed>
     */
    public static function datosEnganche(int $empresaId, int $cuentacajaId): array
    {
        $cuenta = Cuentacaja::query()
            ->with(['cuentacontables', 'bancos', 'monedas', 'empresas'])
            ->find($cuentacajaId);

        if (! $cuenta || ! $cuenta->perteneceAEmpresa($empresaId)) {
            return ['ok' => false, 'error' => 'Cuenta de caja no encontrada o no pertenece a la empresa.'];
        }

        $cc = $cuenta->cuentacontables;
        $codigoContable = $cc ? MayorPlanoCuentaSupport::parsearCodigoCuenta((string) $cc->codigo) : 0;
        $cuentaIb = trim((string) ($cuenta->cuenta_interbanking ?? ''));

        $saldoIb = null;
        $movimientosCount = 0;
        $ultimoMovimiento = null;
        $bankNumber = '';

        if ($cuentaIb !== '' && $empresaId > 0) {
            $saldoIb = InterbankingSaldoDiario::query()
                ->where('empresa_id', $empresaId)
                ->where('account_number', $cuentaIb)
                ->orderByDesc('fecha')
                ->first();

            $movimientosCount = InterbankingMovimiento::query()
                ->where('empresa_id', $empresaId)
                ->where('account_number', $cuentaIb)
                ->count();

            $ultimoMovimiento = InterbankingMovimiento::query()
                ->where('empresa_id', $empresaId)
                ->where('account_number', $cuentaIb)
                ->orderByDesc('process_date')
                ->orderByDesc('id')
                ->first(['process_date', 'synced_at', 'bank_number', 'currency', 'amount', 'code_description_ib']);

            $bankNumber = (string) ($saldoIb->bank_number ?? $ultimoMovimiento->bank_number ?? '');
        }

        $faltantes = [];
        if (! $cc) {
            $faltantes[] = 'Falta cuenta contable en cuentas de caja.';
        }
        if ($cuentaIb === '') {
            $faltantes[] = 'Falta cuenta Interbanking en cuentas de caja.';
        }
        if ($cuentaIb !== '' && $movimientosCount === 0 && ! $saldoIb) {
            $faltantes[] = 'Sin movimientos ni saldos Interbanking persistidos para esa cuenta.';
        }

        return [
            'ok' => true,
            'enganche_completo' => $faltantes === [],
            'faltantes' => $faltantes,
            'cuentacaja' => [
                'id' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'tipocuenta' => $cuenta->tipocuenta,
                'cbu' => $cuenta->cbu ?? '',
                'banco' => $cuenta->bancos?->nombre ?? '',
                'moneda' => $cuenta->monedas?->abreviatura ?? '',
                'empresa' => $cuenta->empresas?->nombre ?? 'Multiempresa',
            ],
            'contabilidad' => [
                'cuentacontable_id' => $cc?->id,
                'codigo' => $cc?->codigo ?? '',
                'codigo_fmt' => $codigoContable > 0
                    ? MayorPlanoCuentaSupport::formatearCodigoCuenta($codigoContable)
                    : '',
                'nombre' => $cc?->nombre ?? '',
                'origen_mayor' => 'Mayor analítico (ctamov + subdiario bridge Anita)',
            ],
            'interbanking' => [
                'account_number' => $cuentaIb,
                'bank_number' => $bankNumber,
                'cbu_en_cuentacaja' => $cuenta->cbu ?? '',
                'ultimo_saldo' => $saldoIb ? [
                    'fecha' => $saldoIb->fecha?->format('d/m/Y'),
                    'countable_balance' => (float) ($saldoIb->countable_balance ?? 0),
                    'current_operating_balance' => (float) ($saldoIb->current_operating_balance ?? 0),
                    'currency' => $saldoIb->currency ?? '',
                    'account_name' => $saldoIb->account_name ?? '',
                ] : null,
                'movimientos_persistidos' => $movimientosCount,
                'ultimo_movimiento' => $ultimoMovimiento ? [
                    'fecha' => $ultimoMovimiento->process_date?->format('d/m/Y'),
                    'synced_at' => $ultimoMovimiento->synced_at?->format('d/m/Y H:i'),
                    'concepto' => $ultimoMovimiento->code_description_ib ?? '',
                    'importe' => (float) ($ultimoMovimiento->amount ?? 0),
                    'moneda' => $ultimoMovimiento->currency ?? '',
                ] : null,
            ],
        ];
    }
}
