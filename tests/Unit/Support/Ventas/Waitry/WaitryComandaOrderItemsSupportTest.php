<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Models\Stock\Articulo;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\CuentaGastronomiaLinea;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use App\Support\Ventas\Waitry\WaitryComandaOrderItemsSupport;
use Tests\TestCase;

final class WaitryComandaOrderItemsSupportTest extends TestCase
{
    public function test_cortesia_solo_con_sin_cobranza_o_total_minimo(): void
    {
        $ventaNormal = new Venta(['total' => 5000., 'descuento' => 0]);
        $this->assertFalse(WaitryComandaOrderItemsSupport::esFacturaCortesiaWaitry($ventaNormal));

        $ventaDescParcial = new Venta(['total' => 4000., 'descuento' => 20.]);
        $this->assertFalse(WaitryComandaOrderItemsSupport::esFacturaCortesiaWaitry($ventaDescParcial));

        $this->assertTrue(WaitryComandaOrderItemsSupport::esFacturaCortesiaWaitry($ventaNormal, true));

        $ventaCortesia = new Venta(['total' => GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA]);
        $this->assertTrue(WaitryComandaOrderItemsSupport::esFacturaCortesiaWaitry($ventaCortesia));
    }

    public function test_construir_cortesia_precio_cero_y_ultimo_cero_uno(): void
    {
        $venta = new Venta(['id' => 1, 'total' => 0.01]);
        $venta->setRelation('venta_emisiones', collect([
            $this->emision(1, 'SKU-A', 1, 1500.),
            $this->emision(2, 'SKU-B', 2, 800.),
            $this->emision(3, 'SKU-C', 1, 500.),
        ]));

        $items = WaitryComandaOrderItemsSupport::construirDesdeVenta($venta, true);

        $this->assertCount(3, $items);
        $this->assertSame(0., (float) $items[0]['price']);
        $this->assertSame(0., (float) $items[1]['price']);
        $this->assertSame(0.01, (float) $items[2]['price']);
        $this->assertArrayNotHasKey('_impuesto_id', $items[0]);

        $total = array_sum(array_map(
            static fn (array $item): float => (float) $item['price'] * (int) $item['count'],
            $items,
        ));
        $this->assertEqualsWithDelta(0.01, $total, 0.0001);
    }

    public function test_construir_descuento_parcial_escala_al_total_cobrado(): void
    {
        $venta = new Venta(['id' => 2, 'total' => 8000., 'descuento' => 20.]);
        $venta->setRelation('venta_emisiones', collect([
            $this->emision(1, 'SKU-A', 1, 5000.),
            $this->emision(2, 'SKU-B', 1, 5000.),
        ]));

        $items = WaitryComandaOrderItemsSupport::construirDesdeVenta($venta, false);

        $this->assertSame(4000., (float) $items[0]['price']);
        $this->assertSame(4000., (float) $items[1]['price']);

        $total = array_sum(array_map(
            static fn (array $item): float => (float) $item['subtotal'],
            $items,
        ));
        $this->assertEqualsWithDelta(8000., $total, 0.01);
    }

    public function test_construir_sin_descuento_pie_no_escala_si_coincide_total(): void
    {
        $venta = new Venta(['id' => 3, 'total' => 1800.5]);
        $venta->setRelation('venta_emisiones', collect([
            $this->emision(1, 'SKU-A', 1, 1200.50),
            $this->emision(2, 'SKU-B', 2, 300.),
        ]));

        $items = WaitryComandaOrderItemsSupport::construirDesdeVenta($venta, false);

        $this->assertSame(1200.5, (float) $items[0]['price']);
        $this->assertSame(300., (float) $items[1]['price']);
    }

    public function test_construir_incluye_notes_de_comentario_cocina(): void
    {
        $venta = new Venta(['id' => 4, 'total' => 100.]);
        $emision = $this->emision(1, 'SKU-A', 1, 100.);
        $emision->forceFill(['comentario_cocina' => 'Sin cebolla']);
        $venta->setRelation('venta_emisiones', collect([$emision]));

        $items = WaitryComandaOrderItemsSupport::construirDesdeVenta($venta, false);

        $this->assertSame('Sin cebolla', $items[0]['notes']);
    }

    public function test_construir_notes_null_sin_comentario(): void
    {
        $venta = new Venta(['id' => 5, 'total' => 50.]);
        $venta->setRelation('venta_emisiones', collect([
            $this->emision(1, 'SKU-A', 1, 50.),
        ]));

        $items = WaitryComandaOrderItemsSupport::construirDesdeVenta($venta, false);

        $this->assertNull($items[0]['notes']);
    }

    public function test_construir_incluye_order_item_variations_desde_opcionales_cuenta(): void
    {
        $articuloPadre = new Articulo;
        $articuloPadre->forceFill(['id' => 10, 'sku' => 'V0100', 'descripcion' => 'Hamburguesa']);

        $articuloOpcional = new Articulo;
        $articuloOpcional->forceFill(['id' => 20, 'sku' => 'V0200', 'descripcion' => 'Extra queso']);

        $emisionPadre = $this->emision(1, 'V0100', 1, 5000.);
        $emisionPadre->forceFill(['id' => 100, 'articulo_id' => 10]);
        $emisionPadre->setRelation('articulos', $articuloPadre);

        $emisionOpcional = $this->emision(2, 'V0200', 1, 0.);
        $emisionOpcional->forceFill(['id' => 101, 'articulo_id' => 20]);
        $emisionOpcional->setRelation('articulos', $articuloOpcional);

        $venta = new Venta(['id' => 10, 'total' => 5000.]);
        $venta->setRelation('venta_emisiones', collect([$emisionPadre, $emisionOpcional]));

        $linea = new CuentaGastronomiaLinea;
        $linea->forceFill([
            'id' => 1,
            'articulo_id' => 10,
            'opcionales_json' => ['1' => 20],
        ]);
        $linea->setRelation('articulo', $articuloPadre);

        $cuenta = new CuentaGastronomia(['id' => 5]);
        $cuenta->setRelation('lineas', collect([$linea]));

        $items = WaitryComandaOrderItemsSupport::construirDesdeVenta($venta, false, $cuenta);

        $this->assertCount(1, $items);
        $this->assertSame('V0100', $items[0]['item']['externalId']);
        $this->assertCount(1, $items[0]['orderItemVariations']);
        $variacion = $items[0]['orderItemVariations'][0];
        $this->assertSame('V0200', $variacion['externalId']);
        $this->assertSame('Extra queso', $variacion['name']);
        $this->assertSame('V0200', $variacion['itemVariation']['item']['externalId']);
        $this->assertSame('Extra queso', $variacion['itemVariation']['item']['name']);
    }

    public function test_construir_variacion_usa_nombre_cuando_sku_vacio(): void
    {
        $articuloPadre = new Articulo;
        $articuloPadre->forceFill(['id' => 10, 'sku' => 'V0100', 'descripcion' => 'Pizza']);

        $articuloOpcional = new Articulo;
        $articuloOpcional->forceFill(['id' => 30, 'sku' => '', 'descripcion' => 'Sin cebolla']);

        $emisionPadre = $this->emision(1, 'V0100', 1, 3000.);
        $emisionPadre->forceFill(['id' => 200, 'articulo_id' => 10]);
        $emisionPadre->setRelation('articulos', $articuloPadre);

        $emisionOpcional = $this->emision(2, '', 1, 0.);
        $emisionOpcional->forceFill(['id' => 201, 'articulo_id' => 30]);
        $emisionOpcional->setRelation('articulos', $articuloOpcional);

        $venta = new Venta(['id' => 11, 'total' => 3000.]);
        $venta->setRelation('venta_emisiones', collect([$emisionPadre, $emisionOpcional]));

        $linea = new CuentaGastronomiaLinea;
        $linea->forceFill([
            'id' => 2,
            'articulo_id' => 10,
            'opcionales_json' => ['1' => 30],
        ]);

        $cuenta = new CuentaGastronomia(['id' => 6]);
        $cuenta->setRelation('lineas', collect([$linea]));

        $items = WaitryComandaOrderItemsSupport::construirDesdeVenta($venta, false, $cuenta);

        $variacion = $items[0]['orderItemVariations'][0];
        $this->assertSame('ART-30', $variacion['externalId']);
        $this->assertSame('Sin cebolla', $variacion['name']);
    }

    private function emision(int $numeroitem, string $sku, float $cantidad, float $precio): Venta_Emision
    {
        $articulo = new Articulo;
        $articulo->forceFill(['sku' => $sku, 'descripcion' => 'Artículo '.$sku]);
        $emision = new Venta_Emision;
        $emision->forceFill([
            'numeroitem' => $numeroitem,
            'articulo_id' => $numeroitem,
            'cantidad' => $cantidad,
            'precio' => $precio,
            'impuesto_id' => 1,
            'incluyeimpuesto' => 'N',
        ]);
        $emision->setRelation('articulos', $articulo);

        return $emision;
    }
}
