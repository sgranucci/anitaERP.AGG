<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Models\Ventas\CuentaGastronomia;
use App\Support\Ventas\Waitry\WaitryPaymentPayloadSupport;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Tras facturar una orden importada desde Waitry:
 * 1) syncStatusPOS — registra el cobro en Waitry
 * 2) updateexternal — mueve el estado en KDS (Solicitada → Aceptado)
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
    ) {
    }

    /**
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

        if (! $this->authService->credencialesCompletas()) {
            return [
                'ok' => false,
                'mensaje' => 'Waitry: faltan credenciales de API en configuración.',
            ];
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
            $payload = $this->armarPayloadSyncPago($waitryOrderId, $mediosPago, $empresaId, $placeId);
        } catch (InvalidArgumentException $e) {
            Log::warning('waitry.sync_status_pos.payload_invalido', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuenta->id,
                'msg' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        $sync = $this->postYValidarOk(
            (string) config('waitry.sync_status_pos_url'),
            $payload,
            'sync_status_pos',
            $waitryOrderId,
            (int) $cuenta->id,
            $placeId,
        );
        if (! ($sync['ok'] ?? false)) {
            return $sync;
        }

        Log::info('waitry.sync_status_pos.ok', [
            'waitry_order_id' => $waitryOrderId,
            'cuenta_id' => $cuenta->id,
            'place_id' => $placeId,
            'payment_type' => $payload['payment']['type'] ?? null,
        ]);

        $evento = (string) config('waitry.sync_status_pos_event', 'accepted');
        $kds = $this->actualizarEstadoKds($waitryOrderId, $placeId, $evento, (int) $cuenta->id);
        if (! ($kds['ok'] ?? false)) {
            return $kds;
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,mensaje?:string}
     */
    private function actualizarEstadoKds(
        int $waitryOrderId,
        int $placeId,
        string $evento,
        int $cuentaId,
    ): array {
        $url = trim((string) config('waitry.update_order_status_url', ''));
        if ($url === '') {
            return ['ok' => true];
        }

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
            ];
        }

        Log::info('waitry.update_order_status.ok', [
            'waitry_order_id' => $waitryOrderId,
            'cuenta_id' => $cuentaId,
            'place_id' => $placeId,
            'event' => $evento,
        ]);

        return ['ok' => true];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool,mensaje?:string}
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

        if (! ($resultado['ok'] ?? false)) {
            $error = 'Waitry: '.($resultado['error'] ?? ('error en '.$operacion));
            Log::error('waitry.'.$operacion.'.fallo', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuentaId,
                'place_id' => $placeId,
                'http' => $resultado['http_code'] ?? null,
                'error' => $resultado['error'] ?? null,
                'data' => $resultado['data'] ?? null,
            ]);

            return ['ok' => false, 'mensaje' => $error];
        }

        $data = is_array($resultado['data'] ?? null) ? $resultado['data'] : [];
        if (array_key_exists('ok', $data) && $data['ok'] === false) {
            $msgApi = trim((string) ($data['msg'] ?? $data['message'] ?? ($operacion.' rechazado')));
            Log::error('waitry.'.$operacion.'.rechazado', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuentaId,
                'place_id' => $placeId,
                'msg' => $msgApi,
                'data' => $data,
            ]);

            return [
                'ok' => false,
                'mensaje' => 'Waitry: '.$msgApi,
            ];
        }

        return ['ok' => true];
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
