<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorConceptosHeuristicaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorImporteParserSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorPieTotalesSupport;
use PHPUnit\Framework\TestCase;

/**
 * Factura Simmons: pie «SUBTOTAL / IVA (21%) / TOTAL» a veces en una sola línea OCR.
 * No debe sumar IVA+TOTAL ni mapear el bruto como IVA.
 */
class FacturaProveedorSimmonsPieIvaTest extends TestCase
{
    private const TEXTO = <<<'TXT'
SIMMONS DE ARGENTINA S.A.I.C.
FACTURA A 0841-00001124
CUIT: 30-55158730-0
SRES.: CALZADOS FERLI S A
CUIT: 30-55158730-0
SUBTOTAL: $7765807.41
IVA: (21.00%): $1630819.56 TOTAL: $9396620.97
OTRO TEXTO
TOTAL: $9395626.97
IVA: (21.00%): $1630819.56
TXT;

    public function test_conceptos_no_suman_iva_con_total_en_misma_linea(): void
    {
        $conceptos = $this->conceptos()->extraer(self::TEXTO, 9396626.97);

        $porTipo = [];
        foreach ($conceptos as $linea) {
            $porTipo[$linea['tipo']] = ($porTipo[$linea['tipo']] ?? 0) + (float) $linea['importe'];
        }

        $this->assertEqualsWithDelta(7765807.41, $porTipo['neto'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(1630819.56, $porTipo['iva'] ?? 0, 0.01);
        $this->assertLessThan(
            2_000_000,
            $porTipo['iva'] ?? 0,
            'No debe quedar IVA = TOTAL ni IVA+TOTAL: '.json_encode($conceptos)
        );
    }

    public function test_pie_reconoce_subtotal_iva_y_total(): void
    {
        $pie = $this->pie()->extraer(self::TEXTO);

        $this->assertEqualsWithDelta(7765807.41, (float) $pie['subtotal'], 0.01);
        $this->assertEqualsWithDelta(1630819.56, (float) $pie['iva'], 0.01);
        $this->assertNotEmpty($pie['lineas']);

        $ivaLinea = null;
        foreach ($pie['lineas'] as $linea) {
            if (($linea['tipo'] ?? '') === 'iva') {
                $ivaLinea = $linea;
                break;
            }
        }
        $this->assertNotNull($ivaLinea);
        $this->assertEqualsWithDelta(1630819.56, (float) $ivaLinea['importe'], 0.01);
        $this->assertEqualsWithDelta(21.0, (float) ($ivaLinea['alicuota_iva'] ?? 0), 0.01);
    }

    public function test_detecta_importe_iva_antes_del_total(): void
    {
        $parser = new FacturaProveedorImporteParserSupport();
        $support = new FacturaProveedorConceptosHeuristicaSupport($parser);
        $ref = new \ReflectionClass($support);
        $m = $ref->getMethod('detectarEnLinea');
        $m->setAccessible(true);

        $linea = $m->invoke($support, 'IVA: (21.00%): $1630819.56 TOTAL: $9396620.97');
        $this->assertIsArray($linea);
        $this->assertSame('iva', $linea['tipo']);
        $this->assertEqualsWithDelta(1630819.56, (float) $linea['importe'], 0.01);
        $this->assertEqualsWithDelta(21.0, (float) $linea['alicuota_iva'], 0.01);
    }

    private function pie(): FacturaProveedorPieTotalesSupport
    {
        return new FacturaProveedorPieTotalesSupport(new FacturaProveedorImporteParserSupport());
    }

    private function conceptos(): FacturaProveedorConceptosHeuristicaSupport
    {
        return new FacturaProveedorConceptosHeuristicaSupport(new FacturaProveedorImporteParserSupport());
    }
}
