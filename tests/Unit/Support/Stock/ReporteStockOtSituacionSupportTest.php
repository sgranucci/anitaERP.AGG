<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ReporteStockOtSituacionSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): leyenda Situación Stock por OT.
 */
class ReporteStockOtSituacionSupportTest extends TestCase
{
    public function test_ot_en_produccion_manda_aunque_haya_deposito(): void
    {
        $meta = ['situacion' => S::EN_PRODUCCION, 'en_produccion' => true];
        $r = S::situacionFila(false, 1, $meta, false);
        self::assertSame(S::EN_PRODUCCION, $r['situacion']);
        self::assertTrue($r['en_produccion']);
    }

    public function test_altap_excel_con_deposito_es_entrega_aunque_ot_abierta(): void
    {
        $meta = ['situacion' => S::EN_PRODUCCION, 'en_produccion' => true];
        $r = S::situacionFila(false, 1, $meta, true);
        self::assertSame(S::ENTREGA_INMEDIATA, $r['situacion']);
        self::assertFalse($r['en_produccion']);
    }

    public function test_deposito_sin_ot_es_entrega(): void
    {
        $r = S::situacionFila(false, 1, null, false);
        self::assertSame(S::ENTREGA_INMEDIATA, $r['situacion']);
    }

    public function test_forzada_es_produccion(): void
    {
        $r = S::situacionFila(true, 1, null, true);
        self::assertSame(S::EN_PRODUCCION, $r['situacion']);
        self::assertTrue($r['en_produccion']);
    }
}
