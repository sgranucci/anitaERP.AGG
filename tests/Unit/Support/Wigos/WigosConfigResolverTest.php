<?php

namespace Tests\Unit\Support\Wigos;

use App\Support\Wigos\WigosConfigResolver;
use Tests\TestCase;

class WigosConfigResolverTest extends TestCase
{
    public function test_empresa_kandiko_usa_servidores_wilde(): void
    {
        config([
            'wigos.connections' => [
                'A' => ['host' => 'serverwigosa', 'port' => '1433', 'database' => 'wgdb_000'],
                'B' => ['host' => 'serverwigosb', 'port' => '1433', 'database' => 'wgdb_000'],
            ],
            'wigos.account_info_url' => 'http://serverwigosws:7788/WIGOS/AccountInfoJSON?trackdata=%s',
            'wigos.por_empresa' => [
                2 => [
                    'connections' => [
                        'A' => ['host' => 'serverwigosksaa'],
                        'B' => ['host' => 'serverwigosksab'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('serverwigosksaa', WigosConfigResolver::conexion('A', 2)['host']);
        $this->assertSame('serverwigosksab', WigosConfigResolver::conexion('B', 2)['host']);

        $urls = WigosConfigResolver::accountInfoUrls(2);
        $this->assertSame([
            'http://serverwigosksaa:7788/WIGOS/AccountInfoJSON?trackdata=%s',
            'http://serverwigosksab:7788/WIGOS/AccountInfoJSON?trackdata=%s',
        ], $urls);
    }

    public function test_empresa_biyemas_usa_url_global(): void
    {
        config([
            'wigos.connections' => [
                'A' => ['host' => 'serverwigosa', 'port' => '1433', 'database' => 'wgdb_000'],
            ],
            'wigos.account_info_url' => 'http://serverwigosws:7788/WIGOS/AccountInfoJSON?trackdata=%s',
            'wigos.por_empresa' => [
                2 => [
                    'connections' => [
                        'A' => ['host' => 'serverwigosksaa'],
                    ],
                ],
            ],
        ]);

        $this->assertSame([
            'http://serverwigosws:7788/WIGOS/AccountInfoJSON?trackdata=%s',
        ], WigosConfigResolver::accountInfoUrls(1));
    }

    public function test_empresa_rebisco_usa_servidores_rsa(): void
    {
        config([
            'wigos.connections' => [
                'A' => ['host' => 'serverwigosa', 'port' => '1433', 'database' => 'wgdb_000'],
                'B' => ['host' => 'serverwigosb', 'port' => '1433', 'database' => 'wgdb_000'],
            ],
            'wigos.account_info_url' => 'http://serverwigosws:7788/WIGOS/AccountInfoJSON?trackdata=%s',
            'wigos.por_empresa' => [
                3 => [
                    'connections' => [
                        'A' => ['host' => 'serverwigosrsaa'],
                        'B' => ['host' => 'serverwigosrsab'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('serverwigosrsaa', WigosConfigResolver::conexion('A', 3)['host']);
        $this->assertSame('serverwigosrsab', WigosConfigResolver::conexion('B', 3)['host']);

        $urls = WigosConfigResolver::accountInfoUrls(3);
        $this->assertSame([
            'http://serverwigosrsaa:7788/WIGOS/AccountInfoJSON?trackdata=%s',
            'http://serverwigosrsab:7788/WIGOS/AccountInfoJSON?trackdata=%s',
        ], $urls);
    }
}
