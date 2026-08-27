<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAutomaticoSupport;
use Tests\TestCase;

final class CierreJornadaProcesoAutomaticoSupportTest extends TestCase
{
    public function test_total_facturas_suma_lotes_si_falta_total_factura(): void
    {
        $total = CierreJornadaProcesoAutomaticoSupport::totalFacturasDesdeEmision([
            'total_factura' => null,
            'facturas' => [
                ['factura' => 'FAC B 00030-54642', 'total' => 51000],
            ],
        ]);

        $this->assertSame(51000.0, $total);
    }

    public function test_total_facturas_usa_suma_de_lotes_si_declarado_es_cero(): void
    {
        $total = CierreJornadaProcesoAutomaticoSupport::totalFacturasDesdeEmision([
            'total_factura' => 0,
            'facturas' => [
                ['total' => 20000],
                ['total' => 31000],
            ],
        ]);

        $this->assertSame(51000.0, $total);
    }

    public function test_total_facturas_cero_si_no_hay_lotes(): void
    {
        $this->assertSame(0.0, CierreJornadaProcesoAutomaticoSupport::totalFacturasDesdeEmision([]));
    }
}
