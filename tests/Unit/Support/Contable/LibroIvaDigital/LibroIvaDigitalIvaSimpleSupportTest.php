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
        $this->assertSame(100.0, $resumen[0]['neto_gravado']);
        $this->assertSame(21.0, $resumen[0]['iva_credito']);
        $this->assertSame(2.1, $resumen[0]['iva_restitucion']);
    }

    public function test_debito_desde_libro_incluye_exento_como_tipo_3(): void
    {
        $armado = LibroIvaDigitalIvaSimpleSupport::debitoDesdeRegistrosLibro([
            [
                'iva_simple' => [
                    'actividad_codigo' => '561011',
                    'actividad_nombre' => 'Gastronomía',
                    'tipo_sujeto' => 3,
                    'restitucion' => false,
                ],
                'cabecera' => [
                    'operaciones_exentas' => 1234.56,
                ],
                'alicuotas' => [[
                    'neto_gravado' => 100.0,
                    'impuesto_liquidado' => 21.0,
                    'alicuota_iva' => '0005',
                ]],
            ],
        ]);

        $this->assertCount(2, $armado['detalle']);
        $gravado = null;
        $exento = null;
        foreach ($armado['detalle'] as $fila) {
            if (($fila['tipo_operacion'] ?? '') === '1') {
                $gravado = $fila;
            }
            if (($fila['tipo_operacion'] ?? '') === '3') {
                $exento = $fila;
            }
        }
        $this->assertNotNull($gravado);
        $this->assertNotNull($exento);
        $this->assertSame(100.0, $gravado['neto']);
        $this->assertSame(21.0, $gravado['iva']);
        $this->assertSame(1234.56, $exento['exento']);
        $this->assertSame('561011', $exento['actividad_codigo']);
        $this->assertSame(
            '561011;3;;;;;;1234,56;',
            LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($exento),
        );
    }

    public function test_credito_desde_libro_no_mete_exento_en_el_csv(): void
    {
        $armado = LibroIvaDigitalIvaSimpleSupport::creditoDesdeRegistrosLibro([
            [
                'iva_simple' => ['restitucion' => false],
                'cabecera' => [
                    'tipo_comprobante' => '001',
                    'operaciones_exentas' => 50.0,
                    'no_integra_neto' => 10.0,
                    'importe_total' => 181.0,
                ],
                'alicuotas' => [[
                    'neto_gravado' => 100.0,
                    'impuesto_liquidado' => 21.0,
                    'alicuota_iva' => '0005',
                    'concepto_iva_simple' => 3,
                ]],
            ],
            [
                'iva_simple' => ['restitucion' => false],
                'cabecera' => [
                    'tipo_comprobante' => '011',
                    'operaciones_exentas' => 0.0,
                    'no_integra_neto' => 0.0,
                    'importe_total' => 80.0,
                ],
                'alicuotas' => [],
            ],
        ]);

        $this->assertCount(1, $armado['detalle']);
        $this->assertSame(100.0, $armado['detalle'][0]['neto']);
        $this->assertSame(21.0, $armado['detalle'][0]['iva']);
        $this->assertSame(50.0, $armado['total_exento']);
        $this->assertSame(10.0, $armado['total_no_integra']);
        $this->assertSame(80.0, $armado['total_monotributo']);
    }
}
