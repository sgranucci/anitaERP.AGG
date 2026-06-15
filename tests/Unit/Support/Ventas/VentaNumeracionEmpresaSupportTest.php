<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use Tests\TestCase;

final class VentaNumeracionEmpresaSupportTest extends TestCase
{
    public function test_numero_desde_codigo_venta(): void
    {
        $this->assertSame(
            14037,
            VentaNumeracionEmpresaSupport::numeroDesdeCodigoVenta('FAC B-00031-00014037'),
        );
        $this->assertSame(
            220265,
            VentaNumeracionEmpresaSupport::numeroDesdeCodigoVenta('FAC B-00031-00220265'),
        );
    }

    public function test_formatear_codigo_venta(): void
    {
        $codigo = VentaNumeracionEmpresaSupport::formatearCodigoVenta('FAC', 'B', '00031', 14048);
        $this->assertSame('FAC B-00031-00014048', $codigo);
    }
}
