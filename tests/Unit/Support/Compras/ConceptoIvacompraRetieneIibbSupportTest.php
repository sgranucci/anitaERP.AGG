<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ConceptoIvacompraRetieneIibbSupport;
use Tests\TestCase;

class ConceptoIvacompraRetieneIibbSupportTest extends TestCase
{
    public function test_precio_de_compra_entra_a_la_base(): void
    {
        $this->assertSame('S', ConceptoIvacompraRetieneIibbSupport::cuandoAnitaNoTraeMarca('1', 'C', 'N'));
        $this->assertSame('S', ConceptoIvacompraRetieneIibbSupport::cuandoAnitaNoTraeMarca('2', 'C', 'G'));
        $this->assertSame('S', ConceptoIvacompraRetieneIibbSupport::cuandoAnitaNoTraeMarca('11', 'C', 'G'));
        $this->assertSame('S', ConceptoIvacompraRetieneIibbSupport::cuandoAnitaNoTraeMarca('205', 'C', 'G'));
    }

    public function test_monotributo_entra_porque_el_comprobante_no_discrimina_iva(): void
    {
        $this->assertSame('S', ConceptoIvacompraRetieneIibbSupport::cuandoAnitaNoTraeMarca('4', 'D', 'N'));
    }

    public function test_iva_percepciones_y_retenciones_quedan_afuera(): void
    {
        $this->assertSame('N', ConceptoIvacompraRetieneIibbSupport::cuandoAnitaNoTraeMarca('101', 'I', 'I'));
        $this->assertSame('N', ConceptoIvacompraRetieneIibbSupport::cuandoAnitaNoTraeMarca('103', 'O', 'N'));
        $this->assertSame('N', ConceptoIvacompraRetieneIibbSupport::cuandoAnitaNoTraeMarca('201', 'O', 'N'));
        $this->assertSame('N', ConceptoIvacompraRetieneIibbSupport::cuandoAnitaNoTraeMarca('5', 'O', 'N'));
        $this->assertSame('N', ConceptoIvacompraRetieneIibbSupport::cuandoAnitaNoTraeMarca('21', 'D', 'N'));
    }
}
