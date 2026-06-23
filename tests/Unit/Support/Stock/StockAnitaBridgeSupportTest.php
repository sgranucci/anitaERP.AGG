<?php

namespace Tests\Unit\Support\Stock;

use App\Support\Stock\StockAnitaBridgeSupport;
use Tests\TestCase;

class StockAnitaBridgeSupportTest extends TestCase
{
    public function test_biyemas_usa_bridge_global(): void
    {
        config([
            'anita.ip' => '10.20.30.200:8080',
            'anita.bdd_path' => '/usr2/biyemas',
            'stock.anita_stkmov.sistema_ventas' => 'ventas',
            'stock.anita_por_empresa' => [],
            'gastronomia.ticket_tarjeta_anita_por_empresa' => [],
        ]);

        $params = StockAnitaBridgeSupport::parametrosBridge(1);

        $this->assertSame('10.20.30.200:8080', $params['servidor']);
        $this->assertSame('/usr2/biyemas', $params['path_sistema']);
        $this->assertSame('ventas', $params['sistema']);
        $this->assertArrayNotHasKey('ifx_server', $params);
    }

    public function test_kandiko_usa_bridge_propio_desde_gastronomia(): void
    {
        config([
            'anita.ip' => '10.20.30.200:8080',
            'anita.bdd_path' => '/usr2/biyemas',
            'stock.anita_stkmov.sistema_ventas' => 'ventas',
            'stock.anita_por_empresa' => [],
            'gastronomia.ticket_tarjeta_anita_por_empresa' => [
                2 => [
                    'servidor' => '192.168.20.100:8080',
                    'path_sistema' => '/usr2/biyemas',
                    'ifx_server' => 'kancadmin',
                ],
            ],
        ]);

        $params = StockAnitaBridgeSupport::parametrosBridge(2);

        $this->assertSame('192.168.20.100:8080', $params['servidor']);
        $this->assertSame('kancadmin', $params['ifx_server']);
        $this->assertSame('ventas', $params['sistema']);
    }

    public function test_stock_override_tiene_prioridad_sobre_gastronomia(): void
    {
        config([
            'anita.ip' => '10.20.30.200:8080',
            'stock.anita_stkmov.sistema_ventas' => 'ventas',
            'stock.anita_por_empresa' => [
                3 => [
                    'servidor' => '192.168.40.100:8080',
                    'ifx_server' => 'rencadmin',
                ],
            ],
            'gastronomia.ticket_tarjeta_anita_por_empresa' => [],
        ]);

        $params = StockAnitaBridgeSupport::parametrosBridge(3);

        $this->assertSame('192.168.40.100:8080', $params['servidor']);
        $this->assertSame('rencadmin', $params['ifx_server']);
    }
}
