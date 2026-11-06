<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\ControlFerliOtStockSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use PHPUnit\Framework\TestCase;

/**
 * Test puro (sin BD): partes del control stock:control-ferli-ot que no leen la base.
 */
class ControlFerliOtStockSupportTest extends TestCase
{
    public function test_residuo_del_reemplazo_excel(): void
    {
        // OT 21214, depósito 1: 72 de alta, 48 consumidos, el CONOT no restó y el ALTAP no repuso.
        $filas = [
            ['concepto' => 'Alta de produccion', 'cantidad' => 72.0],
            ['concepto' => 'Consumo de OT', 'cantidad' => -12.0],
            ['concepto' => 'Consumo de OT', 'cantidad' => -36.0],
            ['concepto' => 'Reemplazo stock Excel Ferli L12 (ALTAP)', 'cantidad' => 0.0],
            ['concepto' => 'Consumo de OT', 'cantidad' => -12.0],
            ['concepto' => 'Devolución NC OT/lote #1', 'cantidad' => 12.0],
        ];

        $r = ControlFerliOtStockSupport::residuoReemplazoExcel($filas);

        self::assertTrue($r['tiene_reemplazo']);
        self::assertSame(24.0, $r['residuo']);
        self::assertSame(24.0, $r['saldo']);
    }

    public function test_conot_que_lleva_a_cero_no_deja_residuo(): void
    {
        $filas = [
            ['concepto' => 'Alta de produccion', 'cantidad' => 60.0],
            ['concepto' => 'Reemplazo stock Excel Ferli L12 (CONOT)', 'cantidad' => -60.0],
            ['concepto' => 'Reemplazo stock Excel Ferli L12 (ALTAP)', 'cantidad' => 36.0],
        ];

        $r = ControlFerliOtStockSupport::residuoReemplazoExcel($filas);

        self::assertSame(0.0, $r['residuo']);
        self::assertSame(36.0, $r['saldo']);
    }

    public function test_bucket_sin_reemplazo(): void
    {
        $r = ControlFerliOtStockSupport::residuoReemplazoExcel([
            ['concepto' => 'Alta de produccion', 'cantidad' => 12.0],
        ]);

        self::assertFalse($r['tiene_reemplazo']);
    }

    public function test_solo_los_errores_cuentan_como_divergencia(): void
    {
        $controles = [
            'reporte_vs_canonico' => ['severidad' => ControlFerliOtStockSupport::SEVERIDAD_ERROR, 'cantidad' => 2],
            'movimiento_sin_talles' => ['severidad' => ControlFerliOtStockSupport::SEVERIDAD_ERROR, 'cantidad' => 1],
            'bucket_negativo' => ['severidad' => ControlFerliOtStockSupport::SEVERIDAD_AVISO, 'cantidad' => 927],
        ];

        self::assertSame(3, ControlFerliOtStockSupport::cantidadDivergencias($controles));
        self::assertSame(0, ControlFerliOtStockSupport::cantidadDivergencias([]));
    }

    public function test_prefijos_de_devolucion_de_consumo(): void
    {
        $prefijos = PedidoPickingFerliSupport::prefijosConceptoReversoConsumo();

        self::assertContains('Devolución NC OT/lote #', $prefijos);
        self::assertContains('Devolución picking #', $prefijos);
        self::assertContains('Devolución NC picking #', $prefijos);
    }
}
