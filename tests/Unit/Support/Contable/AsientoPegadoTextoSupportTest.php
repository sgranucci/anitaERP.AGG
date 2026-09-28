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
}
