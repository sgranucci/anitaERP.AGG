<?php

namespace Tests\Unit\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Support\Caja\IngresoEgresoVisibilidadSupport;
use Tests\TestCase;

class IngresoEgresoVisibilidadOcCentrocostoTest extends TestCase
{
    public function test_filtra_ordenes_de_pago_por_centro_de_costo_de_la_orden_de_compra(): void
    {
        $query = Caja_Movimiento::query();
        IngresoEgresoVisibilidadSupport::aplicarFiltroOcDelCentrocosto($query, 'caja_movimiento', 6);

        $sql = $query->toSql();
        $this->assertStringContainsString('pagoproveedor', $sql);
        $this->assertStringContainsString('ordencompra', $sql);
        $this->assertStringContainsString('centrocosto_id', $sql);
        $this->assertStringContainsString('pagoproveedor_id', $sql);
        $this->assertContains(6, $query->getBindings());
    }

    public function test_centro_de_costo_invalido_no_devuelve_movimientos(): void
    {
        $query = Caja_Movimiento::query();
        IngresoEgresoVisibilidadSupport::aplicarFiltroOcDelCentrocosto($query, 'caja_movimiento', 0);

        $this->assertStringContainsString('1 = 0', $query->toSql());
    }
}
