<?php

namespace Tests\Unit\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaPedidoFerliSupport;
use PHPUnit\Framework\TestCase;

class MayorPlanoCuentaPedidoFerliSupportTest extends TestCase
{
    public function test_leyenda_de_tiendanube_separa_pedido_y_nombre(): void
    {
        $resuelto = MayorPlanoCuentaPedidoFerliSupport::resolver(
            'Nerea Hernando',
            'Tiendanube pedido #16234',
            '',
        );

        $this->assertSame('16234', $resuelto['pedido']);
        $this->assertSame('Nerea Hernando', $resuelto['nombre']);
    }

    public function test_nombre_con_barra_era_el_formato_viejo(): void
    {
        $resuelto = MayorPlanoCuentaPedidoFerliSupport::resolver(
            'NATALIA MONTANARI/16230',
            '',
            '',
        );

        $this->assertSame('16230', $resuelto['pedido']);
        $this->assertSame('NATALIA MONTANARI', $resuelto['nombre']);
    }

    public function test_el_numero_de_tiendanube_manda_sobre_la_leyenda(): void
    {
        $resuelto = MayorPlanoCuentaPedidoFerliSupport::resolver(
            'Romina Mir',
            'NC por comprobante FAC B-00023-00045408 — Tiendanube pedido #16183',
            '16183',
        );

        $this->assertSame('16183', $resuelto['pedido']);
        $this->assertSame('Romina Mir', $resuelto['nombre']);
    }

    public function test_consumidor_final_no_pisa_el_nombre_del_emisor(): void
    {
        $resuelto = MayorPlanoCuentaPedidoFerliSupport::resolver(
            'CONSUMIDOR FINAL (import Anita)',
            '',
            '',
        );

        $this->assertSame('', $resuelto['pedido']);
        $this->assertSame('', $resuelto['nombre']);
    }

    public function test_insertar_columna_corre_las_letras_de_la_derecha(): void
    {
        $mapa = MayorPlanoCuentaPedidoFerliSupport::insertarColumna([
            'I' => 'cc',
            'J' => 'oc',
            'L' => 'cotiz',
        ], 'J', 'pedido');

        $this->assertSame([
            'I' => 'cc',
            'J' => 'oc',
            'K' => 'pedido',
            'M' => 'cotiz',
        ], $mapa);
    }
}
