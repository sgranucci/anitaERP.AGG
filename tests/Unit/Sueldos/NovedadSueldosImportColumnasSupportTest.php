<?php

namespace Tests\Unit\Sueldos;

use App\Support\Sueldos\NovedadSueldosImportColumnasSupport;
use PHPUnit\Framework\TestCase;

class NovedadSueldosImportColumnasSupportTest extends TestCase
{
    public function test_normaliza_modo_columnas(): void
    {
        $this->assertSame('titulo', NovedadSueldosImportColumnasSupport::normalizarModoColumnas(null));
        $this->assertSame('titulo', NovedadSueldosImportColumnasSupport::normalizarModoColumnas('titulo'));
        $this->assertSame('numero', NovedadSueldosImportColumnasSupport::normalizarModoColumnas('numero'));
        $this->assertTrue(NovedadSueldosImportColumnasSupport::esModoNumero('numero'));
        $this->assertFalse(NovedadSueldosImportColumnasSupport::esModoNumero('titulo'));
    }

    public function test_headers_sinteticos_para_archivo_sin_titulo(): void
    {
        $headers = NovedadSueldosImportColumnasSupport::headersSinteticosDesdeFilas([
            [100, 120, 8],
            [101, 120, 4, 1500],
        ]);

        $this->assertCount(4, $headers);
        $this->assertSame(0, $headers[0]['indice']);
        $this->assertSame('Columna 1', $headers[0]['titulo']);
        $this->assertSame('Columna 4', $headers[3]['titulo']);
    }

    public function test_parece_fila_encabezado_exige_alias(): void
    {
        $this->assertTrue(NovedadSueldosImportColumnasSupport::pareceFilaEncabezado([
            'legajo', 'concepto_codigo', 'valor1',
        ]));
        $this->assertFalse(NovedadSueldosImportColumnasSupport::pareceFilaEncabezado([
            4521, 120, 8,
        ]));
    }

    public function test_fusionar_mapa_vacio_no_pisa_automatico(): void
    {
        $auto = [
            'empresa' => null,
            'legajo' => 0,
            'concepto_codigo' => 1,
            'valor1' => 2,
        ];
        $override = [
            'empresa' => '1',
            'legajo' => '',
            'concepto_codigo' => '',
            'valor1' => '',
        ];

        $mapa = NovedadSueldosImportColumnasSupport::fusionarMapa($auto, $override, true, []);

        $this->assertSame(0, $mapa['empresa']);
        $this->assertSame(0, $mapa['legajo']);
        $this->assertSame(1, $mapa['concepto_codigo']);
        $this->assertSame(2, $mapa['valor1']);
    }

    public function test_resolver_numero_y_letra_excel(): void
    {
        $this->assertSame(0, NovedadSueldosImportColumnasSupport::resolverValorMapeoAIndice('1', true));
        $this->assertSame(1, NovedadSueldosImportColumnasSupport::resolverValorMapeoAIndice('B', true));
        $this->assertSame(2, NovedadSueldosImportColumnasSupport::resolverValorMapeoAIndice('concepto', false, [
            'empresa', 'legajo', 'concepto',
        ]));
    }

    public function test_mapping_ui_modo_numero_es_1_based(): void
    {
        $ui = NovedadSueldosImportColumnasSupport::mappingParaUi(
            ['legajo' => 0, 'concepto_codigo' => 2],
            [
                ['indice' => 0, 'titulo' => 'Columna 1'],
                ['indice' => 1, 'titulo' => 'Columna 2'],
                ['indice' => 2, 'titulo' => 'Columna 3'],
            ],
            true
        );

        $this->assertSame('1', $ui['legajo']);
        $this->assertSame('3', $ui['concepto_codigo']);
    }
}
