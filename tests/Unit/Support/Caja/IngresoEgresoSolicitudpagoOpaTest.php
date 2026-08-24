<?php

namespace Tests\Unit\Support\Caja;

use App\Models\Solicitudpago\Solicitudpago;
use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Tests\TestCase;

class IngresoEgresoSolicitudpagoOpaTest extends TestCase
{
    public function test_tratamiento_anticipada_cierra_como_opa(): void
    {
        $sp = new Solicitudpago(['tratamiento' => SolicitudpagoTratamientos::ANTICIPADA]);

        $this->assertTrue(SolicitudpagoTratamientos::esAnticipada($sp->tratamiento));
        $this->assertTrue(IngresoEgresoSolicitudpagoSupport::esPagoOpa($sp));
        $this->assertSame('OPA', IngresoEgresoSolicitudpagoSupport::abreviaturaTipoPago($sp));
    }

    public function test_tratamiento_normal_sigue_como_opp(): void
    {
        $sp = new Solicitudpago(['tratamiento' => SolicitudpagoTratamientos::NORMAL]);

        $this->assertFalse(IngresoEgresoSolicitudpagoSupport::esPagoOpa($sp));
        $this->assertSame('OPP', IngresoEgresoSolicitudpagoSupport::abreviaturaTipoPago($sp));
        $this->assertSame('OPP', IngresoEgresoSolicitudpagoSupport::abreviaturaTipoPago(null));
    }
}
