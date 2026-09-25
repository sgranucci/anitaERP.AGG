<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\RemitoPrecioEditableSupport;
use Tests\TestCase;

class RemitoPrecioEditableSupportTest extends TestCase
{
    public function test_codigo_es_remitos_surmar_acepta_variantes_del_6(): void
    {
        $this->assertTrue(RemitoPrecioEditableSupport::codigoEsRemitosSurmar('00006'));
        $this->assertTrue(RemitoPrecioEditableSupport::codigoEsRemitosSurmar('6'));
        $this->assertTrue(RemitoPrecioEditableSupport::codigoEsRemitosSurmar('0006'));
        $this->assertFalse(RemitoPrecioEditableSupport::codigoEsRemitosSurmar('00001'));
        $this->assertFalse(RemitoPrecioEditableSupport::codigoEsRemitosSurmar('00007'));
        $this->assertFalse(RemitoPrecioEditableSupport::codigoEsRemitosSurmar(''));
    }

    public function test_fuera_de_elbierzo_no_restringe_por_punto_venta(): void
    {
        config()->set('app.empresa', 'AGG');

        $this->assertTrue(RemitoPrecioEditableSupport::puntoventaPermiteEdicion(null));
        $this->assertTrue(RemitoPrecioEditableSupport::puntoventaPermiteEdicion(1));
    }

    public function test_en_elbierzo_sin_punto_venta_no_permite(): void
    {
        config()->set('app.empresa', 'EL BIERZO');

        $this->assertFalse(RemitoPrecioEditableSupport::puntoventaPermiteEdicion(null));
        $this->assertFalse(RemitoPrecioEditableSupport::puntoventaPermiteEdicion(0));
    }
}
