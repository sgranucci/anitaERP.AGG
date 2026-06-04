<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use App\Support\Ventas\Waitry\WaitryOrdenPaymentEnriquecimientoSupport;
use PHPUnit\Framework\TestCase;

final class WaitryOrdenPaymentEnriquecimientoSupportTest extends TestCase
{
    public function test_fusiona_payment_desde_pos_y_resuelve_tipo(): void
    {
        $details = [
            'orderId' => 100,
            'paid' => true,
            'totalAmount' => 1500.0,
        ];
        $pos = [
            'id' => 100,
            'payment' => [
                'type' => 'totalcoin',
                'total_fee' => ['amount' => 1500, 'currency_code' => 'ARS'],
            ],
        ];

        $this->assertTrue(WaitryOrdenPaymentEnriquecimientoSupport::ordenRequiereEnriquecimientoPayment($details));

        $fusion = WaitryOrdenPaymentEnriquecimientoSupport::fusionarPaymentDesdePos($details, $pos);

        $this->assertSame('totalcoin', WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($fusion));
        $this->assertFalse(WaitryOrdenPaymentEnriquecimientoSupport::ordenRequiereEnriquecimientoPayment($fusion));
    }

    public function test_enriquecer_mapa_ordenes(): void
    {
        $ordenes = [
            1 => ['orderId' => 1, 'paid' => true, 'totalAmount' => 100.0],
            2 => ['orderId' => 2, 'paid' => true, 'payment' => ['type' => 'mercadopago']],
        ];
        $mapPos = [
            1 => ['id' => 1, 'payment' => ['type' => 'mercadopago', 'total_fee' => ['amount' => 100]]],
        ];

        $out = WaitryOrdenPaymentEnriquecimientoSupport::enriquecerMapaOrdenes($ordenes, $mapPos);

        $this->assertSame('mercadopago', WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($out[1]));
        $this->assertSame('mercadopago', WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($out[2]));
    }
}
