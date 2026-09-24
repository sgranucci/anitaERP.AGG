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
}
