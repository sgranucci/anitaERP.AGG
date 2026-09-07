<?php

namespace Tests\Unit\Services\Configuracion;

use App\Services\Configuracion\ArbolaprobacionService;
use PHPUnit\Framework\TestCase;

class AnulaMovimientosArbolSpPagadaTest extends TestCase
{
    public function test_ids_vacios_o_invalidos_no_actualizan(): void
    {
        $svc = $this->getMockBuilder(ArbolaprobacionService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame(0, $svc->anulaMovimientosArbolPendientesAbiertosSolicitudpagoIds([]));
        $this->assertSame(0, $svc->anulaMovimientosArbolPendientesAbiertosSolicitudpagoIds([0, -1]));
        $this->assertSame(0, $svc->anulaMovimientosArbolPendientesAbiertosSolicitudpago(0));
    }
}
