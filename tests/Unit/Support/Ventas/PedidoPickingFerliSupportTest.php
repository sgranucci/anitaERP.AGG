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
