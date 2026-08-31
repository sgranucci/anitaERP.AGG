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
        $this->assertSame(
            'FSL B-00039-00007231',
            VentaNumeracionEmpresaSupport::formatearCodigoVenta('FSL', 'B', '39', 7231),
        );
    }

    public function test_siguiente_numero_salta_colision_sin_puntoventa(): void
    {
        $this->assertSame(
            9,
            VentaNumeracionEmpresaSupport::siguienteNumerocomprobanteParaUnique(0, 6, 'B', null, 8),
        );
    }
}
