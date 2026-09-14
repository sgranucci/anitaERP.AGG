<?php

namespace Tests\Unit\Support\Stock\AnitaSync;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\AnitaSync\Puntoventa\PuntoventaFieldMapper;
use Tests\TestCase;

class PuntoventaFieldMapperFerliTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.empresa' => EntornoEmpresaSupport::FERLI]);
    }

    public function test_ferli_suc_fiscal_e_es_cae_no_exportacion(): void
    {
        $row = (object) ['suc_fiscal' => 'E'];

        $this->assertSame('C', PuntoventaFieldMapper::mapModoFacturacion($row));
        $this->assertSame('wsfev1', PuntoventaFieldMapper::mapWebservice($row));
    }

    public function test_ferli_suc_fiscal_x_es_exportacion_wsfex(): void
    {
        $row = (object) ['suc_fiscal' => 'X'];

        $this->assertSame('E', PuntoventaFieldMapper::mapModoFacturacion($row));
        $this->assertSame('wsfex_v1', PuntoventaFieldMapper::mapWebservice($row));
    }

    public function test_ferli_no_mapea_pathafip_desde_leyenda2(): void
    {
        $row = (object) [
            'suc_numero' => 12,
            'suc_empresa' => 'Factura electronica FCA',
            'suc_fiscal' => 'E',
            'suc_leyenda1' => 'x',
            'suc_leyenda2' => 'cuentascorrientes@ferli.com.ar',
            'suc_nroemp' => 1,
            'suc_cuit' => '',
            'suc_direccion' => '-',
            'suc_cod_postal' => '',
            'suc_telefono' => '',
        ];

        $mapped = PuntoventaFieldMapper::mapAll($row);

        $this->assertSame('C', $mapped['modofacturacion']);
        $this->assertNull($mapped['pathafip']);
        $this->assertSame('wsfev1', $mapped['webservice']);
    }
}
