<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorPrecioPendienteSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorPrecioPendienteSupportTest extends TestCase
{
    public function test_sin_permiso_no_infiere_precio_solicitado_desde_precio_enviado(): void
    {
        $items = RecepcionProveedorPrecioPendienteSupport::normalizarItemsSegunPermiso([
            [
                'precio_ordencompra' => 100.0,
                'precio' => 115.5,
                'comentario_precio' => '',
            ],
        ], false);

        $this->assertSame(100.0, $items[0]['precio']);
        $this->assertNull($items[0]['precio_solicitado']);
        $this->assertFalse($items[0]['fl_precio_diferencia']);
    }

    public function test_sin_permiso_conserva_solicitud_explicita_con_comentario(): void
    {
        $items = RecepcionProveedorPrecioPendienteSupport::normalizarItemsSegunPermiso([
            [
                'precio_ordencompra' => 100.0,
                'precio' => 100.0,
                'precio_solicitado' => 115.5,
                'comentario_precio' => 'Precio de remito',
            ],
        ], false);

        $this->assertSame(115.5, $items[0]['precio']);
        $this->assertSame(115.5, $items[0]['precio_solicitado']);
        $this->assertTrue($items[0]['fl_precio_diferencia']);
    }

    public function test_sin_permiso_infiere_solicitud_desde_precio_con_comentario(): void
    {
        $items = RecepcionProveedorPrecioPendienteSupport::normalizarItemsSegunPermiso([
            [
                'precio_ordencompra' => 100.0,
                'precio' => 115.5,
                'comentario_precio' => 'OC anual',
            ],
        ], false);

        $this->assertSame(115.5, $items[0]['precio']);
        $this->assertSame(115.5, $items[0]['precio_solicitado']);
        $this->assertTrue($items[0]['fl_precio_diferencia']);
    }

    public function test_ocr_sin_permiso_restaura_precios_de_oc(): void
    {
        $lineas = RecepcionProveedorPrecioPendienteSupport::aplicarPreciosOcrSegunPermiso([
            [
                'precio_ordencompra' => 5400.0,
                'precio' => 6100.0,
                'cantidad' => 1,
            ],
        ], false);

        $this->assertSame(5400.0, $lineas[0]['precio']);
        $this->assertArrayNotHasKey('precio_solicitado', $lineas[0]);
        $this->assertFalse($lineas[0]['fl_precio_diferencia']);
    }

    public function test_ocr_con_permiso_conserva_precio_del_remito(): void
    {
        $lineas = RecepcionProveedorPrecioPendienteSupport::aplicarPreciosOcrSegunPermiso([
            [
                'precio_ordencompra' => 5400.0,
                'precio' => 6100.0,
                'cantidad' => 1,
            ],
        ], true);

        $this->assertSame(6100.0, $lineas[0]['precio']);
    }
}
