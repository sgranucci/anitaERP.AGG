<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\RecepcionProveedorAccionLineaOc;
use App\Support\Stock\RecepcionProveedorDevolucionDisponibleSupport;
use PHPUnit\Framework\TestCase;

class RecepcionProveedorDevolucionDisponibleSupportTest extends TestCase
{
    public function test_disponible_resta_devoluciones_previas_como_caso_claspack(): void
    {
        // COM 169767: +1800 DES0204 / +1800 DES0306 / +1 flete
        $recibido = RecepcionProveedorDevolucionDisponibleSupport::sumarPorClaveLinea([
            ['articulo_id' => 18604, 'ordencompra_articulo_id' => 64852, 'cantidad' => 1800],
            ['articulo_id' => 19190, 'ordencompra_articulo_id' => 64853, 'cantidad' => 1800],
            ['articulo_id' => 2414, 'ordencompra_articulo_id' => 64894, 'cantidad' => 1],
        ]);

        // DEP 169898 ya confirmada (misma COM origen)
        $yaDevuelto = RecepcionProveedorDevolucionDisponibleSupport::sumarPorClaveLinea([
            ['articulo_id' => 18604, 'ordencompra_articulo_id' => 64852, 'cantidad' => 1800],
            ['articulo_id' => 19190, 'ordencompra_articulo_id' => 64853, 'cantidad' => 1800],
            ['articulo_id' => 2414, 'ordencompra_articulo_id' => 64894, 'cantidad' => 1],
        ]);

        $disponible = RecepcionProveedorDevolucionDisponibleSupport::disponiblesPorClave($recibido, $yaDevuelto);

        $this->assertSame(0.0, $disponible['18604-64852']);
        $this->assertSame(0.0, $disponible['19190-64853']);
        $this->assertSame(0.0, $disponible['2414-64894']);
    }

    public function test_segunda_dep_completa_debe_rechazarse(): void
    {
        $disponible = [
            '18604-64852' => 0.0,
            '19190-64853' => 0.0,
            '2414-64894' => 0.0,
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('supera lo disponible');

        RecepcionProveedorDevolucionDisponibleSupport::assertCantidadesDentroDisponible($disponible, [
            ['articulo_id' => 18604, 'ordencompra_articulo_id' => 64852, 'cantidad' => 1800],
            ['articulo_id' => 19190, 'ordencompra_articulo_id' => 64853, 'cantidad' => 1800],
            ['articulo_id' => 2414, 'ordencompra_articulo_id' => 64894, 'cantidad' => 1],
        ]);
    }

    public function test_devolucion_parcial_deja_saldo_disponible(): void
    {
        $recibido = ['10-100' => 100.0];
        $yaDevuelto = ['10-100' => 40.0];
        $disponible = RecepcionProveedorDevolucionDisponibleSupport::disponiblesPorClave($recibido, $yaDevuelto);

        $this->assertSame(60.0, $disponible['10-100']);

        RecepcionProveedorDevolucionDisponibleSupport::assertCantidadesDentroDisponible($disponible, [
            ['articulo_id' => 10, 'ordencompra_articulo_id' => 100, 'cantidad' => 60],
        ]);

        $this->expectException(\RuntimeException::class);
        RecepcionProveedorDevolucionDisponibleSupport::assertCantidadesDentroDisponible($disponible, [
            ['articulo_id' => 10, 'ordencompra_articulo_id' => 100, 'cantidad' => 60.1],
        ]);
    }

    public function test_aplicar_disponible_prellena_formulario_con_neto(): void
    {
        $items = RecepcionProveedorDevolucionDisponibleSupport::aplicarDisponibleAItems(
            [
                ['articulo_id' => 10, 'ordencompra_articulo_id' => 100, 'cantidad' => 100, 'descripcion' => 'A'],
                ['articulo_id' => 11, 'ordencompra_articulo_id' => 101, 'cantidad' => 50, 'descripcion' => 'B'],
            ],
            [
                '10-100' => 0.0,
                '11-101' => 25.0,
            ]
        );

        $this->assertSame(0.0, (float) $items[0]['cantidad']);
        $this->assertSame(0.0, (float) $items[0]['cantidad_recepcionada_origen']);
        $this->assertSame(RecepcionProveedorAccionLineaOc::PENDIENTE, $items[0]['accion_linea_oc']);

        $this->assertSame(25.0, (float) $items[1]['cantidad']);
        $this->assertSame(25.0, (float) $items[1]['cantidad_recepcionada_origen']);
        $this->assertSame(RecepcionProveedorAccionLineaOc::RECIBIR, $items[1]['accion_linea_oc']);
    }
}
