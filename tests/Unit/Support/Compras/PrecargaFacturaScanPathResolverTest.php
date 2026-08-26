<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\PrecargaFacturaScanPathResolver;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PrecargaFacturaScanPathResolverTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/factura_scan_'.bin2hex(random_bytes(4));
        mkdir($this->tmp.'/comprobantes/30-70774390-1/2026-08', 0777, true);
        mkdir($this->tmp.'/comprobantes-revisar/2026-08', 0777, true);
        file_put_contents(
            $this->tmp.'/comprobantes/30-70774390-1/2026-08/FGA-A-00014-00038524.pdf',
            '%PDF-1.4'
        );
        file_put_contents(
            $this->tmp.'/comprobantes-revisar/2026-08/20260821_123210_099.pdf',
            '%PDF-1.4'
        );
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
    }

    public function test_resuelve_pdf_en_comprobantes_revisar_desde_unc(): void
    {
        $path = $this->resolver()->resolve(
            '//10.20.30.37/Facturas_scan/comprobantes-revisar/2026-08/20260821_123210_099.pdf'
        );

        $this->assertSame(
            $this->tmp.'/comprobantes-revisar/2026-08/20260821_123210_099.pdf',
            $path
        );
    }

    public function test_resuelve_pdf_en_comprobantes_revisar_con_barras_windows(): void
    {
        $path = $this->resolver()->resolve(
            '//10.20.30.37/Facturas_scan\\comprobantes-revisar\\2026-08\\20260821_123210_099.pdf'
        );

        $this->assertSame(
            $this->tmp.'/comprobantes-revisar/2026-08/20260821_123210_099.pdf',
            $path
        );
    }

    public function test_no_confunde_comprobantes_revisar_con_comprobantes(): void
    {
        $path = $this->resolver()->resolve(
            'storage:/comprobantes-revisar/2026-08/20260821_123210_099.pdf'
        );

        $this->assertSame(
            $this->tmp.'/comprobantes-revisar/2026-08/20260821_123210_099.pdf',
            $path
        );
        $this->assertStringContainsString('comprobantes-revisar', (string) $path);
        $this->assertStringNotContainsString('comprobantes/revisar', (string) $path);
    }

    public function test_sigue_resolviendo_comprobantes_canonico(): void
    {
        $path = $this->resolver()->resolve(
            'storage:/comprobantes/30-70774390-1/2026-08/FGA-A-00014-00038524.pdf'
        );

        $this->assertSame(
            $this->tmp.'/comprobantes/30-70774390-1/2026-08/FGA-A-00014-00038524.pdf',
            $path
        );
    }

    public function test_encuentra_por_basename_en_revisar(): void
    {
        $path = $this->resolver()->resolve('20260821_123210_099.pdf');

        $this->assertSame(
            $this->tmp.'/comprobantes-revisar/2026-08/20260821_123210_099.pdf',
            $path
        );
    }

    public function test_rechaza_path_con_parent_dir(): void
    {
        $this->assertNull(
            $this->resolver()->resolve('../comprobantes-revisar/2026-08/20260821_123210_099.pdf')
        );
    }

    private function resolver(): PrecargaFacturaScanPathResolver
    {
        return (new PrecargaFacturaScanPathResolver())->withBasePath($this->tmp);
    }

    private function rrmdir(string $dir): void
    {
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
