<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\OtBucketSaldoSupport;
use App\Support\Stock\OtBucketStockSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): clave de bucket, visibilidad en Stock por OT y depósito del consumo de OT stock.
 */
class OtBucketSaldoSupportTest extends TestCase
{
    public function test_clave_es_la_ot_resuelta_o_el_lote(): void
    {
        self::assertSame('OT:21214', OtBucketStockSupport::claveDesdeOtResuelta('21214', 21214));
        self::assertSame('OT:21214', OtBucketStockSupport::claveDesdeOtResuelta(0, 21214));
        self::assertSame('L:503760', OtBucketStockSupport::claveDesdeOtResuelta(' 503760 ', 0));
        self::assertSame('L:0', OtBucketStockSupport::claveDesdeOtResuelta(0, 0));
    }

    public function test_lote_importado_siempre_visible(): void
    {
        self::assertTrue(OtBucketSaldoSupport::bucketVisibleEnStockPorOt('L:503760', false, -4));
        self::assertTrue(OtBucketSaldoSupport::bucketVisibleEnStockPorOt('L:503760', false, 12));
    }

    public function test_ot_stock_visible_aunque_este_en_negativo(): void
    {
        self::assertTrue(OtBucketSaldoSupport::bucketVisibleEnStockPorOt('OT:30529', true, -12));
        self::assertTrue(OtBucketSaldoSupport::bucketVisibleEnStockPorOt('OT:30529', true, 0));
    }

    public function test_ot_comercial_visible_solo_con_saldo(): void
    {
        // Alta de Excel de una OT sin talles de STOCK (OT 8021): el picking la consume.
        self::assertTrue(OtBucketSaldoSupport::bucketVisibleEnStockPorOt('OT:8021', false, 119));
        self::assertFalse(OtBucketSaldoSupport::bucketVisibleEnStockPorOt('OT:29653', false, -12));
        self::assertFalse(OtBucketSaldoSupport::bucketVisibleEnStockPorOt('OT:29653', false, 0));
    }

    public function test_consumo_sale_del_deposito_con_mas_saldo(): void
    {
        $saldos = [
            1 => ['saldo' => 24.0],
            14 => ['saldo' => 60.0],
        ];

        self::assertSame(14, OtBucketSaldoSupport::depositoParaConsumir($saldos, 1));
    }

    public function test_a_igual_saldo_queda_el_deposito_del_alta(): void
    {
        $saldos = [
            1 => ['saldo' => 60.0],
            14 => ['saldo' => 60.0],
        ];

        self::assertSame(1, OtBucketSaldoSupport::depositoParaConsumir($saldos, 1));
        self::assertSame(14, OtBucketSaldoSupport::depositoParaConsumir($saldos, 14));
    }

    public function test_sin_saldo_queda_el_deposito_del_alta(): void
    {
        $saldos = [
            1 => ['saldo' => 0.0],
            5 => ['saldo' => -12.0],
        ];

        self::assertSame(1, OtBucketSaldoSupport::depositoParaConsumir($saldos, 1));
        self::assertSame(0, OtBucketSaldoSupport::depositoParaConsumir([], 0));
    }

    public function test_un_local_negativo_no_tapa_la_fabrica(): void
    {
        $saldos = [
            5 => ['saldo' => -30.0],
            1 => ['saldo' => 12.0],
        ];

        self::assertSame(1, OtBucketSaldoSupport::depositoParaConsumir($saldos, 5));
    }
}
