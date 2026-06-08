<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryPaymentGatewaySupport;
use App\Support\Ventas\Waitry\WaitryPaymentPayloadSupport;
use App\Support\Ventas\Waitry\WaitryPaymentTypeSupport;
use InvalidArgumentException;
use Tests\TestCase;

final class WaitryPaymentPayloadSupportTest extends TestCase
{
    private function support(): WaitryPaymentPayloadSupport
    {
        return new WaitryPaymentPayloadSupport(
            new WaitryPaymentTypeSupport,
            new WaitryPaymentGatewaySupport,
        );
    }

    public function test_armar_bloque_push_externo_usa_interface_y_payments(): void
    {
        config([
            'waitry.tipo_pago_cuentacaja' => ['mercadopago' => 201, 'cash' => 999],
        ]);

        $bloque = $this->support()->armarBloquePayment(
            [
                ['cuentacaja_id' => 201, 'moneda_id' => 1, 'monto' => 150.0],
            ],
            1,
            pagoOrdenExternaPush: true,
        );

        $this->assertSame('interface', $bloque['type']);
        $this->assertSame(150.0, $bloque['total_fee']['amount']);
        $this->assertCount(1, $bloque['payments']);
        $this->assertSame('MERCADOPAGO', $bloque['payments'][0]['gateway']);
        $this->assertSame(150.0, $bloque['payments'][0]['amount']);
    }

    public function test_armar_bloque_sync_mantiene_tipo_medio_real(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => ['mercadopago' => 201]]);

        $bloque = $this->support()->armarBloquePayment(
            [['cuentacaja_id' => 201, 'moneda_id' => 1, 'monto' => 80.0]],
            1,
            pagoOrdenExternaPush: false,
        );

        $this->assertSame('mercadopago', $bloque['type']);
        $this->assertArrayNotHasKey('payments', $bloque);
    }

    public function test_monto_total_pagado_suma_varios_medios(): void
    {
        $total = $this->support()->montoTotalPagado([
            ['cuentacaja_id' => 1, 'moneda_id' => 1, 'monto' => 100.0],
            ['cuentacaja_id' => 2, 'moneda_id' => 1, 'monto' => 25.5],
        ]);

        $this->assertSame(125.5, $total);
    }

    public function test_armar_bloque_rechaza_monto_cero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->support()->armarBloquePayment(
            [['cuentacaja_id' => 1, 'moneda_id' => 1, 'monto' => 0.0]],
            1,
        );
    }
}
