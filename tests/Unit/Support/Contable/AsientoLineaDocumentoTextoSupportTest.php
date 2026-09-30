<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoLineaDocumentoTextoSupport;
use PHPUnit\Framework\TestCase;

class AsientoLineaDocumentoTextoSupportTest extends TestCase
{
    public function test_lee_comprobante_con_tipo_letra_y_numero(): void
    {
        $doc = AsientoLineaDocumentoTextoSupport::comprobante('FC A0001-00001234');

        $this->assertTrue($doc['valido']);
        $this->assertFalse($doc['vacio']);
        $this->assertSame('FC', $doc['anita_tipo']);
        $this->assertSame('A', $doc['anita_letra']);
        $this->assertSame(1, $doc['anita_sucursal']);
        $this->assertSame(1234, $doc['anita_nro']);
    }

    public function test_lee_comprobante_solo_numero(): void
    {
        $doc = AsientoLineaDocumentoTextoSupport::comprobante('FC 55821');

        $this->assertSame('FC', $doc['anita_tipo']);
        $this->assertSame(55821, $doc['anita_nro']);
        $this->assertSame(0, $doc['anita_sucursal']);
    }

    public function test_comprobante_vacio(): void
    {
        $doc = AsientoLineaDocumentoTextoSupport::comprobante('  ');

        $this->assertTrue($doc['vacio']);
        $this->assertNull($doc['anita_nro']);
    }

    public function test_comprobante_sin_numero_no_es_valido(): void
    {
        $doc = AsientoLineaDocumentoTextoSupport::comprobante('factura');

        $this->assertFalse($doc['valido']);
        $this->assertNull($doc['anita_nro']);
    }

    public function test_orden_de_compra_toma_los_digitos(): void
    {
        $this->assertSame(45210, AsientoLineaDocumentoTextoSupport::ordenCompra('OC 45210'));
        $this->assertNull(AsientoLineaDocumentoTextoSupport::ordenCompra(''));
    }

    public function test_desde_request_prioriza_el_texto_de_la_linea(): void
    {
        $doc = AsientoLineaDocumentoTextoSupport::desdeRequest([
            'comprobante_linea' => ['FC A0001-10'],
            'ordencompra_linea' => ['99'],
            'mov_anita_nro' => [1],
        ], 0);

        $this->assertSame(10, $doc['anita_nro']);
        $this->assertSame(99, $doc['nro_ordencompra']);
    }
}
