<?php

namespace App\Services\Caja;

use App\Models\Caja\InterbankingTransferencia;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Throwable;

class InterbankingTransferenciaPersistenciaService
{
    public function dedupeHash(int $empresaId, array $t): string
    {
        $payload = implode('|', [
            $empresaId,
            $this->scalarDedupe($t['transfer_id'] ?? null),
            $this->scalarDedupe($t['request_date'] ?? null),
            $this->scalarDedupe($t['validation_code'] ?? null),
            $this->scalarDedupe($t['amount'] ?? null),
            $this->scalarDedupe($t['debit_account'] ?? null),
            $this->scalarDedupe($t['credit_account'] ?? null),
        ]);

        return hash('sha256', $payload);
    }

    private function scalarDedupe(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return $this->jsonEstable($value);
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function jsonEstable(array $data): string
    {
        $this->ordenarClavesRecursivo($data);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        return is_string($json) ? $json : '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ordenarClavesRecursivo(array &$data): void
    {
        ksort($data);
        foreach ($data as &$v) {
            if (is_array($v)) {
                $this->ordenarClavesRecursivo($v);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cuentaTransferenciaJson(mixed $cuenta): ?array
    {
        if (! is_array($cuenta) || $cuenta === []) {
            return null;
        }

        return $cuenta;
    }

    private function cuentaTransferenciaEtiqueta(mixed $cuenta): ?string
    {
        if ($cuenta === null || $cuenta === '') {
            return null;
        }
        if (is_scalar($cuenta)) {
            $s = (string) $cuenta;

            return $s === '' ? null : substr($s, 0, 64);
        }
        if (! is_array($cuenta)) {
            return null;
        }

        $numero = $cuenta['account_number']
            ?? $cuenta['accountNumber']
            ?? $cuenta['number']
            ?? null;
        if ($numero === null || $numero === '') {
            $json = $this->jsonEstable($cuenta);

            return $json === '' ? null : substr($json, 0, 64);
        }

        $banco = $cuenta['bank_number'] ?? $cuenta['bankNumber'] ?? null;
        $etiqueta = $banco !== null && $banco !== ''
            ? (string) $banco.'-'.(string) $numero
            : (string) $numero;

        return substr($etiqueta, 0, 64);
    }

    private function importeTransferencia(mixed $amount): float
    {
        if (is_array($amount)) {
            $v = $amount['value'] ?? $amount['amount'] ?? 0;

            return (float) $v;
        }

        return (float) ($amount ?? 0);
    }

    /**
     * @param  array{
     *     debit_bank_number?: string|null,
     *     debit_account_number?: string|null,
     *     debit_account_type?: string|null,
     *     debit_currency?: string|null
     * }  $filtroDebito
     * @param  array<int, array<string, mixed>>  $filasApi
     */
    public function persistirLote(
        int $empresaId,
        array $filtroDebito,
        array $filasApi,
        ?Carbon $syncedAt = null,
        bool $omitirValidacionCierre = false
    ): int {
        $syncedAt = $syncedAt ?? now();
        $guardados = 0;

        foreach ($filasApi as $t) {
            if (! is_array($t)) {
                continue;
            }

            $requestDate = null;
            if (! empty($t['request_date'])) {
                try {
                    $requestDate = Carbon::parse($t['request_date']);
                } catch (Throwable) {
                    $requestDate = null;
                }
            }

            if (! $omitirValidacionCierre && $requestDate !== null) {
                PeriodoContableCierreSupport::assertOperacionPermitida(
                    $empresaId,
                    $requestDate->format('Y-m-d'),
                    PeriodoContableCierreSupport::ALCANCE_INTERBANKING
                );
            }

            $hash = $this->dedupeHash($empresaId, $t);

            $afip = $t['afip'] ?? null;
            if ($afip !== null && ! is_array($afip)) {
                $afip = null;
            }

            InterbankingTransferencia::updateOrCreate(
                ['dedupe_hash' => $hash],
                [
                    'empresa_id' => $empresaId,
                    'debit_bank_number' => $filtroDebito['debit_bank_number'] ?? null,
                    'debit_account_number' => $filtroDebito['debit_account_number'] ?? null,
                    'debit_account_type' => $filtroDebito['debit_account_type'] ?? null,
                    'debit_currency' => $filtroDebito['debit_currency'] ?? null,
                    'request_date' => $requestDate,
                    'transfer_type_description' => $t['transfer_type_description'] ?? null,
                    'transfer_type_code' => $t['transfer_type_code'] ?? null,
                    'transfer_id' => $t['transfer_id'] ?? null,
                    'network_number' => $t['network_number'] ?? null,
                    'amount' => $this->importeTransferencia($t['amount'] ?? 0),
                    'currency' => $t['currency'] ?? null,
                    'debit_account' => $this->cuentaTransferenciaEtiqueta($t['debit_account'] ?? null),
                    'debit_account_json' => $this->cuentaTransferenciaJson($t['debit_account'] ?? null),
                    'credit_account' => $this->cuentaTransferenciaEtiqueta($t['credit_account'] ?? null),
                    'credit_account_json' => $this->cuentaTransferenciaJson($t['credit_account'] ?? null),
                    'validation_code' => $t['validation_code'] ?? null,
                    'afip_json' => $afip,
                    'synced_at' => $syncedAt,
                ]
            );
            $guardados++;
        }

        return $guardados;
    }

    /**
     * @param  array{
     *     debit_account_number?: string|null,
     *     debit_account_type?: string|null,
     *     debit_bank_number?: string|null,
     *     debit_currency?: string|null,
     *     credit_account_number?: string|null,
     *     credit_account_type?: string|null,
     *     credit_bank_number?: string|null,
     *     credit_currency?: string|null
     * }  $filtrosApi
     * @return array{ok: bool, filas_guardadas: int, paginas: int, error: string|null}
     */
    public function sincronizarDesdeApi(
        InterbankingService $interbankingService,
        int $empresaId,
        array $filtrosApi,
        ?string $dateSince,
        ?string $dateUntil,
        int $pageSize = 100,
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

        $filtroDebito = [
            'debit_bank_number' => $filtrosApi['debit_bank_number'] ?? null,
            'debit_account_number' => $filtrosApi['debit_account_number'] ?? null,
            'debit_account_type' => $filtrosApi['debit_account_type'] ?? null,
            'debit_currency' => $filtrosApi['debit_currency'] ?? null,
        ];

        $paramsBase = array_merge($filtrosApi, [
            'date_since' => $dateSince,
            'date_until' => $dateUntil,
        ]);

        for ($page = 0; $page < $maxPaginas; $page++) {
            $res = $interbankingService->leeTransferencias($empresaId, array_merge($paramsBase, [
                'limit' => $pageSize,
                'page' => $page,
            ]));

            if (empty($res['ok'])) {
                return [
                    'ok' => false,
                    'filas_guardadas' => $filasTotales,
                    'paginas' => $paginas,
                    'error' => $res['error'] ?? 'Error desconocido al consultar la API.',
                ];
            }

            $filas = $res['transfers'] ?? [];
            if (! is_array($filas) || $filas === []) {
                break;
            }

            $filasTotales += $this->persistirLote($empresaId, $filtroDebito, $filas, $syncedAt, $omitirValidacionCierre);
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

    /**
     * Sincroniza transferencias de todas las empresas con `customer_id` en config (sin filtro por cuenta).
     *
     * @return array{ok: bool, empresas_procesadas: int, filas_guardadas: int, errores: array<int, string>}
     */
    public function sincronizarEmpresasConfiguradas(
        InterbankingService $interbankingService,
        int $diasVentana = 14
    ): array {
        $customerIds = config('interbanking.customer_id');
        if (! is_array($customerIds) || $customerIds === []) {
            return [
                'ok' => false,
                'empresas_procesadas' => 0,
                'filas_guardadas' => 0,
                'errores' => [0 => 'interbanking.customer_id no configurado.'],
            ];
        }

        [$dateSince, $dateUntil] = $this->rangoFechasSincronizacion($diasVentana);

        $empresasProcesadas = 0;
        $filasTotales = 0;
        $errores = [];

        foreach ($customerIds as $idx => $_customerId) {
            $empresaId = (int) $idx + 1;

            $resultado = $this->sincronizarDesdeApi(
                $interbankingService,
                $empresaId,
                [],
                $dateSince,
                $dateUntil,
                100,
                80,
                true
            );

            if (empty($resultado['ok'])) {
                $errores[$empresaId] = $resultado['error'] ?? 'Error desconocido al sincronizar transferencias.';

                continue;
            }

            $empresasProcesadas++;
            $filasTotales += (int) ($resultado['filas_guardadas'] ?? 0);
        }

        return [
            'ok' => $errores === [],
            'empresas_procesadas' => $empresasProcesadas,
            'filas_guardadas' => $filasTotales,
            'errores' => $errores,
        ];
    }

    /**
     * Ventana de consulta API (incluye el día de hoy).
     *
     * @return array{0: string, 1: string} dateSince y dateUntil en formato Y-m-d
     */
    public function rangoFechasSincronizacion(int $diasVentana): array
    {
        $diasVentana = max(1, min(60, $diasVentana));
        $dateUntil = Carbon::today();
        $dateSince = $dateUntil->copy()->subDays($diasVentana - 1);

        return [$dateSince->toDateString(), $dateUntil->toDateString()];
    }
}
