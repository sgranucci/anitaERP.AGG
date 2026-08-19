<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashReporteAggMapeoSupport;
use Carbon\Carbon;
use Tests\TestCase;

class FlashReporteAggMapeoSupportTest extends TestCase
{
    public function test_etiqueta_dia_coincide_con_plantilla_julio(): void
    {
        $fecha = Carbon::create(2026, 7, 1);
        $this->assertSame('Mie/Wed     ', FlashReporteAggMapeoSupport::etiquetaDia($fecha));
        $this->assertSame(' 01/07/26 ', FlashReporteAggMapeoSupport::etiquetaFecha($fecha));
    }

    public function test_fila_datos_mapea_metricas_oficiales(): void
    {
        $fecha = Carbon::create(2026, 8, 3);
        $fila = FlashReporteAggMapeoSupport::filaDatos([
            'custom' => 2065,
            'slot_units' => 717,
            'slot_coin_in' => 2753266097,
            'slot_drop' => 518426571,
            'slot_ol_win' => 149316182,
            'slot_pct_coin' => 5.4,
            'slot_pct_drop' => 28.8,
            'slot_win_cust' => 72308,
            'slot_win_unit' => 208251,
            'rul_units' => 54,
            'rul_coin_in' => 739464510,
            'rul_drop' => 69051197,
            'rul_ol_win' => 37953855,
            'rul_pct_coin' => 5.1,
            'rul_pct_drop' => 55.0,
            'rul_win_cust' => 18379,
            'rul_win_seat' => 702849,
            'win_stand' => 242892,
            'el_positions' => 771,
            'win_online' => 187270037,
            'win_financial' => 298023512,
            'bingo_carton' => 2346,
            'bingo_venta' => 6024000,
            'bingo_win' => 2416972,
            'bingo_win_cust' => 1170.4,
            'gaming' => 189687010,
            'ayb' => 4714664,
            'ayb_cust' => 2283.1,
            'estac' => 437003,
            'estac_cust' => 211.6,
            'revenues' => 194838676,
            'revenues_cust' => 94352,
            'pos_online' => 771,
            'pos_vs_budget' => -2023,
            'customer_budget' => 2505,
            'customer_dev_pct' => -17.56,
            'vehiculos' => 12,
            'vehiculos_budget' => 20,
            'index' => ['customer' => 2505, 'vehiculos' => 20],
            'vs_season' => [
                'total' => 2.46,
                'electronic' => 3.94,
                'bingo' => -43.83,
                'ayb' => -9.68,
                'estac' => -5.37,
            ],
            'vs_budget' => [
                'total' => -4.49,
                'electronic' => -8.2,
                'bingo' => -43.83,
                'ayb' => -22.32,
                'estac' => -5.37,
            ],
        ], $fecha);

        $this->assertSame('Lun/Mon     ', $fila['A']);
        $this->assertSame(' 03/08/26 ', $fila['B']);
        $this->assertSame(2065, $fila['C']);
        $this->assertSame(717, $fila['D']);
        $this->assertSame(2753266097.0, $fila['E']);
        $this->assertSame(149316182.0, $fila['G']);
        $this->assertSame(54, $fila['L']);
        $this->assertSame(37953855.0, $fila['O']);
        $this->assertSame(187270037.0, $fila['AD']);
        $this->assertSame(298023512.0, $fila['AE']);
        $this->assertSame(2346, $fila['AG']);
        $this->assertSame(4714664.0, $fila['AL']);
        $this->assertSame(437003.0, $fila['AN']);
        $this->assertSame(194838676.0, $fila['AU']);
        $this->assertEqualsWithDelta(-0.1756, $fila['BA'], 0.0001);
        $this->assertEqualsWithDelta(0.0246, $fila['BB'], 0.0001);
        $this->assertEqualsWithDelta(-0.0449, $fila['BG'], 0.0001);
        $this->assertSame(12, $fila['BL']);
    }

    public function test_titulos_de_mes(): void
    {
        $this->assertSame('Reporte Flash Agosto 26', FlashReporteAggMapeoSupport::tituloMes(Carbon::create(2026, 8, 1)));
        $this->assertSame('Reporte Flash Julio 2026', FlashReporteAggMapeoSupport::tituloMesLargo(Carbon::create(2026, 7, 31)));
    }
}
