<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\CotGuiaFacturaIdentidadSupport;
use PHPUnit\Framework\TestCase;

final class CotGuiaFacturaIdentidadSupportTest extends TestCase
{
    public function testNormalizarCompletaTipoYLetra(): void
    {
        $id = CotGuiaFacturaIdentidadSupport::normalizar('fac', 'a', 12, 83070);

        $this->assertSame('FAC', $id['tipo']);
        $this->assertSame('A', $id['letra']);
        $this->assertSame(12, $id['sucursal']);
        $this->assertSame(83070, $id['numero']);
    }

    public function testPareceRemitoDetectaLetraRDePvRemito(): void
    {
        $this->assertTrue(CotGuiaFacturaIdentidadSupport::pareceRemito('FAC', 'R'));
        $this->assertTrue(CotGuiaFacturaIdentidadSupport::pareceRemito('REM', 'R'));
        $this->assertFalse(CotGuiaFacturaIdentidadSupport::pareceRemito('FAC', 'A'));
    }
}
