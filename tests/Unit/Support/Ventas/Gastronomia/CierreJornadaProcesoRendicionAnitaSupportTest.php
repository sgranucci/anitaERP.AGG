<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRendicionAnitaSupport;
use Tests\TestCase;

final class CierreJornadaProcesoRendicionAnitaSupportTest extends TestCase
{
    public function test_turno_letra_es_n(): void
    {
        $this->assertSame('N', CierreJornadaProcesoRendicionAnitaSupport::TURNO_LETRA);
    }

    public function test_total_facturas_proceso_vacio(): void
    {
        $this->assertSame(0.0, CierreJornadaProcesoRendicionAnitaSupport::totalFacturasProceso([]));
    }

    public function test_movimientos_como_stubs_agrupa_cuentas(): void
    {
        $filas = [
            ['cuentacaja_id' => 5, 'monto' => 100.0, 'cotizacion' => 1.0],
            ['cuentacaja_id' => 0, 'monto' => 50.0, 'cotizacion' => 1.0],
        ];

        $stubs = CierreJornadaProcesoRendicionAnitaSupport::movimientosComoStubs($filas);

        $this->assertCount(1, $stubs);
        $this->assertSame(5, $stubs[0]->cuentacaja_id);
        $this->assertSame(100.0, $stubs[0]->monto);
    }
}
