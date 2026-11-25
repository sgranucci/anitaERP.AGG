<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Support\Compras\ProveedorOperativoComprasSupport;
use PHPUnit\Framework\TestCase;

class ProveedorOperativoComprasSupportTest extends TestCase
{
    public function test_activo_es_operativo(): void
    {
        $proveedor = new Proveedor();
        $proveedor->estado = Proveedor::ESTADO_ACTIVO;

        $this->assertTrue(ProveedorOperativoComprasSupport::esOperativo($proveedor));
        ProveedorOperativoComprasSupport::assertProveedorOperativo($proveedor);
        $this->addToAssertionCount(1);
    }

    public function test_regularizado_es_operativo(): void
    {
        $proveedor = new Proveedor();
        $proveedor->estado = Proveedor::ESTADO_REGULARIZADO;

        $this->assertTrue(ProveedorOperativoComprasSupport::esOperativo($proveedor));
    }

    public function test_suspendido_no_es_operativo(): void
    {
        $proveedor = new Proveedor();
        $proveedor->estado = Proveedor::ESTADO_SUSPENDIDO;

        $this->assertFalse(ProveedorOperativoComprasSupport::esOperativo($proveedor));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('suspendido');
        ProveedorOperativoComprasSupport::assertProveedorOperativo($proveedor);
    }

    public function test_sin_proveedor_no_bloquea(): void
    {
        $this->assertTrue(ProveedorOperativoComprasSupport::esOperativo(null));
        ProveedorOperativoComprasSupport::assertProveedorOperativo(null);
        ProveedorOperativoComprasSupport::assertIdOperativo(null);
        ProveedorOperativoComprasSupport::assertIdOperativo(0);
        $this->addToAssertionCount(1);
    }
}
