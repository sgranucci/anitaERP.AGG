<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\ConceptoCashflowResolverSupport;
use PHPUnit\Framework\TestCase;

class ConceptoCashflowResolverSupportTest extends TestCase
{
    public function test_el_concepto_iva_no_clasifica_cash_flow(): void
    {
        $this->assertTrue(ConceptoCashflowResolverSupport::nombreEsConceptoIva('IVA'));
        $this->assertTrue(ConceptoCashflowResolverSupport::nombreEsConceptoIva(' iva '));
        $this->assertFalse(ConceptoCashflowResolverSupport::nombreEsConceptoIva('RET Y PERC DE IVA DE 3ROS'));
        $this->assertFalse(ConceptoCashflowResolverSupport::nombreEsConceptoIva('MANTENIMIENTO DE EDIFICIO'));
    }
}
