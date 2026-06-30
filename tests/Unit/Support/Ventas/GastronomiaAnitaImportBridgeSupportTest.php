<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\GastronomiaAnitaImport\GastronomiaAnitaImportBridgeSupport;
use Tests\TestCase;

class GastronomiaAnitaImportBridgeSupportTest extends TestCase
{
    public function test_agg_cabecera_venta_usa_bridge_central_sin_ifx_por_empresa(): void
    {
        config([
            'app.empresa' => 'AGG',
            'anita.ip' => '10.20.30.200:8080',
            'gastronomia.ticket_tarjeta_anita_por_empresa' => [
                2 => [
                    'servidor' => '192.168.20.100:8080',
                    'path_sistema' => '/usr2/biyemas',
                    'ifx_server' => 'kancadmin',
                ],
            ],
        ]);

        $payload = GastronomiaAnitaImportBridgeSupport::mergePayloadVentaCabecera(
            ['acc' => 'list', 'tabla' => 'venta'],
            2,
        );

        $this->assertSame(['acc' => 'list', 'tabla' => 'venta'], $payload);
    }

    public function test_no_agg_cabecera_venta_usa_bridge_por_empresa(): void
    {
        config([
            'app.empresa' => 'Otro',
            'stock.anita_por_empresa' => [
                2 => [
                    'servidor' => '192.168.20.100:8080',
                    'path_sistema' => '/usr2/biyemas',
                    'ifx_server' => 'kancadmin',
                ],
            ],
        ]);

        $payload = GastronomiaAnitaImportBridgeSupport::mergePayloadVentaCabecera(
            ['acc' => 'list', 'tabla' => 'venta'],
            2,
        );

        $this->assertSame('192.168.20.100:8080', $payload['servidor']);
        $this->assertSame('kancadmin', $payload['ifx_server']);
    }
}
