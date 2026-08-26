<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\RemitoKilosVillafrancaSupport;
use PHPUnit\Framework\TestCase;

class RemitoKilosVillafrancaSupportTest extends TestCase
{
    public function test_signo_fac_y_nd_suman_nc_resta(): void
    {
        $this->assertSame(1, RemitoKilosVillafrancaSupport::signoTipo('FAC'));
        $this->assertSame(1, RemitoKilosVillafrancaSupport::signoTipo('FAA'));
        $this->assertSame(1, RemitoKilosVillafrancaSupport::signoTipo('FCE'));
        $this->assertSame(1, RemitoKilosVillafrancaSupport::signoTipo('NDT'));
        $this->assertSame(1, RemitoKilosVillafrancaSupport::signoTipo('N/D'));
        $this->assertSame(1, RemitoKilosVillafrancaSupport::signoTipo('NDB'));
        $this->assertSame(-1, RemitoKilosVillafrancaSupport::signoTipo('NCD'));
        $this->assertSame(-1, RemitoKilosVillafrancaSupport::signoTipo('N/C'));
        $this->assertSame(-1, RemitoKilosVillafrancaSupport::signoTipo('NCE'));
        $this->assertSame(-1, RemitoKilosVillafrancaSupport::signoTipo('NCG'));
        $this->assertSame(0, RemitoKilosVillafrancaSupport::signoTipo('REM'));
        $this->assertSame(0, RemitoKilosVillafrancaSupport::signoTipo('COB'));
        $this->assertSame(0, RemitoKilosVillafrancaSupport::signoTipo(''));
    }

    public function test_acumula_fac_mas_nd_menos_nc(): void
    {
        $agg = [];
        RemitoKilosVillafrancaSupport::acumularLinea($agg, [
            'compa_articulo' => '0000000000108',
            'compa_cantidad' => 10,
            'compa_pieza' => 10,
            'compa_precio' => 100,
            'compa_desc' => 'Salamin',
            'compa_incl_imp' => 'N',
        ], 1);
        RemitoKilosVillafrancaSupport::acumularLinea($agg, [
            'compa_articulo' => '0000000000108',
            'compa_cantidad' => 2,
            'compa_pieza' => 2,
            'compa_precio' => 100,
            'compa_desc' => 'Salamin',
        ], 1);
        RemitoKilosVillafrancaSupport::acumularLinea($agg, [
            'compa_articulo' => '0000000000108',
            'compa_cantidad' => 3,
            'compa_pieza' => 3,
            'compa_precio' => 100,
            'compa_desc' => 'Salamin',
        ], -1);

        $this->assertSame('108', $agg['108']['sku']);
        $this->assertEquals(9.0, $agg['108']['kilo']);
        $this->assertEquals(9.0, $agg['108']['pieza']);
        $this->assertEquals(100.0, $agg['108']['precio']);
    }

    public function test_excluye_texto_y_aplica_porcentaje(): void
    {
        $this->assertTrue(RemitoKilosVillafrancaSupport::esLineaExcluida('texto'));
        $this->assertTrue(RemitoKilosVillafrancaSupport::esLineaExcluida(''));
        $this->assertFalse(RemitoKilosVillafrancaSupport::esLineaExcluida('108'));

        $items = RemitoKilosVillafrancaSupport::aplicarPorcentaje([
            ['sku' => '108', 'kilo' => 10, 'pieza' => 10, 'caja' => 0],
        ], 20);

        $this->assertCount(1, $items);
        $this->assertEquals(8.0, $items[0]['kilo']);
        $this->assertEquals(8.0, $items[0]['pieza']);
    }
}
