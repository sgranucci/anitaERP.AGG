<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecuentoItemsRequestSupport;
use Tests\TestCase;

class RecuentoItemsRequestSupportTest extends TestCase
{
    public function test_hidrata_el_ultimo_item_con_cantidad(): void
    {
        $items = [];
        for ($i = 1; $i <= 124; $i++) {
            $items[] = [
                'articulo_id' => $i,
                'recuento_item_id' => $i + 4000,
                'color_id' => 0,
                'talle_id' => 0,
                'detalle' => 'Item '.$i,
                'cantidad_contada' => $i,
                'saldo_sistema' => 0,
                'unidadmedida_id' => 1,
            ];
        }
        $items[] = [
            'articulo_id' => 12831,
            'recuento_item_id' => '',
            'color_id' => 0,
            'talle_id' => 0,
            'detalle' => 'CABEZAL MEI ADVAN SCN6607RS232',
            'cantidad_contada' => 7,
            'saldo_sistema' => 0,
            'unidadmedida_id' => 1,
        ];

        $arrays = RecuentoItemsRequestSupport::arraysDesdeItemsJson(json_encode($items));

        $this->assertNotNull($arrays);
        $this->assertCount(125, $arrays['articulo_ids']);
        $this->assertSame(12831, $arrays['articulo_ids'][124]);
        $this->assertNull($arrays['recuento_item_ids'][124]);
        $this->assertSame(7, $arrays['cantidades_contadas'][124]);
        $this->assertSame('CABEZAL MEI ADVAN SCN6607RS232', $arrays['detalle_articulos'][124]);
    }

    public function test_detecta_post_truncado_sin_cantidad_del_ultimo(): void
    {
        $this->assertTrue(RecuentoItemsRequestSupport::postTruncado([
            'articulo_ids' => [1, 2, 12831],
            'cantidades_contadas' => [2, 3],
            'detalle_articulos' => ['A', 'B'],
        ]));

        $this->assertFalse(RecuentoItemsRequestSupport::postTruncado([
            'articulo_ids' => [1, 2, 12831],
            'cantidades_contadas' => [2, 3, 7],
            'detalle_articulos' => ['A', 'B', 'C'],
        ]));
    }

    public function test_json_invalido_devuelve_null(): void
    {
        $this->assertNull(RecuentoItemsRequestSupport::arraysDesdeItemsJson('{no-json'));
        $this->assertNull(RecuentoItemsRequestSupport::arraysDesdeItemsJson(null));
    }

    public function test_reconstruye_todas_las_lineas_desde_items_json(): void
    {
        $items = [];
        for ($i = 1; $i <= 35; $i++) {
            $items[] = [
                'articulo_id' => $i,
                'sku' => 'SKU-'.$i,
                'detalle' => 'Artículo '.$i,
                'cantidad_contada' => $i === 35 ? -2 : $i,
                'saldo_sistema' => 1,
                'unidadmedida_id' => 1,
                'unidadmedida' => 'UN',
                'color_id' => 0,
                'talle_id' => 0,
            ];
        }

        $lineas = RecuentoItemsRequestSupport::lineasDesdeOldInput(json_encode($items), []);

        $this->assertNotNull($lineas);
        $this->assertCount(35, $lineas);
        $this->assertSame(35, $lineas[34]['articulo_id']);
        $this->assertSame('SKU-35', $lineas[34]['sku']);
        $this->assertSame(-2, $lineas[34]['cantidad_contada']);
    }

    public function test_reconstruye_lineas_desde_arrays_old_si_no_hay_json(): void
    {
        $lineas = RecuentoItemsRequestSupport::lineasDesdeOldInput('', [
            'articulo_ids' => [10, 20, 0],
            'detalle_articulos' => ['A', 'B', 'vacío'],
            'cantidades_contadas' => [1, -3, 0],
            'codigoarticulos' => ['AA', 'BB', ''],
            'saldos_sistema' => [5, 2, 0],
        ]);

        $this->assertNotNull($lineas);
        $this->assertCount(2, $lineas);
        $this->assertSame(20, $lineas[1]['articulo_id']);
        $this->assertSame('BB', $lineas[1]['sku']);
        $this->assertSame(-3, $lineas[1]['cantidad_contada']);
    }

    public function test_normaliza_cantidad_vacia_y_con_coma(): void
    {
        $this->assertSame(0, RecuentoItemsRequestSupport::normalizarCantidadContada(''));
        $this->assertSame(0, RecuentoItemsRequestSupport::normalizarCantidadContada(null));
        $this->assertSame(1.5, RecuentoItemsRequestSupport::normalizarCantidadContada('1,5'));
        $this->assertSame(-2.0, RecuentoItemsRequestSupport::normalizarCantidadContada('-2'));
    }
}
