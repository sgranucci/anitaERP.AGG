<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\OtListaEntregaSupport;
use App\Support\Stock\ReporteStockOtSituacionSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): aviso de OT no terminada, misma lectura que Stock por OT.
 */
class OtListaEntregaSupportTest extends TestCase
{
    public function test_produccion_y_pendiente_avisan_y_entrega_no(): void
    {
        $prod = OtListaEntregaSupport::desdeSituacion(S::EN_PRODUCCION, true, '29885');
        self::assertFalse($prod['lista']);
        self::assertSame('La OT 29885 no está terminada (EN PRODUCCION).', $prod['mensaje']);

        $pend = OtListaEntregaSupport::desdeSituacion(S::PENDIENTE_DE_FABRICACION, false, '30813');
        self::assertFalse($pend['lista']);
        self::assertStringContainsString('PENDIENTE DE FABRICACION', (string) $pend['mensaje']);

        $ok = OtListaEntregaSupport::desdeSituacion(S::ENTREGA_INMEDIATA, false, '1');
        self::assertTrue($ok['lista']);
        self::assertNull($ok['mensaje']);
    }

    public function test_lote_sin_ot_queda_como_entrega(): void
    {
        $r = OtListaEntregaSupport::evaluar(0, 12, null, '8021');
        self::assertTrue($r['lista']);
        self::assertSame(S::ENTREGA_INMEDIATA, $r['situacion']);
        self::assertNull($r['mensaje']);
    }

    public function test_en_produccion_manda_aunque_haya_deposito(): void
    {
        $r = OtListaEntregaSupport::evaluar(
            10,
            4,
            ['situacion' => S::EN_PRODUCCION, 'en_produccion' => true],
            '30001'
        );
        self::assertFalse($r['lista']);
        self::assertSame(S::EN_PRODUCCION, $r['situacion']);
    }
}
