<?php

namespace Tests\Unit\Support\Stock\RecepcionProveedorOcr;

use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrCantidadSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrCodigoBarraSupport;
use App\Support\Stock\RecepcionProveedorOcr\RecepcionProveedorOcrLayoutSupport;
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
        $this->assertSame('2.1.2.1.1000', $lineas[2]['codigo']);
        $this->assertSame(7.0, $lineas[2]['cantidad']);
        $this->assertSame('cantidad_columna', $lineas[2]['cantidades_candidatas'][0]['tipo']);
        $this->assertSame(42.0, $lineas[2]['cantidades_candidatas'][1]['valor']);
        $tipos = array_column($lineas[2]['cantidades_candidatas'], 'valor');
        $this->assertNotContains(6.0, $tipos, 'El factor x 6 no debe ser candidato de cantidad');
    }

    public function test_parsea_lineas_partidas_como_ocr_foto(): void
    {
        $texto = <<<'TXT'
7 PACK x 6
2.1.2.1.1000 SANTOS e/grano x1Kg
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame('2.1.2.1.1000', $lineas[0]['codigo']);
        $this->assertSame(7.0, $lineas[0]['cantidad']);
        $this->assertStringContainsString('SANTOS', $lineas[0]['descripcion']);
    }

    public function test_parsea_remito_buho_con_columnas_sin_confundir_unidxbulto(): void
    {
        $texto = '7 PACK X 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7794520212113 6 42 42,00';

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame(7.0, $lineas[0]['cantidad']);
        $valores = array_column($lineas[0]['cantidades_candidatas'], 'valor');
        $this->assertSame([7.0, 42.0], $valores);
    }

    public function test_corrije_seis_a_siete_usando_unidades_cuando_cantidad_ocr_erronea(): void
    {
        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear('6 PACK x 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7794520212113 6 42 42,00');

        $this->assertCount(1, $lineas);
        $this->assertSame(6.0, $lineas[0]['cantidad']);

        $this->assertSame(7.0, RecepcionProveedorOcrCantidadSupport::resolver(
            ['cantidad_oc' => 7],
            $lineas[0]
        ));
    }

    public function test_corrije_ocho_a_siete_cuando_unidades_indican_siete_bultos(): void
    {
        $texto = <<<'TXT'
CANTIDAD ARTICULOS Y/O MERCADERIA COD. ALT UNID. X BULTO UNIDADES PESO
8 PACK x 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7794520212113 6 42 42,00
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame(8.0, $lineas[0]['cantidad']);

        $matcher = new RecepcionProveedorOcrMatcher;
        $res = $matcher->aplicar(
            [[
                'sku' => '203655',
                'skualternativo' => '',
                'codigo_proveedor' => '2.1.2.1.1000',
                'descripcion' => 'SANTOS E/GRANO',
                'cantidad' => 0,
                'precio' => 0,
                'coeficienteconversion' => 6,
                'cantidad_oc' => 7,
                'precio_ordencompra' => 0,
            ]],
            $lineas
        );

        $this->assertSame(7.0, $res['lineas'][0]['cantidad']);
    }

    public function test_remito_tres_items_tercero_santos_siete_pack(): void
    {
        $texto = <<<'TXT'
CANTIDAD ARTICULOS Y/O MERCADERIA COD. ALT UNID. X BULTO UNIDADES PESO
12 CAJAS 8.2.2.1.0502 EDULCORANTE LSH GOOD FOOD 7794520866668 1 12 12,00
8 CAJAS 8.2.1.1.0800 AZUCAR L5H x SK 7794520821025 1 8 8,00
8 PACK x 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7794520212113 6 42 42,00
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(3, $lineas);
        $this->assertSame('2.1.2.1.1000', $lineas[2]['codigo']);

        $matcher = new RecepcionProveedorOcrMatcher;
        $res = $matcher->aplicar(
            [[
                'sku' => '203655',
                'codigo_proveedor' => '2.1.2.1.1000',
                'descripcion' => 'SANTOS E/GRANO',
                'cantidad' => 0,
                'precio' => 0,
                'coeficienteconversion' => 6,
                'cantidad_oc' => 7,
                'precio_ordencompra' => 0,
                'skualternativo' => '',
            ]],
            $lineas
        );

        $this->assertSame(7.0, $res['lineas'][0]['cantidad']);
    }

    public function test_busca_cantidad_por_unidad_cuando_fila_empieza_por_codigo(): void
    {
        $texto = <<<'TXT'
CANTIDAD ARTICULOS Y/O MERCADERIA COD. ALT UNID. X BULTO UNIDADES PESO
2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7 PACK x 6 7794520212113 6 42 42,00
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame(7.0, $lineas[0]['cantidad']);
        $this->assertSame('PACK', $lineas[0]['unidad_compra']);
    }

    public function test_no_sube_siete_a_ocho_si_unidades_ocr_esta_mal(): void
    {
        $lineaOcr = [
            'cantidad' => 7.0,
            'unidad_compra' => 'PACK',
            'factor_embalaje' => 6.0,
            'cantidades_candidatas' => [
                ['valor' => 7, 'tipo' => 'cantidad_columna', 'unidad' => 'PACK'],
                ['valor' => 42, 'tipo' => 'total_unidades', 'factor' => 6],
                ['valor' => 48, 'tipo' => 'unidades_columna', 'unidad' => 'UNID'],
            ],
        ];

        $this->assertSame(7.0, RecepcionProveedorOcrCantidadSupport::resolver(['cantidad_oc' => 7], $lineaOcr));
    }

    public function test_corrije_cantidad_ocr_seis_cuando_unidades_indican_siete(): void
    {
        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear('6 PACK x 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7794520212113 6 42 42,00');

        $this->assertCount(1, $lineas);
        $this->assertSame(6.0, $lineas[0]['cantidad']);

        $matcher = new RecepcionProveedorOcrMatcher;
        $res = $matcher->aplicar(
            [[
                'sku' => '203655',
                'skualternativo' => '',
                'codigo_proveedor' => '2.1.2.1.1000',
                'descripcion' => 'SANTOS E/GRANO',
                'cantidad' => 6,
                'precio' => 0,
                'coeficienteconversion' => 6,
                'cantidad_oc' => 7,
                'precio_ordencompra' => 0,
            ]],
            $lineas
        );

        $this->assertSame(7.0, $res['lineas'][0]['cantidad']);
        $this->assertSame(42.0, $res['lineas'][0]['cantidad_stock']);
    }

    public function test_rechaza_seis_suelto_antes_de_codigo_buho(): void
    {
        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear('6 2.1.2.1.1000 SANTOS e/grano x1Kg 7794520212113 6 42 42,00');

        $this->assertCount(0, $lineas);
    }

    public function test_rechaza_linea_solo_codigo_buho_sin_columna_cantidad(): void
    {
        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear('2.1.2.1.1000 SANTOS e/grano x1Kg 7794520212113 6 42 42,00');

        $this->assertCount(0, $lineas);
    }

    public function test_descarta_linea_ocr_corrupta_si_existe_buho_valida(): void
    {
        $texto = <<<'TXT'
7 K 6 21211000 N Se/gra x1K
7 PACK x 6 2.1.2.1.1000 SANTOS e/grano x1Kg
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame('2.1.2.1.1000', $lineas[0]['codigo']);
        $this->assertSame(7.0, $lineas[0]['cantidad']);
    }

    public function test_cantidad_columna_prioridad_sobre_unidades_aunque_oc_pida_unidades(): void
    {
        $cantidad = RecepcionProveedorOcrCantidadSupport::resolver(
            ['cantidad_oc' => 42],
            [
                'cantidad' => 7,
                'unidad_compra' => 'PACK',
                'factor_embalaje' => 6,
                'cantidades_candidatas' => [
                    ['valor' => 7, 'tipo' => 'cantidad_columna'],
                    ['valor' => 42, 'tipo' => 'total_unidades', 'factor' => 6],
                ],
            ]
        );

        $this->assertSame(7.0, $cantidad);
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

    public function test_extrae_numero_oc_según_oc_factura_afip(): void
    {
        $extractor = new RecepcionProveedorOcrNumeroOcExtractor;
        $texto = <<<'TXT'
P/CR Precinto de acero numerado Según OC X 000 -
00000221227 de fecha 12/06/2026
TXT;

        $resultado = $extractor->extraer($texto);

        $this->assertSame(221227, $resultado['numero']);
        $this->assertSame('segun_oc_factura', $resultado['origen']);
    }

    public function test_parsea_linea_factura_afip_con_codigo_alfanumerico(): void
    {
        $texto = <<<'TXT'
Código    Producto / Servicio                                         Cantidad        U. medida        Precio Unit.
P/CR      Precinto de acero de alta seguridad con cable                      50,00 unidades                   3200,00     0,00      160000,00    21%           193600,00
de acero numerado Según OC X 000 -
00000221227 de fecha 12/06/2026 Usuario
BIVAS LUCAS solcito GBRAVO
Importe Total: $          193600,00
DUPLICADO
P/CR      Precinto de acero de alta seguridad con cable                      50,00 unidades                   3200,00     0,00      160000,00    21%           193600,00
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame('P/CR', $lineas[0]['codigo']);
        $this->assertSame(50.0, $lineas[0]['cantidad']);
        $this->assertSame(3200.0, $lineas[0]['precio']);
        $this->assertSame('UNID', $lineas[0]['unidad_compra']);
        $this->assertStringContainsString('Según OC X 000', $lineas[0]['descripcion']);
        $this->assertStringContainsString('00000221227', $lineas[0]['descripcion']);
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

    public function test_layout_cantidad_primera_columna(): void
    {
        $texto = <<<'TXT'
CANTIDAD ARTICULOS Y/O MERCADERIA COD. ALT UNID. X BULTO UNIDADES PESO
7 PACK x 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7794520212113 6 42 42,00
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame(7.0, $lineas[0]['cantidad']);
        $this->assertTrue($lineas[0]['cantidad_columna_layout'] ?? false);
    }

    public function test_layout_cantidad_segunda_columna(): void
    {
        $texto = <<<'TXT'
COD. ALT CANTIDAD ARTICULOS Y/O MERCADERIA UNIDADES
2.1.2.1.1000 7 PACK x 6 SANTOS e/grano x 1 Kg. 42
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame(7.0, $lineas[0]['cantidad']);
        $this->assertTrue($lineas[0]['cantidad_columna_layout'] ?? false);
    }

    public function test_layout_cantidad_tercera_columna(): void
    {
        $texto = <<<'TXT'
COD. ALT ARTICULOS Y/O MERCADERIA CANTIDAD UNID. X BULTO UNIDADES
2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7 PACK x 6 6 42
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame(7.0, $lineas[0]['cantidad']);
        $this->assertTrue($lineas[0]['cantidad_columna_layout'] ?? false);
    }

    public function test_layout_no_reconcilia_siete_con_unidades_ocr_cuarenta_y_ocho(): void
    {
        $texto = <<<'TXT'
CANTIDAD ARTICULOS Y/O MERCADERIA COD. ALT UNID. X BULTO UNIDADES PESO
7 PACK x 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7794520212113 6 48 48,00
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertSame(7.0, RecepcionProveedorOcrCantidadSupport::resolver(
            ['cantidad_oc' => 7],
            $lineas[0]
        ));
    }

    public function test_matcher_no_cruza_codigo_proveedor_distinto(): void
    {
        $matcher = new RecepcionProveedorOcrMatcher;
        $res = $matcher->aplicar(
            [[
                'sku' => '203655',
                'skualternativo' => '',
                'codigo_proveedor' => '2.1.2.1.1000',
                'descripcion' => 'SANTOS E/GRANO',
                'cantidad' => 0,
                'precio' => 0,
                'coeficienteconversion' => 6,
                'cantidad_oc' => 7,
                'precio_ordencompra' => 0,
            ]],
            [[
                'codigo' => '8.2.1.1.0800',
                'descripcion' => 'AZUCAR L5H',
                'cantidad' => 8.0,
                'precio' => 0,
                'cantidades_candidatas' => [['valor' => 8, 'tipo' => 'cantidad_columna', 'unidad' => 'CAJAS']],
            ]]
        );

        $this->assertSame(0, $res['resumen']['emparejadas']);
    }

    public function test_extrae_codigo_barra_ean13_en_linea_buho(): void
    {
        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear(
            '7 PACK x 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7794520212113 6 42 42,00'
        );

        $this->assertCount(1, $lineas);
        $this->assertSame('7794520212113', $lineas[0]['codigobarra']);
        $this->assertStringNotContainsString('7794520212113', $lineas[0]['descripcion']);
    }

    public function test_extrae_ean13_con_layout_columna_codigo_barras(): void
    {
        $texto = <<<'TXT'
CANTIDAD ARTICULOS Y/O MERCADERIA COD. ALT COD. BARRAS UNID. X BULTO UNIDADES PESO
7 PACK x 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7794520212113 6 42 42,00
TXT;

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear($texto);

        $this->assertCount(1, $lineas);
        $this->assertSame('7794520212113', $lineas[0]['codigobarra']);
    }

    public function test_normaliza_ean13_ocr_con_caracteres_confundidos(): void
    {
        $this->assertSame(
            '7794520212113',
            RecepcionProveedorOcrCodigoBarraSupport::extraerDeTexto('779452O212113')
        );
        $this->assertSame(
            '7794520866668',
            RecepcionProveedorOcrCodigoBarraSupport::extraerDeTexto('7794520866668')
        );
        $this->assertTrue(RecepcionProveedorOcrCodigoBarraSupport::esEan13Valido('7794520212113'));
        $this->assertNull(RecepcionProveedorOcrCodigoBarraSupport::extraerDeTexto('2.1.2.1.1000 SANTOS e/grano x1Kg'));
    }

    public function test_corrije_ean13_dos_digitos_ocr(): void
    {
        $this->assertSame(
            '7794520212113',
            RecepcionProveedorOcrCodigoBarraSupport::corregirDigitosCorruptos('7194510212113')
        );
    }

    public function test_corrije_ean13_cuatro_digitos_ocr_edulcorante(): void
    {
        $this->assertSame(
            '7794520866668',
            RecepcionProveedorOcrCodigoBarraSupport::corregirDigitosCorruptos('1194520865888')
        );
        $this->assertSame(
            '7794520866668',
            RecepcionProveedorOcrCodigoBarraSupport::extraerDeCelda('1194520x65bbx')
        );
    }

    public function test_corrije_ean13_en_linea_remito(): void
    {
        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear(
            '7 PACK x 6 2.1.2.1.1000 SANTOS e/grano x 1 Kg. 7194510212113 6 42 42,00'
        );

        $this->assertCount(1, $lineas);
        $this->assertSame('7794520212113', $lineas[0]['codigobarra']);
    }

    public function test_matcher_propaga_ocr_codigobarra(): void
    {
        $matcher = new RecepcionProveedorOcrMatcher;
        $res = $matcher->aplicar(
            [[
                'sku' => '203655',
                'skualternativo' => '',
                'codigo_proveedor' => '2.1.2.1.1000',
                'descripcion' => 'SANTOS E/GRANO',
                'cantidad' => 0,
                'precio' => 0,
                'coeficienteconversion' => 6,
                'cantidad_oc' => 7,
                'precio_ordencompra' => 0,
            ]],
            [[
                'codigo' => '2.1.2.1.1000',
                'descripcion' => 'SANTOS e/grano',
                'cantidad' => 7.0,
                'precio' => 0,
                'codigobarra' => '7794520212113',
                'cantidades_candidatas' => [['valor' => 7, 'tipo' => 'cantidad_columna', 'unidad' => 'PACK']],
            ]]
        );

        $this->assertSame('7794520212113', $res['lineas'][0]['ocr_codigobarra']);
    }

    public function test_cantidad_digitos_hasta_letra_7packx_6(): void
    {
        $extraido = RecepcionProveedorOcrLayoutSupport::extraerCantidadDigitosHastaUnidad('7PACKX 6');
        $this->assertNotNull($extraido);
        $this->assertSame(7.0, $extraido['cant']);
        $this->assertSame('PACK', $extraido['unidad']);
        $this->assertSame(6.0, $extraido['factor']);

        $parser = new RecepcionProveedorOcrLineasParser;
        $lineas = $parser->parsear("7PACKX 6\n2.1.2.1.1000 SANTOS e/grano x1Kg");
        $this->assertCount(1, $lineas);
        $this->assertSame(7.0, $lineas[0]['cantidad']);
        $this->assertSame('2.1.2.1.1000', $lineas[0]['codigo']);
        $this->assertSame('PACK', $lineas[0]['unidad_compra']);

        $matcher = new RecepcionProveedorOcrMatcher;
        $res = $matcher->aplicar(
            [[
                'sku' => '203655',
                'codigo_proveedor' => '2.1.2.1.1000',
                'descripcion' => 'SANTOS E/GRANO',
                'cantidad' => 0,
                'precio' => 0,
                'coeficienteconversion' => 6,
                'cantidad_oc' => 7,
                'precio_ordencompra' => 0,
                'skualternativo' => '',
            ]],
            $lineas
        );
        $this->assertSame(7.0, $res['lineas'][0]['cantidad']);
    }

    public function test_cantidad_digitos_no_confunde_codigo_buho(): void
    {
        $this->assertNull(
            RecepcionProveedorOcrLayoutSupport::extraerCantidadDigitosHastaUnidad('2.1.2.1.1000 SANTOS e/grano x1Kg')
        );

        $parser = new RecepcionProveedorOcrLineasParser;
        $this->assertSame([], $parser->parsear('2.1.2.1.1000 SANTOS e/grano x1Kg'));
    }

    public function test_parsea_codigo_buho_con_oooo_ocr_y_cantidad_siete_pack(): void
    {
        $parser = new RecepcionProveedorOcrLineasParser;
        $linea = '7 PACK x 6 2.1.2.1.1ooo SANTOS e/grano )( 1 Kg. 7794520202113 6 42';
        $lineas = $parser->parsear($linea);

        $this->assertCount(1, $lineas);
        $this->assertSame('2.1.2.1.1000', $lineas[0]['codigo']);
        $this->assertSame(7.0, $lineas[0]['cantidad']);
        $this->assertSame('PACK', $lineas[0]['unidad_compra']);
        $this->assertStringContainsString('SANTOS', $lineas[0]['descripcion']);
        $this->assertSame('7794520212113', $lineas[0]['codigobarra']);

        $matcher = new RecepcionProveedorOcrMatcher;
        $res = $matcher->aplicar(
            [[
                'sku' => '203655',
                'codigo_proveedor' => '2.1.2.1.1000',
                'descripcion' => 'SANTOS E/GRANO',
                'cantidad' => 0,
                'precio' => 0,
                'coeficienteconversion' => 6,
                'cantidad_oc' => 7,
                'precio_ordencompra' => 0,
                'skualternativo' => '',
            ]],
            $lineas
        );

        $this->assertSame(7.0, $res['lineas'][0]['cantidad']);
    }

    public function test_completa_ean_truncado_remito_buho_tres_items(): void
    {
        RecepcionProveedorOcrCodigoBarraSupport::overrideCompletarEanParaTests(true);

        try {
            $parser = new RecepcionProveedorOcrLineasParser;
        $texto = <<<'TXT'
GOOD FOOD x4oo SOBRES N/L
8 CAJAS 8.2.1.1.0800 AZUCAR "L5H" )( SK en Sob. 779452032sz
7 PACK x 6 2.1.2.1.1ooo SANTOS e/grano )( 1 Kg. 779452020… 6
TXT;
        $lineas = $parser->parsear($texto);

        $this->assertCount(2, $lineas);
        $this->assertSame('2.1.2.1.1000', $lineas[1]['codigo']);
        $this->assertSame('7794520212113', $lineas[1]['codigobarra']);
        $this->assertStringNotContainsString('7794520', $lineas[1]['descripcion']);
        } finally {
            RecepcionProveedorOcrCodigoBarraSupport::overrideCompletarEanParaTests(null);
        }
    }

    public function test_corrije_ocho_a_siete_sin_unidades_cuando_oc_indica_siete(): void
    {
        $parser = new RecepcionProveedorOcrLineasParser;
        $linea = '8 PACK x 6 2.1.2.1.1ooo SANTOS e/grano )( 1 Kg. 779452020… 6';
        $lineas = $parser->parsear($linea);

        $this->assertCount(1, $lineas);
        $this->assertSame(8.0, $lineas[0]['cantidad']);

        $matcher = new RecepcionProveedorOcrMatcher;
        $res = $matcher->aplicar(
            [[
                'sku' => '203655',
                'codigo_proveedor' => '2.1.2.1.1000',
                'descripcion' => 'SANTOS E/GRANO',
                'cantidad' => 0,
                'precio' => 0,
                'coeficienteconversion' => 6,
                'cantidad_oc' => 7,
                'precio_ordencompra' => 0,
                'skualternativo' => '',
            ]],
            $lineas
        );

        $this->assertSame(7.0, $res['lineas'][0]['cantidad']);
        $this->assertNull($res['lineas'][0]['ocr_codigobarra'] ?? null);
    }
}
