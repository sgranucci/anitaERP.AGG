<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use App\Models\Configuracion\Moneda;
use App\Models\Ventas\CuentaGastronomia;
use App\Support\Ventas\Waitry\WaitryPaymentTypeSupport;
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
        private readonly WaitryPaymentTypeSupport $paymentTypeSupport,
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
        $tipoPago = $this->paymentTypeSupport->resolverDesdeMediosPago($mediosPago, $empresaId);
        $monto = $this->paymentTypeSupport->montoTotalMedioPrincipal($mediosPago);
        if ($monto <= 0.) {
            throw new InvalidArgumentException('Waitry: el monto del pago debe ser mayor a cero.');
        }

        $monedaId = $this->paymentTypeSupport->monedaIdMedioPrincipal($mediosPago);
        $currencyCode = $this->codigoMonedaWaitry($monedaId);

        return [
            'order_id' => $waitryOrderId,
            'event' => (string) config('waitry.sync_status_pos_event', 'accepted'),
            'paid' => true,
            'payment' => [
                'total_fee' => [
                    'amount' => $monto,
                    'currency_code' => $currencyCode,
                    'formatted_amount' => $this->formatearMonto($monto, $currencyCode),
                ],
                'type' => $tipoPago,
            ],
        ];
    }

    private function codigoMonedaWaitry(int $monedaId): string
    {
        if ($monedaId <= 0) {
            return 'ARS';
        }

        $abrev = Moneda::query()->whereKey($monedaId)->value('abreviatura');
        $codigo = strtoupper(trim((string) ($abrev ?? '')));
        if ($codigo === '') {
            return 'ARS';
        }

        if (strlen($codigo) > 3) {
            if (str_contains($codigo, 'USD') || str_contains($codigo, 'U$S') || str_contains($codigo, 'US$')) {
                return 'USD';
            }
            if (str_contains($codigo, 'ARS') || str_contains($codigo, '$')) {
                return 'ARS';
            }
        }

        return $codigo;
    }

    private function formatearMonto(float $monto, string $currencyCode): string
    {
        $simbolo = match (strtoupper($currencyCode)) {
            'USD' => '$',
            'EUR' => '€',
            default => '$',
        };

        return $simbolo.number_format($monto, 2, '.', '');
    }
}
