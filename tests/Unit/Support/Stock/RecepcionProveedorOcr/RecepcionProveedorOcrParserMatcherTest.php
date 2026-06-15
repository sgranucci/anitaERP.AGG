<?php

namespace Tests\Unit\Support\Stock\RecepcionProveedorOcr;

use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrCantidadSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrLineasParser;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrMatcher;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrNumeroOcExtractor;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrNumeroSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorOcrParserMatcherTest extends TestCase
{
    public function test_parsea_numeros_argentinos(): void
    {
        $this->assertSame(12.0, RecepcionProveedorOcrNumeroSupport::parsear('12,00'));
        $this->assertSame(5400.0, RecepcionProveedorOcrNumeroSupport::parsear('5.400,00'));
        $this->assertSame(230588.28, RecepcionProveedorOcrNumeroSupport::parsear('230588.28'));
    }

    public function test_parsea_lineas_de_remito(): void
    {
        $texto = <<<'TXT'
REMITO PROVEEDOR
0000000203657 EDULCO L5H GOOD FOOD 5HISPA 12,00 5400,00
0000000203656 AZUCAR L5H x 5K 5 HISPANOS 8,00 12956,14
203655 SANTOS E/GRANO x 1KG 7 230588,28
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(3, $lineas);
        $this->assertSame('0000000203657', $lineas[0]['codigo']);
        $this->assertSame(12.0, $lineas[0]['cantidad']);
        $this->assertSame(5400.0, $lineas[0]['precio']);
    }

    public function test_parsea_lineas_factura_buho(): void
    {
        $texto = <<<'TXT'
12 CAJAS 8.2.2.1.0502 EDULCORANTE LSH GOOD FOOD X400 SOBRES N/L 7794520866668 1 17-
8 CAJAS 8.2.1.1.0800 AZUCAR "L5H" x SK en Sob. 7794520821025 1 8
7 PACK X 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg.
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(3, $lineas);
        $this->assertSame(12.0, $lineas[0]['cantidad']);
        $this->assertStringContainsString('EDULCORANTE', $lineas[0]['descripcion']);
        $this->assertSame(7.0, $lineas[2]['cantidad']);
        $this->assertSame(42.0, $lineas[2]['cantidades_candidatas'][1]['valor']);
    }

    public function test_matcher_elige_cantidad_segun_oc(): void
    {
        $lineasOc = [
            [
                'sku' => '203655',
                'skualternativo' => '',
                'descripcion' => 'SANTOS E/GRANO x 1KG',
                'cantidad' => 0,
                'precio' => 0,
                'coeficienteconversion' => 1,
                'cantidad_oc' => 7,
                'precio_ordencompra' => 100,
            ],
        ];

        $lineasOcr = [
            [
                'codigo' => '2.1.2.1.1000',
                'descripcion' => 'SANTOS e/grano x 1 Kg.',
                'cantidad' => 7.0,
                'precio' => 0.0,
                'cantidades_candidatas' => [
                    ['valor' => 7.0, 'tipo' => 'bulto', 'unidad' => 'PACK'],
                    ['valor' => 42.0, 'tipo' => 'total_unidades', 'factor' => 6.0],
                ],
            ],
        ];

        $matcher = new RecepcionProveedorOcrMatcher;
        $resultado = $matcher->aplicar($lineasOc, $lineasOcr);

        $this->assertSame(7.0, $resultado['lineas'][0]['cantidad']);
    }

    public function test_matcher_aplica_cantidad_y_precio(): void
    {
        $lineasOc = [
            [
                'sku' => '203657',
                'skualternativo' => '',
                'descripcion' => 'EDULCO L5H GOOD FOOD 5HISPA',
                'cantidad' => 0,
                'precio' => 0,
                'coeficienteconversion' => 1,
                'cantidad_oc' => 12,
                'precio_ordencompra' => 5400,
            ],
            [
                'sku' => '203656',
                'skualternativo' => '',
                'descripcion' => 'AZUCAR L5H x 5K',
                'cantidad' => 0,
                'precio' => 0,
                'coeficienteconversion' => 1,
                'cantidad_oc' => 8,
                'precio_ordencompra' => 12956.14,
            ],
        ];

        $lineasOcr = [
            ['codigo' => '0000000203657', 'descripcion' => 'EDULCO', 'cantidad' => 12.0, 'precio' => 5400.0, 'cantidades_candidatas' => [['valor' => 12.0, 'tipo' => 'bulto']]],
            ['codigo' => '203656', 'descripcion' => 'AZUCAR', 'cantidad' => 8.0, 'precio' => 12956.14, 'cantidades_candidatas' => [['valor' => 8.0, 'tipo' => 'bulto']]],
        ];

        $matcher = new RecepcionProveedorOcrMatcher;
        $resultado = $matcher->aplicar($lineasOc, $lineasOcr);

        $this->assertSame(2, $resultado['resumen']['emparejadas']);
        $this->assertSame(12.0, $resultado['lineas'][0]['cantidad']);
        $this->assertSame(5400.0, $resultado['lineas'][0]['precio']);
        $this->assertSame(8.0, $resultado['lineas'][1]['cantidad']);
    }

    public function test_extrae_numero_oc_con_etiqueta(): void
    {
        $extractor = new RecepcionProveedorOcrNumeroOcExtractor;
        $resultado = $extractor->extraer("REMITO\nOC: 221067\nProveedor XYZ");

        $this->assertSame(221067, $resultado['numero']);
        $this->assertSame('etiqueta_oc', $resultado['origen']);
    }

    public function test_extrae_nro_de_oc_como_en_remito_buho(): void
    {
        $extractor = new RecepcionProveedorOcrNumeroOcExtractor;

        $this->assertSame(221067, $extractor->extraer("Nro O.C.: 221067")['numero']);
        $this->assertSame('nro_de_oc', $extractor->extraer("Nro O.C.: 221067")['origen']);
        $this->assertSame(221067, $extractor->extraer("Nro. de O.C.: 221067")['numero']);
        $this->assertSame(221067, $extractor->extraer("L TRANSPORTISTA: Nro 0.0; 221067")['numero']);
    }

    public function test_extrae_numero_oc_seis_digitos_prefijo_dos(): void
    {
        $extractor = new RecepcionProveedorOcrNumeroOcExtractor;
        $resultado = $extractor->extraer("REMITO 221067\n12 CAJAS EDULCORANTE");

        $this->assertSame(221067, $resultado['numero']);
        $this->assertSame('heuristica_6_digitos', $resultado['origen']);
    }

    public function test_cantidad_support_prefiere_bulto_cuando_oc_coincide(): void
    {
        $cantidad = RecepcionProveedorOcrCantidadSupport::resolver(
            ['cantidad_oc' => 7],
            [
                'cantidad' => 7,
                'cantidades_candidatas' => [
                    ['valor' => 7, 'tipo' => 'bulto'],
                    ['valor' => 42, 'tipo' => 'total_unidades', 'factor' => 6],
                ],
            ]
        );

        $this->assertSame(7.0, $cantidad);
    }
}
