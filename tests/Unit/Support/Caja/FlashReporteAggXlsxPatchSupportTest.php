<?php

namespace Tests\Unit\Support\Caja;

use App\Services\Caja\Flash\FlashReporteAggExcelService;
use App\Support\Caja\Flash\FlashReporteAggXlsxPatchSupport;
use Tests\TestCase;
use ZipArchive;

class FlashReporteAggXlsxPatchSupportTest extends TestCase
{
    public function test_rellena_celdas_sin_romper_el_paquete_oficial(): void
    {
        $plantilla = (new FlashReporteAggExcelService)->rutaPlantilla();
        $path = sys_get_temp_dir().'/flash-agg-patch-'.uniqid('', true).'.xlsx';

        try {
            (new FlashReporteAggXlsxPatchSupport)->rellenar($plantilla, $path, [
                'Datos Biyemas' => [
                    'A9' => 'Sab/Sat    ',
                    'B9' => ' 01/08/26 ',
                    'C9' => 3482,
                    'AU9' => 194838676.5,
                ],
            ]);

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);

            $nombres = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nombres[] = $zip->getNameIndex($i);
            }
            $this->assertContains('xl/charts/chart8.xml', $nombres);
            $this->assertContains('xl/drawings/drawing1.xml', $nombres);
            $this->assertNotContains('xl/drawings/drawing6.xml', $nombres);

            $datos = $zip->getFromName('xl/worksheets/sheet7.xml');
            $this->assertNotFalse($datos);
            $this->assertStringContainsString('<c r="C9"', $datos);
            $this->assertStringContainsString('<v>3482</v>', $datos);
            $this->assertStringContainsString('<v>194838676.5</v>', $datos);

            $shared = $zip->getFromName('xl/sharedStrings.xml');
            $this->assertNotFalse($shared);
            $this->assertStringContainsString('xml:space="preserve"', $shared);

            $zip->close();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
