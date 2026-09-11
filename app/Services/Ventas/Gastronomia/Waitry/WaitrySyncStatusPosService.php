<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\WaitrySyncStatusPos;
use App\Support\Ventas\Waitry\WaitryMediosPagoFromVentaSupport;
use App\Support\Ventas\Waitry\WaitryPaymentPayloadSupport;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Tras facturar una orden importada desde Waitry:
 * 1) syncStatusPOS — registra el cobro en Waitry
 * 2) updateexternal — mueve el estado en KDS (Solicitada → Aceptado).
 *    Apagado por defecto (`WAITRY_UPDATE_ORDER_STATUS_HABILITADO`); el código queda
 *    para reactivar si Waitry vuelve a pedirlo.
 *
 * El POS persiste y encola; el HTTP corre en worker (reintentos + cron de seguridad).
 *
 * @see POST /interface/interface/syncStatusPOS
 * @see POST /live/order/updateexternal
 */
final class WaitrySyncStatusPosService
{
    public function __construct(
        private readonly WaitryHttpClient $httpClient,
        private readonly WaitryAuthService $authService,
        private readonly WaitryPaymentPayloadSupport $paymentPayloadSupport,
        private readonly WaitrySyncStatusPosEnvioService $envioService,
        private readonly WaitryMediosPagoFromVentaSupport $mediosPagoFromVentaSupport,
    ) {
    }

    /**
     * Persiste el sync y lo manda a cola (no bloquea el POS ni el afterResponse).
     *
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{ok:bool,omitida?:bool,encolada?:bool,mensaje?:string}
     */
    public function encolarPagoTrasFactura(
        CuentaGastronomia $cuenta,
        array $mediosPago,
        int $ventaId,
    ): array {
        if (! config('waitry.habilitado', false)) {
            return ['ok' => true, 'omitida' => true];
        }

        $waitryOrderId = (int) ($cuenta->waitry_order_id ?? 0);
        if ($waitryOrderId <= 0 || $ventaId <= 0) {
            return ['ok' => true, 'omitida' => true];
        }

        if ($mediosPago === []) {
            $mediosPago = $this->mediosPagoFromVentaSupport->desdeVentaId($ventaId);
        }
        if ($mediosPago === []) {
            return ['ok' => true, 'omitida' => true];
        }

        $empresaId = (int) $cuenta->empresa_id;
        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return [
                'ok' => false,
                'mensaje' => 'Waitry: no hay placeId configurado para la empresa '.$empresaId.'.',
            ];
        }

        try {
            $this->armarPayloadSyncPago($waitryOrderId, $mediosPago, $empresaId, $placeId);
        } catch (InvalidArgumentException $e) {
            Log::warning('waitry.sync_status_pos.payload_invalido', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuenta->id,
                'venta_id' => $ventaId,
                'msg' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        try {
            $registro = $this->envioService->crearOReusarRegistro(
                $ventaId,
                (int) $cuenta->id,
                $waitryOrderId,
                $empresaId,
                $placeId,
                $mediosPago,
            );
        } catch (Throwable $e) {
            Log::error('waitry.sync_status_pos.persistir_fallo', [
                'venta_id' => $ventaId,
                'waitry_order_id' => $waitryOrderId,
                'msg' => $e->getMessage(),
            ]);

            return $this->sincronizarPagoTrasFactura($cuenta, $mediosPago);
        }

        if ($registro->estado === WaitrySyncStatusPos::ESTADO_ENVIADO) {
            return ['ok' => true];
        }

        if ($registro->estado === WaitrySyncStatusPos::ESTADO_OMITIDO) {
            return ['ok' => true, 'omitida' => true];
        }

        if ($this->envioService->colaRealDisponible()) {
            try {
                $this->envioService->encolarInmediato($registro);
            } catch (Throwable $e) {
                Log::error('waitry.sync_status_pos.dispatch_fallo', [
                    'registro_id' => $registro->id,
                    'venta_id' => $ventaId,
                    'msg' => $e->getMessage(),
                ]);
            }

            Log::info('waitry.sync_status_pos.encolado', [
                'registro_id' => $registro->id,
                'venta_id' => $ventaId,
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuenta->id,
            ]);

            return ['ok' => true, 'encolada' => true];
        }

        return $this->procesarRegistro($registro);
    }

    /**
     * HTTP síncrono (fallback sin cola / tests). Preferir {@see encolarPagoTrasFactura()}.
     *
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{ok:bool,omitida?:bool,mensaje?:string}
     */
    public function sincronizarPagoTrasFactura(
        CuentaGastronomia $cuenta,
        array $mediosPago,
    ): array {
        if (! config('waitry.habilitado', false)) {
            return ['ok' => true, 'omitida' => true];
        }

        $waitryOrderId = (int) ($cuenta->waitry_order_id ?? 0);
        if ($waitryOrderId <= 0) {
            return ['ok' => true, 'omitida' => true];
        }

        if ($mediosPago === []) {
            return ['ok' => true, 'omitida' => true];
        }

        $empresaId = (int) $cuenta->empresa_id;
        $placeId = $this->resolverPlaceId($empresaId);
        if ($placeId === null) {
            return [
                'ok' => false,
                'mensaje' => 'Waitry: no hay placeId configurado para la empresa '.$empresaId.'.',
            ];
        }

        $cuentaId = (int) $cuenta->id;
        $sync = $this->ejecutarSyncStatusPos($waitryOrderId, $mediosPago, $empresaId, $placeId, $cuentaId);
        if (! ($sync['ok'] ?? false)) {
            return $sync;
        }

        return $this->actualizarEstadoKds($waitryOrderId, $placeId, $cuentaId);
    }

    /**
     * @return array{ok:bool,omitida?:bool,mensaje?:string}
     */
    public function procesarRegistro(WaitrySyncStatusPos $registro): array
    {
        $registro->refresh();

        if ($registro->estado === WaitrySyncStatusPos::ESTADO_ENVIADO) {
            return ['ok' => true];
        }
        if ($registro->estado === WaitrySyncStatusPos::ESTADO_OMITIDO) {
            return ['ok' => true, 'omitida' => true];
        }
        if ($registro->estado === WaitrySyncStatusPos::ESTADO_AGOTADO) {
            return [
                'ok' => false,
                'mensaje' => (string) ($registro->ultimo_error ?: 'Waitry: reintentos de sync agotados.'),
            ];
        }

        if (! config('waitry.habilitado', false)) {
            $this->envioService->marcarOmitido($registro, 'Integración Waitry deshabilitada.');

            return ['ok' => true, 'omitida' => true];
        }

        if (! $this->authService->credencialesCompletas()) {
            $this->envioService->registrarFallo($registro, 'Waitry: faltan credenciales de API en configuración.');
            $this->envioService->encolarReintento($registro);

            return [
                'ok' => false,
                'mensaje' => 'Waitry: faltan credenciales de API en configuración.',
            ];
        }

        if ($registro->estado !== WaitrySyncStatusPos::ESTADO_ENVIANDO) {
            if (! $this->envioService->marcarEnviando($registro)) {
                $registro->refresh();
                if ($registro->estado === WaitrySyncStatusPos::ESTADO_ENVIADO) {
                    return ['ok' => true];
                }

                return ['ok' => true, 'omitida' => true];
            }
            $registro->refresh();
        }

        $mediosPago = $registro->mediosPagoPersistidos();
        if ($mediosPago === []) {
            $mediosPago = $this->mediosPagoFromVentaSupport->desdeVentaId((int) $registro->venta_id);
        }
        if ($mediosPago === []) {
            $this->envioService->marcarOmitido($registro, 'Waitry: sin medios de pago para syncStatusPOS.');

            return ['ok' => true, 'omitida' => true];
        }

        $waitryOrderId = (int) $registro->waitry_order_id;
        $placeId = (int) $registro->place_id;
        $empresaId = (int) $registro->empresa_id;
        $cuentaId = (int) ($registro->cuenta_gastronomia_id ?? 0);

        if (! $registro->sync_pos_ok) {
            $sync = $this->ejecutarSyncStatusPos($waitryOrderId, $mediosPago, $empresaId, $placeId, $cuentaId);
            if (! ($sync['ok'] ?? false)) {
                $this->envioService->registrarFallo(
                    $registro,
                    (string) ($sync['mensaje'] ?? 'Waitry: error en syncStatusPOS.'),
                    isset($sync['http_code']) ? (int) $sync['http_code'] : null,
                    is_array($sync['data'] ?? null) ? $sync['data'] : null,
                );
                $this->envioService->encolarReintento($registro);

                return $sync;
            }
            $this->envioService->marcarPasoOk(
                $registro,
                'sync_status_pos',
                is_array($sync['data'] ?? null) ? $sync['data'] : null,
            );
            $registro->refresh();
        }

        if (! $registro->kds_ok) {
            $kds = $this->actualizarEstadoKds($waitryOrderId, $placeId, $cuentaId);
            if (! ($kds['ok'] ?? false)) {
                $this->envioService->registrarFallo(
                    $registro,
                    (string) ($kds['mensaje'] ?? 'Waitry: error al actualizar KDS.'),
                    isset($kds['http_code']) ? (int) $kds['http_code'] : null,
                    is_array($kds['data'] ?? null) ? $kds['data'] : null,
                );
                $this->envioService->encolarReintento($registro);

                return $kds;
            }
            $this->envioService->marcarPasoOk(
                $registro,
                'update_order_status',
                is_array($kds['data'] ?? null) ? $kds['data'] : null,
            );
        }

        $this->envioService->marcarExito($registro);

        return ['ok' => true];
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{ok:bool,mensaje?:string,http_code?:int,data?:array|null}
     */
    private function ejecutarSyncStatusPos(
        int $waitryOrderId,
        array $mediosPago,
        int $empresaId,
        int $placeId,
        int $cuentaId,
    ): array {
        if (! $this->authService->credencialesCompletas()) {
            return [
                'ok' => false,
                'mensaje' => 'Waitry: faltan credenciales de API en configuración.',
            ];
        }

        try {
            $payload = $this->armarPayloadSyncPago($waitryOrderId, $mediosPago, $empresaId, $placeId);
        } catch (InvalidArgumentException $e) {
            Log::warning('waitry.sync_status_pos.payload_invalido', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuentaId,
                'msg' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        $sync = $this->postYValidarOk(
            (string) config('waitry.sync_status_pos_url'),
            $payload,
            'sync_status_pos',
            $waitryOrderId,
            $cuentaId,
            $placeId,
        );
        if (! ($sync['ok'] ?? false)) {
            return $sync;
        }

        Log::info('waitry.sync_status_pos.ok', [
            'waitry_order_id' => $waitryOrderId,
            'cuenta_id' => $cuentaId,
            'place_id' => $placeId,
            'payment_type' => $payload['payment']['type'] ?? null,
        ]);

        return $sync;
    }

    /**
     * @return array{ok:bool,mensaje?:string,http_code?:int,data?:array|null}
     */
    private function actualizarEstadoKds(
        int $waitryOrderId,
        int $placeId,
        int $cuentaId,
    ): array {
        if (! filter_var(config('waitry.update_order_status_habilitado', false), FILTER_VALIDATE_BOOLEAN)) {
            Log::info('waitry.update_order_status.omitido', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuentaId,
                'place_id' => $placeId,
                'motivo' => 'WAITRY_UPDATE_ORDER_STATUS_HABILITADO=false',
            ]);

            return ['ok' => true, 'omitida' => true];
        }

        $url = trim((string) config('waitry.update_order_status_url', ''));
        if ($url === '') {
            return ['ok' => true];
        }

        $evento = (string) config('waitry.sync_status_pos_event', 'accepted');
        $payload = [
            'placeId' => $placeId,
            'orderId' => $waitryOrderId,
            'event' => $evento,
        ];

        $resultado = $this->postYValidarOk(
            $url,
            $payload,
            'update_order_status',
            $waitryOrderId,
            $cuentaId,
            $placeId,
        );
        if (! ($resultado['ok'] ?? false)) {
            $mensaje = $resultado['mensaje'] ?? 'No se pudo actualizar el estado en el KDS Waitry.';

            return [
                'ok' => false,
                'mensaje' => $mensaje,
                'http_code' => $resultado['http_code'] ?? null,
                'data' => $resultado['data'] ?? null,
            ];
        }

        Log::info('waitry.update_order_status.ok', [
            'waitry_order_id' => $waitryOrderId,
            'cuenta_id' => $cuentaId,
            'place_id' => $placeId,
            'event' => $evento,
        ]);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,mensaje?:string,http_code?:int,data?:array|null}
     */
    private function postYValidarOk(
        string $url,
        array $payload,
        string $operacion,
        int $waitryOrderId,
        int $cuentaId,
        int $placeId,
    ): array {
        $resultado = $this->httpClient->postJson($url, $payload, $operacion);
        $data = is_array($resultado['data'] ?? null) ? $resultado['data'] : null;
        $httpCode = isset($resultado['http_code']) ? (int) $resultado['http_code'] : null;

        if (! ($resultado['ok'] ?? false)) {
            $error = 'Waitry: '.($resultado['error'] ?? ('error en '.$operacion));
            Log::error('waitry.'.$operacion.'.fallo', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuentaId,
                'place_id' => $placeId,
                'http' => $httpCode,
                'error' => $resultado['error'] ?? null,
                'data' => $data,
            ]);

            return [
                'ok' => false,
                'mensaje' => $error,
                'http_code' => $httpCode,
                'data' => $data,
            ];
        }

        $dataArr = $data ?? [];
        if (array_key_exists('ok', $dataArr) && $dataArr['ok'] === false) {
            $msgApi = trim((string) ($dataArr['msg'] ?? $dataArr['message'] ?? ($operacion.' rechazado')));
            Log::error('waitry.'.$operacion.'.rechazado', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuentaId,
                'place_id' => $placeId,
                'msg' => $msgApi,
                'data' => $dataArr,
            ]);

            return [
                'ok' => false,
                'mensaje' => 'Waitry: '.$msgApi,
                'http_code' => $httpCode,
                'data' => $dataArr,
            ];
        }

        return ['ok' => true, 'http_code' => $httpCode, 'data' => $data];
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array<string, mixed>
     */
    private function armarPayloadSyncPago(int $waitryOrderId, array $mediosPago, int $empresaId, int $placeId): array
    {
        $payment = $this->paymentPayloadSupport->armarBloquePayment($mediosPago, $empresaId);

        // Waitry (~sep 2026): placeId + orderId + event obligatorios (camelCase).
        return [
            'placeId' => $placeId,
            'orderId' => $waitryOrderId,
            'event' => (string) config('waitry.sync_status_pos_event', 'accepted'),
            'paid' => true,
            'totalPaid' => $this->paymentPayloadSupport->montoTotalPagado($mediosPago),
            'payment' => $payment,
        ];
    }

    private function resolverPlaceId(int $empresaId): ?int
    {
        $map = config('waitry.place_id_por_empresa', []);
        if (! is_array($map)) {
            return null;
        }

        $placeId = (int) ($map[$empresaId] ?? 0);

        return $placeId > 0 ? $placeId : null;
    }
}
