<?php

namespace Tests\Unit\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorCabeceraHeuristicaSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorExtraccionFusionSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorImporteParserSupport;
use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorMonedaOcrSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrNumeroOcExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Banda AFIP: «Fecha de Vto. para el pago» (De Vito A-00002-00001117 y similares).
 */
class FacturaProveedorCabeceraVencimientoAfipTest extends TestCase
{
    public function test_fecha_de_vto_para_el_pago_misma_linea(): void
    {
        $texto = <<<'TXT'
Período Facturado Desde: 01/11/2026    Hasta: 30/11/2026    Fecha de Vto. para el pago: 30/11/2026
CUIT: 30551587300
CAE N°: 86463142519889
Fecha de Vencimiento: 19/11/2026
TXT;

        $cabecera = $this->cabecera()->extraer($texto);

        $this->assertSame('2026-11-30', $cabecera['fecha_vencimiento']);
        $this->assertSame('2026-11-19', $cabecera['fecha_vto_cai_cae']);
    }

    public function test_fecha_de_vto_para_el_pago_valores_en_linea_siguiente(): void
    {
        $texto = <<<'TXT'
Período Facturado Desde:     Hasta:     Fecha de Vto. para el pago:
01/11/2026                   30/11/2026  30/11/2026
Condición de Venta: Contado
TXT;

        $cabecera = $this->cabecera()->extraer($texto);

        $this->assertSame('2026-11-30', $cabecera['fecha_vencimiento']);
    }

    public function test_fusion_prioriza_heuristica_de_vencimiento_pago(): void
    {
        $fusion = (new FacturaProveedorExtraccionFusionSupport)->fusionar(
            ['fecha_vencimiento' => '2026-11-30'],
            ['fecha_vencimiento' => '2026-11-19'],
        );

        $this->assertSame('2026-11-30', $fusion['fecha_vencimiento']);
    }

    private function cabecera(): FacturaProveedorCabeceraHeuristicaSupport
    {
        return new FacturaProveedorCabeceraHeuristicaSupport(
            new FacturaProveedorImporteParserSupport(),
            new RecepcionProveedorOcrNumeroOcExtractor(),
            new FacturaProveedorMonedaOcrSupport(),
        );
    }
}
