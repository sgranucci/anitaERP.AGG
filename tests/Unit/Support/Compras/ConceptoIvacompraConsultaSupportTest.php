<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ConceptoIvacompraConsultaSupport;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ConceptoIvacompraConsultaSupportTest extends TestCase
{
    public function test_firma_listar_acepta_numero_oc_opcional(): void
    {
        $m = new ReflectionMethod(ConceptoIvacompraConsultaSupport::class, 'listarPorTipoTransaccion');
        $params = $m->getParameters();
        $this->assertCount(3, $params);
        $this->assertSame('numeroOc', $params[2]->getName());
        $this->assertTrue($params[2]->allowsNull());
    }

    public function test_hermano_indirecto_solo_para_tipo_directo(): void
    {
        $this->assertSame('FIB', ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('FDB'));
        $this->assertSame('FIL', ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('fdl'));
        $this->assertSame('CIB', ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('CDB'));
        $this->assertNull(ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('FIB'));
        $this->assertNull(ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('FDS'));
        $this->assertNull(ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('FNB'));
        $this->assertNull(ConceptoIvacompraConsultaSupport::abreviaturaHermanoIndirecto('FGA'));
    }

    public function test_ids_concepto_tipo_invalido_vacio(): void
    {
        $this->assertSame([], ConceptoIvacompraConsultaSupport::idsConceptoParaTipo(0));
        $this->assertSame([], ConceptoIvacompraConsultaSupport::idsConceptoParaTipo(-1, '223960'));
    }
}
