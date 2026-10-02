<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoComprobanteIvaNumeracionSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoComprobanteIvaNumeracionSupportTest extends TestCase
{
    public function test_ico_e_ido_numeran_solos(): void
    {
        $this->assertTrue(IngresoEgresoComprobanteIvaNumeracionSupport::esAutomatico('ICO'));
        $this->assertTrue(IngresoEgresoComprobanteIvaNumeracionSupport::esAutomatico('ido'));
        $this->assertTrue(IngresoEgresoComprobanteIvaNumeracionSupport::esAutomatico(' ICO '));
    }

    public function test_factura_sigue_pidiendo_numero(): void
    {
        $this->assertFalse(IngresoEgresoComprobanteIvaNumeracionSupport::esAutomatico('FAC'));
        $this->assertFalse(IngresoEgresoComprobanteIvaNumeracionSupport::esAutomatico(''));
        $this->assertFalse(IngresoEgresoComprobanteIvaNumeracionSupport::esAutomatico(null));
    }

    public function test_letra_y_sucursal_fijas_de_anita(): void
    {
        $this->assertSame('A', IngresoEgresoComprobanteIvaNumeracionSupport::LETRA);
        $this->assertSame(0, IngresoEgresoComprobanteIvaNumeracionSupport::SUCURSAL);
    }
}
