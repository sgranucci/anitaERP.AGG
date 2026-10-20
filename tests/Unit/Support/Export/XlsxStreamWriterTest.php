<?php

namespace Tests\Unit\Support\Export;

use App\Support\Export\XlsxStreamWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class XlsxStreamWriterTest extends TestCase
{
    public function test_genera_xlsx_con_numeros_y_texto(): void
    {
        $path = sys_get_temp_dir().'/mayor-plano-'.uniqid('', true).'.xlsx';

        try {
            $writer = new XlsxStreamWriter($path, 'Mayor plano');
            $writer->escribirCabecera(['Empresa', 'Nro.Asi.', 'Debe', 'Detalle']);
            $writer->escribirFila([2, '223358', 1500.5, 'Compra & "OC"']);
            $info = $writer->cerrar();

            $this->assertSame(1, $info['filas']);
            $this->assertGreaterThan(0, $info['bytes']);
            $this->assertFileExists($path);

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $workbook = (string) $zip->getFromName('xl/workbook.xml');
            $zip->close();

            $this->assertStringContainsString('Mayor plano', $workbook);
            $this->assertStringContainsString('1500.5', $sheet);
            $this->assertStringContainsString('Compra &amp; &quot;OC&quot;', $sheet);
            $this->assertStringContainsString('autoFilter', $sheet);
            $this->assertStringContainsString(
                '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>',
                $sheet
            );
            $this->assertStringContainsString('<workbookView activeTab="0"/>', $workbook);
            $this->assertStringContainsString('<cols>', $sheet);
            // Empresa / Nro.Asi. no llevan máscara de plata (evita 2.00 y #####).
            $this->assertMatchesRegularExpression('/<c r="A2" t="n"><v>2<\\/v><\\/c>/', $sheet);
            $this->assertMatchesRegularExpression('/<c r="B2" t="n"><v>223358<\\/v><\\/c>/', $sheet);
            $this->assertMatchesRegularExpression('/<c r="C2" t="n" s="2"><v>1500\\.5<\\/v><\\/c>/', $sheet);
        } finally {
            @unlink($path);
            @unlink($path.'.sheet.xml');
        }
    }

    public function test_encabezado_de_reporte_lleva_titulo_y_logo(): void
    {
        $path = sys_get_temp_dir().'/mayor-plano-'.uniqid('', true).'.xlsx';
        $logo = sys_get_temp_dir().'/mayor-logo-'.uniqid('', true).'.png';
        file_put_contents($logo, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        try {
            $writer = new XlsxStreamWriter($path, 'Mayor por cuenta');
            $writer->setEncabezadoReporte([
                'Mayor analítico por cuenta contable',
                'Generado 19/10/2026 16:30',
            ], $logo);
            $writer->escribirCabecera(['Fecha', 'Debe']);
            $writer->escribirFila(['01/01/2026', 10]);
            $info = $writer->cerrar();

            $this->assertSame(1, $info['filas']);

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertNotFalse($zip->getFromName('xl/media/logo.png'));
            $this->assertNotFalse($zip->getFromName('xl/drawings/drawing1.xml'));
            $zip->close();

            $this->assertStringContainsString('Mayor analítico por cuenta contable', $sheet);
            $this->assertStringContainsString('Generado 19/10/2026 16:30', $sheet);
            $this->assertStringContainsString('ySplit="4"', $sheet);
            $this->assertStringContainsString('activeCell="A5"', $sheet);
            $this->assertStringContainsString('autoFilter ref="A4:', $sheet);
            $this->assertStringContainsString('<drawing r:id="rId1"/>', $sheet);
        } finally {
            @unlink($path);
            @unlink($path.'.sheet.xml');
            @unlink($logo);
        }
    }
}
