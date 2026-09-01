<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Contable\CanonEntidades;

use App\Support\Contable\CanonEntidades\CanonEntidadesCalculoSupport;
use App\Support\Contable\CanonEntidades\CanonEntidadesReglasSupport;
use PHPUnit\Framework\TestCase;

class CanonEntidadesCalculoSupportTest extends TestCase
{
    public function test_maquinas_solo_dias_con_win_positivo(): void
    {
        $out = CanonEntidadesCalculoSupport::calcular([
            $this->dia('2026-07-01', 10_000.00, 0),
            $this->dia('2026-07-02', -3_883_084.70, 0),
            $this->dia('2026-07-03', 0, 0),
        ], CanonEntidadesReglasSupport::REGLA_REBISCO);

        $this->assertEquals(100.00, $out['filas'][0]['canon_maq']);
        $this->assertFalse($out['filas'][0]['excluido_maq']);
        $this->assertEquals(0.0, $out['filas'][1]['canon_maq']);
        $this->assertTrue($out['filas'][1]['excluido_maq']);
        $this->assertTrue($out['filas'][2]['excluido_maq']);
        $this->assertEquals(100.00, $out['totales']['canon_maq']);
        $this->assertSame(2, $out['totales']['dias_excluidos_maq']);
    }

    public function test_bingo_plana_es_uno_por_ciento(): void
    {
        $out = CanonEntidadesCalculoSupport::calcular([
            $this->dia('2026-07-01', 0, 10_000.00),
            $this->dia('2026-07-02', 0, 23_726.00),
        ], CanonEntidadesReglasSupport::REGLA_KANDIKO);

        $this->assertEquals(100.00, $out['filas'][0]['canon_bin']);
        $this->assertEquals(237.26, $out['filas'][1]['canon_bin']);
        $this->assertEquals(337.26, $out['totales']['canon_bin']);
    }

    public function test_bingo_biyemas_escalon_sobre_acumulado_no_por_dia(): void
    {
        // Primer día consume el piso de $1.500.000 al 2%.
        // 07/07: 2.412.000 → 3,25% pleno = 78.390 (no 59.640 del criterio por día).
        $out = CanonEntidadesCalculoSupport::calcular([
            $this->dia('2026-07-01', 0, 1_500_000.00),
            $this->dia('2026-07-07', 0, 2_412_000.00),
        ], CanonEntidadesReglasSupport::REGLA_BIYEMAS);

        $this->assertEquals(30_000.00, $out['filas'][0]['canon_bin']);
        $this->assertEquals(78_390.00, $out['filas'][1]['canon_bin']);
        $this->assertEquals(108_390.00, $out['totales']['canon_bin']);
        $this->assertEquals(1_500_000.00, $out['filas'][0]['bingo_tramo_2']);
        $this->assertEquals(0.0, $out['filas'][1]['bingo_tramo_2']);
        $this->assertEquals(2_412_000.00, $out['filas'][1]['bingo_tramo_325']);
    }

    public function test_bingo_biyemas_primer_dia_por_encima_del_piso(): void
    {
        $out = CanonEntidadesCalculoSupport::calcular([
            $this->dia('2026-07-07', 0, 2_412_000.00),
        ], CanonEntidadesReglasSupport::REGLA_BIYEMAS);

        // 1.500.000 × 2% + 912.000 × 3,25% = 30.000 + 29.640 = 59.640
        $this->assertEquals(59_640.00, $out['filas'][0]['canon_bin']);
        $this->assertEquals(59_640.00, $out['totales']['canon_bin']);
    }

    public function test_totales_son_suma_de_dias(): void
    {
        $out = CanonEntidadesCalculoSupport::calcular([
            $this->dia('2026-07-01', 200.00, 100.00),
            $this->dia('2026-07-02', 300.00, 50.00),
        ], CanonEntidadesReglasSupport::REGLA_REBISCO);

        $sumaMaq = $out['filas'][0]['canon_maq'] + $out['filas'][1]['canon_maq'];
        $sumaBin = $out['filas'][0]['canon_bin'] + $out['filas'][1]['canon_bin'];

        $this->assertEquals($sumaMaq, $out['totales']['canon_maq']);
        $this->assertEquals($sumaBin, $out['totales']['canon_bin']);
        $this->assertEquals($sumaMaq + $sumaBin, $out['totales']['canon_total']);
        $this->assertEquals(2.00, $out['filas'][0]['canon_maq']);
        $this->assertEquals(1.00, $out['filas'][0]['canon_bin']);
        $this->assertEquals(3.00, $out['filas'][0]['canon_total']);
    }

    public function test_anexa_haber_diario_y_diferencia(): void
    {
        $calculo = CanonEntidadesCalculoSupport::calcular([
            $this->dia('2026-07-28', 0, 5_586_000.00),
        ], CanonEntidadesReglasSupport::REGLA_BIYEMAS);

        $filas = CanonEntidadesCalculoSupport::anexarHaberDiario($calculo['filas'], [
            ['fecha' => '2026-07-28', 'tipo' => 'BIN', 'haber' => 123_045.00],
        ]);

        $this->assertEquals(181_545.00, $filas[0]['canon_bin']);
        $this->assertEquals(123_045.00, $filas[0]['haber_bin']);
        $this->assertEquals(58_500.00, $filas[0]['dif_dia']);
    }

    public function test_dia_sin_flash_no_resta_ni_computa_maquinas(): void
    {
        $out = CanonEntidadesCalculoSupport::calcular([
            [
                'fecha_iso' => '2026-07-01',
                'win_electronico' => 0,
                'ventas_bingo' => 0,
                'tiene_flash' => false,
            ],
        ], CanonEntidadesReglasSupport::REGLA_BIYEMAS);

        $this->assertTrue($out['filas'][0]['excluido_maq']);
        $this->assertEquals(0.0, $out['totales']['canon_maq']);
        $this->assertSame(0, $out['totales']['dias_con_flash']);
    }

    /**
     * @return array<string, mixed>
     */
    private function dia(string $iso, float $win, float $bingo): array
    {
        return [
            'fecha_iso' => $iso,
            'win_electronico' => $win,
            'ventas_bingo' => $bingo,
            'tiene_flash' => true,
        ];
    }
}
