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
