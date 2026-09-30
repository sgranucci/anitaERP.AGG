<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\OrdencompraReporteEntregaSql;
use PHPUnit\Framework\TestCase;

final class OrdencompraReporteEntregaSqlTest extends TestCase
{
    public function test_reparte_el_pool_en_orden_de_linea_hasta_la_capacidad(): void
    {
        $asignado = OrdencompraReporteEntregaSql::repartirPool([
            ['id' => 2, 'capacidad' => 20.11, 'penvp_orden' => 2],
            ['id' => 1, 'capacidad' => 40.7, 'penvp_orden' => 1],
            ['id' => 3, 'capacidad' => 10.0, 'penvp_orden' => null],
        ], 55.0);

        $this->assertEqualsWithDelta(40.7, $asignado[1], 0.0001);
        $this->assertEqualsWithDelta(14.3, $asignado[2], 0.0001);
        $this->assertEqualsWithDelta(0.0, $asignado[3], 0.0001);
    }

    public function test_un_pool_que_cubre_todas_las_lineas_deja_saldo_cero(): void
    {
        $asignado = OrdencompraReporteEntregaSql::repartirPool([
            ['id' => 10, 'capacidad' => 40.7, 'penvp_orden' => null],
            ['id' => 11, 'capacidad' => 20.11, 'penvp_orden' => null],
            ['id' => 12, 'capacidad' => 4.771, 'penvp_orden' => null],
            ['id' => 13, 'capacidad' => 17.25, 'penvp_orden' => null],
        ], 82.831);

        $this->assertEqualsWithDelta(40.7, $asignado[10], 0.0001);
        $this->assertEqualsWithDelta(20.11, $asignado[11], 0.0001);
        $this->assertEqualsWithDelta(4.771, $asignado[12], 0.0001);
        $this->assertEqualsWithDelta(17.25, $asignado[13], 0.0001);
    }
}
