<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\GastronomiaTicketTarjetaAnitaBridgeSupport;
use Tests\TestCase;

class GastronomiaTicketTarjetaAnitaBridgeSupportTest extends TestCase
{
    public function test_biyemas_usa_bridge_global(): void
    {
        config([
            'anita.ip' => '10.20.30.200:8080',
            'anita.bdd_path' => '/usr2/biyemas',
            'gastronomia.ticket_tarjeta_anita_sistema' => 'base_admin',
            'gastronomia.ticket_tarjeta_anita_por_empresa' => [],
        ]);

        $params = GastronomiaTicketTarjetaAnitaBridgeSupport::parametrosBridge(1);

        $this->assertSame('10.20.30.200:8080', $params['servidor']);
        $this->assertSame('/usr2/biyemas', $params['path_sistema']);
        $this->assertSame('base_admin', $params['sistema']);
    }

    public function test_kandiko_usa_bridge_propio(): void
    {
        config([
            'anita.ip' => '10.20.30.200:8080',
            'anita.bdd_path' => '/usr2/biyemas',
            'gastronomia.ticket_tarjeta_anita_sistema' => 'base_admin',
            'gastronomia.ticket_tarjeta_anita_por_empresa' => [
                2 => [
                    'servidor' => '192.168.20.100:8080',
                    'path_sistema' => '/usr2/biyemas',
                    'ifx_server' => 'kancadmin',
                ],
            ],
        ]);

        $params = GastronomiaTicketTarjetaAnitaBridgeSupport::parametrosBridge(2);

        $this->assertSame('192.168.20.100:8080', $params['servidor']);
        $this->assertSame('/usr2/biyemas', $params['path_sistema']);
        $this->assertSame('base_admin', $params['sistema']);
        $this->assertSame('kancadmin', $params['ifx_server']);
    }

    public function test_rebisco_usa_bridge_propio(): void
    {
        config([
            'anita.ip' => '10.20.30.200:8080',
            'anita.bdd_path' => '/usr2/biyemas',
            'gastronomia.ticket_tarjeta_anita_sistema' => 'base_admin',
            'gastronomia.ticket_tarjeta_anita_por_empresa' => [
                3 => [
                    'servidor' => '192.168.40.100:8080',
                    'path_sistema' => '/usr2/biyemas',
                    'ifx_server' => 'rencadmin',
                ],
            ],
        ]);

        $params = GastronomiaTicketTarjetaAnitaBridgeSupport::parametrosBridge(3);

        $this->assertSame('192.168.40.100:8080', $params['servidor']);
        $this->assertSame('/usr2/biyemas', $params['path_sistema']);
        $this->assertSame('base_admin', $params['sistema']);
        $this->assertSame('rencadmin', $params['ifx_server']);
    }

    public function test_biyemas_no_fuerza_ifx_server_propio(): void
    {
        config([
            'anita.ip' => '10.20.30.200:8080',
            'anita.bdd_path' => '/usr2/biyemas',
            'gastronomia.ticket_tarjeta_anita_sistema' => 'base_admin',
            'gastronomia.ticket_tarjeta_anita_por_empresa' => [],
        ]);

        $params = GastronomiaTicketTarjetaAnitaBridgeSupport::parametrosBridge(1);

        $this->assertArrayNotHasKey('ifx_server', $params);
    }
}
