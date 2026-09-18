<?php

namespace Tests\Unit\Support\Ventas;

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
}
