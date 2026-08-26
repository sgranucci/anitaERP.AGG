<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraLegajoGastronomiaSupport;
use Tests\TestCase;

class OrdencompraLegajoGastronomiaP0Test extends TestCase
{
    public function test_sin_movimientos_no_esta_autorizado(): void
    {
        $this->assertFalse(OrdencompraLegajoGastronomiaSupport::autorizacionCompletaDesdeEstados([]));
    }

    public function test_pendiente_bloquea_aunque_haya_aprobado_viejo(): void
    {
        $this->assertFalse(OrdencompraLegajoGastronomiaSupport::autorizacionCompletaDesdeEstados([
            'Pendiente',
            'Aprobado',
        ]));
    }

    public function test_rechazo_reciente_no_autoriza(): void
    {
        $this->assertFalse(OrdencompraLegajoGastronomiaSupport::autorizacionCompletaDesdeEstados([
            'Rechazado',
            'Aprobado',
        ]));
    }

    public function test_aprobado_reciente_sin_pendiente_autoriza(): void
    {
        $this->assertTrue(OrdencompraLegajoGastronomiaSupport::autorizacionCompletaDesdeEstados([
            'Aprobado',
            'Sin efecto',
        ]));
    }

    public function test_sin_efecto_solo_no_autoriza(): void
    {
        $this->assertFalse(OrdencompraLegajoGastronomiaSupport::autorizacionCompletaDesdeEstados([
            'Sin efecto',
        ]));
    }
}
