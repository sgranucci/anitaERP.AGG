<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\Sicore;

use App\Support\Contable\Sicore\SicoreLiquidacionTipoSupport;
use PHPUnit\Framework\TestCase;

class SicoreLiquidacionTipoSupportTest extends TestCase
{
    public function test_sugiere_rangos_1q_y_dj(): void
    {
        $r1 = SicoreLiquidacionTipoSupport::sugerirRangos('1q', '2026-11-10');
        $this->assertSame('2026-11-01', $r1['compras_desde']);
        $this->assertSame('2026-11-15', $r1['compras_hasta']);
        $this->assertSame('2026-10-01', $r1['sueldos_desde']);

        $rd = SicoreLiquidacionTipoSupport::sugerirRangos('dj', '2026-10-20');
        $this->assertSame('2026-10-01', $rd['compras_desde']);
        $this->assertSame('2026-10-31', $rd['compras_hasta']);
        $this->assertSame('2026-09-01', $rd['sueldos_desde']);
    }

    public function test_dj_saldo_a_pagar_igual_f744_octubre(): void
    {
        $porCodigo = [
            '217' => ['q1' => 5402848.60, 'q2' => 7041911.79, 'total' => 12444760.39],
            '767' => ['q1' => 60900.00, 'q2' => 60900.00, 'total' => 121800.00],
            '787' => ['q1' => 14453532.31, 'q2' => 0.0, 'total' => 14453532.31],
        ];
        $saf = ['217' => 0.0, '767' => 0.0, '787' => 338251.74];
        $ingresos = [
            '217' => ['fecha' => '2026-10-20', 'importe' => 5402848.60],
            '767' => ['fecha' => '2026-10-20', 'importe' => 60900.00],
            '787' => ['fecha' => '2026-10-20', 'importe' => 14453532.31],
        ];

        $cuadro = SicoreLiquidacionTipoSupport::armarCuadro(
            'dj',
            $porCodigo,
            $saf,
            $ingresos,
            '2026-10-01',
            '2026-10-31',
            '2026-09-01',
            '2026-10-02',
        );

        $this->assertSame(7041911.79, $cuadro['saldo_pagar']['217']);
        $this->assertSame(60900.00, $cuadro['saldo_pagar']['767']);
        $this->assertSame(0.0, $cuadro['saldo_pagar']['787']);
        $this->assertSame(338251.74, $cuadro['saldo_favor_siguiente']['787']);
        $this->assertSame(7102811.79, $cuadro['total_a_pagar']);
    }

    public function test_1q_a_pagar_igual_retenciones(): void
    {
        $porCodigo = [
            '217' => ['q1' => 3130647.91, 'q2' => 0.0, 'total' => 3130647.91],
            '767' => ['q1' => 0.0, 'q2' => 0.0, 'total' => 0.0],
            '787' => ['q1' => 3508916.09, 'q2' => 0.0, 'total' => 3508916.09],
        ];

        $cuadro = SicoreLiquidacionTipoSupport::armarCuadro(
            '1q',
            $porCodigo,
            [],
            [],
            '2026-11-01',
            '2026-11-15',
            '2026-10-01',
            '2026-11-02',
        );

        $this->assertSame(6639564.00, $cuadro['total_a_pagar']);
        $this->assertSame(3130647.91, $cuadro['saldo_pagar']['217']);
        $this->assertSame('1q', $cuadro['tipo']);
    }

    public function test_nombre_zip(): void
    {
        $this->assertSame(
            'SICORE BIYEMAS DJ 10-2026.zip',
            SicoreLiquidacionTipoSupport::nombreZip('BIYEMAS', 'dj', '2026-10-01'),
        );
        $this->assertSame(
            'SICORE BIYEMAS 1Q 11-2026.zip',
            SicoreLiquidacionTipoSupport::nombreZip('BIYEMAS', '1q', '2026-11-01'),
        );
    }

    public function test_dj_exige_fecha_si_hay_ingreso(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SicoreLiquidacionTipoSupport::parsearSafIngresos('dj', [
            'ingreso_importe' => ['217' => 100],
            'ingreso_fecha' => ['217' => ''],
        ]);
    }
}
