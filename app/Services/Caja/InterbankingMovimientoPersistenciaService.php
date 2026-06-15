<?php

namespace App\Services\Caja;

use App\Models\Caja\InterbankingMovimiento;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Throwable;

class InterbankingMovimientoPersistenciaService
{
    /**
     * Identificador estable por fila API para upsert (evita duplicados al re-sincronizar).
     */
    public function dedupeHash(
        int $empresaId,
        string $accountNumber,
        string $bankNumber,
        string $currency,
        string $movementType,
        array $m
    ): string {
        $processDate = isset($m['process_date']) ? (string) $m['process_date'] : '';
        $voucher = array_key_exists('voucher_number', $m) && $m['voucher_number'] !== null
            ? (string) $m['voucher_number'] : '';
        $opIb = isset($m['operation_code_ib']) ? (string) $m['operation_code_ib'] : '';
        $amount = isset($m['amount']) ? (string) $m['amount'] : '';
        $dc = isset($m['debit_credit_type']) ? (string) $m['debit_credit_type'] : '';
        $cbu = isset($m['account_cbu']) ? (string) $m['account_cbu'] : '';

        $payload = implode('|', [
            $empresaId,
            $accountNumber,
            $bankNumber,
            $currency,
            $movementType,
            $processDate,
            $voucher,
            $opIb,
            $amount,
            $dc,
            $cbu,
        ]);

        return hash('sha256', $payload);
    }

    /**
     * @param  array<int, array<string, mixed>>  $filasApi
     */
    public function persistirLote(
        int $empresaId,
        string $bankNumber,
        string $accountNumber,
        string $accountType,
        string $currency,
        string $movementType,
        array $filasApi,
        ?Carbon $syncedAt = null,
        bool $omitirValidacionCierre = false
    ): int {
        $syncedAt = $syncedAt ?? now();
        $guardados = 0;

        foreach ($filasApi as $m) {
            if (! is_array($m)) {
                continue;
            }

            $processDate = null;
            if (! empty($m['process_date'])) {
                try {
                    $processDate = Carbon::parse($m['process_date']);
                } catch (Throwable) {
                    $processDate = null;
                }
            }

            if (! $omitirValidacionCierre && $processDate !== null) {
                PeriodoContableCierreSupport::assertOperacionPermitida(
                    $empresaId,
                    $processDate->format('Y-m-d'),
                    PeriodoContableCierreSupport::ALCANCE_INTERBANKING
                );
            }

            $hash = $this->dedupeHash($empresaId, $accountNumber, $bankNumber, $currency, $movementType, $m);

            InterbankingMovimiento::updateOrCreate(
                ['dedupe_hash' => $hash],
                [
                    'empresa_id' => $empresaId,
                    'bank_number' => $bankNumber,
                    'account_number' => $accountNumber,
                    'account_type' => $accountType !== '' ? $accountType : 'CC',
                    'currency' => $currency,
                    'movement_type' => $movementType,
                    'process_date' => $processDate,
                    'debit_credit_type' => strtoupper(substr((string) ($m['debit_credit_type'] ?? ''), 0, 1)),
                    'amount' => $m['amount'] ?? 0,
                    'operation_code_ib' => $m['operation_code_ib'] ?? null,
                    'operation_code_bank' => $m['operation_code_bank'] ?? null,
                    'code_description_ib' => $m['code_description_ib'] ?? null,
                    'code_description_bank' => $m['code_description_bank'] ?? null,
                    'customer_cuit' => $m['customer_cuit'] ?? null,
                    'depositor_code' => $m['depositor_code'] ?? null,
                    'depositor_description' => $m['depositor_description'] ?? null,
                    'voucher_number' => $m['voucher_number'] ?? null,
                    'account_cbu' => $m['account_cbu'] ?? null,
                    'grouping_code_ib' => $m['grouping_code_ib'] ?? null,
                    'branch_office_activity' => $m['branch_office_activity'] ?? null,
                    'synced_at' => $syncedAt,
                ]
            );
            $guardados++;
        }

        return $guardados;
    }

    /**
     * Descarga todas las páginas disponibles de la API y persiste (hasta límite de páginas).
     *
     * @return array{ok: bool, filas_guardadas: int, paginas: int, error: string|null}
     */
    public function sincronizarDesdeApi(
        InterbankingService $interbankingService,
        int $empresaId,
        string $accountNumber,
        string $bankNumber,
        string $accountType,
        string $currency,
        string $movementType,
        ?string $dateSince,
        ?string $dateUntil,
        int $pageSize = 200,
        int $maxPaginas = 80,
        bool $omitirValidacionCierre = false
    ): array {
        if (! $omitirValidacionCierre) {
            PeriodoContableCierreSupport::assertRangoOperacionPermitido(
                $empresaId,
                $dateSince,
                $dateUntil,
                PeriodoContableCierreSupport::ALCANCE_INTERBANKING
            );
        }

        $pageSize = max(1, min(500, $pageSize));
        $filasTotales = 0;
        $paginas = 0;
        $syncedAt = now();

        for ($page = 0; $page < $maxPaginas; $page++) {
            $res = $interbankingService->leeMovimientos($empresaId, $accountNumber, $movementType, [
                'bank_number' => $bankNumber,
                'account_type' => $accountType,
                'currency' => $currency,
                'date_since' => $dateSince,
                'date_until' => $dateUntil,
                'limit' => $pageSize,
                'page' => $page,
            ]);

            if (empty($res['ok'])) {
                return [
                    'ok' => false,
                    'filas_guardadas' => $filasTotales,
                    'paginas' => $paginas,
                    'error' => $res['error'] ?? 'Error desconocido al consultar la API.',
                ];
            }

            $filas = $res['movements_detail'] ?? [];
            if (! is_array($filas) || $filas === []) {
                break;
            }

            $filasTotales += $this->persistirLote(
                $empresaId,
                $bankNumber,
                $accountNumber,
                $accountType,
                $currency,
                $movementType,
                $filas,
                $syncedAt,
                $omitirValidacionCierre
            );
            $paginas++;

            $gen = $res['general_data'] ?? null;
            $totalRows = is_array($gen) && isset($gen['total_rows']) ? (int) $gen['total_rows'] : null;
            if ($totalRows !== null && ($page + 1) * $pageSize >= $totalRows) {
                break;
            }

            if (count($filas) < $pageSize) {
                break;
            }
        }

        return [
            'ok' => true,
            'filas_guardadas' => $filasTotales,
            'paginas' => $paginas,
            'error' => null,
        ];
    }
}
