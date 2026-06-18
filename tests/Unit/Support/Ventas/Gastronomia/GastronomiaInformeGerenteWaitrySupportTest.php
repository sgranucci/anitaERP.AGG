<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\GastronomiaInformeGerenteWaitrySupport;
use Tests\TestCase;

class GastronomiaInformeGerenteWaitrySupportTest extends TestCase
{
    public function test_aplica_waitry_a_fila_existente_del_punto_de_venta(): void
    {
        $filas = [
            [
                'puntoventa_id' => 12,
                'codigo' => '00003',
                'nombre' => 'FCE BSA',
                'total' => 500.0,
                'total_facturas' => 500.0,
                'cantidad_facturas' => 10,
                'cantidad_notas_credito' => 0,
            ],
            [
                'puntoventa_id' => 8,
                'codigo' => '00008',
                'nombre' => 'POS Salón',
                'total' => 300.0,
                'total_facturas' => 300.0,
                'cantidad_facturas' => 6,
                'cantidad_notas_credito' => 0,
            ],
        ];

        $resultado = GastronomiaInformeGerenteWaitrySupport::aplicarAVentasPorPuntoventa($filas, [
            'empresa_id' => 1,
            'total' => 150.0,
            'cantidad_ordenes' => 3,
            'puntoventa_id' => 12,
            'codigo' => '00003',
            'nombre' => 'FCE BSA',
        ]);

        $this->assertSame(650.0, $resultado[0]['total']);
        $this->assertSame(150.0, $resultado[0]['waitry_sin_facturar']);
        $this->assertSame(3, $resultado[0]['cantidad_waitry_sin_facturar']);
        $this->assertSame(300.0, $resultado[1]['total']);
    }

    public function test_agrega_fila_si_el_punto_de_venta_no_estaba_en_la_grilla(): void
    {
        $resultado = GastronomiaInformeGerenteWaitrySupport::aplicarAVentasPorPuntoventa([], [
            'empresa_id' => 1,
            'total' => 80.0,
            'cantidad_ordenes' => 2,
            'puntoventa_id' => 99,
            'codigo' => '00099',
            'nombre' => 'PV Proceso',
        ]);

        $this->assertCount(1, $resultado);
        $this->assertSame(99, $resultado[0]['puntoventa_id']);
        $this->assertSame(80.0, $resultado[0]['total']);
        $this->assertSame(80.0, $resultado[0]['waitry_sin_facturar']);
    }
}
