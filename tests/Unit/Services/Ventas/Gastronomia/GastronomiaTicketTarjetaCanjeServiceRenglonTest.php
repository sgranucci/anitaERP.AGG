<?php

namespace Tests\Unit\Services\Ventas\Gastronomia;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaTicketTarjetaCanjeService;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class GastronomiaTicketTarjetaCanjeServiceRenglonTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fallback_solo_movimiento_resuelve_inroticket_distinto(): void
    {
        $filaExacta = [];
        $filaMovimiento = (object) [
            'imovimientoid' => '832459',
            'inroticket' => '572870',
            'fmontoticket' => '10000.0',
            'fmonto' => '200000.0',
            'cestado' => 'P',
            'ifecha' => (int) date('Ymd'),
            'cnrodocumento' => '23314540',
            'cnrocupon' => ' ',
        ];

        $api = Mockery::mock(ApiAnita::class);
        $api->shouldReceive('apiCall')
            ->twice()
            ->andReturn(json_encode($filaExacta), json_encode([$filaMovimiento]));

        $service = new GastronomiaTicketTarjetaCanjeService($api);

        $ref = new \ReflectionMethod($service, 'resolverTicketDesdeCodigo');
        $ref->setAccessible(true);
        [$ticketId, $numeroTicket, $fila] = $ref->invoke($service, '832459-1', 2);

        $this->assertSame(832459, $ticketId);
        $this->assertSame(572870, $numeroTicket);
        $this->assertSame('10000.0', $fila->fmontoticket);
    }

    public function test_fallback_solo_movimiento_con_inroticket_1(): void
    {
        $fila = (object) [
            'imovimientoid' => '832459',
            'inroticket' => '1',
            'fmontoticket' => '10000.0',
            'fmonto' => '200000.0',
            'cestado' => 'P',
            'ifecha' => (int) date('Ymd'),
            'cnrodocumento' => '23314540',
            'cnrocupon' => ' ',
        ];

        $api = Mockery::mock(ApiAnita::class);
        $api->shouldReceive('apiCall')
            ->twice()
            ->andReturn('[]', json_encode([$fila]));

        $service = new GastronomiaTicketTarjetaCanjeService($api);

        $ref = new \ReflectionMethod($service, 'resolverTicketDesdeCodigo');
        $ref->setAccessible(true);
        [$ticketId, $numeroTicket] = $ref->invoke($service, '832459-1', 2);

        $this->assertSame(832459, $ticketId);
        $this->assertSame(1, $numeroTicket);
    }

    public function test_manual_con_guion_832459_1_parsea_renglon(): void
    {
        $service = app(GastronomiaTicketTarjetaCanjeService::class);
        $this->assertSame([832459, 1], $service->parseCodigoBarras('832459-1'));
    }

    public function test_error_cuando_movimiento_sin_pendientes(): void
    {
        $api = Mockery::mock(ApiAnita::class);
        $api->shouldReceive('apiCall')->andReturn('[]');

        $service = new GastronomiaTicketTarjetaCanjeService($api);
        config([
            'gastronomia.ticket_tarjeta_anita_por_empresa' => [
                2 => ['servidor' => '192.168.20.100:8080', 'path_sistema' => '/usr2/biyemas'],
            ],
        ]);

        $ref = new \ReflectionMethod($service, 'resolverTicketDesdeCodigo');
        $ref->setAccessible(true);

        try {
            $ref->invoke($service, '832459-1', 2);
            $this->fail('Se esperaba InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('832459/1', $e->getMessage());
            $this->assertStringContainsString('192.168.20.100:8080', $e->getMessage());
        }
    }
}
