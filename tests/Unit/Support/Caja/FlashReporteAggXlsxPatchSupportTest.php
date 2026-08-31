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
                'Datos Biyemas' => array_merge(
                    \App\Support\Caja\Flash\FlashReporteAggMapeoSupport::encabezadosHojaDatos(),
                    [
                        'A9' => 'Sab/Sat    ',
                        'B9' => ' 01/08/26 ',
                        'C9' => 3482,
                        'AU9' => 194838676.5,
                    ]
                ),
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
            $this->assertStringContainsString('<c r="BC7"', $datos);
            $this->assertStringContainsString('<c r="A8"', $datos);

            $shared = $zip->getFromName('xl/sharedStrings.xml');
            $this->assertNotFalse($shared);
            $this->assertStringContainsString('xml:space="preserve"', $shared);
            $this->assertStringContainsString('>Electronic</t>', $shared);
            $this->assertStringContainsString('> Day       </t>', $shared);

            $tablaXml = $zip->getFromName('xl/worksheets/sheet5.xml');
            $this->assertNotFalse($tablaXml);
            $this->assertStringContainsString('HLOOKUP("Electronic",', $tablaXml);
            $this->assertStringNotContainsString('r="J3"', $tablaXml);

            $zip->close();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_sanea_hoja_tabla_a_solo_a_g(): void
    {
        $patcher = new FlashReporteAggXlsxPatchSupport;
        $xml = '<worksheet><dimension ref="A2:O23"/><cols><col min="9" max="12" hidden="1"/></cols>'
            .'<sheetData><row r="3"><c r="E3"><f>HLOOKUP(J3,Resumen!BE5:BH39,1,0)</f></c>'
            .'<c r="J3" t="s"><v>42</v></c></row><row r="14"><c r="L14"/></row></sheetData>'
            .'<mergeCells count="1"><mergeCell ref="J3:J4"/></mergeCells></worksheet>';

        $saneado = $patcher->sanearXmlHojaTabla($xml);

        $this->assertStringContainsString('HLOOKUP("Electronic",', $saneado);
        $this->assertStringNotContainsString('HLOOKUP(J3,', $saneado);
        $this->assertStringNotContainsString('r="J3"', $saneado);
        $this->assertStringNotContainsString('mergeCells', $saneado);
        $this->assertStringContainsString('ref="A1:G13"', $saneado);
        $this->assertStringContainsString('width="17.138671875"', $saneado);
        $this->assertStringNotContainsString('<row r="14"', $saneado);
    }
}
