<?php

namespace Tests\Unit\Support\Caja;

use App\Models\Caja\Flash\FlashCaja;
use App\Support\Caja\Flash\FlashCajaAnitaMapeoSupport;
use Tests\TestCase;

class FlashCajaAnitaMapeoSupportTest extends TestCase
{
    public function test_mapea_salas_de_las_tres_empresas(): void
    {
        $this->assertSame(1, FlashCajaAnitaMapeoSupport::empresaIdDesdeSala(21));
        $this->assertSame(2, FlashCajaAnitaMapeoSupport::empresaIdDesdeSala(38));
        $this->assertSame(3, FlashCajaAnitaMapeoSupport::empresaIdDesdeSala(43));
        $this->assertNull(FlashCajaAnitaMapeoSupport::empresaIdDesdeSala(99));

        $this->assertSame(21, FlashCajaAnitaMapeoSupport::salaDesdeEmpresaId(1));
        $this->assertSame(38, FlashCajaAnitaMapeoSupport::salaDesdeEmpresaId(2));
        $this->assertSame(43, FlashCajaAnitaMapeoSupport::salaDesdeEmpresaId(3));
        $this->assertNull(FlashCajaAnitaMapeoSupport::salaDesdeEmpresaId(9));
    }

    public function test_valores_sql_incluyen_clave_y_ceros_poker(): void
    {
        $flash = new FlashCaja([
            'empresa_id' => 1,
            'fecha' => '2026-08-15',
            'att' => 1200,
            'ayb' => 10.5,
            'vending' => 20.25,
            'slot_d' => 1,
            'slot_r' => 2,
            'slot_coin_in' => 3,
            'soft_count' => 0,
            'hard_count' => 0,
            'cant_slots' => 700,
            'rul_d' => 0,
            'rul_r' => 0,
            'rul_coin_in' => 0,
            'soft_rul' => 0,
            'hard_rul' => 0,
            'cant_rul' => 50,
            'cotizacion' => 1.25,
            'comentario' => 'cron diario',
            'bingo_cant_carton' => 10,
            'bingo_total_venta' => 100,
            'bingo_resultado' => 40,
            'pos_online' => 770,
            'win_ol_slot' => 80.25,
            'win_ol_rul' => 20.5,
            'estac' => 5,
            'cant_vehic' => 3,
            'show' => 0,
        ]);

        $valores = FlashCajaAnitaMapeoSupport::valoresDesdeFlash($flash, 21, 1);
        $this->assertSame(1, $valores['flash_empresa']);
        $this->assertSame(21, $valores['flash_sala']);
        $this->assertSame(20260815, $valores['flash_fecha']);
        $this->assertEquals(30.75, $valores['flash_ayb']);
        $this->assertEquals(80.25, $valores['flash_win_ol_slot']);
        $this->assertSame(0, $valores['flash_cant_poker']);
        $this->assertSame('cron diario', $valores['flash_comentario']);

        $sql = FlashCajaAnitaMapeoSupport::valoresSql($valores);
        $this->assertStringContainsString('20260815', $sql);
        $this->assertStringContainsString("'cron diario'", $sql);
        $this->assertStringContainsString('80.2500', $sql);

        $update = FlashCajaAnitaMapeoSupport::valoresUpdateSql($valores);
        $this->assertStringContainsString("flash_comentario = 'cron diario'", $update);
        $this->assertStringContainsString('flash_win_ol_slot = 80.2500', $update);
        $this->assertStringContainsString('flash_att = 1200', $update);
        $this->assertStringNotContainsString('flash_empresa =', $update);
        $this->assertStringNotContainsString('flash_fecha =', $update);

        $this->assertSame(
            ' WHERE flash_empresa = 1 AND flash_sala = 21 AND flash_fecha = 20260815',
            FlashCajaAnitaMapeoSupport::whereClave(1, 21, 20260815)
        );
    }

    public function test_flash_ayb_anita_suma_ayb_y_vending(): void
    {
        $flash = new FlashCaja(['ayb' => 843840.96, 'vending' => 647900.0]);

        $this->assertEquals(1491740.96, FlashCajaAnitaMapeoSupport::flashAybParaAnita($flash));
    }
}
