<?php

namespace Tests\Unit\Support\Contable\LibroIvaDigital;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalConceptoIvacompraSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalIvaSimpleSupport;
use PHPUnit\Framework\TestCase;

class LibroIvaDigitalIvaSimpleSupportTest extends TestCase
{
    public function test_credito_parea_neto_e_iva_de_la_misma_alicuota(): void
    {
        $acum = [];
        LibroIvaDigitalIvaSimpleSupport::acumularCredito($acum, [
            'concepto_iva_simple' => 3,
            'tasa' => 21,
            'neto' => 100.0,
            'iva' => 0.0,
        ]);
        LibroIvaDigitalIvaSimpleSupport::acumularCredito($acum, [
            'concepto_iva_simple' => 3,
            'tasa' => 21,
            'neto' => 0.0,
            'iva' => 21.0,
        ]);

        $this->assertCount(1, $acum);
        $armado = LibroIvaDigitalIvaSimpleSupport::lineasDesdeAcumuladoCredito($acum);
        $this->assertSame(['3;5;100,00;21,00;21,00;'], $armado['lineas']);
        $this->assertSame(100.0, $armado['detalle'][0]['neto']);
        $this->assertSame(21.0, $armado['detalle'][0]['iva']);
    }

    public function test_concepto_iva_simple_sale_del_nombre_de_gravado_no_del_iva(): void
    {
        $this->assertSame(3, LibroIvaDigitalConceptoIvacompraSupport::conceptoIvaSimpleDesdeNombre('Servicios gravados 21%'));
        $this->assertSame(1, LibroIvaDigitalConceptoIvacompraSupport::conceptoIvaSimpleDesdeNombre('IVA 21%'));
        $this->assertSame(2, LibroIvaDigitalConceptoIvacompraSupport::conceptoIvaSimpleDesdeNombre('Locación de inmueble'));
        $this->assertSame(4, LibroIvaDigitalConceptoIvacompraSupport::conceptoIvaSimpleDesdeNombre('Bienes de uso'));
    }

    public function test_prorrateo_global_deja_iva_computable_en_cero(): void
    {
        $acum = [];
        LibroIvaDigitalIvaSimpleSupport::acumularCredito($acum, [
            'concepto' => 1,
            'tasa' => 21,
            'neto' => 100.0,
            'iva' => 21.0,
        ], true);

        $armado = LibroIvaDigitalIvaSimpleSupport::lineasDesdeAcumuladoCredito($acum);
        $this->assertSame(['1;5;100,00;21,00;;'], $armado['lineas']);
        $this->assertSame(0.0, $armado['detalle'][0]['iva_computable']);
    }

    public function test_resumen_por_concepto_separa_restitucion(): void
    {
        $credito = [[
            'concepto' => 1,
            'neto' => 100.0,
            'iva' => 21.0,
            'iva_computable' => 21.0,
        ]];
        $restitucion = [[
            'concepto' => 1,
            'neto' => 10.0,
            'iva' => 2.1,
        ]];

        $resumen = LibroIvaDigitalIvaSimpleSupport::resumenPorConcepto($credito, $restitucion);
        $this->assertCount(1, $resumen);
        $this->assertSame('Bienes', $resumen[0]['concepto_nombre']);
        $this->assertSame(1, $resumen[0]['renglones_credito']);
        $this->assertSame(1, $resumen[0]['renglones_restitucion']);
        $this->assertSame(110.0, $resumen[0]['neto_gravado']);
        $this->assertSame(23.1, $resumen[0]['iva_credito']);
        $this->assertSame(2.1, $resumen[0]['iva_restitucion']);
    }
}
