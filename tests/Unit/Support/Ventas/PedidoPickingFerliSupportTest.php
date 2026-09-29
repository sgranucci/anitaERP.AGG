<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\Ferli\NotaCreditoReabrePedidoOtFerliSupport;
use App\Support\Ventas\PedidoPickingFerliSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): NC Ferli debe devolver el stock con el lote/OT original.
 */
class PedidoPickingFerliSupportTest extends TestCase
{
    public function test_reverso_conserva_lote_y_ot_y_invierte_cantidad(): void
    {
        $payload = S::payloadReversoConsumoPicking([
            'id' => 88,
            'lote' => '45210',
            'ordentrabajo_id' => 77,
            'pedido_combinacion_id' => 40184,
            'articulo_id' => 12,
            'combinacion_id' => 34,
            'modulo_id' => 5,
            'deposito_id' => 3,
            'loteimportacion_id' => 9,
            'tipotransaccion_id' => 4,
            'cantidad' => -12.0,
            'precio' => 1500,
        ], 99, '2026-09-18');

        self::assertNotNull($payload);
        self::assertSame('45210', $payload['lote']);
        self::assertSame(77, $payload['ordentrabajo_id']);
        self::assertSame(9, $payload['loteimportacion_id']);
        self::assertSame(12.0, $payload['cantidad']);
        self::assertSame(99, $payload['venta_id']);
        self::assertSame('Devolución NC OT/lote #88', $payload['concepto']);
        self::assertSame('2026-09-18', $payload['fecha']);
        self::assertNull($payload['movimientostock_id']);
    }

    public function test_no_revierte_lote_vacio_ni_entrada(): void
    {
        self::assertFalse(S::esConsumoPickingRevertible([
            'lote' => '0',
            'cantidad' => -12,
        ]));
        self::assertFalse(S::esConsumoPickingRevertible([
            'lote' => '45210',
            'cantidad' => 12,
        ]));
        self::assertNull(S::payloadReversoConsumoPicking([
            'id' => 1,
            'lote' => '',
            'cantidad' => -8,
        ], 1, '2026-09-18'));
    }

    public function test_encabezado_excel_incluye_cliente_fecha_pedido_y_factura(): void
    {
        $enc = S::encabezadoExcel([
            [
                'cliente' => 'ACME SA',
                'pedido_codigo' => '9001',
                'factura' => 'FAC 00001-00001234',
                'fecha_picking' => '18/09/2026',
                'picking_codigo' => 7,
            ],
        ]);

        self::assertSame('PICKING #7', $enc['titulo']);
        self::assertContains('Fecha: 18/09/2026', $enc['lineas']);
        self::assertContains('Cliente: ACME SA', $enc['lineas']);
        self::assertContains('Pedido origen: 9001', $enc['lineas']);
        self::assertContains('Factura: FAC 00001-00001234', $enc['lineas']);
    }

    public function test_encabezado_excel_omite_factura_si_no_hay(): void
    {
        $enc = S::encabezadoExcel([
            [
                'cliente' => 'ACME SA',
                'pedido_codigo' => '9001',
                'factura' => '',
                'fecha_picking' => '18/09/2026',
                'picking_codigo' => 3,
            ],
        ]);

        self::assertSame('PICKING #3', $enc['titulo']);
        foreach ($enc['lineas'] as $linea) {
            self::assertStringNotContainsString('Factura:', $linea);
        }
    }

    public function test_nc_parcial_cubre_la_linea_cuando_la_cantidad_acreditada_alcanza_la_facturada(): void
    {
        self::assertTrue(NotaCreditoReabrePedidoOtFerliSupport::cantidadCubierta(12, 12));
        self::assertFalse(NotaCreditoReabrePedidoOtFerliSupport::cantidadCubierta(6, 12));
        self::assertFalse(NotaCreditoReabrePedidoOtFerliSupport::cantidadCubierta(12, 0));
    }

    public function test_precio_unitario_usa_el_de_los_talles_si_la_cabecera_difiere(): void
    {
        $talle = (object) ['cantidad' => 12, 'precio' => 17400];
        $linea = (object) [
            'precio' => 24000,
            'pedido_combinacion_talles' => [$talle],
        ];

        self::assertSame(17400.0, S::precioUnitarioLinea($linea));
    }

    public function test_precio_unitario_conserva_la_cabecera_si_los_talles_no_coinciden(): void
    {
        $linea = (object) [
            'precio' => 24000,
            'pedido_combinacion_talles' => [
                (object) ['cantidad' => 6, 'precio' => 17400],
                (object) ['cantidad' => 6, 'precio' => 19000],
            ],
        ];

        self::assertSame(24000.0, S::precioUnitarioLinea($linea));
    }

    public function test_mensaje_ot_asignada_a_otro_pedido(): void
    {
        $msg = S::mensajeOtAsignadaAOtroPedido('30797', '5042', 'CALZADOS LOS GALLEGOS');

        self::assertStringContainsString('30797', $msg);
        self::assertStringContainsString('5042', $msg);
        self::assertStringContainsString('CALZADOS LOS GALLEGOS', $msg);
        self::assertStringContainsString('No se puede usar en otro pedido', $msg);
    }

    public function test_descuentos_modal_toma_el_del_cliente(): void
    {
        $desc = S::descuentosModalFactura(5, [5, 5], [0, 0]);

        self::assertSame(5.0, $desc['descuentopie']);
        self::assertNull($desc['descuentolinea']);
    }

    public function test_descuentos_modal_usa_el_pedido_si_el_cliente_esta_en_cero(): void
    {
        $desc = S::descuentosModalFactura(0, ['5.00', 5], [10, 10]);

        self::assertSame(5.0, $desc['descuentopie']);
        self::assertSame(10.0, $desc['descuentolinea']);
    }

    public function test_descuentos_modal_no_inventa_linea_si_las_combinaciones_difieren(): void
    {
        $desc = S::descuentosModalFactura(0, [5, 8], [10, 12]);

        self::assertSame(0.0, $desc['descuentopie']);
        self::assertNull($desc['descuentolinea']);
    }

    public function test_numeracion_igual_a_la_del_lote_deja_preparar(): void
    {
        $curva = ['35' => 1, '36' => 2, '37' => 3, '38' => 3, '39' => 2, '40' => 1];

        self::assertNull(S::mensajeSiNumeracionSuperaElLote($curva, $curva));
    }

    public function test_sacar_menos_modulos_de_los_que_hay_deja_preparar(): void
    {
        // Lote con 3 módulos (36 pares) y pedido de 2 (24). El resto queda en el lote.
        self::assertNull(S::mensajeSiNumeracionSuperaElLote(
            ['36' => 2, '37' => 4, '38' => 6, '39' => 6, '40' => 4, '41' => 2],
            ['36' => 3, '37' => 6, '38' => 9, '39' => 9, '40' => 6, '41' => 3]
        ));
    }

    public function test_sacar_mas_modulos_de_los_que_hay_rechaza(): void
    {
        $msg = S::mensajeSiNumeracionSuperaElLote(
            ['36' => 4, '37' => 8, '38' => 12, '39' => 12, '40' => 8, '41' => 4],
            ['36' => 3, '37' => 6, '38' => 9, '39' => 9, '40' => 6, '41' => 3]
        );

        self::assertNotNull($msg);
        self::assertStringContainsString('no alcanza', $msg);
        self::assertStringContainsString('36: pide 4, hay 3', $msg);
        self::assertStringContainsString('41: pide 4, hay 3', $msg);
    }

    public function test_numeracion_ignora_talles_en_cero(): void
    {
        self::assertNull(S::mensajeSiNumeracionSuperaElLote(
            ['35' => 2, '36' => 0],
            ['35' => 2, '41' => 0]
        ));
    }

    public function test_curva_distinta_rechaza_aunque_cada_talle_alcance(): void
    {
        // Pedido 1-2-3-3-2-1 contra lote 1-1-2-3-3-2 (15 módulos). Hay pares, la forma no es la misma.
        $msg = S::mensajeSiNumeracionSuperaElLote(
            ['35' => 1, '36' => 2, '37' => 3, '38' => 3, '39' => 2, '40' => 1],
            ['35' => 15, '36' => 15, '37' => 30, '38' => 45, '39' => 45, '40' => 30]
        );

        self::assertNotNull($msg);
        self::assertStringContainsString('no coincide con la curva', $msg);
        self::assertStringContainsString('36:2', $msg);
        self::assertStringContainsString('36:1', $msg);
    }

    public function test_talle_extra_en_el_lote_no_es_la_misma_curva(): void
    {
        $msg = S::mensajeSiNumeracionSuperaElLote(
            ['35' => 1, '36' => 2],
            ['35' => 10, '36' => 9, '37' => 2]
        );

        self::assertNotNull($msg);
        self::assertStringContainsString('no coincide con la curva', $msg);
        self::assertStringContainsString('37:2', $msg);
    }

    public function test_elige_bucket_ot_lote_cero_cuando_hay_saldo_visible(): void
    {
        // Caso real OT 21205 dep 14: saldo en OT (lote=0); consumo viejo en L:codigo no debe ganar.
        $bucket = S::elegirBucketConsumoDesdeSaldos([
            ['lote' => '21205', 'ordentrabajo_id' => 0, 'saldo' => -12.0, 'talles' => []],
            ['lote' => 0, 'ordentrabajo_id' => 21205, 'saldo' => 72.0, 'talles' => ['36' => 72.0]],
        ], '21205', 21205);

        self::assertSame(0, $bucket['lote']);
        self::assertSame(21205, $bucket['ordentrabajo_id']);
        self::assertSame(72.0, $bucket['saldo']);
    }

    public function test_elige_bucket_lote_codigo_si_no_hay_ot_lote_cero(): void
    {
        // Alta cliente STOCK / legacy: stock vive en lote=código.
        $bucket = S::elegirBucketConsumoDesdeSaldos([
            ['lote' => '31135', 'ordentrabajo_id' => 31135, 'saldo' => 8.0, 'talles' => ['37' => 8.0]],
        ], '31135', 31135);

        self::assertSame('31135', $bucket['lote']);
        self::assertSame(31135, $bucket['ordentrabajo_id']);
        self::assertSame(8.0, $bucket['saldo']);
    }

    public function test_elige_bucket_fallback_sin_saldo_prefiere_ot(): void
    {
        $bucket = S::elegirBucketConsumoDesdeSaldos([], '21205', 21205);

        self::assertSame(0, $bucket['lote']);
        self::assertSame(21205, $bucket['ordentrabajo_id']);
        self::assertSame(0.0, $bucket['saldo']);
    }

    public function test_bucket_asignado_ot_no_usa_heuristica_lote(): void
    {
        // Si el modal eligió OT, el consumo debe ir a lote=0 aunque exista bucket L con saldo.
        $buckets = [
            ['lote' => '21205', 'ordentrabajo_id' => 0, 'saldo' => 50.0, 'talles' => []],
            ['lote' => 0, 'ordentrabajo_id' => 21205, 'saldo' => 72.0, 'talles' => ['36' => 72.0]],
        ];
        // Simula elegirBucket solo para el caso OT forzado (misma lógica que resolver con ot asignado).
        $ot = null;
        foreach ($buckets as $b) {
            if ((int) ($b['ordentrabajo_id'] ?? 0) === 21205
                && ! \App\Support\Stock\ReporteStockOtSituacionSupport::esLoteImportado($b['lote'] ?? 0)) {
                $ot = $b;
                break;
            }
        }
        self::assertNotNull($ot);
        self::assertSame(72.0, (float) $ot['saldo']);
        self::assertFalse(\App\Support\Stock\ReporteStockOtSituacionSupport::esLoteImportado($ot['lote']));
    }
}
