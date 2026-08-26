<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use App\Support\Stock\RecepcionProveedorAnitaReferenciaSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorAnitaReferenciaSupportTest extends TestCase
{
    public function test_factura_con_guion_no_desborda_integer(): void
    {
        $ref = RecepcionProveedorAnitaReferenciaSupport::referenciaDesdeTexto('FAC 00002-00000821', 'A');

        $this->assertSame('FAC', $ref['tipo']);
        $this->assertSame(2, $ref['sucursal']);
        $this->assertSame(821, $ref['nro']);
        $this->assertLessThanOrEqual(RecepcionProveedorAnitaReferenciaSupport::INFORMIX_INTEGER_MAX, $ref['nro']);
    }

    public function test_bloque_de_digitos_afip_usa_ultimos_8_como_nro(): void
    {
        $ref = RecepcionProveedorAnitaReferenciaSupport::referenciaDesdeTexto('00134000386366', 'A');

        $this->assertSame('FAC', $ref['tipo']);
        $this->assertSame(1340, $ref['sucursal']);
        $this->assertSame(386366, $ref['nro']);
        $this->assertLessThanOrEqual(RecepcionProveedorAnitaReferenciaSupport::INFORMIX_INTEGER_MAX, $ref['nro']);
    }

    public function test_cae_o_numero_largo_no_se_envia_entero_fuera_de_rango(): void
    {
        $ref = RecepcionProveedorAnitaReferenciaSupport::referenciaDesdeTexto('74101234567890', 'A');

        $this->assertSame(34567890, $ref['nro']);
        $this->assertSame(41012, $ref['sucursal']);
        $this->assertLessThanOrEqual(RecepcionProveedorAnitaReferenciaSupport::INFORMIX_INTEGER_MAX, $ref['nro']);
        $this->assertSame('0', RecepcionProveedorAnitaEscrituraSupport::enteroSql(74101234567890));
        $this->assertSame('821', RecepcionProveedorAnitaEscrituraSupport::enteroSql(821));
    }
}
