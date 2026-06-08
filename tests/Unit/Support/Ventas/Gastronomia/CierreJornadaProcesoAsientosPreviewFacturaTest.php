<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosPreviewSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use Tests\TestCase;

final class CierreJornadaProcesoAsientosPreviewFacturaTest extends TestCase
{
    public function test_preview_factura_consolida_comandas_qr(): void
    {
        $movimientos = [
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 100.0,
                'impuesto_interno' => 0.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 100.0],
                ],
            ],
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 200.0,
                'impuesto_interno' => 0.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 200.0],
                ],
            ],
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 50.0,
                'impuesto_interno' => 0.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 50.0],
                ],
            ],
        ];

        $preview = CierreJornadaProcesoAsientosPreviewSupport::generarPreviewFacturaProceso(
            $movimientos,
            1,
            ['cuenta_ventas_id' => 10, 'cuenta_iva_id' => 20],
        );

        $this->assertSame(2, $preview['cantidad_comandas']);
        $this->assertSame(300.0, $preview['total']);
        $this->assertNotNull($preview['asiento']);
        $this->assertSame(300.0, $preview['resumen_debe']);
        $this->assertSame(300.0, $preview['resumen_haber']);
    }

    public function test_preview_factura_usa_total_completo_si_hay_redistribucion_mixta(): void
    {
        $movimientos = [
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 1000.0,
                'impuesto_interno' => 100.0,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 600.0],
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 400.0],
                ],
            ],
        ];

        $preview = CierreJornadaProcesoAsientosPreviewSupport::generarPreviewFacturaProceso(
            $movimientos,
            1,
            ['cuenta_ventas_id' => 10, 'cuenta_iva_id' => 20],
        );

        $this->assertSame(1000.0, $preview['total']);
        $this->assertSame(1000.0, $preview['resumen_debe']);
        $this->assertSame(1000.0, $preview['resumen_haber']);
        $this->assertSame(
            1000.0,
            CierreJornadaProcesoAsientosPreviewSupport::totalQrFacturaProceso($movimientos),
        );
    }
}
