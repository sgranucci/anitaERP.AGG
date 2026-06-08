<?php

namespace Tests\Unit\Support\Ventas\Waitry;

use App\Support\Ventas\Waitry\WaitryTableAccesoSupport;
use Tests\TestCase;

final class WaitryTableAccesoSupportTest extends TestCase
{
    public function test_extraer_desde_orden_con_table_y_layout(): void
    {
        $acceso = WaitryTableAccesoSupport::extraerDesdeOrden([
            'table' => [
                'id' => 103443,
                'name' => 'K1',
                'layout' => [
                    'id' => 32392,
                    'name' => 'Kiosco 1',
                ],
            ],
        ]);

        $this->assertSame(103443, $acceso['table_id']);
        $this->assertSame('K1', $acceso['table_name']);
        $this->assertSame(32392, $acceso['layout_id']);
        $this->assertSame('Kiosco 1', $acceso['layout_name']);
    }

    public function test_extraer_table_id_legacy_table_id_en_raiz(): void
    {
        $this->assertSame(101066, WaitryTableAccesoSupport::extraerTableId(['tableId' => 101066]));
    }
}
