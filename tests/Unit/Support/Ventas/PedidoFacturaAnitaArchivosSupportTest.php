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
        config()->set('facturacion.PUNTOVENTA_DIVISION_REPARTO_101_ID', 9);

        $this->assertTrue(PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision(5));
        $this->assertTrue(PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision(6));
        $this->assertTrue(PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision(9));
        $this->assertFalse(PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision(8));
        $this->assertFalse(PedidoFacturaAnitaArchivosSupport::esPuntoVentaDivision(0));
    }

    public function test_path_villafranca_solo_para_sucursal_de_division(): void
    {
        $division = ['00015', '00002', '00001'];

        $this->assertSame(
            PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA,
            PedidoFacturaAnitaArchivosSupport::resolverPathSistema(
                PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA,
                '15',
                $division,
            ),
        );
        $this->assertNull(PedidoFacturaAnitaArchivosSupport::resolverPathSistema(
            PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA,
            '8',
            $division,
        ));
        $this->assertNull(PedidoFacturaAnitaArchivosSupport::resolverPathSistema(
            PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA,
            '00010',
            $division,
        ));
        $this->assertNull(PedidoFacturaAnitaArchivosSupport::resolverPathSistema(null, '8', $division));
        $this->assertSame(
            PedidoFacturaAnitaArchivosSupport::PATH_VILLAFRANCA,
            PedidoFacturaAnitaArchivosSupport::resolverPathSistema(null, '00001', $division),
        );
    }
}
