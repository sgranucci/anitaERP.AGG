<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\TransferenciaMercaderiaLineaReversoSupport;
use PHPUnit\Framework\TestCase;

class TransferenciaMercaderiaLineaReversoSupportTest extends TestCase
{
    public function test_con_conversion_revierte_cada_movimiento_con_su_cantidad(): void
    {
        $salida = [[
            'articulo_id' => 100,
            'deposito_id' => 1,
            'cantidad' => -1.0,
            'precio' => 10.0,
        ]];
        $entrada = [[
            'articulo_id' => 200,
            'deposito_id' => 8,
            'cantidad' => 0.34,
            'precio' => 29.411765,
        ]];

        $doc = TransferenciaMercaderiaLineaReversoSupport::lineasDocumento($salida, $entrada);
        $this->assertCount(1, $doc);
        $this->assertSame(200, $doc[0]['articulo_origen_id']);
        $this->assertSame(100, $doc[0]['articulo_destino_id']);
        $this->assertEqualsWithDelta(0.34, $doc[0]['cantidad_origen'], 0.000001);
        $this->assertEqualsWithDelta(1.0, $doc[0]['cantidad_destino'], 0.000001);
        $this->assertTrue($doc[0]['fl_conversion_formula']);

        $devolver = TransferenciaMercaderiaLineaReversoSupport::payloadLineas($salida);
        $this->assertSame([100], $devolver['articulos_id']);
        $this->assertEqualsWithDelta(1.0, $devolver['cantidades'][0], 0.000001);

        $quitar = TransferenciaMercaderiaLineaReversoSupport::payloadLineas($entrada);
        $this->assertSame([200], $quitar['articulos_id']);
        $this->assertEqualsWithDelta(0.34, $quitar['cantidades'][0], 0.000001);
    }

    public function test_sin_conversion_mantiene_articulo_y_cantidad(): void
    {
        $salida = [[
            'articulo_id' => 50,
            'deposito_id' => 1,
            'cantidad' => -3.0,
            'precio' => 5.0,
        ]];
        $entrada = [[
            'articulo_id' => 50,
            'deposito_id' => 8,
            'cantidad' => 3.0,
            'precio' => 5.0,
        ]];

        $doc = TransferenciaMercaderiaLineaReversoSupport::lineasDocumento($salida, $entrada);
        $this->assertSame(50, $doc[0]['articulo_origen_id']);
        $this->assertSame(50, $doc[0]['articulo_destino_id']);
        $this->assertEqualsWithDelta(3.0, $doc[0]['cantidad_origen'], 0.000001);
        $this->assertEqualsWithDelta(3.0, $doc[0]['cantidad_destino'], 0.000001);
        $this->assertFalse($doc[0]['fl_conversion_formula']);
    }
}
