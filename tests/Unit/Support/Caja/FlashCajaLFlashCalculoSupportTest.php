<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashCajaLFlashCalculoSupport;
use Tests\TestCase;

class FlashCajaLFlashCalculoSupportTest extends TestCase
{
    public function test_mes_completo_electronic_es_budget_por_indice(): void
    {
        $coef = FlashCajaLFlashCalculoSupport::coeficientesSeason(
            $this->budget(slot: 186063395, rul: 24946916, totalSlot: 31, totalRul: 31),
            $this->index(slot: 1.16, rul: 1),
            31,
        );

        $this->assertSame(240780454.2, $coef['coef_elec']);
    }

    public function test_suma_de_indices_incompleta_no_infla_el_presupuesto_del_dia(): void
    {
        // Noviembre 2026: 30 días, pero total_sslot quedó en la suma de la primera semana (~6,92).
        // 30/6,92 * índice multiplicaba el budget por ~4 y Electronic salía cerca de -85%.
        $incompleto = FlashCajaLFlashCalculoSupport::coeficientesSeason(
            $this->budget(slot: 186063395, rul: 24946916, totalSlot: 6.92, totalRul: 7),
            $this->index(slot: 1.16, rul: 1),
            30,
        );
        $normalizado = FlashCajaLFlashCalculoSupport::coeficientesSeason(
            $this->budget(slot: 186063395, rul: 24946916, totalSlot: 30, totalRul: 30),
            $this->index(slot: 1.16, rul: 1),
            30,
        );

        $this->assertSame($normalizado['coef_elec'], $incompleto['coef_elec']);
        $this->assertSame(240780454.2, $incompleto['coef_elec']);
    }

    /**
     * @return array<string, float>
     */
    private function budget(float $slot, float $rul, float $totalSlot, float $totalRul): array
    {
        return [
            'budget_total' => $slot + $rul,
            'budget_slot' => $slot,
            'budget_rul' => $rul,
            'budget_poker' => 0.0,
            'budget_bingo' => 0.0,
            'budget_ayb' => 0.0,
            'budget_estac' => 0.0,
            'budget_pos' => 0.0,
            'budget_electronic' => $slot + $rul,
            'total_season' => 0.0,
            'total_sbingo' => 0.0,
            'total_sslot' => $totalSlot,
            'total_srul' => $totalRul,
            'total_spoker' => 0.0,
            'total_s_estac' => 0.0,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function index(float $slot, float $rul): array
    {
        return [
            'customer' => 0.0,
            'season_index' => 0.0,
            'sindex_bingo' => 0.0,
            'sindex_slot' => $slot,
            'sindex_rul' => $rul,
            'sindex_poker' => 0.0,
            'sindex_estac' => 0.0,
            'vehiculos' => 0.0,
        ];
    }
}
