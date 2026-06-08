<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryOrdenCobroSupport;
use Tests\TestCase;

final class WaitryOrdenCobroSupportTest extends TestCase
{
    public function test_cobrada_en_totem_desde_paid_waitry_en_linea(): void
    {
        $this->assertTrue(WaitryOrdenCobroSupport::cobradaEnTotem([
            'paid_waitry' => true,
            'total_neto_waitry' => 0.0,
        ]));
        $this->assertFalse(WaitryOrdenCobroSupport::cobradaEnTotem([
            'paid_waitry' => false,
            'monto_cobro_waitry' => 0.0,
        ]));
    }

    public function test_cobrada_en_totem_desde_waitry_cobro_totem(): void
    {
        $this->assertTrue(WaitryOrdenCobroSupport::cobradaEnTotem([
            'waitry_cobro_totem' => true,
            'paid_waitry' => null,
            'monto_cobro_waitry' => 0.0,
        ]));
    }

    public function test_cobrada_en_totem_desde_paid_crudo_waitry(): void
    {
        $this->assertTrue(WaitryOrdenCobroSupport::cobradaEnTotem(['paid' => 1]));
        $this->assertFalse(WaitryOrdenCobroSupport::cobradaEnTotem(['paid' => 0]));
    }
}
