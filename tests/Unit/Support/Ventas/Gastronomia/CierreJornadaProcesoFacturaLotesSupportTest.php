<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaLotesSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use Tests\TestCase;

final class CierreJornadaProcesoFacturaLotesSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['arca_wsfe.receptor.consumidor_final_umbral_monto' => 1000.]);
        config(['gastronomia.cierre_jornada_cf_lote_porcentaje_tope' => 20.]);
        config(['gastronomia.cierre_jornada_cf_lote_monto' => 0.]);
    }

    public function test_arma_lotes_respetando_tope_y_objetivo(): void
    {
        $comandas = [];
        for ($i = 1; $i <= 5; $i++) {
            $comandas[] = [
                'waitry_order_id' => $i,
                'total' => 200.,
            ];
        }

        $lotes = CierreJornadaProcesoFacturaLotesSupport::armarLotes($comandas, 1000., 200.);

        $this->assertGreaterThanOrEqual(2, count($lotes));
        $suma = 0.;
        foreach ($lotes as $lote) {
            $this->assertLessThanOrEqual(1000., $lote['total']);
            $suma += $lote['total'];
        }
        $this->assertSame(1000., round($suma, 2));
    }

    public function test_plan_desde_movimientos_cuadra_factura_mas_ajuste(): void
    {
        $movimientos = [
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'waitry_order_id' => 1,
                'total' => 800.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 800.],
                ],
            ],
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'waitry_order_id' => 2,
                'total' => 120.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 120.],
                ],
            ],
        ];

        $plan = CierreJornadaProcesoFacturaLotesSupport::armarPlanDesdeMovimientos($movimientos);

        $this->assertTrue($plan['cuadre_ok']);
        $this->assertSame(800., $plan['total_factura']);
        $this->assertSame(120., $plan['total_ajuste']);
        $this->assertSame(920., $plan['total_grupo']);
        $this->assertCount(1, $plan['lotes']);
    }

    public function test_plan_usa_monto_fijo_como_objetivo_y_no_el_porcentaje(): void
    {
        config([
            'arca_wsfe.receptor.consumidor_final_umbral_monto' => 10_000_000.,
            'gastronomia.cierre_jornada_cf_lote_porcentaje_tope' => 20.,
            'gastronomia.cierre_jornada_cf_lote_monto' => 100000.,
        ]);

        $movimientos = [];
        for ($i = 1; $i <= 15; $i++) {
            $movimientos[] = [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'waitry_order_id' => $i,
                'total' => 100000.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 100000.],
                ],
            ];
        }

        $plan = CierreJornadaProcesoFacturaLotesSupport::armarPlanDesdeMovimientos($movimientos);

        $this->assertSame(100000., $plan['objetivo_lote']);
        $this->assertCount(15, $plan['lotes']);
        $this->assertSame(1_500_000., $plan['total_factura']);
        foreach ($plan['lotes'] as $lote) {
            $this->assertSame(100000., $lote['total']);
        }
    }
}
