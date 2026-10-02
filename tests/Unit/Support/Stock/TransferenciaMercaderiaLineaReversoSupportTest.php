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

    public function test_payload_conserva_combinacion_modulo_y_talles(): void
    {
        $lineas = [[
            'articulo_id' => 12909,
            'cantidad' => -12.0,
            'combinacion_id' => 18245,
            'modulo_id' => 30,
            'medidas' => [
                ['medida' => '29', 'cantidad' => 2, 'precio' => 0, 'talle_id' => 14],
                ['medida' => '34', 'cantidad' => 2, 'precio' => 0, 'talle_id' => 19],
            ],
        ]];

        $payload = TransferenciaMercaderiaLineaReversoSupport::payloadLineas($lineas);

        $this->assertSame([18245], $payload['combinaciones_id']);
        $this->assertSame([30], $payload['modulos_id']);
        $this->assertEqualsWithDelta(12.0, $payload['cantidades'][0], 0.000001);

        $decoded = json_decode($payload['medidas'][0], true);
        $this->assertCount(2, $decoded);
        $this->assertSame(14, $decoded[0]['talle_id']);
        $this->assertEqualsWithDelta(2.0, $decoded[0]['cantidad'], 0.000001);
        $this->assertSame(19, $decoded[1]['talle_id']);
    }

    public function test_medidas_desde_talles_usa_valor_absoluto(): void
    {
        $talle = new \stdClass();
        $talle->cantidad = -2;
        $talle->precio = 10;
        $talle->talle_id = 14;
        $talle->talles = (object) ['nombre' => '29'];

        $linea = new \stdClass();
        $linea->articulo_movimiento_talles = [$talle];

        $medidas = TransferenciaMercaderiaLineaReversoSupport::medidasDesdeTalles($linea);

        $this->assertSame('29', $medidas[0]['medida']);
        $this->assertSame(14, $medidas[0]['talle_id']);
        $this->assertEqualsWithDelta(2.0, $medidas[0]['cantidad'], 0.000001);
    }
}
