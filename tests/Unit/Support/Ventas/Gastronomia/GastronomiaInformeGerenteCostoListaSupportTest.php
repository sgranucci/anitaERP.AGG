<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\GastronomiaInformeGerenteCostoListaSupport;
use Tests\TestCase;

class GastronomiaInformeGerenteCostoListaSupportTest extends TestCase
{
    public function test_listas_junio_usa_5006_y_5005(): void
    {
        $listas = GastronomiaInformeGerenteCostoListaSupport::listasDesdeFechaJornada('2026-06-15');

        $this->assertSame('5006', $listas['lista_actual']);
        $this->assertSame('5005', $listas['lista_anterior']);
        $this->assertSame('Junio', $listas['mes_actual_label']);
        $this->assertSame('Mayo', $listas['mes_anterior_label']);
    }

    public function test_listas_enero_usa_mes_anterior_diciembre(): void
    {
        $listas = GastronomiaInformeGerenteCostoListaSupport::listasDesdeFechaJornada('2026-01-10');

        $this->assertSame('5001', $listas['lista_actual']);
        $this->assertSame('5012', $listas['lista_anterior']);
    }

    public function test_porcentaje_diferencia_costo(): void
    {
        $this->assertSame(10.0, GastronomiaInformeGerenteCostoListaSupport::porcentajeDiferenciaCosto(100.0, 110.0));
        $this->assertSame(-20.0, GastronomiaInformeGerenteCostoListaSupport::porcentajeDiferenciaCosto(100.0, 80.0));
        $this->assertNull(GastronomiaInformeGerenteCostoListaSupport::porcentajeDiferenciaCosto(0.0, 80.0));
        $this->assertNull(GastronomiaInformeGerenteCostoListaSupport::porcentajeDiferenciaCosto(100.0, null));
    }
}
