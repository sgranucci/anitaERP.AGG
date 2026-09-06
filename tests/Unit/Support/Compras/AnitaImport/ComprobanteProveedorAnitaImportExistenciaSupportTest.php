<?php

namespace Tests\Unit\Support\Compras\AnitaImport;

use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportExistenciaSupport;
use PHPUnit\Framework\TestCase;

class ComprobanteProveedorAnitaImportExistenciaSupportTest extends TestCase
{
    public function test_omite_por_nro_interno(): void
    {
        $indice = [
            'por_interno' => [9001 => 44],
            'por_clave' => [],
        ];
        $hit = ComprobanteProveedorAnitaImportExistenciaSupport::buscarSoloIndice($indice, [
            'com_tipo' => 'FAC',
            'com_letra' => 'A',
            'com_sucursal' => 1,
            'com_nro' => 10,
            'com_nro_interno' => 9001,
        ], 1, 50);

        $this->assertSame(44, $hit['id']);
        $this->assertSame('anita_nro_interno', $hit['motivo']);
    }

    public function test_omite_por_empresa_proveedor_tipo_letra_sucursal_numero(): void
    {
        $claveDocumento = 'FAC|A|1|10';
        $indice = [
            'por_interno' => [],
            'por_clave' => [
                ComprobanteProveedorAnitaImportExistenciaSupport::claveFiscal(1, 50, $claveDocumento) => 77,
            ],
        ];
        $hit = ComprobanteProveedorAnitaImportExistenciaSupport::buscarSoloIndice($indice, [
            'com_tipo' => 'FAC',
            'com_letra' => 'A',
            'com_sucursal' => 1,
            'com_nro' => 10,
            'com_nro_interno' => 0,
        ], 1, 50);

        $this->assertSame(77, $hit['id']);
        $this->assertSame('clave', $hit['motivo']);
        $this->assertSame('FAC A 1-10', $hit['etiqueta']);
    }

    public function test_no_omite_si_el_proveedor_es_otro(): void
    {
        $claveDocumento = 'FAC|A|1|10';
        $indice = [
            'por_interno' => [],
            'por_clave' => [
                // Mismo documento fiscal, pero de otro proveedor.
                ComprobanteProveedorAnitaImportExistenciaSupport::claveFiscal(1, 99, $claveDocumento) => 77,
            ],
        ];

        $this->assertNull(ComprobanteProveedorAnitaImportExistenciaSupport::buscarSoloIndice($indice, [
            'com_tipo' => 'FAC',
            'com_letra' => 'A',
            'com_sucursal' => 1,
            'com_nro' => 10,
            'com_nro_interno' => 0,
        ], 1, 50));
    }

    public function test_no_omite_si_no_esta(): void
    {
        $this->assertNull(ComprobanteProveedorAnitaImportExistenciaSupport::buscarSoloIndice([
            'por_interno' => [],
            'por_clave' => [],
        ], [
            'com_tipo' => 'FAC',
            'com_letra' => 'A',
            'com_sucursal' => 1,
            'com_nro' => 99,
            'com_nro_interno' => 1,
        ], 1, 50));
    }
}
