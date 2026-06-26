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

    public function test_kandiko_caea_pv_31_ncd_usa_nck_en_venta(): void
    {
        $this->assertSame('NCK', KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge('NCD', '00031', '2', 'A'));
        $this->assertSame('NCD', KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge('NCD', '00031', '3', 'C'));
        $this->assertSame('NCD', KandikoAnitaVentaTipoSupport::tipoVentaAnitaBridge('NCD', '00014', '2', 'A'));
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

    public function test_conciliacion_caea_kandiko_acepta_fak_y_fac_en_anita(): void
    {
        $this->assertTrue(KandikoAnitaVentaTipoSupport::esPvCaeaKandiko('00031', '2', 'A'));
        $this->assertSame('FAC-14041', KandikoAnitaVentaTipoSupport::claveConciliacionDesdeNumero(14041));
        $this->assertTrue(KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv('FAK', '00031', '2', 'A'));
        $this->assertTrue(KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv('FAC', '00031', '2', 'A'));
        $this->assertTrue(KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv('NCD', '00031', '2', 'A'));
        $this->assertFalse(KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv('FAK', '00031', '3', 'C'));
        $this->assertTrue(KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv('FAC', '00031', '3', 'C'));
    }

    public function test_kandiko_excluye_fsl_slots_y_acepta_tipos_gastronomia(): void
    {
        $this->assertFalse(KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv('FSL', '00014', '2', 'A'));
        $this->assertTrue(KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv('FAC', '00014', '2', 'A'));
        $this->assertTrue(KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv('NCD', '00014', '2', 'A'));
        $this->assertTrue(KandikoAnitaVentaTipoSupport::cabeceraAnitaCorrespondeAlPv('NCK', '00015', '2', 'C'));
    }
}
