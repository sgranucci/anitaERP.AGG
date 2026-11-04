<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorConceptosHeuristicaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorImporteParserSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorPieTotalesSupport;
use PHPUnit\Framework\TestCase;

/**
 * Factura clásica Cianbox / MIX-57: pie «Gravado / I.V.A. 21% / Total» en u$s
 * y encabezado «I.V.A. Responsable Inscripto … CUIT» que no debe ser concepto.
 */
class FacturaProveedorPieCianboxMix57Test extends TestCase
{
    private const TEXTO = <<<'TXT'
Sr./es CALZADOS FERLI SA                                                        Código 4851
I.V.A. Responsable Inscripto                                                     CUIT 30551587300
Tel.: 44421587 ** +54 9 11 3215-2937
No Gravado                     u$s 0,00
Exento                     u$s 0,00
Gravado                   u$s 730,58
Redondeo                     u$s 0,00
AL SOLO EFECTO IMPOSITIVO, EL TIPO DE CAMBIO APLICADO ES DE                                                       I.V.A. 21,00%                   u$s 153,42
$1.570,00 POR CADA DOLAR - Transporte: ENVIAMELO
Total             u$s 884,00
C.A.E. Nº 86440815408698
TXT;

    public function test_pie_reconoce_gravado_iva_y_total_en_dolares(): void
    {
        $pie = $this->pie()->extraer(self::TEXTO);

        $this->assertSame(730.58, $pie['subtotal']);
        $this->assertSame(153.42, $pie['iva']);
        $this->assertSame(884.0, $pie['total']);
        $this->assertSame('pie_pares', $pie['origen']);

        $suma = round(array_sum(array_column($pie['lineas'], 'importe')), 2);
        $this->assertEqualsWithDelta(884.0, $suma, 0.01);
    }

    public function test_conceptos_no_toman_cuit_como_iva(): void
    {
        $conceptos = $this->conceptos()->extraer(self::TEXTO, 884.0);

        foreach ($conceptos as $linea) {
            $this->assertLessThan(
                1_000_000,
                (float) $linea['importe'],
                'No debe aparecer el CUIT 30551587300 como importe: '.json_encode($linea)
            );
            $this->assertStringNotContainsStringIgnoringCase(
                'responsable inscripto',
                (string) $linea['descripcion']
            );
        }

        $porTipo = [];
        foreach ($conceptos as $linea) {
            $porTipo[$linea['tipo']] = ($porTipo[$linea['tipo']] ?? 0) + (float) $linea['importe'];
        }

        $this->assertEqualsWithDelta(730.58, $porTipo['neto'] ?? 0, 0.01);
        $this->assertEqualsWithDelta(153.42, $porTipo['iva'] ?? 0, 0.01);
    }

    public function test_parser_rechaza_cuit_como_importe(): void
    {
        $parser = new FacturaProveedorImporteParserSupport();

        $this->assertNull($parser->parsear('30551587300'));
        $this->assertNull($parser->parsear('30-55158730-0'));
        $this->assertNull($parser->importeAlFinalDeLinea(
            'I.V.A. Responsable Inscripto                                                     CUIT 30551587300'
        ));
        $this->assertSame(730.58, $parser->parsear('u$s 730,58'));
        $this->assertSame(153.42, $parser->importeAlFinalDeLinea('I.V.A. 21,00%                   u$s 153,42'));
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
