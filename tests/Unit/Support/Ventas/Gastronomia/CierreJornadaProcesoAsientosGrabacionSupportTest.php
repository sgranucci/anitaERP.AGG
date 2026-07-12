<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Caja\Cuentacaja;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosGrabacionSupport;
use InvalidArgumentException;
use Tests\TestCase;

final class CierreJornadaProcesoAsientosGrabacionSupportTest extends TestCase
{
    public function test_payload_desde_lineas_preview_arma_debe_haber(): void
    {
        $config = [
            'cuenta_ventas_id' => 10,
            'cuenta_iva_id' => 20,
        ];
        $cache = [];

        $payload = CierreJornadaProcesoAsientosGrabacionSupport::payloadDesdeLineasPreview(
            [
                ['concepto' => 'Medio cobro (ref. caja)', 'cuenta_id' => 10, 'debe' => 600., 'haber' => 0.],
                ['concepto' => 'Ventas gravadas', 'cuenta_id' => 10, 'debe' => 0., 'haber' => 495.87],
                ['concepto' => 'IVA', 'cuenta_id' => 20, 'debe' => 0., 'haber' => 104.13],
            ],
            1,
            $config,
            $cache,
        );

        $this->assertCount(3, $payload['cuentacontable_ids']);
        $this->assertSame(10, $payload['cuentacontable_ids'][1]);
        $this->assertSame(600., $payload['debes'][0]);
        $this->assertSame('', $payload['debes'][1]);
        $this->assertSame(495.87, $payload['haberes'][1]);
    }

    public function test_armar_payloads_asigna_centrocosto_85_a_cuenta_ventas(): void
    {
        $cuentaVentasId = 586;
        $cuentaIvaId = 399;
        $cuentaVentas = \App\Models\Contable\Cuentacontable::query()->find($cuentaVentasId, ['id', 'manejaccosto']);
        $cuentaIva = \App\Models\Contable\Cuentacontable::query()->find($cuentaIvaId, ['id', 'manejaccosto']);
        if ($cuentaVentas === null || $cuentaIva === null) {
            $this->markTestSkipped('Sin cuentas ventas/IVA 586/399 en el entorno.');
        }

        $ccId = \App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosCentrocostoSupport::idCentrocostoGastronomia();
        if ($ccId === null) {
            $this->markTestSkipped('Sin centro de costo 85 en el entorno.');
        }

        $payloads = CierreJornadaProcesoAsientosGrabacionSupport::armarPayloadsAsientos(
            [[
                'codigo' => 'sin_facturar_qr',
                'titulo' => 'Test CC',
                'lineas' => [
                    ['concepto' => 'Medio de cobro — QR', 'cuenta_id' => 10, 'debe' => 100., 'haber' => 0.],
                    ['concepto' => 'Ventas gravadas', 'cuenta_id' => $cuentaVentasId, 'debe' => 0., 'haber' => 80.],
                    ['concepto' => 'IVA', 'cuenta_id' => $cuentaIvaId, 'debe' => 0., 'haber' => 20.],
                ],
            ]],
            1,
            ['cuenta_ventas_id' => $cuentaVentasId, 'cuenta_iva_id' => $cuentaIvaId],
            '2026-06-01',
            '2026-06-01',
        );

        $ccs = $payloads[0]['payload']['centrocosto_ids'] ?? [];
        $this->assertCount(3, $ccs);
        $this->assertNull($ccs[0]);
        $this->assertSame($ccId, $ccs[1]);
        $this->assertNull($ccs[2]);
    }

    public function test_armar_payloads_usa_descripcion_compensacion_fondo_fijo(): void
    {
        $payloads = CierreJornadaProcesoAsientosGrabacionSupport::armarPayloadsAsientos(
            [[
                'codigo' => CierreJornadaProcesoAsientosGrabacionSupport::CODIGO_ASIENTO_COMPENSACION_FONDO_FIJO,
                'titulo' => '3 — Reducion de Fondo fijo',
                'lineas' => [
                    ['concepto' => 'Efectivo Waitry no incluido en factura del proceso', 'cuenta_id' => 10, 'debe' => 100., 'haber' => 0.],
                    ['concepto' => 'Reducion de Fondo fijo', 'cuenta_id' => 60, 'debe' => 0., 'haber' => 100.],
                ],
            ]],
            1,
            ['cuenta_ventas_id' => 10, 'cuenta_iva_id' => 20, 'cuenta_fondo_fijo_maquinas_id' => 60],
            '2026-06-01',
            '2026-06-01',
        );

        $this->assertCount(1, $payloads);
        $this->assertSame(
            CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO_COMPENSACION_FONDO_FIJO,
            $payloads[0]['payload']['observacion'] ?? null,
        );
        $this->assertSame(
            [
                CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO_COMPENSACION_FONDO_FIJO,
                CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO_COMPENSACION_FONDO_FIJO,
            ],
            $payloads[0]['payload']['observaciones'] ?? null,
        );
    }

    public function test_armar_payloads_usa_descripcion_venta_gastronomia(): void
    {
        $payloads = CierreJornadaProcesoAsientosGrabacionSupport::armarPayloadsAsientos(
            [[
                'codigo' => 'sin_facturar_qr',
                'titulo' => '1 — Waitry sin facturar (QR / Mercado Pago tras redistribución)',
                'lineas' => [
                    ['concepto' => 'Medio de cobro — QR', 'cuenta_id' => 10, 'debe' => 100., 'haber' => 0.],
                    ['concepto' => 'Ventas gravadas', 'cuenta_id' => 20, 'debe' => 0., 'haber' => 100.],
                ],
            ]],
            1,
            ['cuenta_ventas_id' => 10, 'cuenta_iva_id' => 20],
            '2026-06-01',
            '2026-06-01',
        );

        $this->assertCount(1, $payloads);
        $this->assertSame(
            CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
            $payloads[0]['payload']['observacion'] ?? null,
        );
        $this->assertSame(
            [
                CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
                CierreJornadaProcesoAsientosGrabacionSupport::DESCRIPCION_ASIENTO,
            ],
            $payloads[0]['payload']['observaciones'] ?? null,
        );
    }

    public function test_armar_payloads_lanza_si_asiento_no_cuadra(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no cuadra');

        CierreJornadaProcesoAsientosGrabacionSupport::armarPayloadsAsientos(
            [[
                'codigo' => 'test',
                'titulo' => 'Test',
                'lineas' => [
                    ['concepto' => 'Debe', 'cuenta_id' => 10, 'debe' => 100., 'haber' => 0.],
                    ['concepto' => 'Haber', 'cuenta_id' => 20, 'debe' => 0., 'haber' => 50.],
                ],
            ]],
            1,
            ['cuenta_ventas_id' => 10, 'cuenta_iva_id' => 20],
            '2026-06-01',
            '2026-06-01',
        );
    }

    public function test_resolver_prioriza_cuentacaja_cuando_id_coincide_con_cuentacontable(): void
    {
        $caja = Cuentacaja::query()->with('cuentacontables:id,codigo')->find(25);
        if ($caja === null) {
            $this->markTestSkipped('Sin cuentacaja id 25 en el entorno de prueba.');
        }

        $esperado = (int) ($caja->cuentacontables?->id ?? $caja->cuentacontable_id ?? 0);
        if ($esperado <= 0) {
            $this->markTestSkipped('Cuentacaja 25 sin cuenta contable vinculada.');
        }

        $cache = [];
        $resuelto = CierreJornadaProcesoAsientosGrabacionSupport::resolverCuentacontableId(
            25,
            1,
            [],
            $cache,
        );

        $this->assertSame($esperado, $resuelto);
        $this->assertNotSame(25, $resuelto);
    }
}
