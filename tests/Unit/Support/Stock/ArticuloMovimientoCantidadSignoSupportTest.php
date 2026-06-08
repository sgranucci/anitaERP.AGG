<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ArticuloMovimientoCantidadSignoSupport;
use App\Support\Ventas\TipotransaccionOperacionStockSupport;
use PHPUnit\Framework\TestCase;

class ArticuloMovimientoCantidadSignoSupportTest extends TestCase
{
    public function test_cantidad_venta_salida_negativa_entrada_positiva(): void
    {
        $this->assertSame(-5.0, ArticuloMovimientoCantidadSignoSupport::cantidadCorregida(
            5,
            null,
            null,
            1,
            TipotransaccionOperacionStockSupport::SALIDA,
        ));
        $this->assertSame(3.0, ArticuloMovimientoCantidadSignoSupport::cantidadCorregida(
            -3,
            null,
            null,
            2,
            TipotransaccionOperacionStockSupport::ENTRADA,
        ));
    }

    public function test_cantidad_stock_por_signo_db(): void
    {
        $this->assertSame(2.0, ArticuloMovimientoCantidadSignoSupport::cantidadCorregida(
            -2,
            10,
            1,
            null,
            null,
        ));
        $this->assertSame(-4.0, ArticuloMovimientoCantidadSignoSupport::cantidadCorregida(
            4,
            10,
            -1,
            null,
            null,
        ));
    }

    public function test_prioriza_tipo_stock_sobre_venta(): void
    {
        $this->assertSame(-1.0, ArticuloMovimientoCantidadSignoSupport::cantidadCorregida(
            1,
            5,
            -1,
            99,
            TipotransaccionOperacionStockSupport::ENTRADA,
        ));
    }

    public function test_sin_operacion_stock_retorna_null(): void
    {
        $this->assertNull(ArticuloMovimientoCantidadSignoSupport::cantidadCorregida(
            1,
            null,
            null,
            1,
            TipotransaccionOperacionStockSupport::SIN_OPERACION,
        ));
    }
}
