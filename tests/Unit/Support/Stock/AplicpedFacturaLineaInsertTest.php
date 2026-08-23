<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use PHPUnit\Framework\TestCase;

class AplicpedFacturaLineaInsertTest extends TestCase
{
    public function test_factura_hacia_pep_pone_cantidad_en_cantfact(): void
    {
        $insert = RecepcionProveedorAnitaEscrituraSupport::aplicpedFacturaLineaInsert(
            '3593',
            ['tipo' => 'FGA', 'letra' => 'A', 'sucursal' => 3, 'nro' => 946427],
            ['tipo' => 'PEP', 'letra' => 'X', 'sucursal' => 0, 'nro' => 218023],
            1,
            5,
            RecepcionProveedorAnitaEscrituraSupport::skuAnita13('123456'),
            12.5,
            7001,
        );

        $this->assertStringContainsString('aplp_tipo', $insert['campos']);
        $this->assertStringContainsString('aplp_ref_tipo', $insert['campos']);
        $this->assertStringContainsString('aplp_cantfact', $insert['campos']);
        $this->assertStringContainsString("'FGA'", $insert['valores']);
        $this->assertStringContainsString("'PEP'", $insert['valores']);
        $this->assertStringContainsString("'X'", $insert['valores']);
        $this->assertStringContainsString('218023', $insert['valores']);
        $this->assertStringContainsString('12.500000', $insert['valores']);
        $this->assertStringContainsString('0.000000', $insert['valores']); // cantentr
        $this->assertStringContainsString('7001', $insert['valores']);
    }

    public function test_com_sigue_poniendo_cantidad_en_cantentr(): void
    {
        $insert = RecepcionProveedorAnitaEscrituraSupport::aplicpedLineaInsert(
            '3593',
            ['tipo' => 'COM', 'letra' => 'X', 'sucursal' => 0, 'nro' => 100],
            ['tipo' => 'PEP', 'letra' => 'X', 'sucursal' => 0, 'nro' => 218023],
            1,
            5,
            RecepcionProveedorAnitaEscrituraSupport::skuAnita13('123456'),
            12.5,
            7001,
        );

        $this->assertStringContainsString("'COM'", $insert['valores']);
        $this->assertStringContainsString('12.500000', $insert['valores']);
        // cantfact sigue en 0 para COM
        $this->assertMatchesRegularExpression('/12\.500000.*, 7001, 0\.000000/', $insert['valores']);
    }

    public function test_factura_anticipada_pone_orden_menos_uno(): void
    {
        $insert = RecepcionProveedorAnitaEscrituraSupport::aplicpedFacturaAnticipadaInsert(
            '3593',
            ['tipo' => 'FIU', 'letra' => 'C', 'sucursal' => 1, 'nro' => 105],
            ['tipo' => 'PEP', 'letra' => 'X', 'sucursal' => 0, 'nro' => 222177],
            427819,
        );

        $this->assertStringContainsString('aplp_orden_com', $insert['campos']);
        $this->assertStringContainsString('aplp_orden', $insert['campos']);
        $this->assertStringContainsString('OC ANTICIPADA', $insert['valores']);
        $this->assertStringContainsString('-1', $insert['valores']);
        $this->assertStringContainsString('427819', $insert['valores']);
        $this->assertStringContainsString("'FIU'", $insert['valores']);
        $this->assertStringContainsString('222177', $insert['valores']);
    }
}
