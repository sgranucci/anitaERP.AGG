<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryPaymentGatewaySupport;
use PHPUnit\Framework\TestCase;

final class WaitryPaymentGatewaySupportTest extends TestCase
{
    public function test_posnet_sin_payments(): void
    {
        $orden = [
            'payment' => [
                'type' => 'credit_card',
                'total_fee' => ['amount' => 100],
            ],
        ];

        $this->assertNull(WaitryPaymentGatewaySupport::extraerGatewayDesdeOrden($orden));
        $this->assertTrue(WaitryPaymentGatewaySupport::esGatewayPosnetKiosko(null));
        $this->assertFalse(WaitryPaymentGatewaySupport::esGatewayQrKiosko(null));
    }

    public function test_qr_kiosk_mpqr(): void
    {
        $orden = [
            'payment' => [
                'type' => 'credit_card',
                'payments' => [['gateway' => 'KIOSK MPQR', 'amount' => 50]],
            ],
        ];

        $gw = WaitryPaymentGatewaySupport::extraerGatewayDesdeOrden($orden);
        $this->assertSame('KIOSK MPQR', $gw);
        $this->assertTrue(WaitryPaymentGatewaySupport::esGatewayQrKiosko($gw));
        $this->assertFalse(WaitryPaymentGatewaySupport::esGatewayPosnetKiosko($gw));
    }

    public function test_posnet_kiosk_mp(): void
    {
        $gw = 'KIOSK MP';
        $this->assertTrue(WaitryPaymentGatewaySupport::esGatewayPosnetKiosko($gw));
        $this->assertFalse(WaitryPaymentGatewaySupport::esGatewayQrKiosko($gw));
    }

    public function test_orden_push_erp_por_prefijo_e(): void
    {
        $this->assertTrue(WaitryPaymentGatewaySupport::esOrdenPushErp([
            'display_id' => 'E-26BADA',
            'waitry_tipo_pago' => 'credit_card',
        ]));
    }

    public function test_interface_sin_facturar_no_es_push_erp(): void
    {
        $this->assertFalse(WaitryPaymentGatewaySupport::esOrdenPushErp([
            'payment' => ['type' => 'interface', 'payments' => [['gateway' => 'TOTALCOIN', 'amount' => 40]]],
            'waitry_tipo_pago' => 'interface',
        ]));
    }

    public function test_interface_facturada_anita_si_es_push_erp(): void
    {
        $this->assertTrue(WaitryPaymentGatewaySupport::esOrdenPushErp([
            'payment' => ['type' => 'interface'],
            'waitry_tipo_pago' => 'interface',
            'facturada_erp' => true,
        ]));
    }

    public function test_interface_facturada_totem_no_es_push_erp(): void
    {
        $this->assertFalse(WaitryPaymentGatewaySupport::esOrdenPushErp([
            'payment' => ['type' => 'interface'],
            'waitry_tipo_pago' => 'interface',
            'facturada_erp' => true,
            'anita_es_totem' => true,
        ]));
    }

    public function test_gateway_interface_facturada_es_push_erp(): void
    {
        $this->assertTrue(WaitryPaymentGatewaySupport::esOrdenPushErp([
            'payment' => [
                'type' => 'credit_card',
                'payments' => [['gateway' => 'interface', 'amount' => 9700]],
            ],
            'waitry_tipo_pago' => 'interface',
            'facturada_erp' => true,
        ]));
    }

    public function test_interface_qr_celular_facturada_no_es_push_erp(): void
    {
        $this->assertFalse(WaitryPaymentGatewaySupport::esOrdenPushErp([
            'payment' => [
                'type' => 'interface',
                'payments' => [['gateway' => 'TOTALCOIN', 'amount' => 40]],
            ],
            'waitry_tipo_pago' => 'interface',
            'facturada_erp' => true,
        ]));
    }

    public function test_es_gateway_cobro_externo_push_erp(): void
    {
        $this->assertTrue(WaitryPaymentGatewaySupport::esGatewayCobroExternoPushErp('interface'));
        $this->assertTrue(WaitryPaymentGatewaySupport::esGatewayCobroExternoPushErp('INTERFACE'));
        $this->assertFalse(WaitryPaymentGatewaySupport::esGatewayCobroExternoPushErp('CASH'));
        $this->assertFalse(WaitryPaymentGatewaySupport::esGatewayCobroExternoPushErp(null));
    }
}
