<?php

namespace Tests\Unit\Support\Contable\LibroIvaDigital;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalArchivosSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasImportesSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use PHPUnit\Framework\TestCase;

class LibroIvaDigitalFormatoSupportTest extends TestCase
{
    public function test_csv_compras_omitidos_incluye_comprobante_y_motivo(): void
    {
        $csv = LibroIvaDigitalArchivosSupport::csvComprasOmitidos([
            [
                'motivo' => 'cuit_informante',
                'motivo_texto' => 'CUIT del vendedor igual al CUIT del informante',
                'origen' => 'anita',
                'fecha' => '20260815',
                'tipo_comprobante' => '001',
                'punto_venta' => 1,
                'numero_comprobante' => 124233,
                'letra' => 'A',
                'tipo_abrev' => 'FAC',
                'proveedor_codigo' => '000123',
                'numero_identificacion' => '30682403671',
                'nombre_vendedor' => 'BIYEMAS S.A.',
                'importe_total' => 1500.5,
            ],
        ]);

        $this->assertStringContainsString('INFORME', LibroIvaDigitalArchivosSupport::COMPRAS_OMITIDOS);
        $this->assertStringContainsString('Motivo;Origen;Fecha', $csv);
        $this->assertStringContainsString('15/08/2026', $csv);
        $this->assertStringContainsString('BIYEMAS S.A.', $csv);
        $this->assertStringContainsString('30682403671', $csv);
        $this->assertStringContainsString('124233', $csv);
        $this->assertStringContainsString('cuit_informante', $csv);
    }

    public function test_compras_cbte_en_pesos_no_reconvierte_dolares(): void
    {
        $cabecera = LibroIvaDigitalMapeosSupport::cabeceraImportesEnPesos([
            'fecha' => '20260715',
            'tipo_comprobante' => '001',
            'punto_venta' => 7,
            'numero_comprobante' => 96,
            'codigo_documento' => '80',
            'numero_identificacion' => '30712345678',
            'nombre_vendedor' => 'PROVEEDOR USD',
            'importe_total' => 13946042.0,
            'codigo_moneda' => 'DOL',
            'tipo_cambio' => 1450.0,
            'cantidad_alicuotas' => 1,
            'credito_fiscal_computable' => 2420383.50,
        ]);

        $linea = LibroIvaDigitalFormatoSupport::registroComprasCbte($cabecera);

        $this->assertSame(325, strlen($linea));
        $this->assertSame('PES', rtrim(substr($linea, 224, 3)));
        $this->assertEqualsWithDelta(
            1.0,
            LibroIvaDigitalFormatoSupport::parseTipoCambio10(substr($linea, 227, 10)),
            0.000001,
        );
        $this->assertEqualsWithDelta(
            13946042.0,
            LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 104, 15)),
            0.001,
        );
    }

    public function test_tipo_cambio_desbordado_no_alarga_el_registro(): void
    {
        $this->assertSame(10, strlen(LibroIvaDigitalFormatoSupport::tipoCambio10(1_000_000.0)));
        $this->assertSame(15, strlen(LibroIvaDigitalFormatoSupport::importe15(12.34)));
    }

    public function test_compra_dolares_erp_pasa_a_pesos_antes_del_portal(): void
    {
        $totales = LibroIvaDigitalComprasImportesSupport::aplicarCoeficiente([
            'neto_gravado' => 60.19,
            'iva' => 12.64,
            'exento' => 0.0,
            'credito_computable' => 12.64,
            'alicuotas' => [[
                'neto' => 60.19,
                'iva' => 12.64,
                'tasa' => 21.0,
            ]],
        ], 1495.0);

        $this->assertEqualsWithDelta(89984.05, $totales['neto_gravado'], 0.001);
        $this->assertEqualsWithDelta(18896.80, $totales['iva'], 0.001);
        $this->assertEqualsWithDelta(18896.80, $totales['credito_computable'], 0.001);
        $this->assertEqualsWithDelta(89984.05, $totales['alicuotas'][0]['neto'], 0.001);
        $this->assertEqualsWithDelta(18896.80, $totales['alicuotas'][0]['iva'], 0.001);

        $importeTotal = round(72.83 * 1495.0, 2);
        $this->assertEqualsWithDelta(108880.85, $importeTotal, 0.001);

        $cabecera = LibroIvaDigitalMapeosSupport::cabeceraImportesEnPesos([
            'fecha' => '20260803',
            'tipo_comprobante' => '001',
            'punto_venta' => 4,
            'numero_comprobante' => 3596,
            'codigo_documento' => '80',
            'numero_identificacion' => '30718194233',
            'nombre_vendedor' => 'SOLIDO SUPPLY',
            'importe_total' => $importeTotal,
            'codigo_moneda' => 'DOL',
            'tipo_cambio' => 1495.0,
            'cantidad_alicuotas' => 1,
            'credito_fiscal_computable' => $totales['credito_computable'],
        ]);

        $linea = LibroIvaDigitalFormatoSupport::registroComprasCbte($cabecera);
        $this->assertSame('PES', rtrim(substr($linea, 224, 3)));
        $this->assertEqualsWithDelta(
            1.0,
            LibroIvaDigitalFormatoSupport::parseTipoCambio10(substr($linea, 227, 10)),
            0.000001,
        );
        $this->assertEqualsWithDelta(
            108880.85,
            LibroIvaDigitalFormatoSupport::parseImporte15(substr($linea, 104, 15)),
            0.001,
        );
        $this->assertSame(
            LibroIvaDigitalComprasImportesSupport::aplicarCoeficiente(['neto_gravado' => 10.0], 1.0)['neto_gravado'],
            10.0,
        );
    }
}
