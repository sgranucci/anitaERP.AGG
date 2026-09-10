<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor;

use App\Support\Compras\PrecargaProveedor\PrecargaProveedorAbreviaturaTipoSupport;
use PHPUnit\Framework\TestCase;

class PrecargaProveedorAbreviaturaTipoSupportTest extends TestCase
{
    public function test_cc_gastronomia_fuerza_fga(): void
    {
        $this->assertSame(
            'FGA',
            PrecargaProveedorAbreviaturaTipoSupport::abreviatura('FC', 85, 'Directo', 'B')
        );
    }

    public function test_cc_logistica_indirecto_bienes_es_fib(): void
    {
        $this->assertSame(
            'FIB',
            PrecargaProveedorAbreviaturaTipoSupport::abreviatura('FC', 103, 'Indirecto', 'B')
        );
    }

    public function test_cc_104_es_feg(): void
    {
        $this->assertSame(
            'FEG',
            PrecargaProveedorAbreviaturaTipoSupport::abreviatura('FC', 104, 'Indirecto', 'B')
        );
    }

    public function test_servicio_con_iva_indirecto_es_fis(): void
    {
        $this->assertSame(
            'FIS',
            PrecargaProveedorAbreviaturaTipoSupport::abreviatura('FC', 103, 'Indirecto', 'S')
        );
    }

    public function test_nota_credito_respeta_inicial(): void
    {
        $this->assertSame(
            'CIB',
            PrecargaProveedorAbreviaturaTipoSupport::abreviatura('NC', 103, 'Indirecto', 'B')
        );
    }

    public function test_oc_con_varios_cc_incluye_fib_y_fga(): void
    {
        $porFamilia = PrecargaProveedorAbreviaturaTipoSupport::abreviaturasFinoDesdeCentros([
            ['codigo' => 103, 'tipoiva' => 'Indirecto'],
            ['codigo' => 85, 'tipoiva' => 'Directo'],
        ], 'B', false);

        $this->assertContains('FIB', $porFamilia['FC']);
        $this->assertContains('FGA', $porFamilia['FC']);
        $this->assertContains('CIB', $porFamilia['NC']);
        $this->assertContains('CGA', $porFamilia['NC']);
    }

    public function test_siempre_ofrece_gastronomia_aunque_la_oc_no_tenga_cc_85(): void
    {
        $porFamilia = PrecargaProveedorAbreviaturaTipoSupport::abreviaturasFinoDesdeCentros([
            ['codigo' => 103, 'tipoiva' => 'Indirecto'],
        ], 'B', true);

        $this->assertContains('FIB', $porFamilia['FC']);
        $this->assertContains('FGA', $porFamilia['FC']);
        $this->assertContains('CGA', $porFamilia['NC']);
        $this->assertContains('DGA', $porFamilia['ND']);
    }

    public function test_fc_nc_nd_son_tipos_genericos(): void
    {
        $this->assertTrue(PrecargaProveedorAbreviaturaTipoSupport::esTipoGenerico('FC'));
        $this->assertTrue(PrecargaProveedorAbreviaturaTipoSupport::esTipoGenerico('nc'));
        $this->assertFalse(PrecargaProveedorAbreviaturaTipoSupport::esTipoGenerico('FGA'));
        $this->assertFalse(PrecargaProveedorAbreviaturaTipoSupport::esTipoGenerico('FIB'));
    }
}
