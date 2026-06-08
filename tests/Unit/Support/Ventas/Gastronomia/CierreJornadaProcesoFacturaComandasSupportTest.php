<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaComandasSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use Tests\TestCase;

final class CierreJornadaProcesoFacturaComandasSupportTest extends TestCase
{
    public function test_clasifica_comanda_mixta_a_facturacion_completa(): void
    {
        $mov = [
            'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
            'total' => 1000.,
            'medios_pago_planificados' => [
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 600.],
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 400.],
            ],
        ];

        $this->assertTrue(CierreJornadaProcesoFacturaComandasSupport::comandaVaAFacturacion($mov));
        $this->assertFalse(CierreJornadaProcesoFacturaComandasSupport::comandaVaAAjusteStock($mov));
        $this->assertSame(1000., CierreJornadaProcesoFacturaComandasSupport::montoComandaCompleto($mov));
    }

    public function test_clasifica_comanda_solo_efectivo_a_ajuste(): void
    {
        $mov = [
            'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
            'total' => 50.,
            'medios_pago_planificados' => [
                ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 50.],
            ],
        ];

        $this->assertFalse(CierreJornadaProcesoFacturaComandasSupport::comandaVaAFacturacion($mov));
        $this->assertTrue(CierreJornadaProcesoFacturaComandasSupport::comandaVaAAjusteStock($mov));
    }

    public function test_clasificar_cuadra_totales_grupo(): void
    {
        $movimientos = [
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 1000.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR, 'monto' => 600.],
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 400.],
                ],
            ],
            [
                'grupo' => CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR,
                'total' => 50.,
                'medios_pago_planificados' => [
                    ['clave' => CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO, 'monto' => 50.],
                ],
            ],
        ];

        $clasificacion = CierreJornadaProcesoFacturaComandasSupport::clasificar($movimientos);

        $this->assertCount(1, $clasificacion['facturar']);
        $this->assertCount(1, $clasificacion['ajuste']);
        $this->assertSame(
            1050.,
            CierreJornadaProcesoFacturaComandasSupport::totalComandas($clasificacion['facturar'])
            + CierreJornadaProcesoFacturaComandasSupport::totalComandas($clasificacion['ajuste']),
        );
    }

    public function test_referencias_comandas_para_persistencia_incluye_ids_y_montos(): void
    {
        $refs = CierreJornadaProcesoFacturaComandasSupport::referenciasComandasParaPersistencia(
            [
                [
                    'waitry_order_id' => 200,
                    'display_id' => 'M-12',
                    'referencia_waitry' => 'mesa 12',
                    'total' => 1500.5,
                    'medio_waitry_clave' => CierreJornadaProcesoMedioSupport::CLAVE_QR,
                    'placed_at' => '2026-06-03 14:00:00',
                ],
                [
                    'waitry_order_id' => 100,
                    'total' => 500.,
                ],
            ],
            [
                100 => ['displayId' => 'B-1', 'reference' => 'barra', 'placedAt' => '2026-06-03 13:00:00'],
            ],
        );

        $this->assertCount(2, $refs);
        $this->assertSame(100, $refs[0]['waitry_order_id']);
        $this->assertSame('B-1', $refs[0]['display_id']);
        $this->assertSame('barra', $refs[0]['referencia_waitry']);
        $this->assertSame(500., $refs[0]['total']);
        $this->assertSame(200, $refs[1]['waitry_order_id']);
        $this->assertSame('M-12', $refs[1]['display_id']);
        $this->assertSame(1500.5, $refs[1]['total']);
    }
}
