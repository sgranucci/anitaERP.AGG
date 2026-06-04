<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryOrdenEstadoSupport;
use Tests\TestCase;

final class WaitryOrdenEstadoSupportTest extends TestCase
{
    public function test_es_cancelada_desde_flag_waitry(): void
    {
        $this->assertTrue(WaitryOrdenEstadoSupport::esCancelada(['canceled' => true]));
        $this->assertFalse(WaitryOrdenEstadoSupport::esCancelada(['canceled' => false]));
    }

    public function test_separar_canceladas_excluye_de_activas(): void
    {
        $split = WaitryOrdenEstadoSupport::separarCanceladas([
            ['waitry_order_id' => 1, 'total' => 100.0, 'waitry_cancelada' => false],
            ['waitry_order_id' => 2, 'total' => 50.0, 'waitry_cancelada' => true],
        ]);

        $this->assertCount(1, $split['activas']);
        $this->assertCount(1, $split['canceladas']);
        $this->assertSame(1, $split['resumen']['cantidad']);
        $this->assertSame(50.0, $split['resumen']['total']);
    }
}
