<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\IngresoEgresoCuadreCajaAsientoSupport;
use PHPUnit\Framework\TestCase;

class IngresoEgresoCuadreCajaAsientoSupportTest extends TestCase
{
    public function test_totales_operacion_incluye_reemplazo_emitido_sin_cuentas_caja(): void
    {
        $data = [
            'cuentacaja_ids' => [],
            'montos' => [],
            'cheque_anulado_ids' => [55],
            'origen_reemplazo' => ['E'],
            'montocheque_reemplazo' => [1500.5],
            'debeasientos' => [1500.5],
            'haberasientos' => [1500.5],
        ];

        $totales = IngresoEgresoCuadreCajaAsientoSupport::totalesOperacionCaja($data);

        $this->assertSame(0.0, $totales['debe']);
        $this->assertSame(1500.5, $totales['haber']);

        IngresoEgresoCuadreCajaAsientoSupport::assertCuadre($data);
        $this->assertTrue(true);
    }

    public function test_totales_reemplazo_desde_json_si_no_hay_arrays_form(): void
    {
        $data = [
            'datoscheques_reemplazo' => json_encode([
                [
                    'cheque_anulado_id' => 9,
                    'origen_reemplazo' => 'R',
                    'monto_reemplazo' => 200,
                ],
            ]),
        ];

        $totales = IngresoEgresoCuadreCajaAsientoSupport::totalesChequesReemplazo($data);

        $this->assertSame(200.0, $totales['debe']);
        $this->assertSame(0.0, $totales['haber']);
    }

    public function test_opa_con_retenciones_acepta_caja_neta_y_asiento_bruto(): void
    {
        IngresoEgresoCuadreCajaAsientoSupport::assertCuadre([
            'cuentacaja_ids' => [52],
            'montos' => [1716290],
            'debeasientos' => [2096000, '', ''],
            'haberasientos' => ['', 347710, 32000, 1716290],
            'retenciones_precalculadas' => 379710,
        ]);

        $this->assertTrue(true);
    }

    public function test_liquidacion_tarjeta_acepta_caja_neta_y_asiento_bruto(): void
    {
        IngresoEgresoCuadreCajaAsientoSupport::assertCuadre([
            'cuentacaja_ids' => [10],
            'montos' => [1342010.73],
            'debeasientos' => [1342010.73, 93689.27],
            'haberasientos' => ['', 1435700],
            'comprobantes_ivacompra_json' => json_encode([[
                'tipo_tesoreria' => 'LIQUIDACION_TARJETA',
                'total' => 93689.27,
                'bruto_cupones' => 1435700,
            ]]),
        ]);

        $this->assertTrue(true);
    }

    public function test_sin_retenciones_la_caja_tiene_que_igualar_al_asiento(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        IngresoEgresoCuadreCajaAsientoSupport::assertCuadre([
            'cuentacaja_ids' => [1],
            'montos' => [100],
            'debeasientos' => [200],
            'haberasientos' => [200],
            'retenciones_precalculadas' => 0,
        ]);
    }
}
