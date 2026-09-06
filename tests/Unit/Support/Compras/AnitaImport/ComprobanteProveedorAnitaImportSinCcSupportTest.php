<?php

namespace Tests\Unit\Support\Compras\AnitaImport;

use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportSinCcSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAnitaImportSinCcSupportTest extends TestCase
{
    public function test_dry_run_sin_cc_omite_aplicaciones_y_cc(): void
    {
        $stats = [
            'aplicaciones_anita' => 12,
            'adelantos_a_crear' => 3,
        ];
        $pares = array_fill(0, 12, ['credito_es_pago' => false]);

        $out = ComprobanteProveedorAnitaImportSinCcSupport::aplicarDryRun($stats, $pares, true);

        $this->assertTrue($out['sin_cuenta_corriente']);
        $this->assertSame(0, $out['aplicaciones']);
        $this->assertSame(0, $out['aplicaciones_pago_sintetico']);
        $this->assertSame(12, $out['aplicaciones_omitidas']);
        $this->assertSame(0, $out['cc']);
        $this->assertSame(3, $out['adelantos_a_crear_documento']);
    }

    public function test_dry_run_con_cc_cuenta_aplicaciones(): void
    {
        $stats = ['adelantos_a_crear' => 1];
        $pares = [
            ['credito_es_pago' => true],
            ['credito_es_pago' => false],
        ];

        $out = ComprobanteProveedorAnitaImportSinCcSupport::aplicarDryRun($stats, $pares, false);

        $this->assertFalse($out['sin_cuenta_corriente']);
        $this->assertSame(2, $out['aplicaciones']);
        $this->assertSame(1, $out['aplicaciones_pago_sintetico']);
    }
}
