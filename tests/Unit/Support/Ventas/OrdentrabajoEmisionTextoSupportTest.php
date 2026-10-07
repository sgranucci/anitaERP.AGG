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
}
