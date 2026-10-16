<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\Waitry\WaitryMesaAsignadaSupport;
use Tests\TestCase;

final class WaitryMesaAsignadaSupportTest extends TestCase
{
    public function test_la_mesa_de_la_pc_pisa_la_de_la_empresa(): void
    {
        $payload = WaitryMesaAsignadaSupport::payload(101340, 2);

        $this->assertSame(['tableId' => 101340], $payload);
    }

    public function test_sin_mesa_de_pc_usa_la_mesa_por_empresa(): void
    {
        config(['waitry.table_por_empresa' => [
            2 => ['tableId' => 101067],
        ], 'waitry.table' => []]);

        $payload = WaitryMesaAsignadaSupport::payload(null, 2);

        $this->assertSame(['tableId' => 101067], $payload);
    }
}
