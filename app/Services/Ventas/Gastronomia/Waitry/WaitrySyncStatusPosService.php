<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Models\Ventas\CuentaGastronomia;
use App\Support\Ventas\Waitry\WaitryPaymentPayloadSupport;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Notifica a Waitry el cobro de una orden importada (syncStatusPOS).
 *
 * @see POST /interface/interface/syncStatusPOS
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

        try {
            $payload = $this->armarPayload($waitryOrderId, $mediosPago, $empresaId);
        } catch (InvalidArgumentException $e) {
            Log::warning('waitry.sync_status_pos.payload_invalido', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuenta->id,
                'msg' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        $url = (string) config('waitry.sync_status_pos_url');
        $resultado = $this->httpClient->postJson($url, $payload, 'sync_status_pos');

        if (! ($resultado['ok'] ?? false)) {
            $error = 'Waitry pago: '.($resultado['error'] ?? 'error al sincronizar estado POS.');
            Log::error('waitry.sync_status_pos.fallo', [
                'waitry_order_id' => $waitryOrderId,
                'cuenta_id' => $cuenta->id,
                'http' => $resultado['http_code'] ?? null,
                'error' => $resultado['error'] ?? null,
            ]);

            return ['ok' => false, 'mensaje' => $error];
        }

        Log::info('waitry.sync_status_pos.ok', [
            'waitry_order_id' => $waitryOrderId,
            'cuenta_id' => $cuenta->id,
            'payment_type' => $payload['payment']['type'] ?? null,
        ]);

        return ['ok' => true];
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array<string, mixed>
     */
    private function armarPayload(int $waitryOrderId, array $mediosPago, int $empresaId): array
    {
        $payment = $this->paymentPayloadSupport->armarBloquePayment($mediosPago, $empresaId);

        return [
            'order_id' => $waitryOrderId,
            'event' => (string) config('waitry.sync_status_pos_event', 'accepted'),
            'paid' => true,
            'totalPaid' => $this->paymentPayloadSupport->montoTotalPagado($mediosPago),
            'payment' => $payment,
        ];
    }
}
