<?php

namespace Tests\Unit\Support\Caja;

use App\Models\Solicitudpago\Solicitudpago;
use App\Support\Caja\CobranzaNumeracionTransaccion;
use App\Support\Caja\IngresoEgresoAnitaNumeracionSupport;
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

    public function test_opa_id_11_usa_numeracion_secuencial_por_lista(): void
    {
        $prev = config('cobranza.tipotransaccion_caja_ids_secuencial');
        config(['cobranza.tipotransaccion_caja_ids_secuencial' => [1, 10, 11]]);
        try {
            $this->assertContains(11, CobranzaNumeracionTransaccion::tiposTransaccionSecuencial());
            $this->assertTrue(CobranzaNumeracionTransaccion::usaNumeracionSecuencial(11));
        } finally {
            config(['cobranza.tipotransaccion_caja_ids_secuencial' => $prev]);
        }
    }

    public function test_semilla_anita_opa_comparte_serie_con_opp(): void
    {
        $semillas = IngresoEgresoAnitaNumeracionSupport::semillasDefault();

        $this->assertArrayHasKey('OPA', $semillas);
        $this->assertSame($semillas['OPP'], $semillas['OPA']);
        $this->assertArrayHasKey('OPA', IngresoEgresoAnitaNumeracionSupport::mapaSemillas());
    }
}
