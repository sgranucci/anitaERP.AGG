<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaCuadroDetalleSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use Tests\TestCase;

final class CierreJornadaCuadroDetalleSupportTest extends TestCase
{
    public function test_waitry_pago_qr_filtra_por_fecha_y_medio(): void
    {
        $movimientos = [
            [
                'waitry_order_id' => 100,
                'display_id' => 'A-1',
                'placed_at' => '2026-06-03 14:30:00',
                'placed_at_fmt' => '03/06/2026 14:30',
                'total' => 150.0,
                'waitry_tipo_pago' => 'credit_card',
                'waitry_payment_gateway' => 'KIOSK MPQR',
                'waitry_medio_label' => 'QR MP (kiosco)',
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
                'paid_waitry' => true,
                'facturada_erp' => false,
            ],
            [
                'waitry_order_id' => 101,
                'display_id' => 'A-2',
                'placed_at' => '2026-06-03 15:00:00',
                'placed_at_fmt' => '03/06/2026 15:00',
                'total' => 80.0,
                'waitry_tipo_pago' => 'mercadopago',
                'waitry_medio_label' => 'Mercado Pago',
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP,
                'paid_waitry' => true,
                'facturada_erp' => false,
            ],
        ];

        $detalle = CierreJornadaCuadroDetalleSupport::consultar(
            1,
            '2026-06-03',
            CierreJornadaCuadroDetalleSupport::FILA_WAITRY_PAGO,
            'qr',
            $movimientos,
        );

        $this->assertSame(1, $detalle['total_registros']);
        $this->assertSame(150.0, $detalle['total_importe']);
        $this->assertSame(100, $detalle['items'][0]['waitry_order_id']);
        $this->assertSame('2026-06-03 14:30:00', $detalle['items'][0]['fecha_hora_raw']);
    }

    public function test_clasificacion_y_detalle_qr_coinciden(): void
    {
        $movimientos = [
            [
                'waitry_order_id' => 10,
                'total' => 100.0,
                'waitry_tipo_pago' => 'totalcoin',
                'paid_waitry' => true,
                'facturada_erp' => false,
            ],
            [
                'waitry_order_id' => 11,
                'total' => 50.0,
                'waitry_tipo_pago' => 'credit_card',
                'waitry_payment_gateway' => 'KIOSK MPQR',
                'paid_waitry' => true,
                'facturada_erp' => false,
            ],
        ];

        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar($movimientos, 1);
        $detalle = CierreJornadaCuadroDetalleSupport::consultar(
            1,
            '2026-06-03',
            CierreJornadaCuadroDetalleSupport::FILA_WAITRY_PAGO,
            'qr',
            $clasificacion['movimientos'],
        );

        $this->assertSame(150.0, $detalle['total_importe']);
        $this->assertSame(150.0, $clasificacion['grilla']['qr_sin_facturar']);
    }

    public function test_waitry_pago_cuenta_compartida_incluye_qr_y_mp_y_desglosa(): void
    {
        config(['waitry.tipo_pago_cuentacaja' => [
            'mercadopago' => 201,
            'totalcoin' => 201,
        ]]);

        $movimientos = [
            [
                'waitry_order_id' => 100,
                'display_id' => 'W-QR1',
                'placed_at' => '2026-06-04 10:00:00',
                'total' => 150.0,
                'waitry_tipo_pago' => 'credit_card',
                'waitry_payment_gateway' => 'KIOSK MPQR',
                'waitry_medio_label' => 'QR MP (kiosco)',
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
                'paid_waitry' => true,
                'facturada_erp' => false,
            ],
            [
                'waitry_order_id' => 101,
                'display_id' => 'W-MP1',
                'placed_at' => '2026-06-04 11:00:00',
                'total' => 80.0,
                'waitry_tipo_pago' => 'credit_card',
                'waitry_medio_label' => 'Posnet (tótem)',
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP,
                'paid_waitry' => true,
                'facturada_erp' => false,
            ],
        ];

        $detalle = CierreJornadaCuadroDetalleSupport::consultar(
            1,
            '2026-06-04',
            CierreJornadaCuadroDetalleSupport::FILA_WAITRY_PAGO,
            'cc:201',
            $movimientos,
        );

        $this->assertSame(2, $detalle['total_registros']);
        $this->assertSame(230.0, $detalle['total_importe']);
        $this->assertCount(2, $detalle['totales_por_medio_waitry']);
        $this->assertSame(150.0, $detalle['totales_por_medio_waitry'][0]['importe']);
        $this->assertSame(80.0, $detalle['totales_por_medio_waitry'][1]['importe']);
        $this->assertSame('qr', $detalle['totales_por_medio_waitry'][0]['clave']);
        $this->assertSame('mp', $detalle['totales_por_medio_waitry'][1]['clave']);
    }
}
