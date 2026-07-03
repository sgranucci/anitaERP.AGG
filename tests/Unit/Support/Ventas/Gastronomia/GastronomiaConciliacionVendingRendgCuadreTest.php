<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Support\Ventas\Gastronomia\GastronomiaConciliacionVendingRendgSupport;
use Tests\TestCase;

final class GastronomiaConciliacionVendingRendgCuadreTest extends TestCase
{
    public function test_venta_anita_vending_desde_rendg_legacy_host_vending_nro(): void
    {
        $support = app(GastronomiaConciliacionVendingRendgSupport::class);
        $cabeceras = [
            (object) [
                'rendg_host' => 'VENDING NRO.42',
                'rendg_sucursal' => 1202,
                'rendg_empresa' => 3,
                'rendg_total_z' => 554700.0,
                'rendg_tot_nc' => 0.0,
                'rendg_nro_oper' => 765981,
            ],
        ];

        $resultado = $support->ventaAnitaVendingDesdeRendg(3, $cabeceras);

        $this->assertSame(554700.0, $resultado['total']);
        $this->assertCount(1, $resultado['por_pv']);
        $this->assertSame(1202, $resultado['por_pv'][0]['pv_sucursal']);
        $this->assertSame(554700.0, $resultado['por_pv'][0]['rmv_z']);
        $this->assertSame(765981, $resultado['por_pv'][0]['rendg_nro_oper']);
    }

    public function test_venta_anita_vending_desde_rendg_resta_nc(): void
    {
        $support = app(GastronomiaConciliacionVendingRendgSupport::class);
        $cabeceras = [
            (object) [
                'rendg_host' => 'VENDING NRO.1',
                'rendg_sucursal' => 1205,
                'rendg_empresa' => 3,
                'rendg_total_z' => 1000.0,
                'rendg_tot_nc' => 100.0,
                'rendg_nro_oper' => 100,
            ],
        ];

        $resultado = $support->ventaAnitaVendingDesdeRendg(3, $cabeceras);

        $this->assertSame(900.0, $resultado['total']);
        $this->assertSame(100.0, $resultado['por_pv'][0]['rmv_nc']);
    }
}
