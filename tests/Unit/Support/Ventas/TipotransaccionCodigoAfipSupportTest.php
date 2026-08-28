<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use Tests\TestCase;

final class TipotransaccionCodigoAfipSupportTest extends TestCase
{
    public function test_codigo_afip_factura_b_desde_base_legacy(): void
    {
        $this->assertSame(6, TipotransaccionCodigoAfipSupport::codigoAfipParaEmision('001', 'B'));
        $this->assertSame(1, TipotransaccionCodigoAfipSupport::codigoAfipParaEmision('001', 'A'));
    }

    public function test_codigo_afip_nc_b_comparte_base_003(): void
    {
        $this->assertSame(8, TipotransaccionCodigoAfipSupport::codigoAfipParaEmision('003', 'B'));
        $this->assertSame(3, TipotransaccionCodigoAfipSupport::codigoAfipParaEmision('003', 'A'));
    }

    public function test_codigo_afip_final_arca_no_suma_offset(): void
    {
        $this->assertSame(6, TipotransaccionCodigoAfipSupport::codigoAfipParaEmision('006', 'B'));
        $this->assertSame(206, TipotransaccionCodigoAfipSupport::codigoAfipParaEmision('206', 'B'));
    }

    public function test_codigo_afip_fce_mypime_suma_200(): void
    {
        config(['facturacion.LIMITE_FCE' => 1000]);

        $this->assertSame(
            206,
            TipotransaccionCodigoAfipSupport::codigoAfipParaEmision('001', 'B', 'C', 5000),
        );
    }

    public function test_codigo_afip_fce_no_cambia_si_no_alcanza_el_minimo(): void
    {
        config(['facturacion.LIMITE_FCE' => 1000]);

        $this->assertSame(
            6,
            TipotransaccionCodigoAfipSupport::codigoAfipParaEmision('001', 'B', 'C', 999),
        );
    }

    public function test_codigo_afip_sin_modo_fce_no_suma_200(): void
    {
        config(['facturacion.LIMITE_FCE' => 1000]);

        $this->assertSame(
            1,
            TipotransaccionCodigoAfipSupport::codigoAfipParaEmision('001', 'A', 'N', 5000),
        );
    }

    public function test_codigos_base_posibles_incluye_varios_tipotransaccion_mismo_codigo(): void
    {
        $bases = TipotransaccionCodigoAfipSupport::codigosBaseAlmacenadosPosibles(8, 'B');

        $this->assertContains(3, $bases);
        $this->assertNotContains(6, $bases);
    }

    public function test_codigos_base_fce_b_no_mezcla_factura_b_regular(): void
    {
        $bases = TipotransaccionCodigoAfipSupport::codigosBaseAlmacenadosPosibles(206, 'B');

        $this->assertContains(206, $bases);
        $this->assertContains(201, $bases);
        $this->assertNotContains(1, $bases);
        $this->assertNotContains(6, $bases);
    }
}
