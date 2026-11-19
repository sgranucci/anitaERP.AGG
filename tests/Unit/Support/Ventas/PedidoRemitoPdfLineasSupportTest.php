<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\PedidoRemitoPdfLineasSupport;
use Tests\TestCase;

class PedidoRemitoPdfLineasSupportTest extends TestCase
{
    public function test_mismo_articulo_precio_cero_suma_bonificacion_en_linea_paga(): void
    {
        $paga = (object) [
            'precio' => 100.0,
            'kilo' => 25.0,
            'pieza' => 10.0,
            'caja' => 1.0,
            'articulo_id' => 15,
            'descuento' => 0,
            'descuentoventa_ids' => null,
            'articulos' => (object) ['sku' => '118', 'descripcion' => 'Salame'],
            'unidadesdemedidas' => null,
        ];
        $regalo = (object) [
            'precio' => 0.0,
            'kilo' => 2.5,
            'pieza' => 1.0,
            'caja' => 0.0,
            'articulo_id' => 15,
            'descuento' => 0,
            'descuentoventa_ids' => null,
            'articulos' => (object) ['sku' => '118', 'descripcion' => 'Salame'],
            'unidadesdemedidas' => null,
        ];

        $r = PedidoRemitoPdfLineasSupport::armar([$paga, $regalo]);

        $this->assertCount(1, $r['filas']);
        $this->assertSame(25.0, $r['filas'][0]['pesada']);
        $this->assertSame(2.5, $r['filas'][0]['bonificacion']);
        $this->assertSame(2.5, $r['totales']['bonificacion']);
    }

    public function test_otro_articulo_precio_cero_imprime_en_columna_bonificacion(): void
    {
        $paga = (object) [
            'precio' => 100.0,
            'kilo' => 20.0,
            'pieza' => 10.0,
            'caja' => 1.0,
            'articulo_id' => 15,
            'descuento' => 0,
            'descuentoventa_ids' => null,
            'articulos' => (object) ['sku' => '118', 'descripcion' => 'Salame'],
            'unidadesdemedidas' => null,
        ];
        $regalo = (object) [
            'precio' => 0.0,
            'kilo' => 3.0,
            'pieza' => 2.0,
            'caja' => 0.0,
            'articulo_id' => 99,
            'descuento' => 0,
            'descuentoventa_ids' => null,
            'articulos' => (object) ['sku' => '999', 'descripcion' => 'Regalo'],
            'unidadesdemedidas' => null,
        ];

        $r = PedidoRemitoPdfLineasSupport::armar([$paga, $regalo]);

        $this->assertCount(2, $r['filas']);
        $this->assertSame('999', $r['filas'][1]['sku']);
        $this->assertSame(0.0, $r['filas'][1]['pesada']);
        $this->assertSame(3.0, $r['filas'][1]['bonificacion']);
        $this->assertSame(0.0, $r['filas'][1]['precio']);
        $this->assertSame(20.0, $r['totales']['pesada']);
        $this->assertSame(3.0, $r['totales']['bonificacion']);
    }

    public function test_descuento_porcentaje_parte_pesada_y_bonificacion(): void
    {
        $item = (object) [
            'precio' => 150.0,
            'kilo' => 25.0,
            'pieza' => 11.0,
            'caja' => 1.0,
            'articulo_id' => 15,
            'descuento' => 9.09,
            'descuentoventa_ids' => (object) ['nombre' => '10 x 1', 'porcentajedescuento' => 9.09],
            'articulos' => (object) ['sku' => '118', 'descripcion' => 'Salame'],
            'unidadesdemedidas' => null,
        ];

        $r = PedidoRemitoPdfLineasSupport::armar([$item]);

        $this->assertSame(22.7, $r['filas'][0]['pesada']);
        $this->assertSame(2.3, $r['filas'][0]['bonificacion']);
    }
}
