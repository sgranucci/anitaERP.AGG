<?php

namespace Tests\Unit\Support\Caja;

use App\Support\Caja\Flash\FlashCrownCalculoSupport;
use PHPUnit\Framework\TestCase;

class FlashCrownSlotsResultadoOnlineTest extends TestCase
{
    public function test_permite_refrescar_si_flash_sin_slots(): void
    {
        $base = [
            'coin_in' => 0,
            'slot_r' => 0,
            'detalle_json' => [
                'fuentes' => ['resultado_anita'],
                'anita' => ['slot_fuente' => 'resultado.slot_r'],
            ],
        ];

        self::assertTrue(FlashCrownCalculoSupport::puedeRefrescarSlotsDesdeResultadoOnline($base));
    }

    public function test_permite_refrescar_parcial_sin_resulol_anita(): void
    {
        // Caso CIP 12/11: se copió un coin_in incompleto; GST corregido después.
        $base = [
            'coin_in' => 75216800,
            'slot_r' => 75216800,
            'detalle_json' => [
                'fuentes' => ['resultado_anita', 'resultado_online'],
                'anita' => [
                    'slot_fuente' => 'resultado.slot_r',
                    'slot_r_facturable' => 0,
                ],
            ],
        ];

        self::assertFalse(FlashCrownCalculoSupport::slotFuenteEsResulolAnita($base));
        self::assertTrue(FlashCrownCalculoSupport::puedeRefrescarSlotsDesdeResultadoOnline($base));
    }

    public function test_no_pisa_si_anita_trajo_resulol(): void
    {
        $base = [
            'coin_in' => 1000000,
            'slot_r' => 50000,
            'detalle_json' => [
                'fuentes' => ['resultado_anita', 'resulol'],
                'anita' => ['slot_fuente' => 'resulol'],
            ],
        ];

        self::assertTrue(FlashCrownCalculoSupport::slotFuenteEsResulolAnita($base));
        self::assertFalse(FlashCrownCalculoSupport::puedeRefrescarSlotsDesdeResultadoOnline($base));
    }

    public function test_detecta_vivo_incompleto_cip_1_11(): void
    {
        $stored = [
            'att' => 1852,
            'cashdrop' => 13862000,
            'caja_empleado' => 475300,
            'wheel_r' => 4937500,
            'blackjack_r' => 995000,
            'caribbean_r' => 1650000,
            'baccarat_r' => 0,
            'multi_r' => 0,
            'crap_r' => 0,
            'poker_r' => 0,
            'seven_r' => 0,
            'super_peek_r' => 0,
        ];
        $vivo = [
            'att' => 0,
            'cashdrop' => 0,
            'caja_empleado' => 1000,
            'wheel_r' => -42265000,
            'blackjack_r' => 0,
            'caribbean_r' => 0,
            'baccarat_r' => 0,
            'multi_r' => 0,
            'crap_r' => 0,
            'poker_r' => 0,
            'seven_r' => 0,
            'super_peek_r' => 0,
        ];

        self::assertTrue(FlashCrownCalculoSupport::vivoJornadaIncompletoRespectoFlash($vivo, $stored));
    }
}
