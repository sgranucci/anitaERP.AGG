<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\ConceptoIvacompraNombreIaAprendizajeSupport;
use PHPUnit\Framework\TestCase;

class ConceptoIvacompraNombreIaAprendizajeSupportTest extends TestCase
{
    private ConceptoIvacompraNombreIaAprendizajeSupport $support;

    protected function setUp(): void
    {
        parent::setUp();
        $this->support = new ConceptoIvacompraNombreIaAprendizajeSupport;
    }

    public function test_acepta_rotulos_cortos_del_pie(): void
    {
        $this->assertSame('Importe Neto Gravado', $this->support->sanearRotulo('Importe Neto Gravado: $'));
        $this->assertSame('IVA 21%', $this->support->sanearRotulo('IVA 21%: $'));

        $this->assertTrue($this->support->esRotuloPie('Importe Neto Gravado', ['neto']));
        $this->assertTrue($this->support->esRotuloPie('IVA 21%', ['iva']));
        $this->assertTrue($this->support->esRotuloPie('Percepcion IIBB ARBA', ['percepcion_iibb']));
    }

    public function test_rechaza_descripcion_de_item(): void
    {
        $largo = 'Por Locacion de Inmueble ubicado en Chilavert 6452 CABA';
        $this->assertNull($this->support->sanearRotulo($largo));
        $this->assertFalse($this->support->esRotuloPie(
            'Por Locacion de Inmueble',
            ['neto']
        ));
        $this->assertFalse($this->support->esRotuloPie('Subtotal c/IVA', []));
    }

    public function test_no_duplica_alias_existente(): void
    {
        $actual = 'Gravado 21%, Neto gravado, Neto';
        $r = $this->support->mergeAliasSiCabe($actual, 'Neto gravado');
        $this->assertFalse($r['agrego']);
        $this->assertSame('duplicado', $r['motivo']);
        $this->assertSame($actual, $r['nombre_ia']);
    }

    public function test_agrega_alias_nuevo(): void
    {
        $actual = 'Gravado 21%, Neto';
        $r = $this->support->mergeAliasSiCabe($actual, 'Importe Neto Gravado');
        $this->assertTrue($r['agrego']);
        $this->assertSame('Gravado 21%, Neto, Importe Neto Gravado', $r['nombre_ia']);
    }

    public function test_no_supera_255(): void
    {
        // ~241 chars (como en producción), sin espacio para un rótulo de pie típico.
        $base = implode(', ', array_fill(0, 17, 'AliasLargoXX'));
        $nuevo = 'Importe Neto Gravado AFIP Pie';
        $this->assertTrue(mb_strlen($base) > 200 && mb_strlen($base) <= ConceptoIvacompraNombreIaAprendizajeSupport::MAX_NOMBRE_IA);
        $this->assertTrue(mb_strlen($base.', '.$nuevo) > ConceptoIvacompraNombreIaAprendizajeSupport::MAX_NOMBRE_IA);

        $r = $this->support->mergeAliasSiCabe($base, $nuevo);
        $this->assertFalse($r['agrego']);
        $this->assertSame('sin_espacio_255', $r['motivo'] ?? null);
        $this->assertSame($base, $r['nombre_ia']);
    }

    public function test_no_parte_alicuota_con_coma(): void
    {
        $actual = 'IVA 10,5%, I.V.A. INSCRIPTO';
        $r = $this->support->mergeAliasSiCabe($actual, 'IVA 10.5%');
        $this->assertTrue($r['agrego']);
        $this->assertStringContainsString('IVA 10,5%', $r['nombre_ia']);
        $this->assertStringContainsString('IVA 10.5%', $r['nombre_ia']);
    }
}
