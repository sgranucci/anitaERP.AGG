<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorPrecargaTotalSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorPrecargaTotalSupportTest extends TestCase
{
    public function test_sin_precarga_no_bloquea(): void
    {
        ComprobanteProveedorPrecargaTotalSupport::assertCuadraConPrecarga(null, 100.0);
        ComprobanteProveedorPrecargaTotalSupport::assertCuadraConPrecarga(0, 100.0);
        $this->addToAssertionCount(1);
    }

    public function test_tolerancia_es_la_misma_que_cuotas(): void
    {
        $this->assertSame(0.05, ComprobanteProveedorPrecargaTotalSupport::TOLERANCIA);
    }
}
