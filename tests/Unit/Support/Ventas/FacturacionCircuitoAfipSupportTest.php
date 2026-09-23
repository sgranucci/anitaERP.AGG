<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\FacturacionCircuitoAfipSupport;
use Tests\TestCase;

final class FacturacionCircuitoAfipSupportTest extends TestCase
{
    public function test_pex_y_letra_e_son_exportacion(): void
    {
        $this->assertSame(
            FacturacionCircuitoAfipSupport::CIRCUITO_EXPORTACION,
            FacturacionCircuitoAfipSupport::resolverCircuito(null, null, 'E', 'PEX-X-00001-00000169')
        );
        $this->assertTrue(FacturacionCircuitoAfipSupport::documentoEsExportacion('PED-A-00001-PEX-9'));
    }

    public function test_fae_con_pv_export_ok_sin_incoterm_falla(): void
    {
        $pv = (object) ['codigo' => '00004', 'nombre' => 'Export', 'modofacturacion' => 'E', 'webservice' => 'wsfex_v1'];
        $tipo = (object) ['abreviatura' => 'FAE', 'codigo' => '019', 'nombre' => 'Factura export'];

        $this->assertNull(FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pv, $tipo, 'E', 'PEX-1', 3));
        $this->assertNotNull(FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pv, $tipo, 'E', 'PEX-1', 0));
    }

    public function test_fac_en_pv_export_falla(): void
    {
        $pv = (object) ['codigo' => '00004', 'nombre' => 'Export', 'modofacturacion' => 'E', 'webservice' => 'wsfex_v1'];
        $tipo = (object) ['abreviatura' => 'FAC', 'codigo' => '001', 'nombre' => 'Factura'];

        $err = FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pv, $tipo, 'A', null, 0);
        $this->assertNotNull($err);
        $this->assertStringContainsString('exportación', mb_strtolower($err));
    }

    public function test_fae_en_pv_local_falla(): void
    {
        $pv = (object) ['codigo' => '00005', 'nombre' => 'Local', 'modofacturacion' => 'C', 'webservice' => 'wsfev1'];
        $tipo = (object) ['abreviatura' => 'FAE', 'codigo' => '019', 'nombre' => 'Factura export'];

        $err = FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pv, $tipo, 'E', 'PEX-1', 1);
        $this->assertNotNull($err);
    }

    public function test_nce_fce_203_no_es_exportacion(): void
    {
        $tipoFce = (object) ['abreviatura' => 'NCE', 'codigo' => '203'];
        $tipoExp = (object) ['abreviatura' => 'NCE', 'codigo' => '021'];

        $this->assertFalse(FacturacionCircuitoAfipSupport::esTipoExportacion($tipoFce));
        $this->assertTrue(FacturacionCircuitoAfipSupport::esTipoExportacion($tipoExp));
    }
}
