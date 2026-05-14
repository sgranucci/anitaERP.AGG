<?php

namespace App\Services\Caja;

use App\Models\Caja\InterbankingSaldoDiario;
use Carbon\Carbon;
use Throwable;

class InterbankingSaldoPersistenciaService
{
    /**
     * Graba día a día los saldos devueltos por el endpoint de balances (historical_balances
     * y, si no hay historial, una fila según row_date y balances actuales).
     * Idempotente: updateOrCreate por (empresa, cuenta, moneda, fecha).
     */
    public function persistirCuenta(int $empresaId, array $account): void
    {
        $account = $this->normalizarCuenta($account);
        $balances = is_array($account['balances'] ?? null) ? $account['balances'] : [];
        $historical = $account['historical_balances'] ?? null;
        if (! is_array($historical)) {
            $historical = [];
        }

        $rowDateStr = null;
        if (! empty($account['row_date'])) {
            try {
                $rowDateStr = Carbon::parse($account['row_date'])->toDateString();
            } catch (Throwable) {
                $rowDateStr = null;
            }
        }

        if ($historical !== []) {
            foreach ($historical as $day) {
                if (! is_array($day)) {
                    continue;
                }
                $fecha = $this->fechaDesdeOperacion($day['operation_date'] ?? null);
                if ($fecha === null) {
                    continue;
                }
                $this->guardarFila(
                    $empresaId,
                    $account,
                    $fecha,
                    $day,
                    $balances,
                    $rowDateStr
                );
            }

            return;
        }

        if ($rowDateStr !== null) {
            $this->guardarFila(
                $empresaId,
                $account,
                $rowDateStr,
                [
                    'total_debits' => 0,
                    'total_credits' => 0,
                    'day_balance' => $balances['current_operating_balance'] ?? 0,
                ],
                $balances,
                $rowDateStr
            );
        }
    }

    /**
     * @param  array<string, mixed>  $account
     * @return array<string, mixed>
     */
    private function normalizarCuenta(array $account): array
    {
        if (! isset($account['bank_number']) && isset($account['bankNumber'])) {
            $account['bank_number'] = $account['bankNumber'];
        }

        return $account;
    }

    private function fechaDesdeOperacion(mixed $operationDate): ?string
    {
        if ($operationDate === null || $operationDate === '') {
            return null;
        }
        try {
            return Carbon::parse($operationDate)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $dayRow  total_debits, total_credits, day_balance
     * @param  array<string, mixed>  $balances  bloque balances del snapshot
     */
    private function guardarFila(
        int $empresaId,
        array $account,
        string $fecha,
        array $dayRow,
        array $balances,
        ?string $rowDateStr
    ): void {
        $bankNumber = (string) ($account['bank_number'] ?? '');
        $accountNumber = (string) ($account['account_number'] ?? '');
        $currency = (string) ($account['currency'] ?? '');

        $esDiaSnapshot = ($rowDateStr !== null && $fecha === $rowDateStr);

        $attrs = [
            'total_debits' => (float) ($dayRow['total_debits'] ?? 0),
            'total_credits' => (float) ($dayRow['total_credits'] ?? 0),
            'day_balance' => (float) ($dayRow['day_balance'] ?? 0),
            'account_name' => isset($account['account_name']) ? (string) $account['account_name'] : null,
            'account_type' => isset($account['account_type']) ? (string) $account['account_type'] : null,
            'account_label' => isset($account['account_label']) ? (string) $account['account_label'] : null,
        ];

        if ($esDiaSnapshot) {
            $attrs['countable_balance'] = isset($balances['countable_balance']) ? (float) $balances['countable_balance'] : null;
            $attrs['initial_operating_balance'] = isset($balances['initial_operating_balance']) ? (float) $balances['initial_operating_balance'] : null;
            $attrs['current_operating_balance'] = isset($balances['current_operating_balance']) ? (float) $balances['current_operating_balance'] : null;
            $attrs['projected_balance_24hs'] = isset($balances['projected_balance_24hs']) ? (float) $balances['projected_balance_24hs'] : null;
            $attrs['projected_balance_48hs'] = isset($balances['projected_balance_48hs']) ? (float) $balances['projected_balance_48hs'] : null;
        }

        InterbankingSaldoDiario::updateOrCreate(
            [
                'empresa_id' => $empresaId,
                'bank_number' => $bankNumber,
                'account_number' => $accountNumber,
                'currency' => $currency,
                'fecha' => $fecha,
            ],
            $attrs
        );
    }
}
