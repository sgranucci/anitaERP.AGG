<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\VentaFacturasPorArticuloClienteSupport;
use Tests\TestCase;

final class VentaFacturasPorArticuloClienteSupportTest extends TestCase
{
    public function test_clave_linea_concatena_articulo_combinacion_talle(): void
    {
        self::assertSame('10-3-42', VentaFacturasPorArticuloClienteSupport::claveLinea(10, 3, 42));
        self::assertSame('10-0-0', VentaFacturasPorArticuloClienteSupport::claveLinea(10, 0, 0));
    }

    public function test_pendiente_no_baja_de_cero(): void
    {
        self::assertSame(2.0, VentaFacturasPorArticuloClienteSupport::pendiente(5, 3));
        self::assertSame(0.0, VentaFacturasPorArticuloClienteSupport::pendiente(3, 3));
        self::assertSame(0.0, VentaFacturasPorArticuloClienteSupport::pendiente(2, 5));
    }

    public function test_prorratea_nc_previa_fifo_entre_lineas_misma_clave(): void
    {
        $lineas = [
            ['clave' => '7-1-38', 'cantidad' => 2.0],
            ['clave' => '7-1-38', 'cantidad' => 4.0],
            ['clave' => '7-1-39', 'cantidad' => 6.0],
        ];
        $acred = [
            '7-1-38' => 3.0,
            '7-1-39' => 0.0,
        ];

        $out = VentaFacturasPorArticuloClienteSupport::prorratearAcreditado($lineas, $acred);

        self::assertSame(2.0, $out[0]['acreditada']);
        self::assertSame(0.0, $out[0]['pendiente']);
        self::assertSame(1.0, $out[1]['acreditada']);
        self::assertSame(3.0, $out[1]['pendiente']);
        self::assertSame(0.0, $out[2]['acreditada']);
        self::assertSame(6.0, $out[2]['pendiente']);
    }
}
