<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraLegajoGastronomiaSupport;
use Carbon\Carbon;
use Tests\TestCase;

class OrdencompraLegajoGastronomiaP1Test extends TestCase
{
    public function test_dias_en_ubicacion_cero_si_no_hay_fecha(): void
    {
        $this->assertSame(0, OrdencompraLegajoGastronomiaSupport::diasEnUbicacion(null));
    }

    public function test_dias_en_ubicacion_cuenta_dias_calendario(): void
    {
        $desde = Carbon::parse('2026-08-20 10:00:00');
        $ahora = Carbon::parse('2026-08-26 09:00:00');

        $this->assertSame(5, OrdencompraLegajoGastronomiaSupport::diasEnUbicacion($desde, $ahora));
    }

    public function test_enlace_sin_fecha_envio_no_esta_vencido(): void
    {
        $this->assertFalse(OrdencompraLegajoGastronomiaSupport::enlaceVencido(null, 3));
    }

    public function test_enlace_vence_despues_del_plazo(): void
    {
        $envio = Carbon::parse('2026-08-23 08:00:00');
        $ahora = Carbon::parse('2026-08-26 08:00:01');

        $this->assertTrue(OrdencompraLegajoGastronomiaSupport::enlaceVencido($envio, 3, $ahora));
    }

    public function test_enlace_dentro_del_plazo_sigue_vigente(): void
    {
        $envio = Carbon::parse('2026-08-23 08:00:00');
        $ahora = Carbon::parse('2026-08-26 08:00:00');

        $this->assertFalse(OrdencompraLegajoGastronomiaSupport::enlaceVencido($envio, 3, $ahora));
    }
}
