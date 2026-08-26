<?php

namespace Tests\Unit\Support\Compras;

use App\Support\Compras\ComprobanteProveedorAnitaCompraExistenciaSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAnitaCompraExistenciaSupportTest extends TestCase
{
    public function test_tipo_arca_factura_nota_debito_y_credito_a(): void
    {
        $this->assertSame('001', ComprobanteProveedorAnitaCompraExistenciaSupport::tipoArca('01', 'A', 'FAC'));
        $this->assertSame('002', ComprobanteProveedorAnitaCompraExistenciaSupport::tipoArca('02', 'A', 'NDB'));
        $this->assertSame('003', ComprobanteProveedorAnitaCompraExistenciaSupport::tipoArca('03', 'A', 'NCD'));
        $this->assertSame('006', ComprobanteProveedorAnitaCompraExistenciaSupport::tipoArca('01', 'B', 'FAC'));
    }

    public function test_tipo_arca_normaliza_codigo_con_ceros(): void
    {
        $this->assertSame('001', ComprobanteProveedorAnitaCompraExistenciaSupport::tipoArca('001', 'A'));
        $this->assertSame('001', ComprobanteProveedorAnitaCompraExistenciaSupport::tipoArca('1', 'A'));
        $this->assertSame('', ComprobanteProveedorAnitaCompraExistenciaSupport::tipoArca('', 'A', 'FAC'));
    }

    public function test_detecta_duplicado_por_misma_abreviatura(): void
    {
        $hit = ComprobanteProveedorAnitaCompraExistenciaSupport::seleccionarFilaDuplicada(
            [
                ['com_tipo' => 'FAC', 'com_letra' => 'A', 'com_sucursal' => 1, 'com_nro' => 123, 'com_nro_interno' => 50],
            ],
            '001',
            'A',
            ['FAC' => '01'],
            null,
            'FAC',
        );

        $this->assertNotNull($hit);
        $this->assertSame(50, (int) $hit['com_nro_interno']);
    }

    public function test_detecta_duplicado_por_tipo_arca_aunque_cambie_la_abreviatura(): void
    {
        $mapa = [
            'FAC' => '01',
            'FNB' => '01',
            'NCD' => '03',
        ];
        $filas = [
            ['com_tipo' => 'NCD', 'com_letra' => 'A', 'com_sucursal' => 1, 'com_nro' => 123, 'com_nro_interno' => 10],
            ['com_tipo' => 'FNB', 'com_letra' => 'A', 'com_sucursal' => 1, 'com_nro' => 123, 'com_nro_interno' => 20],
        ];

        $hit = ComprobanteProveedorAnitaCompraExistenciaSupport::seleccionarFilaDuplicada(
            $filas,
            '001',
            'A',
            $mapa,
            null,
            'FAC',
        );

        $this->assertNotNull($hit);
        $this->assertSame('FNB', $hit['com_tipo']);
        $this->assertSame(20, (int) $hit['com_nro_interno']);
    }

    public function test_no_confunde_factura_con_nota_de_credito_mismo_numero(): void
    {
        $hit = ComprobanteProveedorAnitaCompraExistenciaSupport::seleccionarFilaDuplicada(
            [
                ['com_tipo' => 'NCD', 'com_letra' => 'A', 'com_sucursal' => 1, 'com_nro' => 123, 'com_nro_interno' => 10],
            ],
            '001',
            'A',
            ['FAC' => '01', 'NCD' => '03'],
            null,
            'FAC',
        );

        $this->assertNull($hit);
    }

    public function test_excluye_el_nro_interno_propio(): void
    {
        $hit = ComprobanteProveedorAnitaCompraExistenciaSupport::seleccionarFilaDuplicada(
            [
                ['com_tipo' => 'FAC', 'com_letra' => 'A', 'com_sucursal' => 1, 'com_nro' => 123, 'com_nro_interno' => 88],
            ],
            '001',
            'A',
            ['FAC' => '01'],
            88,
            'FAC',
        );

        $this->assertNull($hit);
    }

    public function test_no_confunde_factura_a_con_factura_b(): void
    {
        $hit = ComprobanteProveedorAnitaCompraExistenciaSupport::seleccionarFilaDuplicada(
            [
                ['com_tipo' => 'FAC', 'com_letra' => 'B', 'com_sucursal' => 1, 'com_nro' => 123, 'com_nro_interno' => 9],
            ],
            '001',
            'A',
            ['FAC' => '01'],
            null,
            'FAC',
        );

        $this->assertNull($hit);
    }

    public function test_acepta_filas_objeto_como_las_devuelve_el_bridge(): void
    {
        $fila = (object) [
            'com_tipo' => 'FNB',
            'com_letra' => 'A',
            'com_sucursal' => 7,
            'com_nro' => 856,
            'com_nro_interno' => 338213,
        ];

        $hit = ComprobanteProveedorAnitaCompraExistenciaSupport::seleccionarFilaDuplicada(
            [$fila],
            '001',
            'A',
            ['FAC' => '01', 'FNB' => '01'],
            null,
            'FAC',
        );

        $this->assertNotNull($hit);
        $this->assertSame('FNB', $hit['com_tipo']);
        $this->assertSame(338213, (int) $hit['com_nro_interno']);
    }

    public function test_mensaje_incluye_tipo_arca_y_clave_fiscal(): void
    {
        $mensaje = ComprobanteProveedorAnitaCompraExistenciaSupport::mensajeDuplicado(
            [
                'com_tipo' => 'FNB',
                'com_letra' => 'A',
                'com_sucursal' => 1,
                'com_nro' => 123,
                'com_nro_interno' => 456,
                'com_fecha' => '20260801',
                'com_cuit_prov' => '20123456789',
                'com_nombre_prov' => 'Proveedor SA',
            ],
            'A',
            1,
            123,
            '001',
        );

        $this->assertStringContainsString('tipo ARCA 001', $mensaje);
        $this->assertStringContainsString('FNB A 0001-00000123', $mensaje);
        $this->assertStringContainsString('nro. interno Anita 456', $mensaje);
        $this->assertStringContainsString('proveedor + tipo ARCA + letra + sucursal + número', $mensaje);
        $this->assertStringContainsString('No se puede repetir', $mensaje);
    }
}
