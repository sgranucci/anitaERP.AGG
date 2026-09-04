<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashReporteAggPerfilVistaSupport;
use Tests\TestCase;

class FlashReporteAggPerfilVistaSupportTest extends TestCase
{
    public function test_normaliza_perfil_desconocido_a_completa(): void
    {
        $this->assertSame(
            FlashReporteAggPerfilVistaSupport::COMPLETA,
            FlashReporteAggPerfilVistaSupport::normalizar('otro')
        );
        $this->assertTrue(FlashReporteAggPerfilVistaSupport::esFinanzas('finanzas'));
        $this->assertFalse(FlashReporteAggPerfilVistaSupport::esFinanzas('completa'));
    }

    public function test_fila_finanzas_suma_slots_y_ruletas(): void
    {
        $fila = FlashReporteAggPerfilVistaSupport::filaFinanzasDesdeDatos([
            'B' => '1/09/26',
            'E' => 1000.0,
            'M' => 250.5,
            'F' => 800.0,
            'N' => 100.0,
            'AD' => 50.0,
            'AE' => 45.0,
            'AH' => 120.0,
            'AN' => 30.0,
            'AL' => 200.0,
        ]);

        $this->assertSame('1/09/26', $fila['fecha']);
        $this->assertSame(1250.5, $fila['coin_in']);
        $this->assertSame(900.0, $fila['drop']);
        $this->assertSame(50.0, $fila['win_online']);
        $this->assertSame(45.0, $fila['win_financiero']);
        $this->assertSame(120.0, $fila['ventas_bingo']);
        $this->assertSame(30.0, $fila['ventas_parking']);
        $this->assertSame(200.0, $fila['ventas_gastronomia']);
        $this->assertSame(0.0, $fila['ventas_vending']);
    }

    public function test_fila_finanzas_incluye_vending_desde_metricas(): void
    {
        $fila = FlashReporteAggPerfilVistaSupport::filaFinanzasDesdeDatos([
            'B' => '2/09/26',
            'E' => 0,
            'M' => 0,
            'F' => 0,
            'N' => 0,
            'AD' => 0,
            'AE' => 0,
            'AH' => 0,
            'AN' => 0,
            'AL' => 100.0,
        ], 55.5);

        $this->assertSame(100.0, $fila['ventas_gastronomia']);
        $this->assertSame(55.5, $fila['ventas_vending']);
    }

    public function test_totales_finanzas(): void
    {
        $totales = FlashReporteAggPerfilVistaSupport::totalesFinanzas([
            [
                'coin_in' => 10,
                'drop' => 5,
                'win_online' => 1,
                'win_financiero' => 2,
                'ventas_bingo' => 3,
                'ventas_parking' => 4,
                'ventas_gastronomia' => 5,
                'ventas_vending' => 7,
            ],
            [
                'coin_in' => 20,
                'drop' => 15,
                'win_online' => 1,
                'win_financiero' => 2,
                'ventas_bingo' => 3,
                'ventas_parking' => 4,
                'ventas_gastronomia' => 5,
                'ventas_vending' => 3,
            ],
        ]);

        $this->assertSame(30.0, $totales['coin_in']);
        $this->assertSame(20.0, $totales['drop']);
        $this->assertSame(10.0, $totales['ventas_gastronomia']);
        $this->assertSame(10.0, $totales['ventas_vending']);
    }
}
