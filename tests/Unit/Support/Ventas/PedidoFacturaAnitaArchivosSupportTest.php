<?php

namespace Tests\Unit\Support\Ventas;

use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use Tests\TestCase;

class PedidoFacturaAnitaArchivosSupportTest extends TestCase
{
    public function test_es_punto_venta_division_por_ids_de_config(): void
    {
        config()->set('facturacion.PUNTOVENTA_DIVISION_ID', 5);
        config()->set('facturacion.PUNTOVENTA_DIVISION_LOCAL_ID', 6);

        $this->assertTrue(PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision(5));
        $this->assertTrue(PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision(6));
        $this->assertFalse(PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision(8));
        $this->assertFalse(PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision(0));
    }
}
