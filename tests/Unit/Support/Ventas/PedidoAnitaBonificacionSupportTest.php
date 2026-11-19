<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\PedidoAnitaBonificacionSupport;
use Tests\TestCase;

class PedidoAnitaBonificacionSupportTest extends TestCase
{
    public function test_precio_para_import_fuerza_cero_si_es_bonif(): void
    {
        $this->assertSame(0.0, PedidoAnitaBonificacionSupport::precioParaImport(true, 1234.5));
        $this->assertSame(50.0, PedidoAnitaBonificacionSupport::precioParaImport(false, 50.0));
    }

    public function test_fusiona_mismo_articulo_con_ratio_10x1(): void
    {
        $dtoId = PedidoAnitaBonificacionSupport::descuentoventaIdSiFusiona(
            true,
            15,
            15,
            false,
            10.0,
            1.0
        );

        $this->assertSame(2, $dtoId);
    }

    public function test_otro_articulo_no_fusiona_queda_regalo(): void
    {
        $dtoId = PedidoAnitaBonificacionSupport::descuentoventaIdSiFusiona(
            true,
            99,
            15,
            false,
            10.0,
            1.0
        );

        $this->assertNull($dtoId);
    }

    public function test_ratio_raro_no_fusiona_queda_regalo(): void
    {
        $dtoId = PedidoAnitaBonificacionSupport::descuentoventaIdSiFusiona(
            true,
            15,
            15,
            false,
            10.0,
            7.0
        );

        $this->assertNull($dtoId);
    }

    public function test_detecta_linea_bonificacion_anita(): void
    {
        $this->assertTrue(PedidoAnitaBonificacionSupport::esLineaBonificacionAnita(
            (object) ['penv_fl_bonif' => '1', 'penv_precio' => 0, 'penv_pieza' => 1]
        ));
        $this->assertFalse(PedidoAnitaBonificacionSupport::esLineaBonificacionAnita(
            (object) ['penv_fl_bonif' => '0', 'penv_precio' => 100, 'penv_pieza' => 1]
        ));
    }
}
