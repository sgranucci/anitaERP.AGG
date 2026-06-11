<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\KandikoAnitaVentaTipoSupport;
use Tests\TestCase;

class KandikoAnitaVentaTipoSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.empresa' => 'AGG']);
    }

    public function test_kandiko_caea_pv_31_fac_usa_fak_en_venta(): void
    {
        $this->assertTrue(KandikoAnitaVentaTipoSupport::debeUsarTipoVentaAlterno('FAC', '00031', '2', 'A'));
        $this->assertSame('FAK', KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge('FAC', '00031', '2', 'A'));
        $this->assertSame('FAK', KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge('FAC', '31', 2, 'A'));
    }

    public function test_kandiko_pv_31_sin_modo_caea_mantiene_fac(): void
    {
        $this->assertFalse(KandikoAnitaVentaTipoSupport::debeUsarTipoVentaAlterno('FAC', '00031', '2', 'C'));
        $this->assertSame('FAC', KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge('FAC', '00031', '2', 'C'));
    }

    public function test_rebisco_pv_31_mantiene_fac(): void
    {
        $this->assertFalse(KandikoAnitaVentaTipoSupport::debeUsarTipoVentaAlterno('FAC', '00031', '3', 'A'));
        $this->assertSame('FAC', KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge('FAC', '00031', '3', 'C'));
    }

    public function test_kandiko_otro_pv_mantiene_fac(): void
    {
        $this->assertSame('FAC', KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge('FAC', '00014', '2', 'A'));
    }
}
