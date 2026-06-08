<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Configuracion\Moneda;
use InvalidArgumentException;

/**
 * Bloque payment de Waitry (syncStatusPOS y pushExternalOrder).
 */
final class WaitryPaymentPayloadSupport
{
    public function __construct(
        private readonly WaitryPaymentTypeSupport $paymentTypeSupport,
        private readonly WaitryPaymentGatewaySupport $paymentGatewaySupport,
    ) {
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{
     *     type: string,
     *     total_fee: array{amount: float, currency_code: string, formatted_amount: string},
     *     payments?: list<array{gateway: string, amount: float}>
     * }
     */
    public function armarBloquePayment(array $mediosPago, int $empresaId, bool $pagoOrdenExternaPush = false): array
    {
        $monto = $this->paymentTypeSupport->montoTotalMedioPrincipal($mediosPago);
        if ($monto <= 0.) {
            throw new InvalidArgumentException('Waitry: el monto del pago debe ser mayor a cero.');
        }

        $monedaId = $this->paymentTypeSupport->monedaIdMedioPrincipal($mediosPago);
        $currencyCode = $this->codigoMonedaWaitry($monedaId);

        $bloque = [
            'total_fee' => [
                'amount' => $monto,
                'currency_code' => $currencyCode,
                'formatted_amount' => $this->formatearMonto($monto, $currencyCode),
            ],
            'type' => $pagoOrdenExternaPush
                ? WaitryPaymentTypeSupport::TIPO_INTERFACE
                : $this->paymentTypeSupport->resolverDesdeMediosPago($mediosPago, $empresaId),
        ];

        if ($pagoOrdenExternaPush) {
            $payments = $this->paymentGatewaySupport->armarPaymentsDesdeMediosPago($mediosPago, $empresaId);
            if ($payments !== []) {
                $bloque['payments'] = $payments;
            }
        }

        return $bloque;
    }

    /**
     * Suma de montos cobrados (para totalPaid en push/sync).
     *
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     */
    public function montoTotalPagado(array $mediosPago): float
    {
        $total = 0.0;
        foreach ($mediosPago as $medio) {
            if (! is_array($medio)) {
                continue;
            }
            $monto = (float) ($medio['monto'] ?? 0);
            if ($monto > 0.) {
                $total += $monto;
            }
        }

        return round($total, 2);
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
