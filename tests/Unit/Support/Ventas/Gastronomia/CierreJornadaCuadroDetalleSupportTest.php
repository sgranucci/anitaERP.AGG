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
                'waitry_medio_label' => 'QR (Totalcoin / tótem)',
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
}
