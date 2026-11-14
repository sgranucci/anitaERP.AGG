<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\InterbankingEnvio;
use App\Models\Caja\InterbankingEnvioLinea;
use App\Support\Caja\InterbankingCuentacajaParametrosSupport;
use App\Support\Compras\CbuSupport;
use App\Support\Compras\SolicitudRetiroCrownSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Preview + envío on-line de transferencias Interbanking a CBU.
 *
 * Fuentes: pagos ERP (OP + I/E) y CSV de solicitud de retiro (juego online).
 * Anti-duplicado: dedupe_key único en líneas con estado ok.
 */
class InterbankingEnvioTransferenciaService
{
    public function __construct(
        private readonly InterbankingArchivoPagoService $archivoPagoService,
        private readonly InterbankingService $interbankingService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{ok:bool,mensaje:string,filas:list<array<string,mixed>>,omitidas:list<array<string,mixed>>,total_importe:float,cantidad:int,seleccionables:int,cuenta:?Cuentacaja}
     */
    public function previewProveedores(array $filtros): array
    {
        $base = $this->archivoPagoService->listarFilasErpParaEnvio($filtros);
        if (empty($base['ok'])) {
            return array_merge($base, ['seleccionables' => 0]);
        }

        $filas = [];
        foreach ($base['filas'] as $fila) {
            $filas[] = $this->enriquecerFilaProveedor($fila);
        }

        return [
            'ok' => true,
            'mensaje' => '',
            'filas' => $filas,
            'omitidas' => $base['omitidas'],
            'total_importe' => round(array_sum(array_map(
                static fn (array $f): float => empty($f['ya_enviado']) ? (float) $f['importe'] : 0.0,
                $filas
            )), 2),
            'cantidad' => count($filas),
            'seleccionables' => count(array_filter($filas, static fn (array $f): bool => empty($f['ya_enviado']))),
            'cuenta' => $base['cuenta'],
        ];
    }

    /**
     * @return array{ok:bool,mensaje:string,filas:list<array<string,mixed>>,omitidas:list<array<string,mixed>>,total_importe:float,cantidad:int,seleccionables:int,cuenta:?Cuentacaja}
     */
    public function previewSolicitudRetiro(int $empresaId, int $cuentacajaId, string $csv): array
    {
        $cuenta = $this->archivoPagoService->buscarCuentaOrigen($empresaId, $cuentacajaId);
        $valCbu = CbuSupport::validarConMensaje($cuenta ? (string) $cuenta->cbu : '');
        if ($cuenta === null || ! $valCbu['ok']) {
            return [
                'ok' => false,
                'mensaje' => 'Seleccione una cuenta de caja con CBU válido.',
                'filas' => [],
                'omitidas' => [],
                'total_importe' => 0.0,
                'cantidad' => 0,
                'seleccionables' => 0,
                'cuenta' => null,
            ];
        }

        $proc = SolicitudRetiroCrownSupport::procesar(
            $csv,
            $valCbu['cbu'],
            (int) date('Ymd'),
            1
        );

        $filas = [];
        foreach ($proc['filas'] as $fila) {
            if (($fila['destino'] ?? '') !== 'interbanking') {
                continue;
            }
            $operacion = (string) ($fila['operacion'] ?? '');
            $cbu = (string) ($fila['cbu'] ?? '');
            $importe = round((float) ($fila['monto'] ?? 0), 2);
            $dedupe = InterbankingEnvioLinea::dedupeKeySolicitudRetiro($operacion, $cbu, $importe);
            $ya = $this->dedupeKeysEnviados([$dedupe]);

            $filas[] = [
                'selection_key' => $dedupe,
                'origen_tipo' => InterbankingEnvioLinea::ORIGEN_SOLICITUD_RETIRO,
                'origen_id' => null,
                'origen_clave' => $operacion,
                'origen' => 'RETIRO',
                'beneficiario' => (string) ($fila['nombre'] ?? ''),
                'referencia' => $operacion !== '' ? 'Op. '.$operacion : 'Solicitud retiro',
                'cbu' => $cbu,
                'importe' => $importe,
                'concept' => trim((string) ($fila['nombre_archivo'] ?? $fila['nombre'] ?? 'Solicitud retiro')),
                'ya_enviado' => isset($ya[$dedupe]),
                'estado_csv' => (string) ($fila['estado'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'mensaje' => '',
            'filas' => $filas,
            'omitidas' => $proc['omitidas'],
            'total_importe' => round(array_sum(array_map(
                static fn (array $f): float => empty($f['ya_enviado']) ? (float) $f['importe'] : 0.0,
                $filas
            )), 2),
            'cantidad' => count($filas),
            'seleccionables' => count(array_filter($filas, static fn (array $f): bool => empty($f['ya_enviado']))),
            'cuenta' => $cuenta,
        ];
    }

    /**
     * @param  list<array<string,mixed>>  $filasSeleccionadas  filas del preview (completas)
     * @return array{ok:bool,mensaje:string,envio_id:?int,resultados:list<array<string,mixed>>,cantidad_ok:int,cantidad_error:int,cantidad_omitida:int}
     */
    public function enviarSeleccion(
        int $empresaId,
        int $cuentacajaId,
        string $fuente,
        array $filasSeleccionadas,
    ): array {
        if ($filasSeleccionadas === []) {
            return [
                'ok' => false,
                'mensaje' => 'No hay transferencias seleccionadas.',
                'envio_id' => null,
                'resultados' => [],
                'cantidad_ok' => 0,
                'cantidad_error' => 0,
                'cantidad_omitida' => 0,
            ];
        }

        $cuenta = $this->archivoPagoService->buscarCuentaOrigen($empresaId, $cuentacajaId);
        if ($cuenta === null) {
            return [
                'ok' => false,
                'mensaje' => 'Cuenta de caja inválida.',
                'envio_id' => null,
                'resultados' => [],
                'cantidad_ok' => 0,
                'cantidad_error' => 0,
                'cantidad_omitida' => 0,
            ];
        }

        $paramsDebito = InterbankingCuentacajaParametrosSupport::parametrosApiDesdeCuentacaja($cuenta, $empresaId);
        $debitAccount = trim((string) ($cuenta->cuenta_interbanking ?? ''));
        $debitCbu = CbuSupport::normalizar((string) ($cuenta->cbu ?? ''));
        if ($debitAccount === '' || $paramsDebito === null) {
            return [
                'ok' => false,
                'mensaje' => 'La cuenta de caja no tiene número Interbanking / banco configurado para la API.',
                'envio_id' => null,
                'resultados' => [],
                'cantidad_ok' => 0,
                'cantidad_error' => 0,
                'cantidad_omitida' => 0,
            ];
        }
        if (strlen($debitCbu) !== 22) {
            return [
                'ok' => false,
                'mensaje' => 'La cuenta de caja origen no tiene CBU válido (22 dígitos) para Confección Interbanking.',
                'envio_id' => null,
                'resultados' => [],
                'cantidad_ok' => 0,
                'cantidad_error' => 0,
                'cantidad_omitida' => 0,
            ];
        }

        $keys = [];
        foreach ($filasSeleccionadas as $fila) {
            $key = (string) ($fila['selection_key'] ?? '');
            if ($key !== '') {
                $keys[] = $key;
            }
        }
        $ya = $this->dedupeKeysEnviados($keys);

        try {
            $envio = InterbankingEnvio::query()->create([
                'empresa_id' => $empresaId,
                'cuentacaja_id' => (int) $cuenta->id,
                'usuario_id' => Auth::id(),
                'fuente' => $fuente,
                'debit_account_number' => $debitAccount,
                'debit_bank_number' => $paramsDebito['bank_number'],
                'debit_account_type' => $paramsDebito['account_type'],
                'debit_currency' => $paramsDebito['currency'],
                'cantidad_total' => count($filasSeleccionadas),
                'cantidad_ok' => 0,
                'cantidad_error' => 0,
                'total_importe' => 0,
                'estado' => 'parcial',
            ]);

            $ok = 0;
            $err = 0;
            $omit = 0;
            $totalOk = 0.0;
            $resultados = [];

            foreach ($filasSeleccionadas as $fila) {
                $selectionKey = (string) ($fila['selection_key'] ?? '');
                $cbu = CbuSupport::normalizar((string) ($fila['cbu'] ?? ''));
                $importe = round((float) ($fila['importe'] ?? 0), 2);
                $origenTipo = (string) ($fila['origen_tipo'] ?? '');
                $origenId = isset($fila['origen_id']) ? (int) $fila['origen_id'] : null;
                if ($origenId !== null && $origenId <= 0) {
                    $origenId = null;
                }
                $origenClave = (string) ($fila['origen_clave'] ?? '');
                $beneficiario = (string) ($fila['beneficiario'] ?? $fila['proveedor_nombre'] ?? '');
                $concept = (string) ($fila['concept'] ?? $fila['referencia'] ?? 'Transferencia');
                $referencia = (string) ($fila['referencia'] ?? '');

                if ($selectionKey === '' || strlen($cbu) !== 22 || $importe < 0.005) {
                    $err++;
                    $resultados[] = [
                        'selection_key' => $selectionKey,
                        'estado' => InterbankingEnvioLinea::ESTADO_ERROR,
                        'error' => 'Fila inválida (CBU/importe/clave).',
                    ];

                    continue;
                }

                if (isset($ya[$selectionKey])) {
                    $omit++;
                    InterbankingEnvioLinea::query()->create([
                        'interbanking_envio_id' => $envio->id,
                        'origen_tipo' => $origenTipo,
                        'origen_id' => $origenId,
                        'origen_clave' => $origenClave !== '' ? $origenClave : null,
                        'dedupe_key' => null,
                        'beneficiario' => $beneficiario,
                        'credit_cbu' => $cbu,
                        'amount' => $importe,
                        'concept' => $concept,
                        'referencia' => $referencia,
                        'estado' => InterbankingEnvioLinea::ESTADO_OMITIDO,
                        'error_mensaje' => 'Ya enviado anteriormente (anti-duplicado).',
                    ]);
                    $resultados[] = [
                        'selection_key' => $selectionKey,
                        'estado' => InterbankingEnvioLinea::ESTADO_OMITIDO,
                        'error' => 'Ya enviado anteriormente.',
                    ];

                    continue;
                }

                $confectionType = $fuente === InterbankingEnvio::FUENTE_SOLICITUD_RETIRO
                    ? 'third-party'
                    : 'supplier';

                $api = $this->interbankingService->crearTransferenciaACbu($empresaId, [
                    'confection_type' => $confectionType,
                    'debit_cbu' => $debitCbu,
                    'debit_account_number' => $debitAccount,
                    'debit_bank_number' => $paramsDebito['bank_number'],
                    'debit_account_type' => $paramsDebito['account_type'],
                    'debit_currency' => $paramsDebito['currency'],
                    'credit_cbu' => $cbu,
                    'amount' => $importe,
                    'concept' => $concept,
                    'confection_id' => $origenId ?: null,
                    'request_date' => date('Y-m-d'),
                ]);

                if (! empty($api['ok'])) {
                    $ok++;
                    $totalOk += $importe;
                    $ya[$selectionKey] = true;
                    try {
                        InterbankingEnvioLinea::query()->create([
                            'interbanking_envio_id' => $envio->id,
                            'origen_tipo' => $origenTipo,
                            'origen_id' => $origenId,
                            'origen_clave' => $origenClave !== '' ? $origenClave : null,
                            'dedupe_key' => $selectionKey,
                            'beneficiario' => $beneficiario,
                            'credit_cbu' => $cbu,
                            'amount' => $importe,
                            'concept' => $concept,
                            'referencia' => $referencia,
                            'estado' => InterbankingEnvioLinea::ESTADO_OK,
                            'http_code' => (int) ($api['http_code'] ?? 0) ?: null,
                            'api_response' => $api['data'] ?? null,
                        ]);
                    } catch (Throwable $dup) {
                        // Carrera: otro proceso grabó el mismo dedupe_key.
                        $ok--;
                        $totalOk -= $importe;
                        $omit++;
                        InterbankingEnvioLinea::query()->create([
                            'interbanking_envio_id' => $envio->id,
                            'origen_tipo' => $origenTipo,
                            'origen_id' => $origenId,
                            'origen_clave' => $origenClave !== '' ? $origenClave : null,
                            'dedupe_key' => null,
                            'beneficiario' => $beneficiario,
                            'credit_cbu' => $cbu,
                            'amount' => $importe,
                            'concept' => $concept,
                            'referencia' => $referencia,
                            'estado' => InterbankingEnvioLinea::ESTADO_OMITIDO,
                            'http_code' => (int) ($api['http_code'] ?? 0) ?: null,
                            'api_response' => $api['data'] ?? null,
                            'error_mensaje' => 'Duplicado concurrente: '.$dup->getMessage(),
                        ]);
                        $resultados[] = [
                            'selection_key' => $selectionKey,
                            'estado' => InterbankingEnvioLinea::ESTADO_OMITIDO,
                            'error' => 'Ya enviado (duplicado concurrente).',
                        ];

                        continue;
                    }
                    $resultados[] = [
                        'selection_key' => $selectionKey,
                        'estado' => InterbankingEnvioLinea::ESTADO_OK,
                        'http_code' => $api['http_code'] ?? null,
                    ];
                } else {
                    $err++;
                    InterbankingEnvioLinea::query()->create([
                        'interbanking_envio_id' => $envio->id,
                        'origen_tipo' => $origenTipo,
                        'origen_id' => $origenId,
                        'origen_clave' => $origenClave !== '' ? $origenClave : null,
                        'dedupe_key' => null,
                        'beneficiario' => $beneficiario,
                        'credit_cbu' => $cbu,
                        'amount' => $importe,
                        'concept' => $concept,
                        'referencia' => $referencia,
                        'estado' => InterbankingEnvioLinea::ESTADO_ERROR,
                        'http_code' => (int) ($api['http_code'] ?? 0) ?: null,
                        'api_response' => $api['data'] ?? null,
                        'error_mensaje' => (string) ($api['error'] ?? 'Error API'),
                    ]);
                    $resultados[] = [
                        'selection_key' => $selectionKey,
                        'estado' => InterbankingEnvioLinea::ESTADO_ERROR,
                        'error' => (string) ($api['error'] ?? 'Error API'),
                        'http_code' => $api['http_code'] ?? null,
                    ];
                }
            }

            $estado = 'parcial';
            if ($ok > 0 && $err === 0 && $omit === 0) {
                $estado = 'completo';
            } elseif ($ok === 0 && $err > 0) {
                $estado = 'error';
            } elseif ($ok === 0 && $omit > 0 && $err === 0) {
                $estado = 'omitido';
            }

            $envio->update([
                'cantidad_ok' => $ok,
                'cantidad_error' => $err,
                'total_importe' => round($totalOk, 2),
                'estado' => $estado,
            ]);

            return [
                'ok' => $ok > 0 || ($omit > 0 && $err === 0),
                'mensaje' => sprintf(
                    'Envío #%d: %d ok, %d error, %d omitidas (duplicado).',
                    $envio->id,
                    $ok,
                    $err,
                    $omit
                ),
                'envio_id' => (int) $envio->id,
                'resultados' => $resultados,
                'cantidad_ok' => $ok,
                'cantidad_error' => $err,
                'cantidad_omitida' => $omit,
            ];
        } catch (Throwable $e) {
            Log::warning('Interbanking envío transferencias: excepción', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'mensaje' => 'No se pudo completar el envío: '.$e->getMessage(),
                'envio_id' => null,
                'resultados' => [],
                'cantidad_ok' => 0,
                'cantidad_error' => 0,
                'cantidad_omitida' => 0,
            ];
        }
    }

    /**
     * @param  array<string,mixed>  $fila
     * @return array<string,mixed>
     */
    private function enriquecerFilaProveedor(array $fila): array
    {
        $pagoproveedorId = (int) ($fila['pagoproveedor_id'] ?? 0);
        $cajaMovimientoId = (int) ($fila['caja_movimiento_id'] ?? 0);

        if ($pagoproveedorId > 0) {
            $origenTipo = InterbankingEnvioLinea::ORIGEN_PAGOPROVEEDOR;
            $origenId = $pagoproveedorId;
            $dedupe = InterbankingEnvioLinea::dedupeKeyPagoproveedor($pagoproveedorId);
        } elseif ($cajaMovimientoId > 0) {
            $origenTipo = InterbankingEnvioLinea::ORIGEN_CAJA_MOVIMIENTO;
            $origenId = $cajaMovimientoId;
            $dedupe = InterbankingEnvioLinea::dedupeKeyCajaMovimiento($cajaMovimientoId);
        } else {
            $origenTipo = 'desconocido';
            $origenId = null;
            $dedupe = 'tmp:'.md5(json_encode($fila));
        }

        $ya = $this->dedupeKeysEnviados([$dedupe]);

        return array_merge($fila, [
            'selection_key' => $dedupe,
            'origen_tipo' => $origenTipo,
            'origen_id' => $origenId,
            'origen_clave' => null,
            'beneficiario' => (string) ($fila['proveedor_nombre'] ?? ''),
            'concept' => (string) ($fila['referencia'] ?? ''),
            'ya_enviado' => isset($ya[$dedupe]),
        ]);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string,true>
     */
    private function dedupeKeysEnviados(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys, static fn ($k) => is_string($k) && $k !== '')));
        if ($keys === []) {
            return [];
        }

        return InterbankingEnvioLinea::query()
            ->where('estado', InterbankingEnvioLinea::ESTADO_OK)
            ->whereIn('dedupe_key', $keys)
            ->pluck('dedupe_key')
            ->mapWithKeys(static fn ($k) => [(string) $k => true])
            ->all();
    }
}
