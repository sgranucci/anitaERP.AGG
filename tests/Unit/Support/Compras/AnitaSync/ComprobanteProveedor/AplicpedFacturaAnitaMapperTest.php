<?php

namespace Tests\Unit\Support\Compras\AnitaSync\ComprobanteProveedor;

use App\Support\Compras\AnitaSync\ComprobanteProveedor\AplicpedFacturaAnitaMapper;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use PHPUnit\Framework\TestCase;

class AplicpedFacturaAnitaMapperTest extends TestCase
{
    public function test_linea_anticipada_usa_menos_uno_como_a_compprov(): void
    {
        $linea = AplicpedFacturaAnitaMapper::lineaAnticipadaAnita();

        $this->assertSame(-1, $linea['orden_com']);
        $this->assertSame(-1, $linea['penvp_orden']);
        $this->assertSame('OC ANTICIPADA', $linea['sku']);
        $this->assertSame(0.0, $linea['cantidad']);
        $this->assertTrue($linea['anticipada']);
        $this->assertSame(
            RecepcionProveedorAnitaEscrituraSupport::APLICPED_ORDEN_ANTICIPADA,
            $linea['orden_com']
        );
    }
}
