<?php

namespace Tests\Unit\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Support\Compras\ProveedorPadronArcaSuspensionSupport;
use PHPUnit\Framework\TestCase;

class ProveedorPadronArcaSuspensionRegularizadoTest extends TestCase
{
    public function test_no_suspende_si_el_proveedor_esta_regularizado(): void
    {
        $proveedor = new Proveedor();
        $proveedor->estado = Proveedor::ESTADO_REGULARIZADO;
        $proveedor->tiposuspension_id = null;

        $resultado = ProveedorPadronArcaSuspensionSupport::aplicar($proveedor, [
            'aplica' => true,
            'debe_suspender' => true,
            'ok' => false,
            'mensaje' => 'IVA no activo',
        ]);

        $this->assertFalse($resultado['suspendido']);
        $this->assertTrue($resultado['regularizado'] ?? false);
        $this->assertSame(Proveedor::ESTADO_REGULARIZADO, $proveedor->estado);
    }
}
