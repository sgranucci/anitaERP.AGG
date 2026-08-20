<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\VentaEmisionCajaPiezaSupport;
use PHPUnit\Framework\TestCase;

final class VentaEmisionCajaPiezaSupportTest extends TestCase
{
    public function test_en_agg_quita_pieza_y_caja_del_insert(): void
    {
        $filtrado = VentaEmisionCajaPiezaSupport::filtrarPayload(
            [
                'venta_id' => 1,
                'detalle' => 'CERVEZA LATA SALTA ROJA',
                'cantidad' => 2,
                'pieza' => 0,
                'caja' => 0,
                'precio' => 5400,
            ],
            false
        );

        $this->assertArrayNotHasKey('pieza', $filtrado);
        $this->assertArrayNotHasKey('caja', $filtrado);
        $this->assertSame(2, $filtrado['cantidad']);
        $this->assertSame(5400, $filtrado['precio']);
    }

    public function test_en_el_bierzo_conserva_pieza_y_caja(): void
    {
        $filtrado = VentaEmisionCajaPiezaSupport::filtrarPayload(
            [
                'cantidad' => 10.5,
                'pieza' => 3,
                'caja' => 1,
            ],
            true
        );

        $this->assertSame(3, $filtrado['pieza']);
        $this->assertSame(1, $filtrado['caja']);
        $this->assertSame(10.5, $filtrado['cantidad']);
    }
}
