<?php

namespace Tests\Unit\Support\Ventas\AnitaImport;

use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportAplmovSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use PHPUnit\Framework\TestCase;

class ClienteCuentacorrienteAnitaImportAplmovSupportTest extends TestCase
{
    public function test_cob_y_nc_son_credito(): void
    {
        $this->assertTrue(ClienteCuentacorrienteAnitaImportAplmovSupport::esTipoPago('COB'));
        $this->assertTrue(ClienteCuentacorrienteAnitaImportAplmovSupport::esCredito('NCA', []));
        $this->assertTrue(ClienteCuentacorrienteAnitaImportAplmovSupport::esCredito('NCA', ['NCA' => -1]));
        $this->assertFalse(ClienteCuentacorrienteAnitaImportAplmovSupport::esCredito('FAC', ['FAC' => 1]));
    }

    public function test_par_aplica_cob_a_factura(): void
    {
        $par = ClienteCuentacorrienteAnitaImportAplmovSupport::parDesdeFila([
            'aplv_tipo' => 'FAC',
            'aplv_letra' => 'A',
            'aplv_sucursal' => 12,
            'aplv_nro' => 100,
            'aplv_fecha' => 20260115,
            'aplv_monto' => 1500.5,
            'aplv_tipo_cob' => 'COB',
            'aplv_letra_cob' => 'X',
            'aplv_sucursal_cob' => 1,
            'aplv_nro_cob' => 88,
            'aplv_nro_cuota' => 1,
        ], ['FAC' => 1]);

        $this->assertNotNull($par);
        $this->assertTrue($par['credito_es_pago']);
        $this->assertSame('COB|X|1|88', $par['credito']['clave']);
        $this->assertSame('FAC|A|12|100', $par['deuda']['clave']);
        $this->assertSame(1500.5, $par['monto']);
        $this->assertSame('2026-01-15', $par['fecha']);
    }

    public function test_fallback_ref_cuando_cob_vacio(): void
    {
        $par = ClienteCuentacorrienteAnitaImportAplmovSupport::parDesdeFila([
            'aplv_tipo' => 'FAC',
            'aplv_letra' => 'A',
            'aplv_sucursal' => 1,
            'aplv_nro' => 10,
            'aplv_fecha' => 20260201,
            'aplv_monto' => 200,
            'aplv_tipo_cob' => '',
            'aplv_letra_cob' => '',
            'aplv_sucursal_cob' => 0,
            'aplv_nro_cob' => 0,
            'aplv_ref_tipo' => 'NCA',
            'aplv_ref_letra' => 'A',
            'aplv_ref_sucursal' => 1,
            'aplv_ref_nro' => 5,
        ], ['FAC' => 1, 'NCA' => -1], true);

        $this->assertNotNull($par);
        $this->assertFalse($par['credito_es_pago']);
        $this->assertSame('NCA|A|1|5', $par['credito']['clave']);
    }

    public function test_omite_mismo_documento_y_deduplica(): void
    {
        $filas = [
            [
                'aplv_tipo' => 'FAC',
                'aplv_letra' => 'A',
                'aplv_sucursal' => 1,
                'aplv_nro' => 1,
                'aplv_fecha' => 20260101,
                'aplv_monto' => 10,
                'aplv_tipo_cob' => 'FAC',
                'aplv_letra_cob' => 'A',
                'aplv_sucursal_cob' => 1,
                'aplv_nro_cob' => 1,
            ],
            [
                'aplv_tipo' => 'FAC',
                'aplv_letra' => 'A',
                'aplv_sucursal' => 1,
                'aplv_nro' => 1,
                'aplv_fecha' => 20260101,
                'aplv_monto' => 10,
                'aplv_tipo_cob' => 'COB',
                'aplv_letra_cob' => 'X',
                'aplv_sucursal_cob' => 1,
                'aplv_nro_cob' => 2,
                'aplv_nro_cuota' => 1,
            ],
            [
                'aplv_tipo' => 'FAC',
                'aplv_letra' => 'A',
                'aplv_sucursal' => 1,
                'aplv_nro' => 1,
                'aplv_fecha' => 20260101,
                'aplv_monto' => 10,
                'aplv_tipo_cob' => 'COB',
                'aplv_letra_cob' => 'X',
                'aplv_sucursal_cob' => 1,
                'aplv_nro_cob' => 2,
                'aplv_nro_cuota' => 1,
            ],
        ];

        $pares = ClienteCuentacorrienteAnitaImportAplmovSupport::paresDesdeFilas($filas, ['FAC' => 1]);
        $this->assertCount(1, $pares);
    }

    public function test_clave_y_etiqueta(): void
    {
        $this->assertSame(
            'FAC|A|12|83016',
            ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento('FAC', 'A', 12, 83016)
        );
        $this->assertSame(
            'FAC A-00012-00083016',
            ClienteCuentacorrienteAnitaImportClaveSupport::etiquetaErp('FAC', 'A', 12, 83016)
        );
        $this->assertSame('608', ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoErp('000608'));
    }
}
