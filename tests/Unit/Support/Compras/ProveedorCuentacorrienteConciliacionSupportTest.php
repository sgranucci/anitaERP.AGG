<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ProveedorCuentacorrienteConciliacionSupport;
use Tests\TestCase;

class ProveedorCuentacorrienteConciliacionSupportTest extends TestCase
{
    public function test_dc_misma_moneda_es_leftover_ap(): void
    {
        $dc = ProveedorCuentacorrienteConciliacionSupport::dcEsperada(
            ['total' => -1000, 'moneda_id' => 2, 'cotizacion' => 1200],
            ['total' => 1000, 'moneda_id' => 2, 'cotizacion' => 1100]
        );

        $this->assertSame(100000.0, $dc);
    }

    public function test_dc_cruzada_es_economica(): void
    {
        $dc = ProveedorCuentacorrienteConciliacionSupport::dcEsperada(
            ['total' => -1000, 'moneda_id' => 2, 'cotizacion' => 1200],
            ['total' => 1100000, 'moneda_id' => 1, 'cotizacion' => 1]
        );

        $this->assertSame(-100000.0, $dc);
    }

    public function test_misma_moneda_liquidada_a_cotizacion_de_factura_no_tiene_dc(): void
    {
        $dc = ProveedorCuentacorrienteConciliacionSupport::dcEsperada(
            ['total' => -5126, 'moneda_id' => 2, 'cotizacion' => 1513, 'cotizacion_liquidacion' => 1513],
            ['total' => 5126, 'moneda_id' => 2, 'cotizacion' => 1535, 'cotizacion_liquidacion' => 1513]
        );

        $this->assertSame(0.0, $dc);
    }

    public function test_desvia_respeta_tolerancia(): void
    {
        $this->assertFalse(ProveedorCuentacorrienteConciliacionSupport::desvia(10, 10.02, 0.05));
        $this->assertTrue(ProveedorCuentacorrienteConciliacionSupport::desvia(10, 10.2, 0.05));
    }
}
