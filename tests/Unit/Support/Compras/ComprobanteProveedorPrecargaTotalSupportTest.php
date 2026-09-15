<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
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

    public function test_precarga_sin_total_no_es_usable(): void
    {
        $precarga = new Precarga_Comprobante_Proveedor([
            'total' => 0,
            'subtotal' => 0,
        ]);

        $this->assertFalse(
            ComprobanteProveedorPrecargaTotalSupport::precargaTieneTotalUsable($precarga)
        );
    }

    public function test_precarga_con_total_es_usable(): void
    {
        $precarga = new Precarga_Comprobante_Proveedor([
            'total' => 34087.51,
            'subtotal' => 0,
        ]);

        $this->assertTrue(
            ComprobanteProveedorPrecargaTotalSupport::precargaTieneTotalUsable($precarga)
        );
    }
}
