<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\MovimientoStockFerliSupport;
use App\Support\Stock\TransferenciaMercaderiaLoteBucketFerliSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): numeración completa del renglón y lote que graba una TRA Ferli.
 */
class MovimientoStockTallesCantidadFerliTest extends TestCase
{
    public function test_talles_que_suman_la_cantidad_pasan(): void
    {
        self::assertNull(MovimientoStockFerliSupport::errorSiTallesNoSumanCantidad(1, 12, [2, 4, 4, 2]));
        self::assertNull(MovimientoStockFerliSupport::errorSiTallesNoSumanCantidad(1, -12, [-2, -4, -4, -2]));
        self::assertNull(MovimientoStockFerliSupport::errorSiTallesNoSumanCantidad(1, -12, ['2', '4', '4', '2']));
    }

    public function test_renglon_sin_talles_se_rechaza_con_el_numero_de_linea(): void
    {
        $error = MovimientoStockFerliSupport::errorSiTallesNoSumanCantidad(3, -24, []);

        self::assertSame('Línea 3: falta la numeración por talle (24 pares sin talles).', $error);
    }

    public function test_talles_en_cero_cuentan_como_sin_talles(): void
    {
        $error = MovimientoStockFerliSupport::errorSiTallesNoSumanCantidad(2, 12, [0, 0, 0]);

        self::assertStringStartsWith('Línea 2: falta la numeración por talle', (string) $error);
    }

    public function test_suma_distinta_se_rechaza(): void
    {
        $error = MovimientoStockFerliSupport::errorSiTallesNoSumanCantidad(1, -24, [5, 5, 5, 5, 5]);

        self::assertSame('Línea 1: la suma de talles (25 pares) no coincide con la cantidad (24 pares).', $error);
    }

    public function test_lote_tipeado_es_el_lote_del_bucket(): void
    {
        self::assertSame(503760, TransferenciaMercaderiaLoteBucketFerliSupport::loteDesdeTexto(' 503760 '));
        self::assertSame(21214, TransferenciaMercaderiaLoteBucketFerliSupport::loteDesdeTexto('21214'));
    }

    public function test_lote_de_alta_o_vacio_usa_el_correlativo(): void
    {
        self::assertNull(TransferenciaMercaderiaLoteBucketFerliSupport::loteDesdeTexto(''));
        self::assertNull(TransferenciaMercaderiaLoteBucketFerliSupport::loteDesdeTexto('LOTE DE ALTA'));
        self::assertNull(TransferenciaMercaderiaLoteBucketFerliSupport::loteDesdeTexto('0'));
        self::assertNull(TransferenciaMercaderiaLoteBucketFerliSupport::loteDesdeTexto('12-A'));
    }
}
