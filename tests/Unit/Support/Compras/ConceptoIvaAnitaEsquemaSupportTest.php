<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ConceptoIvaAnitaEsquemaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Tests\TestCase;

class ConceptoIvaAnitaEsquemaSupportTest extends TestCase
{
    public function test_ferli_omite_tipo_alicuota_y_concciva(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::FERLI]);

        $this->assertFalse(ConceptoIvaAnitaEsquemaSupport::incluyeTipoAlicuotaRetiene());
        $this->assertFalse(ConceptoIvaAnitaEsquemaSupport::leeConcciva());

        $sql = ConceptoIvaAnitaEsquemaSupport::sqlCamposCabecera();
        $this->assertStringContainsString('concc_concepto', $sql);
        $this->assertStringContainsString('concc_cta_haber', $sql);
        $this->assertStringNotContainsString('concc_tipo_conc', $sql);
        $this->assertStringNotContainsString('concc_alicuota_iva', $sql);
        $this->assertStringNotContainsString('concc_retiene_ibr', $sql);
    }

    public function test_agg_incluye_tipo_alicuota_y_concciva(): void
    {
        config(['app.empresa' => EntornoEmpresaSupport::AGG]);

        $this->assertTrue(ConceptoIvaAnitaEsquemaSupport::incluyeTipoAlicuotaRetiene());
        $this->assertTrue(ConceptoIvaAnitaEsquemaSupport::leeConcciva());
        $this->assertStringContainsString('concc_tipo_conc', ConceptoIvaAnitaEsquemaSupport::sqlCamposCabecera());
    }
}
