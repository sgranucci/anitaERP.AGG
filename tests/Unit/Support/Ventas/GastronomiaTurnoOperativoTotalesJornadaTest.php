<?php

namespace Tests\Unit\Support\Ventas;

use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class GastronomiaTurnoOperativoTotalesJornadaTest extends TestCase
{
    public function test_query_emisiones_jornada_empresa_devuelve_builder(): void
    {
        $q = GastronomiaTurnoOperativoTotalesSupport::queryEmisionesJornadaEmpresa(
            1,
            '2026-05-31',
            now()->subHours(8),
            now(),
        );

        $this->assertInstanceOf(Builder::class, $q);
        $this->assertSame(VentaGastronomiaEmision::class, $q->getModel()::class);
        $this->assertStringContainsString('venta_gastronomia_emision', $q->toSql());
    }
}
