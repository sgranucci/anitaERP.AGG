<?php

namespace Tests\Unit\Support\Ventas\Gastronomia;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\WaitryComandaEnvio;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use Tests\TestCase;

class VentaGastronomiaEmisionWaitrySupportTest extends TestCase
{
    public function test_resolver_order_id_prioriza_emision_cuenta_y_envio(): void
    {
        $emision = new VentaGastronomiaEmision(['venta_id' => 10, 'waitry_order_id' => 100]);
        $this->assertSame(100, VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision));

        $emision = new VentaGastronomiaEmision(['venta_id' => 11, 'waitry_order_id' => null]);
        $cuenta = new CuentaGastronomia(['waitry_order_id' => 200]);
        $emision->setRelation('cuenta', $cuenta);
        $this->assertSame(200, VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision));

        $emision = new VentaGastronomiaEmision(['venta_id' => 12, 'waitry_order_id' => null]);
        $emision->setRelation('cuenta', new CuentaGastronomia(['waitry_order_id' => null]));
        $envio = new WaitryComandaEnvio(['waitry_order_id' => 300]);
        $emision->setRelation('waitryComandaEnvio', $envio);
        $this->assertSame(300, VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision));
    }

    public function test_venta_id_con_waitry_order_id_cero_retorna_null(): void
    {
        $this->assertNull(VentaGastronomiaEmisionWaitrySupport::ventaIdConWaitryOrderId(0, 1));
    }
}
