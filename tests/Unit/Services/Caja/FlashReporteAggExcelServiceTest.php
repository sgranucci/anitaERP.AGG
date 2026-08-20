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
            $this->assertTrue($tabla->getColumnDimension('D')->getVisible(), 'Tabla D debe verse (vs año ant)');
            foreach (['G', 'H', 'I', 'J', 'K', 'L'] as $col) {
                $this->assertFalse($tabla->getColumnDimension($col)->getVisible(), "Tabla {$col} debe permanecer oculta");
            }

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

    public function test_la_plantilla_oficial_se_guarda_sin_precalcular_formulas(): void
    {
        $service = new FlashReporteAggExcelService;
        $spreadsheet = IOFactory::load($service->rutaPlantilla());
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.'/flash-reporte-agg-test-'.uniqid('', true).'.xlsx';

        try {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save($path);

            $this->assertFileExists($path);
            $this->assertGreaterThan(1000, filesize($path));
        } finally {
            $spreadsheet->disconnectWorksheets();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
