<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\AnitaComprobDescuentoSupport as S;
use PHPUnit\Framework\TestCase;

/**
 * a-comprob.c calcula(): tot_dto sobre neto; letra != A → tot_dto *= (1 + tasa/100).
 * Caso Berlanga FAC B: neto 7808.08 × 1.21 = 9447.78 (Anita ven_monto_desc).
 */
final class AnitaComprobDescuentoSupportTest extends TestCase
{
    public function test_letra_b_expresa_descuento_sobre_bruto(): void
    {
        $this->assertSame(9447.78, S::expresarImporte('B', 7808.08, 21.0));
        $this->assertSame(9447.78, S::expresarParaLetra('B', ['21' => 7808.08]));
        $this->assertTrue(S::debeExpresarSobreBruto([], 'B'));
        $this->assertTrue(S::debeExpresarSobreBruto([], 'C'));
    }

    public function test_circuito_pos_no_expresa_sobre_bruto(): void
    {
        $this->assertFalse(S::debeExpresarSobreBruto([S::FLAG_OMITIR_CIRCUITO_POS => true], 'B'));
    }

    public function test_letra_a_deja_descuento_sobre_neto(): void
    {
        $this->assertSame(7808.08, S::expresarImporte('A', 7808.08, 21.0));
        $this->assertFalse(S::debeExpresarSobreBruto([], 'A'));
    }

    public function test_mezcla_21_y_10_5_como_a_comprob(): void
    {
        // tot_dto * 1.21 + _tot_dto_otasa * 1.105
        $out = S::expresarParaLetra('B', [
            '21' => 100.0,
            '10.5' => 50.0,
        ]);
        $this->assertSame(176.25, $out);
    }

    public function test_tasa_cero_no_infla(): void
    {
        $this->assertSame(100.0, S::expresarImporte('B', 100.0, 0.0));
    }
}
