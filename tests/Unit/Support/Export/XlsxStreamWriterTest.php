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
}
