<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorAnitaEsquemaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Tests\TestCase;

class ProveedorAnitaEsquemaSupportTest extends TestCase
{
    public function test_interforming_usa_campos_sin_columnas_agg(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::INTERFORMING]);
        config(['proveedor.filtro_empresa' => false]);

        $this->assertSame(
            ProveedorAnitaEsquemaSupport::VARIANTE_INTERFORMING,
            ProveedorAnitaEsquemaSupport::variante()
        );
        $this->assertFalse(ProveedorAnitaEsquemaSupport::esEsquemaAgg());
        $this->assertFalse(ProveedorAnitaEsquemaSupport::leeTablasHijasAgg());

        $campos = ProveedorAnitaEsquemaSupport::camposPromaeLectura();
        $this->assertStringContainsString('prom_nombre', $campos);
        $this->assertStringContainsString('prom_cod_ret_suss', $campos);
        $this->assertStringNotContainsString('prom_cta_cont_me', $campos);
        $this->assertStringNotContainsString('prom_ag_perc_iva', $campos);
        $this->assertStringNotContainsString('prom_ret_ibr_bsas', $campos);
        $this->assertStringNotContainsString('prom_fe_ini_excl', $campos);

        $preview = ProveedorAnitaEsquemaSupport::camposPromaeExclusionPreview();
        $this->assertStringNotContainsString('prom_fe_ini_excl', $preview);
        $this->assertStringContainsString('prom_excl_retiva', $preview);
    }

    public function test_agg_incluye_columnas_extendidas(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::AGG]);
        config(['proveedor.filtro_empresa' => false]);

        $this->assertSame(ProveedorAnitaEsquemaSupport::VARIANTE_AGG, ProveedorAnitaEsquemaSupport::variante());
        $this->assertTrue(ProveedorAnitaEsquemaSupport::leeTablasHijasAgg());
        $campos = ProveedorAnitaEsquemaSupport::camposPromaeLectura();
        $this->assertStringContainsString('prom_cta_cont_me', $campos);
        $this->assertStringContainsString('prom_ag_perc_iva', $campos);
    }

    public function test_ferli_usa_promae_hasta_concepto_sin_hijas_agg(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::FERLI]);
        config(['proveedor.filtro_empresa' => false]);

        $this->assertSame(ProveedorAnitaEsquemaSupport::VARIANTE_FERLI, ProveedorAnitaEsquemaSupport::variante());
        $this->assertTrue(ProveedorAnitaEsquemaSupport::esFerli());
        $this->assertFalse(ProveedorAnitaEsquemaSupport::esEsquemaAgg());
        $this->assertFalse(ProveedorAnitaEsquemaSupport::leeTablasHijasAgg());

        $campos = ProveedorAnitaEsquemaSupport::camposPromaeLectura();
        $this->assertStringContainsString('prom_cta_cont_me', $campos);
        $this->assertStringContainsString('prom_concepto', $campos);
        $this->assertStringContainsString('prom_cod_ret_suss', $campos);
        $this->assertStringNotContainsString('prom_ag_perc_iva', $campos);
        $this->assertStringNotContainsString('prom_descuento', $campos);
        $this->assertStringNotContainsString('prom_ret_ibr_bsas', $campos);
        $this->assertStringNotContainsString('prom_fe_ini_excl', $campos);

        $permitidas = ProveedorAnitaEsquemaSupport::columnasPromaePermitidasEnEscritura();
        $this->assertCount(50, $permitidas);
        $this->assertSame('prom_concepto', $permitidas[49]);
        $this->assertNotContains('prom_fecha_exclib', $permitidas);
        $this->assertNotContains('prom_excl_retib', $permitidas);
        $this->assertNotContains('prom_fe_ini_excl', $permitidas);
        $this->assertNotContains('prom_fe_ini_exclib', $permitidas);
        $this->assertNotContains('prom_ag_perc_ib', $permitidas);
        $this->assertNotContains('prom_ag_perc_iva', $permitidas);
        $this->assertNotContains('prom_descuento', $permitidas);
        $this->assertContains('prom_prov_vario', $permitidas);
        $this->assertContains('prom_regimen', $permitidas);
        $this->assertFalse(ProveedorAnitaEsquemaSupport::escribeTablasHijasAgg());
    }

    public function test_agg_escribe_columnas_extendidas_de_promae(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::AGG]);
        config(['proveedor.filtro_empresa' => false]);

        $this->assertSame([], ProveedorAnitaEsquemaSupport::columnasPromaeOmitidasEnEscritura());
        $this->assertNull(ProveedorAnitaEsquemaSupport::columnasPromaePermitidasEnEscritura());
        $this->assertTrue(ProveedorAnitaEsquemaSupport::escribeTablasHijasAgg());
    }
}
