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

    public function test_expectativa_detalle_cuenta_renglones_y_kilos_del_payload(): void
    {
        $esperado = PedidoFacturaAnitaArchivosSupport::expectativaDetalleDesdePayload([
            'data_factura' => [
                ['sku' => '117', 'articulo_id' => 20, 'cantidad' => 2.1, 'pieza' => 1, 'caja' => 0.1],
                ['sku' => '150', 'articulo_id' => 133, 'cantidad' => 2.9, 'pieza' => 1, 'caja' => 0.2],
                ['sku' => '234', 'articulo_id' => 271, 'cantidad' => 1.8, 'pieza' => 2, 'caja' => 0.1],
                ['sku' => '331', 'articulo_id' => 150, 'cantidad' => 4.0, 'pieza' => 1, 'caja' => 0.2],
                ['sku' => '430', 'articulo_id' => 59, 'cantidad' => 6.2, 'pieza' => 1, 'caja' => 0.3],
                ['sku' => 'texto', 'articulo_id' => null, 'cantidad' => 0, 'pieza' => 0, 'caja' => 0],
            ],
        ]);

        $this->assertSame(5, $esperado['compaux_lineas']);
        $this->assertSame(5, $esperado['stkmov_lineas']);
        $this->assertEqualsWithDelta(17.0, $esperado['compaux_kilos'], 0.0001);
        $this->assertEqualsWithDelta(17.0, $esperado['stkmov_kilos'], 0.0001);
    }

    public function test_expectativa_detalle_omite_texto_en_stkmov_y_lineas_en_cero(): void
    {
        $esperado = PedidoFacturaAnitaArchivosSupport::expectativaDetalleDesdePayload([
            'data_factura' => [
                ['sku' => '117', 'articulo_id' => 20, 'cantidad' => 2.1, 'pieza' => 1, 'caja' => 0],
                ['sku' => 'texto', 'articulo_id' => 0, 'cantidad' => 1.0, 'pieza' => 0, 'caja' => 0],
                ['sku' => '999', 'articulo_id' => 1, 'cantidad' => 0, 'pieza' => 0, 'caja' => 0],
            ],
        ]);

        $this->assertSame(2, $esperado['compaux_lineas']);
        $this->assertEqualsWithDelta(3.1, $esperado['compaux_kilos'], 0.0001);
        $this->assertSame(1, $esperado['stkmov_lineas']);
        $this->assertEqualsWithDelta(2.1, $esperado['stkmov_kilos'], 0.0001);
    }

    public function test_lectura_detalle_corrupta_detecta_csv_ajeno_del_bridge(): void
    {
        $esperado = ['lineas' => 5, 'kilos' => 17.0];
        $skus = ['117', '150', '234', '331', '430'];

        $this->assertTrue(PedidoFacturaAnitaArchivosSupport::lecturaDetalleCorrupta(
            ['lineas' => 28, 'kilos' => 0.0, 'skus' => ['999']],
            $esperado,
            $skus,
        ));
        $this->assertTrue(PedidoFacturaAnitaArchivosSupport::lecturaDetalleCorrupta(
            ['lineas' => 5, 'kilos' => 0.0, 'skus' => $skus],
            $esperado,
            $skus,
        ));
        $this->assertFalse(PedidoFacturaAnitaArchivosSupport::lecturaDetalleCorrupta(
            ['lineas' => 2, 'kilos' => 5.0, 'skus' => ['117', '150']],
            $esperado,
            $skus,
        ));
        $this->assertFalse(PedidoFacturaAnitaArchivosSupport::lecturaDetalleCorrupta(
            ['lineas' => 5, 'kilos' => 17.0, 'skus' => $skus],
            $esperado,
            $skus,
        ));
    }
}
