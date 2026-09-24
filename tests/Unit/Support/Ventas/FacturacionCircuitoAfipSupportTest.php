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
        $this->assertTrue(FacturacionCircuitoAfipSupport::documentoEsExportacion('FAF E-00103-00002047'));
    }

    public function test_fae_con_pv_export_ok_sin_incoterm_falla(): void
    {
        $pv = (object) ['codigo' => '00004', 'nombre' => 'Export', 'modofacturacion' => 'E', 'webservice' => 'wsfex_v1'];
        $tipo = (object) ['abreviatura' => 'FAE', 'codigo' => '019', 'nombre' => 'Factura export'];

        $this->assertNull(FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pv, $tipo, 'E', 'PEX-1', 3));
        $this->assertNotNull(FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pv, $tipo, 'E', 'PEX-1', 0));
    }

    public function test_faf_ferli_con_pv_00103_ok_exige_incoterm(): void
    {
        $pv = (object) ['codigo' => '00103', 'nombre' => 'COMERCIO EXTERIOR', 'modofacturacion' => 'E', 'webservice' => 'wsfex_v1'];
        $faf = (object) ['abreviatura' => 'FAF', 'codigo' => '001', 'nombre' => 'FACTURA'];

        $this->assertTrue(FacturacionCircuitoAfipSupport::esTipoExportacion($faf));
        $this->assertNull(FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pv, $faf, 'E', null, 1));
        $this->assertNotNull(FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pv, $faf, 'E', null, 0));
    }

    public function test_ncd_ferli_en_export_ok_y_en_local_ok(): void
    {
        $pvExp = (object) ['codigo' => '00103', 'nombre' => 'COMERCIO EXTERIOR', 'modofacturacion' => 'E', 'webservice' => 'wsfex_v1'];
        $pvLoc = (object) ['codigo' => '00012', 'nombre' => 'Local', 'modofacturacion' => 'C', 'webservice' => 'wsfev1'];
        $ncd = (object) ['abreviatura' => 'NCD', 'codigo' => '003', 'nombre' => 'Credito'];

        $this->assertFalse(FacturacionCircuitoAfipSupport::esTipoExportacion($ncd));
        $this->assertTrue(FacturacionCircuitoAfipSupport::esTipoPermitidoEnExportacion($ncd));
        $this->assertNull(FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pvExp, $ncd, 'E', null, 0));
        $this->assertNull(FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pvLoc, $ncd, 'A', null, 0));
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

    public function test_faf_en_pv_local_falla(): void
    {
        $pv = (object) ['codigo' => '00012', 'nombre' => 'Local', 'modofacturacion' => 'C', 'webservice' => 'wsfev1'];
        $faf = (object) ['abreviatura' => 'FAF', 'codigo' => '001', 'nombre' => 'FACTURA'];

        $err = FacturacionCircuitoAfipSupport::mensajeErrorSiInvalido($pv, $faf, 'A', null, 1);
        $this->assertNotNull($err);
    }

    public function test_nce_fce_203_no_es_exportacion(): void
    {
        $tipoFce = (object) ['abreviatura' => 'NCE', 'codigo' => '203'];
        $tipoExp = (object) ['abreviatura' => 'NCE', 'codigo' => '021'];

        $this->assertFalse(FacturacionCircuitoAfipSupport::esTipoExportacion($tipoFce));
        $this->assertTrue(FacturacionCircuitoAfipSupport::esTipoExportacion($tipoExp));
    }

    public function test_filtrar_tipos_export_incluye_faf_y_ncd_local_excluye_faf(): void
    {
        $tipos = [
            (object) ['abreviatura' => 'FAC', 'codigo' => '001'],
            (object) ['abreviatura' => 'FAF', 'codigo' => '001'],
            (object) ['abreviatura' => 'NCD', 'codigo' => '003'],
            (object) ['abreviatura' => 'FAE', 'codigo' => '019'],
        ];

        $exp = FacturacionCircuitoAfipSupport::filtrarTiposPorCircuito(
            $tipos,
            FacturacionCircuitoAfipSupport::CIRCUITO_EXPORTACION
        );
        $abrevsExp = array_map(static fn ($t) => $t->abreviatura, $exp);
        $this->assertContains('FAF', $abrevsExp);
        $this->assertContains('NCD', $abrevsExp);
        $this->assertContains('FAE', $abrevsExp);
        $this->assertNotContains('FAC', $abrevsExp);

        $loc = FacturacionCircuitoAfipSupport::filtrarTiposPorCircuito(
            $tipos,
            FacturacionCircuitoAfipSupport::CIRCUITO_LOCAL
        );
        $abrevsLoc = array_map(static fn ($t) => $t->abreviatura, $loc);
        $this->assertContains('FAC', $abrevsLoc);
        $this->assertContains('NCD', $abrevsLoc);
        $this->assertNotContains('FAF', $abrevsLoc);
        $this->assertNotContains('FAE', $abrevsLoc);
    }
}
