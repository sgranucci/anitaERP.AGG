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

    public function test_pendiente_manda_aunque_haya_deposito_y_altap_excel(): void
    {
        $meta = ['situacion' => S::PENDIENTE_DE_FABRICACION, 'en_produccion' => false];
        $r = S::situacionFila(false, 1, $meta, true);
        self::assertSame(S::PENDIENTE_DE_FABRICACION, $r['situacion']);
        self::assertFalse($r['en_produccion']);
    }

    public function test_altap_excel_con_deposito_es_entrega_si_ot_no_pendiente_ni_produccion(): void
    {
        $meta = ['situacion' => S::ENTREGA_INMEDIATA, 'en_produccion' => false];
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

    public function test_desde_tarea_ids_solo_pendiente(): void
    {
        // Sin config Laravel: id pendiente = 0 → tratar [0] como sin avance si coincide.
        // Con ids de cierre vacíos y pendiente 0, [31] no está en sinAvance → EN PRODUCCION.
        // Probamos la rama cierre / vacía sin depender de config.
        $vacio = S::desdeTareaIds([]);
        self::assertSame(S::ENTREGA_INMEDIATA, $vacio['situacion']);
        self::assertFalse($vacio['en_produccion']);
    }

    public function test_es_cliente_stock_requiere_id_positivo_igual_config(): void
    {
        self::assertFalse(S::esClienteStock(0));
        self::assertFalse(S::esClienteStock(null));
        self::assertFalse(S::esClienteStock(''));
    }

    public function test_filtro_entrega_excluye_pendiente(): void
    {
        self::assertFalse(S::pasaFiltroEstadoOt('ENTREGA', S::PENDIENTE_DE_FABRICACION, false));
        self::assertTrue(S::pasaFiltroEstadoOt('ENTREGA', S::ENTREGA_INMEDIATA, false));
        self::assertTrue(S::pasaFiltroEstadoOt('PRODUCCION', S::PENDIENTE_DE_FABRICACION, false));
    }
}
