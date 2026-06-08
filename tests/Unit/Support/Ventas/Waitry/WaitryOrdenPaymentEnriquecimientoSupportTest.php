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

    public function test_fusionar_acceso_desde_pos_cuando_details_no_trae_table(): void
    {
        $details = [
            'orderId' => 200,
            'paid' => true,
            'payment' => ['type' => 'credit_card'],
        ];
        $pos = [
            'id' => 200,
            'table' => ['id' => 101066, 'layout' => ['id' => 32211], 'name' => 'Tomasso'],
            'tableId' => 101066,
            'layout' => 32211,
        ];

        $fusion = WaitryOrdenPaymentEnriquecimientoSupport::fusionarAccesoDesdePos($details, $pos);

        $this->assertSame(101066, (int) ($fusion['tableId'] ?? 0));
        $this->assertSame(101066, (int) ($fusion['table']['id'] ?? 0));
    }
}
