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
}
