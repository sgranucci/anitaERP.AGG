<?php

namespace Tests\Unit\Services\Caja;

use App\Services\Caja\Flash\FlashReporteAggExcelService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FlashReporteAggExcelServiceTest extends TestCase
{
    public function test_la_plantilla_oficial_esta_en_el_repo(): void
    {
        $service = new FlashReporteAggExcelService;
        $ruta = $service->rutaPlantilla();

        $this->assertFileExists($ruta);
        $this->assertGreaterThan(100000, filesize($ruta));
    }

    public function test_la_plantilla_respeta_columnas_ocultas_del_formato_oficial(): void
    {
        $service = new FlashReporteAggExcelService;
        $spreadsheet = IOFactory::load($service->rutaPlantilla());

        try {
            $tabla = $spreadsheet->getSheetByName('Tabla');
            $this->assertNotNull($tabla);
            foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $col) {
                $this->assertTrue($tabla->getColumnDimension($col)->getVisible(), "Tabla {$col} debe verse (Slots/Ruletas/Total EP/Electronic/vs mes ant/vs año ant)");
            }
            foreach (['I', 'J', 'K', 'L'] as $col) {
                $this->assertFalse($tabla->getColumnDimension($col)->getVisible(), "Tabla {$col} debe permanecer oculta");
            }
            $this->assertSame('Slots', (string) $tabla->getCell('B2')->getValue());
            $this->assertSame('Ruletas', (string) $tabla->getCell('C2')->getValue());
            $this->assertSame('Total EP', (string) $tabla->getCell('D2')->getValue());
            $this->assertSame('Total EP', (string) $tabla->getCell('D8')->getValue());
            $this->assertSame('=INDEX(\'Biyemas S.A.\'!G7:G37,\'Biyemas S.A.\'!D1)', $tabla->getCell('B4')->getValue());
            $this->assertSame('=INDEX(\'Biyemas S.A.\'!O7:O37,\'Biyemas S.A.\'!D1)', $tabla->getCell('C4')->getValue());
            $this->assertSame('=INDEX(\'Kandiko S.A\'!G7:G37,\'Kandiko S.A\'!D1)', $tabla->getCell('B5')->getValue());
            $this->assertSame('=INDEX(\'Rebisco S.A.\'!G7:G37,\'Rebisco S.A.\'!D1)', $tabla->getCell('B6')->getValue());
            $this->assertSame('=SUM(B4,C4)', (string) $tabla->getCell('D4')->getValue());
            $this->assertSame('=SUM(D4:D6)', (string) $tabla->getCell('D3')->getValue());
            $this->assertSame('=SUM(B10,C10)', (string) $tabla->getCell('D10')->getValue());
            $this->assertSame('=SUM(D10:D12)', (string) $tabla->getCell('D9')->getValue());
            $this->assertSame(
                '[$$-2C0A]\\ #,##0',
                $tabla->getStyle('D4')->getNumberFormat()->getFormatCode()
            );
            $this->assertSame('=+\'Biyemas S.A.\'!G39', (string) $tabla->getCell('B10')->getValue());
            $this->assertSame('=+\'Biyemas S.A.\'!O39', (string) $tabla->getCell('C10')->getValue());
            $this->assertStringContainsString('HLOOKUP(J3,', (string) $tabla->getCell('E3')->getValue());

            $resumen = $spreadsheet->getSheetByName('Resumen');
            $this->assertNotNull($resumen);
            foreach (range(22, 29) as $idx) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx);
                $this->assertFalse($resumen->getColumnDimension($col)->getVisible(), "Resumen {$col} (poker) sigue oculta");
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function test_el_relleno_conserva_graficos_formulas_y_drawing_oficial(): void
    {
        $service = new FlashReporteAggExcelService;
        $patcher = new \App\Support\Caja\Flash\FlashReporteAggXlsxPatchSupport;
        $path = sys_get_temp_dir().'/flash-reporte-agg-test-'.uniqid('', true).'.xlsx';

        try {
            $patcher->rellenar($service->rutaPlantilla(), $path, [
                'Datos Biyemas' => [
                    'A1' => 'prueba patch',
                    'A9' => 'Sab/Sat    ',
                    'C9' => 2065,
                    'A35' => 'Total final',
                    'A43' => 'Total final mes ant.  ',
                    'C43' => 64391,
                ],
                'Biyemas S.A.' => [
                    'A3' => 'Reporte Flash Agosto 26',
                    'BP7' => 'Sab/Sat    ',
                ],
            ]);

            $this->assertFileExists($path);
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $this->assertNotFalse($zip->locateName('xl/charts/chart1.xml'));
            $this->assertNotFalse($zip->locateName('xl/drawings/drawing1.xml'));
            $this->assertFalse($zip->locateName('xl/drawings/drawing6.xml'));
            $this->assertFalse($zip->locateName('xl/calcChain.xml'));

            $seasonRels = $zip->getFromName('xl/worksheets/_rels/sheet6.xml.rels');
            $this->assertNotFalse($seasonRels);
            $this->assertStringContainsString('drawing1.xml', $seasonRels);

            $biyemas = $zip->getFromName('xl/worksheets/sheet2.xml');
            $this->assertNotFalse($biyemas);
            $this->assertStringContainsString("IF(BP7=A7,'Datos Biyemas'!C9", $biyemas);
            $this->assertStringContainsString("VLOOKUP(+BP45,'Datos Biyemas'!A9:BB145,3,0)", $biyemas);
            $this->assertStringNotContainsString('tableParts', $biyemas);

            $datos = $zip->getFromName('xl/worksheets/sheet7.xml');
            $this->assertNotFalse($datos);
            $this->assertStringContainsString('<v>2065</v>', $datos);

            $workbook = $zip->getFromName('xl/workbook.xml');
            $this->assertNotFalse($workbook);
            $this->assertStringContainsString('fullCalcOnLoad="1"', $workbook);

            $zip->close();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
