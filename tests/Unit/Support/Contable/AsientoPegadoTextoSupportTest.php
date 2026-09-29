<?php

namespace Tests\Unit\Support\Contable;

use App\Support\Contable\AsientoImportColumnasSupport;
use App\Support\Contable\AsientoPegadoTextoSupport;
use PHPUnit\Framework\TestCase;

class AsientoPegadoTextoSupportTest extends TestCase
{
    public function test_importes_argentinos_y_de_excel_ingles(): void
    {
        $this->assertSame(1025504.12, AsientoImportColumnasSupport::parsearImporteTexto('1.025.504,12'));
        $this->assertSame(1025504.12, AsientoImportColumnasSupport::parsearImporteTexto('1,025,504.12'));
        $this->assertSame(1500.0, AsientoImportColumnasSupport::parsearImporteTexto('1500,00'));
        $this->assertSame(1500.5, AsientoImportColumnasSupport::parsearImporteTexto('1500.50'));
        $this->assertSame(1025.0, AsientoImportColumnasSupport::parsearImporteTexto('1.025'));
        $this->assertSame(8912936.58, AsientoImportColumnasSupport::parsearImporteTexto('8.912.936.58'));
        $this->assertSame(2494744.04, AsientoImportColumnasSupport::parsearImporteTexto('2.494.744.04'));
        $this->assertSame(11407680.62, AsientoImportColumnasSupport::parsearImporteTexto('11.407.680.62'));
        $this->assertSame(2494744.04, AsientoImportColumnasSupport::parsearImporteTexto('2.494,744.04'));
        $this->assertNull(AsientoImportColumnasSupport::parsearImporteTexto(''));
    }

    public function test_decodifica_grilla_anita_con_encabezado_y_omite_el_total(): void
    {
        $texto = implode("\n", [
            'mar jun 23 14:07:13 ART 2026',
            'Asientos contables',
            "Nro.Cta.\tDescripcion\tC.Cos.\tDebe\tHaber\tDescripcion del movimiento\tTip\tNumero comp.",
            "532030-010\tINTERESES CANON CFA\t97\t1,025,504.12\t\tDev Intereses Canon CFA\t\t",
            "215010-013\tCANON CFA INTERESES A DEV\t\t\t1.106.104,98\tDev Intereses Canon CFA\t\t",
            "Total asiento\t\t\t1,025,504.12\t1.106.104,98\t\t\t",
        ]);

        $resultado = AsientoPegadoTextoSupport::decodificar($texto);

        $this->assertTrue($resultado['con_encabezado']);
        $this->assertCount(2, $resultado['filas']);
        $this->assertSame('532030-010', $resultado['filas'][0]['codigo_cuenta']);
        $this->assertSame('97', $resultado['filas'][0]['codigo_centrocosto']);
        $this->assertSame(1025504.12, $resultado['filas'][0]['debe']);
        $this->assertSame(0.0, $resultado['filas'][0]['haber']);
        $this->assertSame('Dev Intereses Canon CFA', $resultado['filas'][0]['detalle']);
        $this->assertSame('215010-013', $resultado['filas'][1]['codigo_cuenta']);
        $this->assertSame('', $resultado['filas'][1]['codigo_centrocosto']);
        $this->assertSame(0.0, $resultado['filas'][1]['debe']);
        $this->assertSame(1106104.98, $resultado['filas'][1]['haber']);
        $this->assertSame('Dev Intereses Canon CFA', $resultado['filas'][1]['detalle']);
    }

    public function test_sin_encabezado_usa_el_orden_del_listado(): void
    {
        $texto = "532030-010\tINTERESES CANON CFA\t97\t1.025.504,12\t\tDev Intereses Canon CFA\n"
            ."215010-013\tCANON CFA INTERESES A DEV\t\t\t1.106.104,98\tDev Intereses Canon CFA";

        $resultado = AsientoPegadoTextoSupport::decodificar($texto);

        $this->assertFalse($resultado['con_encabezado']);
        $this->assertCount(2, $resultado['filas']);
        $this->assertSame('97', $resultado['filas'][0]['codigo_centrocosto']);
        $this->assertSame(1025504.12, $resultado['filas'][0]['debe']);
        $this->assertSame('Dev Intereses Canon CFA', $resultado['filas'][0]['detalle']);
        $this->assertSame(1106104.98, $resultado['filas'][1]['haber']);
    }

    public function test_impresion_alineada_asigna_debe_y_haber_aunque_el_importe_invada_la_columna(): void
    {
        $texto = implode("\n", [
            'Asientos contables',
            $this->linea([0 => 'Nro.Cta.', 18 => 'Descripcion', 52 => 'C.Cos.', 62 => 'Debe', 80 => 'Haber', 96 => 'Descripcion del movimiento']),
            $this->linea([0 => '532030-010', 18 => 'INTERESES CANON CFA', 52 => '97', 62 => '1,025,504.12', 96 => 'Dev Intereses Canon CFA']),
            $this->linea([0 => '215010-013', 18 => 'CANON CFA INTERESES A DEV', 70 => '1,106,104.98', 96 => 'Dev Intereses Canon CFA']),
            $this->linea([0 => 'Total asiento', 62 => '1,025,504.12', 78 => '1,106,104.98']),
        ]);

        $resultado = AsientoPegadoTextoSupport::decodificar($texto);

        $this->assertTrue($resultado['con_encabezado']);
        $this->assertCount(2, $resultado['filas']);
        $this->assertSame('532030-010', $resultado['filas'][0]['codigo_cuenta']);
        $this->assertSame('97', $resultado['filas'][0]['codigo_centrocosto']);
        $this->assertSame(1025504.12, $resultado['filas'][0]['debe']);
        $this->assertSame(0.0, $resultado['filas'][0]['haber']);
        $this->assertSame('Dev Intereses Canon CFA', $resultado['filas'][0]['detalle']);
        $this->assertSame('215010-013', $resultado['filas'][1]['codigo_cuenta']);
        $this->assertSame('', $resultado['filas'][1]['codigo_centrocosto']);
        $this->assertSame(0.0, $resultado['filas'][1]['debe']);
        $this->assertSame(1106104.98, $resultado['filas'][1]['haber']);
    }

    /**
     * @param  array<int, string>  $campos
     */
    private function linea(array $campos): string
    {
        $linea = '';
        foreach ($campos as $columna => $texto) {
            $columna = (int) $columna;
            if (strlen($linea) < $columna) {
                $linea .= str_repeat(' ', $columna - strlen($linea));
            }
            $linea = substr_replace(
                $linea.str_repeat(' ', max(0, $columna + strlen($texto) - strlen($linea))),
                $texto,
                $columna,
                strlen($texto)
            );
        }

        return rtrim($linea);
    }

    public function test_descripcion_simple_queda_como_detalle_si_no_hay_columna_de_movimiento(): void
    {
        $texto = "cuenta\tdescripcion\tdebe\thaber\n"
            ."111010001\tCaja\t1500,00\t\n"
            ."411010001\tVentas\t\t1.500,00";

        $resultado = AsientoPegadoTextoSupport::decodificar($texto);

        $this->assertCount(2, $resultado['filas']);
        $this->assertSame('Caja', $resultado['filas'][0]['detalle']);
        $this->assertSame(1500.0, $resultado['filas'][0]['debe']);
        $this->assertSame(1500.0, $resultado['filas'][1]['haber']);
        $this->assertSame('Ventas', $resultado['filas'][1]['detalle']);
    }

    public function test_el_importe_derecho_de_la_impresion_va_al_haber_y_el_cc_queda_en_97(): void
    {
        $texto = "532030-010 INTERESES CANON CFA 97 1,025,504.12 Dev Intereses Canon CFA\n"
            ."215010-013 CANON CFA INTERESES A DEV 1,106,104.98 Dev Intereses Canon CFA\n"
            ."Total asiento 1,025,504.12 1,106,104.98";
        $lineas = [[
            ['t' => '1,025,504.12', 'x1' => 800, 'x2' => 1000],
            ['t' => '1,106,104.98', 'x1' => 1080, 'x2' => 1320],
            ['t' => 'Debe', 'x1' => 700, 'x2' => 760],
            ['t' => 'Haber', 'x1' => 1700, 'x2' => 1780],
        ]];

        $resultado = AsientoPegadoTextoSupport::decodificarOcr($texto, $lineas);

        $this->assertCount(2, $resultado['filas']);
        $this->assertSame('97', $resultado['filas'][0]['codigo_centrocosto']);
        $this->assertSame(1025504.12, $resultado['filas'][0]['debe']);
        $this->assertSame(0.0, $resultado['filas'][0]['haber']);
        $this->assertSame(0.0, $resultado['filas'][1]['debe']);
        $this->assertSame(1106104.98, $resultado['filas'][1]['haber']);
        $this->assertSame('', $resultado['filas'][1]['codigo_centrocosto']);
    }

    public function test_revaluo_anita_lee_el_mismo_importe_en_debe_y_en_haber(): void
    {
        $texto = implode("\n", [
            'Nro.Cta. Descripcion C.Cos. Debe Haber',
            '521090-003 AMORT. CANON CFE 8.912.936.58',
            '52 ¡090-004 AMORT. CANON CFA 2.494,744.04',
            '124010-011 AMORT ACUMULADA CANON CFE 8.912.936.58',
            '124010-013 AMORT ACUMULADA CANON CFA 2.494.744.04',
            'Total asiento 11.407.680.62 11.407.680.62',
        ]);
        $lineas = [
            [
                ['t' => 'Nro.Cta.', 'x1' => 40, 'x2' => 120],
                ['t' => 'Descripcion', 'x1' => 180, 'x2' => 320],
                ['t' => 'C.Cos.', 'x1' => 480, 'x2' => 540],
                ['t' => 'Debe', 'x1' => 700, 'x2' => 760],
                ['t' => 'Haber', 'x1' => 980, 'x2' => 1060],
            ],
            [
                ['t' => '521090-003', 'x1' => 40, 'x2' => 160],
                ['t' => 'AMORT.', 'x1' => 180, 'x2' => 260],
                ['t' => 'CANON', 'x1' => 270, 'x2' => 340],
                ['t' => 'CFE', 'x1' => 350, 'x2' => 400],
                ['t' => '8.912.936.58', 'x1' => 620, 'x2' => 820],
            ],
            [
                ['t' => '52', 'x1' => 40, 'x2' => 70],
                ['t' => '¡090-004', 'x1' => 72, 'x2' => 160],
                ['t' => 'AMORT.', 'x1' => 180, 'x2' => 260],
                ['t' => 'CANON', 'x1' => 270, 'x2' => 340],
                ['t' => 'CFA', 'x1' => 350, 'x2' => 400],
                ['t' => '2.494.744.04', 'x1' => 640, 'x2' => 820],
            ],
            [
                ['t' => '124010-011', 'x1' => 40, 'x2' => 160],
                ['t' => 'AMORT', 'x1' => 180, 'x2' => 260],
                ['t' => 'ACUMULADA', 'x1' => 270, 'x2' => 400],
                ['t' => 'CANON', 'x1' => 410, 'x2' => 480],
                ['t' => 'CFE', 'x1' => 490, 'x2' => 540],
                ['t' => '8.912.936.58', 'x1' => 900, 'x2' => 1100],
            ],
            [
                ['t' => '12á010-013', 'x1' => 40, 'x2' => 160],
                ['t' => 'AMORT', 'x1' => 180, 'x2' => 260],
                ['t' => 'ACUMULADA', 'x1' => 270, 'x2' => 400],
                ['t' => 'CANON', 'x1' => 410, 'x2' => 480],
                ['t' => 'CFA', 'x1' => 490, 'x2' => 540],
                ['t' => '2.494.74d.04', 'x1' => 900, 'x2' => 1100],
            ],
        ];

        $resultado = AsientoPegadoTextoSupport::decodificarOcr($texto, $lineas);

        $this->assertCount(4, $resultado['filas']);
        $this->assertSame('521090-003', $resultado['filas'][0]['codigo_cuenta']);
        $this->assertSame(8912936.58, $resultado['filas'][0]['debe']);
        $this->assertSame(0.0, $resultado['filas'][0]['haber']);
        $this->assertSame('AMORT. CANON CFE', $resultado['filas'][0]['detalle']);
        $this->assertSame('521090-004', $resultado['filas'][1]['codigo_cuenta']);
        $this->assertSame(2494744.04, $resultado['filas'][1]['debe']);
        $this->assertSame(0.0, $resultado['filas'][1]['haber']);
        $this->assertSame('124010-011', $resultado['filas'][2]['codigo_cuenta']);
        $this->assertSame(0.0, $resultado['filas'][2]['debe']);
        $this->assertSame(8912936.58, $resultado['filas'][2]['haber']);
        $this->assertSame('124010-013', $resultado['filas'][3]['codigo_cuenta']);
        $this->assertSame(0.0, $resultado['filas'][3]['debe']);
        $this->assertSame(2494744.04, $resultado['filas'][3]['haber']);
    }

    public function test_si_el_texto_pierde_una_cuenta_la_toma_del_hocr(): void
    {
        $texto = implode("\n", [
            '521090-003 AMORT. CANON CFE 8.912.936.58',
            '124010-011 AMORT ACUMULADA CANON CFE 8.912.936.58',
        ]);
        $lineas = [
            [
                ['t' => '521090-003', 'x1' => 40, 'x2' => 160],
                ['t' => '8.912.936.58', 'x1' => 620, 'x2' => 820],
            ],
            [
                ['t' => '521090-004', 'x1' => 40, 'x2' => 160],
                ['t' => 'AMORT.', 'x1' => 180, 'x2' => 260],
                ['t' => '2.494.744.04', 'x1' => 640, 'x2' => 820],
            ],
            [
                ['t' => '124010-011', 'x1' => 40, 'x2' => 160],
                ['t' => '8.912.936.58', 'x1' => 900, 'x2' => 1100],
            ],
            [
                ['t' => '124010-013', 'x1' => 40, 'x2' => 160],
                ['t' => '2.494.744.04', 'x1' => 900, 'x2' => 1100],
            ],
        ];

        $resultado = AsientoPegadoTextoSupport::decodificarOcr($texto, $lineas);

        $this->assertCount(4, $resultado['filas']);
        $this->assertSame('521090-004', $resultado['filas'][2]['codigo_cuenta']);
        $this->assertSame(2494744.04, $resultado['filas'][2]['debe']);
        $this->assertSame('124010-013', $resultado['filas'][3]['codigo_cuenta']);
        $this->assertSame(2494744.04, $resultado['filas'][3]['haber']);
    }
}
