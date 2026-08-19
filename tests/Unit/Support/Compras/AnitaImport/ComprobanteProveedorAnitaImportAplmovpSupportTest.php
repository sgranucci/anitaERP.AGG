<?php

namespace Tests\Unit\Support\Compras\AnitaImport;

use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportAplmovpSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAnitaImportAplmovpSupportTest extends TestCase
{
    public function test_op_y_nc_son_credito(): void
    {
        $this->assertTrue(ComprobanteProveedorAnitaImportAplmovpSupport::esTipoPago('OPP'));
        $this->assertTrue(ComprobanteProveedorAnitaImportAplmovpSupport::esCredito('NCA', []));
        $this->assertTrue(ComprobanteProveedorAnitaImportAplmovpSupport::esCredito('NCA', ['NCA' => 'R']));
        $this->assertFalse(ComprobanteProveedorAnitaImportAplmovpSupport::esCredito('FAC', ['FAC' => 'S']));
    }

    public function test_par_aplica_op_a_factura(): void
    {
        $par = ComprobanteProveedorAnitaImportAplmovpSupport::parDesdeFila([
            'aplvp_proveedor' => '3593',
            'aplvp_tipo' => 'FAC',
            'aplvp_letra' => 'A',
            'aplvp_sucursal' => 1,
            'aplvp_nro' => 100,
            'aplvp_fecha' => 20260115,
            'aplvp_monto' => 1500.5,
            'aplvp_tipo_cob' => 'OPP',
            'aplvp_letra_cob' => 'X',
            'aplvp_sucursal_cob' => 1,
            'aplvp_nro_cob' => 88,
        ], ['FAC' => 'S']);

        $this->assertNotNull($par);
        $this->assertTrue($par['credito_es_pago']);
        $this->assertSame('003593|OPP|X|1|88', $par['credito']['clave']);
        $this->assertSame('003593|FAC|A|1|100', $par['deuda']['clave']);
        $this->assertSame(1500.5, $par['monto']);
        $this->assertSame('2026-01-15', $par['fecha']);
    }

    public function test_par_aplica_nc_a_factura(): void
    {
        $par = ComprobanteProveedorAnitaImportAplmovpSupport::parDesdeFila([
            'aplvp_proveedor' => '3593',
            'aplvp_tipo' => 'FAC',
            'aplvp_letra' => 'A',
            'aplvp_sucursal' => 1,
            'aplvp_nro' => 100,
            'aplvp_fecha' => 20260201,
            'aplvp_monto' => 200,
            'aplvp_tipo_cob' => 'NCA',
            'aplvp_letra_cob' => 'A',
            'aplvp_sucursal_cob' => 1,
            'aplvp_nro_cob' => 12,
        ], ['FAC' => 'S', 'NCA' => 'R']);

        $this->assertNotNull($par);
        $this->assertFalse($par['credito_es_pago']);
        $this->assertSame('003593|NCA|A|1|12', $par['credito']['clave']);
        $this->assertSame('003593|FAC|A|1|100', $par['deuda']['clave']);
    }

    public function test_omite_monto_cero_y_deduplica(): void
    {
        $filas = [
            [
                'aplvp_proveedor' => '3593',
                'aplvp_tipo' => 'FAC',
                'aplvp_letra' => 'A',
                'aplvp_sucursal' => 1,
                'aplvp_nro' => 1,
                'aplvp_fecha' => 20260101,
                'aplvp_monto' => 0,
                'aplvp_tipo_cob' => 'OPP',
                'aplvp_letra_cob' => 'X',
                'aplvp_sucursal_cob' => 1,
                'aplvp_nro_cob' => 2,
            ],
            [
                'aplvp_proveedor' => '3593',
                'aplvp_tipo' => 'FAC',
                'aplvp_letra' => 'A',
                'aplvp_sucursal' => 1,
                'aplvp_nro' => 1,
                'aplvp_fecha' => 20260101,
                'aplvp_monto' => 10,
                'aplvp_tipo_cob' => 'OPP',
                'aplvp_letra_cob' => 'X',
                'aplvp_sucursal_cob' => 1,
                'aplvp_nro_cob' => 2,
            ],
            [
                'aplvp_proveedor' => '3593',
                'aplvp_tipo' => 'FAC',
                'aplvp_letra' => 'A',
                'aplvp_sucursal' => 1,
                'aplvp_nro' => 1,
                'aplvp_fecha' => 20260101,
                'aplvp_monto' => 10,
                'aplvp_tipo_cob' => 'OPP',
                'aplvp_letra_cob' => 'X',
                'aplvp_sucursal_cob' => 1,
                'aplvp_nro_cob' => 2,
            ],
        ];

        $pares = ComprobanteProveedorAnitaImportAplmovpSupport::paresDesdeFilas($filas, ['FAC' => 'S']);
        $this->assertCount(1, $pares);
        $this->assertSame(10.0, $pares[0]['monto']);
    }
}
