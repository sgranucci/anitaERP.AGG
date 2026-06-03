<?php

namespace Tests\Unit\Services\Ventas\Gastronomia;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\WaitryComandaEnvio;
use App\Services\Ventas\Gastronomia\GastronomiaBackfillWaitryEmisionOrderIdService;
use Tests\TestCase;

class GastronomiaBackfillWaitryEmisionOrderIdServiceTest extends TestCase
{
    public function test_resolver_order_id_y_origen_prioriza_cuenta_sobre_envio(): void
    {
        $service = new GastronomiaBackfillWaitryEmisionOrderIdService;

        $emision = new VentaGastronomiaEmision(['waitry_order_id' => null]);
        $emision->setRelation('cuenta', new CuentaGastronomia(['waitry_order_id' => 42]));
        $emision->setRelation('waitryComandaEnvio', new WaitryComandaEnvio(['waitry_order_id' => 99]));

        $res = $service->resolverOrderIdYOrigen($emision);

        $this->assertSame(42, $res['waitry_order_id']);
        $this->assertSame('cuenta', $res['origen']);
    }

    public function test_resolver_order_id_y_origen_usa_envio_si_cuenta_vacia(): void
    {
        $service = new GastronomiaBackfillWaitryEmisionOrderIdService;

        $emision = new VentaGastronomiaEmision(['waitry_order_id' => null]);
        $emision->setRelation('cuenta', new CuentaGastronomia(['waitry_order_id' => null]));
        $emision->setRelation('waitryComandaEnvio', new WaitryComandaEnvio(['waitry_order_id' => 77]));

        $res = $service->resolverOrderIdYOrigen($emision);

        $this->assertSame(77, $res['waitry_order_id']);
        $this->assertSame('envio', $res['origen']);
    }
}
