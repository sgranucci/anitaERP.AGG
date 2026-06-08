<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaRecuperacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use Tests\TestCase;

final class CierreJornadaProcesoFacturaRecuperacionSupportTest extends TestCase
{
    public function test_numerocomprobante_desde_referencia_factura(): void
    {
        $this->assertSame(183672, CierreJornadaProcesoFacturaRecuperacionSupport::numerocomprobanteDesdeReferencia('FAC B 00020-183672'));
        $this->assertSame(183675, CierreJornadaProcesoFacturaRecuperacionSupport::numerocomprobanteDesdeReferencia('FAC B-00020-00183675'));
    }

    public function test_armar_lotes_desde_recuperacion(): void
    {
        $movimientos = [
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'waitry_order_id' => 100,
                'total' => 50.,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP,
            ],
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'waitry_order_id' => 200,
                'total' => 60.,
                'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_MP,
            ],
        ];

        $lotes = CierreJornadaProcesoFacturaRecuperacionSupport::armarLotesDesdeRecuperacion([
            [
                'lote' => 1,
                'factura' => 'FAC B 00020-183672',
                'total' => 110.,
                'waitry_order_ids' => [100, 200],
            ],
        ], $movimientos);

        $this->assertCount(1, $lotes);
        $this->assertSame(183672, $lotes[0]['numerocomprobante_forzado']);
        $this->assertSame(2, $lotes[0]['cantidad_comandas']);
    }
}
