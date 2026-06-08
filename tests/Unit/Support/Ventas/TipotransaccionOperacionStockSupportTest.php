<?php

namespace Tests\Unit\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use App\Support\Ventas\TipotransaccionOperacionStockSupport;
use PHPUnit\Framework\TestCase;

class TipotransaccionOperacionStockSupportTest extends TestCase
{
    public function test_afecta_stock_solo_salida_y_entrada(): void
    {
        $this->assertTrue(TipotransaccionOperacionStockSupport::afectaStock('S'));
        $this->assertTrue(TipotransaccionOperacionStockSupport::afectaStock('E'));
        $this->assertFalse(TipotransaccionOperacionStockSupport::afectaStock('N'));
        $this->assertFalse(TipotransaccionOperacionStockSupport::afectaStock('O'));
    }

    public function test_cantidad_firmada_salida_negativa_entrada_positiva(): void
    {
        $this->assertSame(-3.0, TipotransaccionOperacionStockSupport::cantidadFirmada(3, 'S'));
        $this->assertSame(-3.0, TipotransaccionOperacionStockSupport::cantidadFirmada(-3, 'S'));
        $this->assertSame(2.5, TipotransaccionOperacionStockSupport::cantidadFirmada(2.5, 'E'));
        $this->assertSame(2.5, TipotransaccionOperacionStockSupport::cantidadFirmada(-2.5, 'E'));
    }

    public function test_firmar_payload_desde_tipotransaccion_marca_cantidad_ya_firmada(): void
    {
        $tipo = new Tipotransaccion(['operacionstock' => 'S']);

        $resultado = TipotransaccionOperacionStockSupport::firmarPayloadDesdeTipotransaccion(
            ['cantidad' => 4],
            $tipo,
        );

        $this->assertSame(-4.0, $resultado['cantidad']);
        $this->assertTrue($resultado['cantidad_ya_firmada']);
    }

    public function test_firmar_payload_desde_tipotransaccion_sin_operacion_retorna_null(): void
    {
        $tipo = new Tipotransaccion(['operacionstock' => 'O']);

        $this->assertNull(
            TipotransaccionOperacionStockSupport::firmarPayloadDesdeTipotransaccion(['cantidad' => 1], $tipo)
        );
    }
}
