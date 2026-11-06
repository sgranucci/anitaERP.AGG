<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\OrdentrabajoEmisionTextoSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD). El corte del material de la OT no parte el nombre.
 */
class OrdentrabajoEmisionTextoSupportTest extends TestCase
{
    private const MUESTRA = '1.2.5 LH.ALICANTE BLANCO -0.90- P/3.4 SKIN.DESCARNE OFF WHITE -0.56- P/7.8.9 BETZ CE -0.31- P/6 PELO PELO 64 -0.08- P';

    public function test_corta_en_la_barra_del_material_y_no_parte_betz(): void
    {
        $lineas = OrdentrabajoEmisionTextoSupport::partirMaterial(self::MUESTRA);

        self::assertSame(
            '1.2.5 LH.ALICANTE BLANCO -0.90- P/3.4 SKIN.DESCARNE OFF WHITE -0.56- P',
            $lineas[0]
        );
        self::assertSame(
            '7.8.9 BETZ CE -0.31- P/6 PELO PELO 64 -0.08- P',
            $lineas[1]
        );
        self::assertSame('', $lineas[2]);
        self::assertStringNotContainsString('BETZ C', $lineas[0]);
        foreach ($lineas as $linea) {
            self::assertLessThanOrEqual(OrdentrabajoEmisionTextoSupport::ANCHO_MATERIAL_MM, OrdentrabajoEmisionTextoSupport::anchoMm($linea));
        }
    }

    public function test_un_texto_corto_queda_en_la_primera_linea(): void
    {
        $lineas = OrdentrabajoEmisionTextoSupport::partirMaterial('1 C.BLANCO -0.10- P');

        self::assertSame('1 C.BLANCO -0.10- P', $lineas[0]);
        self::assertSame('', $lineas[1]);
        self::assertSame('', $lineas[2]);
    }

    public function test_el_forro_de_la_ot_no_parte_entretela(): void
    {
        $texto = ' CACHAREL NEGRO -5.50- D/ VACUNO NEGRO -2.64- D/ PECARI NEGRO -0.66- D/ ENTRETELA  -1.54- D';
        $lineas = OrdentrabajoEmisionTextoSupport::partirForro($texto);

        self::assertSame(
            'CACHAREL NEGRO -5.50- D/ VACUNO NEGRO -2.64- D/ PECARI NEGRO -0.66- D',
            $lineas[0]
        );
        self::assertSame('ENTRETELA  -1.54- D', $lineas[1]);
        self::assertStringNotContainsString('ENTRETEL', $lineas[0]);
        self::assertLessThanOrEqual(
            OrdentrabajoEmisionTextoSupport::ANCHO_FORRO_LINEA1_MM,
            OrdentrabajoEmisionTextoSupport::anchoMm(OrdentrabajoEmisionTextoSupport::PREFIJO_FORRO.$lineas[0])
        );
        self::assertLessThanOrEqual(
            OrdentrabajoEmisionTextoSupport::ANCHO_FORRO_LINEA2_MM,
            OrdentrabajoEmisionTextoSupport::anchoMm($lineas[1])
        );
    }

    public function test_el_empaque_de_la_ot_31282_entra_entero(): void
    {
        $texto = 'ART.A35 1.20 NEGRO 22.00/MEMORY FOAM NEGRO 0.00';
        $cuerpo = OrdentrabajoEmisionTextoSupport::cuerpoEmpaque($texto);
        $linea = OrdentrabajoEmisionTextoSupport::PREFIJO_EMPAQUE.$cuerpo;

        self::assertSame($texto, $cuerpo);
        self::assertLessThanOrEqual(
            OrdentrabajoEmisionTextoSupport::ANCHO_EMPAQUE_MM,
            OrdentrabajoEmisionTextoSupport::anchoMm($linea)
        );
    }

    public function test_un_empaque_largo_corta_en_el_espacio_y_no_a_media_palabra(): void
    {
        $texto = 'ART.A35 1.20 NEGRO 22.00/MEMORY FOAM NEGRO 0.00/'.str_repeat('CINTA LARGA ', 30);
        $cuerpo = OrdentrabajoEmisionTextoSupport::cuerpoEmpaque($texto);
        $linea = OrdentrabajoEmisionTextoSupport::PREFIJO_EMPAQUE.$cuerpo;

        self::assertNotSame($texto, $cuerpo);
        $resto = substr($texto, strlen($cuerpo));
        self::assertNotSame('', $resto);
        self::assertContains($resto[0], [' ', '/']);
        self::assertLessThanOrEqual(
            OrdentrabajoEmisionTextoSupport::ANCHO_EMPAQUE_MM,
            OrdentrabajoEmisionTextoSupport::anchoMm($linea)
        );
    }

    public function test_sin_barra_de_material_corta_en_el_espacio(): void
    {
        $texto = str_repeat('PALABRA ', 40);
        $lineas = OrdentrabajoEmisionTextoSupport::partir(trim($texto), 2, 80.0);

        self::assertNotSame('', $lineas[0]);
        self::assertStringEndsWith('PALABRA', $lineas[0]);
        self::assertStringStartsWith('PALABRA', $lineas[1]);
        self::assertLessThanOrEqual(80.0, OrdentrabajoEmisionTextoSupport::anchoMm($lineas[0]));
    }

    public function test_lista_larga_de_piezas_de_la_ot_31432_entra_entera(): void
    {
        $texto = '1.2.3.4.5.8.9.10.11.12.13.14.15 SKIN.DESCARNE CINZA -2.24- P/6.7 WOLF CROCHET CINZA -0.99- P';
        $lineas = OrdentrabajoEmisionTextoSupport::partirMaterial($texto);

        self::assertSame(
            '1.2.3.4.5.8.9.10.11.12.13.14.15 SKIN.DESCARNE CINZA -2.24- P',
            $lineas[0]
        );
        self::assertSame('6.7 WOLF CROCHET CINZA -0.99- P', $lineas[1]);
        self::assertSame('', $lineas[2]);
        self::assertStringContainsString('10.11.12.13.14.15', $lineas[0]);
        foreach ($lineas as $linea) {
            self::assertLessThanOrEqual(
                OrdentrabajoEmisionTextoSupport::ANCHO_MATERIAL_MM,
                OrdentrabajoEmisionTextoSupport::anchoMm($linea)
            );
        }
    }

    public function test_lista_de_piezas_sin_espacio_corta_en_el_punto(): void
    {
        $piezas = '1.2.3.4.5.6.7.8.9.10.11.12.13.14.15.16.17.18.19.20.21.22.23.24.25.26.27.28.29.30';
        $lineas = OrdentrabajoEmisionTextoSupport::partir($piezas, 2, 80.0);

        self::assertMatchesRegularExpression('/^\d+(\.\d+)*$/', $lineas[0]);
        self::assertMatchesRegularExpression('/^\d+(\.\d+)*$/', $lineas[1]);
        self::assertSame($piezas, $lineas[0].'.'.$lineas[1]);
        self::assertLessThanOrEqual(80.0, OrdentrabajoEmisionTextoSupport::anchoMm($lineas[0]));
    }

    public function test_apliques_largos_parten_sin_cortar_palabra(): void
    {
        $texto = 'ARGO.6.5 NIQUEL 48.00/ETIQUETA FRAGOL VISON 16.00/DIJE FRAGOLA NIQUEL 16.00/CADENA METALICA 16.00';
        $lineas = OrdentrabajoEmisionTextoSupport::partirAplique($texto);

        self::assertStringContainsString('ARGO.6.5 NIQUEL', $lineas[0]);
        self::assertStringContainsString('CADENA METALICA', implode(' ', $lineas));
        self::assertLessThanOrEqual(
            OrdentrabajoEmisionTextoSupport::ANCHO_APLIQUE_LINEA1_MM,
            OrdentrabajoEmisionTextoSupport::anchoMm(
                OrdentrabajoEmisionTextoSupport::PREFIJO_APLIQUE.$lineas[0]
            )
        );
        foreach (array_slice($lineas, 1) as $linea) {
            self::assertLessThanOrEqual(
                OrdentrabajoEmisionTextoSupport::ANCHO_APLIQUE_LINEA2_MM,
                OrdentrabajoEmisionTextoSupport::anchoMm($linea)
            );
        }
    }
}
