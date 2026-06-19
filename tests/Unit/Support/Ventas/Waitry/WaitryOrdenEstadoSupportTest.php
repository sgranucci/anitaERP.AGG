<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryOrdenEstadoSupport;
use Tests\TestCase;

final class WaitryOrdenEstadoSupportTest extends TestCase
{
    public function test_es_cancelada_desde_flag_waitry(): void
    {
        $this->assertTrue(WaitryOrdenEstadoSupport::esCancelada(['canceled' => true]));
        $this->assertFalse(WaitryOrdenEstadoSupport::esCancelada(['canceled' => false]));
    }

    public function test_es_cancelada_por_current_state_o_status(): void
    {
        $this->assertTrue(WaitryOrdenEstadoSupport::esCancelada([
            'orderId' => 100,
            'current_state' => 'cancelled',
        ]));
        $this->assertTrue(WaitryOrdenEstadoSupport::esCancelada([
            'orderId' => 101,
            'status' => 'anulada',
        ]));
        $this->assertFalse(WaitryOrdenEstadoSupport::esCancelada([
            'orderId' => 102,
            'current_state' => 'closed',
            'paid' => 1,
        ]));
    }

    public function test_es_cancelada_linea_usa_flag_o_estado(): void
    {
        $this->assertTrue(WaitryOrdenEstadoSupport::esCanceladaLinea([
            'waitry_cancelada' => true,
        ]));
        $this->assertTrue(WaitryOrdenEstadoSupport::esCanceladaLinea([
            'current_state' => 'rejected',
        ]));
        $this->assertFalse(WaitryOrdenEstadoSupport::esCanceladaLinea([
            'waitry_cancelada' => false,
            'paid_waitry' => true,
        ]));
    }

    public function test_es_cancelada_por_fragmento_en_estado(): void
    {
        $this->assertTrue(WaitryOrdenEstadoSupport::esCancelada([
            'orderId' => 200,
            'current_state' => 'ORDER_CANCELLED',
        ]));
        $this->assertTrue(WaitryOrdenEstadoSupport::esCancelada([
            'orderId' => 201,
            'cancelledAt' => '2026-06-01 12:00:00',
        ]));
    }

    public function test_filtrar_ordenes_activas_excluye_canceladas(): void
    {
        $filtro = WaitryOrdenEstadoSupport::filtrarOrdenesActivas([
            1 => ['orderId' => 1, 'current_state' => 'closed'],
            2 => ['orderId' => 2, 'current_state' => 'cancelled'],
        ]);

        $this->assertSame(1, $filtro['cantidad_excluidas']);
        $this->assertCount(1, $filtro['activas']);
        $this->assertArrayHasKey(1, $filtro['activas']);
    }

    public function test_separar_canceladas_excluye_de_activas(): void
    {
        $split = WaitryOrdenEstadoSupport::separarCanceladas([
            ['waitry_order_id' => 1, 'total' => 100.0, 'waitry_cancelada' => false],
            ['waitry_order_id' => 2, 'total' => 50.0, 'waitry_cancelada' => true],
        ]);

        $this->assertCount(1, $split['activas']);
        $this->assertCount(1, $split['canceladas']);
        $this->assertSame(1, $split['resumen']['cantidad']);
        $this->assertSame(50.0, $split['resumen']['total']);
    }

    public function test_es_anulada_por_descuento_total_cortesia_impaga(): void
    {
        $orden = [
            'orderId' => 17573854,
            'totalAmount' => 7800,
            'totalDiscount' => 0,
            'paid' => false,
        ];

        $this->assertTrue(WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotal($orden));
        $this->assertSame(0.0, WaitryOrdenEstadoSupport::montoNetoOperativo($orden));
        $this->assertSame(7800.0, WaitryOrdenEstadoSupport::montoDescuentoWaitry($orden));
    }

    public function test_total_discount_igual_total_amount_es_precio_pleno_sin_descuento(): void
    {
        $orden = [
            'orderId' => 17752108,
            'totalAmount' => 1300,
            'totalDiscount' => 1300,
            'paid' => false,
        ];

        $this->assertFalse(WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotal($orden));
        $this->assertSame(1300.0, WaitryOrdenEstadoSupport::montoNetoOperativo($orden));
        $this->assertSame(0.0, WaitryOrdenEstadoSupport::montoDescuentoWaitry($orden));
    }

    public function test_es_anulada_por_descuento_total_no_aplica_si_esta_cobrada(): void
    {
        $orden = [
            'totalAmount' => 100,
            'totalDiscount' => 100,
            'paid' => true,
        ];

        $this->assertFalse(WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotal($orden));
    }

    public function test_filtrar_ordenes_activas_excluye_anuladas_por_descuento(): void
    {
        $filtro = WaitryOrdenEstadoSupport::filtrarOrdenesActivas([
            1 => ['orderId' => 1, 'totalAmount' => 100, 'totalDiscount' => 0, 'paid' => false],
            2 => ['orderId' => 2, 'totalAmount' => 7800, 'totalDiscount' => 7800, 'paid' => false],
        ]);

        $this->assertCount(1, $filtro['activas']);
        $this->assertSame(1, $filtro['cantidad_anuladas_descuento_excluidas']);
        $this->assertSame(100.0, $filtro['waitry_anuladas_descuento']['total']);
        $this->assertArrayHasKey(2, $filtro['activas']);
    }

    public function test_separar_canceladas_excluye_anuladas_descuento(): void
    {
        $split = WaitryOrdenEstadoSupport::separarCanceladas([
            [
                'waitry_order_id' => 3,
                'total' => 7800,
                'paid_waitry' => false,
                'waitry_anulada_descuento' => true,
                'total_neto_waitry' => 0,
            ],
        ]);

        $this->assertCount(0, $split['activas']);
        $this->assertCount(1, $split['anuladas_descuento']);
        $this->assertSame(7800.0, $split['resumen_anuladas_descuento']['total']);
    }

    public function test_enriquecer_lineas_impagas_con_ordenes_waitry(): void
    {
        $lineas = [
            [
                'waitry_order_id' => 17573854,
                'total' => 7800.0,
                'paid_waitry' => false,
                'monto_cobro_waitry' => 0.0,
                'facturada_erp' => false,
            ],
            [
                'waitry_order_id' => 999,
                'total' => 100.0,
                'paid_waitry' => false,
                'total_discount_waitry' => 0.0,
                'total_neto_waitry' => 100.0,
                'waitry_anulada_descuento' => false,
            ],
        ];

        $ordenes = [
            17573854 => [
                'orderId' => 17573854,
                'totalAmount' => 7800,
                'totalDiscount' => 7800,
                'paid' => false,
            ],
        ];

        $enriquecidas = WaitryOrdenEstadoSupport::enriquecerLineasImpagasConOrdenes($lineas, $ordenes);

        $this->assertFalse($enriquecidas[0]['waitry_anulada_descuento']);
        $this->assertSame(0.0, $enriquecidas[0]['total_discount_waitry']);
        $this->assertSame(7800.0, $enriquecidas[0]['total_neto_waitry']);
        $this->assertFalse($enriquecidas[1]['waitry_anulada_descuento']);
    }

    public function test_linea_impaga_legacy_snapshot_sin_descuento_no_es_anulada(): void
    {
        $linea = [
            'waitry_order_id' => 17752108,
            'total' => 1300.0,
            'total_amount_waitry' => 1300.0,
            'total_discount_waitry' => 1300.0,
            'total_neto_waitry' => 0.0,
            'paid_waitry' => false,
            'monto_cobro_waitry' => 0.0,
            'waitry_anulada_descuento' => false,
        ];

        $this->assertFalse(WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($linea));
    }

    public function test_linea_cobrada_con_descuento_total_no_es_anulada_por_descuento(): void
    {
        $linea = [
            'waitry_order_id' => 17571551,
            'total' => 4900.0,
            'total_discount_waitry' => 4900.0,
            'total_neto_waitry' => 0.0,
            'paid_waitry' => true,
            'monto_cobro_waitry' => 4900.0,
            'waitry_anulada_descuento' => false,
        ];

        $this->assertFalse(WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($linea));
    }

    public function test_linea_impaga_con_descuento_total_si_es_anulada(): void
    {
        $linea = [
            'waitry_order_id' => 17573854,
            'total' => 7800.0,
            'total_amount_waitry' => 7800.0,
            'total_discount_waitry' => 0.0,
            'total_neto_waitry' => 0.0,
            'paid_waitry' => false,
            'monto_cobro_waitry' => 0.0,
            'waitry_anulada_descuento' => true,
        ];

        $this->assertTrue(WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($linea));
    }
}
