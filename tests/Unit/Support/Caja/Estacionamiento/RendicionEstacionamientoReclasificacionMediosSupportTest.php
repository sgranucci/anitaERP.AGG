<?php

namespace Tests\Unit\Support\Caja\Estacionamiento;

use App\Support\Caja\Estacionamiento\RendicionEstacionamientoReclasificacionMediosSupport;
use Tests\TestCase;

class RendicionEstacionamientoReclasificacionMediosSupportTest extends TestCase
{
    public function test_el_pase_entre_efectivo_y_mercado_pago_es_la_distribucion_contada(): void
    {
        $out = RendicionEstacionamientoReclasificacionMediosSupport::distribucionContada(
            [25 => 37500.0, 201 => 4500.0],
            [25 => 36000.0, 201 => 6000.0],
        );

        $this->assertSame([25 => 36000.0, 201 => 6000.0], $out);
    }

    public function test_un_total_distinto_no_es_reclasificacion(): void
    {
        $this->assertNull(RendicionEstacionamientoReclasificacionMediosSupport::distribucionContada(
            [25 => 37500.0, 201 => 4500.0],
            [25 => 36000.0, 201 => 4500.0],
        ));
    }
}
