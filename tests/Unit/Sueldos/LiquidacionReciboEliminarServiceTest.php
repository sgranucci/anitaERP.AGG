<?php

namespace Tests\Unit\Sueldos;

use App\Services\Sueldos\LiquidacionReciboEliminarService;
use PHPUnit\Framework\TestCase;

class LiquidacionReciboEliminarServiceTest extends TestCase
{
    public function test_filtro_pasa_a_seleccion_explicita_con_restantes(): void
    {
        $out = LiquidacionReciboEliminarService::armarFiltrosTrasQuitar(
            ['agrupamiento_id' => 3],
            [10, 20, 10, 0, -1]
        );

        $this->assertSame([10, 20], $out['empleado_ids']);
        $this->assertSame(3, $out['agrupamiento_id']);
    }

    public function test_filtro_vacio_queda_como_seleccion_sin_empleados(): void
    {
        $out = LiquidacionReciboEliminarService::armarFiltrosTrasQuitar(null, []);

        $this->assertArrayHasKey('empleado_ids', $out);
        $this->assertSame([], $out['empleado_ids']);
    }

    public function test_conserva_otros_filtros_al_vaciar(): void
    {
        $out = LiquidacionReciboEliminarService::armarFiltrosTrasQuitar(
            ['empleado_ids' => [1, 2, 3], 'centrocosto_id' => 9],
            []
        );

        $this->assertSame([], $out['empleado_ids']);
        $this->assertSame(9, $out['centrocosto_id']);
    }
}
