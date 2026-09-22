<?php

namespace Tests\Unit\Support\Contable\LibroIvaDigital;

use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Concepto_Ivacompra;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalConceptoIvacompraSupport;
use PHPUnit\Framework\TestCase;

class LibroIvaDigitalConceptoIvacompraExentoNegativoTest extends TestCase
{
    public function test_exento_y_descuento_81_conservan_signo_negativo(): void
    {
        $totales = LibroIvaDigitalConceptoIvacompraSupport::desglosarComprobante([
            $this->concepto('G', 1000.0, '50', 21.0),
            $this->concepto('E', -100.0, '81'),
            $this->concepto('I', 189.0, '503', 21.0),
        ], 'A');

        $this->assertEqualsWithDelta(-100.0, $totales['exento'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $totales['neto_gravado'], 0.001);
        $this->assertEqualsWithDelta(189.0, $totales['iva'], 0.001);
    }

    public function test_descuento_gravado_80_netea_el_neto_informable(): void
    {
        $totales = LibroIvaDigitalConceptoIvacompraSupport::desglosarComprobante([
            $this->concepto('G', 1000.0, '50', 21.0),
            $this->concepto('G', -80.0, '80', 21.0),
            $this->concepto('I', 193.2, '503', 21.0),
        ], 'A');

        $this->assertEqualsWithDelta(920.0, $totales['neto_gravado'], 0.001);
        $this->assertEqualsWithDelta(920.0, $totales['alicuotas'][0]['neto'], 0.001);
    }

    private function concepto(string $tipo, float $monto, string $codigo, float $tasa = 0.0): Comprobante_Proveedor_Concepto
    {
        $ci = new Concepto_Ivacompra([
            'codigo' => $codigo,
            'nombre' => 'Test '.$codigo,
            'tipoconcepto' => $tipo,
        ]);
        $ci->setRelation('impuestos', (object) ['valor' => $tasa]);

        $linea = new Comprobante_Proveedor_Concepto(['monto' => $monto]);
        $linea->setRelation('concepto_ivacompras', $ci);

        return $linea;
    }
}
